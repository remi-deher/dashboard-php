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
 * Rendu pour Glances
 */
WIDGET_RENDERERS['glances'] = function(data) {
    // ... (code inchangé)
    if (data.error) throw new Error(data.error);
    return `... (html glances) ...`;
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
 * (NOUVEAU) Rendu pour Calendrier M365
 */
WIDGET_RENDERERS['m365_calendar'] = function(data) {
    if (data.error) {
        throw new Error(data.error);
    }
    
    let html = '<ul class="m365-widget-list">';
    if (!data.events || data.events.length === 0) {
        return '<p class="widget-no-data"><i class="fas fa-check-circle"></i> Aucun événement à venir.</p>';
    }
    
    // Fonction pour formater l'heure
    const formatTime = (dateStr) => {
        const date = new Date(dateStr);
        return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    };

    data.events.forEach(event => {
        const start = formatTime(event.start);
        const end = formatTime(event.end);
        html += `
            <li>
                <span class="event-time">${event.isAllDay ? 'Toute la journée' : start + ' - ' + end}</span>
                <span class="event-subject" title="${event.subject}">${event.subject}</span>
            </li>`;
    });
    
    html += '</ul>';
    
    // Ajoute un peu de style
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
