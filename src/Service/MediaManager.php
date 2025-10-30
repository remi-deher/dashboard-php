<?php
// Fichier: /src/Service/MediaManager.php

namespace App\Service;

class MediaManager
{
    private string $uploadDir;
    private string $faviconCacheDir;
    private string $baseUploadPath = '/assets/uploads/';
    private string $baseFaviconPath = '/assets/favicons/';

    public function __construct(string $projectRoot)
    {
        // Définit les chemins absolus pour les opérations de fichiers
        $this->uploadDir = $projectRoot . '/public/assets/uploads/';
        $this->faviconCacheDir = $projectRoot . '/public/assets/favicons/';
    }

    /**
     * Tente de récupérer la favicon d'une URL et la met en cache.
     * @param string $url L'URL du service
     * @return string|null Le chemin public vers l'icône en cache, ou null si échec
     */
    public function fetchAndCacheFavicon(string $url): ?string
    {
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
            return null; // URL invalide
        }

        $faviconUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($domain) . "&sz=64";
        
        if (!$this->ensureDirectoryExists($this->faviconCacheDir)) {
             error_log('Impossible de créer le dossier cache favicon: ' . $this->faviconCacheDir);
             return null; // Échec création dossier
        }

        $filename = md5($domain) . '.png';
        $cachePath = $this->faviconCacheDir . $filename;
        $publicPath = $this->baseFaviconPath . $filename;

        // Mettre en cache pour 7 jours (604800 secondes)
        if (file_exists($cachePath) && (time() - filemtime($cachePath) < 604800)) {
             return $publicPath; // Utiliser le cache s'il est valide
        }

        // Tenter de télécharger l'icône
        try {
            $ch = curl_init($faviconUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);      
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'DashboardFaviconFetcher/1.0');

            $imageData = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code === 200 && $imageData && strlen($imageData) > 0) {
                if (@imagecreatefromstring($imageData) !== false) {
                     if (file_put_contents($cachePath, $imageData) !== false) {
                         return $publicPath; // Succès
                     } else {
                         error_log('Impossible d\'écrire dans le cache favicon: ' . $cachePath);
                     }
                }
            } else {
                 error_log("Erreur fetch favicon pour {$domain}: HTTP {$http_code}, cURL error: {$curl_error}");
            }
        } catch (\Exception $e) {
            error_log("Exception fetch favicon pour {$domain}: " . $e->getMessage());
            return null;
        }

        return null; // Échec
    }

    /**
     * Gère l'upload d'un fichier (icône personnalisée, image de fond).
     * Supprime l'ancien fichier s'il est remplacé ou si $remove est true.
     */
    public function handleUpload(string $fileKey, ?string $currentUrl = null, bool $remove = false): ?string
    {
        if (!$this->ensureDirectoryExists($this->uploadDir)) {
             error_log('Impossible de créer le dossier uploads: ' . $this->uploadDir);
             return $currentUrl; // Retourne l'URL actuelle en cas d'échec
        }

        // Vérifie si l'URL actuelle est un upload personnalisé (pas une favicon)
        $isCustomUpload = $currentUrl && strpos($currentUrl, $this->baseUploadPath) === 0;
        
        // Condition pour supprimer l'ancien fichier
        $shouldDeleteOld = $isCustomUpload && ($remove || (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK && $_FILES[$fileKey]['size'] > 0));

        if ($shouldDeleteOld) {
            $baseName = basename($currentUrl); 
            if ($baseName && strpos($baseName, '..') === false) { 
                $filePath = realpath($this->uploadDir . $baseName);
                // Vérifier que le fichier est bien dans le dossier upload et existe avant de supprimer
                if ($filePath && strpos($filePath, realpath($this->uploadDir)) === 0 && file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            if ($remove) {
                return null; // Suppression explicite demandée
            }
        }

        // Vérifier s'il y a un nouveau fichier à uploader
        if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK || $_FILES[$fileKey]['size'] === 0) {
             return $shouldDeleteOld && $remove ? null : $currentUrl;
        }

        // Traiter le nouveau fichier
        $extension = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
             error_log("Type de fichier non autorisé uploadé: " . $extension);
             return $currentUrl; // Ignore l'upload
        }

        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destinationPath = $this->uploadDir . $safeFilename;

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destinationPath)) {
            return $this->baseUploadPath . $safeFilename; // Retourne le nouveau chemin public
        } else {
            error_log("Erreur lors du déplacement du fichier uploadé vers: " . $destinationPath);
            return $currentUrl; // Retourne l'URL actuelle en cas d'échec
        }
    }
    
    /**
     * S'assure qu'un dossier existe, sinon tente de le créer.
     */
    private function ensureDirectoryExists(string $directoryPath): bool
    {
        if (is_dir($directoryPath)) {
            return true;
        }
        // Tenter de créer récursivement
        return mkdir($directoryPath, 0755, true);
    }
}
