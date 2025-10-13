<?php
// Fichier: /public/api/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/db_connection.php';
require_once __DIR__ . '/../../src/Controller/ApiController.php';
require_once __DIR__ . '/../../src/Model/ServiceModel.php';

if (!isset($pdo)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'La connexion PDO n\'a pas pu être établie.']);
    exit;
}

$serviceModel = new ServiceModel($pdo);
$controller = new ApiController($serviceModel);

$action = $_GET['action'] ?? '';

switch ($action) {
    // NOUVELLE ROUTE
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
