<?php
// ================================================================
// dashboard_patient.php — Tableau de bord Patient Premium
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php');
}

$patient_id = $_SESSION['user_id'];

// ================================================================
// DONNÉES DU PATIENT
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) { session_destroy(); redirect('../index.php'); }

// ================================================================
// STATISTIQUES AVANCÉES
// ================================================================

// Statistiques générales
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN statut IN ('confirme','en_attente') AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS a_venir,
        SUM(CASE WHEN statut = 'en_attente' AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut = 'confirme' AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS confirmes,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termines,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) AS annules,
        SUM(CASE WHEN statut = 'refuse' THEN 1 ELSE 0 END) AS refuses
    FROM rendezvous WHERE id_patient = :id
");
$stmt->execute([':id' => $patient_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistiques par mois (6 derniers mois)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date_rdv, '%Y-%m') AS mois,
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termines,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) AS annules
    FROM rendezvous 
    WHERE id_patient = :id 
        AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_rdv, '%Y-%m')
    ORDER BY mois ASC
");
$stmt->execute([':id' => $patient_id]);
$stats_mensuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques par spécialité
$stmt = $pdo->prepare("
    SELECT 
        m.specialite,
        COUNT(*) AS total,
        SUM(CASE WHEN r.statut = 'termine' THEN 1 ELSE 0 END) AS termines
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    WHERE r.id_patient = :id
    GROUP BY m.specialite
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute([':id' => $patient_id]);
$stats_specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PROCHAINS RDV
// ================================================================
$stmt = $pdo->prepare("
    SELECT r.*, m.nom AS m_nom, m.prenom AS m_prenom, m.specialite, 
           m.cabinet, m.ville, m.photo AS m_photo
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    WHERE r.id_patient = :id
      AND r.statut IN ('confirme','en_attente')
      AND r.date_rdv >= CURDATE()
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    LIMIT 5
");
$stmt->execute([':id' => $patient_id]);
$prochains_rdv = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// DERNIERS RDV TERMINÉS
// ================================================================
$stmt = $pdo->prepare("
    SELECT r.*, m.nom AS m_nom, m.prenom AS m_prenom, m.specialite
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    WHERE r.id_patient = :id AND r.statut = 'termine'
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 3
");
$stmt->execute([':id' => $patient_id]);
$derniers_rdv = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// NOTIFICATIONS
// ================================================================
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE id_destinataire = :id AND type_user = 'patient' AND lu = 0
    ORDER BY date_envoi DESC
    LIMIT 5
");
$stmt->execute([':id' => $patient_id]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($notifs);

// ================================================================
// DOSSIER MÉDICAL
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// ACTIVITÉ RÉCENTE (Journal d'accès)
// ================================================================
$stmt = $pdo->prepare("
    SELECT action, date_action 
    FROM journal_acces 
    WHERE id_user = :id AND type_user = 'patient'
    ORDER BY date_action DESC 
    LIMIT 5
");
$stmt->execute([':id' => $patient_id]);
$activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HELPERS
// ================================================================
$mois_fr = ['January'=>'Janv','February'=>'Févr','March'=>'Mars','April'=>'Avr',
            'May'=>'Mai','June'=>'Juin','July'=>'Juil','August'=>'Août',
            'September'=>'Sept','October'=>'Oct','November'=>'Nov','December'=>'Déc'];
$jours_fr = ['Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mer',
             'Thursday'=>'Jeu','Friday'=>'Ven','Saturday'=>'Sam','Sunday'=>'Dim'];

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

$heure_actuelle = (int)date('H');
if ($heure_actuelle < 12) $salutation = 'Bonjour';
elseif ($heure_actuelle < 18) $salutation = 'Bon après-midi';
else $salutation = 'Bonsoir';

$initiales = strtoupper(mb_substr($patient['prenom'],0,1).mb_substr($patient['nom'],0,1));

$couleurs_specialites = [
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
function getCouleurSpecialite($s, $c) {
    return $c[$s] ?? ['color' => '#64748B', 'bg' => '#F1F5F9'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Tableau de bord Patient</title>
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
        }
        .sidebar-user .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sidebar-user .user-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            flex-shrink: 0;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
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
        }
        .top-header .header-right .header-avatar:hover {
            transform: scale(1.05);
        }
        .top-header .header-right .header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
        }
        .chart-card .chart-header h3 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--dark);
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

        /* ============================================================
           RDV CARDS
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
        .rdv-item .rdv-info .rdv-doctor {
            font-weight: 600;
            font-size: 0.85rem;
        }
        .rdv-item .rdv-info .rdv-specialite {
            font-size: 0.7rem;
            color: var(--gray);
        }
        .rdv-item .rdv-info .rdv-motif {
            font-size: 0.7rem;
            color: var(--gray-light);
            margin-top: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
        .rdv-item .rdv-status .badge.refuse {
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
           NOTIFICATIONS SIDEBAR
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
           ACTIVITY LOG
        ============================================================ */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #F8FAFC;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item .act-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--gray-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: var(--gray);
            flex-shrink: 0;
        }
        .activity-item .act-text {
            flex: 1;
            font-size: 0.8rem;
            color: #1E293B;
        }
        .activity-item .act-text .act-action {
            font-weight: 500;
        }
        .activity-item .act-time {
            font-size: 0.6rem;
            color: var(--gray-light);
            flex-shrink: 0;
        }

        /* ============================================================
           DOSSIER QUICK
        ============================================================ */
        .dossier-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .dossier-grid .dossier-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.7rem;
            background: var(--gray-bg);
            border-radius: 8px;
            font-size: 0.75rem;
        }
        .dossier-grid .dossier-item .label {
            color: var(--gray);
            font-weight: 500;
        }
        .dossier-grid .dossier-item .value {
            font-weight: 600;
            color: var(--dark);
        }
        .dossier-grid .dossier-item .value.empty {
            color: var(--gray-light);
            font-weight: 400;
        }
        .dossier-grid .dossier-item.alert {
            background: #FEF2F2;
            border-left: 3px solid var(--red);
        }
        .dossier-grid .dossier-item.warning {
            background: #FFF7ED;
            border-left: 3px solid var(--orange);
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1200px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
            .two-col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
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

            .dossier-grid {
                grid-template-columns: 1fr;
            }

            .top-header {
                padding: 0 1rem;
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
            <div class="brand-sub">Patient Dashboard</div>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if(!empty($patient['photo']) && file_exists(__DIR__.'/../assets/img/'.$patient['photo'])): ?>
                <img src="../assets/img/<?=htmlspecialchars($patient['photo'])?>" alt="">
            <?php else: ?>
                <?=$initiales?>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <div class="name"><?=htmlspecialchars($patient['prenom'].' '.$patient['nom'])?></div>
            <div class="role">Patient</div>
        </div>
        <div class="user-status"></div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Navigation</div>
        <a href="dashboard_patient.php" class="active">
            <i class="fas fa-th-large"></i> Tableau de bord
        </a>
        <a href="mes_rdv.php">
            <i class="fas fa-calendar-check"></i> Mes RDV
            <?php if((int)$stats['a_venir'] > 0): ?>
                <span class="badge"><?=(int)$stats['a_venir']?></span>
            <?php endif; ?>
        </a>
        <a href="prendre_rdv.php">
            <i class="fas fa-plus-circle"></i> Prendre RDV
        </a>
        <a href="liste_medecins.php">
            <i class="fas fa-user-md"></i> Médecins
        </a>
        <a href="dossier_patient.php">
            <i class="fas fa-folder-medical"></i> Mon Dossier
        </a>
        <a href="historique_patient.php">
            <i class="fas fa-history"></i> Historique
        </a>
        <a href="notifications.php">
            <i class="fas fa-bell"></i> Notifications
            <?php if($notif_count > 0): ?>
                <span class="badge"><?=$notif_count?></span>
            <?php endif; ?>
        </a>
        <a href="profil_patient.php">
            <i class="fas fa-user-cog"></i> Mon Profil
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
                Tableau de bord <span>• Patient</span>
            </div>
        </div>
        <div class="header-right">
            <a href="notifications.php" class="notif-btn">
                <i class="fas fa-bell"></i>
                <?php if($notif_count > 0): ?>
                    <span class="notif-dot"><?=$notif_count?></span>
                <?php endif; ?>
            </a>
            <a href="profil_patient.php" class="header-avatar">
                <?php if(!empty($patient['photo']) && file_exists(__DIR__.'/../assets/img/'.$patient['photo'])): ?>
                    <img src="../assets/img/<?=htmlspecialchars($patient['photo'])?>" alt="">
                <?php else: ?>
                    <?=$initiales?>
                <?php endif; ?>
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
                <h2><?=$salutation?>, <?=htmlspecialchars($patient['prenom'])?> ! 👋</h2>
                <p>
                    <?php if((int)$stats['a_venir'] > 0): ?>
                        Vous avez <strong><?=(int)$stats['a_venir']?> rendez-vous</strong> à venir.
                    <?php else: ?>
                        Aucun rendez-vous à venir. Prenez un RDV dès maintenant !
                    <?php endif; ?>
                    <?php if((int)$stats['en_attente'] > 0): ?>
                        <span style="color:var(--orange);font-weight:600;">(<?=(int)$stats['en_attente']?> en attente)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="greeting-actions">
                <a href="prendre_rdv.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Prendre RDV
                </a>
                <a href="liste_medecins.php" class="btn btn-outline">
                    <i class="fas fa-search"></i> Trouver un médecin
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
                    <div class="stat-label">Terminés</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#FEF2F2;color:var(--red);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" style="color:var(--red);"><?=(int)$stats['annules']?></div>
                    <div class="stat-label">Annulés</div>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon" style="background:#F0F4FF;color:var(--dark);">
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
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:0.4rem;"></i> Évolution des consultations</h3>
                    <span class="chart-badge">6 derniers mois</span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="evolutionChart"></canvas>
                </div>
            </div>
            <div class="chart-card animate-in">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie" style="color:var(--purple);margin-right:0.4rem;"></i> Par spécialité</h3>
                    <span class="chart-badge">Top 5</span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="specialiteChart"></canvas>
                </div>
            </div>
        </div>

        <!-- TWO COLUMN -->
        <div class="two-col">

            <!-- LEFT: PROCHAINS RDV -->
            <div class="card animate-in">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check" style="color:var(--green);"></i> Prochains rendez-vous</h3>
                    <a href="mes_rdv.php" class="see-all">Voir tout →</a>
                </div>
                <?php if(empty($prochains_rdv)): ?>
                    <div class="rdv-empty">
                        <div class="empty-icon">📅</div>
                        <p>Aucun rendez-vous à venir.</p>
                        <a href="prendre_rdv.php" style="display:inline-block;margin-top:0.5rem;padding:0.4rem 1rem;background:var(--green);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.8rem;">
                            <i class="fas fa-plus"></i> Prendre RDV
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($prochains_rdv as $rdv): ?>
                        <a href="detail_rdv.php?id=<?=$rdv['id']?>" class="rdv-item">
                            <div class="rdv-time">
                                <div class="day"><?=(new DateTime($rdv['date_rdv']))->format('d')?></div>
                                <div class="date"><?=$jours_fr[(new DateTime($rdv['date_rdv']))->format('l')]??''?></div>
                                <div class="hour"><?=substr($rdv['heure_rdv'],0,5)?></div>
                            </div>
                            <div class="rdv-info">
                                <div class="rdv-doctor">Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?></div>
                                <div class="rdv-specialite"><?=htmlspecialchars($rdv['specialite'])?> • <?=htmlspecialchars($rdv['cabinet'])?></div>
                                <div class="rdv-motif"><?=htmlspecialchars(mb_substr($rdv['motif'],0,50)).(mb_strlen($rdv['motif'])>50?'...':'')?></div>
                            </div>
                            <div class="rdv-status">
                                <span class="badge <?=$rdv['statut']?>">
                                    <?php if($rdv['statut'] === 'confirme'): ?>
                                        <i class="fas fa-check-circle"></i> Confirmé
                                    <?php elseif($rdv['statut'] === 'en_attente'): ?>
                                        <i class="fas fa-clock"></i> Attente
                                    <?php endif; ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- RIGHT: NOTIFICATIONS + ACTIVITÉ -->
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

                <div style="margin-top:1rem;padding-top:0.8rem;border-top:1px solid #F1F5F9;">
                    <div class="card-header" style="margin-bottom:0.5rem;padding-bottom:0;">
                        <h3 style="font-size:0.8rem;"><i class="fas fa-history" style="color:var(--gray);"></i> Activité récente</h3>
                    </div>
                    <?php if(empty($activites)): ?>
                        <div style="font-size:0.75rem;color:var(--gray-light);text-align:center;padding:0.3rem 0;">
                            Aucune activité récente
                        </div>
                    <?php else: ?>
                        <?php foreach($activites as $act): ?>
                            <div class="activity-item">
                                <div class="act-icon"><i class="fas fa-circle" style="font-size:0.4rem;"></i></div>
                                <div class="act-text">
                                    <span class="act-action"><?=htmlspecialchars(str_replace('_',' ',$act['action']))?></span>
                                </div>
                                <div class="act-time"><?=timeAgo($act['date_action'])?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DOSSIER RAPIDE -->
        <div class="card animate-in">
            <div class="card-header">
                <h3><i class="fas fa-folder-medical" style="color:var(--purple);"></i> Mon dossier médical</h3>
                <a href="dossier_patient.php" class="see-all">Voir complet →</a>
            </div>
            <div class="dossier-grid">
                <div class="dossier-item <?=!empty($patient['groupe_sanguin'])?'':'alert'?>">
                    <span class="label"><i class="fas fa-tint"></i> Groupe sanguin</span>
                    <span class="value <?=empty($patient['groupe_sanguin'])?'empty':''?>">
                        <?=$patient['groupe_sanguin'] ?: 'Non renseigné'?>
                    </span>
                </div>
                <div class="dossier-item">
                    <span class="label"><i class="fas fa-ruler-vertical"></i> Taille</span>
                    <span class="value <?=empty($patient['taille'])?'empty':''?>">
                        <?=!empty($patient['taille']) ? (int)$patient['taille'].' cm' : '—'?>
                    </span>
                </div>
                <div class="dossier-item">
                    <span class="label"><i class="fas fa-weight"></i> Poids</span>
                    <span class="value <?=empty($patient['poids'])?'empty':''?>">
                        <?=!empty($patient['poids']) ? (int)$patient['poids'].' kg' : '—'?>
                    </span>
                </div>
                <div class="dossier-item <?=!empty($dossier['allergies'])?'alert':''?>">
                    <span class="label"><i class="fas fa-allergies"></i> Allergies</span>
                    <span class="value <?=empty($dossier['allergies'])?'empty':''?>">
                        <?=!empty($dossier['allergies']) ? '⚠️ Oui' : 'Aucune'?>
                    </span>
                </div>
                <div class="dossier-item <?=!empty($dossier['maladies_chroniques'])?'warning':''?>">
                    <span class="label"><i class="fas fa-heartbeat"></i> Maladies chroniques</span>
                    <span class="value <?=empty($dossier['maladies_chroniques'])?'empty':''?>">
                        <?=!empty($dossier['maladies_chroniques']) ? 'Oui' : 'Aucune'?>
                    </span>
                </div>
                <div class="dossier-item">
                    <span class="label"><i class="fas fa-syringe"></i> Antécédents</span>
                    <span class="value <?=empty($dossier['antecedents'])?'empty':''?>">
                        <?=!empty($dossier['antecedents']) ? '✅ Renseignés' : '—'?>
                    </span>
                </div>
            </div>
        </div>

        <!-- DERNIERS RDV TERMINÉS -->
        <?php if(!empty($derniers_rdv)): ?>
        <div class="card animate-in">
            <div class="card-header">
                <h3><i class="fas fa-check-circle" style="color:var(--green);"></i> Dernières consultations</h3>
                <a href="historique_patient.php" class="see-all">Historique →</a>
            </div>
            <?php foreach($derniers_rdv as $rdv): ?>
                <a href="detail_rdv.php?id=<?=$rdv['id']?>" class="rdv-item">
                    <div class="rdv-time">
                        <div class="day"><?=(new DateTime($rdv['date_rdv']))->format('d')?></div>
                        <div class="date"><?=$jours_fr[(new DateTime($rdv['date_rdv']))->format('l')]??''?></div>
                        <div class="hour"><?=substr($rdv['heure_rdv'],0,5)?></div>
                    </div>
                    <div class="rdv-info">
                        <div class="rdv-doctor">Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?></div>
                        <div class="rdv-specialite"><?=htmlspecialchars($rdv['specialite'])?></div>
                        <div class="rdv-motif"><?=htmlspecialchars(mb_substr($rdv['motif'],0,50)).(mb_strlen($rdv['motif'])>50?'...':'')?></div>
                    </div>
                    <div class="rdv-status">
                        <span class="badge termine">
                            <i class="fas fa-check-circle"></i> Terminé
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
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
            foreach(array_reverse($stats_mensuelles) as $row) {
                $mois_labels[] = $row['mois'];
                $termines_data[] = (int)$row['termines'];
                $annules_data[] = (int)$row['annules'];
            }
            echo json_encode([
                'labels' => $mois_labels,
                'termines' => $termines_data,
                'annules' => $annules_data
            ]);
        ?>;
        
        const ctx1 = document.getElementById('evolutionChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: evoData.labels,
                datasets: [
                    {
                        label: 'Terminés',
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
                        borderDash: [5, 5],
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
                            font: { size: 11, weight: '500' },
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

        // --- Spécialité Chart (Pie) ---
        const specData = <?php 
            $spec_labels = [];
            $spec_values = [];
            $spec_colors = ['#0077B6','#7209B7','#F77F00','#006D77','#3A0CA3','#0D9488','#7C3AED','#2563EB','#BE185D','#059669'];
            $i = 0;
            foreach($stats_specialites as $row) {
                $spec_labels[] = $row['specialite'];
                $spec_values[] = (int)$row['total'];
                $i++;
            }
            echo json_encode([
                'labels' => $spec_labels,
                'values' => $spec_values,
                'colors' => array_slice($spec_colors, 0, count($spec_labels))
            ]);
        ?>;
        
        const ctx2 = document.getElementById('specialiteChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: specData.labels,
                datasets: [{
                    data: specData.values,
                    backgroundColor: specData.colors,
                    borderColor: '#fff',
                    borderWidth: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 12,
                            font: { size: 10, weight: '500' },
                            boxWidth: 8,
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.9)',
                        titleColor: '#fff',
                        bodyColor: '#E2E8F0',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a,b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
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