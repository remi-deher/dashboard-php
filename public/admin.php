<?php
// On inclut notre connexion à la base de données
require_once __DIR__ . '/../src/db_connection.php';

// ===================================================================
// PARTIE 1 : TRAITEMENT DES FORMULAIRES (AJOUT, MODIFICATION, SUPPRESSION)
// ===================================================================

// On vérifie si le formulaire a été soumis (méthode POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action : AJOUTER un service
    if ($action === 'add') {
        $stmt = $pdo->prepare(
            'INSERT INTO services (nom, url, icone, description, groupe, ordre_affichage) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_POST['nom'],
            $_POST['url'],
            $_POST['icone'],
            $_POST['description'],
            $_POST['groupe'],
            $_POST['ordre_affichage']
        ]);
    }

    // Action : MODIFIER un service
    if ($action === 'update') {
        $stmt = $pdo->prepare(
            'UPDATE services SET nom = ?, url = ?, icone = ?, description = ?, groupe = ?, ordre_affichage = ? WHERE id = ?'
        );
        $stmt->execute([
            $_POST['nom'],
            $_POST['url'],
            $_POST['icone'],
            $_POST['description'],
            $_POST['groupe'],
            $_POST['ordre_affichage'],
            $_POST['id']
        ]);
    }

    // Action : SUPPRIMER un service
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$_POST['id']]);
    }

    // Redirection pour éviter la resoumission du formulaire (Pattern PRG)
    header('Location: /admin.php');
    exit;
}

// ===================================================================
// PARTIE 2 : PRÉPARATION DES DONNÉES POUR L'AFFICHAGE
// ===================================================================

$edit_service = null;
// Si on a un paramètre 'edit' dans l'URL, on récupère les infos du service à modifier
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$_GET['edit']]);
    $edit_service = $stmt->fetch();
}

// On récupère TOUS les services pour les afficher dans le tableau
$all_services = $pdo->query('SELECT * FROM services ORDER BY groupe, ordre_affichage, nom')->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

    <div class="admin-container">
        <header class="admin-header">
            <h1><i class="fas fa-cog"></i> Panneau de Gestion</h1>
            <a href="/" class="back-btn">&larr; Retour au Dashboard</a>
        </header>

        <section class="services-list">
            <h2>Services Actuels</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>URL</th>
                        <th>Icône</th>
                        <th>Groupe</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_services as $service): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['nom']) ?></td>
                        <td><a href="<?= htmlspecialchars($service['url']) ?>" target="_blank"><?= htmlspecialchars($service['url']) ?></a></td>
                        <td><i class="<?= htmlspecialchars($service['icone']) ?>"></i> (<?= htmlspecialchars($service['icone']) ?>)</td>
                        <td><?= htmlspecialchars($service['groupe']) ?></td>
                        <td class="actions">
                            <a href="admin.php?edit=<?= $service['id'] ?>" class="edit-btn">Modifier</a>
                            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                <button type="submit" class="delete-btn">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="service-form">
            <h2><?= $edit_service ? 'Modifier le service' : 'Ajouter un nouveau service' ?></h2>
            
            <form method="post">
                <input type="hidden" name="action" value="<?= $edit_service ? 'update' : 'add' ?>">
                <?php if ($edit_service): ?>
                    <input type="hidden" name="id" value="<?= $edit_service['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nom">Nom du service</label>
                    <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($edit_service['nom'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="url">URL</label>
                    <input type="url" id="url" name="url" value="<?= htmlspecialchars($edit_service['url'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="icone">Icône (classe Font Awesome)</label>
                    <input type="text" id="icone" name="icone" value="<?= htmlspecialchars($edit_service['icone'] ?? '') ?>" placeholder="ex: fas fa-server">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($edit_service['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="groupe">Groupe</label>
                    <input type="text" id="groupe" name="groupe" value="<?= htmlspecialchars($edit_service['groupe'] ?? 'Général') ?>" required>
                </div>
                <div class="form-group">
                    <label for="ordre_affichage">Ordre d'affichage</label>
                    <input type="number" id="ordre_affichage" name="ordre_affichage" value="<?= htmlspecialchars($edit_service['ordre_affichage'] ?? '0') ?>" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-btn"><?= $edit_service ? 'Mettre à jour' : 'Ajouter le service' ?></button>
                    <?php if ($edit_service): ?>
                        <a href="/admin.php" class="cancel-btn">Annuler la modification</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

    </div>

</body>
</html>
