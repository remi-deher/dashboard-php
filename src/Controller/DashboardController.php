<?php
// Fichier: /src/Controller/DashboardController.php

namespace App\Controller;

use App\Model\ServiceModel;
use PDO;

class DashboardController
{
    private ServiceModel $serviceModel;
    private PDO $pdo;

    public function __construct(ServiceModel $serviceModel, PDO $pdo)
    {
        $this->serviceModel = $serviceModel;
        $this->pdo = $pdo;
    }

    // Affiche la page principale, charge les données nécessaires pour la vue et la modale
    public function index(?int $edit_service_id = null, ?int $edit_dashboard_id = null): void
    {
        $all_services = $this->serviceModel->getAll(); // Pour la liste dans la modale
        $all_dashboards = $this->pdo->query('SELECT * FROM dashboards ORDER BY ordre_affichage, nom')->fetchAll(PDO::FETCH_ASSOC); // Pour les onglets et la modale

        // Récupérer les paramètres globaux (thème, fond, etc.)
        $settings_raw = $this->pdo->query('SELECT * FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings['background_color'] = $settings_raw['background_color'] ?? '';
        $settings['background_image'] = $settings_raw['background_image'] ?? '';
        $settings['theme'] = $settings_raw['theme'] ?? 'default-dark'; // Récupérer le thème

        // Récupérer les données pour l'édition si un ID est passé en URL
        $edit_service = $edit_service_id ? $this->serviceModel->getById($edit_service_id) : null;
        $edit_dashboard = $edit_dashboard_id ? $this->getDashboardById($edit_dashboard_id) : null;

        // Inclure le template principal
        require __DIR__ . '/../../templates/dashboard.php';
    }

    // Méthodes pour afficher la modale ouverte sur le bon onglet via une URL spécifique
    public function showAdminForService(int $id): void
    {
        $this->index($id, null);
    }

    public function showAdminForDashboard(int $id): void
    {
        $this->index(null, $id);
    }

    // --- Méthodes pour les actions POST (Formulaires) ---

    public function addService(): void {
        $data = $_POST; // Récupère les données du formulaire

        // 1. Gérer l'upload d'icône personnalisée
        $data['icone_url'] = $this->handleUpload('icone_upload');

        // 2. Tenter de récupérer la favicon si aucune icône n'est fournie
        if (empty($data['icone_url']) && empty($data['icone'])) {
            $data['icone_url'] = $this->fetchAndCacheFavicon($data['url']);
        }

        // 3. Définir la taille par défaut si non fournie (peut être ajouté au formulaire plus tard)
        $data['size_class'] = $data['size_class'] ?? 'size-medium';

        // 4. Créer le service en BDD
        $this->serviceModel->create($data);

        // 5. Rediriger vers la page principale
        header('Location: /');
        exit;
    }

    public function updateService(int $id): void {
        $data = $_POST;
        $current = $this->serviceModel->getById($id); // Récupérer l'état actuel pour l'icône

        // 1. Gérer l'upload/suppression d'icône personnalisée
        $data['icone_url'] = $this->handleUpload('icone_upload', $current['icone_url'] ?? null, isset($_POST['remove_icone']));

        // 2. Tenter la favicon si pas d'icône perso/FA et qu'on ne supprime pas
        if (!isset($_POST['remove_icone']) && empty($_FILES['icone_upload']['name']) && empty($data['icone'])) {
             // Utiliser la valeur actuelle OU chercher une nouvelle favicon si l'URL a changé OU si l'ancienne URL était nulle
            $newFaviconIfNeeded = (isset($current['icone_url']) && strpos($current['icone_url'], '/assets/favicons/') === 0 && $current['url'] === $data['url'])
                ? $current['icone_url'] // Garder l'ancienne favicon si l'URL n'a pas changé
                : $this->fetchAndCacheFavicon($data['url']); // Chercher la nouvelle

            // $data['icone_url'] contient déjà le résultat de handleUpload (null si remove, inchangé sinon)
            // On utilise la favicon seulement si $data['icone_url'] est vide après handleUpload
            $data['icone_url'] = $data['icone_url'] ?: $newFaviconIfNeeded;
        }

        // 3. Assurer une valeur pour size_class
        $data['size_class'] = $data['size_class'] ?? $current['size_class'] ?? 'size-medium';

        // 4. Mettre à jour le service
        $this->serviceModel->update($id, $data);

        // 5. Rediriger
        header('Location: /');
        exit;
    }

    public function deleteService(int $id): void {
        $service = $this->serviceModel->getById($id);
        if ($service && !empty($service['icone_url'])) {
            // Supprime l'icône uploadée (pas les favicons)
            $this->handleUpload('', $service['icone_url'], true);
        }
        $this->serviceModel->delete($id);
        header('Location: /');
        exit;
    }

    public function addDashboard(): void {
        $icone_url = $this->handleUpload('icone_upload');
        // ordre_affichage est géré par JS Sortable, on peut mettre 0 ou un grand nombre par défaut
        $ordre = (int)($_POST['ordre_affichage'] ?? 9999);
        $stmt = $this->pdo->prepare("INSERT INTO dashboards (nom, icone, icone_url, ordre_affichage) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nom'],
            $_POST['icone'] ?? 'fas fa-th-large',
            $icone_url,
            $ordre
        ]);
        header('Location: /');
        exit;
    }

    public function updateDashboard(int $id): void {
        $current = $this->getDashboardById($id);
        if (!$current) { /* Gérer erreur */ header('Location: /'); exit; }

        $icone_url = $this->handleUpload('icone_upload', $current['icone_url'] ?? null, isset($_POST['remove_icone']));
        // L'ordre est principalement géré par JS, mais on peut le sauvegarder ici si fourni
        $ordre = isset($_POST['ordre_affichage']) ? (int)$_POST['ordre_affichage'] : $current['ordre_affichage'];

        $stmt = $this->pdo->prepare("UPDATE dashboards SET nom = ?, icone = ?, icone_url = ?, ordre_affichage = ? WHERE id = ?");
        $stmt->execute([
            $_POST['nom'],
            $_POST['icone'] ?? 'fas fa-th-large',
            $icone_url,
            $ordre,
            $id
        ]);
        header('Location: /');
        exit;
    }

    public function deleteDashboard(int $id): void {
        $dashboard = $this->getDashboardById($id);
        if (!$dashboard) { /* Gérer erreur */ header('Location: /'); exit; }

        // Supprimer l'icône uploadée si elle existe
        if (!empty($dashboard['icone_url'])) {
            $this->handleUpload('', $dashboard['icone_url'], true);
        }

        // Trouver un dashboard de repli (le premier par ordre d'affichage, ou le premier par ID si ordre non fiable)
        $fallbackStmt = $this->pdo->query("SELECT id FROM dashboards WHERE id != {$id} ORDER BY ordre_affichage, id LIMIT 1");
        $fallbackId = $fallbackStmt->fetchColumn();

        if ($fallbackId) {
            // Réassigner les services de ce dashboard vers le dashboard de repli
            $this->pdo->prepare("UPDATE services SET dashboard_id = ? WHERE dashboard_id = ?")->execute([$fallbackId, $id]);
        } else {
            // Cas où il ne reste qu'un seul dashboard (celui qu'on supprime)
            // On pourrait soit empêcher la suppression, soit supprimer les services orphelins.
            // Pour l'instant, on laisse les services (ils ne seront plus visibles).
            // Alternative: Supprimer les services: $this->pdo->prepare("DELETE FROM services WHERE dashboard_id = ?")->execute([$id]);
        }


        // Supprimer le dashboard lui-même
        $this->pdo->prepare("DELETE FROM dashboards WHERE id = ?")->execute([$id]);

        header('Location: /');
        exit;
    }

    public function saveSettings(): void {
        // Sauvegarde background_color
        $stmtColor = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('background_color', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmtColor->execute([$_POST['background_color'] ?? '']);

        // Gestion de l'image de fond
        $stmtImg = $this->pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'background_image'");
        $current_image = $stmtImg->fetchColumn();
        $image_url = $this->handleUpload('background_image', $current_image ?: null, isset($_POST['remove_background_image']));
        $stmtImgSave = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('background_image', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmtImgSave->execute([$image_url]);

        // Sauvegarde du thème sélectionné
        if (isset($_POST['theme'])) {
            $themeName = basename($_POST['theme']); // Sécurité de base
            $themePath = __DIR__ . '/../../public/assets/themes/' . $themeName;
            if (is_dir($themePath)) {
                $stmtTheme = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('theme', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmtTheme->execute([$themeName]);
            }
        }

        header('Location: /');
        exit;
    }

    // --- Méthodes pour les actions API (appelées par JS) ---

    /**
     * Sauvegarde l'ordre des services pour un dashboard donné.
     * Reçoit un JSON comme: { "dashboardId": 1, "ids": [3, 1, 2] }
     */
    public function saveLayout(): void {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($input['dashboardId']) || !isset($input['ids']) || !is_array($input['ids'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides ou manquantes.']);
            return;
        }

        $dashboardId = (int)$input['dashboardId'];
        $orderedIds = array_map('intval', $input['ids']); // S'assurer que ce sont des entiers

        if ($dashboardId <= 0) {
             http_response_code(400);
             echo json_encode(['error' => 'ID de dashboard invalide.']);
             return;
        }

        try {
            // Utiliser la nouvelle méthode du modèle
            $this->serviceModel->updateOrderForDashboard($dashboardId, $orderedIds);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            // Loguer l'erreur côté serveur est recommandé
            error_log("Erreur saveLayout: " . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors de la sauvegarde de l\'ordre.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Sauvegarde l'ordre des onglets (dashboards).
     * Reçoit un JSON comme: [1, 3, 2] (tableau d'IDs de dashboard dans l'ordre)
     */
    public function saveDashboardLayout(): void {
        header('Content-Type: application/json');
        $dashboardIds = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dashboardIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Données JSON invalides (attendu un tableau d\'IDs).']);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'UPDATE dashboards SET ordre_affichage = ? WHERE id = ?'
            );
            foreach ($dashboardIds as $index => $id) {
                // S'assurer que l'ID est un entier
                $id = (int)$id;
                if ($id > 0) {
                    $stmt->execute([$index, $id]);
                }
            }
            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            error_log("Erreur saveDashboardLayout: " . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors de la sauvegarde de l\'ordre des dashboards.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Déplace un service vers un autre dashboard (met à jour dashboard_id).
     * L'ordre dans le nouveau dashboard sera mis à jour via saveLayout séparément.
     */
    public function moveService(int $id, int $dashboardId): void {
        header('Content-Type: application/json');

        // Valider les IDs
        if ($id <= 0 || $dashboardId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de service ou de dashboard invalide.']);
            return;
        }

        try {
            // Utiliser la méthode simplifiée du modèle
            $this->serviceModel->updateDashboardId($id, $dashboardId);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Erreur moveService: " . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors du déplacement du service.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    // --- Méthodes privées utilitaires ---

    private function getDashboardById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM dashboards WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Tente de récupérer la favicon d'une URL et la met en cache.
     * Utilise l'API Google S2 pour une meilleure fiabilité.
     * @param string $url L'URL du service
     * @return string|null Le chemin public vers l'icône en cache, ou null si échec
     */
    private function fetchAndCacheFavicon(string $url): ?string
    {
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
            return null; // URL invalide
        }

        // Utiliser l'API de Google pour une récupération fiable (taille 64px)
        $faviconUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($domain) . "&sz=64";

        $cacheDir = __DIR__ . '/../../public/assets/favicons/';
        if (!is_dir($cacheDir)) {
            // Tenter de créer le dossier récursivement
            if (!mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
                 error_log('Impossible de créer le dossier cache favicon: ' . $cacheDir);
                 return null; // Échec création dossier
            }
        }

        $filename = md5($domain) . '.png'; // Nom de fichier basé sur le domaine
        $cachePath = $cacheDir . $filename;
        $publicPath = '/assets/favicons/' . $filename; // Chemin accessible par le navigateur

        // Mettre en cache pour 7 jours (604800 secondes)
        if (file_exists($cachePath) && (time() - filemtime($cachePath) < 604800)) {
             return $publicPath; // Utiliser le cache s'il est valide
        }

        // Tenter de télécharger l'icône
        try {
            $ch = curl_init($faviconUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout connexion 5s
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);      // Timeout total 10s
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Moins sûr, mais évite les pbs de cert
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            // Ajouter un User-Agent peut aider
            curl_setopt($ch, CURLOPT_USERAGENT, 'DashboardFaviconFetcher/1.0');

            $imageData = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code === 200 && $imageData && strlen($imageData) > 0) {
                // Vérifier si c'est bien une image valide (Google renvoie parfois un 1x1 pixel vide)
                // @ supprime les warnings si $imageData n'est pas une image valide
                if (@imagecreatefromstring($imageData) !== false) {
                     // Sauvegarder l'image dans le cache
                     if (file_put_contents($cachePath, $imageData) !== false) {
                         return $publicPath; // Succès
                     } else {
                         error_log('Impossible d\'écrire dans le cache favicon: ' . $cachePath);
                     }
                }
                // Si ce n'est pas une image valide, on considère comme un échec
            } else {
                 error_log("Erreur fetch favicon pour {$domain}: HTTP {$http_code}, cURL error: {$curl_error}");
            }
        } catch (\Exception $e) {
            // Gérer l'échec de cURL ou file_put_contents
            error_log("Exception fetch favicon pour {$domain}: " . $e->getMessage());
            return null;
        }

        return null; // Échec de la récupération ou sauvegarde
    }


    /**
     * Gère l'upload d'un fichier (icône personnalisée, image de fond).
     * Supprime l'ancien fichier s'il est remplacé ou si $remove est true.
     * Ne supprime PAS les favicons mises en cache.
     */
    private function handleUpload(string $fileKey, ?string $currentUrl = null, bool $remove = false): ?string
    {
        // Dossier 'uploads' pour les icônes/images personnalisées
        $uploadDir = __DIR__ . '/../../public/assets/uploads/';
        if (!is_dir($uploadDir)) {
             if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                  error_log('Impossible de créer le dossier uploads: ' . $uploadDir);
                  return $currentUrl; // Retourne l'URL actuelle en cas d'échec
             }
        }

        $isCustomUpload = $currentUrl && strpos($currentUrl, '/assets/uploads/') === 0;

        // Condition pour supprimer l'ancien fichier
        $shouldDeleteOld = $isCustomUpload && ($remove || (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK && $_FILES[$fileKey]['size'] > 0));

        if ($shouldDeleteOld) {
            $baseName = basename($currentUrl); // Sécurité
            if ($baseName && strpos($baseName, '..') === false) { // Éviter path traversal
                $filePath = realpath($uploadDir . $baseName);
                // Vérifier que le fichier est bien dans le dossier upload et existe avant de supprimer
                if ($filePath && strpos($filePath, realpath($uploadDir)) === 0 && file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            if ($remove) {
                return null; // Suppression explicite demandée
            }
        }

        // Vérifier s'il y a un nouveau fichier à uploader
        if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK || $_FILES[$fileKey]['size'] === 0) {
            // Aucun nouveau fichier uploadé, retourne l'URL actuelle (qui peut être null si $remove était true)
             return $shouldDeleteOld && $remove ? null : $currentUrl;
        }

        // Traiter le nouveau fichier
        $extension = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
        // Valider l'extension (sécurité basique)
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
             error_log("Type de fichier non autorisé uploadé: " . $extension);
             return $currentUrl; // Ignore l'upload
        }

        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destinationPath = $uploadDir . $safeFilename;

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destinationPath)) {
            return '/assets/uploads/' . $safeFilename; // Retourne le nouveau chemin public
        } else {
            error_log("Erreur lors du déplacement du fichier uploadé vers: " . $destinationPath);
            return $currentUrl; // Retourne l'URL actuelle en cas d'échec
        }
    }
}
