document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('dashboard-container');

    /**
     * Vérifie le statut d'un service et met à jour l'UI.
     * @param {object} service - L'objet service contenant l'URL.
     * @param {HTMLElement} cardElement - L'élément de la carte du service.
     */
    const checkServiceStatus = (service, cardElement) => {
        const statusDot = cardElement.querySelector('.status-dot');
        if (!statusDot) return;

        // On encode l'URL pour la passer en paramètre GET
        const fetchUrl = `/api/check_status.php?url=${encodeURIComponent(service.url)}`;

        fetch(fetchUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('La réponse du serveur de statut n\'est pas OK');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'online') {
                    statusDot.classList.add('online');
                } else {
                    statusDot.classList.add('offline');
                }
            })
            .catch(error => {
                console.error(`Erreur de statut pour ${service.nom}:`, error);
                statusDot.classList.add('offline'); // On considère offline en cas d'erreur
            });
    };

    /**
     * Récupère les services et construit le dashboard.
     */
    const buildDashboard = () => {
        fetch('/api/get_services.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('La réponse du serveur des services n\'est pas OK');
                }
                return response.json();
            })
            .then(groups => {
                container.innerHTML = ''; // Vide le message de chargement

                // Pour chaque groupe reçu du PHP...
                for (const groupName in groups) {
                    // 1. Crée le titre du groupe
                    const groupTitle = document.createElement('h2');
                    groupTitle.className = 'group-title';
                    groupTitle.textContent = groupName;
                    container.appendChild(groupTitle);

                    // 2. Crée la grille pour les services
                    const grid = document.createElement('div');
                    grid.className = 'service-grid';

                    // 3. Pour chaque service dans le groupe...
                    groups[groupName].forEach(service => {
                        const card = document.createElement('a');
                        card.className = 'service-card';
                        card.href = service.url;
                        card.target = '_blank';
                        card.title = service.description || service.nom;

                        card.innerHTML = `
                            <div class="status-dot"></div>
                            <div class="icon">
                                <i class="${service.icone || 'fas fa-link'}"></i>
                            </div>
                            <div class="title">${service.nom}</div>
                        `;
                        grid.appendChild(card);

                        // 4. On lance la vérification du statut pour cette carte
                        checkServiceStatus(service, card);
                    });
                    
                    container.appendChild(grid);
                }
            })
            .catch(error => {
                console.error("Erreur lors de la construction du dashboard :", error);
                container.innerHTML = '<p class="loading-message" style="color: var(--status-offline);">Impossible de charger les services.</p>';
            });
    };

    // Lancement de la construction de la page
    buildDashboard();
});
