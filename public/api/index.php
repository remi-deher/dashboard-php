<?php
// Fichier: /public/api/index.php

require_once __DIR__ . '/../../src/db_connection.php';
require_once __DIR__ . '/../../src/Controller/ApiController.php';

$serviceModel = new ServiceModel($pdo);
$controller = new ApiController($serviceModel);

// On regarde une action dans l'URL, ex: /api/index.php?action=getServices
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getServices':
        $controller->getServices();
        break;
    case 'checkStatus':
        $controller->checkStatus();
        break;
    default:
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
