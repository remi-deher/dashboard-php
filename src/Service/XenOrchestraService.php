<?php
// Fichier: /src/Service/XenOrchestraService.php

namespace App\Service;

class XenOrchestraService
{
    private string $host;
    private string $token;

    public function __construct(?string $host, ?string $token)
    {
        $this->host = $host ?? '';
        $this->token = $token ?? '';
    }

    /**
     * Appelle l'API JSON-RPC de Xen Orchestra pour obtenir les stats de VM.
     * @return array
     */
    public function getVmStats(): array
    {
        if (empty($this->host) || empty($this->token)) {
            return ['error' => 'Xen Orchestra n\'est pas configuré dans config.php'];
        }

        $apiUrl = rtrim($this->host, '/') . '/api/jsonrpc';
        $payload = json_encode([
            "jsonrpc" => "2.0",
            "method" => "vm.getAllRecords", // Récupère toutes les VM
            "params" => [],
            "id" => 1
        ]);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'Authorization: Bearer ' . $this->token // Authentification
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            // Nécessaire si votre XOA utilise un certificat auto-signé
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($http_code !== 200) {
                return ['error' => "Erreur API XOA: HTTP {$http_code} - {$error}"];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'Réponse JSON XOA invalide.'];
            }

            if (isset($data['error'])) {
                return ['error' => "Erreur XOA: " . $data['error']['message']];
            }

            // Calculer les statistiques
            return $this->parseVmRecords($data['result'] ?? []);

        } catch (\Exception $e) {
            return ['error' => 'Erreur cURL: ' . $e->getMessage()];
        }
    }

    /**
     * Traite la liste des VM pour en faire des stats simples.
     * @param array $vms Liste des objets VM depuis XOA
     * @return array
     */
    private function parseVmRecords(array $vms): array
    {
        $stats = [
            'running' => 0,
            'halted' => 0,
            'paused' => 0,
            'total' => count($vms)
        ];

        foreach ($vms as $vm) {
            if ($vm['is_a_template'] || $vm['is_a_snapshot']) {
                $stats['total']--; // Ne pas compter les templates/snapshots
                continue;
            }

            $powerState = $vm['power_state'] ?? 'halted';
            if ($powerState === 'Running') {
                $stats['running']++;
            } elseif ($powerState === 'Halted') {
                $stats['halted']++;
            } elseif ($powerState === 'Paused') {
                $stats['paused']++;
            }
        }
        return $stats;
    }
}
