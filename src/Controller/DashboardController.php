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
        
        $settings_raw = $this->pdo->query('SELECT * FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings['background_color'] = $settings_raw['background_color'] ?? '';
        $settings['background_image'] = $settings_raw['background_image'] ?? '';
        
        $edit_service = $edit_service_id ? $this->serviceModel->getById($edit_service_id) : null;
        $edit_dashboard = $edit_dashboard_id ? $this->getDashboardById($edit_dashboard_id) : null;
        
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
        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('background_color', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$_POST['background_color']]);

        $stmt = $this->pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'background_image'");
        $current_image = $stmt->fetchColumn();
        $image_url = $this->handleUpload('background_image', $current_image ?: null, isset($_POST['remove_background_image']));

        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('background_image', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$image_url]);

        header('Location: /');
        exit;
    }
    
    public function saveLayout(): void {
        header('Content-Type: application/json');
        $layoutData = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($layoutData)) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides.']);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'UPDATE services SET gs_x = ?, gs_y = ?, gs_width = ?, gs_height = ? WHERE id = ?'
            );
            foreach ($layoutData as $item) {
                // *** CORRECTION DU BUG ICI ***
                // Utiliser 'w' et 'h' au lieu de 'width' et 'height'
                $stmt->execute([
                    $item['x'],
                    $item['y'],
                    $item['w'], // Corrigé
                    $item['h'], // Corrigé
                    $item['id']
                ]);
            }
            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la sauvegarde.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    public function saveDashboardLayout(): void {
        header('Content-Type: application/json');
        $dashboardIds = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dashboardIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides.']);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'UPDATE dashboards SET ordre_affichage = ? WHERE id = ?'
            );
            foreach ($dashboardIds as $index => $id) {
                $stmt->execute([$index, $id]);
            }
            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la sauvegarde.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * NOUVEAU: Gère le déplacement d'un service vers un autre dashboard
     */
    public function moveService(int $id, int $dashboardId): void {
        header('Content-Type: application/json');
        
        $layoutData = json_decode(file_get_contents('php://input'), true);
        $x = $layoutData['x'] ?? 0;
        $y = $layoutData['y'] ?? 0;
        $w = $layoutData['w'] ?? 2; // Défaut 2 (au lieu de 1)
        $h = $layoutData['h'] ?? 1;

        try {
            $this->serviceModel->updateDashboardIdAndLayout($id, $dashboardId, $x, $y, $w, $h);
            
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors du déplacement.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    private function getDashboardById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM dashboards WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function handleUpload(string $fileKey, ?string $currentUrl = null, bool $remove = false): ?string
    {
        $uploadDir = __DIR__ . '/../../public/assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        if (($remove || (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK)) && $currentUrl) {
            $filePath = realpath($uploadDir . basename($currentUrl));
            if ($filePath && strpos($filePath, realpath($uploadDir)) === 0 && file_exists($filePath)) {
                unlink($filePath);
            }
            if ($remove) {
                return null;
            }
        }
        if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            return $currentUrl;
        }
        $extension = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $safeFilename)) {
            return '/assets/uploads/' . $safeFilename;
        }
        return $currentUrl;
    }
}
