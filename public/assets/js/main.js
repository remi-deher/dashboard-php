document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.getElementById('dashboard-tabs-container');
    const servicesContainer = document.getElementById('dashboard-container');
    const themeSwitcher = document.getElementById('theme-switcher');
    
    let grid = null; // Variable globale pour notre grille
    let statusRefreshInterval = null;
    
    // --- GESTION DE LA MODALE DE PARAMÈTRES ---
    const settingsModal = document.getElementById('settings-modal');
    const openModalBtn = document.getElementById('open-settings-modal');
    const closeModalBtn = document.getElementById('close-settings-modal');
    
    if(settingsModal && openModalBtn && closeModalBtn) {
        openModalBtn.addEventListener('click', () => {
            settingsModal.style.display = 'flex';
        });
        closeModalBtn.addEventListener('click', () => {
            settingsModal.style.display = 'none';
        });
        window.addEventListener('click', (event) => {
            if (event.target === settingsModal) {
                settingsModal.style.display = 'none';
            }
        });
    }
    if (window.location.pathname.includes('/edit/')) {
        if(settingsModal) settingsModal.style.display = 'flex';
    }

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

    // --- LOGIQUE DE STATUT DE SERVICE ---
    const checkServiceStatus = (serviceUrl, cardElement) => {
        if (!serviceUrl || !cardElement) return;
        const statusIndicator = cardElement.querySelector('.card-status');
        const latencyIndicator = cardElement.querySelector('.card-latency');
        if(statusIndicator) statusIndicator.classList.add('checking');

        fetch(`/api/status/check?url=${encodeURIComponent(serviceUrl)}`)
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
            document.querySelectorAll('.grid-stack-item').forEach(item => {
                const content = item.querySelector('.grid-stack-item-content');
                if (content && item.dataset.url) {
                    checkServiceStatus(item.dataset.url, content);
                }
            });
        }, 60000);
    };


    // --- LOGIQUE GRIDSTACK ---
    const buildServicesGrid = (dashboardId) => {
        if (!dashboardId) return;

        document.querySelectorAll('.dashboard-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.id == dashboardId);
        });

        if (!grid) {
            grid = GridStack.init({
                cellHeight: 90,
                margin: 10,
                float: true,
            });

            grid.on('change', function(event, items) {
                const layout = items.map(item => ({
                    id: item.id,
                    x: item.x,
                    y: item.y,
                    width: item.w, // Utiliser w et h pour la sauvegarde
                    height: item.h
                }));
                
                clearTimeout(window.saveTimeout);
                window.saveTimeout = setTimeout(() => {
                    fetch('/api/services/layout/save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(layout)
                    });
                }, 500);
            });
        }
        
        fetch(`/api/services?dashboard_id=${dashboardId}`)
            .then(response => response.json())
            .then(services => { // 'services' est maintenant un tableau plat
                grid.removeAll(); 
                
                if (!services || services.length === 0) {
                     // Si le conteneur a une classe grid-stack, il peut ne pas afficher le message.
                     // On peut le vider et y ajouter le message.
                     const container = document.getElementById('dashboard-container');
                     container.innerHTML = '<p class="loading-message">Ce dashboard est vide.</p>';
                     return;
                }
                
                // *** CORRECTION ICI ***
                // On boucle directement sur le tableau de services
                services.forEach(service => {
                    let iconHtml = service.icone_url
                        ? `<img src="${service.icone_url}" class="card-icon-custom" alt="${service.nom}">`
                        : `<i class="${service.icone || 'fas fa-link'}"></i>`;

                    const content = `
                        <div class="grid-stack-item-content ${service.card_color ? 'custom-color' : ''}" style="${service.card_color ? '--card-bg-color-custom:' + service.card_color : ''}">
                            <div class="card-status"></div>
                            <div class="card-latency">...</div>
                            <a href="${service.url}" target="_blank" class="card-link" title="${service.description || service.nom}">
                                <div class="card-icon">${iconHtml}</div>
                                <div class="card-title">${service.nom}</div>
                            </a>
                        </div>`;

                    const widgetNode = {
                        x: service.gs_x,
                        y: service.gs_y,
                        w: service.gs_width,
                        h: service.gs_height,
                        content: content,
                        id: service.id
                    };
                    
                    const el = grid.addWidget(widgetNode);
                    el.dataset.url = service.url;
                    checkServiceStatus(service.url, el.querySelector('.grid-stack-item-content'));
                });
                
                startStatusRefresh();
            })
            .catch(error => {
                console.error("Erreur:", error);
                const container = document.getElementById('dashboard-container');
                container.innerHTML = '<p class="loading-message">Erreur de chargement des services.</p>';
            });
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
