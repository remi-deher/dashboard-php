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
            $stmt = $this->serviceModel->getPdo()->query('SELECT id, nom, icone FROM dashboards ORDER BY ordre_affichage, nom');
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
            
            $grouped_services = [];
            foreach ($services as $service) {
                $group_name = $service['groupe'];
                if (!isset($grouped_services[$group_name])) {
                    $grouped_services[$group_name] = [];
                }
                $grouped_services[$group_name][] = [
                    'nom' => $service['nom'],
                    'url' => $service['url'],
                    'icone' => $service['icone'],
                    'description' => $service['description']
                ];
            }
            echo json_encode($grouped_services);
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
        curl_close($ch);
        $is_online = ($http_code >= 200 && $http_code < 400);
        echo json_encode(['status' => $is_online ? 'online' : 'offline']);
    }
}
