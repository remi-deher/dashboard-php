<?php
// Fichier: /src/Controller/DashboardController.php

namespace App\Controller;

use PDO;

class DashboardController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        $stmt = $this->pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'background'");
        $background = $stmt->fetchColumn();

        require_once __DIR__ . '/../../templates/dashboard.php';
    }
}
