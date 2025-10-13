CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    icone VARCHAR(255),
    description TEXT,
    groupe VARCHAR(255) DEFAULT 'Général',
    ordre_affichage INT DEFAULT 0
);

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
