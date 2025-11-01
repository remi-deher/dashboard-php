<?php
// Fichier: /src/Controller/DashboardController.php

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel; 
use App\Model\SettingsModel;  

class DashboardController
{
    // --- FIX : AJOUT DES DÉCLARATIONS DE PROPRIÉTÉS ---
    private ServiceModel $serviceModel;
    private DashboardModel $dashboardModel; 
    private SettingsModel $settingsModel;   

    public function __construct(
        ServiceModel $serviceModel, 
        DashboardModel $dashboardModel, 
        SettingsModel $settingsModel
    ) {
        $this->serviceModel = $serviceModel;
        $this->dashboardModel = $dashboardModel;
        $this->settingsModel = $settingsModel;
    }

    // Affiche la page principale, charge les données nécessaires pour la vue et la modale
    public function index(?int $edit_service_id = null, ?int $edit_dashboard_id = null): void
    {
        $all_services = $this->serviceModel->getAll();
        $all_dashboards = $this->dashboardModel->getAllOrdered();
        $settings_raw = $this->settingsModel->getAllAsKeyPair();

        // Initialise tous les paramètres possibles
        $settings = [
            'background_color' => $settings_raw['background_color'] ?? '',
            'background_image' => $settings_raw['background_image'] ?? '',
            'theme' => $settings_raw['theme'] ?? 'default-dark',
            
            // Widgets
            'xen_orchestra_host' => $settings_raw['xen_orchestra_host'] ?? '',
            'xen_orchestra_token' => $settings_raw['xen_orchestra_token'] ?? '',
            'proxmox_token_id' => $settings_raw['proxmox_token_id'] ?? '',
            'proxmox_token_secret' => $settings_raw['proxmox_token_secret'] ?? '',
            'portainer_api_key' => $settings_raw['portainer_api_key'] ?? '',
        ];

        $edit_service = $edit_service_id ? $this->serviceModel->getById($edit_service_id) : null;
        $edit_dashboard = $edit_dashboard_id ? $this->dashboardModel->getById($edit_dashboard_id) : null;

        // Inclure le template principal
        require __DIR__ . '/../../templates/dashboard.php';
    }

    // Méthodes pour afficher la modale ouverte sur le bon onglet via une URL spécifique
    public function showAdminForService(int $id): void
    {
        $this->index($id, null);
    }

    public function showAdminForDashboard(int $id): void
    {
        $this->index(null, $id);
    }
    
}
