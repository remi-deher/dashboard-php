<?php
// Fichier: /src/Controller/AdminController.php

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel;
use App\Model\SettingsModel;
use App\Service\MediaManager; // AJOUTÉ

class AdminController
{
    private ServiceModel $serviceModel;
    private DashboardModel $dashboardModel;
    private SettingsModel $settingsModel;
    private MediaManager $mediaManager; // AJOUTÉ

    // CONSTRUCTEUR MIS À JOUR
    public function __construct(
        ServiceModel $serviceModel,
        DashboardModel $dashboardModel,
        SettingsModel $settingsModel,
        MediaManager $mediaManager // AJOUTÉ
    ) {
        $this->serviceModel = $serviceModel;
        $this->dashboardModel = $dashboardModel;
        $this->settingsModel = $settingsModel;
        $this->mediaManager = $mediaManager; // AJOUTÉ
    }

    // --- Méthodes pour les actions POST (Formulaires) ---

    public function addService(): void {
        $data = $_POST; 
        
        // UTILISATION DU SERVICE
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
        
        // UTILISATION DU SERVICE
        $data['icone_url'] = $this->mediaManager->handleUpload('icone_upload', $current['icone_url'] ?? null, isset($_POST['remove_icone']));
        if (!isset($_POST['remove_icone']) && empty($_FILES['icone_upload']['name']) && empty($data['icone'])) {
            $newFaviconIfNeeded = (isset($current['icone_url']) && strpos($current['icone_url'], '/assets/favicons/') === 0 && $current['url'] === $data['url'])
                ? $current['icone_url'] 
                : $this->mediaManager->fetchAndCacheFavicon($data['url']); // Appel au service
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
            // UTILISATION DU SERVICE
            $this->mediaManager->handleUpload('', $service['icone_url'], true);
        }
        
        $this->serviceModel->delete($id);
        header('Location: /');
        exit;
    }

    public function addDashboard(): void {
        // UTILISATION DU SERVICE
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

        // UTILISATION DU SERVICE
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
            // UTILISATION DU SERVICE
            $this->mediaManager->handleUpload('', $dashboard['icone_url'], true);
        }

        $fallbackId = $this->dashboardModel->getFallbackDashboardId($id);

        if ($fallbackId) {
            // UTILISATION DU SERVICE MODEL (plus de $pdo ici !)
            $this->serviceModel->reassignServices($id, $fallbackId);
        }

        $this->dashboardModel->delete($id);

        header('Location: /');
        exit;
    }

    public function saveSettings(): void {
        $this->settingsModel->save('background_color', $_POST['background_color'] ?? '');

        // Gestion de l'image de fond
        $current_image = $this->settingsModel->get('background_image');
        // UTILISATION DU SERVICE
        $image_url = $this->mediaManager->handleUpload('background_image', $current_image ?: null, isset($_POST['remove_background_image']));
        $this->settingsModel->save('background_image', $image_url);

        // Sauvegarde du thème sélectionné
        if (isset($_POST['theme'])) {
            $themeName = basename($_POST['theme']); 
            $themePath = dirname(__DIR__, 2) . '/public/assets/themes/' . $themeName;
            if (is_dir($themePath)) {
                $this->settingsModel->save('theme', $themeName);
            }
        }

        header('Location: /');
        exit;
    }
}
