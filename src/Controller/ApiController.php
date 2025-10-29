<?php
// Fichier: /src/Controller/ApiController.php

namespace App\Controller;

use App\Model\ServiceModel;
use PDO; // Ajouté pour utiliser PDO::FETCH_KEY_PAIR

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
            // Le tri par ordre_affichage est important ici aussi pour les onglets
            $stmt = $this->serviceModel->getPdo()->query('SELECT id, nom, icone, icone_url FROM dashboards ORDER BY ordre_affichage, nom');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); // Utiliser FETCH_ASSOC pour un JSON propre
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de récupérer les dashboards.', 'details' => $e->getMessage()]);
        }
    }

    public function getServices(): void {
        header('Content-Type: application/json');
        $dashboardId = filter_input(INPUT_GET, 'dashboard_id', FILTER_VALIDATE_INT);

        // Vérifier si dashboardId est valide
        if ($dashboardId === false || $dashboardId <= 0) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'ID de dashboard invalide ou manquant.']);
            return;
        }

        try {
            // Utilise la méthode du modèle qui trie déjà par ordre_affichage
            $services = $this->serviceModel->getAllByDashboardId($dashboardId);

            $output_services = [];
            foreach ($services as $service) {
                // *** MODIFIÉ ICI *** : Remplacer gs_* par ordre_affichage et size_class
                $output_services[] = [
                    'id'              => $service['id'],
                    'nom'             => $service['nom'],
                    'url'             => $service['url'],
                    'icone'           => $service['icone'],
                    'icone_url'       => $service['icone_url'],
                    'description'     => $service['description'],
                    'card_color'      => $service['card_color'],
                    'ordre_affichage' => $service['ordre_affichage'] ?? 0, // Ajouté
                    'size_class'      => $service['size_class'] ?? 'size-medium' // Ajouté
                ];
            }
            echo json_encode($output_services);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de récupérer les services.', 'details' => $e->getMessage()]);
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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); // Ne récupère que les en-têtes
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout connexion
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout total
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Suivre redirections
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignorer erreurs SSL (à utiliser avec prudence)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Ignorer erreurs SSL (à utiliser avec prudence)
        // Récupérer les temps détaillés
        curl_setopt($ch, CURLOPT_TIMECONDITION, 1);
        curl_setopt($ch, CURLOPT_TIMEVALUE, time());
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_HEADER, true); // Important pour récupérer les temps via CURLINFO_*_T

        $response = curl_exec($ch);
        $error_no = curl_errno($ch);

        if ($error_no !== 0) {
            // Erreur cURL (timeout, DNS, etc.)
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
        // Utiliser CURLINFO_*_T pour microsecondes, puis convertir en ms
        $connect_time_us = curl_getinfo($ch, CURLINFO_CONNECT_TIME_T);
        $starttransfer_time_us = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME_T); // TTFB en microsecondes

        curl_close($ch);

        $connect_time_ms = round($connect_time_us / 1000);
        $ttfb_ms = round($starttransfer_time_us / 1000);

        // Considérer 2xx et 3xx comme "online"
        $is_online = ($http_code >= 200 && $http_code < 400);

        echo json_encode([
            'status' => $is_online ? 'online' : 'offline',
            'connect_time' => $connect_time_ms,
            'ttfb' => $is_online ? $ttfb_ms : 0 // Ne pas montrer TTFB si offline
        ]);
    }

    /**
     * NOUVEAU: Endpoint pour sauvegarder la taille d'une tuile
     */
    public function saveServiceSize(int $id): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['sizeClass']) || !is_string($data['sizeClass'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides ou classe de taille manquante/invalide.']);
            return;
        }

        // Valider la classe de taille (sécurité)
        $allowedSizes = ['size-small', 'size-medium', 'size-large']; // Adaptez si nécessaire
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
}
