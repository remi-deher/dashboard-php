<?php
// Fichier: /src/db_connection.php

// On charge le fichier de configuration
$config = require __DIR__ . '/../config/config.php';

$db_config = $config['database'];

$dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], $options);
} catch (\PDOException $e) {
     // Gérer l'erreur proprement (logs, etc.)
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
