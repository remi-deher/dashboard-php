<?php
// Fichier: /public/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement de l'autoloader et de la connexion PDO
require_once __DIR__ . '/../vendor/autoload.php';
$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    die("Erreur: Le fichier de configuration 'config/config.php' est manquant. Veuillez copier 'config/config.php.EXAMPLE' et le configurer.");
}
$config = require $configPath; 
require_once __DIR__ . '/../src/db_connection.php';
if (!isset($pdo)) {
     die("Erreur critique: La connexion PDO n'a pas pu être établie.");
}

// 2. Utilisation des namespaces
use App\Controller\ApiController;
use App\Controller\DashboardController;
use App\Controller\AdminController;
use App\Model\ServiceModel;
use App\Model\DashboardModel;
use App\Model\SettingsModel;
use App\Service\MediaManager;
use App\Service\WidgetServiceRegistry; // AJOUTÉ
use App\Service\XenOrchestraService;
use App\Service\Widget\GlancesService; // AJOUTÉ
// (Ajoutez ici les futurs services : ProxmoxService, PortainerService, etc.)
use App\Router;

// 3. Initialisation des services
try {
    $projectRoot = dirname(__DIR__);

    // Modèles
    $serviceModel = new ServiceModel($pdo);
    $dashboardModel = new DashboardModel($pdo);
    $settingsModel = new SettingsModel($pdo);
    
    // Récupérer les paramètres de la BDD
    $db_settings = $settingsModel->getAllAsKeyPair();
    
    // Services
    $mediaManager = new MediaManager($projectRoot);

    // --- NOUVELLE ARCHITECTURE WIDGET ---
    
    // 3a. Initialiser le registre
    $widgetRegistry = new WidgetServiceRegistry();

    // 3b. Initialiser et enregistrer chaque service de widget
    
    // Xen Orchestra
    $xenOrchestraService = new XenOrchestraService(
        $db_settings['xen_orchestra_host'] ?? null,
        $db_settings['xen_orchestra_token'] ?? null
    );
    $widgetRegistry->register('xen_orchestra', $xenOrchestraService);

    // Glances (AJOUTÉ)
    // Glances n'a pas besoin de config globale, il utilise l'URL du service
    $glancesService = new GlancesService(); 
    $widgetRegistry->register('glances', $glancesService);

    /*
    // Squelette pour Proxmox (quand vous le créerez)
    $proxmoxService = new ProxmoxService(
        $db_settings['proxmox_token_id'] ?? null,
        $db_settings['proxmox_token_secret'] ?? null
    );
    $widgetRegistry->register('proxmox', $proxmoxService);
    
    // Squelette pour Portainer (quand vous le créerez)
    $portainerService = new PortainerService(
        $db_settings['portainer_api_key'] ?? null
    );
    $widgetRegistry->register('portainer', $portainerService);
    */

    // 3c. Contrôleurs
    
    // MODIFIÉ : On injecte le registre, et non plus $xenOrchestraService
    $apiController = new ApiController($serviceModel, $dashboardModel, $pdo, $widgetRegistry); 
    
    $dashboardController = new DashboardController($serviceModel, $dashboardModel, $settingsModel); 
    $adminController = new AdminController($serviceModel, $dashboardModel, $settingsModel, $mediaManager);

} catch (\Exception $e) {
    die("Erreur lors de l'initialisation des services: " . $e->getMessage());
}

// 4. Création du routeur (inchangé)
$router = new Router();

// 5. Définition des routes (inchangé)
$router->add('GET', '/', [$dashboardController, 'index']); 
$router->add('GET', '/service/edit/{id}', [$dashboardController, 'showAdminForService']);
$router->add('GET', '/dashboard/edit/{id}', [$dashboardController, 'showAdminForDashboard']);
$router->add('GET', '/api/dashboards', [$apiController, 'getDashboards']); 
$router->add('GET', '/api/services', [$apiController, 'getServices']); 
$router->add('GET', '/api/status/check', [$apiController, 'checkStatus']); 
$router->add('GET', '/api/widget/data/{id}', [$apiController, 'getWidgetData']);
$router->add('POST', '/api/services/layout/save', [$apiController, 'saveLayout']); 
$router->add('POST', '/api/dashboards/layout/save', [$apiController, 'saveDashboardLayout']); 
$router->add('POST', '/api/service/resize/{id}', [$apiController, 'saveServiceSize']);
$router->add('POST', '/api/service/move/{id}/{dashboardId}', [$apiController, 'moveService']);
$router->add('POST', '/service/add', [$adminController, 'addService']);
$router->add('POST', '/service/update/{id}', [$adminController, 'updateService']);
$router->add('POST', '/service/delete/{id}', [$adminController, 'deleteService']);
$router->add('POST', '/dashboard/add', [$adminController, 'addDashboard']);
$router->add('POST', '/dashboard/update/{id}', [$adminController, 'updateDashboard']);
$router->add('POST', '/dashboard/delete/{id}', [$adminController, 'deleteDashboard']);
$router->add('POST', '/settings/save', [$adminController, 'saveSettings']);

// 6. Lancement du routeur (inchangé)
try {
    $router->run();
} catch (\Exception $e) {
    http_response_code(500);
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "Une erreur interne inattendue est survenue."; 
}
