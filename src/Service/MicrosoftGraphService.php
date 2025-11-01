<?php
// Fichier: /src/Service/MicrosoftGraphService.php

namespace App\Service;

use App\Model\SettingsModel;
use App\Service\Widget\WidgetInterface;

class MicrosoftGraphService implements WidgetInterface
{
    private SettingsModel $settingsModel;
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    private string $redirectUri;
    private ?string $refreshToken;

    private const M365_SCOPES = [
        'offline_access',
        'User.Read',
        'Calendars.Read',
        'Calendars.Read.Shared',
        'Mail.ReadBasic',
        'Reports.Read.All',
        'User.Read.All',
        'Group.Read.All'
    ];

    public function __construct(SettingsModel $settingsModel, string $redirectUri, array $config)
    {
        $this->settingsModel = $settingsModel;
        $this->redirectUri = $redirectUri;
        
        $this->clientId = $config['m365_client_id'] ?? '';
        $this->clientSecret = $config['m365_client_secret'] ?? '';
        $this->tenantId = $config['m365_tenant_id'] ?? 'common';
        $this->refreshToken = $config['m365_refresh_token'] ?? null;
    }

    /**
     * Point d'entrée pour tous les widgets M365.
     */
    public function getWidgetData(array $service): array
    {
        if (empty($this->refreshToken)) {
            return ['error' => 'M365 non connecté. Allez dans Widgets > Se connecter.'];
        }
        
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['error' => 'Impossible de rafraîchir le token M365. Reconnectez-vous.'];
        }

        switch ($service['widget_type']) {
            case 'm365_calendar':
                return $this->getCalendarData($accessToken, $service);
            case 'm365_mail_stats':
                return $this->getMailStatsData($accessToken, $service);
            default:
                return ['error' => 'Type de widget M365 non supporté'];
        }
    }

    /**
     * Génère l'URL de connexion pour l'administrateur.
     */
    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => implode(' ', self::M365_SCOPES),
            'state' => '12345'
        ];
        
        return "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?" . http_build_query($params);
    }

    /**
     * Échange le code d'autorisation contre un Refresh Token et le sauvegarde.
     */
    public function handleCallback(string $code): bool
    {
        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $params = [
            'client_id' => $this->clientId,
            'scope' => implode(' ', self::M365_SCOPES),
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'client_secret' => $this->clientSecret,
        ];

        $data = $this->postToApi($url, $params);

        if (isset($data['refresh_token'])) {
            $this->settingsModel->save('m365_refresh_token', $data['refresh_token']);
            return true;
        }
        
        error_log("Erreur handleCallback M365: " . ($data['error_description'] ?? 'Réponse inconnue'));
        return false;
    }

    /**
     * Utilise le Refresh Token pour obtenir un Access Token frais.
     */
    private function getAccessToken(): ?string
    {
        if (empty($this->refreshToken)) {
            return null;
        }
        
        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $params = [
            'client_id' => $this->clientId,
            'scope' => implode(' ', self::M365_SCOPES),
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
            'client_secret' => $this->clientSecret,
        ];
        
        $data = $this->postToApi($url, $params);
        
        if (isset($data['access_token'])) {
            return $data['access_token'];
        }

        if (isset($data['error'])) {
            error_log("Erreur getAccessToken M365: " . $data['error_description']);
            $this->settingsModel->save('m365_refresh_token', null);
        }
        return null;
    }
    
    /**
     * Récupère les utilisateurs et groupes pour le menu déroulant.
     */
    public function listDirectoryTargets(): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['error' => 'Non connecté à M365.'];
        }

        $targets = [];

        $me = $this->getFromApi("https://graph.microsoft.com/v1.0/me?\$select=displayName,mail,userPrincipalName", $accessToken);
        if (isset($me['mail']) || isset($me['userPrincipalName'])) {
             $targets[] = [
                'name' => $me['displayName'] . " (Moi)",
                'email' => $me['mail'] ?? $me['userPrincipalName']
             ];
        }

        $users = $this->getFromApi("https://graph.microsoft.com/v1.0/users?\$top=100&\$select=displayName,mail,userPrincipalName", $accessToken);
        if (isset($users['value'])) {
            foreach ($users['value'] as $user) {
                $email = $user['mail'] ?? $user['userPrincipalName'];
                if (!empty($email) && $email !== ($me['mail'] ?? $me['userPrincipalName'])) {
                    $targets[] = [
                        'name' => "[U] " . $user['displayName'],
                        'email' => $email
                    ];
                }
            }
        }

        $groups = $this->getFromApi("https://graph.microsoft.com/v1.0/groups?\$top=100&\$filter=groupTypes/any(c:c eq 'Unified') or mailEnabled eq true&\$select=displayName,mail,userPrincipalName", $accessToken);
         if (isset($groups['value'])) {
            foreach ($groups['value'] as $group) {
                $email = $group['mail'] ?? $group['userPrincipalName'];
                if (!empty($email)) {
                    $targets[] = [
                        'name' => "[G] " . $group['displayName'],
                        'email' => $email
                    ];
                }
            }
        }
        
        usort($targets, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        return $targets;
    }

    // --- Fonctions des widgets (getCalendarData, getMailStatsData) ---

    private function getCalendarData(string $accessToken, array $service): array
    {
        $start = date('c'); // e.g., 2025-11-01T18:46:48+00:00
        $end = date('c', strtotime('+1 day'));
        
        $userIdentifier = (!empty($service['description'])) ? trim($service['description']) : 'me';
        
        // --- FIX : AJOUT DE urlencode() ---
        // Les dates ISO 8601 contiennent un '+' qui doit être encodé en %2B
        $url = "https://graph.microsoft.com/v1.0/users/{$userIdentifier}/calendarview?startdatetime=" . urlencode($start) . "&enddatetime=" . urlencode($end) . "&\$orderby=start/dateTime&\$top=5";
        // --- FIN DU FIX ---
        
        $data = $this->getFromApi($url, $accessToken);
        
        if (isset($data['error'])) {
            return ['error' => $data['error']['message']];
        }
        
        $events = [];
        foreach ($data['value'] ?? [] as $event) {
            $events[] = [
                'subject' => $event['subject'],
                'start' => $event['start']['dateTime'],
                'end' => $event['end']['dateTime'],
                'isAllDay' => $event['isAllDay']
            ];
        }
        return ['events' => $events];
    }

    private function getMailStatsData(string $accessToken, array $service): array
    {
        $userIdentifier = (!empty($service['description'])) ? trim($service['description']) : 'me';
        
        if ($userIdentifier === 'me') {
            $url_count = "https://graph.microsoft.com/v1.0/me/messages/delta?\$select=id&\$top=1000";
            $data_count = $this->getFromApi($url_count, $accessToken);
            
            if (isset($data_count['error'])) {
                return ['error' => $data_count['error']['message']];
            }
            return [
                'message_count' => count($data_count['value'] ?? []),
                'next_link' => isset($data_count['@odata.nextLink'])
            ];
        } else {
             $date = date('Y-m-d'); // Ce format est sûr pour les URL
            $url = "https://graph.microsoft.com/v1.0/reports/getEmailActivityUserDetail(userPrincipalName='{$userIdentifier}')";
            $data = $this->getFromApi($url, $accessToken);

            if (isset($data['error'])) {
                return ['error' => "Erreur API Graph: " . $data['error']['message'] . ". (Permissions admin requises ?)" ];
            }
            
            return [
                'report_data' => $data['value'] ?? 'Données de rapport reçues'
            ];
        }
    }
    
    // --- Fonctions utilitaires cURL ---

    private function getFromApi(string $url, string $accessToken): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? ['error' => 'Réponse JSON invalide de l\'API Graph'];
    }
    
    private function postToApi(string $url, array $params): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? ['error' => 'Réponse JSON invalide du serveur d\'auth'];
    }
}
