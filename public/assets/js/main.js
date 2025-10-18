// Fichier: /public/assets/js/main.js

// NOUVEAU: Fonction utilitaire pour limiter les événements (ex: molette)
const throttle = (func, limit) => {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.getElementById('dashboard-tabs-container');
    const servicesContainer = document.getElementById('dashboard-container');
    const themeSwitcher = document.getElementById('theme-switcher');
    
    let grid = null; // Variable globale pour notre grille
    let statusRefreshInterval = null;

    // NOUVEAU: Variables pour la navigation
    let allDashboards = [];
    let currentDashboardId = null;

    
    // --- NOUVEAU: GESTION DE LA MODALE DE PARAMÈTRES (AMÉLIORÉE AVEC ONGLETS) ---
    const settingsModal = document.getElementById('settings-modal');
    const openModalBtn = document.getElementById('open-settings-modal');
    const closeModalBtn = document.getElementById('close-settings-modal');
    const modalTabs = document.querySelectorAll('.modal-tab-btn');
    const modalTabContents = document.querySelectorAll('.modal-tab-content');

    const showModalTab = (tabId) => {
        modalTabContents.forEach(content => {
            content.classList.toggle('active', content.id === tabId);
        });
        modalTabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabId);
        });
    };

    modalTabs.forEach(tab => {
        tab.addEventListener('click', () => showModalTab(tab.dataset.tab));
    });

    if(settingsModal && openModalBtn && closeModalBtn) {
        openModalBtn.addEventListener('click', () => {
            settingsModal.style.display = 'flex';
            showModalTab('tab-dashboards'); // Ouvre le premier onglet par défaut
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

    // MODIFIÉ: Logique d'ouverture de modale pour gérer les onglets
    if (window.location.pathname.includes('/service/edit/')) {
        if(settingsModal) settingsModal.style.display = 'flex';
        showModalTab('tab-services'); // Ouvre l'onglet services
    } else if (window.location.pathname.includes('/dashboard/edit/')) {
        if(settingsModal) settingsModal.style.display = 'flex';
        showModalTab('tab-dashboards'); // Ouvre l'onglet dashboards
    }


    // --- LOGIQUE DU SÉLECTEUR DE THÈME (inchangée) ---
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

    // --- LOGIQUE DE STATUT DE SERVICE (inchangée) ---
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
                // Exclure la tuile "ajouter"
                if (item.id === 'add-tile') return; 
                
                const content = item.querySelector('.grid-stack-item-content');
                if (content && item.dataset.url) {
                    checkServiceStatus(item.dataset.url, content);
                }
            });
        }, 60000);
    };


    // --- NOUVEAU: Fonctions de navigation ---
    const navigateToDashboard = (dashboardId) => {
        if (!dashboardId) return;
        currentDashboardId = dashboardId;
        buildServicesGrid(dashboardId);
    };

    const navigateNext = () => {
        const currentIndex = allDashboards.findIndex(db => db.id == currentDashboardId);
        const nextIndex = (currentIndex + 1) % allDashboards.length;
        navigateToDashboard(allDashboards[nextIndex].id);
    };

    const navigatePrev = () => {
        const currentIndex = allDashboards.findIndex(db => db.id == currentDashboardId);
        const prevIndex = (currentIndex - 1 + allDashboards.length) % allDashboards.length;
        navigateToDashboard(allDashboards[prevIndex].id);
    };

    // --- NOUVEAU: Écouteurs pour la navigation ---
    document.getElementById('nav-arrow-left').addEventListener('click', navigatePrev);
    document.getElementById('nav-arrow-right').addEventListener('click', navigateNext);
    
    // Navigation à la molette sur la barre d'onglets
    tabsContainer.addEventListener('wheel', throttle((event) => {
        event.preventDefault();
        if (event.deltaY > 0) {
            navigateNext();
        } else {
            navigatePrev();
        }
    }, 200)); // 200ms de throttle


    // --- LOGIQUE GRIDSTACK (MODIFIÉE) ---
    const buildServicesGrid = (dashboardId) => {
        if (!dashboardId) return;

        // Met en surbrillance l'onglet actif
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
                const layout = items
                    .filter(item => item.id !== 'add-tile') // Exclure la tuile "add"
                    .map(item => ({
                        id: item.id,
                        x: item.x,
                        y: item.y,
                        width: item.w,
                        height: item.h
                    }));
                
                if (layout.length === 0) return; // Ne pas sauvegarder si on a juste bougé la tuile "add"

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
            .then(services => {
                grid.removeAll(); 
                
                // MODIFIÉ: Ne plus afficher de message "dashboard vide" ici
                // car on va ajouter la tuile "add"
                
                // Boucle sur les services
                if (services && services.length > 0) {
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
                }
                
                // NOUVEAU: Ajouter la tuile "Ajouter un service"
                const addTileContent = `
                    <div class="grid-stack-item-content add-new-tile" id="add-new-service-tile">
                        <i class="fas fa-plus"></i>
                        <span>Ajouter un service</span>
                    </div>
                `;
                
                const addTileNode = {
                    w: 2, // Largeur par défaut
                    h: 1, // Hauteur par défaut
                    content: addTileContent,
                    id: 'add-tile',
                    autoPosition: true,
                    locked: true // Statique
                };
                
                const addTileWidget = grid.addWidget(addTileNode);
                
                // Ajouter le listener pour ouvrir la modale
                addTileWidget.querySelector('#add-new-service-tile').addEventListener('click', () => {
                    settingsModal.style.display = 'flex';
                    showModalTab('tab-services');
                    
                    // Optionnel: scroll vers le formulaire d'ajout
                    const serviceForm = document.querySelector('#tab-services form');
                    if (serviceForm) {
                        serviceForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
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
                
                allDashboards = dashboards; // Stocker pour la navigation
                
                tabsContainer.innerHTML = '';
                dashboards.forEach(db => {
                    const tab = document.createElement('button');
                    tab.className = 'dashboard-tab';
                    tab.dataset.id = db.id;
                    
                    let iconHtml = db.icone_url
                        ? `<img src="${db.icone_url}" class="tab-icon-custom" alt="${db.nom}">`
                        : `<i class="${db.icone || 'fas fa-th-large'}"></i>`;
                    
                    tab.innerHTML = `${iconHtml} ${db.nom}`;
                    // MODIFIÉ: Utiliser la nouvelle fonction de navigation
                    tab.onclick = () => navigateToDashboard(db.id);
                    tabsContainer.appendChild(tab);
                });
                
                // MODIFIÉ: Utiliser la nouvelle fonction de navigation
                navigateToDashboard(dashboards[0].id);
            });
    };

    loadTabsAndFirstDashboard();
});
