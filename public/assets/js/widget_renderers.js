// Fichier: /public/assets/js/widget_renderers.js

// Objet global pour stocker les fonctions de rendu
const WIDGET_RENDERERS = {};

/**
 * Rendu pour Xen Orchestra
 * (Logique déplacée depuis dashboard.js)
 */
WIDGET_RENDERERS['xen_orchestra'] = function(data) {
    // data contient: { running: X, halted: Y, total: Z } ou { error: '...' }
    if (data.error) {
        throw new Error(data.error);
    }
    
    return `
        <ul class="xen-widget">
            <li class="running">
                <span><i class="fas fa-play-circle" style="color:var(--status-online); margin-right: 8px;"></i> En marche</span>
                <span class="value">${data.running}</span>
            </li>
            <li class="halted">
                <span><i class="fas fa-stop-circle" style="color:var(--status-offline); margin-right: 8px;"></i> Arrêtées</span>
                <span class="value">${data.halted}</span>
            </li>
            <li class="total">
                <span><i class="fas fa-server" style="margin-right: 8px;"></i> Total VMs</span>
                <span class="value">${data.total}</span>
            </li>
        </ul>`;
};

/**
 * Rendu pour Glances
 */
WIDGET_RENDERERS['glances'] = function(data) {
    // data contient: { cpu_total: X, mem_used_percent: Y, load_1: Z, ... } ou { error: '...' }
    if (data.error) {
        throw new Error(data.error);
    }

    // Détermine la couleur de la charge CPU
    let cpuColor = 'var(--status-online)';
    if (data.cpu_total > 75) cpuColor = '#f56565'; // offline color
    else if (data.cpu_total > 50) cpuColor = '#ecc94b'; // warning yellow
    
    let memColor = 'var(--status-online)';
    if (data.mem_used_percent > 85) memColor = '#f56565';
    else if (data.mem_used_percent > 70) memColor = '#ecc94b';

    return `
        <style>
            .glances-widget { font-size: 0.9rem; text-align: left; width: 100%; padding: 0 5px; }
            .glances-widget strong { font-size: 1.1rem; padding: 2px 6px; border-radius: 6px; }
            .glances-widget li { margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;}
        </style>
        <ul class="glances-widget">
            <li>
                <span><i class="fas fa-microchip" style="margin-right: 8px;"></i> CPU</span>
                <strong style="color: ${cpuColor}; background-color: ${cpuColor}20;">
                    ${Math.round(data.cpu_total)}%
                </strong>
            </li>
            <li>
                <span><i class="fas fa-memory" style="margin-right: 8px;"></i> RAM</span>
                <strong style="color: ${memColor}; background-color: ${memColor}20;">
                    ${Math.round(data.mem_used_percent)}%
                </strong>
            </li>
            <li>
                <span><i class="fas fa-chart-line" style="margin-right: 8px;"></i> Load (1m)</span>
                <strong>${data.load_1.toFixed(2)}</strong>
            </li>
        </ul>`;
};


/**
 * Squelette pour Proxmox
 */
WIDGET_RENDERERS['proxmox'] = function(data) {
    if (data.error) {
        throw new Error(data.error);
    }
    // À vous de jouer : data contiendra ce que votre ProxmoxService retourne
    // ex: { nodes: [ { name: 'pve1', cpu: 0.5, mem_percent: 60 } ], vms: { running: 5, stopped: 2 } }
    return `
        <ul class="xen-widget">
            <li class="running">
                <span><i class="fas fa-play-circle"></i> VMs</span>
                <span class="value">${data.vms?.running ?? 'N/A'}</span>
            </li>
            <li class="halted">
                <span><i class="fas fa-stop-circle"></i> CTs</span>
                <span class="value">${data.cts?.running ?? 'N/A'}</span>
            </li>
        </ul>`;
};

/**
 * Squelette pour Portainer
 */
WIDGET_RENDERERS['portainer'] = function(data) {
     if (data.error) {
        throw new Error(data.error);
    }
    // ex: { stacks: 5, running: 10, stopped: 2 }
     return `
        <ul class="xen-widget">
            <li class="running">
                <span><i class="fab fa-docker"></i> Running</span>
                <span class="value">${data.running ?? 'N/A'}</span>
            </li>
            <li class="halted">
                <span><i class="fas fa-exclamation-triangle"></i> Stopped</span>
                <span class="value">${data.stopped ?? 'N/A'}</span>
            </li>
        </ul>`;
};
