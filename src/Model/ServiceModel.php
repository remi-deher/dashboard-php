<?php
// Fichier: /src/Model/ServiceModel.php

namespace App\Model;

use PDO;

class ServiceModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM services ORDER BY groupe, ordre_affichage, nom');
        return $stmt->fetchAll();
    }

    public function getAllByDashboardId(int $dashboardId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM services WHERE dashboard_id = ? ORDER BY groupe, ordre_affichage, nom'
        );
        $stmt->execute([$dashboardId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM services WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO services (nom, url, icone, description, groupe, ordre_affichage, dashboard_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nom'],
            $data['url'],
            $data['icone'],
            $data['description'],
            $data['groupe'],
            $data['ordre_affichage'],
            $data['dashboard_id']
        ]);
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE services SET nom = ?, url = ?, icone = ?, description = ?, groupe = ?, ordre_affichage = ?, dashboard_id = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['nom'],
            $data['url'],
            $data['icone'],
            $data['description'],
            $data['groupe'],
            $data['ordre_affichage'],
            $data['dashboard_id'],
            $id
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * NOUVEAU: Met à jour le dashboard_id ET la position/taille de la tuile.
     */
    public function updateDashboardIdAndLayout(int $serviceId, int $dashboardId, ?int $x, ?int $y, ?int $w, ?int $h): void
    {
        // La requête sauvegarde maintenant la position et la taille
        $stmt = $this->pdo->prepare(
            'UPDATE services SET dashboard_id = ?, gs_x = ?, gs_y = ?, gs_width = ?, gs_height = ? WHERE id = ?'
        );
        $stmt->execute([$dashboardId, $x, $y, $w, $h, $serviceId]);
    }
}
