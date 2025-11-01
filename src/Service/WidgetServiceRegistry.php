<?php
// Fichier: /src/Service/WidgetServiceRegistry.php

namespace App\Service;

use App\Service\Widget\WidgetInterface;

class WidgetServiceRegistry
{
    /** @var WidgetInterface[] */
    private array $services = [];

    /**
     * Enregistre un service de widget avec son type.
     *
     * @param string $type (ex: 'xen_orchestra', 'glances')
     * @param WidgetInterface $service L'instance du service
     */
    public function register(string $type, WidgetInterface $service): void
    {
        $this->services[$type] = $service;
    }

    /**
     * Récupère un service de widget par son type.
     *
     * @param string $type
     * @return WidgetInterface|null
     */
    public function getService(string $type): ?WidgetInterface
    {
        return $this->services[$type] ?? null;
    }
}
