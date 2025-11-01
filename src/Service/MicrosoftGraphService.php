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

    public function __construct(SettingsModel $settingsModel, string $redirectUri, array $config)
    {
        $this->settingsModel = $settingsModel;
        $this->redirectUri = $redirectUri;
        
        $this->clientId = $config['m365_client_id'] ?? '';
        $this->clientSecret = $config['m365_client_secret'] ?? '';
        $this->tenantId = $config['m365_tenant_id'] ?? 'common'; // 'common' pour multi-tenant
        $this->refreshToken = $config['m365_refresh_token'] ?? null;
    }

    /**
     * Point d'entrée pour tous les widgets M365.
     * Il route vers la bonne méthode en fonction du type de widget.
     */
    public function getWidgetData(array $service): array
    {
        if (empty($this->refreshToken)) {
            return ['error' => 'M365 non connecté. Allez dans Widgets > Se connecter.'];
        }
        
        // On rafraîchit le token d'accès
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['error' => 'Impossible de rafraîchir le token M365. Reconnectez-vous.'];
        }

        switch ($service['widget_type']) {
            case 'm365_calendar':
                return $this->getCalendarData($accessToken);
            case 'm365_mail_stats':
                return $this->getMailStatsData($accessToken);
            default:
                return ['error' => 'Type de widget M365 non supporté'];
        }
    }

    /**
     * Génère l'URL de connexion pour l'administrateur.
     */
    public function getAuthUrl(): string
    {
        $scopes = [
            'offline_access', // Nécessaire pour le refresh token
            'User.Read',      // Pour info de base
            'Calendars.Read', // Pour le widget Calendrier
            'Mail.ReadBasic', // Pour les stats de mail (non admin)
            'Reports.Read.All' // Pour les stats admin (mails envoyés/reçus)
        ];
        
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => implode(' ', $scopes),
            'state' => '12345' // Devrait être un token CSRF
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
            'scope' => 'offline_access User.Read Calendars.Read Mail.ReadBasic Reports.Read.All',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'client_secret' => $this->clientSecret,
        ];

        $data = $this->postToApi($url, $params);

        if (isset($data['refresh_token'])) {
            // Succès ! On sauvegarde le refresh token dans la BDD
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
        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $params = [
            'client_id' => $this->clientId,
            'scope' => 'offline_access User.Read Calendars.Read Mail.ReadBasic Reports.Read.All',
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
            'client_secret' => $this->clientSecret,
        ];
        
        $data = $this->postToApi($url, $params);
        
        if (isset($data['access_token'])) {
            return $data['access_token'];
        }

        // Si le refresh token est expiré, on le supprime pour forcer une reconnexion
        if (isset($data['error'])) {
            error_log("Erreur getAccessToken M365: " . $data['error_description']);
            $this->settingsModel->save('m365_refresh_token', null);
        }
        return null;
    }

    // --- Squelettes pour les widgets ---

    private function getCalendarData(string $accessToken): array
    {
        $start = date('c'); // Maintenant
        $end = date('c', strtotime('+1 day')); // Dans 24h
        
        $url = "https://graph.microsoft.com/v1.0/me/calendarview?startdatetime={$start}&enddatetime={$end}&\$orderby=start/dateTime&\$top=5";
        $data = $this->getFromApi($url, $accessToken);
        
        if (isset($data['error'])) {
            return ['error' => $data['error']['message']];
        }
        
        // Formater les données pour le frontend
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

    private function getMailStatsData(string $accessToken): array
    {
        // Exemple : Récupérer les rapports d'activité utilisateur (nécessite les permissions admin)
        // Ceci est un exemple, l'endpoint exact dépend de ce que vous voulez.
        $date = date('Y-m-d');
        $url = "https://graph.microsoft.com/v1.0/reports/getEmailActivityUserDetail(date={$date})";
        $data = $this->getFromApi($url, $accessToken);

        if (isset($data['error'])) {
            return ['error' => "Erreur API Graph: " . $data['error']['message'] . ". (Avez-vous les permissions admin ?)" ];
        }
        
        // $data['value'] contient un CSV, il faut le parser.
        // C'est complexe, pour l'instant retournons juste un compte.
        // NOTE: Cet endpoint spécifique retourne du CSV, pas du JSON.
        // Un endpoint plus simple :
        $url_count = "https://graph.microsoft.com/v1.0/me/messages/delta?\$select=id&\$top=1000";
        $data_count = $this->getFromApi($url_count, $accessToken);
         if (isset($data_count['error'])) {
            return ['error' => $data_count['error']['message']];
        }

        return [
            'message_count' => count($data_count['value'] ?? []),
            'next_link' => isset($data_count['@odata.nextLink'])
        ];
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
