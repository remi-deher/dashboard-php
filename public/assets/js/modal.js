// Fichier: /public/assets/js/modal.js

// Fonction utilitaire pour les onglets de la modale de gestion
function showModalTab(tabId) {
    const modalTabs = document.querySelectorAll('.modal-tab-btn');
    const modalTabContents = document.querySelectorAll('.modal-tab-content');
    
    modalTabContents.forEach(content => content.classList.toggle('active', content.id === tabId));
    modalTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === tabId));
};

/**
 * Initialise toute la logique pour les 3 modales
 */
function initModals(elements) {
    const {
        settingsModal, openModalBtn, closeModalBtn,
        quickAddFab, quickAddServiceModal, closeQuickAddServiceModal, quickAddServiceDashboardIdInput,
        addDashboardTabBtn, quickAddDashboardModal, closeQuickAddDashboardModal
    } = elements;

    // 1. Modale de GESTION (la grande)
    if (settingsModal && openModalBtn && closeModalBtn) {

        // --- DÉBUT DE LA CORRECTION ---
        // Sélectionner les onglets A L'INTÉRIEUR de la modale de gestion
        const modalTabs = settingsModal.querySelectorAll('.modal-tab-btn'); 
        
        // Ajouter l'écouteur de clic manquant
        modalTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                showModalTab(tab.dataset.tab);
            });
        });
        // --- FIN DE LA CORRECTION ---

        openModalBtn.addEventListener('click', () => {
            settingsModal.style.display = 'flex';
            if (!window.location.pathname.includes('/edit/')) {
                showModalTab('tab-dashboards');
            }
        });

        const closeModalAction = () => {
             settingsModal.style.display = 'none';
             if (window.location.pathname.includes('/edit/')) {
                 window.history.pushState({}, '', '/');
             }
        }
        closeModalBtn.addEventListener('click', closeModalAction);

        // Gérer l'ouverture directe via URL
        if (window.location.pathname.includes('/service/edit/')) {
            if (settingsModal) settingsModal.style.display = 'flex';
            showModalTab('tab-services');
        } else if (window.location.pathname.includes('/dashboard/edit/')) {
            if (settingsModal) settingsModal.style.display = 'flex';
            showModalTab('tab-dashboards');
        }
    }
    
    // 2. Modale AJOUT SERVICE (FAB +)
    if (quickAddFab && quickAddServiceModal && quickAddServiceDashboardIdInput) {
        quickAddFab.addEventListener('click', () => {
            if (STATE.currentDashboardId) {
                quickAddServiceDashboardIdInput.value = STATE.currentDashboardId;
                quickAddServiceModal.style.display = 'flex';
                quickAddServiceModal.querySelector('input[name="nom"]').focus();
            } else {
                alert("Veuillez d'abord charger un dashboard.");
            }
        });
        closeQuickAddServiceModal.addEventListener('click', () => quickAddServiceModal.style.display = 'none');
    }

    // 3. Modale AJOUT DASHBOARD (Onglet +)
    if (addDashboardTabBtn && quickAddDashboardModal && closeQuickAddDashboardModal) {
        addDashboardTabBtn.addEventListener('click', () => {
            quickAddDashboardModal.style.display = 'flex';
            quickAddDashboardModal.querySelector('input[name="nom"]').focus();
        });
        closeQuickAddDashboardModal.addEventListener('click', () => quickAddDashboardModal.style.display = 'none');
    }

    // 4. Logique de fermeture partagée (clic sur fond)
    window.addEventListener('click', (event) => {
        if (event.target === settingsModal) {
            settingsModal.style.display = 'none'; // Utilise la fermeture simple
             if (window.location.pathname.includes('/edit/')) {
                 window.history.pushState({}, '', '/');
             }
        }
        if (event.target === quickAddServiceModal) {
            quickAddServiceModal.style.display = 'none';
        }
        if (event.target === quickAddDashboardModal) {
            quickAddDashboardModal.style.display = 'none';
        }
    });
}
