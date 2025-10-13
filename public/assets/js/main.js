document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.getElementById('dashboard-tabs-container');
    const servicesContainer = document.getElementById('dashboard-container');
    const themeSwitcher = document.getElementById('theme-switcher');

    // --- LOGIQUE DU SÉLECTEUR DE THÈME ---
    const currentTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', currentTheme);
    if (currentTheme === 'light') {
        themeSwitcher.innerHTML = '<i class="fas fa-moon"></i>';
    } else {
        themeSwitcher.innerHTML = '<i class="fas fa-sun"></i>';
    }

    themeSwitcher.addEventListener('click', () => {
        let theme = document.documentElement.getAttribute('data-theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
            themeSwitcher.innerHTML = '<i class="fas fa-moon"></i>';
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            themeSwitcher.innerHTML = '<i class="fas fa-sun"></i>';
        }
    });
    // --- FIN DE LA LOGIQUE DU THÈME ---

    const checkServiceStatus = (service, cardElement) => {
        const fetchUrl = `/api/?action=checkStatus&url=${encodeURIComponent(service.url)}`;
        fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                cardElement.classList.add(data.status === 'online' ? 'online' : 'offline');
            })
            .catch(error => {
                console.error(`Erreur de statut pour ${service.nom}:`, error);
                cardElement.classList.add('offline');
            });
    };

    const buildServicesGrid = (dashboardId) => {
        if (!dashboardId) return;

        // Met en surbrillance l'onglet actif
        document.querySelectorAll('.dashboard-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.id == dashboardId);
        });

        // Animation de transition
        servicesContainer.style.opacity = '0';
        
        setTimeout(() => {
            servicesContainer.innerHTML = '<p class="loading-message">Chargement des services...</p>';
            servicesContainer.style.opacity = '1';

            // On demande les services pour un dashboard spécifique
            fetch(`/api/?action=getServices&dashboard_id=${dashboardId}`)
                .then(response => response.json())
                .then(groups => {
                    servicesContainer.innerHTML = '';
                    if (Object.keys(groups).length === 0) {
                         servicesContainer.innerHTML = '<p class="loading-message">Ce dashboard est vide.</p>';
                    }
                    
                    let cardAnimationDelay = 0;
                    for (const groupName in groups) {
                        const groupTitle = document.createElement('h2');
                        groupTitle.className = 'group-title';
                        groupTitle.textContent = groupName;
                        servicesContainer.appendChild(groupTitle);

                        const grid = document.createElement('div');
                        grid.className = 'service-grid';

                        groups[groupName].forEach(service => {
                            const card = document.createElement('a');
                            card.className = 'service-card';
                            card.href = service.url;
                            card.target = '_blank';
                            card.title = service.description || service.nom;
                            // Ajout d'un décalage pour l'animation d'entrée
                            card.style.animationDelay = `${cardAnimationDelay}ms`;
                            cardAnimationDelay += 30; // 30ms de décalage entre chaque carte

                            card.innerHTML = `
                                <div class="card-icon"><i class="${service.icone || 'fas fa-link'}"></i></div>
                                <div class="card-title">${service.nom}</div>`;
                            grid.appendChild(card);
                            checkServiceStatus(service, card);
                        });
                        
                        servicesContainer.appendChild(grid);
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la construction du dashboard:", error);
                    servicesContainer.innerHTML = '<p class="loading-message" style="color: var(--status-offline);">Impossible de charger les services.</p>';
                });
        }, 200); // Délai pour que l'animation de fondu soit visible
    };

    const loadTabsAndFirstDashboard = () => {
        fetch('/api/?action=getDashboards')
            .then(response => response.json())
            .then(dashboards => {
                if (dashboards.length === 0) {
                    servicesContainer.innerHTML = '<p class="loading-message">Aucun dashboard configuré. Allez dans le panneau de gestion.</p>';
                    return;
                }
                tabsContainer.innerHTML = '';
                dashboards.forEach(db => {
                    const tab = document.createElement('button');
                    tab.className = 'dashboard-tab';
                    tab.dataset.id = db.id;
                    tab.innerHTML = `<i class="${db.icone}"></i> ${db.nom}`;
                    tab.onclick = () => buildServicesGrid(db.id);
                    tabsContainer.appendChild(tab);
                });
                buildServicesGrid(dashboards[0].id);
            });
    };

    loadTabsAndFirstDashboard();
});
