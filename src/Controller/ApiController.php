<?php
// Fichier: /src/Controller/ApiController.php (Corrigé avec ob_clean)

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel;
use App\Service\XenOrchestraService;
use PDO;

class ApiController
{
    private ServiceModel $serviceModel;
    private DashboardModel $dashboardModel;
    private XenOrchestraService $xenOrchestraService;
    private PDO $pdo; 

    public function __construct(
        ServiceModel $serviceModel, 
        DashboardModel $dashboardModel, 
        PDO $pdo,
        XenOrchestraService $xenOrchestraService
    ) {
        $this->serviceModel = $serviceModel;
        $this->dashboardModel = $dashboardModel;
        $this->pdo = $pdo;
        $this->xenOrchestraService = $xenOrchestraService;
    }

    // --- Fonction utilitaire pour envoyer du JSON proprement ---
    private function sendJsonResponse(mixed $data, int $http_code = 200): void
    {
        http_response_code($http_code);
        header('Content-Type: application/json');
        
        // CORRECTION ROBUSTE : Nettoie tout ce qui a pu être imprimé (Warnings, Notices)
        // avant d'envoyer notre JSON.
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        echo json_encode($data);
        exit; // Termine le script
    }
    // -----------------------------------------------------------

    public function getDashboards(): void
    {
        try {
            $dashboards = $this->dashboardModel->getAllForTabs();
            $this->sendJsonResponse($dashboards); 
        } catch (\Exception $e) {
            error_log('Erreur getDashboards: ' . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Impossible de récupérer les dashboards.'], 500);
        }
    }

    public function getServices(): void {
        $dashboardId = filter_input(INPUT_GET, 'dashboard_id', FILTER_VALIDATE_INT);

        if ($dashboardId === false || $dashboardId <= 0) {
            $this->sendJsonResponse(['error' => 'ID de dashboard invalide ou manquant.'], 400);
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
                    'size_class'      => $service['size_class'] ?? 'size-medium',
                    'widget_type'     => $service['widget_type'] ?? 'link'
                ];
            }
            $this->sendJsonResponse($output_services);
        } catch (\Exception $e) {
            error_log('Erreur getServices: ' . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Impossible de récupérer les services.'], 500);
        }
    }
    
    public function getWidgetData(int $id): void
    {
        try {
            $service = $this->serviceModel->getById($id);
            if (!$service) {
                $this->sendJsonResponse(['error' => 'Service non trouvé.'], 404);
                return;
            }

            $data = [];
            
            // CORRECTION SPÉCIFIQUE : Gère le cas où widget_type est NULL
            $widgetType = $service['widget_type'] ?? null; 
            
            switch ($widgetType) {
                case 'xen_orchestra':
                    $data = $this->xenOrchestraService->getVmStats();
                    break;
                case 'link':
                case null: // Les liens simples ou les anciens services n'ont pas de données de widget
                    $data = ['error' => 'Ce service est un lien, il n\'a pas de données de widget.'];
                    break;
                default:
                    $data = ['error' => 'Type de widget non supporté'];
                    break;
            }
            
            $this->sendJsonResponse($data);

        } catch (\Exception $e) {
            error_log('Erreur getWidgetData: ' . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Erreur interne du serveur.'], 500);
        }
    }

    public function checkStatus(): void {
        $url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
        if (!$url) {
            $this->sendJsonResponse(['status' => 'error', 'message' => 'URL invalide ou manquante.'], 400);
            return;
        }

        // ... (logique cURL inchangée) ...
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
            $this->sendJsonResponse([
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
        $this->sendJsonResponse([
            'status' => $is_online ? 'online' : 'offline',
            'connect_time' => $connect_time_ms,
            'ttfb' => $is_online ? $ttfb_ms : 0 
        ]);
    }

    public function saveServiceSize(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['sizeClass']) || !is_string($data['sizeClass'])) {
            $this->sendJsonResponse(['error' => 'Données JSON invalides ou classe de taille manquante/invalide.'], 400);
            return;
        }
        $allowedSizes = ['size-small', 'size-medium', 'size-large']; 
        if (!in_array($data['sizeClass'], $allowedSizes)) {
            $this->sendJsonResponse(['error' => 'Classe de taille non autorisée.'], 400);
            return;
        }
        try {
            $this->serviceModel->updateSizeClass($id, $data['sizeClass']);
            $this->sendJsonResponse(['success' => true]);
        } catch (\Exception $e) {
            error_log('Erreur saveServiceSize: ' . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Erreur lors de la sauvegarde de la taille.'], 500);
        }
    }

    public function saveLayout(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($input['dashboardId']) || !isset($input['ids']) || !is_array($input['ids'])) {
            $this->sendJsonResponse(['error' => 'Données JSON invalides ou manquantes.'], 400);
            return;
        }
        $dashboardId = (int)$input['dashboardId'];
        $orderedIds = array_map('intval', $input['ids']);
        if ($dashboardId <= 0) {
             $this->sendJsonResponse(['error' => 'ID de dashboard invalide.'], 400);
             return;
        }
        try {
            $this->serviceModel->updateOrderForDashboard($dashboardId, $orderedIds);
            $this->sendJsonResponse(['success' => true]);
        } catch (\Exception $e) {
            error_log("Erreur saveLayout: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Erreur lors de la sauvegarde de l\'ordre.'], 500);
        }
    }

    public function saveDashboardLayout(): void {
        $dashboardIds = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dashboardIds)) {
            $this->sendJsonResponse(['error' => 'Données JSON invalides (attendu un tableau d\'IDs).'], 400);
            return;
        }
        try {
            $this->dashboardModel->updateOrder($dashboardIds);
            $this->sendJsonResponse(['success' => true]);
        } catch (\Exception $e) {
            error_log("Erreur saveDashboardLayout: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Erreur lors de la sauvegarde de l\'ordre des dashboards.'], 500);
        }
    }

    public function moveService(int $id, int $dashboardId): void {
        if ($id <= 0 || $dashboardId <= 0) {
            $this->sendJsonResponse(['error' => 'ID de service ou de dashboard invalide.'], 400);
            return;
        }
        try {
            $this->serviceModel->updateDashboardId($id, $dashboardId);
            $this->sendJsonResponse(['success' => true]);
        } catch (\Exception $e) {
            error_log("Erreur moveService: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Erreur lors du déplacement du service.'], 500);
        }
    }
}
