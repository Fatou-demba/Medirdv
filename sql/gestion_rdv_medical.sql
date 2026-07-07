-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 24 juin 2026 à 13:15
-- Version du serveur : 9.1.0
-- Version de PHP : 8.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_rdv_medical`
--

-- --------------------------------------------------------

--
-- Structure de la table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (

  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Déchargement des données de la table `admins`
--

INSERT INTO `admins` (`id`, `nom`, `email`, `mot_de_passe`, `date_creation`) VALUES
(1, 'Admin Principal', 'admin@medirdv.sn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-05-11 22:51:49');

-- --------------------------------------------------------

--
-- Structure de la table `consultations`
--

DROP TABLE IF EXISTS `consultations`;
CREATE TABLE IF NOT EXISTS `consultations` (

  `id` int NOT NULL AUTO_INCREMENT,
  `id_rendezvous` int NOT NULL,
  `id_patient` int NOT NULL,
  `id_medecin` int NOT NULL,
  `diagnostic` text,
  `traitement` text,
  `ordonnance` text,
  `compte_rendu` text,
  `prochain_rdv` date DEFAULT NULL,
  `date_consultation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_rendezvous` (`id_rendezvous`),
  KEY `id_patient` (`id_patient`),
  KEY `id_medecin` (`id_medecin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- --------------------------------------------------------

--
-- Structure de la table `disponibilites`
--

DROP TABLE IF EXISTS `disponibilites`;
CREATE TABLE IF NOT EXISTS `disponibilites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_medecin` int NOT NULL,
  `jour_semaine` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `duree_rdv` int DEFAULT '30',
  `actif` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `dispo_unique` (`id_medecin`,`jour_semaine`,`heure_debut`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `disponibilites`
--

INSERT INTO `disponibilites` (`id`, `id_medecin`, `jour_semaine`, `heure_debut`, `heure_fin`, `duree_rdv`, `actif`) VALUES
(1, 1, 'lundi', '09:00:00', '12:00:00', 30, 1),
(2, 1, 'lundi', '14:00:00', '17:00:00', 30, 1),
(3, 1, 'mercredi', '09:00:00', '12:00:00', 30, 1),
(4, 1, 'vendredi', '09:00:00', '12:00:00', 30, 1);

-- --------------------------------------------------------

--
-- Structure de la table `dossiers`
--

DROP TABLE IF EXISTS `dossiers`;
CREATE TABLE IF NOT EXISTS `dossiers` (

  `id` int NOT NULL AUTO_INCREMENT,
  `id_patient` int NOT NULL,
  `antecedents` text,
  `allergies` text,
  `maladies_chroniques` text,
  `chirurgies` text,
  `traitements_en_cours` text,
  `notes_admin` text,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_patient` (`id_patient`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Déchargement des données de la table `dossiers`
--

INSERT INTO `dossiers` (`id`, `id_patient`, `antecedents`, `allergies`, `maladies_chroniques`, `chirurgies`, `traitements_en_cours`, `notes_admin`, `date_creation`, `date_modification`) VALUES
(1, 2, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 21:59:10', '2026-06-13 21:59:10');

-- --------------------------------------------------------

--
-- Structure de la table `journal_acces`
--

DROP TABLE IF EXISTS `journal_acces`;
CREATE TABLE IF NOT EXISTS `journal_acces` (

  `id` int NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `type_user` enum('patient','medecin','admin') NOT NULL,
  `action` varchar(200) NOT NULL,
  `id_dossier` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `date_action` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Déchargement des données de la table `journal_acces`
--

INSERT INTO `journal_acces` (`id`, `id_user`, `type_user`, `action`, `id_dossier`, `ip_address`, `date_action`) VALUES
(1, 2, 'patient', 'connexion', NULL, '::1', '2026-06-14 23:26:23'),
(2, 2, 'patient', 'consultation_detail_rdv_#1', NULL, '::1', '2026-06-14 23:30:55'),
(3, 2, 'patient', 'connexion', NULL, '::1', '2026-06-21 23:53:32'),
(4, 2, 'patient', 'connexion', NULL, '::1', '2026-06-24 13:00:54'),
(5, 2, 'patient', 'connexion', NULL, '::1', '2026-06-24 13:08:28'),
(6, 2, 'patient', 'consultation_dossier', 1, '::1', '2026-06-24 13:09:01');

-- --------------------------------------------------------

--
-- Structure de la table `medecins`
--

DROP TABLE IF EXISTS `medecins`;
CREATE TABLE IF NOT EXISTS `medecins` (

  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `specialite` varchar(100) NOT NULL,
  `num_ordre` varchar(50) NOT NULL,
  `cabinet` varchar(200) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `biographie` text,
  `statut` enum('en_attente','valide','refuse','suspendu') DEFAULT 'en_attente',
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `num_ordre` (`num_ordre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Déchargement des données de la table `medecins`
--

INSERT INTO `medecins` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `specialite`, `num_ordre`, `cabinet`, `ville`, `telephone`, `photo`, `biographie`, `statut`, `date_inscription`) VALUES
(1, 'Ba', 'Mamadou', 'dr.ba@test.sn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cardiologie', 'ORD-2024-001', 'Clinique du Lac', 'Dakar', NULL, NULL, NULL, 'valide', '2026-05-11 22:51:50'),
(2, 'Faye', 'Ibrahima', 'ibsibzo97@gmail.com', '$2y$12$/s.2GjNoG2ODMtIMyM4jtetPy/NZsH.nHK/WO4X09RGccNdNtKi2e', 'Urologie', 'ORD-645-HG4', 'parcelle Assénie U14', 'Dakar', '703362964', 'dr_6a2b4248835ea.jpeg', 'cc', 'en_attente', '2026-06-11 23:18:32');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (

  `id` int NOT NULL AUTO_INCREMENT,
  `id_destinataire` int NOT NULL,
  `type_user` enum('patient','medecin') NOT NULL,
  `type_notif` enum('rdv_confirme','rdv_annule','rappel','nouveau_rdv','message') NOT NULL,
  `message` text NOT NULL,
  `id_rdv` int DEFAULT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `date_envoi` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_rdv` (`id_rdv`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `id_destinataire`, `type_user`, `type_notif`, `message`, `id_rdv`, `lu`, `date_envoi`) VALUES
(1, 1, '', 'nouveau_rdv', 'Nouvelle demande d\'inscription médecin : Dr. Ibrahima Faye (Urologie)', NULL, 0, '2026-06-11 23:18:32'),
(2, 2, 'patient', 'nouveau_rdv', 'Votre demande de RDV avec Dr. Mamadou Ba le 2026-06-24 à 10:00 a été envoyée.', 1, 0, '2026-06-13 22:25:01'),
(3, 1, 'medecin', 'nouveau_rdv', 'Nouvelle demande de RDV de Ibrahima Faye le 2026-06-24 à 10:00.', 1, 0, '2026-06-13 22:25:01');

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `type_user` enum('patient','medecin','admin') NOT NULL,
  `token` varchar(64) NOT NULL,
  `expire_at` datetime NOT NULL,
  `utilise` tinyint(1) DEFAULT '0',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `type_user`, `token`, `expire_at`, `utilise`, `date_creation`) VALUES
(1, 'ibrahima.faye42@unchk.edu.sn', 'patient', '4bf4136feb9fbe56a849b397104667b7443c74c8a875c452cd4fdd812ea74d92', '2026-06-22 00:52:35', 1, '2026-06-21 23:52:35');

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

DROP TABLE IF EXISTS `patients`;
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `date_naissance` date NOT NULL,
  `groupe_sanguin` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `taille` decimal(5,2) DEFAULT NULL,
  `poids` decimal(5,2) DEFAULT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `statut` enum('actif','suspendu','supprime') DEFAULT 'actif',
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `nom`, `prenom`, `email`, `telephone`, `date_naissance`, `groupe_sanguin`, `taille`, `poids`, `mot_de_passe`, `photo`, `statut`, `date_inscription`) VALUES
(1, 'Diallo', 'Fatou', 'fatou.diallo@test.sn', '771234567', '1995-03-15', NULL, NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'actif', '2026-05-11 22:51:50'),
(2, 'Faye', 'Ibrahima', 'ibrahima.faye42@unchk.edu.sn', '773588475', '2000-01-01', 'O+', NULL, NULL, '$2y$12$naXboGyg973KKgJs9f6dFefgY7FjXSDXykKTFCHJign5/4lq51ToO', 'patient_2_1781388367.jpeg', 'actif', '2026-06-13 21:59:10');

-- --------------------------------------------------------

--
-- Structure de la table `rendezvous`
--

DROP TABLE IF EXISTS `rendezvous`;
CREATE TABLE IF NOT EXISTS `rendezvous` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_patient` int NOT NULL,
  `id_medecin` int NOT NULL,
  `date_rdv` date NOT NULL,
  `heure_rdv` time NOT NULL,
  `motif` text NOT NULL,
  `statut` enum('en_attente','confirme','refuse','annule','termine') DEFAULT 'en_attente',
  `message_medecin` text,
  `motif_annulation` text,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `anti_doublon` (`id_medecin`,`date_rdv`,`heure_rdv`),
  KEY `id_patient` (`id_patient`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `rendezvous`
--

INSERT INTO `rendezvous` (`id`, `id_patient`, `id_medecin`, `date_rdv`, `heure_rdv`, `motif`, `statut`, `message_medecin`, `motif_annulation`, `date_creation`) VALUES
(1, 2, 1, '2026-06-24', '10:00:00', 'fggdgygcdrtdgfrdhjhgf-ttrdd', 'en_attente', NULL, NULL, '2026-06-13 22:25:01');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`id_rendezvous`) REFERENCES `rendezvous` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`id_patient`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultations_ibfk_3` FOREIGN KEY (`id_medecin`) REFERENCES `medecins` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `disponibilites`
--
ALTER TABLE `disponibilites`
  ADD CONSTRAINT `disponibilites_ibfk_1` FOREIGN KEY (`id_medecin`) REFERENCES `medecins` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `dossiers`
--
ALTER TABLE `dossiers`
  ADD CONSTRAINT `dossiers_ibfk_1` FOREIGN KEY (`id_patient`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`id_rdv`) REFERENCES `rendezvous` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `rendezvous`
--
ALTER TABLE `rendezvous`
  ADD CONSTRAINT `rendezvous_ibfk_1` FOREIGN KEY (`id_patient`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rendezvous_ibfk_2` FOREIGN KEY (`id_medecin`) REFERENCES `medecins` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
