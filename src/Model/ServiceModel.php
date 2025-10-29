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

    // Récupère tous les services, triés pour la modale
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM services ORDER BY dashboard_id, groupe, ordre_affichage, nom');
        return $stmt->fetchAll();
    }

    // Récupère les services d'un dashboard spécifique, triés pour l'affichage
    public function getAllByDashboardId(int $dashboardId): array
    {
        $stmt = $this->pdo->prepare(
            // *** MODIFIÉ ICI *** : Tri par ordre_affichage
            'SELECT * FROM services WHERE dashboard_id = ? ORDER BY ordre_affichage, nom'
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
        // *** MODIFIÉ ICI *** : Ajout de size_class, retrait des gs_*
        $stmt = $this->pdo->prepare(
            'INSERT INTO services (nom, url, icone, description, groupe, ordre_affichage, dashboard_id, icone_url, card_color, size_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nom'],
            $data['url'],
            $data['icone'] ?? null,
            $data['description'] ?? null,
            $data['groupe'] ?? 'Général',
            $data['ordre_affichage'] ?? 0,
            $data['dashboard_id'],
            $data['icone_url'] ?? null,
            $data['card_color'] ?? null,
            $data['size_class'] ?? 'size-medium' // Ajouté
        ]);
    }

    public function update(int $id, array $data): void
    {
         // *** MODIFIÉ ICI *** : Ajout de size_class, retrait des gs_*
        $stmt = $this->pdo->prepare(
            'UPDATE services SET nom = ?, url = ?, icone = ?, description = ?, groupe = ?, ordre_affichage = ?, dashboard_id = ?, icone_url = ?, card_color = ?, size_class = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['nom'],
            $data['url'],
            $data['icone'] ?? null,
            $data['description'] ?? null,
            $data['groupe'] ?? 'Général',
            $data['ordre_affichage'] ?? 0,
            $data['dashboard_id'],
            $data['icone_url'] ?? null,
            $data['card_color'] ?? null,
            $data['size_class'] ?? 'size-medium', // Ajouté
            $id
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Met à jour uniquement le dashboard_id d'un service.
     * L'ordre sera mis à jour via une autre méthode (saveLayout).
     */
    public function updateDashboardId(int $serviceId, int $dashboardId): void
    {
        // On pourrait optionnellement mettre ordre_affichage à 0 ou MAX+1 ici,
        // mais la sauvegarde explicite de l'ordre via saveLayout est plus fiable.
        $stmt = $this->pdo->prepare(
            'UPDATE services SET dashboard_id = ? WHERE id = ?'
        );
        $stmt->execute([$dashboardId, $serviceId]);
    }

    /**
     * Met à jour l'ordre d'affichage des services pour un dashboard spécifique.
     * @param int $dashboardId L'ID du dashboard concerné.
     * @param array $orderedIds Tableau des IDs de service dans le nouvel ordre.
     */
    public function updateOrderForDashboard(int $dashboardId, array $orderedIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE services SET ordre_affichage = ? WHERE id = ? AND dashboard_id = ?'
            );
            foreach ($orderedIds as $index => $serviceId) {
                // Assurer que les IDs sont des entiers
                $serviceId = (int)$serviceId;
                if ($serviceId > 0) {
                    $stmt->execute([$index, $serviceId, $dashboardId]);
                }
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            // Loguer ou relancer l'exception
            throw $e;
        }
    }

     /**
      * Met à jour la classe de taille d'un service spécifique.
      * @param int $serviceId L'ID du service.
      * @param string $sizeClass La nouvelle classe CSS (ex: 'size-small').
      */
     public function updateSizeClass(int $serviceId, string $sizeClass): void
     {
         $stmt = $this->pdo->prepare(
             'UPDATE services SET size_class = ? WHERE id = ?'
         );
         $stmt->execute([$sizeClass, $serviceId]);
     }
}
