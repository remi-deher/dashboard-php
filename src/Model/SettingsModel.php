<?php
// Fichier: /src/Model/SettingsModel.php

namespace App\Model;

use PDO;

class SettingsModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère tous les paramètres sous forme de tableau clé => valeur
     */
    public function getAllAsKeyPair(): array
    {
        return $this->pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Récupère une valeur de paramètre unique
     */
    public function get(string $key): ?string
    {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    /**
     * Crée ou met à jour un paramètre
     */
    public function save(string $key, ?string $value): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        return $stmt->execute([$key, $value]);
    }
}
