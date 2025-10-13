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
            /* La couleur est une base, l'image la remplace si elle existe */
            background-color: <?= htmlspecialchars($background_color ?? 'var(--bg-color)') ?>;
            <?php if (!empty($background_image)): ?>
            background-image: url('<?= htmlspecialchars($background_image) ?>');
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
        <a href="/admin" class="manage-btn" title="Gérer les services">
            <i class="fas fa-cog"></i>
        </a>
    </header>

    <main id="dashboard-container">
        <p class="loading-message">Chargement...</p>
    </main>

    <script src="/assets/js/main.js"></script>
</body>
</html>
