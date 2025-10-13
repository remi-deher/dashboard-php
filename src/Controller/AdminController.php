<?php
// Fichier: /src/Controller/AdminController.php

require_once __DIR__ . '/../Model/ServiceModel.php';

class AdminController
{
    private ServiceModel $serviceModel;
    private PDO $pdo;

    public function __construct(ServiceModel $serviceModel, PDO $pdo)
    {
        $this->serviceModel = $serviceModel;
        $this->pdo = $pdo;
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            switch ($action) {
                case 'add_service':
                    $this->serviceModel->create($_POST);
                    break;
                case 'update_service':
                    $this->serviceModel->update((int)$_POST['id'], $_POST);
                    break;
                case 'delete_service':
                    $this->serviceModel->delete((int)$_POST['id']);
                    break;
                case 'add_dashboard':
                    $this->createDashboard($_POST);
                    break;
                case 'update_dashboard':
                    $this->updateDashboard($_POST);
                    break;
                case 'delete_dashboard':
                    $this->deleteDashboard((int)$_POST['id']);
                    break;
                case 'save_settings':
                    $this->saveSettings($_POST);
                    break;
            }
            header('Location: /admin.php');
            exit;
        }

        $this->displayPage();
    }

    private function displayPage(): void
    {
        $all_services = $this->serviceModel->getAll();
        $all_dashboards = $this->pdo->query('SELECT * FROM dashboards ORDER BY ordre_affichage, nom')->fetchAll();
        
        $settings_raw = $this->pdo->query('SELECT * FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings['background'] = $settings_raw['background'] ?? '';
        
        $edit_service = isset($_GET['edit_service']) ? $this->serviceModel->getById((int)$_GET['edit_service']) : null;
        $edit_dashboard = isset($_GET['edit_dashboard']) ? $this->getDashboardById((int)$_GET['edit_dashboard']) : null;
        
        require __DIR__ . '/../../templates/admin.php';
    }

    private function saveSettings(array $data): void {
        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('background', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$data['background']]);
    }

    private function createDashboard(array $data): void {
        $stmt = $this->pdo->prepare("INSERT INTO dashboards (nom, icone, ordre_affichage) VALUES (?, ?, ?)");
        $stmt->execute([$data['nom'], $data['icone'], $data['ordre_affichage']]);
    }
    
    private function updateDashboard(array $data): void {
        $stmt = $this->pdo->prepare("UPDATE dashboards SET nom = ?, icone = ?, ordre_affichage = ? WHERE id = ?");
        $stmt->execute([$data['nom'], $data['icone'], $data['ordre_affichage'], $data['id']]);
    }

    private function deleteDashboard(int $id): void {
        // Optionnel : Réassigner les services de ce dashboard ou les supprimer
        $this->pdo->prepare("UPDATE services SET dashboard_id = (SELECT id FROM dashboards ORDER BY id LIMIT 1) WHERE dashboard_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM dashboards WHERE id = ?")->execute([$id]);
    }
    
    private function getDashboardById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM dashboards WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
