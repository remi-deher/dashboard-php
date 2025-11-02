// Fichier: /public/assets/js/dashboard.js (Corrigé et Refactorisé)

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

function checkServiceStatus(serviceUrl, cardElement) {
    if (!serviceUrl || !cardElement) return;
    const statusIndicator = cardElement.querySelector('.card-status');
    const latencyIndicator = cardElement.querySelector('.card-latency');
    if(statusIndicator) statusIndicator.classList.add('checking');

    apiCheckStatus(serviceUrl)
        .then(data => {
            cardElement.classList.remove('online', 'offline', 'checking');
            if (data && data.status) {
                 cardElement.classList.add(data.status === 'online' ? 'online' : 'offline');
                 if (latencyIndicator) {
                     if (data.status === 'online') {
                         latencyIndicator.innerHTML = `<span>${data.ttfb}ms</span> <i class="fas fa-info-circle"></i>`;
                         latencyIndicator.title = `TTFB: ${data.ttfb}ms / Connexion: ${data.connect_time}ms`;
                     } else {
                         latencyIndicator.innerHTML = `<span>N/A</span>`;
                         latencyIndicator.title = `Service hors ligne` + (data.message ? `: ${data.message}`: '');
                     }
                 }
            } else { throw new Error("Réponse invalide de l'API de statut"); }
        })
        .catch(error => {
            console.error("Erreur checkStatus:", error);
            cardElement.classList.remove('online', 'checking');
            cardElement.classList.add('offline');
            if (latencyIndicator) {
                latencyIndicator.innerHTML = `<span>Erreur</span>`;
                latencyIndicator.title = `Vérification échouée: ${error.message}`;
            }
        })
        .finally(() => {
            if(statusIndicator) statusIndicator.classList.remove('checking');
        });
};

function startStatusRefresh() {
    if (STATE.statusRefreshInterval) clearInterval(STATE.statusRefreshInterval);

    const refreshAllVisible = () => {
         const activeContainer = getDashboardContainer(STATE.currentDashboardId);
         if(activeContainer){
             // Rafraîchir les LIENS (ping)
             activeContainer.querySelectorAll('.dashboard-item[data-url]').forEach(item => {
                 // Tous les services, y compris les widgets, ont un check de statut
                 checkServiceStatus(item.dataset.url, item);
             });
             // Rafraîchir les WIDGETS (données)
             activeContainer.querySelectorAll('.dashboard-item[data-widget-id]').forEach(item => {
                loadWidgetData(item, item.dataset.widgetId, item.dataset.widgetType);
             });
         }
    };

    refreshAllVisible(); // Exécution immédiate
    STATE.statusRefreshInterval = setInterval(refreshAllVisible, 60000); // 1 minute
};


/**
 * MODIFIÉ : Charge les données d'un widget via l'API et utilise
 * le registre WIDGET_RENDERERS pour l'affichage.
 */
function loadWidgetData(item, serviceId, widgetType) {
    const container = item.querySelector('.widget-container');
    
    if (!container) {
        console.error(`Erreur: Conteneur .widget-container non trouvé pour le service ${serviceId}.`);
        return;
    }

    container.classList.add('loading');
    container.innerHTML = '<p class="loading-message" style="font-size: 0.9rem;">Chargement...</p>';

    apiGetWidgetData(serviceId)
        .then(data => {
            container.classList.remove('loading');
            
            // AJOUT: Injecter l'ID du service pour le renderer (ex: ID de canvas)
            data.serviceId = serviceId; 

            // On vérifie si un renderer existe pour ce type de widget
            const renderer = WIDGET_RENDERERS[widgetType];
            
            if (renderer) {
                // Le renderer gère lui-même les data.error
                // et retourne le HTML ou lance une erreur
                const html = renderer(data);
                container.innerHTML = html;
            } else {
                // Gérer les types de widgets inconnus
                console.error(`Type de widget inconnu: '${widgetType}' pour le service ${serviceId}`);
                throw new Error(`Type de widget inconnu: '${widgetType}'`);
            }
        })
        .catch(error => {
            // Cette section attrape les erreurs de fetch (apiGetWidgetData)
            // OU les erreurs lancées par le renderer (ex: data.error)
            console.error(`Erreur widget ${serviceId} (${widgetType}):`, error);
            container.classList.remove('loading');
            container.innerHTML = `<p class="widget-error" title="${error.message}"><i class="fas fa-exclamation-triangle"></i> Erreur</p>`;
        });
}


// --- Helpers de Navigation (inchangés) ---
// ... (getDashboardByIndex, updateNavArrows, getDashboardContainer) ...
function getDashboardByIndex(offset) {
    if (!STATE.allDashboards || STATE.allDashboards.length <= 1) return null;
    const currentIndex = STATE.allDashboards.findIndex(db => db.id == STATE.currentDashboardId);
    if (currentIndex === -1) return null;
    const targetIndex = (currentIndex + offset + STATE.allDashboards.length) % STATE.allDashboards.length;
    return STATE.allDashboards[targetIndex];
};
function updateNavArrows() {
     DOM.navArrowLeft.style.display = getDashboardByIndex(-1) ? 'flex' : 'none';
     DOM.navArrowRight.style.display = getDashboardByIndex(1) ? 'flex' : 'none';
};
function getDashboardContainer(dashboardId, create = false) {
    if (!dashboardId) return null;
    let container = DOM.dashboardsWrapper.querySelector(`.dashboard-grid[data-dashboard-id="${dashboardId}"]`);
    if (!container && create) {
        container = document.createElement('div');
        container.className = 'dashboard-grid';
        container.dataset.dashboardId = dashboardId;
        container.style.display = 'none';
        DOM.dashboardsWrapper.appendChild(container);
        initSortableForContainer(container);
    }
    return container;
}


// --- buildServicesGrid (inchangé, sauf pour la partie widget) ---
function buildServicesGrid(dashboardId) {
    const container = getDashboardContainer(dashboardId, true);
    if (!container) return Promise.reject("Conteneur de dashboard introuvable.");

    container.innerHTML = '<p class="loading-message">Chargement...</p>';
    container.style.display = 'grid';

    return apiGetServices(dashboardId)
        .then(services => {
            container.innerHTML = ''; 
            if (services.error) { throw new Error(services.error); }
            if (!services || services.length === 0) {
                 if (STATE.statusRefreshInterval) clearInterval(STATE.statusRefreshInterval);
                 return;
            }

            services.forEach(service => {
                const item = document.createElement('div');
                item.className = `dashboard-item ${service.size_class || 'size-medium'}`;
                item.dataset.serviceId = service.id;
                item.dataset.dashboardId = dashboardId;
                item.dataset.url = service.url; // TOUJOURS ajouter l'URL pour le ping
                
                let iconHtml = service.icone_url
                    ? `<img src="${service.icone_url}" class="card-icon-custom" alt="">`
                    : `<i class="${service.icone || 'fas fa-link'} card-icon-fa"></i>`;

                // Logique Link vs Widget
                if (service.widget_type === 'link') {
                    item.dataset.widgetType = 'link';
                    item.innerHTML = `
                        <div class="dashboard-item-content ${service.card_color ? 'custom-color' : ''}" style="${service.card_color ? '--card-bg-color-custom:' + service.card_color : ''}">
                             <div class="card-status" title="Vérification du statut..."></div>
                             <div class="card-latency" title="Latence...">...</div>
                             <a href="${service.url}" target="_blank" class="card-link" title="${service.description || service.nom}">
                                 <div class="card-icon">${iconHtml}</div>
                                 <div class="card-title">${service.nom}</div>
                             </a>
                             <div class="resize-handle"></div> 
                        </div>`;
                    
                    container.appendChild(item);
                    checkServiceStatus(service.url, item); 

                } else {
                    // C'est un WIDGET
                    item.dataset.widgetId = service.id;
                    item.dataset.widgetType = service.widget_type;
                    item.innerHTML = `
                        <div class="dashboard-item-content ${service.card_color ? 'custom-color' : ''}" style="${service.card_color ? '--card-bg-color-custom:' + service.card_color : ''}">
                             <div class="card-status" title="Vérification du statut..."></div>
                             <div class="card-latency" title="Latence...">...</div>
                             
                             <div class="widget-container"></div> 
                             
                             <div class="card-title">${service.nom}</div>
                             <div class="resize-handle"></div> 
                        </div>`;
                    
                    container.appendChild(item);
                    // Tous les services, y compris les widgets, ont un check de statut (ping)
                    checkServiceStatus(service.url, item);
                    // Et on charge les données spécifiques du widget
                    loadWidgetData(item, service.id, service.widget_type);
                }
            });

            initInteractForItems(container);
            startStatusRefresh();
        })
        .catch(error => {
            console.error("Erreur buildServicesGrid:", error);
            container.innerHTML = `<p class="loading-message">Erreur de chargement: ${error.message}</p>`;
            if (STATE.statusRefreshInterval) clearInterval(STATE.statusRefreshInterval);
        });
};

// --- Logique de navigation (inchangée) ---
// ... (navigateToDashboard, navigateNext, navigatePrev, createDashboardTabElement, appendAddButtonToTabs, loadTabsAndFirstDashboard) ...
function navigateToDashboard(dashboardId) {
    const targetId = parseInt(dashboardId, 10);
    if (isNaN(targetId) || STATE.isNavigating || targetId === STATE.currentDashboardId) return;
    STATE.isNavigating = true;
    console.log(`Navigating to dashboard ${targetId}`);
    const previousDashboardId = STATE.currentDashboardId;
    STATE.currentDashboardId = targetId;
    if (previousDashboardId) {
        const oldContainer = getDashboardContainer(previousDashboardId);
        if (oldContainer) oldContainer.style.display = 'none';
    }
    document.querySelectorAll('.dashboard-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.id == targetId);
    });
    const container = getDashboardContainer(targetId, true);
    if (!container) {
         console.error("Impossible de créer/trouver le conteneur pour", targetId);
         STATE.isNavigating = false;
         return;
    }
    const needsBuilding = !container.querySelector('.dashboard-item') && !container.querySelector('.loading-message');
    if (needsBuilding) {
        buildServicesGrid(targetId).finally(() => { STATE.isNavigating = false; updateNavArrows(); });
    } else {
        container.style.display = 'grid';
        STATE.isNavigating = false;
        updateNavArrows();
        startStatusRefresh();
    }
};
const navigateNext = () => {
    const nextDashboard = getDashboardByIndex(1);
    if (nextDashboard) navigateToDashboard(nextDashboard.id);
};
const navigatePrev = () => {
    const prevDashboard = getDashboardByIndex(-1);
    if (prevDashboard) navigateToDashboard(prevDashboard.id);
};
function createDashboardTabElement(db) {
     const tab = document.createElement('button');
     tab.className = 'dashboard-tab';
     tab.dataset.id = db.id;
     let iconHtml = db.icone_url
         ? `<img src="${db.icone_url}" class="tab-icon-custom" alt="">`
         : `<i class="${db.icone || 'fas fa-th-large'}"></i>`;
     tab.innerHTML = `${iconHtml} ${db.nom}`;
     tab.onclick = () => navigateToDashboard(db.id);
     return tab;
}
function appendAddButtonToTabs() {
    DOM.tabsContainer.appendChild(DOM.addDashboardTabBtn);
}
function loadTabsAndFirstDashboard() {
     DOM.dashboardsWrapper.innerHTML = '<p class="loading-message">Chargement initial...</p>';
     apiGetDashboards()
        .then(dashboards => {
            DOM.tabsContainer.innerHTML = '';
            DOM.dashboardsWrapper.innerHTML = '';
            STATE.sortableInstances = {};
            if (dashboards.error) { throw new Error(dashboards.error); }
            if (!dashboards || dashboards.length === 0) {
                 DOM.dashboardsWrapper.innerHTML = '<p class="loading-message">Aucun dashboard. Ajoutez-en un via <i class="fas fa-cog"></i>.</p>';
                 STATE.allDashboards = [];
                 updateNavArrows();
                 appendAddButtonToTabs();
                 return;
            }
            STATE.allDashboards = dashboards;
            dashboards.forEach(db => {
                const tab = createDashboardTabElement(db);
                DOM.tabsContainer.appendChild(tab);
                getDashboardContainer(db.id, true);
            });
            appendAddButtonToTabs();
            initTabSorting(DOM.tabsContainer);
            if (dashboards.length > 0) {
                navigateToDashboard(dashboards[0].id);
            } else {
                 updateNavArrows();
            }
        })
        .catch(error => {
            console.error("Erreur loadTabsAndFirstDashboard:", error);
            DOM.dashboardsWrapper.innerHTML = `<p class="loading-message">Erreur critique au chargement: ${error.message}</p>`;
            DOM.tabsContainer.innerHTML = '';
            updateNavArrows();
        });
};
