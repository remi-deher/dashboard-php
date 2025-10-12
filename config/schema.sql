CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    icone VARCHAR(255),
    description TEXT,
    groupe VARCHAR(255) DEFAULT 'Général',
    ordre_affichage INT DEFAULT 0
);
