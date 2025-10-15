<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Dashboard</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        body {
            /* On utilise les nouvelles variables passées par le DashboardController */
            background-color: <?= htmlspecialchars($settings['background_color'] ?? 'var(--bg-color)') ?>;
            <?php if (!empty($settings['background_image'])): ?>
            background-image: url('<?= htmlspecialchars($settings['background_image']) ?>');
            <?php endif; ?>
            
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
    </style>
</head>
<body>

    <nav id="dashboard-tabs-container"></nav>

    <header class="top-search-container">
        <button id="theme-switcher" class="manage-btn" title="Changer le thème">
            <i class="fas fa-sun"></i>
        </button>
        <form action="https://www.google.com/search" method="get" target="_blank" class="search-form">
            <i class="fab fa-google"></i>
            <input type="search" name="q" placeholder="Rechercher sur le web..." required>
        </form>
        <button id="open-settings-modal" class="manage-btn" title="Gérer les services">
            <i class="fas fa-cog"></i>
        </button>
    </header>

    <main id="dashboard-container">
        <p class="loading-message">Chargement...</p>
    </main>
    
    <div id="settings-modal" class="modal-container" style="display: none;">
        <div class="modal-content">
            <header class="modal-header">
                <h1><i class="fas fa-cog"></i> Panneau de Gestion</h1>
                <button id="close-settings-modal" class="close-modal-btn">&times;</button>
            </header>
            
            <div class="modal-body">
                <section>
                    <h2>Gestion des Dashboards</h2>
                    <table>
                        <thead><tr><th>Nom</th><th>Icône</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($all_dashboards as $dashboard): ?>
                            <tr>
                                <td><?= htmlspecialchars($dashboard['nom']) ?></td>
                                <td>
                                    <?php if (!empty($dashboard['icone_url'])): ?>
                                        <img src="<?= htmlspecialchars($dashboard['icone_url']) ?>" alt="Icône" class="current-icon-preview">
                                    <?php else: ?>
                                        <i class="<?= htmlspecialchars($dashboard['icone']) ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="/dashboard/edit/<?= $dashboard['id'] ?>" class="edit-btn">Modifier</a>
                                    <form method="post" action="/dashboard/delete/<?= $dashboard['id'] ?>" onsubmit="return confirm('Supprimer ce dashboard ? Les services seront réassignés.');">
                                        <button type="submit" class="delete-btn">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3><?= $edit_dashboard ? 'Modifier le dashboard' : 'Ajouter un dashboard' ?></h3>
                    <form method="post" action="<?= $edit_dashboard ? '/dashboard/update/' . $edit_dashboard['id'] : '/dashboard/add' ?>" class="compact-form" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($edit_dashboard['id'] ?? '') ?>">
                        <input type="text" name="nom" placeholder="Nom du dashboard" value="<?= htmlspecialchars($edit_dashboard['nom'] ?? '') ?>" required>
                        <input type="text" name="icone" placeholder="Icône Font Awesome (ex: fas fa-home)" value="<?= htmlspecialchars($edit_dashboard['icone'] ?? 'fas fa-th-large') ?>">
                        
                        <div class="form-group">
                            <label>...ou téléverser une icône personnalisée</label>
                            <?php if (!empty($edit_dashboard['icone_url'])): ?>
                                <img src="<?= htmlspecialchars($edit_dashboard['icone_url']) ?>" alt="Icône actuelle" class="current-icon-preview">
                                <label><input type="checkbox" name="remove_icone"> Supprimer l'icône actuelle</label>
                            <?php endif; ?>
                            <input type="file" name="icone_upload" accept="image/png, image/jpeg, image/svg+xml, image/webp">
                        </div>

                        <input type="number" name="ordre_affichage" placeholder="Ordre" value="<?= htmlspecialchars($edit_dashboard['ordre_affichage'] ?? '0') ?>">
                        <button type="submit" class="submit-btn"><?= $edit_dashboard ? 'Mettre à jour' : 'Ajouter' ?></button>
                        <?php if ($edit_dashboard): ?><a href="/" class="cancel-btn">Annuler</a><?php endif; ?>
                    </form>
                </section>

                <section>
                    <h2>Gestion des Services</h2>
                    <table>
                        <thead><tr><th>Nom</th><th>Groupe</th><th>Dashboard</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($all_services as $service): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($service['icone_url'])): ?>
                                        <img src="<?= htmlspecialchars($service['icone_url']) ?>" alt="Icône" class="current-icon-preview">
                                    <?php else: ?>
                                        <i class="<?= htmlspecialchars($service['icone']) ?>"></i>
                                    <?php endif; ?>
                                     <?= htmlspecialchars($service['nom']) ?>
                                </td>
                                <td><?= htmlspecialchars($service['groupe']) ?></td>
                                <td>
                                    <?php foreach($all_dashboards as $d) { if($d['id'] == $service['dashboard_id']) echo htmlspecialchars($d['nom']); } ?>
                                </td>
                                <td class="actions">
                                    <a href="/service/edit/<?= $service['id'] ?>" class="edit-btn">Modifier</a>
                                    <form method="post" action="/service/delete/<?= $service['id'] ?>" onsubmit="return confirm('Supprimer ce service ?');">
                                        <button type="submit" class="delete-btn">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3><?= $edit_service ? 'Modifier le service' : 'Ajouter un service' ?></h3>
                    <form method="post" action="<?= $edit_service ? '/service/update/' . $edit_service['id'] : '/service/add' ?>" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($edit_service['id'] ?? '') ?>">
                        
                        <div class="form-group">
                            <label>Dashboard</label>
                            <select name="dashboard_id" required>
                                <?php foreach ($all_dashboards as $dashboard): ?>
                                <option value="<?= $dashboard['id'] ?>" <?= (($edit_service['dashboard_id'] ?? ($all_dashboards[0]['id'] ?? '')) == $dashboard['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dashboard['nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($edit_service['nom'] ?? '') ?>" required></div>
                        <div class="form-group"><label>URL</label><input type="url" name="url" value="<?= htmlspecialchars($edit_service['url'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Icône Font Awesome</label><input type="text" name="icone" value="<?= htmlspecialchars($edit_service['icone'] ?? '') ?>" placeholder="ex: fas fa-server"></div>
                        <div class="form-group">
                            <label>...ou téléverser une icône personnalisée</label>
                            <?php if (!empty($edit_service['icone_url'])): ?>
                                <img src="<?= htmlspecialchars($edit_service['icone_url']) ?>" alt="Icône actuelle" class="current-icon-preview">
                                <label><input type="checkbox" name="remove_icone"> Supprimer l'icône actuelle</label>
                            <?php endif; ?>
                            <input type="file" name="icone_upload" accept="image/png, image/jpeg, image/svg+xml, image/webp">
                        </div>

                        <div class="form-group"><label>Taille de la carte</label> <select name="card_size"> <option value="small" <?= (($edit_service['card_size'] ?? '') == 'small') ? 'selected' : '' ?>>Petite</option> <option value="medium" <?= (($edit_service['card_size'] ?? 'medium') == 'medium') ? 'selected' : '' ?>>Moyenne</option> <option value="large" <?= (($edit_service['card_size'] ?? '') == 'large') ? 'selected' : '' ?>>Grande</option> </select> </div>
                        <div class="form-group"><label>Couleur personnalisée</label> <input type="color" name="card_color" value="<?= htmlspecialchars($edit_service['card_color'] ?? '#ffffff') ?>"> </div>

                        <div class="form-group"><label>Groupe</label><input type="text" name="groupe" value="<?= htmlspecialchars($edit_service['groupe'] ?? 'Général') ?>" required></div>
                        <div class="form-group"><label>Ordre</label><input type="number" name="ordre_affichage" value="<?= htmlspecialchars($edit_service['ordre_affichage'] ?? '0') ?>" required></div>
                        <div class="form-group"><label>Description</label><textarea name="description"><?= htmlspecialchars($edit_service['description'] ?? '') ?></textarea></div>
                        
                        <div class="form-actions">
                            <button type="submit" class="submit-btn"><?= $edit_service ? 'Mettre à jour' : 'Ajouter' ?></button>
                            <?php if ($edit_service): ?><a href="/" class="cancel-btn">Annuler</a><?php endif; ?>
                        </div>
                    </form>
                </section>

                <section>
                    <h2>Paramètres Généraux</h2>
                    <form method="post" action="/settings/save" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="background_color">Couleur de fond</label>
                            <input type="text" id="background_color" name="background_color" value="<?= htmlspecialchars($settings['background_color'] ?? '') ?>" placeholder="ex: #161b22 ou linear-gradient(...)">
                        </div>

                        <div class="form-group">
                            <label for="background_image">Image de fond</label>
                             <?php if (!empty($settings['background_image'])): ?>
                                <label><input type="checkbox" name="remove_background_image"> Supprimer l'image de fond</label>
                            <?php endif; ?>
                            <input type="file" id="background_image" name="background_image" accept="image/png, image/jpeg, image/webp">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="submit-btn">Enregistrer</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
</body>
</html>
