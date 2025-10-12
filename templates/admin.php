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
                        <th>Nom</th><th>URL</th><th>Icône</th><th>Groupe</th><th>Actions</th>
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
                            <form method="post" onsubmit="return confirm('Êtes-vous sûr ?');">
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
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($edit_service['nom'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="url">URL</label>
                    <input type="url" id="url" name="url" value="<?= htmlspecialchars($edit_service['url'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="icone">Icône</label>
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
                    <label for="ordre_affichage">Ordre</label>
                    <input type="number" id="ordre_affichage" name="ordre_affichage" value="<?= htmlspecialchars($edit_service['ordre_affichage'] ?? '0') ?>" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn"><?= $edit_service ? 'Mettre à jour' : 'Ajouter' ?></button>
                    <?php if ($edit_service): ?>
                        <a href="/admin.php" class="cancel-btn">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
