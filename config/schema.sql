CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    icone VARCHAR(255),
    description TEXT,
    groupe VARCHAR(255) DEFAULT 'Général',
    ordre_affichage INT DEFAULT 0
);

ALTER TABLE services 
ADD COLUMN widget_type VARCHAR(50) DEFAULT 'link';

CREATE TABLE dashboards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    icone VARCHAR(255) DEFAULT 'fas fa-th-large',
    ordre_affichage INT DEFAULT 0
);

ALTER TABLE services ADD COLUMN dashboard_id INT;

-- Créez un premier dashboard par défaut
INSERT INTO dashboards (nom) VALUES ('Principal');

-- Associez tous vos services existants à ce nouveau dashboard (en supposant que son ID est 1)
UPDATE services SET dashboard_id = 1;

-- Rendez la colonne non-nulle pour le futur
ALTER TABLE services MODIFY COLUMN dashboard_id INT NOT NULL;

CREATE TABLE settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
);

INSERT INTO settings (setting_key, setting_value) VALUES ('background', 'linear-gradient(160deg, #161b22 0%, #0d1117 100%)');

-- Ajoute une colonne pour les URL d'icônes personnalisées dans la table des services
ALTER TABLE services ADD COLUMN icone_url VARCHAR(255) NULL DEFAULT NULL;

-- Ajoute une colonne pour les URL d'icônes personnalisées dans la table des dashboards
ALTER TABLE dashboards ADD COLUMN icone_url VARCHAR(255) NULL DEFAULT NULL;

-- On renomme la clé 'background' en 'background_color' pour plus de clarté
UPDATE settings SET setting_key = 'background_color' WHERE setting_key = 'background';

ALTER TABLE services
ADD COLUMN card_size VARCHAR(20) DEFAULT 'medium',
ADD COLUMN card_color VARCHAR(30) NULL DEFAULT NULL;
