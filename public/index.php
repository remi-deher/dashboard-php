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
// CHARGER LA CONFIGURATION pour qu'elle soit disponible
$config = require $configPath; 

require_once __DIR__ . '/../src/db_connection.php'; // Injecte $pdo

if (!isset($pdo)) {
     die("Erreur critique: La connexion PDO n'a pas pu être établie.");
}


// 2. Utilisation des namespaces pour les classes
use App\Controller\ApiController;
use App\Controller\DashboardController;
use App\Controller\AdminController;
use App\Model\ServiceModel;
use App\Model\DashboardModel;
use App\Model\SettingsModel;
use App\Service\MediaManager;
use App\Service\XenOrchestraService; // AJOUTÉ
use App\Router;

// 3. Initialisation des services (injection de dépendances)
try {
    $projectRoot = dirname(__DIR__);

    // Modèles
    $serviceModel = new ServiceModel($pdo);
    $dashboardModel = new DashboardModel($pdo);
    $settingsModel = new SettingsModel($pdo);
    
    // Services
    $mediaManager = new MediaManager($projectRoot);
    // AJOUTÉ : Instancier le service XOA avec la config
    $xenOrchestraService = new XenOrchestraService(
        $config['api_keys']['xen_orchestra_host'] ?? null,
        $config['api_keys']['xen_orchestra_token'] ?? null
    );

    // Contrôleurs
    // AJOUTÉ : Injecter le service XOA dans ApiController
    $apiController = new ApiController($serviceModel, $dashboardModel, $pdo, $xenOrchestraService); 
    $dashboardController = new DashboardController($serviceModel, $dashboardModel, $settingsModel); 
    $adminController = new AdminController($serviceModel, $dashboardModel, $settingsModel, $mediaManager);

} catch (\Exception $e) {
    die("Erreur lors de l'initialisation des services: " . $e->getMessage());
}

// 4. Création du routeur
$router = new Router();

// 5. Définition des routes
$router->add('GET', '/', [$dashboardController, 'index']); 
$router->add('GET', '/service/edit/{id}', [$dashboardController, 'showAdminForService']);
$router->add('GET', '/dashboard/edit/{id}', [$dashboardController, 'showAdminForDashboard']);

// API
$router->add('GET', '/api/dashboards', [$apiController, 'getDashboards']); 
$router->add('GET', '/api/services', [$apiController, 'getServices']); 
$router->add('GET', '/api/status/check', [$apiController, 'checkStatus']); 

// API - NOUVELLE ROUTE WIDGET
$router->add('GET', '/api/widget/data/{id}', [$apiController, 'getWidgetData']);

// API (POST)
$router->add('POST', '/api/services/layout/save', [$apiController, 'saveLayout']); 
$router->add('POST', '/api/dashboards/layout/save', [$apiController, 'saveDashboardLayout']); 
$router->add('POST', '/api/service/resize/{id}', [$apiController, 'saveServiceSize']);
$router->add('POST', '/api/service/move/{id}/{dashboardId}', [$apiController, 'moveService']);

// Formulaires (POST)
$router->add('POST', '/service/add', [$adminController, 'addService']);
$router->add('POST', '/service/update/{id}', [$adminController, 'updateService']);
$router->add('POST', '/service/delete/{id}', [$adminController, 'deleteService']);
$router->add('POST', '/dashboard/add', [$adminController, 'addDashboard']);
$router->add('POST', '/dashboard/update/{id}', [$adminController, 'updateDashboard']);
$router->add('POST', '/dashboard/delete/{id}', [$adminController, 'deleteDashboard']);
$router->add('POST', '/settings/save', [$adminController, 'saveSettings']);


// 6. Lancement du routeur
try {
    $router->run();
} catch (\Exception $e) {
    http_response_code(500);
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "Une erreur interne inattendue est survenue."; 
}
