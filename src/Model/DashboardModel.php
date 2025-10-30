<?php
// Fichier: /src/Model/DashboardModel.php

namespace App\Model;

use PDO;

class DashboardModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM dashboards WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Récupère tous les dashboards, triés par ordre d'affichage
     */
    public function getAllOrdered(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM dashboards ORDER BY ordre_affichage, nom');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les champs minimum pour les onglets de l'API
     */
    public function getAllForTabs(): array
    {
        $stmt = $this->pdo->query('SELECT id, nom, icone, icone_url FROM dashboards ORDER BY ordre_affichage, nom');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouveau dashboard
     */
    public function create(string $nom, ?string $icone, ?string $icone_url, int $ordre): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO dashboards (nom, icone, icone_url, ordre_affichage) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([
            $nom,
            $icone ?? 'fas fa-th-large',
            $icone_url,
            $ordre
        ]);
    }

    /**
     * Met à jour un dashboard existant
     */
    public function update(int $id, string $nom, ?string $icone, ?string $icone_url, int $ordre): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE dashboards SET nom = ?, icone = ?, icone_url = ?, ordre_affichage = ? WHERE id = ?"
        );
        return $stmt->execute([
            $nom,
            $icone ?? 'fas fa-th-large',
            $icone_url,
            $ordre,
            $id
        ]);
    }

    /**
     * Supprime un dashboard
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM dashboards WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Trouve un dashboard de repli (le premier) pour y déplacer les services
     */
    public function getFallbackDashboardId(int $excludeId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM dashboards WHERE id != ? ORDER BY ordre_affichage, id LIMIT 1");
        $stmt->execute([$excludeId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    /**
     * Met à jour l'ordre d'affichage des dashboards
     */
    public function updateOrder(array $dashboardIds): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE dashboards SET ordre_affichage = ? WHERE id = ?'
            );
            foreach ($dashboardIds as $index => $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $stmt->execute([$index, $id]);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur updateOrder (DashboardModel): " . $e->getMessage());
            return false;
        }
    }
}
