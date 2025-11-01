<?php
// Fichier: /templates/partials/_modal_manage.php
?>
<div id="settings-modal" class="modal-container" style="display: none;">
    <div class="modal-content">
        <header class="modal-header">
            <h1><i class="fas fa-cog"></i> Panneau de Gestion</h1>
            <button id="close-settings-modal" class="close-modal-btn">&times;</button>
        </header>

        <div class="modal-tabs">
            <button class="modal-tab-btn active" data-tab="tab-dashboards">Dashboards</button>
            <button class="modal-tab-btn" data-tab="tab-services">Services</button>
            <button class="modal-tab-btn" data-tab="tab-widgets">Widgets</button>
            <button class="modal-tab-btn" data-tab="tab-settings">Paramètres</button>
        </div>

        <div class="modal-body">

            <div id="tab-dashboards" class="modal-tab-content active">
                <section>
                    <h2>Gestion des Dashboards</h2>
                    <p>Vous pouvez réorganiser les dashboards en les glissant-déposant dans la barre d'onglets.</p>
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
                                        <i class="<?= htmlspecialchars($dashboard['icone'] ?? 'fas fa-th-large') ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="/dashboard/edit/<?= $dashboard['id'] ?>" class="edit-btn">Modifier</a>
                                    <form method="post" action="/dashboard/delete/<?= $dashboard['id'] ?>" onsubmit="return confirm('Supprimer ce dashboard ? Les services seront réassignés au premier dashboard disponible.');">
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
                        <input type="hidden" name="ordre_affichage" value="<?= htmlspecialchars($edit_dashboard['ordre_affichage'] ?? '9999') ?>">
                        <button type="submit" class="submit-btn"><?= $edit_dashboard ? 'Mettre à jour' : 'Ajouter' ?></button>
                        <?php if ($edit_dashboard): ?><a href="/" class="cancel-btn">Annuler</a><?php endif; ?>
                    </form>
                </section>
            </div>

            <div id="tab-services" class="modal-tab-content">
                <section>
                    <h2>Gestion des Services</h2>
                    <table>
                        <thead><tr><th>Nom</th><th>Groupe</th><th>Dashboard</th><th>Ordre</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($all_services as $service): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($service['icone_url'])): ?>
                                        <img src="<?= htmlspecialchars($service['icone_url']) ?>" alt="Icône" class="current-icon-preview">
                                    <?php elseif (!empty($service['icone'])): ?>
                                        <i class="<?= htmlspecialchars($service['icone']) ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-link"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($service['nom']) ?>
                                    <?php if($service['widget_type'] !== 'link'): ?>
                                        <small style="color: var(--text-muted-color); font-size: 0.8em; display: block; margin-left: 28px;">(Widget: <?= htmlspecialchars($service['widget_type']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($service['groupe'] ?? 'Général') ?></td>
                                <td>
                                    <?php
                                    $dashboardName = 'N/A';
                                    foreach($all_dashboards as $d) {
                                        if($d['id'] == $service['dashboard_id']) {
                                            $dashboardName = $d['nom'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($dashboardName);
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($service['ordre_affichage'] ?? '0') ?></td>
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
                            <label>Type de service</label>
                            <select name="widget_type">
                                <option value="link" <?= !isset($edit_service['widget_type']) || $edit_service['widget_type'] === 'link' ? 'selected' : '' ?>>
                                    Lien simple (avec statut)
                                </option>
                                
                                <?php // Liste des widgets
                                $widgets = [
                                    'xen_orchestra' => 'Widget Xen Orchestra',
                                    'glances' => 'Widget Glances',
                                    'proxmox' => 'Widget Proxmox',
                                    'portainer' => 'Widget Portainer',
                                    'm365_calendar' => 'Widget M365 Calendrier',
                                    'm365_mail_stats' => 'Widget M365 Mail Stats'
                                ];
                                
                                foreach ($widgets as $type => $label): ?>
                                <option value="<?= $type ?>" <?= (isset($edit_service['widget_type']) && $edit_service['widget_type'] === $type) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Dashboard</label>
                            <select name="dashboard_id" required>
                                <?php
                                $firstDashboardId = $all_dashboards[0]['id'] ?? '';
                                $selectedDashboardId = $edit_service['dashboard_id'] ?? $firstDashboardId;
                                foreach ($all_dashboards as $dashboard): ?>
                                <option value="<?= $dashboard['id'] ?>" <?= ($selectedDashboardId == $dashboard['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dashboard['nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($edit_service['nom'] ?? '') ?>" required></div>
                        <div class="form-group">
                            <label>URL</label>
                            <input type="url" name="url" value="<?= htmlspecialchars($edit_service['url'] ?? '') ?>" required>
                            <small>Pour les liens, c'est la cible. Pour les widgets (Glances, Proxmox...), c'est l'URL de l'hôte/API (ex: http://192.168.1.50:61208).</small>
                        </div>
                        <div class="form-group"><label>Icône Font Awesome</label><input type="text" name="icone" value="<?= htmlspecialchars($edit_service['icone'] ?? '') ?>" placeholder="ex: fas fa-server"></div>
                        <div class="form-group">
                            <label>...ou téléverser une icône personnalisée</label>
                            <?php if (!empty($edit_service['icone_url']) && strpos($edit_service['icone_url'], '/assets/uploads/') === 0): ?>
                                <img src="<?= htmlspecialchars($edit_service['icone_url']) ?>" alt="Icône actuelle" class="current-icon-preview">
                                <label><input type="checkbox" name="remove_icone"> Supprimer l'icône actuelle</label>
                            <?php elseif (!empty($edit_service['icone_url'])): ?>
                                 <img src="<?= htmlspecialchars($edit_service['icone_url']) ?>" alt="Favicon actuelle" class="current-icon-preview">
                                 <small>(Favicon détectée)</small>
                            <?php endif; ?>
                            <input type="file" name="icone_upload" accept="image/png, image/jpeg, image/svg+xml, image/webp">
                            <small>Si aucun des deux n'est fourni, la favicon du site sera utilisée (pour les liens).</small>
                        </div>
                        <div class="form-group"><label>Couleur personnalisée</label> <input type="color" name="card_color" value="<?= htmlspecialchars($edit_service['card_color'] ?? '#ffffff') ?>"> </div>
                        <input type="hidden" name="size_class" value="<?= htmlspecialchars($edit_service['size_class'] ?? 'size-medium') ?>">
                        <div class="form-group"><label>Groupe</label><input type="text" name="groupe" value="<?= htmlspecialchars($edit_service['groupe'] ?? 'Général') ?>"></div>
                        <input type="hidden" name="ordre_affichage" value="<?= htmlspecialchars($edit_service['ordre_affichage'] ?? '0') ?>">
                        <div class="form-group"><label>Description</label><textarea name="description"><?= htmlspecialchars($edit_service['description'] ?? '') ?></textarea></div>

                        <div class="form-actions">
                            <button type="submit" class="submit-btn"><?= $edit_service ? 'Mettre à jour' : 'Ajouter' ?></button>
                            <?php if ($edit_service): ?><a href="/" class="cancel-btn">Annuler</a><?php endif; ?>
                        </div>
                    </form>
                </section>
            </div>

            <div id="tab-widgets" class="modal-tab-content">
                <section>
                    <h2>Paramètres des Widgets</h2>
                    <form id="widget-settings-form" method="post" action="/settings/save" enctype="multipart/form-data">
                        
                        <h3>Xen Orchestra</h3>
                        <div class="form-group">
                            <label for="xen_orchestra_host">Hôte XOA</label>
                            <input type="text" id="xen_orchestra_host" name="xen_orchestra_host" value="<?= htmlspecialchars($settings['xen_orchestra_host'] ?? '') ?>" placeholder="https://mon-xoa.domaine.com">
                        </div>
                        <div class="form-group">
                            <label for="xen_orchestra_token">Token d'API XOA</label>
                            <input type="password" id="xen_orchestra_token" name="xen_orchestra_token" value="<?= htmlspecialchars($settings['xen_orchestra_token'] ?? '') ?>" placeholder="Collez votre token ici">
                            <small>Créez un token dans votre profil XOA (User > My account > API tokens).</small>
                        </div>
                        
                        <hr style="border-color: var(--border-color); margin: 25px 0;">
                        
                        <h3>Proxmox</h3>
                        <div class="form-group">
                            <label for="proxmox_token_id">Token ID Proxmox</label>
                            <input type="text" id="proxmox_token_id" name="proxmox_token_id" value="<?= htmlspecialchars($settings['proxmox_token_id'] ?? '') ?>" placeholder="utilisateur@pve!mon-token">
                        </div>
                        <div class="form-group">
                            <label for="proxmox_token_secret">Token Secret Proxmox</label>
                            <input type="password" id="proxmox_token_secret" name="proxmox_token_secret" value="<?= htmlspecialchars($settings['proxmox_token_secret'] ?? '') ?>" placeholder="UUID (ex: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)">
                        </div>
                        
                        <hr style="border-color: var(--border-color); margin: 25px 0;">

                        <h3>Portainer</h3>
                        <div class="form-group">
                            <label for="portainer_api_key">Clé d'API Portainer</label>
                            <input type="password" id="portainer_api_key" name="portainer_api_key" value="<?= htmlspecialchars($settings['portainer_api_key'] ?? '') ?>" placeholder="Collez votre clé d'API Portainer ici">
                            <small>Créez une clé dans Portainer (User > My account > API tokens).</small>
                        </div>
                        
                        <hr style="border-color: var(--border-color); margin: 25px 0;">

                        <h3>Microsoft 365 (Graph API)</h3>
                        <div class="form-group">
                            <label for="m365_tenant_id">ID de Locataire (Tenant ID)</label>
                            <input type="text" id="m365_tenant_id" name="m365_tenant_id" value="<?= htmlspecialchars($settings['m365_tenant_id'] ?? 'common') ?>" placeholder="common (défaut) ou votre ID de tenant">
                        </div>
                        <div class="form-group">
                            <label for="m365_client_id">ID d'Application (Client ID)</label>
                            <input type="text" id="m365_client_id" name="m365_client_id" value="<?= htmlspecialchars($settings['m365_client_id'] ?? '') ?>" placeholder="Client ID de votre App Azure">
                        </div>
                        <div class="form-group">
                            <label for="m365_client_secret">Secret Client</label>
                            <input type="password" id="m365_client_secret" name="m365_client_secret" value="<?= htmlspecialchars($settings['m365_client_secret'] ?? '') ?>" placeholder="Collez votre secret client ici">
                        </div>
                        
                        <small>URL de rappel à entrer dans Azure : <code><?= htmlspecialchars($base_url . '/auth/m365/callback') ?></code></small>
                        
                        <div class="form-actions" style="margin-top: 15px;">
                            <?php if (empty($settings['m365_refresh_token'])): ?>
                                <a href="/auth/m365/connect" class="edit-btn" style="background-color: #0078d4;">Se connecter à Microsoft 365</a>
                            <?php else: ?>
                                <span style="color: var(--status-online); display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-check-circle"></i> Connecté à Microsoft 365
                                </span>
                                <a href="/auth/m365/connect" class="cancel-btn">Se reconnecter</a>
                            <?php endif; ?>
                        </div>
                        
                        <hr style="border-color: var(--border-color); margin: 25px 0;">
                        
                        <div class="form-actions">
                            <button type="submit" class="submit-btn">Enregistrer les Paramètres Widgets</button>
                        </div>
                    </form>
                </section>
            </div>

            <div id="tab-settings" class="modal-tab-content">
                <section>
                    <h2>Paramètres Généraux</h2>
                    <form id="settings-form" method="post" action="/settings/save" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="theme_selector">Thème</label>
                            <select id="theme_selector" name="theme">
                                <?php
                                    $themesDir = __DIR__ . '/../../public/assets/themes/';
                                    $availableThemes = array_filter(glob($themesDir . '*'), 'is_dir');
                                    foreach ($availableThemes as $themePath) {
                                        $themeName = basename($themePath);
                                        $selected = (($settings['theme'] ?? 'default-dark') === $themeName) ? 'selected' : '';
                                        $displayName = ucwords(str_replace(['-', '_'], ' ', $themeName));
                                        echo "<option value=\"" . htmlspecialchars($themeName) . "\" $selected>" . htmlspecialchars($displayName) . "</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="background_color">Couleur de fond (CSS)</label>
                            <input type="text" id="background_color" name="background_color" value="<?= htmlspecialchars($settings['background_color'] ?? '') ?>" placeholder="ex: #161b22 ou linear-gradient(...)">
                            <small>Surcharge la couleur de fond du thème si défini.</small>
                        </div>
                        <div class="form-group">
                            <label for="background_image">Image de fond</label>
                            <?php if (!empty($settings['background_image'])): ?>
                                 <img src="<?= htmlspecialchars($settings['background_image']) ?>" alt="Image actuelle" style="max-width: 100px; max-height: 50px; display: block; margin-bottom: 5px;">
                                <label><input type="checkbox" name="remove_background_image"> Supprimer l'image de fond</label>
                            <?php endif; ?>
                            <input type="file" id="background_image" name="background_image" accept="image/png, image/jpeg, image/webp">
                             <small>Surcharge l'image de fond du thème si défini.</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="submit-btn">Enregistrer les Paramètres Généraux</button>
                        </div>
                    </form>
                </section>
            </div>

        </div>
    </div>
</div>

<?php // Gérer l'ouverture de la modale après l'auth
if ($settings['open_modal_to_widgets']): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ouvre la modale principale
        document.getElementById('open-settings-modal').click();
        // Bascule sur l'onglet "Widgets"
        showModalTab('tab-widgets');
        // Nettoie l'URL pour ne pas ré-ouvrir à chaque rechargement
        window.history.pushState({}, '', '/');
    });
</script>
<?php endif; ?>
