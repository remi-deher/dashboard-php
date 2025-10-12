<?php
// Fichier: /public/admin.php (le nouveau point d'entrée)

// 1. Chargement des dépendances
require_once __DIR__ . '/../src/db_connection.php';
require_once __DIR__ . '/../src/Controller/AdminController.php';

// 2. Initialisation des objets (le Modèle puis le Contrôleur)
$serviceModel = new ServiceModel($pdo);
$controller = new AdminController($serviceModel);

// 3. Routage : décider quelle méthode du contrôleur appeler
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'update') {
        $controller->save();
    } elseif ($action === 'delete') {
        $controller->delete();
    } else {
        // Action POST non reconnue, on affiche la page par défaut
        $controller->index();
    }
} else {
    // Si ce n'est pas une requête POST, on affiche la page par défaut
    $controller->index();
}
