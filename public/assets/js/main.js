// Fichier: /public/assets/js/main.js

// -------------------------------------------------------------------
// OBJET DOM : stocke les éléments DOM fréquemment utilisés
// -------------------------------------------------------------------
const DOM = {
    tabsContainer: null,
    dashboardsWrapper: null,
    settingsModal: null,
    openModalBtn: null,
    closeModalBtn: null,
    navArrowLeft: null,
    navArrowRight: null,
    dropZoneLeft: null,
    dropZoneRight: null,
    
    // Modales "Ajout Rapide" (le parcours)
    quickAddFab: null, // Le bouton +
    
    // Étape 1 (Nouvelle modale)
    quickAddStep1_ChoiceModal: null,
    closeQuickAddStep1Modal: null,

    // Étape 2 (L'ancienne modale, maintenant réutilisée)
    quickAddStep2_ServiceModal: null,
    closeQuickAddStep2Modal: null,
    quickAddServiceDashboardIdInput: null,
    
    // Bouton "+" des onglets
    addDashboardTabBtn: null, // Note: cet élément est créé
    quickAddDashboardModal: null,
    closeQuickAddDashboardModal: null
};

// -------------------------------------------------------------------
// OBJET STATE : stocke l'état partagé de l'application
// -------------------------------------------------------------------
const STATE = {
    sortableInstances: {},
    currentDashboardId: null,
    allDashboards: [],
    statusRefreshInterval: null,
    isNavigating: false,
    draggedItem: null,
    switchDashboardTimer: null,
    isHoveringZone: false
};


// -------------------------------------------------------------------
// POINT D'ENTRÉE PRINCIPAL
// -------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {

    // 1. Récupérer tous les éléments DOM
    DOM.tabsContainer = document.getElementById('dashboard-tabs-container');
    DOM.dashboardsWrapper = document.getElementById('dashboards-wrapper');
    DOM.settingsModal = document.getElementById('settings-modal');
    DOM.openModalBtn = document.getElementById('open-settings-modal');
    DOM.closeModalBtn = document.getElementById('close-settings-modal');
    DOM.navArrowLeft = document.getElementById('nav-arrow-left');
    DOM.navArrowRight = document.getElementById('nav-arrow-right');
    DOM.dropZoneLeft = document.getElementById('drop-zone-left');
    DOM.dropZoneRight = document.getElementById('drop-zone-right');
    
    // Modales "Ajout Rapide" (le parcours)
    DOM.quickAddFab = document.getElementById('quick-add-service-fab');
    DOM.quickAddStep1_ChoiceModal = document.getElementById('modal-add-step1-choice');
    DOM.closeQuickAddStep1Modal = document.getElementById('close-modal-add-step1');
    DOM.quickAddStep2_ServiceModal = document.getElementById('quick-add-service-modal');
    DOM.closeQuickAddStep2Modal = document.getElementById('close-quick-add-service-modal');
    DOM.quickAddServiceDashboardIdInput = document.getElementById('quick-add-service-dashboard-id');
    
    DOM.quickAddDashboardModal = document.getElementById('quick-add-dashboard-modal');
    DOM.closeQuickAddDashboardModal = document.getElementById('close-quick-add-dashboard-modal');

    // Créer l'élément pour le bouton "+" des onglets
    DOM.addDashboardTabBtn = document.createElement('button');
    DOM.addDashboardTabBtn.className = 'dashboard-tab add-tab-btn';
    DOM.addDashboardTabBtn.innerHTML = '<i class="fas fa-plus"></i>';
    DOM.addDashboardTabBtn.title = "Ajouter un dashboard";

    // 2. Initialiser les modules
    initModals(DOM); // Initialise la logique des 3 modales (depuis modal.js)
    loadTabsAndFirstDashboard(); // Charge les données et lance l'app (depuis dashboard.js)

    // 3. Attacher les écouteurs de navigation principaux
    // --- FIX : AJOUT DE VÉRIFICATIONS DE NULLITÉ ---
    // (Cela empêchera les erreurs JS si le PHP échoue)
    if (DOM.navArrowLeft) {
        DOM.navArrowLeft.addEventListener('click', navigatePrev);
    }
    if (DOM.navArrowRight) {
        DOM.navArrowRight.addEventListener('click', navigateNext);
    }
    if (DOM.tabsContainer) {
        DOM.tabsContainer.addEventListener('wheel', throttle((event) => {
            event.preventDefault();
            if (event.deltaY > 0) navigateNext();
            else navigatePrev();
        }, 200));
    }
    // --- FIN DU FIX ---

}); // Fin DOMContentLoaded
