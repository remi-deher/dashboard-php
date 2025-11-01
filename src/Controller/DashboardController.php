<?php
// Fichier: /src/Controller/DashboardController.php

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel; // AJOUTÉ
use App\Model\SettingsModel;  // AJOUTÉ

class DashboardController
{
    private ServiceModel $serviceModel;
    private DashboardModel $dashboardModel; // AJOUTÉ
    private SettingsModel $settingsModel;   // AJOUTÉ

    public function __construct(
        ServiceModel $serviceModel, 
        DashboardModel $dashboardModel, 
        SettingsModel $settingsModel
    ) {
        $this->serviceModel = $serviceModel;
        $this->dashboardModel = $dashboardModel; // AJOUTÉ
        $this->settingsModel = $settingsModel;   // AJOUTÉ
    }

    // Affiche la page principale, charge les données nécessaires pour la vue et la modale
    public function index(?int $edit_service_id = null, ?int $edit_dashboard_id = null): void
    {
        // UTILISATION DES MODÈLES
        $all_services = $this->serviceModel->getAll();
        $all_dashboards = $this->dashboardModel->getAllOrdered();
        $settings_raw = $this->settingsModel->getAllAsKeyPair();

        $settings['background_color'] = $settings_raw['background_color'] ?? '';
        $settings['background_image'] = $settings_raw['background_image'] ?? '';
        $settings['theme'] = $settings_raw['theme'] ?? 'default-dark'; 
        
        // --- AJOUT DE CES LIGNES ---
        $settings['xen_orchestra_host'] = $settings_raw['xen_orchestra_host'] ?? '';
        // Récupère le token pour le champ 'value' du formulaire
        $settings['xen_orchestra_token'] = $settings_raw['xen_orchestra_token'] ?? '';
        // --- FIN DE L'AJOUT ---

        // UTILISATION DES MODÈLES
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
