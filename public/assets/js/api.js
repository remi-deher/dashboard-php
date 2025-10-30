// Fichier: /public/assets/js/api.js

/**
 * Gestion centralisée des erreurs de fetch
 */
function handleFetchError(response) {
    if (!response.ok) {
        return response.json().then(err => Promise.reject(err));
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

// --- NOUVELLE FONCTION ---
/**
 * Récupère les données d'un widget
 */
function apiGetWidgetData(serviceId) {
    return fetch(`/api/widget/data/${serviceId}`)
        .then(handleFetchError);
}
// -------------------------

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
