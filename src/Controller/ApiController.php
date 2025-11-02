<?php
// Fichier: /src/Controller/ApiController.php

namespace App\Controller;

use App\Model\ServiceModel;
use App\Model\DashboardModel;
use App\Service\WidgetServiceRegistry;
use App\Service\MicrosoftGraphService; // AJOUTÉ
use PDO;
use Predis\Client as RedisClient; // AJOUTÉ

class ApiController
{
    private ServiceModel $serviceModel;
    private DashboardModel $dashboardModel;
    private WidgetServiceRegistry $widgetRegistry;
    private PDO $pdo; 
    private MicrosoftGraphService $microsoftGraphService; // AJOUTÉ
    private RedisClient $redis; // AJOUTÉ

    public function __construct(
        ServiceModel $serviceModel, 
        DashboardModel $dashboardModel, 
        PDO $pdo,
        WidgetServiceRegistry $widgetRegistry,
        MicrosoftGraphService $microsoftGraphService, // AJOUTÉ
        RedisClient $redis // AJOUTÉ
    ) {
        $this->serviceModel = $serviceModel;
        $this->dashboardModel = $dashboardModel;
        $this->pdo = $pdo;
        $this->widgetRegistry = $widgetRegistry;
        $this->microsoftGraphService = $microsoftGraphService; // AJOUTÉ
        $this->redis = $redis; // AJOUTÉ
    }

    private function sendJsonResponse(mixed $data, int $http_code = 200): void
    {
        http_response_code($http_code);
        header('Content-Type: application/json');
        
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        echo json_encode($data);
        exit;
    }

    // --- NOUVELLE MÉTHODE ---
    public function getM365Targets(): void
    {
        try {
            $targets = $this->microsoftGraphService->listDirectoryTargets();
            if (isset($targets['error'])) {
                $this->sendJsonResponse($targets, 401); // 401 Unauthorized (Probablement pas connecté)
            } else {
                $this->sendJsonResponse($targets);
            }
        } catch (\Exception $e) {
            error_log('Erreur getM365Targets: ' . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Erreur interne du serveur.'], 500);
        }
    }
    // --- FIN ---

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
    
    /**
     * MODIFIÉ : Lit les données du widget DEPUIS LE CACHE REDIS
     */
    public function getWidgetData(int $id): void
    {
        try {
            $cacheKey = "widget:data:" . $id;
            $cachedData = $this->redis->get($cacheKey);

            if ($cachedData) {
                // Les données sont en cache, on les envoie brutes (c'est déjà du JSON)
                http_response_code(200);
                header('Content-Type: application/json');
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                echo $cachedData;
                exit;
            }

            // Si le cache est vide (le worker n'a pas encore tourné)
            // On renvoie un statut "en attente"
            // Le client recevra les données via SSE dès qu'elles seront prêtes.
            $this->sendJsonResponse(['error' => 'Données en cours de génération...'], 202); // 202 Accepted

        } catch (\Exception $e) {
            error_log('Erreur getWidgetData (Cache): ' . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Erreur interne du serveur (Cache).'], 500);
        }
    }

    public function checkStatus(): void {
        $url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
        if (!$url) {
            $this->sendJsonResponse(['status' => 'error', 'message' => 'URL invalide ou manquante.'], 400);
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
