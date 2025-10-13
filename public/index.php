<?php
// Fichier: /public/index.php

// 1. Chargement des dépendances OBLIGATOIRES
// On a besoin de la connexion BDD pour le contrôleur
require_once __DIR__ . '/../src/db_connection.php';
require_once __DIR__ . '/../src/Controller/DashboardController.php';

// 2. Initialisation du contrôleur en lui passant la variable $pdo
// La variable $pdo vient de db_connection.php
$controller = new DashboardController($pdo);

// 3. Appel de la méthode qui va afficher la page.
$controller->index();
