<?php
// Fichier: /src/Model/ServiceModel.php

class ServiceModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ==========================================================
    // MÉTHODE MANQUANTE : C'EST LA CAUSE DE VOTRE ERREUR
    // Elle permet aux autres parties du code d'accéder à la connexion PDO.
    // ==========================================================
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM services ORDER BY groupe, ordre_affichage, nom');
        return $stmt->fetchAll();
    }

    // =================================================================
    // NOUVELLE MÉTHODE : Assurez-vous qu'elle est bien présente aussi
    // =================================================================
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
        return $result ?: null;
    }

    // ==========================================================
    // MÉTHODE MODIFIÉE : Ajout de 'dashboard_id'
    // ==========================================================
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
            $data['dashboard_id'] // Champ ajouté
        ]);
    }

    // ==========================================================
    // MÉTHODE MODIFIÉE : Ajout de 'dashboard_id'
    // ==========================================================
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
            $data['dashboard_id'], // Champ ajouté
            $id
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$id]);
    }
}
