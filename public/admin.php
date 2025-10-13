<?php
// Fichier: /public/admin.php

// Gardez ceci activé pour le débogage !
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Chargement de TOUTES les dépendances nécessaires
require_once __DIR__ . '/../src/db_connection.php';
require_once __DIR__ . '/../src/Model/ServiceModel.php';
require_once __DIR__ . '/../src/Controller/AdminController.php';

// 2. Initialisation des objets
$serviceModel = new ServiceModel($pdo);

// ==========================================================
// CORRECTION CLÉ : On passe maintenant les DEUX arguments requis
// au constructeur du AdminController : $serviceModel ET $pdo.
// ==========================================================
$controller = new AdminController($serviceModel, $pdo);

// 3. Appel de la méthode qui gère toute la logique de la page
// Nous avons aussi renommé la méthode principale en 'handleRequest'
$controller->handleRequest();
