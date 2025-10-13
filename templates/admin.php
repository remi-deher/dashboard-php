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

        <section>
            <h2>Gestion des Dashboards</h2>
            <table>
                <thead><tr><th>Nom</th><th>Icône</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($all_dashboards as $dashboard): ?>
                    <tr>
                        <td><?= htmlspecialchars($dashboard['nom']) ?></td>
                        <td><i class="<?= htmlspecialchars($dashboard['icone']) ?>"></i> (<?= htmlspecialchars($dashboard['icone']) ?>)</td>
                        <td class="actions">
                            <a href="admin.php?edit_dashboard=<?= $dashboard['id'] ?>" class="edit-btn">Modifier</a>
                            <form method="post" onsubmit="return confirm('Supprimer ce dashboard ? Les services seront réassignés.');">
                                <input type="hidden" name="action" value="delete_dashboard">
                                <input type="hidden" name="id" value="<?= $dashboard['id'] ?>">
                                <button type="submit" class="delete-btn">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3><?= $edit_dashboard ? 'Modifier le dashboard' : 'Ajouter un dashboard' ?></h3>
            <form method="post" class="compact-form">
                <input type="hidden" name="action" value="<?= $edit_dashboard ? 'update_dashboard' : 'add_dashboard' ?>">
                <?php if ($edit_dashboard): ?><input type="hidden" name="id" value="<?= $edit_dashboard['id'] ?>"><?php endif; ?>
                <input type="text" name="nom" placeholder="Nom du dashboard" value="<?= htmlspecialchars($edit_dashboard['nom'] ?? '') ?>" required>
                <input type="text" name="icone" placeholder="Icône Font Awesome" value="<?= htmlspecialchars($edit_dashboard['icone'] ?? 'fas fa-th-large') ?>">
                <input type="number" name="ordre_affichage" placeholder="Ordre" value="<?= htmlspecialchars($edit_dashboard['ordre_affichage'] ?? '0') ?>">
                <button type="submit" class="submit-btn"><?= $edit_dashboard ? 'Mettre à jour' : 'Ajouter' ?></button>
                <?php if ($edit_dashboard): ?><a href="/admin.php" class="cancel-btn">Annuler</a><?php endif; ?>
            </form>
        </section>

        <section>
            <h2>Gestion des Services</h2>
            <table>
                <thead><tr><th>Nom</th><th>URL</th><th>Groupe</th><th>Dashboard</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($all_services as $service): ?>
                    <tr>
                        <td><i class="<?= htmlspecialchars($service['icone']) ?>"></i> <?= htmlspecialchars($service['nom']) ?></td>
                        <td><a href="<?= htmlspecialchars($service['url']) ?>" target="_blank">Lien</a></td>
                        <td><?= htmlspecialchars($service['groupe']) ?></td>
                        <td>
                            <?php foreach($all_dashboards as $d) { if($d['id'] == $service['dashboard_id']) echo htmlspecialchars($d['nom']); } ?>
                        </td>
                        <td class="actions">
                            <a href="admin.php?edit_service=<?= $service['id'] ?>" class="edit-btn">Modifier</a>
                            <form method="post" onsubmit="return confirm('Supprimer ce service ?');">
                                <input type="hidden" name="action" value="delete_service">
                                <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                <button type="submit" class="delete-btn">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3><?= $edit_service ? 'Modifier le service' : 'Ajouter un service' ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="<?= $edit_service ? 'update_service' : 'add_service' ?>">
                <?php if ($edit_service): ?><input type="hidden" name="id" value="<?= $edit_service['id'] ?>"><?php endif; ?>
                
                <div class="form-group">
                    <label>Dashboard</label>
                    <select name="dashboard_id" required>
                        <?php foreach ($all_dashboards as $dashboard): ?>
                        <option value="<?= $dashboard['id'] ?>" <?= (($edit_service['dashboard_id'] ?? '') == $dashboard['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dashboard['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($edit_service['nom'] ?? '') ?>" required></div>
                <div class="form-group"><label>URL</label><input type="url" name="url" value="<?= htmlspecialchars($edit_service['url'] ?? '') ?>" required></div>
                <div class="form-group"><label>Icône</label><input type="text" name="icone" value="<?= htmlspecialchars($edit_service['icone'] ?? '') ?>" placeholder="ex: fas fa-server"></div>
                <div class="form-group"><label>Groupe</label><input type="text" name="groupe" value="<?= htmlspecialchars($edit_service['groupe'] ?? 'Général') ?>" required></div>
                <div class="form-group"><label>Ordre</label><input type="number" name="ordre_affichage" value="<?= htmlspecialchars($edit_service['ordre_affichage'] ?? '0') ?>" required></div>
                <div class="form-group"><label>Description</label><textarea name="description"><?= htmlspecialchars($edit_service['description'] ?? '') ?></textarea></div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-btn"><?= $edit_service ? 'Mettre à jour' : 'Ajouter' ?></button>
                    <?php if ($edit_service): ?><a href="/admin.php" class="cancel-btn">Annuler</a><?php endif; ?>
                </div>
            </form>
        </section>

        <section>
            <h2>Paramètres Généraux</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_settings">
                <div class="form-group">
                    <label for="background">Fond d'écran (propriété CSS `background`)</label>
                    <input type="text" id="background" name="background" value="<?= htmlspecialchars($settings['background'] ?? '') ?>" placeholder="ex: #161b22 ou url('/assets/images/bg.jpg')">
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Enregistrer les paramètres</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
