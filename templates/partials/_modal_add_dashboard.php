<?php // Fichier: /templates/partials/_modal_add_dashboard.php ?>
<div id="quick-add-dashboard-modal" class="modal-container" style="display: none;">
    <div class="modal-content quick-modal-content">
        <header class="modal-header">
            <h1>Ajouter un dashboard</h1>
            <button id="close-quick-add-dashboard-modal" class="close-modal-btn">&times;</button>
        </header>
        <div class="quick-modal-body">
            <form method="post" action="/dashboard/add" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nom du dashboard</label>
                    <input type="text" name="nom" placeholder="ex: Travail" required>
                </div>
                <div class="form-group">
                    <label>Icône Font Awesome (optionnel)</label>
                    <input type="text" name="icone" placeholder="ex: fas fa-briefcase">
                </div>
                <div class="form-group">
                    <label>...ou téléverser une icône (optionnel)</label>
                    <input type="file" name="icone_upload" accept="image/png, image/jpeg, image/svg+xml, image/webp">
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>
