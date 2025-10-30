<?php
// Fichier: /src/Controller/ApiController.php

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel; // AJOUTÉ
use PDO;

class ApiController
{
    private ServiceModel $serviceModel;
    private DashboardModel $dashboardModel; // AJOUTÉ
    private PDO $pdo; // Gardé pour checkStatus (cURL) pour l'instant

    public function __construct(
        ServiceModel $serviceModel, 
        DashboardModel $dashboardModel, // AJOUTÉ
        PDO $pdo
    ) {
        $this->serviceModel = $serviceModel;
        $this->dashboardModel = $dashboardModel; // AJOUTÉ
        $this->pdo = $pdo;
    }

    public function getDashboards(): void
    {
        header('Content-Type: application/json');
        try {
            // UTILISATION DU MODÈLE
            $dashboards = $this->dashboardModel->getAllForTabs();
            echo json_encode($dashboards); 
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de récupérer les dashboards.', 'details' => $e->getMessage()]);
        }
    }

    public function getServices(): void {
        // ... (cette méthode n'utilisait que ServiceModel, donc elle ne change pas) ...
        header('Content-Type: application/json');
        $dashboardId = filter_input(INPUT_GET, 'dashboard_id', FILTER_VALIDATE_INT);

        if ($dashboardId === false || $dashboardId <= 0) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'ID de dashboard invalide ou manquant.']);
            return;
        }

        try {
            $services = $this->serviceModel->getAllByDashboardId($dashboardId);

            $output_services = [];
            foreach ($services as $service) {
                $output_services[] = [
                    'id'              => $service['id'],
                    'nom'             => $service['nom'],
                    'url'             => $service['url'],
                    'icone'           => $service['icone'],
                    'icone_url'       => $service['icone_url'],
                    'description'     => $service['description'],
                    'card_color'      => $service['card_color'],
                    'ordre_affichage' => $service['ordre_affichage'] ?? 0, 
                    'size_class'      => $service['size_class'] ?? 'size-medium' 
                ];
            }
            echo json_encode($output_services);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de récupérer les services.', 'details' => $e->getMessage()]);
        }
    }

    public function checkStatus(): void {
        // ... (cette méthode ne change pas, elle fait du cURL) ...
        header('Content-Type: application/json');
        $url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
        if (!$url) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'URL invalide ou manquante.']);
            return;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($ch, CURLOPT_TIMECONDITION, 1);
        curl_setopt($ch, CURLOPT_TIMEVALUE, time());
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_HEADER, true); 

        $response = curl_exec($ch);
        $error_no = curl_errno($ch);

        if ($error_no !== 0) {
            curl_close($ch);
            echo json_encode([
                'status' => 'offline',
                'connect_time' => 0,
                'ttfb' => 0,
                'message' => 'Erreur cURL: ' . curl_error($ch)
            ]);
            return;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $connect_time_us = curl_getinfo($ch, CURLINFO_CONNECT_TIME_T);
        $starttransfer_time_us = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME_T); 

        curl_close($ch);

        $connect_time_ms = round($connect_time_us / 1000);
        $ttfb_ms = round($starttransfer_time_us / 1000);

        $is_online = ($http_code >= 200 && $http_code < 400);

        echo json_encode([
            'status' => $is_online ? 'online' : 'offline',
            'connect_time' => $connect_time_ms,
            'ttfb' => $is_online ? $ttfb_ms : 0 
        ]);
    }

    public function saveServiceSize(int $id): void {
        // ... (cette méthode n'utilisait que ServiceModel, donc elle ne change pas) ...
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['sizeClass']) || !is_string($data['sizeClass'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides ou classe de taille manquante/invalide.']);
            return;
        }

        $allowedSizes = ['size-small', 'size-medium', 'size-large']; 
        if (!in_array($data['sizeClass'], $allowedSizes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Classe de taille non autorisée.']);
            return;
        }

        try {
            $this->serviceModel->updateSizeClass($id, $data['sizeClass']);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la sauvegarde de la taille.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    public function saveLayout(): void {
        // ... (cette méthode n'utilisait que ServiceModel, donc elle ne change pas) ...
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($input['dashboardId']) || !isset($input['ids']) || !is_array($input['ids'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides ou manquantes.']);
            return;
        }

        $dashboardId = (int)$input['dashboardId'];
        $orderedIds = array_map('intval', $input['ids']);

        if ($dashboardId <= 0) {
             http_response_code(400);
             echo json_encode(['error' => 'ID de dashboard invalide.']);
             return;
        }

        try {
            $this->serviceModel->updateOrderForDashboard($dashboardId, $orderedIds);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Erreur saveLayout: " . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors de la sauvegarde de l\'ordre.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    public function saveDashboardLayout(): void {
        header('Content-Type: application/json');
        $dashboardIds = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dashboardIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides (attendu un tableau d\'IDs).']);
            return;
        }

        try {
            // UTILISATION DU MODÈLE
            $this->dashboardModel->updateOrder($dashboardIds);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Erreur saveDashboardLayout: " . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors de la sauvegarde de l\'ordre des dashboards.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    public function moveService(int $id, int $dashboardId): void {
        // ... (cette méthode n'utilisait que ServiceModel, donc elle ne change pas) ...
        header('Content-Type: application/json');

        if ($id <= 0 || $dashboardId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de service ou de dashboard invalide.']);
            return;
        }

        try {
            $this->serviceModel->updateDashboardId($id, $dashboardId);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Erreur moveService: " . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors du déplacement du service.', 'details' => $e->getMessage()]);
        }
        exit;
    }
}
