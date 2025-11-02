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

/* * SUPPRIMÉ : startStatusRefresh()
 * La mise à jour est maintenant gérée par Server-Sent Events (SSE)
 */
// function startStatusRefresh() { ... }


/**
 * AJOUT : Initialise la connexion Server-Sent Events (SSE)
 * Appelée une seule fois au chargement de la page.
 */
function initSseStream() {
    console.log("Initialisation du flux SSE (/api/stream)...");
    const eventSource = new EventSource('/api/stream');

    eventSource.addEventListener('widget_update', (e) => {
        try {
            const update = JSON.parse(e.data);
            const serviceId = update.serviceId;
            
            // Trouver l'élément widget sur la page
            const item = document.querySelector(`.dashboard-item[data-widget-id="${serviceId}"]`);

            // Si le widget n'est pas sur la page (ou pas sur ce dashboard), ignorer
            if (!item || item.closest('.dashboard-grid').style.display === 'none') {
                return;
            }

            console.log(`[SSE] Mise à jour reçue pour le widget ${serviceId}`);

            // Nous avons reçu les données. Nous devons les parser et les afficher.
            const payload = JSON.parse(update.payload); // C'est le { ... }
            const widgetType = item.dataset.widgetType;
            const renderer = WIDGET_RENDERERS[widgetType];

            if (!renderer) return;

            // Recréer le contexte de rendu (comme dans la v2)
            const sizeClass = item.classList.contains('size-large') ? 'size-large'
                            : item.classList.contains('size-small') ? 'size-small'
                            : 'size-medium';
            
            const renderContext = {
                data: payload,
                serviceId: serviceId,
                sizeClass: sizeClass,
                widgetType: widgetType
            };

            // Mettre à jour le HTML du widget
            const container = item.querySelector('.widget-container');
            if (container) {
                // Gérer les erreurs venant du worker (ex: M365 non connecté)
                if (payload.error) {
                    throw new Error(payload.error);
                }
                container.innerHTML = renderer(renderContext);
            }

        } catch (error) {
            console.error("Erreur lors du traitement du message SSE:", error, e.data);
            const container = item.querySelector('.widget-container');
            if (container) {
                container.innerHTML = `<p class="widget-error" title="${error.message}"><i class="fas fa-exclamation-triangle"></i> Erreur</p>`;
            }
        }
    });

    eventSource.onerror = (err) => {
        console.error("Erreur EventSource:", err);
        // La reconnexion est gérée automatiquement par le navigateur
    };
}


/**
 * MODIFIÉ : Gère le chargement initial (lecture du cache)
 */
function loadWidgetData(item, serviceId, widgetType) {
    const container = item.querySelector('.widget-container');
    
    if (!container) {
        console.error(`Erreur: Conteneur .widget-container non trouvé pour le service ${serviceId}.`);
        return;
    }

    container.classList.add('loading');
    container.innerHTML = '<p class="loading-message" style="font-size: 0.9rem;">Chargement...</p>';

    // Cet appel va maintenant lire le cache Redis (via ApiController)
    apiGetWidgetData(serviceId)
        .then(data => {
            container.classList.remove('loading');

            const sizeClass = item.classList.contains('size-large') ? 'size-large'
                            : item.classList.contains('size-small') ? 'size-small'
                            : 'size-medium';
            
            const renderContext = {
                data: data,
                serviceId: serviceId,
                sizeClass: sizeClass,
                widgetType: widgetType
            };

            const renderer = WIDGET_RENDERERS[widgetType];
            
            if (renderer) {
                const html = renderer(renderContext);
                container.innerHTML = html;
            } else {
                throw new Error(`Type de widget inconnu: '${widgetType}'`);
            }
        })
        .catch(error => {
            console.error(`Erreur widget ${serviceId} (${widgetType}):`, error);
            container.classList.remove('loading');
            container.innerHTML = `<p class="widget-error" title="${error.message}"><i class="fas fa-exclamation-triangle"></i> ${error.message}</p>`;
        });
}


// --- Helpers de Navigation (inchangés) ---
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


// --- buildServicesGrid ---
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
                 return;
            }

            services.forEach(service => {
                const item = document.createElement('div');
                const sizeClass = service.size_class || 'size-medium';
                
                item.className = `dashboard-item ${sizeClass}`;
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
                    // Et on charge les données spécifiques du widget (lecture du cache)
                    loadWidgetData(item, service.id, service.widget_type);
                }
            });

            initInteractForItems(container);
            // startStatusRefresh(); // SUPPRIMÉ
        })
        .catch(error => {
            console.error("Erreur buildServicesGrid:", error);
            container.innerHTML = `<p class="loading-message">Erreur de chargement: ${error.message}</p>`;
        });
};

// --- Logique de navigation ---
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
        // startStatusRefresh(); // SUPPRIMÉ
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
            
            // AJOUT: Démarrer le client SSE une fois les dashboards chargés
            initSseStream();
        })
        .catch(error => {
            console.error("Erreur loadTabsAndFirstDashboard:", error);
            DOM.dashboardsWrapper.innerHTML = `<p class="loading-message">Erreur critique au chargement: ${error.message}</p>`;
            DOM.tabsContainer.innerHTML = '';
            updateNavArrows();
        });
};
