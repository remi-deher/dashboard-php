<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>

    <nav class="navbar">
        <div class="navbar-title">
            <i class="fas fa-gauge"></i>
            <span>Mon Dashboard</span>
        </div>

        <div class="navbar-search">
            <form action="https://www.google.com/search" method="get" target="_blank" class="search-form">
                <i class="fab fa-google"></i>
                <input type="search" name="q" placeholder="Rechercher sur Google..." required>
            </form>
        </div>

        <div class="navbar-actions">
            <a href="/admin.php" class="manage-btn">
                <i class="fas fa-cog"></i>
                <span>Gérer</span>
            </a>
        </div>
    </nav>

    <main id="dashboard-container">
        <p class="loading-message">Chargement des services...</p>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>
