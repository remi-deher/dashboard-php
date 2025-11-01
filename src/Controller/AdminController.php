<?php
// Fichier: /src/Controller/AdminController.php

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel;
use App\Model\SettingsModel;
use App\Service\MediaManager;
use App\Service\MicrosoftGraphService;
class AdminController
{
    private ServiceModel $serviceModel;
    private DashboardModel $dashboardModel;
    private SettingsModel $settingsModel;
    private MediaManager $mediaManager;
    private MicrosoftGraphService $microsoftGraphService;

    public function __construct(
        ServiceModel $serviceModel,
        DashboardModel $dashboardModel,
        SettingsModel $settingsModel,
        MediaManager $mediaManager,
        MicrosoftGraphService $microsoftGraphService
    ) {
        $this->serviceModel = $serviceModel;
        $this->dashboardModel = $dashboardModel;
        $this->settingsModel = $settingsModel;
        $this->mediaManager = $mediaManager;
        $this->microsoftGraphService = $microsoftGraphService;
    }


    public function addService(): void {
        $data = $_POST; 
        $data['icone_url'] = $this->mediaManager->handleUpload('icone_upload');
        if (empty($data['icone_url']) && empty($data['icone'])) {
            $data['icone_url'] = $this->mediaManager->fetchAndCacheFavicon($data['url']);
        }
        $data['size_class'] = $data['size_class'] ?? 'size-medium';
        $this->serviceModel->create($data);
        header('Location: /');
        exit;
    }

    public function updateService(int $id): void {
        $data = $_POST;
        $current = $this->serviceModel->getById($id); 
        $data['icone_url'] = $this->mediaManager->handleUpload('icone_upload', $current['icone_url'] ?? null, isset($_POST['remove_icone']));
        if (!isset($_POST['remove_icone']) && empty($_FILES['icone_upload']['name']) && empty($data['icone'])) {
            $newFaviconIfNeeded = (isset($current['icone_url']) && strpos($current['icone_url'], '/assets/favicons/') === 0 && $current['url'] === $data['url'])
                ? $current['icone_url'] 
                : $this->mediaManager->fetchAndCacheFavicon($data['url']);
            $data['icone_url'] = $data['icone_url'] ?: $newFaviconIfNeeded;
        }
        $data['size_class'] = $data['size_class'] ?? $current['size_class'] ?? 'size-medium';
        $this->serviceModel->update($id, $data);
        header('Location: /');
        exit;
    }

    public function deleteService(int $id): void {
        $service = $this->serviceModel->getById($id);
        if ($service && !empty($service['icone_url'])) {
            $this->mediaManager->handleUpload('', $service['icone_url'], true);
        }
        $this->serviceModel->delete($id);
        header('Location: /');
        exit;
    }

    public function addDashboard(): void {
        $icone_url = $this->mediaManager->handleUpload('icone_upload');
        $ordre = (int)($_POST['ordre_affichage'] ?? 9999);
        $this->dashboardModel->create(
            $_POST['nom'],
            $_POST['icone'] ?? 'fas fa-th-large',
            $icone_url,
            $ordre
        );
        header('Location: /');
        exit;
    }

    public function updateDashboard(int $id): void {
        $current = $this->dashboardModel->getById($id);
        if (!$current) { header('Location: /'); exit; }
        $icone_url = $this->mediaManager->handleUpload('icone_upload', $current['icone_url'] ?? null, isset($_POST['remove_icone']));
        $ordre = isset($_POST['ordre_affichage']) ? (int)$_POST['ordre_affichage'] : $current['ordre_affichage'];
        $this->dashboardModel->update(
            $id,
            $_POST['nom'],
            $_POST['icone'] ?? 'fas fa-th-large',
            $icone_url,
            $ordre
        );
        header('Location: /');
        exit;
    }

    public function deleteDashboard(int $id): void {
        $dashboard = $this->dashboardModel->getById($id);
        if (!$dashboard) { header('Location: /'); exit; }
        if (!empty($dashboard['icone_url'])) {
            $this->mediaManager->handleUpload('', $dashboard['icone_url'], true);
        }
        $fallbackId = $this->dashboardModel->getFallbackDashboardId($id);
        if ($fallbackId) {
            $this->serviceModel->reassignServices($id, $fallbackId);
        }
        $this->dashboardModel->delete($id);
        header('Location: /');
        exit;
    }

    public function saveSettings(): void {
        // Paramètres généraux (si le formulaire "Général" est soumis)
        if (isset($_POST['theme'])) {
            $this->settingsModel->save('background_color', $_POST['background_color'] ?? '');
            $current_image = $this->settingsModel->get('background_image');
            $remove_bg_image = isset($_POST['remove_background_image']) && $_POST['remove_background_image'] === 'on';
            $image_url = $this->mediaManager->handleUpload('background_image', $current_image ?: null, $remove_bg_image);
            $this->settingsModel->save('background_image', $image_url);
            
            $themeName = basename($_POST['theme']); 
            $themePath = dirname(__DIR__, 2) . '/public/assets/themes/' . $themeName;
            if (is_dir($themePath)) {
                $this->settingsModel->save('theme', $themeName);
            }
        }
        
        // Paramètres des Widgets (si le formulaire "Widgets" est soumis)
        if (isset($_POST['xen_orchestra_host'])) {
            // Xen Orchestra
            $this->settingsModel->save('xen_orchestra_host', $_POST['xen_orchestra_host']);
            if (!empty($_POST['xen_orchestra_token'])) {
                $this->settingsModel->save('xen_orchestra_token', $_POST['xen_orchestra_token']);
            }

            // Proxmox
            $this->settingsModel->save('proxmox_token_id', $_POST['proxmox_token_id'] ?? '');
            if (!empty($_POST['proxmox_token_secret'])) {
                $this->settingsModel->save('proxmox_token_secret', $_POST['proxmox_token_secret']);
            }
            
            // Portainer
            if (!empty($_POST['portainer_api_key'])) {
                $this->settingsModel->save('portainer_api_key', $_POST['portainer_api_key']);
            }

            // Microsoft 365 (AJOUTÉ)
            $this->settingsModel->save('m365_client_id', $_POST['m365_client_id'] ?? '');
            $this->settingsModel->save('m365_tenant_id', $_POST['m365_tenant_id'] ?? 'common');
            if (!empty($_POST['m365_client_secret'])) {
                $this->settingsModel->save('m365_client_secret', $_POST['m365_client_secret']);
            }
        }

        header('Location: /');
        exit;
    }


    /**
     * Redirige l'utilisateur vers la page de consentement Microsoft.
     */
    public function connectM365(): void
    {
        $authUrl = $this->microsoftGraphService->getAuthUrl();
        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * Gère le retour de Microsoft après le consentement.
     */
    public function callbackM365(): void
    {
        $code = $_GET['code'] ?? null;
        if (!$code) {
            die("Erreur: Code d'autorisation manquant.");
        }

        $success = $this->microsoftGraphService->handleCallback($code);

        if ($success) {
            // Redirige vers la page principale, la modale s'ouvrira sur le bon onglet
            header('Location: /?auth=m365_success');
            exit;
        } else {
            die("Erreur lors de l'échange du code. Vérifiez vos logs et vos Client Secrets.");
        }
    }
}
