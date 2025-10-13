<?php
// Fichier: /public/api/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement de l'autoloader de Composer
require_once __DIR__ . '/../../vendor/autoload.php';

// 2. Connexion à la BDD
require_once __DIR__ . '/../../src/db_connection.php';

if (!isset($pdo)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'La connexion PDO n\'a pas pu être établie.']);
    exit;
}

// 3. Initialisation des objets avec leur namespace
$serviceModel = new App\Model\ServiceModel($pdo);
$controller = new App\Controller\ApiController($serviceModel);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getDashboards':
        $controller->getDashboards();
        break;
    case 'getServices':
        $controller->getServices();
        break;
    case 'checkStatus':
        $controller->checkStatus();
        break;
    default:
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint non trouvé']);
}
