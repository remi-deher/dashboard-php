<?php
// Fichier: /public/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement des dépendances et de la configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/db_connection.php'; // Pour l'injection de $pdo

use App\Controller\ApiController;
use App\Controller\DashboardController;
use App\Model\ServiceModel;
use App\Router;

// 2. Initialisation des services
$serviceModel = new ServiceModel($pdo);
$dashboardController = new DashboardController($serviceModel, $pdo);

// 3. Création du routeur
$router = new Router();

// 4. Définition des routes
// Routes principales pour affichage (y compris les formulaires d'édition)
$router->add('GET', '/', [$dashboardController, 'index']);
$router->add('GET', '/service/edit/{id}', [$dashboardController, 'showAdminForService']);
$router->add('GET', '/dashboard/edit/{id}', [$dashboardController, 'showAdminForDashboard']);


// Routes de l'API (pour le front-end JS)
$router->add('GET', '/api/dashboards', [new ApiController($serviceModel), 'getDashboards']);
$router->add('GET', '/api/services', [new ApiController($serviceModel), 'getServices']);
$router->add('GET', '/api/status/check', [new ApiController($serviceModel), 'checkStatus']);

// Routes pour les actions des formulaires de gestion (POST)
$router->add('POST', '/service/add', [$dashboardController, 'addService']);
$router->add('POST', '/service/update/{id}', [$dashboardController, 'updateService']);
$router->add('POST', '/service/delete/{id}', [$dashboardController, 'deleteService']);
$router->add('POST', '/dashboard/add', [$dashboardController, 'addDashboard']);
$router->add('POST', '/dashboard/update/{id}', [$dashboardController, 'updateDashboard']);
$router->add('POST', '/dashboard/delete/{id}', [$dashboardController, 'deleteDashboard']);
$router->add('POST', '/settings/save', [$dashboardController, 'saveSettings']);


// 5. Lancement du routeur
$router->run();
