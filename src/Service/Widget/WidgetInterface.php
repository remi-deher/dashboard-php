<?php
// Fichier: /src/Service/Widget/WidgetInterface.php

namespace App\Service\Widget;

/**
 * Interface pour tous les services de widget.
 * Chaque widget doit pouvoir récupérer ses données en se basant
 * sur la configuration du service (ex: l'URL) et les paramètres globaux.
 */
interface WidgetInterface
{
    /**
     * Récupère les données à afficher pour le widget.
     *
     * @param array $service Les informations du service depuis la BDD (contient 'url', 'nom', etc.)
     * @return array Les données formatées pour l'API (ou ['error' => 'message'])
     */
    public function getWidgetData(array $service): array;
}
