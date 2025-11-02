// Fichier: /public/assets/js/modal.js

// Fonction utilitaire pour les onglets de la modale de gestion
function showModalTab(tabId) {
    const modalTabs = document.querySelectorAll('.modal-tab-btn');
    const modalTabContents = document.querySelectorAll('.modal-tab-content');
    
    modalTabContents.forEach(content => content.classList.toggle('active', content.id === tabId));
    modalTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === tabId));
};


// --- DÉBUT DES FONCTIONS DÉPLACÉES (FIX) ---
// Logique pour le sélecteur M365 dynamique

let m365TargetsCache = null; // Cache pour éviter les appels API multiples
let isFetching = false;

/**
 * Appelle l'API pour lister les utilisateurs et groupes
 */
function fetchM365Targets() {
    if (m365TargetsCache) {
        return Promise.resolve(m365TargetsCache);
    }
    if (isFetching) {
        return new Promise(resolve => setTimeout(() => resolve(fetchM365Targets()), 100));
    }
    
    isFetching = true;
    return fetch('/api/m365/targets')
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur de connexion M365. Vérifiez la connexion dans l\'onglet Widgets.');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            m365TargetsCache = data;
            isFetching = false;
            return data;
        })
        .catch(err => {
            isFetching = false;
            console.error(err);
            return [{ name: err.message, email: '' }];
        });
}

/**
 * Gère l'affichage/masquage des champs
 */
function toggleM365Selector(form, widgetType, currentValue) {
    const descGroup = form.querySelector('[data-role="description-group"]');
    const m365Group = form.querySelector('[data-role="m365-target-group"]');
    
    if (!descGroup || !m365Group) return; 
    
    const m365Select = m365Group.querySelector('select');
    const descTextarea = descGroup.querySelector('textarea');

    if (widgetType.startsWith('m365_')) {
        // C'est un widget M365
        descGroup.style.display = 'none';
        if (descTextarea) descTextarea.disabled = true;
        
        m365Group.style.display = 'block';
        if (m365Select) m365Select.disabled = false;
        
        if (m365Select) m365Select.innerHTML = '<option value="">Chargement...</option>';
        fetchM365Targets().then(targets => {
            if (!m365Select) return;
            m365Select.innerHTML = '';
            if (targets.length === 0) {
                 m365Select.innerHTML = '<option value="">Aucune cible trouvée</option>';
                 return;
            }
            targets.forEach(target => {
                const isSelected = (target.email === currentValue);
                m365Select.innerHTML += `<option value="${target.email}" ${isSelected ? 'selected' : ''}>${target.name}</option>`;
            });
        });

    } else {
        // C'est un autre widget ou un lien
        descGroup.style.display = 'block';
        if (descTextarea) descTextarea.disabled = false;
        
        m365Group.style.display = 'none';
        if (m365Select) m365Select.disabled = true;
    }
}

/**
 * Initialise la logique pour un formulaire donné (Add ou Edit)
 */
function setupM365Selector(formElement) {
    const typeSelect = formElement.querySelector('[data-role="widget-type-select"]');
    const descriptionTextarea = formElement.querySelector('[data-role="description-group"] textarea');

    if (!typeSelect || !descriptionTextarea) return;

    typeSelect.addEventListener('change', function() {
        toggleM365Selector(formElement, this.value, null);
    });

    // Vérifie l'état initial
    const initialWidgetType = typeSelect.value;
    const initialDescriptionValue = descriptionTextarea.value;
    toggleM365Selector(formElement, initialWidgetType, initialDescriptionValue);
}

// --- FIN DES FONCTIONS DÉPLACÉES ---


/**
 * Initialise toute la logique pour les modales
 */
function initModals(elements) {
    const {
        settingsModal, openModalBtn, closeModalBtn,
        
        quickAddFab,
        quickAddStep1_ChoiceModal, closeQuickAddStep1Modal,
        quickAddStep2_ServiceModal, closeQuickAddStep2Modal, quickAddServiceDashboardIdInput,

        addDashboardTabBtn, quickAddDashboardModal, closeQuickAddDashboardModal
    } = elements;

    // 1. Modale de GESTION (la grande)
    if (settingsModal && openModalBtn && closeModalBtn) {
        const modalTabs = settingsModal.querySelectorAll('.modal-tab-btn'); 
        
        modalTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                showModalTab(tab.dataset.tab);
            });
        });

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

        if (window.location.pathname.includes('/service/edit/')) {
            if (settingsModal) settingsModal.style.display = 'flex';
            showModalTab('tab-services');
        } else if (window.location.pathname.includes('/dashboard/edit/')) {
            if (settingsModal) settingsModal.style.display = 'flex';
            showModalTab('tab-dashboards');
        }
        
        // --- APPEL AJOUTÉ ---
        // Initialise le sélecteur M365 pour le formulaire d'ÉDITION
        const editForm = settingsModal.querySelector('form[data-form="edit-service"]');
        if (editForm) {
            setupM365Selector(editForm);
        }
        // --- FIN ---
    }
    
    // 2. PARCOURS D'AJOUT DE SERVICE (WIZARD)
    if (quickAddFab && quickAddStep1_ChoiceModal && quickAddStep2_ServiceModal) {
        
        // ÉTAPE 1 : Le bouton + ouvre la modale de CHOIX
        quickAddFab.addEventListener('click', () => {
            if (STATE.currentDashboardId) {
                if (quickAddServiceDashboardIdInput) {
                    quickAddServiceDashboardIdInput.value = STATE.currentDashboardId;
                }
                quickAddStep1_ChoiceModal.style.display = 'flex';
            } else {
                alert("Veuillez d'abord charger un dashboard.");
            }
        });
        
        if (closeQuickAddStep1Modal) {
            closeQuickAddStep1Modal.addEventListener('click', () => quickAddStep1_ChoiceModal.style.display = 'none');
        }

        // Logique de navigation (Étape 1 -> Étape 2)
        quickAddStep1_ChoiceModal.querySelectorAll('.choice-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const choice = btn.dataset.choice; // 'link' or 'widget'
                
                const form = quickAddStep2_ServiceModal.querySelector('form[data-form="quick-add"]');
                if (!form) return;
                
                const typeSelect = form.querySelector('[data-role="widget-type-select"]');
                if (!typeSelect) return;
                
                const typeSelectGroup = typeSelect.closest('.form-group');
                
                if (choice === 'link') {
                    typeSelect.value = 'link';
                    if (typeSelectGroup) typeSelectGroup.style.display = 'none';
                    toggleM365Selector(form, 'link', null);

                } else {
                    if (typeSelectGroup) typeSelectGroup.style.display = 'block';
                    
                    Array.from(typeSelect.options).forEach(opt => {
                        opt.style.display = (opt.value === 'link') ? 'none' : 'block';
                    });
                    
                    // --- FIX : Correction du sélecteur CSS ---
                    const firstWidget = typeSelect.querySelector('option:not([value="link"])');
                    // --- FIN DU FIX ---
                    
                    if (firstWidget) {
                        typeSelect.value = firstWidget.value;
                    }
                    
                    toggleM365Selector(form, typeSelect.value, null);
                }

                quickAddStep1_ChoiceModal.style.display = 'none';
                quickAddStep2_ServiceModal.style.display = 'flex';
                
                const nomInput = quickAddStep2_ServiceModal.querySelector('input[name="nom"]');
                if (nomInput) nomInput.focus();
            });
        });
        
        if (closeQuickAddStep2Modal) {
            closeQuickAddStep2Modal.addEventListener('click', () => quickAddStep2_ServiceModal.style.display = 'none');
        }

        // --- APPEL AJOUTÉ ---
        // Initialise le sélecteur M365 pour le formulaire d'AJOUT RAPIDE
        const quickAddForm = quickAddStep2_ServiceModal.querySelector('form[data-form="quick-add"]');
        if (quickAddForm) {
            setupM365Selector(quickAddForm);
        }
        // --- FIN ---
    }

    // 3. Modale AJOUT DASHBOARD (Onglet +)
    if (addDashboardTabBtn && quickAddDashboardModal && closeQuickAddDashboardModal) {
        addDashboardTabBtn.addEventListener('click', () => {
            quickAddDashboardModal.style.display = 'flex';
            const nomInput = quickAddDashboardModal.querySelector('input[name="nom"]');
            if (nomInput) nomInput.focus();
        });
        if (closeQuickAddDashboardModal) {
            closeQuickAddDashboardModal.addEventListener('click', () => quickAddDashboardModal.style.display = 'none');
        }
    }

    // 4. Logique de fermeture partagée (clic sur fond)
    window.addEventListener('click', (event) => {
        if (event.target === settingsModal) {
            settingsModal.style.display = 'none';
             if (window.location.pathname.includes('/edit/')) {
                 window.history.pushState({}, '', '/');
             }
        }
        if (event.target === quickAddStep1_ChoiceModal) {
            quickAddStep1_ChoiceModal.style.display = 'none';
        }
        if (event.target === quickAddStep2_ServiceModal) {
            quickAddStep2_ServiceModal.style.display = 'none';
        }
        if (event.target === quickAddDashboardModal) {
            quickAddDashboardModal.style.display = 'none';
        }
    });
}
