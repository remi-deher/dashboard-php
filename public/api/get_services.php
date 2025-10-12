<?php
// Fichier: /public/api/get_services.php

header('Content-Type: application/json');

// Inclusion de notre connexion BDD
require_once __DIR__ . '/../../src/db_connection.php';

try {
    // On récupère tous les services, triés par groupe puis par ordre d'affichage
    $stmt = $pdo->query('SELECT nom, url, icone, description, groupe
                         FROM services
                         ORDER BY groupe, ordre_affichage, nom');
    $services = $stmt->fetchAll();

    // On réorganise le tableau pour avoir les groupes comme clés
    $grouped_services = [];
    foreach ($services as $service) {
        $group_name = $service['groupe'];
        if (!isset($grouped_services[$group_name])) {
            $grouped_services[$group_name] = [];
        }
        $grouped_services[$group_name][] = $service;
    }

    echo json_encode($grouped_services);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des services : ' . $e->getMessage()]);
}
