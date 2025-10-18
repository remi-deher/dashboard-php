<?php
// Fichier: /src/Controller/ApiController.php

namespace App\Controller;

use App\Model\ServiceModel;

class ApiController
{
    private ServiceModel $serviceModel;

    public function __construct(ServiceModel $serviceModel) {
        $this->serviceModel = $serviceModel;
    }

    public function getDashboards(): void
    {
        header('Content-Type: application/json');
        try {
            $stmt = $this->serviceModel->getPdo()->query('SELECT id, nom, icone, icone_url FROM dashboards ORDER BY ordre_affichage, nom');
            echo json_encode($stmt->fetchAll());
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de récupérer les dashboards.']);
        }
    }

    public function getServices(): void {
        header('Content-Type: application/json');
        $dashboardId = (int)($_GET['dashboard_id'] ?? 0);

        if ($dashboardId <= 0) {
            echo json_encode([]);
            return;
        }

        try {
            $services = $this->serviceModel->getAllByDashboardId($dashboardId);
            
            $output_services = [];
            foreach ($services as $service) {
                $output_services[] = [
                    'id'          => $service['id'],
                    'nom'         => $service['nom'],
                    'url'         => $service['url'],
                    'icone'       => $service['icone'],
                    'icone_url'   => $service['icone_url'],
                    'description' => $service['description'],
                    'card_color'  => $service['card_color'],
                    // Coordonnées GridStack
                    // *** CORRECTION DU BUG ICI ***
                    'gs_x'        => $service['gs_x'] ?? 0,
                    'gs_y'        => $service['gs_y'] ?? 0,
                    'gs_width'    => $service['gs_width'] ?? 2, // Défaut 2
                    'gs_height'   => $service['gs_height'] ?? 1  // Défaut 1
                ];
            }
            echo json_encode($output_services);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de récupérer les services.']);
        }
    }

    public function checkStatus(): void {
        header('Content-Type: application/json');
        $url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);
        if (!$url) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'URL invalide ou manquante.']);
            return;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        curl_exec($ch);
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $connect_time_ms = round(curl_getinfo($ch, CURLINFO_CONNECT_TIME_T) / 1000);
        $ttfb_ms = round(curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME_T) / 1000);

        curl_close($ch);
        
        $is_online = ($http_code >= 200 && $http_code < 400);
        
        echo json_encode([
            'status' => $is_online ? 'online' : 'offline',
            'connect_time' => $connect_time_ms,
            'ttfb' => $ttfb_ms
        ]);
    }
}
