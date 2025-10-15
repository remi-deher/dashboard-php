<?php
// Fichier: /src/Controller/DashboardController.php

namespace App\Controller;

use App\Model\ServiceModel;
use PDO;

class DashboardController
{
    private ServiceModel $serviceModel;
    private PDO $pdo;

    public function __construct(ServiceModel $serviceModel, PDO $pdo)
    {
        $this->serviceModel = $serviceModel;
        $this->pdo = $pdo;
    }

    public function index(?int $edit_service_id = null, ?int $edit_dashboard_id = null): void
    {
        $all_services = $this->serviceModel->getAll();
        $all_dashboards = $this->pdo->query('SELECT * FROM dashboards ORDER BY ordre_affichage, nom')->fetchAll();
        
        // On récupère toutes les clés de la table settings
        $settings_raw = $this->pdo->query('SELECT * FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings['background_color'] = $settings_raw['background_color'] ?? '';
        $settings['background_image'] = $settings_raw['background_image'] ?? '';
        
        $edit_service = $edit_service_id ? $this->serviceModel->getById($edit_service_id) : null;
        $edit_dashboard = $edit_dashboard_id ? $this->getDashboardById($edit_dashboard_id) : null;
        
        require __DIR__ . '/../../templates/dashboard.php';
    }
    
    // Méthodes pour les routes d'édition
    public function showAdminForService(int $id): void
    {
        $this->index($id, null);
    }

    public function showAdminForDashboard(int $id): void
    {
        $this->index(null, $id);
    }
    
    // --- Méthodes pour les actions POST ---

    public function addService(): void {
        $data = $_POST;
        $data['icone_url'] = $this->handleUpload('icone_upload');
        $this->serviceModel->create($data);
        header('Location: /');
        exit;
    }

    public function updateService(int $id): void {
        $data = $_POST;
        $current = $this->serviceModel->getById($id);
        $data['icone_url'] = $this->handleUpload('icone_upload', $current['icone_url'] ?? null, isset($_POST['remove_icone']));
        $this->serviceModel->update($id, $data);
        header('Location: /');
        exit;
    }

    public function deleteService(int $id): void {
        // Avant de supprimer le service, on supprime son icône personnalisée si elle existe
        $service = $this->serviceModel->getById($id);
        if (!empty($service['icone_url'])) {
            $this->handleUpload('', $service['icone_url'], true);
        }
        $this->serviceModel->delete($id);
        header('Location: /');
        exit;
    }
    
    public function addDashboard(): void {
        $icone_url = $this->handleUpload('icone_upload');
        $stmt = $this->pdo->prepare("INSERT INTO dashboards (nom, icone, icone_url, ordre_affichage) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['nom'], $_POST['icone'], $icone_url, $_POST['ordre_affichage']]);
        header('Location: /');
        exit;
    }

    public function updateDashboard(int $id): void {
        $current = $this->getDashboardById($id);
        $icone_url = $this->handleUpload('icone_upload', $current['icone_url'] ?? null, isset($_POST['remove_icone']));
        
        $stmt = $this->pdo->prepare("UPDATE dashboards SET nom = ?, icone = ?, icone_url = ?, ordre_affichage = ? WHERE id = ?");
        $stmt->execute([$_POST['nom'], $_POST['icone'], $icone_url, $_POST['ordre_affichage'], $id]);
        header('Location: /');
        exit;
    }

    public function deleteDashboard(int $id): void {
        // Avant de supprimer le dashboard, on supprime son icône personnalisée si elle existe
        $dashboard = $this->getDashboardById($id);
        if (!empty($dashboard['icone_url'])) {
            $this->handleUpload('', $dashboard['icone_url'], true);
        }

        $this->pdo->prepare("UPDATE services SET dashboard_id = (SELECT id FROM dashboards ORDER BY id LIMIT 1) WHERE dashboard_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM dashboards WHERE id = ?")->execute([$id]);
        header('Location: /');
        exit;
    }

    public function saveSettings(): void {
        // Gère la couleur
        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('background_color', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$_POST['background_color']]);

        // Gère l'upload d'image
        $stmt = $this->pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'background_image'");
        $current_image = $stmt->fetchColumn();
        $image_url = $this->handleUpload('background_image', $current_image ?: null, isset($_POST['remove_background_image']));

        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('background_image', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$image_url]);

        header('Location: /');
        exit;
    }
    
    private function getDashboardById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM dashboards WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Gère le téléversement d'un fichier, sa suppression et retourne le chemin d'accès public.
     */
    private function handleUpload(string $fileKey, ?string $currentUrl = null, bool $remove = false): ?string
    {
        $uploadDir = __DIR__ . '/../../public/assets/uploads/';

        // Crée le dossier d'upload s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Si la case "supprimer" est cochée ou si un nouveau fichier remplace un ancien
        if (($remove || (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK)) && $currentUrl) {
            $filePath = realpath($uploadDir . basename($currentUrl));
            if ($filePath && strpos($filePath, realpath($uploadDir)) === 0 && file_exists($filePath)) {
                unlink($filePath);
            }
            if ($remove) {
                return null;
            }
        }

        // Si aucun nouveau fichier n'est envoyé, on garde l'ancien (sauf si remove était coché)
        if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            return $currentUrl;
        }

        // Déplace le nouveau fichier
        $extension = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $safeFilename)) {
            return '/assets/uploads/' . $safeFilename;
        }

        return $currentUrl; // Retourne l'ancien en cas d'échec de l'upload
    }
}
