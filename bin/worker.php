<?php
// Fichier: /bin/worker.php
// (À exécuter en ligne de commande: php bin/worker.php)

// 1. Chargement de l'environnement
require_once __DIR__ . '/../vendor/autoload.php';
$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    die("Erreur: Le fichier de configuration 'config/config.php' est manquant.\n");
}
require_once __DIR__ . '/../src/db_connection.php'; // $pdo
if (!isset($pdo)) {
     die("Erreur critique: La connexion PDO n'a pas pu être établie.\n");
}

echo "Démarrage du worker...\n";

// 2. Namespaces (identiques à index.php)
use App\Model\ServiceModel;
use App\Model\SettingsModel;
use App\Service\WidgetServiceRegistry;
use App\Service\XenOrchestraService;
use App\Service\Widget\GlancesService;
use App\Service\MicrosoftGraphService;
use Predis\Client as RedisClient;

// 3. Initialisation des services
try {
    $settingsModel = new SettingsModel($pdo);
    $serviceModel = new ServiceModel($pdo);
    $db_settings = $settingsModel->getAllAsKeyPair();
    
    // Connexion Redis
    $redis = new RedisClient('tcp://127.0.0.1:6379');
    echo "Connecté à Redis.\n";

    // --- Enregistrement des Widgets (comme dans index.php) ---
    $widgetRegistry = new WidgetServiceRegistry();

    $xenOrchestraService = new XenOrchestraService(
        $db_settings['xen_orchestra_host'] ?? null,
        $db_settings['xen_orchestra_token'] ?? null
    );
    $widgetRegistry->register('xen_orchestra', $xenOrchestraService);

    $glancesService = new GlancesService(); 
    $widgetRegistry->register('glances', $glancesService);

    // M365 (nécessite l'URL de base, mais non critique pour le worker)
    $microsoftGraphService = new MicrosoftGraphService(
        $settingsModel,
        'http://localhost/auth/m365/callback', // L'URI de rappel n'est pas utilisée ici
        [
            'm365_client_id' => $db_settings['m365_client_id'] ?? '',
            'm365_client_secret' => $db_settings['m365_client_secret'] ?? '',
            'm365_tenant_id' => $db_settings['m365_tenant_id'] ?? 'common',
            'm365_refresh_token' => $db_settings['m365_refresh_token'] ?? null
        ]
    );
    $widgetRegistry->register('m365_calendar', $microsoftGraphService);
    $widgetRegistry->register('m365_mail_stats', $microsoftGraphService);
    // --- Fin enregistrement ---

    echo "Worker initialisé. Démarrage de la boucle principale (toutes les 60s).\n";

    // 4. La boucle infinie du worker
    while (true) {
        $services = $serviceModel->getAll();
        echo "Cycle de rafraîchissement démarré pour " . count($services) . " services.\n";

        foreach ($services as $service) {
            if ($service['widget_type'] === 'link' || empty($service['widget_type'])) {
                continue;
            }

            $widgetService = $widgetRegistry->getService($service['widget_type']);
            if (!$widgetService) {
                echo "  [!] Service '{$service['widget_type']}' non trouvé (ID: {$service['id']}).\n";
                continue;
            }

            // C'est l'appel lent (Xen, M365, etc.)
            $data = $widgetService->getWidgetData($service);
            $jsonPayload = json_encode($data);
            $cacheKey = "widget:data:" . $service['id'];

            $oldPayload = $redis->get($cacheKey);

            // Ne met à jour et ne publie que si les données ont changé
            if ($jsonPayload !== $oldPayload) {
                echo "  [+] Données mises à jour pour le service {$service['id']} ({$service['nom']}).\n";
                
                // Mettre à jour le cache
                $redis->set($cacheKey, $jsonPayload);
                
                // Publier la notification (le 'serviceId' est le message)
                $redis->publish("widget_updates", $service['id']);
            } else {
                 // echo "  [=] Données inchangées pour le service {$service['id']}.\n";
            }
            
            // Éviter de surcharger les API externes
            sleep(1); 
        }

        echo "Cycle terminé. En attente 60s...\n\n";
        sleep(60); // Attendre 1 minute avant le prochain cycle
    }

} catch (\Exception $e) {
    die("Erreur fatale du worker: " . $e->getMessage() . "\n");
}
