<?php
// Fichier: /src/Controller/ApiController.php

// Ce contrôleur a besoin du ServiceModel pour interagir avec la base de données.
require_once __DIR__ . '/../Model/ServiceModel.php';

class ApiController
{
    private ServiceModel $serviceModel;

    // La connexion au modèle est passée lors de la création du contrôleur.
    public function __construct(ServiceModel $serviceModel)
    {
        $this->serviceModel = $serviceModel;
    }

    /**
     * Récupère tous les services, les groupe et les retourne en JSON.
     * Remplace l'ancien fichier /api/get_services.php
     */
    public function getServices(): void
    {
        // On s'assure que la réponse sera bien interprétée comme du JSON.
        header('Content-Type: application/json');

        try {
            // On utilise le modèle pour récupérer les données brutes.
            $services = $this->serviceModel->getAll();
            
            // On recrée la même logique de groupement que dans l'ancien script.
            // Le frontend s'attend à recevoir les données sous cette forme.
            $grouped_services = [];
            foreach ($services as $service) {
                $group_name = $service['groupe'];
                if (!isset($grouped_services[$group_name])) {
                    $grouped_services[$group_name] = [];
                }
                // On ne garde que les champs utiles pour le frontend pour alléger la réponse.
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
            echo json_encode(['error' => 'Une erreur interne est survenue.']);
        }
    }

    /**
     * Vérifie le statut en ligne d'une URL donnée.
     * Remplace l'ancien fichier /api/check_status.php
     */
    public function checkStatus(): void
    {
        header('Content-Type: application/json');

        $url = filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL);

        if (!$url) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => 'URL invalide ou manquante.']);
            return; // On arrête l'exécution
        }

        // La logique cURL reste identique, elle est performante et fiable.
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Important pour les certs auto-signés
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $is_online = ($http_code >= 200 && $http_code < 400);

        echo json_encode(['status' => $is_online ? 'online' : 'offline']);
    }
}
