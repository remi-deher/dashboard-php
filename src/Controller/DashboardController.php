<?php
// Fichier: /src/Controller/DashboardController.php

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel; 
use App\Model\SettingsModel;  

class DashboardController
{
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
            
            'xen_orchestra_host' => $settings_raw['xen_orchestra_host'] ?? '',
            'xen_orchestra_token' => $settings_raw['xen_orchestra_token'] ?? '',
            'proxmox_token_id' => $settings_raw['proxmox_token_id'] ?? '',
            'proxmox_token_secret' => $settings_raw['proxmox_token_secret'] ?? '',
            'portainer_api_key' => $settings_raw['portainer_api_key'] ?? '',
            
            'm365_client_id' => $settings_raw['m365_client_id'] ?? '',
            'm365_client_secret' => $settings_raw['m365_client_secret'] ?? '',
            'm365_tenant_id' => $settings_raw['m365_tenant_id'] ?? 'common',
            'm365_refresh_token' => $settings_raw['m365_refresh_token'] ?? null,
        ];
        
        $settings['open_modal_to_widgets'] = isset($_GET['auth']) && $_GET['auth'] === 'm365_success';

        $edit_service = $edit_service_id ? $this->serviceModel->getById($edit_service_id) : null;
        $edit_dashboard = $edit_dashboard_id ? $this->dashboardModel->getById($edit_dashboard_id) : null;

        // --- FIX CORRIGÉ POUR REVERSE PROXY ---
        $is_https = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || // <-- TYPO CORRIGÉE
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        );
        $protocol = $is_https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base_url = $protocol . "://" . $host;
        // --- FIN DU FIX ---

        require __DIR__ . '/../../templates/dashboard.php';
    }

    public function showAdminForService(int $id): void
    {
        $this->index($id, null);
    }

    public function showAdminForDashboard(int $id): void
    {
        $this->index(null, $id);
    }
}
