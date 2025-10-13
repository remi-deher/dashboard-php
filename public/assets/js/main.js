document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.getElementById('dashboard-tabs-container');
    const servicesContainer = document.getElementById('dashboard-container');
    const themeSwitcher = document.getElementById('theme-switcher');
    let statusRefreshInterval = null;

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

    const checkServiceStatus = (serviceUrl, cardElement) => {
        if (!serviceUrl || !cardElement) return;

        const statusIndicator = cardElement.querySelector('.card-status');
        const latencyIndicator = cardElement.querySelector('.card-latency');
        if(statusIndicator) statusIndicator.classList.add('checking');

        const fetchUrl = `/api/status/check?url=${encodeURIComponent(serviceUrl)}`;
        
        fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                cardElement.classList.remove('online', 'offline');
                cardElement.classList.add(data.status === 'online' ? 'online' : 'offline');
                
                if (latencyIndicator) {
                    if (data.status === 'online') {
                        latencyIndicator.innerHTML = `<span>${data.ttfb}ms</span> <i class="fas fa-info-circle"></i>`;
                        latencyIndicator.title = `Temps de réponse (TTFB). Connexion: ${data.connect_time}ms`;
                    } else {
                        latencyIndicator.innerHTML = `<span>N/A</span>`;
                        latencyIndicator.title = `Service hors ligne`;
                    }
                }
            })
            .catch(error => {
                console.error(`Erreur de statut pour ${serviceUrl}:`, error);
                cardElement.classList.remove('online');
                cardElement.classList.add('offline');
                if (latencyIndicator) {
                    latencyIndicator.innerHTML = `<span>Erreur</span>`;
                    latencyIndicator.title = `Impossible de vérifier le statut`;
                }
            })
            .finally(() => {
                if(statusIndicator) statusIndicator.classList.remove('checking');
            });
    };

    const startStatusRefresh = () => {
        if (statusRefreshInterval) clearInterval(statusRefreshInterval);
        
        statusRefreshInterval = setInterval(() => {
            document.querySelectorAll('.service-card').forEach(card => {
                checkServiceStatus(card.dataset.url, card);
            });
        }, 60000); // Rafraîchit toutes les 60 secondes
    };

    const buildServicesGrid = (dashboardId) => {
        if (!dashboardId) return;

        document.querySelectorAll('.dashboard-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.id == dashboardId);
        });

        servicesContainer.style.opacity = '0';
        
        setTimeout(() => {
            servicesContainer.innerHTML = '<p class="loading-message">Chargement des services...</p>';
            servicesContainer.style.opacity = '1';

            fetch(`/api/services?dashboard_id=${dashboardId}`)
                .then(response => response.json())
                .then(groups => {
                    servicesContainer.innerHTML = '';
                    if (Object.keys(groups).length === 0) {
                         servicesContainer.innerHTML = '<p class="loading-message">Ce dashboard est vide.</p>';
                         return;
                    }
                    
                    let cardAnimationDelay = 0;
                    for (const groupName in groups) {
                        const groupTitle = document.createElement('h2');
                        groupTitle.className = 'group-title collapsible';
                        groupTitle.innerHTML = `<i class="fas fa-chevron-down"></i> ${groupName}`;
                        servicesContainer.appendChild(groupTitle);

                        const grid = document.createElement('div');
                        grid.className = 'service-grid';

                        groups[groupName].forEach(service => {
                            const card = document.createElement('a');
                            card.className = `service-card ${service.card_size || 'medium'}`;
                            card.href = service.url;
                            card.dataset.url = service.url;
                            card.target = '_blank';
                            card.title = service.description || service.nom;
                            card.style.animationDelay = `${cardAnimationDelay}ms`;
                            cardAnimationDelay += 30;

                            if (service.card_color) {
                                card.style.setProperty('--card-bg-color-custom', service.card_color);
                                card.classList.add('custom-color');
                            }

                            let iconHtml = service.icone_url
                                ? `<img src="${service.icone_url}" class="card-icon-custom" alt="${service.nom}">`
                                : `<i class="${service.icone || 'fas fa-link'}"></i>`;

                            card.innerHTML = `
                                <div class="card-status"></div>
                                <div class="card-latency">...</div>
                                <div class="card-icon">${iconHtml}</div>
                                <div class="card-title">${service.nom}</div>`;
                            grid.appendChild(card);
                            checkServiceStatus(service.url, card);
                        });
                        
                        servicesContainer.appendChild(grid);

                        groupTitle.addEventListener('click', () => {
                            grid.classList.toggle('collapsed');
                            groupTitle.querySelector('i').classList.toggle('fa-chevron-down');
                            groupTitle.querySelector('i').classList.toggle('fa-chevron-right');
                        });
                    }
                    startStatusRefresh();
                })
                .catch(error => {
                    console.error("Erreur:", error);
                });
        }, 200);
    };

    const loadTabsAndFirstDashboard = () => {
        fetch('/api/dashboards')
            .then(response => response.json())
            .then(dashboards => {
                if (dashboards.length === 0) {
                    servicesContainer.innerHTML = '<p class="loading-message">Aucun dashboard configuré.</p>';
                    return;
                }
                tabsContainer.innerHTML = '';
                dashboards.forEach(db => {
                    const tab = document.createElement('button');
                    tab.className = 'dashboard-tab';
                    tab.dataset.id = db.id;
                    
                    let iconHtml = db.icone_url
                        ? `<img src="${db.icone_url}" class="tab-icon-custom" alt="${db.nom}">`
                        : `<i class="${db.icone || 'fas fa-th-large'}"></i>`;
                    
                    tab.innerHTML = `${iconHtml} ${db.nom}`;
                    tab.onclick = () => buildServicesGrid(db.id);
                    tabsContainer.appendChild(tab);
                });
                buildServicesGrid(dashboards[0].id);
            });
    };

    loadTabsAndFirstDashboard();
});
