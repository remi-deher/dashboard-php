<?php
// Fichier: /src/Controller/DashboardController.php

class DashboardController
{
    /**
     * Affiche la page principale du dashboard.
     * Son seul rôle est de charger la vue correspondante.
     */
    public function index(): void
    {
        // On charge le fichier de template qui contient le HTML.
        require_once __DIR__ . '/../../templates/dashboard.php';
    }
}
