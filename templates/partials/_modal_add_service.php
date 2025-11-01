<?php // Fichier: /templates/partials/_modal_add_service.php ?>
<div id="quick-add-service-modal" class="modal-container" style="display: none;">
    <div class="modal-content quick-modal-content">
        <header class="modal-header">
            <h1>Ajouter un service</h1>
            <button id="close-quick-add-service-modal" class="close-modal-btn">&times;</button>
        </header>
        <div class="quick-modal-body">
            <form method="post" action="/service/add" enctype="multipart/form-data" data-form="quick-add">
                <input type="hidden" name="dashboard_id" id="quick-add-service-dashboard-id" value="">
                
                <div class="form-group">
                    <label>Type de service</label>
                    <select name="widget_type" data-role="widget-type-select">
                        <option value="link" selected>Lien simple (avec statut)</option>
                        <option value="xen_orchestra">Widget Xen Orchestra</option>
                        <option value="glances">Widget Glances</option>
                        <option value="proxmox">Widget Proxmox</option>
                        <option value="portainer">Widget Portainer</option>
                        <option value="m365_calendar">Widget M365 Calendrier</option>
                        <option value="m365_mail_stats">Widget M365 Mail Stats</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Nom du service</label>
                    <input type="text" name="nom" placeholder="ex: Mon Serveur" required>
                </div>
                <div class="form-group">
                    <label>URL</label>
                    <input type="url" name="url" placeholder="https://..." required>
                    <small>Pour les liens, c'est la cible. Pour les widgets (Glances, Proxmox...), c'est l'URL de l'hôte/API (ex: http://192.168.1.50:61208).</small>
                </div>
                <div class="form-group">
                    <label>Icône Font Awesome (optionnel)</label>
                    <input type="text" name="icone" placeholder="ex: fas fa-server">
                    <small>Si laissé vide, une favicon sera recherchée (pour les liens).</small>
                </div>
                
                <div class="form-group" data-role="description-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Description du service..."></textarea>
                    <small data-role="description-help-text">Pour les widgets M365, ce champ sera remplacé par un sélecteur.</small>
                </div>
                
                <div class="form-group" data-role="m365-target-group" style="display: none;">
                    <label>Cible M365 (Utilisateur ou Groupe)</label>
                    <select name="description">
                        </select>
                    <small>Liste des utilisateurs et groupes de votre tenant.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>
