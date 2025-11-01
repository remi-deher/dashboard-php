<?php
// Fichier: /templates/dashboard.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Dashboard</title>

    <link rel="stylesheet" href="/assets/themes/<?= htmlspecialchars($settings['theme'] ?? 'default-dark') ?>/style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        body {
            /* Styles de fond (gérés principalement par le thème) */
            <?php if (!empty($settings['background_color'])): ?>
            /* background-color: <?= htmlspecialchars($settings['background_color']) ?>; */ /* Peut surcharger le thème */
            <?php endif; ?>
            <?php if (!empty($settings['background_image'])): ?>
            /* background-image: url('<?= htmlspecialchars($settings['background_image']) ?>'); */ /* Peut surcharger le thème */
            /* background-size: cover; */
            /* background-position: center; */
            /* background-attachment: fixed; */
            <?php endif; ?>
        }
    </style>
</head>
<body>

    <button id="nav-arrow-left" class="nav-arrow" title="Dashboard précédent"><i class="fas fa-chevron-left"></i></button>
    <button id="nav-arrow-right" class="nav-arrow" title="Dashboard suivant"><i class="fas fa-chevron-right"></i></button>

    <div id="drop-zone-left" class="drop-zone">
        <i class="fas fa-chevron-left"></i>
        <span class="zone-label"></span>
    </div>
    <div id="drop-zone-right" class="drop-zone">
        <i class="fas fa-chevron-right"></i>
        <span class="zone-label"></span>
    </div>

    <nav id="dashboard-tabs-container"></nav>

    <header class="top-search-container">
        <form action="https://www.google.com/search" method="get" target="_blank" class="search-form">
            <i class="fab fa-google"></i>
            <input type="search" name="q" placeholder="Rechercher sur le web..." required>
        </form>
        <button id="open-settings-modal" class="manage-btn" title="Gérer les services">
            <i class="fas fa-cog"></i>
        </button>
    </header>

    <main id="dashboards-wrapper">
        </main>

    <?php require __DIR__ . '/partials/_modal_manage.php'; ?>
    <?php require __DIR__ . '/partials/_modal_add_dashboard.php'; ?>
    <?php require __DIR__ . '/partials/_modal_add_service.php'; ?>
    

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
    
    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/widget_renderers.js"></script>
    <script src="/assets/js/grid.js"></script>
    <script src="/assets/js/modal.js"></script>
    <script src="/assets/js/dashboard.js"></script> 
    <script src="/assets/js/main.js"></script>
    
    <button id="quick-add-service-fab" class="fab-add-service" title="Ajouter un service au dashboard actuel">
        <i class="fas fa-plus"></i>
    </button>

    <?php if ($settings['open_modal_to_widgets']): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ouvre la modale principale
            // Note: open-settings-modal est défini dans DOM (main.js)
            // et l'événement click est attaché dans initModals (modal.js)
            const openBtn = document.getElementById('open-settings-modal');
            if (openBtn) {
                openBtn.click();
            }
            
            // Bascule sur l'onglet "Widgets"
            // showModalTab est défini dans modal.js
            if (typeof showModalTab === 'function') {
                showModalTab('tab-widgets');
            } else {
                console.error('showModalTab() n\'est pas défini. Assurez-vous que modal.js est chargé.');
            }
            
            // Nettoie l'URL
            window.history.pushState({}, '', '/');
        });
    </script>
    <?php endif; ?>

</body>
</html>
