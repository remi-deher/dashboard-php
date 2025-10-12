<?php
// Fichier: /public/index.php
// Il agit maintenant comme un "Front Controller" ou un routeur pour la page d'accueil.

// 1. Chargement du contrôleur nécessaire.
require_once __DIR__ . '/../src/Controller/DashboardController.php';

// 2. Initialisation du contrôleur.
$controller = new DashboardController();

// 3. Appel de la méthode qui va afficher la page.
$controller->index();
