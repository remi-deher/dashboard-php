<?php
// Fichier: /public/index.php

// Afficher les erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement de l'autoloader et de la connexion PDO
require_once __DIR__ . '/../vendor/autoload.php';
// Gérer l'absence du fichier de configuration proprement
$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    die("Erreur: Le fichier de configuration 'config/config.php' est manquant. Veuillez copier 'config/config.php.EXAMPLE' et le configurer.");
}
require_once __DIR__ . '/../src/db_connection.php'; // Injecte $pdo

// Vérifier si la connexion PDO a réussi
if (!isset($pdo)) {
     die("Erreur critique: La connexion PDO n'a pas pu être établie. Vérifiez votre configuration et l'état du serveur de base de données.");
}


// 2. Utilisation des namespaces pour les classes
use App\Controller\ApiController;
use App\Controller\DashboardController;
use App\Model\ServiceModel;
use App\Router;

// 3. Initialisation des services (injection de dépendances)
try {
    $serviceModel = new ServiceModel($pdo);
    $apiController = new ApiController($serviceModel); // ApiController a besoin de ServiceModel
    $dashboardController = new DashboardController($serviceModel, $pdo); // DashboardController a besoin des deux
} catch (\Exception $e) {
    // Gérer les erreurs potentielles lors de l'instanciation (ex: problème de BDD)
    die("Erreur lors de l'initialisation des services: " . $e->getMessage());
}

// 4. Création du routeur
$router = new Router();

// 5. Définition des routes

// --- Routes principales pour l'affichage HTML ---
$router->add('GET', '/', [$dashboardController, 'index']); // Page d'accueil
// Routes pour pré-ouvrir la modale en mode édition
$router->add('GET', '/service/edit/{id}', [$dashboardController, 'showAdminForService']);
$router->add('GET', '/dashboard/edit/{id}', [$dashboardController, 'showAdminForDashboard']);


// --- Routes de l'API (appelées par JavaScript via fetch) ---
$router->add('GET', '/api/dashboards', [$apiController, 'getDashboards']); // Récupérer la liste des dashboards
$router->add('GET', '/api/services', [$apiController, 'getServices']); // Récupérer les services d'un dashboard (avec ?dashboard_id=X)
$router->add('GET', '/api/status/check', [$apiController, 'checkStatus']); // Vérifier le statut d'une URL (avec ?url=Y)

// Routes POST pour la sauvegarde de la disposition (depuis SortableJS/InteractJS)
$router->add('POST', '/api/services/layout/save', [$dashboardController, 'saveLayout']); // Sauvegarder l'ordre des services DANS un dashboard
$router->add('POST', '/api/dashboards/layout/save', [$dashboardController, 'saveDashboardLayout']); // Sauvegarder l'ordre des onglets dashboards

// *** NOUVELLE ROUTE *** : Sauvegarder la taille d'un service (depuis InteractJS)
$router->add('POST', '/api/service/resize/{id}', [$apiController, 'saveServiceSize']);

// Route POST pour déplacer un service vers un autre dashboard (depuis SortableJS onAdd)
$router->add('POST', '/api/service/move/{id}/{dashboardId}', [$dashboardController, 'moveService']);


// --- Routes pour les actions des formulaires de gestion (méthode POST depuis HTML) ---
// Services
$router->add('POST', '/service/add', [$dashboardController, 'addService']);
$router->add('POST', '/service/update/{id}', [$dashboardController, 'updateService']);
$router->add('POST', '/service/delete/{id}', [$dashboardController, 'deleteService']);
// Dashboards
$router->add('POST', '/dashboard/add', [$dashboardController, 'addDashboard']);
$router->add('POST', '/dashboard/update/{id}', [$dashboardController, 'updateDashboard']);
$router->add('POST', '/dashboard/delete/{id}', [$dashboardController, 'deleteDashboard']);
// Paramètres globaux
$router->add('POST', '/settings/save', [$dashboardController, 'saveSettings']);


// 6. Lancement du routeur
try {
    $router->run();
} catch (\Exception $e) {
    // Attrape les erreurs non gérées par le routeur lui-même (ex: erreur fatale dans un contrôleur)
    http_response_code(500);
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "Une erreur interne inattendue est survenue."; // Message générique pour l'utilisateur
}
