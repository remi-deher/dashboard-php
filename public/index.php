<?php
// Fichier: /public/index.php

// 1. Chargement de l'autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Connexion à la BDD (ce fichier ne change pas)
require_once __DIR__ . '/../src/db_connection.php';

// 3. Initialisation du contrôleur en utilisant son namespace
$controller = new App\Controller\DashboardController($pdo);

// 4. Appel de la méthode qui va afficher la page.
$controller->index();
