// Fichier: /public/assets/js/api.js (Corrigé)

/**
 * Gestion centralisée des erreurs de fetch
 * @param {Response} response 
 * @returns 
 */
function handleFetchError(response) {
    if (!response.ok) {
        // CORRECTION : Nous analysons la réponse JSON de l'erreur,
        // puis nous la rejetons en tant qu'objet Error standard
        // afin que le .catch() de notre widget puisse toujours lire "error.message".
        return response.json().then(err_data => {
            // Tente de trouver le message d'erreur dans la réponse JSON
            const errorMsg = err_data.error || err_data.message || response.statusText || 'Erreur inconnue';
            throw new Error(errorMsg);
        }).catch(parseError => {
            // Si le JSON de l'erreur est invalide, on rejette avec le statut
            throw new Error(response.statusText || 'Erreur HTTP ' + response.status);
        });
    }
    return response.json();
}

/**
 * Récupère le statut d'une URL
 */
function apiCheckStatus(serviceUrl) {
    return fetch(`/api/status/check?url=${encodeURIComponent(serviceUrl)}`)
        .then(handleFetchError);
}

/**
 * Récupère la liste des dashboards
 */
function apiGetDashboards() {
    return fetch('/api/dashboards')
        .then(handleFetchError);
}

/**
 * Récupère les services pour un dashboard donné
 */
function apiGetServices(dashboardId) {
    return fetch(`/api/services?dashboard_id=${dashboardId}`)
        .then(handleFetchError);
}

/**
 * Récupère les données d'un widget
 */
function apiGetWidgetData(serviceId) {
    return fetch(`/api/widget/data/${serviceId}`)
        .then(handleFetchError);
}

/**
 * Sauvegarde l'ordre des services d'un dashboard
 */
function apiSaveServiceLayout(dashboardId, orderedIds) {
    return fetch(`/api/services/layout/save`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ dashboardId: parseInt(dashboardId, 10), ids: orderedIds }),
        keepalive: true
    }).then(handleFetchError);
}

/**
 * Sauvegarde l'ordre des onglets (dashboards)
 */
function apiSaveDashboardLayout(newOrderIds) {
    return fetch('/api/dashboards/layout/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(newOrderIds),
        keepalive: true
    }).then(handleFetchError);
}

/**
 * Sauvegarde la nouvelle taille d'un service
 */
function apiSaveServiceSize(serviceId, sizeClass) {
    return fetch(`/api/service/resize/${serviceId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sizeClass: sizeClass }),
        keepalive: true
    }).then(handleFetchError);
}

/**
 * Déplace un service vers un nouveau dashboard
 */
function apiMoveService(serviceId, newDashboardId) {
     return fetch(`/api/service/move/${serviceId}/${newDashboardId}`, { 
         method: 'POST' 
    }).then(handleFetchError);
}
