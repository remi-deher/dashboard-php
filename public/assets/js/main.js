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
    // Éléments DOM principaux
    const tabsContainer = document.getElementById('dashboard-tabs-container');
    const dashboardsWrapper = document.getElementById('dashboards-wrapper'); // Conteneur pour TOUS les dashboards
    const settingsModal = document.getElementById('settings-modal');
    const openModalBtn = document.getElementById('open-settings-modal');
    const closeModalBtn = document.getElementById('close-settings-modal');
    const navArrowLeft = document.getElementById('nav-arrow-left');
    const navArrowRight = document.getElementById('nav-arrow-right');
    const dropZoneLeft = document.getElementById('drop-zone-left');
    const dropZoneRight = document.getElementById('drop-zone-right');

    // Variables d'état
    let sortableInstances = {}; // Stocke les instances SortableJS par dashboardId
    let currentDashboardId = null;
    let allDashboards = []; // Liste des dashboards {id, nom, ...}
    let statusRefreshInterval = null;
    let isNavigating = false; // Verrou pour navigation
    let draggedItem = null; // Élément en cours de drag par SortableJS
    let switchDashboardTimer = null; // Timer pour le survol des zones/onglets
    let isHoveringZone = false; // Indique si on survole une zone de drop latérale

    // --- GESTION DE LA MODALE DE PARAMÈTRE (inchangée dans sa logique interne) ---
    const modalTabs = document.querySelectorAll('.modal-tab-btn');
    const modalTabContents = document.querySelectorAll('.modal-tab-content');

    const showModalTab = (tabId) => {
        modalTabContents.forEach(content => content.classList.toggle('active', content.id === tabId));
        modalTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === tabId));
    };
    modalTabs.forEach(tab => tab.addEventListener('click', () => showModalTab(tab.dataset.tab)));

    if (settingsModal && openModalBtn && closeModalBtn) {
        openModalBtn.addEventListener('click', () => {
            settingsModal.style.display = 'flex';
            if (!window.location.pathname.includes('/edit/')) {
                showModalTab('tab-dashboards');
            }
        });
        const closeModalAction = () => {
             settingsModal.style.display = 'none';
             if (window.location.pathname.includes('/edit/')) {
                 window.history.pushState({}, '', '/'); // Revenir à la racine après fermeture si on était en édition
             }
        }
        closeModalBtn.addEventListener('click', closeModalAction);
        window.addEventListener('click', (event) => {
            if (event.target === settingsModal) {
                closeModalAction();
            }
        });
    }
    // Gérer l'ouverture directe via URL
    if (window.location.pathname.includes('/service/edit/')) {
        if (settingsModal) settingsModal.style.display = 'flex';
        showModalTab('tab-services');
    } else if (window.location.pathname.includes('/dashboard/edit/')) {
        if (settingsModal) settingsModal.style.display = 'flex';
        showModalTab('tab-dashboards');
    }


    // --- LOGIQUE DE STATUT DE SERVICE (inchangée) ---
    const checkServiceStatus = (serviceUrl, cardElement) => {
        if (!serviceUrl || !cardElement) return;
        const statusIndicator = cardElement.querySelector('.card-status');
        const latencyIndicator = cardElement.querySelector('.card-latency');
        if(statusIndicator) statusIndicator.classList.add('checking');

        fetch(`/api/status/check?url=${encodeURIComponent(serviceUrl)}`)
            .then(response => response.ok ? response.json() : Promise.reject(`HTTP error ${response.status}`))
            .then(data => {
                cardElement.classList.remove('online', 'offline', 'checking'); // Nettoyer classes
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
                } else {
                     throw new Error("Réponse invalide de l'API de statut");
                }
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

    const startStatusRefresh = () => {
        if (statusRefreshInterval) clearInterval(statusRefreshInterval);

        const refreshAllVisible = () => {
             const activeContainer = getDashboardContainer(currentDashboardId);
             if(activeContainer){
                 activeContainer.querySelectorAll('.dashboard-item[data-url]').forEach(item => {
                     checkServiceStatus(item.dataset.url, item); // item est maintenant la tuile elle-même
                 });
             }
        };

        refreshAllVisible(); // Exécution immédiate
        statusRefreshInterval = setInterval(refreshAllVisible, 60000); // Toutes les 60 secondes
    };

    // --- Helpers pour dashboards adjacents et navigation ---
    const getDashboardByIndex = (offset) => {
        if (!allDashboards || allDashboards.length <= 1) return null;
        const currentIndex = allDashboards.findIndex(db => db.id == currentDashboardId);
        if (currentIndex === -1) return null;
        const targetIndex = (currentIndex + offset + allDashboards.length) % allDashboards.length;
        return allDashboards[targetIndex];
    };

    const updateNavArrows = () => {
         navArrowLeft.style.display = getDashboardByIndex(-1) ? 'flex' : 'none';
         navArrowRight.style.display = getDashboardByIndex(1) ? 'flex' : 'none';
    };

    // --- NOUVEAU: Récupérer/Créer le conteneur pour un dashboard ---
    function getDashboardContainer(dashboardId, create = false) {
        if (!dashboardId) return null;
        let container = dashboardsWrapper.querySelector(`.dashboard-grid[data-dashboard-id="${dashboardId}"]`);
        if (!container && create) {
            container = document.createElement('div');
            container.className = 'dashboard-grid';
            container.dataset.dashboardId = dashboardId;
            container.style.display = 'none'; // Caché par défaut
            dashboardsWrapper.appendChild(container);
            // Initialiser SortableJS pour ce nouveau conteneur
            initSortableForContainer(container);
        }
        return container;
    }

    // --- NOUVEAU: Initialiser SortableJS pour un conteneur spécifique ---
    function initSortableForContainer(container) {
        const dashboardId = container.dataset.dashboardId;
        if (!dashboardId) {
            console.error("Impossible d'initialiser Sortable: dashboardId manquant sur le conteneur");
            return;
        }
        // Détruire l'ancienne instance si elle existe pour éviter les doublons
        if (sortableInstances[dashboardId]) {
            sortableInstances[dashboardId].destroy();
        }

        sortableInstances[dashboardId] = new Sortable(container, {
            group: 'shared-dashboards', // Nom de groupe partagé pour permettre le D&D inter-dashboards
            animation: 150,
            ghostClass: 'sortable-ghost', // Classe CSS pour l'élément fantôme
            dragClass: 'sortable-drag', // Classe CSS pour l'élément déplacé
            filter: '.resize-handle, a', // Ne pas démarrer le drag depuis les poignées ou les liens
            preventOnFilter: true, // Comportement par défaut mais explicitons-le

            onStart: (evt) => {
                draggedItem = evt.item; // Mémoriser l'élément déplacé
                // Afficher les zones de drop latérales si des dashboards adjacents existent
                const prevDb = getDashboardByIndex(-1);
                const nextDb = getDashboardByIndex(1);
                if(prevDb) {
                    dropZoneLeft.querySelector('.zone-label').textContent = prevDb.nom;
                    dropZoneLeft.classList.add('visible');
                }
                if(nextDb) {
                    dropZoneRight.querySelector('.zone-label').textContent = nextDb.nom;
                    dropZoneRight.classList.add('visible');
                }
                 // Ajouter écouteur pour le survol des zones pendant le drag
                 document.addEventListener('dragover', handleDragOverZone);
            },
            onEnd: (evt) => {
                 // Nettoyage après le drop
                 document.removeEventListener('dragover', handleDragOverZone);
                 dropZoneLeft.classList.remove('visible', 'drop-hover');
                 dropZoneRight.classList.remove('visible', 'drop-hover');
                 clearTimeout(switchDashboardTimer);
                 draggedItem = null;
                 isHoveringZone = false;

                // evt.to: conteneur où l'élément a été lâché
                // evt.from: conteneur d'où l'élément vient
                const targetDashboardId = evt.to.dataset.dashboardId;

                // Sauvegarder l'ordre dans le dashboard cible
                const items = Array.from(evt.to.children).filter(el => el.classList.contains('dashboard-item')); // S'assurer qu'on ne prend que les items
                const orderIds = items.map(item => item.dataset.serviceId);
                saveDashboardOrder(targetDashboardId, orderIds);

                // Si l'élément a été déplacé vers un *nouveau* dashboard via onAdd
                // la mise à jour BDD du dashboard_id a déjà été faite dans onAdd.
                // Si l'élément est resté dans le même dashboard (evt.from === evt.to),
                // seule la sauvegarde de l'ordre ci-dessus est nécessaire.
            },
            onAdd: (evt) => {
                // Appelé quand un élément entre dans CE dashboard depuis un AUTRE via D&D
                const serviceId = evt.item.dataset.serviceId;
                const newDashboardId = evt.to.dataset.dashboardId;
                const oldDashboardId = evt.from.dataset.dashboardId;

                if (!serviceId || !newDashboardId || !oldDashboardId || newDashboardId === oldDashboardId) return;

                // Mettre à jour l'ID du dashboard côté client (sur l'élément déplacé)
                evt.item.dataset.dashboardId = newDashboardId;

                console.log(`[onAdd] Service ${serviceId} moved from ${oldDashboardId} to ${newDashboardId}`);

                // Appeler l'API pour mettre à jour le dashboard_id en BDD
                fetch(`/api/service/move/${serviceId}/${newDashboardId}`, { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error("Erreur API moveService:", data.error || 'Erreur inconnue');
                            // Annuler visuellement ? C'est complexe car onAdd est déjà fait.
                            // Idéalement, informer l'utilisateur.
                            // evt.from.appendChild(evt.item); // Tentative de retour, peut causer des pbs
                        }
                        // Si succès, la sauvegarde de l'ordre se fera dans onEnd du conteneur cible.
                    })
                    .catch(error => {
                        console.error("Erreur fetch moveService:", error);
                        // Informer l'utilisateur
                    });
            }
        });
    }

    // --- NOUVEAU: Gestionnaire pour le survol des zones latérales PENDANT le drag ---
    const handleDragOverZone = (event) => {
         if (!draggedItem) return;

         const clientX = event.clientX;
         const zoneWidth = 90; // Largeur des zones latérales
         let targetDashboard = null;
         let direction = null;

         if (clientX < zoneWidth) {
             targetDashboard = getDashboardByIndex(-1);
             direction = 'left';
             if (targetDashboard) {
                 dropZoneLeft.classList.add('drop-hover');
                 dropZoneRight.classList.remove('drop-hover');
             } else {
                 dropZoneLeft.classList.remove('drop-hover');
             }
         } else if (clientX > window.innerWidth - zoneWidth) {
             targetDashboard = getDashboardByIndex(1);
             direction = 'right';
             if (targetDashboard) {
                 dropZoneRight.classList.add('drop-hover');
                 dropZoneLeft.classList.remove('drop-hover');
             } else {
                  dropZoneRight.classList.remove('drop-hover');
             }
         } else {
             // Pas dans une zone latérale
             isHoveringZone = false;
             clearTimeout(switchDashboardTimer);
             dropZoneLeft.classList.remove('drop-hover');
             dropZoneRight.classList.remove('drop-hover');
             return;
         }

         // Si on survole une zone valide et qu'on n'était pas déjà dessus
         if (targetDashboard && isHoveringZone !== direction) {
             isHoveringZone = direction;
             clearTimeout(switchDashboardTimer);

             switchDashboardTimer = setTimeout(() => {
                 if (!draggedItem || !isHoveringZone || !targetDashboard?.id) return; // Vérifications

                 const serviceId = draggedItem.dataset.serviceId;
                 const currentDashboardId = draggedItem.dataset.dashboardId;
                 const newDashboardId = targetDashboard.id;

                 console.log(`[Zone Hover] Attempting to move ${serviceId} to ${newDashboardId}`);

                 // 1. Appel API pour changer le dashboard_id
                 fetch(`/api/service/move/${serviceId}/${newDashboardId}`, { method: 'POST' })
                     .then(response => response.json())
                     .then(data => {
                         if (data && data.success) {
                             // 2. Si succès, supprimer l'élément du DOM actuel
                             const itemToRemove = draggedItem; // Garder référence
                             draggedItem = null; // Stopper les autres handlers
                             isHoveringZone = false;
                             clearTimeout(switchDashboardTimer);
                             document.removeEventListener('dragover', handleDragOverZone);
                             dropZoneLeft.classList.remove('visible', 'drop-hover');
                             dropZoneRight.classList.remove('visible', 'drop-hover');

                             // Supprimer de l'instance Sortable d'origine
                             const sourceSortable = sortableInstances[currentDashboardId];
                              if(sourceSortable && itemToRemove.parentNode === sourceSortable.el) {
                                  itemToRemove.parentNode.removeChild(itemToRemove);
                                   // Sauver l'ordre du dashboard source APRES suppression
                                   const sourceItems = Array.from(sourceSortable.el.children).filter(el => el.classList.contains('dashboard-item'));
                                   saveDashboardOrder(currentDashboardId, sourceItems.map(item => item.dataset.serviceId));
                              }

                             // 3. Naviguer vers le nouveau dashboard (qui rechargera les items incluant le nouveau)
                             navigateToDashboard(newDashboardId);

                         } else {
                             console.error("Erreur API moveService (zone hover):", data.error || 'Erreur inconnue');
                             dropZoneLeft.classList.remove('drop-hover');
                             dropZoneRight.classList.remove('drop-hover');
                             isHoveringZone = false; // Permettre nouvelle tentative
                         }
                     })
                     .catch(err => {
                         console.error("Erreur fetch moveService (zone hover):", err);
                         dropZoneLeft.classList.remove('drop-hover');
                         dropZoneRight.classList.remove('drop-hover');
                         isHoveringZone = false;
                     });

             }, 800); // Délai avant déclenchement
         } else if (!targetDashboard) {
              // Survole une zone mais pas de dashboard cible
              isHoveringZone = false;
              clearTimeout(switchDashboardTimer);
              if(direction === 'left') dropZoneLeft.classList.remove('drop-hover');
              if(direction === 'right') dropZoneRight.classList.remove('drop-hover');
         }
     };

    // --- NOUVEAU: Initialiser interact.js pour le redimensionnement ---
    function initInteractForItems(container) {
        if (!container) return;
        // Cibler les items qui n'ont pas déjà été initialisés par interact
        interact(container.querySelectorAll('.dashboard-item:not(.interact-initialized)'))
            .resizable({
                edges: { left: false, right: true, bottom: true, top: false }, // Poignées en bas à droite (ajuster si besoin)
                listeners: {
                    move(event) {
                        let target = event.target;
                        // Mettre à jour la taille via les styles inline pendant le redimensionnement
                        target.style.width = event.rect.width + 'px';
                        target.style.height = event.rect.height + 'px';
                         // Ajouter une classe pendant le resize pour feedback visuel
                         target.classList.add('resizing');
                    },
                    end(event) {
                        const target = event.target;
                        target.classList.remove('resizing'); // Enlever la classe de feedback
                        const serviceId = target.dataset.serviceId;
                        const newWidth = parseInt(target.style.width, 10);
                        const newHeight = parseInt(target.style.height, 10);

                        console.log(`Resized service ${serviceId} to ${newWidth}x${newHeight}`);

                        // --- Logique pour déterminer la nouvelle classe de taille ---
                        // Ceci est un exemple simple, à adapter selon vos besoins et CSS Grid
                        let newSizeClass = 'size-medium'; // Par défaut
                        const columnWidth = 180; // Largeur de base d'une colonne (doit correspondre au CSS)
                        const rowHeight = 120; // Hauteur approximative d'une ligne (ajuster)

                        if (newWidth >= (columnWidth * 2 - 15)) { // Si largeur >= 2 colonnes (moins le gap)
                            newSizeClass = 'size-large';
                        } else if (newHeight < (rowHeight - 10)) { // Si hauteur < 1 ligne
                            newSizeClass = 'size-small';
                        }
                        // Appliquer la nouvelle classe et potentiellement retirer l'ancienne
                         target.classList.remove('size-small', 'size-medium', 'size-large');
                         target.classList.add(newSizeClass);
                         // Reset des styles inline pour laisser le CSS gérer la taille finale via la classe
                         target.style.width = '';
                         target.style.height = '';


                        // Sauvegarder la nouvelle classe
                        saveServiceSize(serviceId, newSizeClass);
                    }
                },
                modifiers: [
                    // Restreindre la taille minimale/maximale
                    interact.modifiers.restrictSize({
                        min: { width: 150, height: 80 }, // Ajuster les tailles min
                        // max: { width: 500, height: 300 } // Décommenter si max souhaité
                    }),
                    // Optionnel: restreindre aux 'pas' de la grille (plus complexe)
                     /* interact.modifiers.snapSize({
                         targets: [
                           interact.snappers.grid({ width: columnWidth + 15, height: rowHeight + 15}) // Taille + gap
                         ]
                     })*/
                ],
                inertia: false // Pas d'inertie pour le redimensionnement
            })
            // Marquer comme initialisé pour éviter ré-attachement des écouteurs
            .on('resizemove resizestart resizeend', (event) => {
                 event.target.classList.add('interact-initialized');
            });
    }

    // --- NOUVEAU: Sauvegarder l'ordre via API ---
    function saveDashboardOrder(dashboardId, orderedIds) {
        if (!dashboardId || !orderedIds) return;
        console.log(`Saving order for dashboard ${dashboardId}:`, orderedIds);
        fetch(`/api/services/layout/save`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            // S'assurer que le payload correspond à ce qu'attend le backend
            body: JSON.stringify({ dashboardId: parseInt(dashboardId, 10), ids: orderedIds }),
            keepalive: true // Important pour que la requête parte même si on quitte la page
        })
        .then(response => response.ok ? response.json() : Promise.reject(`HTTP error ${response.status}`))
        .then(data => {
            if (!data.success) {
                console.error("Error saving layout via API:", data.error);
                // TODO: Notifier l'utilisateur de l'échec
            }
        })
        .catch(err => {
            console.error("Fetch Error saveDashboardOrder:", err);
            // TODO: Notifier l'utilisateur de l'échec
        });
    }

    // --- NOUVEAU: Sauvegarder la taille via API ---
    function saveServiceSize(serviceId, sizeClass) {
        if (!serviceId || !sizeClass) return;
        console.log(`Saving size for service ${serviceId}: ${sizeClass}`);
        fetch(`/api/service/resize/${serviceId}`, { // Utilise le nouvel endpoint
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sizeClass: sizeClass }),
            keepalive: true
        })
        .then(response => response.ok ? response.json() : Promise.reject(`HTTP error ${response.status}`))
        .then(data => {
            if (!data.success) {
                console.error("Error saving size via API:", data.error);
                // TODO: Notifier l'utilisateur
            }
        })
        .catch(err => {
            console.error("Fetch Error saveServiceSize:", err);
             // TODO: Notifier l'utilisateur
        });
    }


    // --- Logique de construction/mise à jour de la grille (MODIFIÉE) ---
    const buildServicesGrid = (dashboardId) => {
        const container = getDashboardContainer(dashboardId, true); // S'assurer que le conteneur existe
        if (!container) return Promise.reject("Conteneur de dashboard introuvable.");

        container.innerHTML = '<p class="loading-message">Chargement...</p>'; // Indicateur
        container.style.display = 'grid'; // Afficher (même si vide pour l'instant)

        return fetch(`/api/services?dashboard_id=${dashboardId}`)
            .then(response => response.ok ? response.json() : Promise.reject(`HTTP error ${response.status}`))
            .then(services => {
                container.innerHTML = ''; // Nettoyer

                if (services.error) { throw new Error(services.error); }

                if (!services || services.length === 0) {
                     container.innerHTML = '<p class="loading-message">Ce dashboard est vide.</p>';
                     // Arrêter le rafraîchissement s'il n'y a rien à vérifier
                     if (statusRefreshInterval) clearInterval(statusRefreshInterval);
                     return;
                }

                services.forEach(service => {
                    const item = document.createElement('div');
                    // Appliquer la classe de taille et autres classes nécessaires
                    item.className = `dashboard-item ${service.size_class || 'size-medium'}`;
                    item.dataset.serviceId = service.id;
                    item.dataset.dashboardId = dashboardId; // Important pour savoir d'où vient l'item
                    item.dataset.url = service.url; // Pour le check de statut

                    let iconHtml = service.icone_url
                        ? `<img src="${service.icone_url}" class="card-icon-custom" alt="">` // Alt vide pour déco
                        : `<i class="${service.icone || 'fas fa-link'} card-icon-fa"></i>`;

                    // Structure interne de la tuile
                    item.innerHTML = `
                        <div class="dashboard-item-content ${service.card_color ? 'custom-color' : ''}" style="${service.card_color ? '--card-bg-color-custom:' + service.card_color : ''}">
                             <div class="card-status" title="Vérification du statut..."></div>
                             <div class="card-latency" title="Latence...">...</div>
                             <a href="${service.url}" target="_blank" class="card-link" title="${service.description || service.nom}">
                                 <div class="card-icon">${iconHtml}</div>
                                 <div class="card-title">${service.nom}</div>
                             </a>
                             <div class="resize-handle"></div> </div>`;

                    container.appendChild(item);
                    // Lancer le premier check de statut pour cet item
                    checkServiceStatus(service.url, item);
                });

                // Initialiser interact.js sur TOUS les items de ce conteneur (même ceux déjà initialisés, interact gère ça)
                initInteractForItems(container);

                // (Ré)Démarrer le rafraîchissement périodique
                startStatusRefresh();
            })
            .catch(error => {
                console.error("Erreur buildServicesGrid:", error);
                container.innerHTML = `<p class="loading-message">Erreur de chargement: ${error.message}</p>`;
                if (statusRefreshInterval) clearInterval(statusRefreshInterval);
            });
    };

    // --- Navigation entre Dashboards (MODIFIÉE) ---
    const navigateToDashboard = (dashboardId) => {
        const targetId = parseInt(dashboardId, 10);
        if (isNaN(targetId) || isNavigating || targetId === currentDashboardId) {
             return; // ID invalide, navigation déjà en cours, ou déjà sur ce dashboard
        }
        isNavigating = true;
        console.log(`Navigating to dashboard ${targetId}`);

        const previousDashboardId = currentDashboardId;
        currentDashboardId = targetId;

        // Cacher l'ancien conteneur
        if (previousDashboardId) {
            const oldContainer = getDashboardContainer(previousDashboardId);
            if (oldContainer) oldContainer.style.display = 'none';
        }

        // Mettre à jour l'onglet actif visuellement
        document.querySelectorAll('.dashboard-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.id == targetId);
        });

        // Afficher/Construire le nouveau conteneur
        const container = getDashboardContainer(targetId, true); // Crée si besoin
        if (!container) {
             console.error("Impossible de créer/trouver le conteneur pour", targetId);
             isNavigating = false;
             return; // Ne devrait pas arriver
        }

        const needsBuilding = !container.querySelector('.dashboard-item') && !container.querySelector('.loading-message');

        if (needsBuilding) {
             // Charger les données si le conteneur est vide
            buildServicesGrid(targetId).finally(() => { isNavigating = false; updateNavArrows(); });
        } else {
             // Juste afficher le conteneur s'il a déjà du contenu
            container.style.display = 'grid'; // Assurez-vous que c'est le bon display
            isNavigating = false;
            updateNavArrows();
             // Relancer un refresh immédiat car les statuts pourraient être vieux
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

    // --- Chargement initial (MODIFIÉ) ---
    const loadTabsAndFirstDashboard = () => {
         dashboardsWrapper.innerHTML = '<p class="loading-message">Chargement initial...</p>'; // Message initial

         fetch('/api/dashboards')
            .then(response => response.ok ? response.json() : Promise.reject(`HTTP error ${response.status}`))
            .then(dashboards => {
                tabsContainer.innerHTML = ''; // Vider anciens onglets
                dashboardsWrapper.innerHTML = ''; // Vider message initial
                sortableInstances = {}; // Réinitialiser

                if (dashboards.error) { throw new Error(dashboards.error); }

                if (!dashboards || dashboards.length === 0) {
                     dashboardsWrapper.innerHTML = '<p class="loading-message">Aucun dashboard. Ajoutez-en un via <i class="fas fa-cog"></i>.</p>';
                     allDashboards = [];
                     updateNavArrows(); // Cacher les flèches
                     // Ajouter quand même le bouton '+'
                     appendAddButtonToTabs();
                     return;
                }
                allDashboards = dashboards;

                dashboards.forEach(db => {
                    const tab = createDashboardTabElement(db);
                    tabsContainer.appendChild(tab);
                    // Pré-créer le conteneur vide pour chaque dashboard
                    getDashboardContainer(db.id, true);
                });

                appendAddButtonToTabs(); // Ajouter le bouton '+'
                initTabSorting(); // Activer le tri des onglets

                // Charger et afficher le premier dashboard
                if (dashboards.length > 0) {
                    navigateToDashboard(dashboards[0].id);
                } else {
                     updateNavArrows(); // S'assurer que les flèches sont cachées
                }
            })
            .catch(error => {
                console.error("Erreur loadTabsAndFirstDashboard:", error);
                dashboardsWrapper.innerHTML = `<p class="loading-message">Erreur critique au chargement: ${error.message}</p>`;
                tabsContainer.innerHTML = ''; // Vider aussi les onglets en cas d'erreur grave
                updateNavArrows();
            });
    };

    // --- Fonctions utilitaires pour les onglets ---
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
        const addBtn = document.createElement('button');
        addBtn.className = 'dashboard-tab add-tab-btn';
        addBtn.id = 'add-new-btn';
        addBtn.innerHTML = '<i class="fas fa-plus"></i>';
        addBtn.title = "Ajouter/Gérer Dashboards & Services";
        addBtn.addEventListener('click', () => {
            if(settingsModal) {
                 settingsModal.style.display = 'flex';
                 showModalTab('tab-dashboards'); // Ouvrir gestion dashboards par défaut
            }
        });
        tabsContainer.appendChild(addBtn);
    }

    function initTabSorting() {
         new Sortable(tabsContainer, {
            animation: 150,
            filter: '.add-tab-btn', // Ne pas déplacer le bouton '+'
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: (evt) => {
                const tabs = Array.from(tabsContainer.children);
                const newOrderIds = tabs
                    .filter(tab => tab.dataset.id) // Exclure le bouton '+'
                    .map(tab => tab.dataset.id);

                // Mettre à jour l'ordre local pour la navigation immédiate
                allDashboards.sort((a, b) => newOrderIds.indexOf(a.id.toString()) - newOrderIds.indexOf(b.id.toString()));
                updateNavArrows(); // Mettre à jour la visibilité des flèches

                // Sauvegarde de l'ordre des dashboards via API
                fetch('/api/dashboards/layout/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(newOrderIds),
                    keepalive: true
                }).catch(err => console.error("Erreur sauvegarde ordre onglets:", err));
            }
        });
    }

    // --- Écouteurs globaux (Navigation flèches, molette onglets) ---
    navArrowLeft.addEventListener('click', navigatePrev);
    navArrowRight.addEventListener('click', navigateNext);
    tabsContainer.addEventListener('wheel', throttle((event) => {
        event.preventDefault();
        if (event.deltaY > 0) navigateNext();
        else navigatePrev();
    }, 200));

    // --- Lancement initial ---
    loadTabsAndFirstDashboard();

}); // Fin DOMContentLoaded
