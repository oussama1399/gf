CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(50),
    profile_picture VARCHAR(255),
    created_at DATETIME,
    fonction VARCHAR(255)
);

CREATE TABLE tb_agents (
    ppr VARCHAR(50) PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    id_fonction INT,
    CD_ETAB VARCHAR(50),
    cin VARCHAR(255),
    email VARCHAR(255) UNIQUE
);

CREATE TABLE z_etab (
    CD_ETAB VARCHAR(50) PRIMARY KEY,
    NOM_ETABL VARCHAR(255) NOT NULL,
    LA_VILLE VARCHAR(255),
    typeEtab VARCHAR(255),
    Actif TINYINT(1) DEFAULT 1,
    DateModification DATETIME
);

CREATE TABLE all_flotte (
    nd VARCHAR(50) PRIMARY KEY,
    id_statut INT,
    id_operateur INT,
    id_type_abonnement INT,
    CD_PROV VARCHAR(50),
    CD_ETAB VARCHAR(50)
);

CREATE TABLE affectation_flotte (
    nd VARCHAR(50) NOT NULL,
    ppr VARCHAR(50) NOT NULL,
    date_affectation DATETIME,
    id_fonction INT,
    id_statut INT,
    PRIMARY KEY (nd, ppr)
);

CREATE TABLE demandes_carte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ppr VARCHAR(50),
    motif TEXT,
    type_demande ENUM('changement', 'nouvelle', 'remplacement'),
    statut ENUM('en_attente', 'approuve', 'rejete'),
    commentaire_admin TEXT,
    date_demande DATETIME,
    traite_le DATETIME,
    traite_par INT
);

CREATE TABLE historique_flotte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ppr VARCHAR(50),
    nom VARCHAR(255),
    prenom VARCHAR(255),
    lib_etab VARCHAR(255),
    lib_fonction VARCHAR(255),
    date_affectation DATETIME,
    statut_nd VARCHAR(255),
    CD_PROV VARCHAR(50),
    date_session DATETIME,
    type_action VARCHAR(255)
);

CREATE TABLE r_statuts (
    id_statut INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(255) NOT NULL
);

CREATE TABLE r_fonction (
    id_fonction INT AUTO_INCREMENT PRIMARY KEY,
    libelle_fr VARCHAR(255) NOT NULL
);

CREATE TABLE r_operateurs (
    id_operateur INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(255) NOT NULL
);

CREATE TABLE r_type_abonnement (
    id_type_abonnement INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(255) NOT NULL
);

CREATE TABLE z_direction (
    CD_PROV VARCHAR(50) PRIMARY KEY,
    libelle VARCHAR(255)
);

-- Initial data for lookup tables
INSERT INTO r_statuts (libelle) VALUES ('actif'), ('inactif'), ('suspendu'), ('resilie'), ('perdu');
INSERT INTO r_fonction (libelle_fr) VALUES ('Fonction A'), ('Fonction B'), ('Fonction C');
INSERT INTO r_operateurs (libelle) VALUES ('IAM'), ('Orange'), ('Inwi');
INSERT INTO r_type_abonnement (libelle) VALUES ('Abonnement A'), ('Abonnement B');
INSERT INTO z_direction (CD_PROV, libelle) VALUES ('PROV1', 'Province 1'), ('PROV2', 'Province 2');
