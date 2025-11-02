// Fichier: /public/assets/js/widget_renderers.js

// Objet global pour stocker les fonctions de rendu
const WIDGET_RENDERERS = {};

/**
 * Rendu pour Xen Orchestra
 */
WIDGET_RENDERERS['xen_orchestra'] = function(data) {
    // ... (code inchangé)
    if (data.error) throw new Error(data.error);
    return `... (html xen) ...`;
};

/**
 * Rendu pour Glances (MODIFIÉ AVEC CHART.JS)
 */
WIDGET_RENDERERS['glances'] = function(data) {
    if (data.error) throw new Error(data.error);
    
    // HTML avec les conteneurs de graphiques (avec ID unique grâce à data.serviceId)
    const html = `
        <div class="glances-widget">
            <div class="glances-charts-row">
                <div class="chart-container-half">
                    <canvas id="glances-cpu-chart-${data.serviceId}"></canvas>
                    <div class="chart-label">CPU: ${data.cpu_total.toFixed(1)}%</div>
                </div>
                <div class="chart-container-half">
                    <canvas id="glances-mem-chart-${data.serviceId}"></canvas>
                    <div class="chart-label">RAM: ${data.mem_used_percent.toFixed(1)}%</div>
                </div>
            </div>
            <div class="glances-charts-row load-chart">
                <canvas id="glances-load-chart-${data.serviceId}"></canvas>
            </div>
        </div>
        <style>
            .glances-widget { width: 100%; height: 100%; display: flex; flex-direction: column; padding-top: 5px; }
            .glances-charts-row { display: flex; flex-shrink: 0; }
            .glances-charts-row.load-chart { flex-grow: 1; margin-top: 10px; }
            .chart-container-half { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; position: relative; }
            .chart-label { font-size: 0.8rem; color: var(--text-muted-color); margin-top: 5px; }
            canvas { max-height: 100px; } /* Ajuster la hauteur max pour la vue */
        </style>
    `;

    // Le script Chart.js doit être exécuté APRES que le HTML a été injecté dans le DOM.
    // setTimeout(fn, 0) est une astuce pour exécuter le code juste après le cycle de rendu actuel.
    setTimeout(() => {
        if (typeof Chart === 'undefined') {
            console.error("Chart.js n'est pas chargé.");
            return;
        }

        // Récupérer les couleurs du thème CSS
        const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--status-online').trim();
        const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--text-muted-color').trim();

        const configBase = {
             responsive: true,
             maintainAspectRatio: false,
             plugins: { legend: { display: false } },
        };
        
        // --- Graphique CPU (Doughnut) ---
        new Chart(
            document.getElementById(`glances-cpu-chart-${data.serviceId}`),
            {
                type: 'doughnut',
                data: {
                    labels: ['Utilisé', 'Libre'],
                    datasets: [{
                        data: [data.cpu_total, 100 - data.cpu_total],
                        backgroundColor: [primaryColor, 'rgba(128, 128, 128, 0.2)'],
                        borderColor: 'transparent',
                    }]
                },
                options: { ...configBase, cutout: '70%', parsing: false, tooltips: { enabled: false } }
            }
        );

        // --- Graphique RAM (Doughnut) ---
        new Chart(
            document.getElementById(`glances-mem-chart-${data.serviceId}`),
            {
                type: 'doughnut',
                data: {
                    labels: ['Utilisé', 'Libre'],
                    datasets: [{
                        data: [data.mem_used_percent, 100 - data.mem_used_percent],
                        backgroundColor: [primaryColor, 'rgba(128, 128, 128, 0.2)'],
                        borderColor: 'transparent',
                    }]
                },
                options: { ...configBase, cutout: '70%', parsing: false, tooltips: { enabled: false } }
            }
        );
        
        // --- Graphique Load (Bar) ---
        new Chart(
            document.getElementById(`glances-load-chart-${data.serviceId}`),
            {
                type: 'bar',
                data: {
                    labels: ['1min', '5min', '15min'],
                    datasets: [{
                        label: 'Charge (Load)',
                        data: [data.load_1, data.load_5, data.load_15],
                        backgroundColor: [primaryColor, primaryColor, primaryColor],
                        borderColor: primaryColor,
                        borderWidth: 1
                    }]
                },
                options: {
                    ...configBase,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(160, 174, 192, 0.1)' },
                            ticks: { color: mutedColor }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: mutedColor }
                        }
                    }
                }
            }
        );

    }, 0); 

    return html;
};


/**
 * Squelette pour Proxmox
 */
WIDGET_RENDERERS['proxmox'] = function(data) {
    // ... (code inchangé)
    if (data.error) throw new Error(data.error);
    return `... (html proxmox) ...`;
};

/**
 * Squelette pour Portainer
 */
WIDGET_RENDERERS['portainer'] = function(data) {
    // ... (code inchangé)
    if (data.error) throw new Error(data.error);
     return `... (html portainer) ...`;
};

/**
 * (CORRIGÉ) Rendu pour Calendrier M365 (Utilise Day.js)
 */
WIDGET_RENDERERS['m365_calendar'] = function(data) {
    if (data.error) {
        throw new Error(data.error);
    }
    
    // Configuration de Day.js
    if (typeof dayjs !== 'undefined') {
        // --- CORRECTION ---
        // Utiliser les variables globales chargées par les CDN
        dayjs.extend(window.dayjs_plugin_utc);
        dayjs.extend(window.dayjs_plugin_timezone);
        // --- FIN CORRECTION ---
        dayjs.locale('fr'); // Définit la locale en français
    } else {
        console.error("Day.js n'est pas chargé. Utilisation du format brut.");
        // Fallback simple si dayjs n'est pas là
        return `<p class="widget-error">Erreur: Day.js manquant pour le formatage.</p>`;
    }
    
    let html = '<ul class="m365-widget-list">';
    if (!data.events || data.events.length === 0) {
        return '<p class="widget-no-data"><i class="fas fa-check-circle"></i> Aucun événement à venir.</p>';
    }
    
    // Fonction pour formater l'heure (MODIFIÉE AVEC DAY.JS)
    const formatTime = (dateStr) => {
        // Les dates M365 sont en UTC. On les convertit au fuseau local de l'utilisateur.
        return dayjs(dateStr).local().format('HH:mm');
    };

    data.events.forEach(event => {
        const start = formatTime(event.start);
        const end = formatTime(event.end);

        const startTimeDisplay = event.isAllDay 
            ? 'Toute la journée' 
            : `${start} - ${end}`;
            
        html += `
            <li>
                <span class="event-time">${startTimeDisplay}</span>
                <span class="event-subject" title="${event.subject}">${event.subject}</span>
            </li>`;
    });
    
    html += '</ul>';
    
    // Ajoute un peu de style (inchangé)
    const style = `
        <style>
            .m365-widget-list { list-style: none; margin: 0; padding: 0 5px; text-align: left; font-size: 0.9rem; }
            .m365-widget-list li { margin-bottom: 6px; display: flex; flex-direction: column; }
            .m365-widget-list .event-time { font-weight: 600; color: var(--text-muted-color); font-size: 0.85em; }
            .m365-widget-list .event-subject { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .widget-no-data { font-size: 0.95rem; color: var(--text-muted-color); }
        </style>`;
    
    return style + html;
};

/**
 * (NOUVEAU) Rendu pour Stats Mail M365
 */
WIDGET_RENDERERS['m365_mail_stats'] = function(data) {
    if (data.error) {
        throw new Error(data.error);
    }
    
    // data contient { message_count: X, next_link: bool }
    
    let countHtml = data.next_link ? `${data.message_count}+` : data.message_count;
    
    return `
        <style>
            .m365-mail-widget { text-align: center; }
            .m365-mail-widget .count { font-size: 2.5rem; font-weight: 700; color: var(--status-online); }
            .m365-mail-widget .label { font-size: 0.9rem; color: var(--text-muted-color); }
        </style>
        <div class="m365-mail-widget">
            <div class="count">${countHtml}</div>
            <div class="label">Messages (delta)</div>
        </div>`;
};
