<?php
// Fichier: /public/admin.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement de l'autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Connexion à la BDD
require_once __DIR__ . '/../src/db_connection.php';

// 3. Initialisation des objets avec leur namespace
$serviceModel = new App\Model\ServiceModel($pdo);
$controller = new App\Controller\AdminController($serviceModel, $pdo);

// 4. Appel de la méthode qui gère toute la logique de la page
$controller->handleRequest();
