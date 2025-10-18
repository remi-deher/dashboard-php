// Fichier: /public/assets/js/main.js

// Fonction utilitaire pour limiter les événements (ex: molette)
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
    
    const dropZoneLeft = document.getElementById('drop-zone-left');
    const dropZoneRight = document.getElementById('drop-zone-right');
    let draggedTile = null;
    let switchDashboardTimer = null;
    let isHoveringZone = false;
    let isNavigating = false; // Verrou pour empêcher double-clic

    let grid = null;

    let statusRefreshInterval = null;
    let allDashboards = [];
    let currentDashboardId = null;

    
    // --- GESTION DE LA MODALE DE PARAMÈTRES (inchangée) ---
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
            showModalTab('tab-dashboards'); 
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
    if (window.location.pathname.includes('/service/edit/')) {
        if(settingsModal) settingsModal.style.display = 'flex';
        showModalTab('tab-services'); 
    } else if (window.location.pathname.includes('/dashboard/edit/')) {
        if(settingsModal) settingsModal.style.display = 'flex';
        showModalTab('tab-dashboards'); 
    }


    // --- LOGIQUE DU SÉLECTEUR DE THÈME (inchangée) ---
    const currentTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', currentTheme);
    if (currentTheme === 'light') themeSwitcher.innerHTML = '<i class="fas fa-moon"></i>';
    else themeSwitcher.innerHTML = '<i class="fas fa-sun"></i>';
    
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
                const content = item.querySelector('.grid-stack-item-content');
                if (content && item.dataset.url) {
                    checkServiceStatus(item.dataset.url, content);
                }
            });
        }, 60000);
    };

    // --- Helper pour trouver les dashboards adjacents ---
    const getDashboardByIndex = (offset) => {
        const currentIndex = allDashboards.findIndex(db => db.id == currentDashboardId);
        if (currentIndex === -1) return null;
        
        const targetIndex = (currentIndex + offset + allDashboards.length) % allDashboards.length;
        if (targetIndex === currentIndex) return null; // S'il n'y a qu'un seul dashboard
        
        return allDashboards[targetIndex];
    };

    // --- MODIFIÉ: Logique de déplacement de service (API) ---
    const moveServiceToDashboard = (serviceId, newDashboardId, layout) => {
        // Retourne la promesse pour la synchronisation
        return fetch(`/api/service/move/${serviceId}/${newDashboardId}`, {
            method: 'POST',
            // NOUVEAU: Envoyer les coordonnées dans le body
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                x: layout.x,
                y: layout.y,
                w: layout.w,
                h: layout.h
            })
        })
        .then(response => response.json())
        .catch(error => {
            console.error("Erreur lors du déplacement de la tuile:", error);
        });
    };

    // --- MODIFIÉ: Gestionnaire de survol pendant le glissement ---
    const handleDragMove = (event) => {
        if (!draggedTile) return;

        const clientX = event.clientX;
        const zoneWidth = 90; // Doit correspondre au CSS
        
        let targetDashboard = null;
        let direction = null;

        // Survole la zone de gauche
        if (clientX < zoneWidth) {
            targetDashboard = getDashboardByIndex(-1);
            direction = 'left';
            if (targetDashboard) {
                dropZoneLeft.classList.add('drop-hover');
                dropZoneRight.classList.remove('drop-hover');
            }
        // Survole la zone de droite
        } else if (clientX > window.innerWidth - zoneWidth) {
            targetDashboard = getDashboardByIndex(1);
            direction = 'right';
            if (targetDashboard) {
                dropZoneRight.classList.add('drop-hover');
                dropZoneLeft.classList.remove('drop-hover');
            }
        // N'est pas sur une zone
        } else {
            isHoveringZone = false;
            clearTimeout(switchDashboardTimer);
            dropZoneLeft.classList.remove('drop-hover');
            dropZoneRight.classList.remove('drop-hover');
            return;
        }
        
        // Si on est sur une zone et que ce n'est pas la même qu'avant
        if (targetDashboard && isHoveringZone !== direction) {
            isHoveringZone = direction;
            clearTimeout(switchDashboardTimer);
            
            // Lancer le timer pour changer de dashboard
            switchDashboardTimer = setTimeout(() => {
                if (!draggedTile) return;
                
                const serviceId = draggedTile.gridstackNode.id;
                // NOUVEAU: Capturer le layout actuel
                const layout = draggedTile.gridstackNode;
                
                // 1. Appeler l'API pour déplacer le service (en envoyant le layout)
                moveServiceToDashboard(serviceId, targetDashboard.id, layout).then(() => {
                    // 2. Retirer la tuile de la grille actuelle (elle est maintenant "fantôme")
                    grid.removeWidget(draggedTile, false, false); // Ne pas sauvegarder, ne pas animer
                    
                    // 3. Annuler le glissement natif
                    grid.cancelDrag();
                    
                    // 4. Naviguer vers le nouveau dashboard
                    if (direction === 'left') navigatePrev();
                    else navigateNext();
                });
                
            }, 800); // Délai de 800ms
        }
    };


    // --- Fonctions de navigation (inchangées) ---
    const navigateToDashboard = (dashboardId) => {
        if (isNavigating || !dashboardId) return;
        isNavigating = true;
        
        currentDashboardId = dashboardId;
        
        document.querySelectorAll('.dashboard-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.id == dashboardId);
        });

        buildServicesGrid(dashboardId)
            .finally(() => {
                isNavigating = false;
            });
    };

    const navigateNext = () => {
        const nextDashboard = getDashboardByIndex(1);
        if (nextDashboard) navigateToDashboard(nextDashboard.id);
    };

    const navigatePrev = () => {
        const prevDashboard = getDashboardByIndex(-1);
        if (prevDashboard) navigateToDashboard(prevDashboard.id);
    };

    // --- Écouteurs pour la navigation (inchangés) ---
    document.getElementById('nav-arrow-left').addEventListener('click', navigatePrev);
    document.getElementById('nav-arrow-right').addEventListener('click', navigateNext);
    
    tabsContainer.addEventListener('wheel', throttle((event) => {
        event.preventDefault();
        if (event.deltaY > 0) navigateNext();
        else navigatePrev();
    }, 200));


    // --- LOGIQUE GRIDSTACK (inchangée) ---
    const buildServicesGrid = (dashboardId) => {
        const existingMessage = servicesContainer.querySelector('.loading-message');
        if (existingMessage) {
            servicesContainer.removeChild(existingMessage);
        }

        grid.removeAll();
        
        return fetch(`/api/services?dashboard_id=${dashboardId}`)
            .then(response => response.json())
            .then(services => {
                
                if (!services || services.length === 0) {
                     const emptyMessage = document.createElement('p');
                     emptyMessage.className = 'loading-message';
                     emptyMessage.textContent = 'Ce dashboard est vide.';
                     servicesContainer.appendChild(emptyMessage);
                     return;
                }
                
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
                servicesContainer.innerHTML = '<p class="loading-message">Erreur de chargement des services.</p>';
                throw error;
            });
    };
    
    // Initialisation de la grille et des événements (inchangée)
    const initGrid = () => {
        grid = GridStack.init({
            el: '#dashboard-container',
            cellHeight: 90,
            margin: 10,
            float: true,
        });

        grid.on('dragstart', (event, el) => {
            draggedTile = el;
            dropZoneLeft.classList.add('visible');
            dropZoneRight.classList.add('visible');

            const prevDb = getDashboardByIndex(-1);
            const nextDb = getDashboardByIndex(1);
            dropZoneLeft.querySelector('.zone-label').textContent = prevDb ? prevDb.nom : '';
            dropZoneRight.querySelector('.zone-label').textContent = nextDb ? nextDb.nom : '';
            
            document.addEventListener('mousemove', handleDragMove);
        });

        grid.on('dragstop', (event, el) => {
            document.removeEventListener('mousemove', handleDragMove);
            dropZoneLeft.classList.remove('visible', 'drop-hover');
            dropZoneRight.classList.remove('visible', 'drop-hover');
            clearTimeout(switchDashboardTimer);
            draggedTile = null;
            isHoveringZone = false;
            
            const layout = grid.save(false);
            if (layout.length === 0) return;

            clearTimeout(window.saveTimeout);
            window.saveTimeout = setTimeout(() => {
                fetch('/api/services/layout/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(layout)
                });
            }, 500);
        });
    };
    
    // Chargement initial (inchangé)
    const loadTabsAndFirstDashboard = () => {
        fetch('/api/dashboards')
            .then(response => response.json())
            .then(dashboards => {
                tabsContainer.innerHTML = '';
                
                if (dashboards.length === 0) {
                    servicesContainer.innerHTML = '<p class="loading-message">Aucun dashboard configuré.</p>';
                }
                
                allDashboards = dashboards;
                
                dashboards.forEach(db => {
                    const tab = document.createElement('button');
                    tab.className = 'dashboard-tab';
                    tab.dataset.id = db.id;
                    
                    let iconHtml = db.icone_url
                        ? `<img src="${db.icone_url}" class="tab-icon-custom" alt="${db.nom}">`
                        : `<i class="${db.icone || 'fas fa-th-large'}"></i>`;
                    
                    tab.innerHTML = `${iconHtml} ${db.nom}`;
                    tab.onclick = () => {
                        navigateToDashboard(db.id);
                    };
                    tabsContainer.appendChild(tab);
                });
                
                const addBtn = document.createElement('button');
                addBtn.className = 'dashboard-tab add-tab-btn';
                addBtn.id = 'add-new-btn';
                addBtn.innerHTML = '<i class="fas fa-plus"></i> Ajouter';
                addBtn.title = "Ajouter un service ou un dashboard";
                addBtn.addEventListener('click', () => {
                    settingsModal.style.display = 'flex';
                    showModalTab('tab-services'); 
                });
                tabsContainer.appendChild(addBtn);

                new Sortable(tabsContainer, {
                    animation: 150,
                    filter: '.add-tab-btn', 
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: (evt) => {
                        const tabs = Array.from(tabsContainer.children);
                        const newOrderIds = tabs
                            .filter(tab => tab.dataset.id) 
                            .map(tab => tab.dataset.id);
                        
                        allDashboards.sort((a, b) => {
                            return newOrderIds.indexOf(a.id.toString()) - newOrderIds.indexOf(b.id.toString());
                        });

                        fetch('/api/dashboards/layout/save', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(newOrderIds)
                        });
                    }
                });

                initGrid();
                
                if (dashboards.length > 0) {
                    navigateToDashboard(dashboards[0].id);
                }
            });
    };

    loadTabsAndFirstDashboard();
});
