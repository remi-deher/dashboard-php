<?php
// Fichier: /src/Controller/DashboardController.php

class DashboardController
{
    private PDO $pdo;

    // Le contrôleur a maintenant besoin de la connexion PDO pour récupérer les paramètres
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        // On récupère le paramètre du fond d'écran depuis la nouvelle table 'settings'
        $stmt = $this->pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'background'");
        // fetchColumn() est parfait pour récupérer une seule valeur
        $background = $stmt->fetchColumn();

        // On charge la vue en lui passant la variable $background
        require_once __DIR__ . '/../../templates/dashboard.php';
    }
}
