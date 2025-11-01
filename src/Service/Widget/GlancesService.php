<?php
// Fichier: /src/Service/Widget/GlancesService.php

namespace App\Service\Widget;

class GlancesService implements WidgetInterface
{
    // Glances n'a pas besoin de config globale (pas d'API key)
    public function __construct()
    {
        // Constructeur vide pour l'instant
    }

    /**
     * Récupère les données depuis l'API REST de Glances.
     * L'URL du service (ex: http://192.168.1.50:61208) est utilisée
     * comme hôte pour l'API.
     */
    public function getWidgetData(array $service): array
    {
        $host = rtrim($service['url'], '/');
        
        // Nous allons chercher 3 endpoints : cpu, mem, load
        $cpuData = $this->fetchGlancesApi($host . '/api/3/cpu');
        $memData = $this->fetchGlancesApi($host . '/api/3/mem');
        $loadData = $this->fetchGlancesApi($host . '/api/3/load');

        if (isset($cpuData['error']) || isset($memData['error']) || isset($loadData['error'])) {
            return ['error' => $cpuData['error'] ?? $memData['error'] ?? $loadData['error'] ?? 'Erreur Glances inconnue'];
        }

        return [
            'cpu_total' => $cpuData['total'] ?? 0,
            'mem_used_percent' => $memData['percent'] ?? 0,
            'load_1' => $loadData['min1'] ?? 0,
            'load_5' => $loadData['min5'] ?? 0,
            'load_15' => $loadData['min15'] ?? 0,
        ];
    }

    /**
     * Fonction utilitaire cURL pour Glances
     */
    private function fetchGlancesApi(string $url): array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($http_code !== 200) {
                return ['error' => "HTTP {$http_code} - {$error}"];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'Réponse JSON invalide'];
            }
            return $data;

        } catch (\Exception $e) {
            return ['error' => 'Erreur cURL: ' . $e->getMessage()];
        }
    }
}
