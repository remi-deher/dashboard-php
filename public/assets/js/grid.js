// Fichier: /public/assets/js/grid.js

/**
 * Initialise SortableJS pour un conteneur de dashboard
 */
function initSortableForContainer(container) {
    const dashboardId = container.dataset.dashboardId;
    if (!dashboardId) {
        console.error("Impossible d'initialiser Sortable: dashboardId manquant");
        return;
    }
    if (STATE.sortableInstances[dashboardId]) {
        STATE.sortableInstances[dashboardId].destroy();
    }

    STATE.sortableInstances[dashboardId] = new Sortable(container, {
        group: 'shared-dashboards',
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        filter: '.resize-handle, a',
        preventOnFilter: true,

        onStart: (evt) => {
            STATE.draggedItem = evt.item;
            const prevDb = getDashboardByIndex(-1);
            const nextDb = getDashboardByIndex(1);
            if(prevDb) {
                DOM.dropZoneLeft.querySelector('.zone-label').textContent = prevDb.nom;
                DOM.dropZoneLeft.classList.add('visible');
            }
            if(nextDb) {
                DOM.dropZoneRight.querySelector('.zone-label').textContent = nextDb.nom;
                DOM.dropZoneRight.classList.add('visible');
            }
            document.addEventListener('dragover', handleDragOverZone);
        },
        onEnd: (evt) => {
            document.removeEventListener('dragover', handleDragOverZone);
            DOM.dropZoneLeft.classList.remove('visible', 'drop-hover');
            DOM.dropZoneRight.classList.remove('visible', 'drop-hover');
            clearTimeout(STATE.switchDashboardTimer);
            STATE.draggedItem = null;
            STATE.isHoveringZone = false;

            const targetDashboardId = evt.to.dataset.dashboardId;
            const items = Array.from(evt.to.children).filter(el => el.classList.contains('dashboard-item'));
            const orderIds = items.map(item => item.dataset.serviceId);
            
            // APPEL API
            apiSaveServiceLayout(targetDashboardId, orderIds)
                .catch(err => console.error("Error saving layout:", err));
        },
        onAdd: (evt) => {
            const serviceId = evt.item.dataset.serviceId;
            const newDashboardId = evt.to.dataset.dashboardId;
            const oldDashboardId = evt.from.dataset.dashboardId;

            if (!serviceId || !newDashboardId || !oldDashboardId || newDashboardId === oldDashboardId) return;

            evt.item.dataset.dashboardId = newDashboardId;
            console.log(`[onAdd] Service ${serviceId} moved from ${oldDashboardId} to ${newDashboardId}`);

            // APPEL API
            apiMoveService(serviceId, newDashboardId)
                .catch(error => console.error("Erreur fetch moveService:", error));
        }
    });
}

/**
 * Gestionnaire pour le survol des zones latérales PENDANT le drag
 */
function handleDragOverZone(event) {
     if (!STATE.draggedItem) return;

     const clientX = event.clientX;
     const zoneWidth = 90;
     let targetDashboard = null;
     let direction = null;

     if (clientX < zoneWidth) {
         targetDashboard = getDashboardByIndex(-1);
         direction = 'left';
         if (targetDashboard) {
             DOM.dropZoneLeft.classList.add('drop-hover');
             DOM.dropZoneRight.classList.remove('drop-hover');
         } else {
             DOM.dropZoneLeft.classList.remove('drop-hover');
         }
     } else if (clientX > window.innerWidth - zoneWidth) {
         targetDashboard = getDashboardByIndex(1);
         direction = 'right';
         if (targetDashboard) {
             DOM.dropZoneRight.classList.add('drop-hover');
             DOM.dropZoneLeft.classList.remove('drop-hover');
         } else {
              DOM.dropZoneRight.classList.remove('drop-hover');
         }
     } else {
         STATE.isHoveringZone = false;
         clearTimeout(STATE.switchDashboardTimer);
         DOM.dropZoneLeft.classList.remove('drop-hover');
         DOM.dropZoneRight.classList.remove('drop-hover');
         return;
     }

     if (targetDashboard && STATE.isHoveringZone !== direction) {
         STATE.isHoveringZone = direction;
         clearTimeout(STATE.switchDashboardTimer);

         STATE.switchDashboardTimer = setTimeout(() => {
             if (!STATE.draggedItem || !STATE.isHoveringZone || !targetDashboard?.id) return;

             const serviceId = STATE.draggedItem.dataset.serviceId;
             const currentDashboardId = STATE.draggedItem.dataset.dashboardId;
             const newDashboardId = targetDashboard.id;

             console.log(`[Zone Hover] Attempting to move ${serviceId} to ${newDashboardId}`);

             // APPEL API
             apiMoveService(serviceId, newDashboardId)
                 .then(data => {
                     if (data && data.success) {
                         const itemToRemove = STATE.draggedItem;
                         STATE.draggedItem = null;
                         STATE.isHoveringZone = false;
                         clearTimeout(STATE.switchDashboardTimer);
                         document.removeEventListener('dragover', handleDragOverZone);
                         DOM.dropZoneLeft.classList.remove('visible', 'drop-hover');
                         DOM.dropZoneRight.classList.remove('visible', 'drop-hover');

                         const sourceSortable = STATE.sortableInstances[currentDashboardId];
                          if(sourceSortable && itemToRemove.parentNode === sourceSortable.el) {
                              itemToRemove.parentNode.removeChild(itemToRemove);
                               const sourceItems = Array.from(sourceSortable.el.children).filter(el => el.classList.contains('dashboard-item'));
                               // APPEL API
                               apiSaveServiceLayout(currentDashboardId, sourceItems.map(item => item.dataset.serviceId));
                          }
                         navigateToDashboard(newDashboardId);
                     } else {
                         console.error("Erreur API moveService (zone hover):", data.error || 'Erreur inconnue');
                         DOM.dropZoneLeft.classList.remove('drop-hover');
                         DOM.dropZoneRight.classList.remove('drop-hover');
                         STATE.isHoveringZone = false;
                     }
                 })
                 .catch(err => {
                     console.error("Erreur fetch moveService (zone hover):", err);
                     DOM.dropZoneLeft.classList.remove('drop-hover');
                     DOM.dropZoneRight.classList.remove('drop-hover');
                     STATE.isHoveringZone = false;
                 });

         }, 800);
     } else if (!targetDashboard) {
          STATE.isHoveringZone = false;
          clearTimeout(STATE.switchDashboardTimer);
          if(direction === 'left') DOM.dropZoneLeft.classList.remove('drop-hover');
          if(direction === 'right') DOM.dropZoneRight.classList.remove('drop-hover');
     }
 };

/**
 * Initialise interact.js pour le redimensionnement
 */
function initInteractForItems(container) {
    if (!container) return;
    interact(container.querySelectorAll('.dashboard-item:not(.interact-initialized)'))
        .resizable({
            edges: { left: false, right: true, bottom: true, top: false },
            listeners: {
                move(event) {
                    let target = event.target;
                    target.style.width = event.rect.width + 'px';
                    target.style.height = event.rect.height + 'px';
                    target.classList.add('resizing');
                },
                end(event) {
                    const target = event.target;
                    target.classList.remove('resizing');
                    const serviceId = target.dataset.serviceId;
                    const newWidth = parseInt(target.style.width, 10);
                    const newHeight = parseInt(target.style.height, 10);
                    
                    let newSizeClass = 'size-medium';
                    const columnWidth = 180;
                    const rowHeight = 120;
                    if (newWidth >= (columnWidth * 2 - 15)) {
                        newSizeClass = 'size-large';
                    } else if (newHeight < (rowHeight - 10)) {
                        newSizeClass = 'size-small';
                    }
                     target.classList.remove('size-small', 'size-medium', 'size-large');
                     target.classList.add(newSizeClass);
                     target.style.width = '';
                     target.style.height = '';

                    // APPEL API
                    apiSaveServiceSize(serviceId, newSizeClass)
                        .catch(err => console.error("Error saving size:", err));
                }
            },
            modifiers: [
                interact.modifiers.restrictSize({
                    min: { width: 150, height: 80 },
                }),
            ],
            inertia: false
        })
        .on('resizemove resizestart resizeend', (event) => {
             event.target.classList.add('interact-initialized');
        });
}

/**
 * Initialise le tri des onglets
 */
function initTabSorting(container) {
     new Sortable(container, {
        animation: 150,
        filter: '.add-tab-btn',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: (evt) => {
            const tabs = Array.from(container.children);
            const newOrderIds = tabs
                .filter(tab => tab.dataset.id)
                .map(tab => tab.dataset.id);

            STATE.allDashboards.sort((a, b) => newOrderIds.indexOf(a.id.toString()) - newOrderIds.indexOf(b.id.toString()));
            updateNavArrows(); // Mettre à jour la visibilité des flèches

            // APPEL API
            apiSaveDashboardLayout(newOrderIds)
                .catch(err => console.error("Erreur sauvegarde ordre onglets:", err));
        }
    });
}
