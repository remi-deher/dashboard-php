<?php
// Fichier: /public/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement des dépendances et de la configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/db_connection.php'; // Pour l'injection de $pdo

use App\Controller\AdminController;
use App\Controller\ApiController;
use App\Controller\DashboardController;
use App\Model\ServiceModel;
use App\Router;

// 2. Initialisation des services
$serviceModel = new ServiceModel($pdo);

// 3. Création du routeur
$router = new Router();

// 4. Définition des routes
// Routes principales
$router->add('GET', '/', [new DashboardController($pdo), 'index']);
$router->add('GET', '/admin', [new AdminController($serviceModel, $pdo), 'showAdmin']);
$router->add('GET', '/admin/service/edit/{id}', [new AdminController($serviceModel, $pdo), 'showAdmin']);
$router->add('GET', '/admin/dashboard/edit/{id}', [new AdminController($serviceModel, $pdo), 'showAdmin']);

// Routes de l'API (anciennement dans api/index.php)
$router->add('GET', '/api/dashboards', [new ApiController($serviceModel), 'getDashboards']);
$router->add('GET', '/api/services', [new ApiController($serviceModel), 'getServices']);
$router->add('GET', '/api/status/check', [new ApiController($serviceModel), 'checkStatus']);

// Routes pour les actions des formulaires de l'administration
$router->add('POST', '/admin/service/add', [new AdminController($serviceModel, $pdo), 'addService']);
$router->add('POST', '/admin/service/update/{id}', [new AdminController($serviceModel, $pdo), 'updateService']);
$router->add('POST', '/admin/service/delete/{id}', [new AdminController($serviceModel, $pdo), 'deleteService']);
$router->add('POST', '/admin/dashboard/add', [new AdminController($serviceModel, $pdo), 'addDashboard']);
$router->add('POST', '/admin/dashboard/update/{id}', [new AdminController($serviceModel, $pdo), 'updateDashboard']);
$router->add('POST', '/admin/dashboard/delete/{id}', [new AdminController($serviceModel, $pdo), 'deleteDashboard']);
$router->add('POST', '/admin/settings/save', [new AdminController($serviceModel, $pdo), 'saveSettings']);


// 5. Lancement du routeur
$router->run();
