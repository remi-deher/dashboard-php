<?php
// Fichier: /public/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement
require_once __DIR__ . '/../vendor/autoload.php';
$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    die("Erreur: Le fichier de configuration 'config/config.php' est manquant.");
}
$config = require $configPath; 
require_once __DIR__ . '/../src/db_connection.php';
if (!isset($pdo)) {
     die("Erreur critique: La connexion PDO n'a pas pu être établie.");
}

// 2. Namespaces
use App\Controller\ApiController;
use App\Controller\DashboardController;
use App\Controller\AdminController;
use App\Controller\StreamController; // AJOUTÉ
use App\Model\ServiceModel;
use App\Model\DashboardModel;
use App\Model\SettingsModel;
use App\Service\MediaManager;
use App\Service\WidgetServiceRegistry;
use App\Service\XenOrchestraService;
use App\Service\Widget\GlancesService;
use App\Service\MicrosoftGraphService;
use App\Router;
use Predis\Client as RedisClient; // AJOUTÉ

// 3. Initialisation des services
try {
    $projectRoot = dirname(__DIR__);

    // AJOUT : Connexion Redis
    $redisClient = new RedisClient('tcp://127.0.0.1:6379');

    // Modèles
    $serviceModel = new ServiceModel($pdo);
    $dashboardModel = new DashboardModel($pdo);
    $settingsModel = new SettingsModel($pdo);
    
    // Paramètres BDD
    $db_settings = $settingsModel->getAllAsKeyPair();
    
    // Services
    $mediaManager = new MediaManager($projectRoot);

    // --- Architecture Widget ---
    $widgetRegistry = new WidgetServiceRegistry();

    // Xen Orchestra
    $xenOrchestraService = new XenOrchestraService(
        $db_settings['xen_orchestra_host'] ?? null,
        $db_settings['xen_orchestra_token'] ?? null
    );
    $widgetRegistry->register('xen_orchestra', $xenOrchestraService);

    // Glances
    $glancesService = new GlancesService(); 
    $widgetRegistry->register('glances', $glancesService);

    // Microsoft Graph
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    );
    $protocol = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = $protocol . "://" . $host;
    $m365_redirect_uri = $base_url . '/auth/m365/callback';
    
    $microsoftGraphService = new MicrosoftGraphService(
        $settingsModel,
        $m365_redirect_uri,
        [
            'm365_client_id' => $db_settings['m365_client_id'] ?? '',
            'm365_client_secret' => $db_settings['m365_client_secret'] ?? '',
            'm365_tenant_id' => $db_settings['m365_tenant_id'] ?? 'common',
            'm365_refresh_token' => $db_settings['m365_refresh_token'] ?? null
        ]
    );
    $widgetRegistry->register('m365_calendar', $microsoftGraphService);
    $widgetRegistry->register('m365_mail_stats', $microsoftGraphService);


    // Contrôleurs
    // MODIFIÉ : Injection de $redisClient
    $apiController = new ApiController(
        $serviceModel, 
        $dashboardModel, 
        $pdo, 
        $widgetRegistry, 
        $microsoftGraphService,
        $redisClient // AJOUTÉ
    ); 
    
    $dashboardController = new DashboardController($serviceModel, $dashboardModel, $settingsModel); 
    $adminController = new AdminController($serviceModel, $dashboardModel, $settingsModel, $mediaManager, $microsoftGraphService);

    // AJOUTÉ : Nouveau contrôleur de stream
    $streamController = new StreamController($redisClient);

} catch (\Exception $e) {
    die("Erreur lors de l'initialisation des services: " . $e->getMessage());
}

// 4. Création du routeur
$router = new Router();

// 5. Définition des routes
$router->add('GET', '/', [$dashboardController, 'index']); 
$router->add('GET', '/service/edit/{id}', [$dashboardController, 'showAdminForService']);
$router->add('GET', '/dashboard/edit/{id}', [$dashboardController, 'showAdminForDashboard']);
$router->add('GET', '/api/dashboards', [$apiController, 'getDashboards']); 
$router->add('GET', '/api/services', [$apiController, 'getServices']); 
$router->add('GET', '/api/status/check', [$apiController, 'checkStatus']); 
$router->add('GET', '/api/widget/data/{id}', [$apiController, 'getWidgetData']);

// --- NOUVELLE ROUTE API ---
$router->add('GET', '/api/m365/targets', [$apiController, 'getM365Targets']);

// --- NOUVELLE ROUTE STREAM ---
$router->add('GET', '/api/stream', [$streamController, 'handleStream']);

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
$router->add('GET', '/auth/m365/connect', [$adminController, 'connectM365']);
$router->add('GET', '/auth/m365/callback', [$adminController, 'callbackM365']);

// 6. Lancement du routeur
try {
    $router->run();
} catch (\Exception $e) {
    http_response_code(500);
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "Une erreur interne inattendue est survenue."; 
}
