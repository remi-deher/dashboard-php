<?php // Fichier: /templates/partials/_modal_add_service.php ?>
<div id="quick-add-service-modal" class="modal-container" style="display: none;">
    <div class="modal-content quick-modal-content">
        <header class="modal-header">
            <h1>Ajouter un service</h1>
            <button id="close-quick-add-service-modal" class="close-modal-btn">&times;</button>
        </header>
        <div class="quick-modal-body">
            <form method="post" action="/service/add" enctype="multipart/form-data">
                <input type="hidden" name="dashboard_id" id="quick-add-service-dashboard-id" value="">
                
                <div class="form-group">
                    <label>Nom du service</label>
                    <input type="text" name="nom" placeholder="ex: Mon Serveur" required>
                </div>
                <div class="form-group">
                    <label>URL</label>
                    <input type="url" name="url" placeholder="https://..." required>
                </div>
                <div class="form-group">
                    <label>Icône Font Awesome (optionnel)</label>
                    <input type="text" name="icone" placeholder="ex: fas fa-server">
                    <small>Si laissé vide, une favicon sera recherchée.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>
