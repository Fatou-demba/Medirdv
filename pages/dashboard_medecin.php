<?php
// ================================================================
// dashboard_medecin.php — Tableau de bord Médecin Premium
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn() || getRole() !== 'medecin') {
    redirect('login.php');
}

$medecin_id = $_SESSION['user_id'];

// ================================================================
// DONNÉES DU MÉDECIN
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id AND statut = 'valide' LIMIT 1");
$stmt->execute([':id' => $medecin_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$medecin) { session_destroy(); redirect('../index.php'); }

// ================================================================
// STATISTIQUES AVANCÉES
// ================================================================

// Statistiques générales des rendez-vous
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN statut IN ('confirme','en_attente') AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS a_venir,
        SUM(CASE WHEN statut = 'en_attente' AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut = 'confirme' AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS confirmes,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termines,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) AS annules,
        SUM(CASE WHEN statut = 'refuse' THEN 1 ELSE 0 END) AS refuses,
        SUM(CASE WHEN statut = 'termine' AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS dernier_mois
    FROM rendezvous WHERE id_medecin = :id
");
$stmt->execute([':id' => $medecin_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistiques des consultations par mois (12 derniers mois)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date_rdv, '%Y-%m') AS mois,
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termines,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) AS annules,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente
    FROM rendezvous 
    WHERE id_medecin = :id 
        AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_rdv, '%Y-%m')
    ORDER BY mois ASC
");
$stmt->execute([':id' => $medecin_id]);
$stats_mensuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques des patients uniques
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT id_patient) AS total_patients,
        COUNT(DISTINCT CASE WHEN date_rdv >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN id_patient END) AS nouveau_mois
    FROM rendezvous 
    WHERE id_medecin = :id AND statut IN ('termine', 'confirme')
");
$stmt->execute([':id' => $medecin_id]);
$patient_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Top 5 patients les plus fidèles
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.nom,
        p.prenom,
        p.telephone,
        COUNT(r.id) AS nb_rdv,
        MAX(r.date_rdv) AS dernier_rdv
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    WHERE r.id_medecin = :id AND r.statut = 'termine'
    GROUP BY p.id, p.nom, p.prenom, p.telephone
    ORDER BY nb_rdv DESC
    LIMIT 5
");
$stmt->execute([':id' => $medecin_id]);
$top_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Répartition par jour de la semaine
$stmt = $pdo->prepare("
    SELECT 
        DAYOFWEEK(date_rdv) AS jour_num,
        DAYNAME(date_rdv) AS jour,
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termines
    FROM rendezvous 
    WHERE id_medecin = :id AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY DAYOFWEEK(date_rdv), DAYNAME(date_rdv)
    ORDER BY jour_num
");
$stmt->execute([':id' => $medecin_id]);
$stats_jours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Taux d'annulation
$stmt = $pdo->prepare("
    SELECT 
        (SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS taux_annulation
    FROM rendezvous 
    WHERE id_medecin = :id AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
");
$stmt->execute([':id' => $medecin_id]);
$taux_annulation = $stmt->fetch(PDO::FETCH_ASSOC)['taux_annulation'] ?? 0;

// ================================================================
// PROCHAINS RDV (aujourd'hui et à venir)
// ================================================================
$stmt = $pdo->prepare("
    SELECT r.*, p.nom AS p_nom, p.prenom AS p_prenom, p.telephone,
           p.groupe_sanguin, p.date_naissance,
           d.allergies, d.maladies_chroniques
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    LEFT JOIN dossiers d ON d.id_patient = p.id
    WHERE r.id_medecin = :id
      AND r.statut IN ('confirme','en_attente')
      AND r.date_rdv >= CURDATE()
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    LIMIT 6
");
$stmt->execute([':id' => $medecin_id]);
$prochains_rdv = $stmt->fetchAll(PDO::FETCH_ASSOC);

// RDV du jour
$stmt = $pdo->prepare("
    SELECT r.*, p.nom AS p_nom, p.prenom AS p_prenom, p.telephone,
           p.groupe_sanguin, d.allergies
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    LEFT JOIN dossiers d ON d.id_patient = p.id
    WHERE r.id_medecin = :id
      AND r.date_rdv = CURDATE()
      AND r.statut IN ('confirme','en_attente')
    ORDER BY r.heure_rdv ASC
");
$stmt->execute([':id' => $medecin_id]);
$rdv_aujourdhui = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// DERNIÈRE CONSULTATION
// ================================================================
$stmt = $pdo->prepare("
    SELECT r.*, p.nom AS p_nom, p.prenom AS p_prenom, 
           c.diagnostic, c.traitement, c.date_consultation
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    LEFT JOIN consultations c ON c.id_rendezvous = r.id
    WHERE r.id_medecin = :id AND r.statut = 'termine'
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 1
");
$stmt->execute([':id' => $medecin_id]);
$derniere_consult = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// NOTIFICATIONS
// ================================================================
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE id_destinataire = :id AND type_user = 'medecin' AND lu = 0
    ORDER BY date_envoi DESC
    LIMIT 5
");
$stmt->execute([':id' => $medecin_id]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($notifs);

// ================================================================
// DISPONIBILITÉS
// ================================================================
$stmt = $pdo->prepare("
    SELECT * FROM disponibilites 
    WHERE id_medecin = :id AND actif = 1
    ORDER BY FIELD(jour_semaine, 'lundi','mardi','mercredi','jeudi','vendredi','samedi')
");
$stmt->execute([':id' => $medecin_id]);
$disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// STATUT EN LIGNE (simulé)
// ================================================================
$est_en_ligne = true;

// ================================================================
// HELPERS
// ================================================================
$mois_fr = ['January'=>'Janv','February'=>'Févr','March'=>'Mars','April'=>'Avr',
            'May'=>'Mai','June'=>'Juin','July'=>'Juil','August'=>'Août',
            'September'=>'Sept','October'=>'Oct','November'=>'Nov','December'=>'Déc'];
$jours_fr = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mer',
             'Thursday'=>'Jeu','Friday'=>'Ven','Saturday'=>'Sam','Sunday'=>'Dim'];
$jours_fr_complet = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
                     'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];

function fmtDate($d, $j, $m) {
    if(!$d) return '—';
    $dt = new DateTime($d);
    return ($j[$dt->format('l')]??'').' '.$dt->format('j').' '.($m[$dt->format('F')]??'').' '.$dt->format('Y');
}

function timeAgo($date) {
    $diff = (new DateTime())->diff(new DateTime($date));
    if ($diff->days > 7) return $diff->days.'j';
    if ($diff->days > 0) return $diff->days.'j';
    if ($diff->h > 0) return $diff->h.'h';
    if ($diff->i > 0) return $diff->i.'min';
    return 'à l\'instant';
}

function getAge($dateNaissance) {
    if(!$dateNaissance) return '?';
    $now = new DateTime();
    $birth = new DateTime($dateNaissance);
    $age = $now->diff($birth);
    return $age->y.' ans';
}

function getCouleurSpecialite($s) {
    $couleurs = [
        'Cardiologie' => ['color' => '#0077B6', 'bg' => '#EFF6FF'],
        'Pédiatrie' => ['color' => '#7209B7', 'bg' => '#FDF4FF'],
        'Dermatologie' => ['color' => '#F77F00', 'bg' => '#FFF7ED'],
        'Gynécologie' => ['color' => '#006D77', 'bg' => '#F0FDF4'],
        'Neurologie' => ['color' => '#3A0CA3', 'bg' => '#EEF0FF'],
        'Ophtalmologie' => ['color' => '#0D9488', 'bg' => '#F0FDFA'],
        'ORL' => ['color' => '#7C3AED', 'bg' => '#F5F3FF'],
        'Urologie' => ['color' => '#2563EB', 'bg' => '#EFF6FF'],
        'Psychiatrie' => ['color' => '#BE185D', 'bg' => '#FDF2F8'],
        'Dentiste' => ['color' => '#059669', 'bg' => '#ECFDF5'],
    ];
    return $couleurs[$s] ?? ['color' => '#64748B', 'bg' => '#F1F5F9'];
}

$heure_actuelle = (int)date('H');
if ($heure_actuelle < 12) $salutation = 'Bonjour';
elseif ($heure_actuelle < 18) $salutation = 'Bon après-midi';
else $salutation = 'Bonsoir';

$initiales = strtoupper(mb_substr($medecin['prenom'],0,1).mb_substr($medecin['nom'],0,1));
$couleur_spec = getCouleurSpecialite($medecin['specialite']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Tableau de bord Médecin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ============================================================
           VARIABLES & RESET
        ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --primary: #0077B6;
            --primary-dark: #023E8A;
            --primary-light: #0096C7;
            --green: #2DC653;
            --green-dark: #25B347;
            --orange: #F77F00;
            --red: #D62828;
            --purple: #7209B7;
            --dark: #03045E;
            --dark-2: #0F172A;
            --gray: #64748B;
            --gray-light: #94A3B8;
            --gray-bg: #F1F5F9;
            --white: #FFFFFF;
            --shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 260px;
            --header-height: 68px;
        }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #F8FAFF;
            color: #1E293B;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ============================================================
           SCROLLBAR
        ============================================================ */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #F1F5F9; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

        /* ============================================================
           SIDEBAR
        ============================================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--dark);
            color: #fff;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 4px 0 20px rgba(3,4,94,0.2);
        }
        .sidebar-brand {
            padding: 1.5rem 1.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }
        .sidebar-brand .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--green);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 900;
            color: #fff;
            flex-shrink: 0;
        }
        .sidebar-brand .brand-text {
            font-weight: 800;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
        }
        .sidebar-brand .brand-text span {
            color: var(--green);
        }
        .sidebar-brand .brand-sub {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.5);
            font-weight: 400;
            letter-spacing: 0.3px;
            margin-top: -2px;
        }

        .sidebar-user {
            padding: 1.2rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }
        .sidebar-user .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.15);
            position: relative;
        }
        .sidebar-user .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sidebar-user .user-avatar .online-dot {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: var(--green);
            border-radius: 50%;
            border: 2px solid var(--dark);
        }
        .sidebar-user .user-info {
            flex: 1;
            min-width: 0;
        }
        .sidebar-user .user-info .name {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user .user-info .role {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.5);
            font-weight: 400;
        }
        .sidebar-user .user-info .specialite {
            font-size: 0.65rem;
            color: var(--green);
            font-weight: 500;
        }

        .sidebar-nav {
            flex: 1;
            padding: 0.8rem 1rem 1.5rem;
            overflow-y: auto;
        }
        .sidebar-nav .nav-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: rgba(255,255,255,0.3);
            padding: 0.8rem 0.8rem 0.5rem;
            font-weight: 600;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0.8rem;
            border-radius: 10px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            margin-bottom: 2px;
            position: relative;
        }
        .sidebar-nav a i {
            width: 20px;
            font-size: 1rem;
            text-align: center;
            flex-shrink: 0;
        }
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
        .sidebar-nav a.active {
            background: rgba(45,198,83,0.15);
            color: var(--green);
        }
        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--green);
            border-radius: 0 4px 4px 0;
        }
        .sidebar-nav a .badge {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.15rem 0.55rem;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            font-size: 0.8rem;
            padding: 0.4rem 0;
            transition: var(--transition);
        }
        .sidebar-footer a:hover {
            color: rgba(255,255,255,0.8);
        }
        .sidebar-footer a i {
            width: 20px;
            text-align: center;
        }

        /* ============================================================
           MAIN CONTENT
        ============================================================ */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ============================================================
           HEADER
        ============================================================ */
        .top-header {
            height: var(--header-height);
            background: var(--white);
            border-bottom: 1px solid #E2E8F0;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            flex-shrink: 0;
        }
        .top-header .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .top-header .header-left .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--dark);
            cursor: pointer;
            padding: 0.3rem;
            transition: var(--transition);
        }
        .top-header .header-left .hamburger:hover {
            color: var(--primary);
        }
        .top-header .header-left .page-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark);
        }
        .top-header .header-left .page-title span {
            color: var(--gray-light);
            font-weight: 400;
        }
        .top-header .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .top-header .header-right .notif-btn {
            position: relative;
            color: var(--gray);
            text-decoration: none;
            font-size: 1.2rem;
            transition: var(--transition);
            padding: 0.3rem;
        }
        .top-header .header-right .notif-btn:hover {
            color: var(--dark);
        }
        .top-header .header-right .notif-btn .notif-dot {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--red);
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--white);
        }
        .top-header .header-right .header-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
            overflow: hidden;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
        }
        .top-header .header-right .header-avatar:hover {
            transform: scale(1.05);
        }
        .top-header .header-right .header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .top-header .header-right .header-avatar .mini-dot {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background: var(--green);
            border-radius: 50%;
            border: 2px solid var(--white);
        }
        .top-header .header-right .logout-mobile {
            display: none;
            color: var(--gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
        }
        .top-header .header-right .logout-mobile:hover {
            color: var(--red);
            background: #FEF2F2;
        }

        /* ============================================================
           PAGE CONTENT
        ============================================================ */
        .page-content {
            flex: 1;
            padding: 1.5rem 2rem 2rem;
        }

        /* ============================================================
           GREETING BANNER
        ============================================================ */
        .greeting-banner {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary-dark) 60%, var(--primary-light) 100%);
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }
        .greeting-banner::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(45,198,83,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .greeting-banner .greeting-text h2 {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        .greeting-banner .greeting-text h2 .spec-tag {
            font-size: 0.7rem;
            background: rgba(45,198,83,0.2);
            color: var(--green);
            padding: 0.15rem 0.7rem;
            border-radius: 20px;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        .greeting-banner .greeting-text p {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            margin-top: 0.2rem;
            position: relative;
            z-index: 1;
        }
        .greeting-banner .greeting-text p strong {
            color: var(--green);
            font-weight: 600;
        }
        .greeting-banner .greeting-actions {
            display: flex;
            gap: 0.7rem;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }
        .greeting-banner .greeting-actions .btn {
            padding: 0.55rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }
        .greeting-banner .greeting-actions .btn-primary {
            background: var(--green);
            color: #fff;
            box-shadow: 0 4px 12px rgba(45,198,83,0.3);
        }
        .greeting-banner .greeting-actions .btn-primary:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45,198,83,0.4);
        }
        .greeting-banner .greeting-actions .btn-outline {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1.5px solid rgba(255,255,255,0.2);
        }
        .greeting-banner .greeting-actions .btn-outline:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        /* ============================================================
           STATS GRID
        ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stats-grid .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1rem 1.2rem;
            border: 1px solid #E2E8F0;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stats-grid .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .stats-grid .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .stats-grid .stat-card .stat-info .stat-number {
            font-weight: 800;
            font-size: 1.3rem;
            line-height: 1.2;
        }
        .stats-grid .stat-card .stat-info .stat-label {
            font-size: 0.7rem;
            color: var(--gray);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stats-grid .stat-card .stat-info .stat-trend {
            font-size: 0.6rem;
            font-weight: 600;
            margin-left: 0.3rem;
        }
        .stats-grid .stat-card .stat-info .stat-trend.up {
            color: var(--green);
        }
        .stats-grid .stat-card .stat-info .stat-trend.down {
            color: var(--red);
        }

        /* ============================================================
           CHARTS ROW
        ============================================================ */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .chart-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            border: 1px solid #E2E8F0;
            box-shadow: var(--shadow);
        }
        .chart-card .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .chart-card .chart-header h3 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .chart-card .chart-header .chart-badge {
            font-size: 0.7rem;
            color: var(--gray);
            background: var(--gray-bg);
            padding: 0.15rem 0.6rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .chart-card .chart-wrapper {
            position: relative;
            height: 220px;
        }
        .chart-card .chart-wrapper canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* ============================================================
           TWO COLUMN LAYOUT
        ============================================================ */
        .two-col {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .three-col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* ============================================================
           CARDS
        ============================================================ */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            border: 1px solid #E2E8F0;
            box-shadow: var(--shadow);
        }
        .card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid #F1F5F9;
        }
        .card .card-header h3 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card .card-header .see-all {
            font-size: 0.75rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        .card .card-header .see-all:hover {
            color: var(--primary-dark);
        }

        /* ============================================================
           RDV ITEMS
        ============================================================ */
        .rdv-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.7rem 0;
            border-bottom: 1px solid #F8FAFC;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .rdv-item:last-child {
            border-bottom: none;
        }
        .rdv-item:hover {
            padding-left: 0.5rem;
            background: #FAFBFF;
            border-radius: 6px;
        }
        .rdv-item .rdv-time {
            flex-shrink: 0;
            text-align: center;
            min-width: 50px;
        }
        .rdv-item .rdv-time .day {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--dark);
        }
        .rdv-item .rdv-time .date {
            font-size: 0.6rem;
            color: var(--gray);
            font-weight: 500;
        }
        .rdv-item .rdv-time .hour {
            font-size: 0.65rem;
            color: var(--gray);
            font-weight: 600;
            background: var(--gray-bg);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
        }
        .rdv-item .rdv-info {
            flex: 1;
            min-width: 0;
        }
        .rdv-item .rdv-info .rdv-patient {
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .rdv-item .rdv-info .rdv-patient .age-tag {
            font-size: 0.6rem;
            font-weight: 500;
            color: var(--gray);
            background: var(--gray-bg);
            padding: 0.05rem 0.4rem;
            border-radius: 10px;
        }
        .rdv-item .rdv-info .rdv-motif {
            font-size: 0.7rem;
            color: var(--gray-light);
            margin-top: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .rdv-item .rdv-info .rdv-allergies {
            font-size: 0.6rem;
            color: var(--red);
            font-weight: 500;
        }
        .rdv-item .rdv-status {
            flex-shrink: 0;
        }
        .rdv-item .rdv-status .badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .rdv-item .rdv-status .badge.confirme {
            background: #F0FDF4;
            color: #15803D;
            border: 1px solid #BBF7D0;
        }
        .rdv-item .rdv-status .badge.en_attente {
            background: #FFF7ED;
            color: #C2410C;
            border: 1px solid #FED7AA;
        }
        .rdv-item .rdv-status .badge.termine {
            background: #EFF6FF;
            color: #1D4ED8;
            border: 1px solid #BFDBFE;
        }
        .rdv-item .rdv-status .badge.annule {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        .rdv-empty {
            text-align: center;
            padding: 2rem 0.5rem;
            color: var(--gray);
        }
        .rdv-empty .empty-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }
        .rdv-empty p {
            font-size: 0.85rem;
        }

        /* ============================================================
           TODAY'S RDV
        ============================================================ */
        .today-rdv {
            background: linear-gradient(135deg, #F0FDF4 0%, #ECFDF5 100%);
            border-radius: 10px;
            padding: 0.7rem 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid var(--green);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .today-rdv .today-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .today-rdv .today-info .today-time {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--dark);
        }
        .today-rdv .today-info .today-patient {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--dark);
        }
        .today-rdv .today-info .today-detail {
            font-size: 0.7rem;
            color: var(--gray);
        }
        .today-rdv .today-actions {
            display: flex;
            gap: 0.4rem;
        }
        .today-rdv .today-actions .btn-sm {
            padding: 0.25rem 0.7rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .today-rdv .today-actions .btn-sm-start {
            background: var(--primary);
            color: #fff;
        }
        .today-rdv .today-actions .btn-sm-start:hover {
            background: var(--primary-dark);
        }
        .today-rdv .today-actions .btn-sm-view {
            background: var(--gray-bg);
            color: var(--gray);
        }
        .today-rdv .today-actions .btn-sm-view:hover {
            background: #E2E8F0;
        }

        /* ============================================================
           NOTIFICATIONS
        ============================================================ */
        .notif-item {
            display: flex;
            gap: 0.7rem;
            align-items: flex-start;
            padding: 0.6rem 0;
            border-bottom: 1px solid #F8FAFC;
        }
        .notif-item:last-child {
            border-bottom: none;
        }
        .notif-item .notif-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
            flex-shrink: 0;
            margin-top: 4px;
        }
        .notif-item .notif-content .notif-msg {
            font-size: 0.8rem;
            color: #1E293B;
            line-height: 1.4;
        }
        .notif-item .notif-content .notif-time {
            font-size: 0.65rem;
            color: var(--gray-light);
            margin-top: 0.1rem;
        }
        .notif-empty {
            text-align: center;
            padding: 1.5rem 0.5rem;
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* ============================================================
           TOP PATIENTS
        ============================================================ */
        .top-patient {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #F8FAFC;
        }
        .top-patient:last-child {
            border-bottom: none;
        }
        .top-patient .tp-rank {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--gray-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--gray);
            flex-shrink: 0;
        }
        .top-patient .tp-rank.gold {
            background: #FEF3C7;
            color: #D97706;
        }
        .top-patient .tp-rank.silver {
            background: #F1F5F9;
            color: #64748B;
        }
        .top-patient .tp-rank.bronze {
            background: #FEF3C7;
            color: #B45309;
        }
        .top-patient .tp-info {
            flex: 1;
            min-width: 0;
        }
        .top-patient .tp-info .tp-name {
            font-weight: 600;
            font-size: 0.85rem;
        }
        .top-patient .tp-info .tp-detail {
            font-size: 0.65rem;
            color: var(--gray);
        }
        .top-patient .tp-count {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--primary);
        }

        /* ============================================================
           DISPONIBILITÉS
        ============================================================ */
        .dispo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #F8FAFC;
            font-size: 0.8rem;
        }
        .dispo-item:last-child {
            border-bottom: none;
        }
        .dispo-item .dispo-jour {
            font-weight: 600;
            color: var(--dark);
        }
        .dispo-item .dispo-heures {
            color: var(--gray);
        }
        .dispo-item .dispo-status {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 0.1rem 0.5rem;
            border-radius: 12px;
            background: #F0FDF4;
            color: #15803D;
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1200px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
            .three-col {
                grid-template-columns: 1fr 1fr;
            }
            .two-col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .three-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 280px;
            }

            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.4);
                z-index: 999;
            }
            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .top-header .header-left .hamburger {
                display: flex;
            }
            .top-header .header-left .page-title span {
                display: none;
            }
            .top-header .header-right .logout-mobile {
                display: inline-block;
            }

            .page-content {
                padding: 1rem 1rem 1.5rem;
            }

            .greeting-banner {
                padding: 1.2rem 1.2rem;
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .greeting-banner .greeting-actions {
                justify-content: center;
            }
            .greeting-banner .greeting-text h2 .spec-tag {
                display: block;
                margin: 0.3rem auto 0;
                width: fit-content;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.7rem;
            }
            .stats-grid .stat-card {
                padding: 0.8rem 1rem;
            }
            .stats-grid .stat-card .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            .stats-grid .stat-card .stat-info .stat-number {
                font-size: 1.1rem;
            }

            .chart-card {
                padding: 1rem;
            }
            .chart-card .chart-wrapper {
                height: 180px;
            }

            .card {
                padding: 1rem;
            }

            .rdv-item {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .rdv-item .rdv-time {
                display: flex;
                gap: 0.5rem;
                align-items: center;
                min-width: auto;
                width: 100%;
            }
            .rdv-item .rdv-time .date {
                display: none;
            }
            .rdv-item .rdv-status {
                margin-left: auto;
            }

            .two-col {
                grid-template-columns: 1fr;
            }
            .three-col {
                grid-template-columns: 1fr;
            }

            .top-header {
                padding: 0 1rem;
            }

            .today-rdv {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .today-rdv .today-actions {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
            }
            .stats-grid .stat-card {
                flex-direction: column;
                text-align: center;
                padding: 0.7rem;
            }
            .stats-grid .stat-card .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }
            .stats-grid .stat-card .stat-info .stat-number {
                font-size: 1rem;
            }
            .stats-grid .stat-card .stat-info .stat-label {
                font-size: 0.6rem;
            }

            .greeting-banner .greeting-text h2 {
                font-size: 1rem;
            }
            .greeting-banner .greeting-actions .btn {
                font-size: 0.75rem;
                padding: 0.4rem 0.9rem;
            }

            .chart-card .chart-wrapper {
                height: 150px;
            }
        }

        /* ============================================================
           ANIMATIONS
        ============================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
        }
        .animate-in:nth-child(1) { animation-delay: 0.05s; }
        .animate-in:nth-child(2) { animation-delay: 0.1s; }
        .animate-in:nth-child(3) { animation-delay: 0.15s; }
        .animate-in:nth-child(4) { animation-delay: 0.2s; }
        .animate-in:nth-child(5) { animation-delay: 0.25s; }
        .animate-in:nth-child(6) { animation-delay: 0.3s; }
        .animate-in:nth-child(7) { animation-delay: 0.35s; }
        .animate-in:nth-child(8) { animation-delay: 0.4s; }
    </style>
</head>
<body>

<!-- ============================================================
   SIDEBAR OVERLAY (mobile)
============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ============================================================
   SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon">M</div>
        <div>
            <div class="brand-text">Medi<span>RDV</span></div>
            <div class="brand-sub">Espace Médecin</div>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if(!empty($medecin['photo']) && file_exists(__DIR__.'/../assets/uploads/'.$medecin['photo'])): ?>
                <img src="../assets/uploads/<?=htmlspecialchars($medecin['photo'])?>" alt="">
            <?php else: ?>
                <?=$initiales?>
            <?php endif; ?>
            <div class="online-dot"></div>
        </div>
        <div class="user-info">
            <div class="name">Dr. <?=htmlspecialchars($medecin['prenom'].' '.$medecin['nom'])?></div>
            <div class="role">Médecin</div>
            <div class="specialite"><i class="fas fa-stethoscope"></i> <?=htmlspecialchars($medecin['specialite'])?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Navigation</div>
        <a href="dashboard_medecin.php" class="active">
            <i class="fas fa-th-large"></i> Tableau de bord
        </a>
        <a href="mon_agenda.php">
            <i class="fas fa-calendar-alt"></i> Mon Agenda
            <?php if((int)$stats['a_venir'] > 0): ?>
                <span class="badge"><?=(int)$stats['a_venir']?></span>
            <?php endif; ?>
        </a>
        <a href="gerer_rdv.php">
            <i class="fas fa-clipboard-list"></i> Gérer les RDV
        </a>
        <a href="gestion_rdv.php">
            <i class="fas fa-tasks"></i> Gestion RDV
        </a>
        <a href="profil_medecin.php">
            <i class="fas fa-user-cog"></i> Mon Profil
        </a>
        <a href="historique_medecin.php">
            <i class="fas fa-history"></i> Historique
        </a>
        <a href="notifications.php">
            <i class="fas fa-bell"></i> Notifications
            <?php if($notif_count > 0): ?>
                <span class="badge"><?=$notif_count?></span>
            <?php endif; ?>
        </a>
        <a href="aide.php">
            <i class="fas fa-question-circle"></i> Aide
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
        <div style="font-size:0.6rem;color:rgba(255,255,255,0.2);margin-top:0.5rem;text-align:center;">
            v2.0 — L3 IDA 2025
        </div>
    </div>
</aside>

<!-- ============================================================
   MAIN CONTENT
============================================================ -->
<div class="main-content">

    <!-- ===== HEADER ===== -->
    <header class="top-header">
        <div class="header-left">
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                Tableau de bord <span>• Médecin</span>
            </div>
        </div>
        <div class="header-right">
            <a href="notifications.php" class="notif-btn">
                <i class="fas fa-bell"></i>
                <?php if($notif_count > 0): ?>
                    <span class="notif-dot"><?=$notif_count?></span>
                <?php endif; ?>
            </a>
            <a href="profil_medecin.php" class="header-avatar">
                <?php if(!empty($medecin['photo']) && file_exists(__DIR__.'/../assets/uploads/'.$medecin['photo'])): ?>
                    <img src="../assets/uploads/<?=htmlspecialchars($medecin['photo'])?>" alt="">
                <?php else: ?>
                    <?=$initiales?>
                <?php endif; ?>
                <div class="mini-dot"></div>
            </a>
            <a href="logout.php" class="logout-mobile">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>

    <!-- ===== PAGE CONTENT ===== -->
    <div class="page-content">

        <!-- GREETING BANNER -->
        <div class="greeting-banner animate-in">
            <div class="greeting-text">
                <h2>
                    <?=$salutation?>, Dr. <?=htmlspecialchars($medecin['prenom'])?> ! 👋
                    <span class="spec-tag"><i class="fas fa-stethoscope"></i> <?=htmlspecialchars($medecin['specialite'])?></span>
                </h2>
                <p>
                    <?php if(count($rdv_aujourdhui) > 0): ?>
                        Vous avez <strong><?=count($rdv_aujourdhui)?> rendez-vous</strong> aujourd'hui.
                    <?php else: ?>
                        Aucun rendez-vous aujourd'hui. Profitez-en pour gérer votre agenda.
                    <?php endif; ?>
                    <?php if((int)$stats['en_attente'] > 0): ?>
                        <span style="color:var(--orange);font-weight:600;">(<?=(int)$stats['en_attente']?> en attente)</span>
                    <?php endif; ?>
                    <span style="color:rgba(255,255,255,0.4);margin-left:0.5rem;">• <?=(int)$patient_stats['total_patients']?> patients</span>
                </p>
            </div>
            <div class="greeting-actions">
                <a href="mon_agenda.php" class="btn btn-primary">
                    <i class="fas fa-calendar-alt"></i> Voir mon agenda
                </a>
                <a href="gerer_rdv.php" class="btn btn-outline">
                    <i class="fas fa-clipboard-list"></i> Gérer les RDV
                </a>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#EFF6FF;color:var(--primary);">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" style="color:var(--primary);"><?=(int)$stats['a_venir']?></div>
                    <div class="stat-label">À venir</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#FFF7ED;color:var(--orange);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" style="color:var(--orange);"><?=(int)$stats['en_attente']?></div>
                    <div class="stat-label">En attente</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#F0FDF4;color:var(--green);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" style="color:var(--green);"><?=(int)$stats['termines']?></div>
                    <div class="stat-label">Consultations</div>
                    <span class="stat-trend up">+<?=(int)$stats['dernier_mois']?> ce mois</span>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#FEF2F2;color:var(--red);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" style="color:var(--red);"><?=(int)$stats['annules']?></div>
                    <div class="stat-label">Annulés</div>
                    <span class="stat-trend <?=$taux_annulation > 10 ? 'down' : 'up'?>">
                        <?=number_format($taux_annulation,1)?>% taux
                    </span>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#F0F4FF;color:var(--purple);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" style="color:var(--purple);"><?=(int)$patient_stats['total_patients']?></div>
                    <div class="stat-label">Patients</div>
                    <span class="stat-trend up">+<?=(int)$patient_stats['nouveau_mois']?> nouveau<?=(int)$patient_stats['nouveau_mois']>1?'x':''?></span>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#F5F3FF;color:var(--dark);">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" style="color:var(--dark);"><?=(int)$stats['total']?></div>
                    <div class="stat-label">Total RDV</div>
                </div>
            </div>
        </div>

        <!-- CHARTS -->
        <div class="charts-row">
            <div class="chart-card animate-in">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);"></i> Évolution des consultations</h3>
                    <span class="chart-badge">12 derniers mois</span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="evolutionChart"></canvas>
                </div>
            </div>
            <div class="chart-card animate-in">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar" style="color:var(--orange);"></i> Rendez-vous par jour</h3>
                    <span class="chart-badge">3 derniers mois</span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="jourChart"></canvas>
                </div>
            </div>
        </div>

        <!-- THREE COLUMN -->
        <div class="three-col">

            <!-- RDV DU JOUR -->
            <div class="card animate-in">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-day" style="color:var(--green);"></i> Aujourd'hui</h3>
                    <span class="chart-badge"><?=date('d/m/Y')?></span>
                </div>
                <?php if(empty($rdv_aujourdhui)): ?>
                    <div class="rdv-empty">
                        <div class="empty-icon">📅</div>
                        <p>Pas de rendez-vous aujourd'hui.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($rdv_aujourdhui as $rdv): ?>
                        <div class="today-rdv">
                            <div class="today-info">
                                <span class="today-time"><?=substr($rdv['heure_rdv'],0,5)?></span>
                                <span class="today-patient">
                                    <?=htmlspecialchars($rdv['p_prenom'].' '.$rdv['p_nom'])?>
                                    <span style="font-weight:400;color:var(--gray);font-size:0.7rem;">
                                        (<?=getAge($rdv['date_naissance'])?>)
                                    </span>
                                </span>
                                <?php if(!empty($rdv['allergies'])): ?>
                                    <span class="today-detail" style="color:var(--red);">
                                        <i class="fas fa-allergies"></i> Allergies
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="today-actions">
                                <a href="detail_rdv.php?id=<?=$rdv['id']?>" class="btn-sm btn-sm-view">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="compte_rendu.php?id=<?=$rdv['id']?>" class="btn-sm btn-sm-start">
                                    <i class="fas fa-stethoscope"></i> Consulter
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- PROCHAINS RDV -->
            <div class="card animate-in">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check" style="color:var(--primary);"></i> Prochains RDV</h3>
                    <a href="mon_agenda.php" class="see-all">Voir tout →</a>
                </div>
                <?php if(empty($prochains_rdv)): ?>
                    <div class="rdv-empty">
                        <div class="empty-icon">📋</div>
                        <p>Aucun rendez-vous à venir.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $count = 0;
                    foreach($prochains_rdv as $rdv): 
                        if($count >= 4) break;
                        $count++;
                    ?>
                        <a href="detail_rdv.php?id=<?=$rdv['id']?>" class="rdv-item">
                            <div class="rdv-time">
                                <div class="day"><?=(new DateTime($rdv['date_rdv']))->format('d')?></div>
                                <div class="date"><?=$jours_fr[(new DateTime($rdv['date_rdv']))->format('l')]??''?></div>
                                <div class="hour"><?=substr($rdv['heure_rdv'],0,5)?></div>
                            </div>
                            <div class="rdv-info">
                                <div class="rdv-patient">
                                    <?=htmlspecialchars($rdv['p_prenom'].' '.$rdv['p_nom'])?>
                                    <span class="age-tag"><?=getAge($rdv['date_naissance'])?></span>
                                </div>
                                <div class="rdv-motif"><?=htmlspecialchars(mb_substr($rdv['motif'],0,40)).(mb_strlen($rdv['motif'])>40?'...':'')?></div>
                                <?php if(!empty($rdv['allergies'])): ?>
                                    <div class="rdv-allergies"><i class="fas fa-exclamation-triangle"></i> Allergies</div>
                                <?php endif; ?>
                            </div>
                            <div class="rdv-status">
                                <span class="badge <?=$rdv['statut']?>">
                                    <?php if($rdv['statut'] === 'confirme'): ?>
                                        ✅ Confirmé
                                    <?php elseif($rdv['statut'] === 'en_attente'): ?>
                                        ⏳ Attente
                                    <?php endif; ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if(count($prochains_rdv) > 4): ?>
                        <div style="text-align:center;margin-top:0.5rem;">
                            <a href="mon_agenda.php" style="font-size:0.75rem;color:var(--primary);font-weight:600;text-decoration:none;">
                                + <?=count($prochains_rdv)-4?> autre<?=count($prochains_rdv)-4>1?'s':''?> → 
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- TOP PATIENTS -->
            <div class="card animate-in">
                <div class="card-header">
                    <h3><i class="fas fa-trophy" style="color:var(--orange);"></i> Patients fidèles</h3>
                    <span class="chart-badge">Top 5</span>
                </div>
                <?php if(empty($top_patients)): ?>
                    <div class="rdv-empty">
                        <div class="empty-icon">🏆</div>
                        <p>Aucun patient pour le moment.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $ranks = ['gold' => '🥇', 'silver' => '🥈', 'bronze' => '🥉'];
                    $i = 0;
                    foreach($top_patients as $p):
                        $rankClass = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : ''));
                    ?>
                        <div class="top-patient">
                            <div class="tp-rank <?=$rankClass?>">
                                <?=isset($ranks[$rankClass]) ? $ranks[$rankClass] : ($i+1)?>
                            </div>
                            <div class="tp-info">
                                <div class="tp-name"><?=htmlspecialchars($p['prenom'].' '.$p['nom'])?></div>
                                <div class="tp-detail">
                                    <i class="fas fa-phone"></i> <?=htmlspecialchars($p['telephone'])?>
                                    • Dernier: <?=fmtDate($p['dernier_rdv'],$jours_fr,$mois_fr)?>
                                </div>
                            </div>
                            <div class="tp-count"><?=(int)$p['nb_rdv']?> RDV</div>
                        </div>
                    <?php 
                        $i++;
                    endforeach; 
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TWO COLUMN -->
        <div class="two-col">

            <!-- NOTIFICATIONS -->
            <div class="card animate-in">
                <div class="card-header">
                    <h3><i class="fas fa-bell" style="color:var(--orange);"></i> Notifications</h3>
                    <?php if($notif_count > 0): ?>
                        <a href="notifications.php" class="see-all"><?=$notif_count?> non lue<?=$notif_count>1?'s':''?></a>
                    <?php endif; ?>
                </div>
                <?php if(empty($notifs)): ?>
                    <div class="notif-empty">
                        <i class="fas fa-check-circle" style="color:var(--green);font-size:1.2rem;display:block;margin-bottom:0.3rem;"></i>
                        Tout est tranquille ! Aucune notification.
                    </div>
                <?php else: ?>
                    <?php foreach($notifs as $n): ?>
                        <div class="notif-item">
                            <div class="notif-dot"></div>
                            <div class="notif-content">
                                <div class="notif-msg"><?=htmlspecialchars(mb_substr($n['message'],0,80)).(mb_strlen($n['message'])>80?'...':'')?></div>
                                <div class="notif-time"><i class="far fa-clock"></i> <?=timeAgo($n['date_envoi'])?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- DISPONIBILITÉS -->
            <div class="card animate-in">
                <div class="card-header">
                    <h3><i class="fas fa-clock" style="color:var(--primary);"></i> Mes disponibilités</h3>
                    <a href="profil_medecin.php#disponibilites" class="see-all">Modifier →</a>
                </div>
                <?php if(empty($disponibilites)): ?>
                    <div class="rdv-empty">
                        <div class="empty-icon">⏰</div>
                        <p>Aucune disponibilité configurée.</p>
                        <a href="profil_medecin.php#disponibilites" style="display:inline-block;margin-top:0.5rem;font-size:0.75rem;color:var(--primary);font-weight:600;text-decoration:none;">
                            Configurer →
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($disponibilites as $d): ?>
                        <div class="dispo-item">
                            <span class="dispo-jour"><?=ucfirst($d['jour_semaine'])?></span>
                            <span class="dispo-heures">
                                <?=substr($d['heure_debut'],0,5)?> - <?=substr($d['heure_fin'],0,5)?>
                                <span style="font-size:0.6rem;color:var(--gray-light);">
                                    (<?=$d['duree_rdv']?> min)
                                </span>
                            </span>
                            <span class="dispo-status">✓ Actif</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- DERNIÈRE CONSULTATION -->
        <?php if($derniere_consult): ?>
        <div class="card animate-in">
            <div class="card-header">
                <h3><i class="fas fa-stethoscope" style="color:var(--purple);"></i> Dernière consultation</h3>
                <span class="chart-badge"><?=fmtDate($derniere_consult['date_rdv'],$jours_fr,$mois_fr)?></span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;">
                <div style="flex:1;min-width:200px;">
                    <div style="font-weight:700;font-size:1rem;">
                        <?=htmlspecialchars($derniere_consult['p_prenom'].' '.$derniere_consult['p_nom'])?>
                        <span style="font-weight:400;font-size:0.8rem;color:var(--gray);">
                            • <?=getAge($derniere_consult['date_naissance'])?> • <?=substr($derniere_consult['heure_rdv'],0,5)?>
                        </span>
                    </div>
                    <?php if(!empty($derniere_consult['diagnostic'])): ?>
                        <div style="font-size:0.85rem;color:var(--gray);margin-top:0.3rem;">
                            <strong>Diagnostic :</strong> <?=htmlspecialchars(mb_substr($derniere_consult['diagnostic'],0,150)).(mb_strlen($derniere_consult['diagnostic'])>150?'...':'')?>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($derniere_consult['traitement'])): ?>
                        <div style="font-size:0.8rem;color:var(--gray);margin-top:0.2rem;">
                            <strong>Traitement :</strong> <?=htmlspecialchars(mb_substr($derniere_consult['traitement'],0,100)).(mb_strlen($derniere_consult['traitement'])>100?'...':'')?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:0.5rem;flex-shrink:0;">
                    <a href="detail_rdv.php?id=<?=$derniere_consult['id']?>" class="btn-sm btn-sm-view" style="padding:0.4rem 1rem;background:var(--gray-bg);color:var(--gray);border-radius:8px;text-decoration:none;font-size:0.8rem;font-weight:600;">
                        <i class="fas fa-eye"></i> Voir
                    </a>
                    <a href="compte_rendu.php?id=<?=$derniere_consult['id']?>" class="btn-sm btn-sm-start" style="padding:0.4rem 1rem;background:var(--primary);color:#fff;border-radius:8px;text-decoration:none;font-size:0.8rem;font-weight:600;">
                        <i class="fas fa-file-medical"></i> Compte-rendu
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /page-content -->

    <!-- ===== FOOTER ===== -->
    <footer style="background:var(--dark);color:#94A3B8;padding:1.2rem 2rem;text-align:center;font-size:0.75rem;flex-shrink:0;">
        <p><strong style="color:#fff;">MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    </footer>

</div><!-- /main-content -->

<!-- ============================================================
   SCRIPTS
============================================================ -->
<script>
    // ===== SIDEBAR TOGGLE =====
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }

    // ===== CHARTS =====
    document.addEventListener('DOMContentLoaded', function() {
        // --- Évolution Chart ---
        const evoData = <?php 
            $mois_labels = [];
            $termines_data = [];
            $annules_data = [];
            $attente_data = [];
            foreach($stats_mensuelles as $row) {
                $mois_labels[] = $row['mois'];
                $termines_data[] = (int)$row['termines'];
                $annules_data[] = (int)$row['annules'];
                $attente_data[] = (int)$row['en_attente'];
            }
            echo json_encode([
                'labels' => $mois_labels,
                'termines' => $termines_data,
                'annules' => $annules_data,
                'attente' => $attente_data
            ]);
        ?>;
        
        const ctx1 = document.getElementById('evolutionChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: evoData.labels,
                datasets: [
                    {
                        label: 'Consultations terminées',
                        data: evoData.termines,
                        borderColor: '#2DC653',
                        backgroundColor: 'rgba(45,198,83,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2DC653',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                    },
                    {
                        label: 'En attente',
                        data: evoData.attente,
                        borderColor: '#F77F00',
                        backgroundColor: 'rgba(247,127,0,0.05)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#F77F00',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderDash: [5, 5],
                    },
                    {
                        label: 'Annulés',
                        data: evoData.annules,
                        borderColor: '#D62828',
                        backgroundColor: 'rgba(214,40,40,0.05)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#D62828',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        borderDash: [3, 3],
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15,
                            font: { size: 10, weight: '500' },
                            boxWidth: 10,
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.9)',
                        titleColor: '#fff',
                        bodyColor: '#E2E8F0',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { weight: '600' },
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // --- Jour de la semaine Chart ---
        const jourData = <?php 
            $jours_labels = [];
            $jours_values = [];
            foreach($stats_jours as $row) {
                $jours_labels[] = $row['jour'];
                $jours_values[] = (int)$row['total'];
            }
            echo json_encode([
                'labels' => $jours_labels,
                'values' => $jours_values
            ]);
        ?>;
        
        const ctx2 = document.getElementById('jourChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: jourData.labels,
                datasets: [{
                    label: 'Rendez-vous',
                    data: jourData.values,
                    backgroundColor: ['#0077B6','#0096C7','#00B4D8','#48CAE4','#90E0EF','#ADE8F4','#CAF0F8'],
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.9)',
                        titleColor: '#fff',
                        bodyColor: '#E2E8F0',
                        padding: 12,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.04)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    });

    // ===== CLOSE SIDEBAR ON RESIZE =====
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
</script>
</body>
</html>