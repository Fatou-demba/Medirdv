<?php
// ================================================================
// dashboard_admin.php — Tableau de bord Admin (Design Figma)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn() || getRole() !== 'admin') {
    redirect('login.php');
}

$admin_id = $_SESSION['user_id'];

// Récupérer les infos admin
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// STATISTIQUES AVANCÉES
// ================================================================

// Statistiques globales
$stats = [
    'patients' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif'")->fetchColumn(),
    'patients_mois' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif' AND date_inscription >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
    'medecins' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide'")->fetchColumn(),
    'medecins_mois' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide' AND date_inscription >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
    'medecins_attente' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'en_attente'")->fetchColumn(),
    'rdv_aujourdhui' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE date_rdv = CURDATE()")->fetchColumn(),
    'rdv_attente_aujourdhui' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE date_rdv = CURDATE() AND statut = 'en_attente'")->fetchColumn(),
    'consultations_total' => $pdo->query("SELECT COUNT(*) FROM consultations")->fetchColumn(),
    'consultations_mois' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE date_consultation >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
];
//tester

// RDV par mois (12 derniers mois)
$rdv_par_mois = $pdo->query("
    SELECT 
        DATE_FORMAT(date_rdv, '%b') AS mois,
        MONTH(date_rdv) AS mois_num,
        COUNT(*) AS total
    FROM rendezvous 
    WHERE date_rdv >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_rdv, '%b'), MONTH(date_rdv)
    ORDER BY mois_num ASC
")->fetchAll(PDO::FETCH_ASSOC);

// RDV par spécialité
$rdv_par_specialite = $pdo->query("
    SELECT 
        m.specialite,
        COUNT(*) AS total,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM rendezvous)) AS pourcentage
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    WHERE r.statut = 'termine'
    GROUP BY m.specialite
    ORDER BY total DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

// Derniers RDV
$derniers_rdv = $pdo->query("
    SELECT r.*, 
           p.nom AS p_nom, p.prenom AS p_prenom,
           m.nom AS m_nom, m.prenom AS m_prenom
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    JOIN medecins m ON m.id = r.id_medecin
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Derniers inscrits
$derniers_inscrits = $pdo->query("
    SELECT 'patient' AS type, id, nom, prenom, date_inscription, 'Patient' AS role
    FROM patients WHERE statut = 'actif'
    UNION ALL
    SELECT 'medecin' AS type, id, nom, prenom, date_inscription, 'Médecin' AS role
    FROM medecins WHERE statut = 'valide'
    ORDER BY date_inscription DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Notifications non lues
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type_user = 'admin' AND lu = 0")->fetchColumn();

// Jours de la semaine en français
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];

$date_actuelle = date('d/m/Y');
$jour_actuel = $jours_fr[date('l')];

$initiales = strtoupper(substr($admin['nom'], 0, 1));

// Couleurs spécialités
$couleurs_spec = [
    'Cardiologie' => '#0077B6',
    'Pédiatrie' => '#7209B7',
    'Dermatologie' => '#F77F00',
    'Gynécologie' => '#006D77',
    'Neurologie' => '#3A0CA3',
    'Ophtalmologie' => '#0D9488',
    'ORL' => '#7C3AED',
    'Urologie' => '#2563EB',
    'Psychiatrie' => '#BE185D',
    'Dentiste' => '#059669',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Dashboard Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--teal:#006D77;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F0F4FF;display:flex;min-height:100vh}

        /* ===== SIDEBAR ===== */
        .admin-sidebar{width:220px;background:var(--dark);flex-shrink:0;display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;overflow-y:auto;transition:transform 0.3s ease}
        .sidebar-logo{padding:1.2rem 1.4rem;border-bottom:1px solid rgba(255,255,255,.1)}
        .sidebar-logo .logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.2rem;color:#fff}
        .sidebar-logo .logo span{color:var(--green)}
        .sidebar-logo .role{font-size:.72rem;color:#94A3B8;margin-top:.2rem}
        .sidebar-nav{padding:.8rem 0;flex:1}
        .nav-section{font-size:.65rem;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.8px;padding:.5rem 1.4rem;margin-top:.4rem}
        .nav-item{display:flex;align-items:center;gap:.7rem;padding:.65rem 1.4rem;color:#94A3B8;font-size:.85rem;font-weight:500;cursor:pointer;transition:.15s;text-decoration:none;border:none;background:none;width:100%;text-align:left}
        .nav-item:hover{color:#fff;background:rgba(255,255,255,.07)}
        .nav-item.active{color:#fff;background:rgba(0,119,182,.3);border-right:3px solid var(--blue)}
        .nav-item .icon{font-size:1rem;width:20px;text-align:center}
        .nav-item .badge{margin-left:auto;background:var(--red);color:#fff;border-radius:10px;padding:.05rem .45rem;font-size:.67rem;font-weight:700}
        .sidebar-footer{padding:1rem 1.4rem;border-top:1px solid rgba(255,255,255,.1)}
        .admin-info{display:flex;align-items:center;gap:.6rem}
        .admin-av{width:32px;height:32px;border-radius:8px;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#fff;font-weight:700;flex-shrink:0}
        .admin-name{font-size:.8rem;font-weight:600;color:#fff}
        .admin-role{font-size:.68rem;color:#94A3B8}

        /* ===== MAIN ===== */
        .main-content{margin-left:220px;flex:1;display:flex;flex-direction:column;min-height:100vh}

        /* ===== TOP BAR ===== */
        .top-bar{background:#fff;padding:.9rem 1.8rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--lgray);position:sticky;top:0;z-index:50;box-shadow:0 1px 6px rgba(0,0,0,.05);flex-wrap:wrap;gap:.5rem}
        .top-bar h2{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark)}
        .top-right{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
        .notif-btn{position:relative;cursor:pointer;color:var(--gray);font-size:1.1rem;text-decoration:none}
        .notif-btn .nb{position:absolute;top:-6px;right:-7px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700}
        .date-badge{background:var(--light);color:var(--blue);padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:600}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:var(--dark);cursor:pointer;padding:.3rem}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:99}

        /* ===== PAGE CONTENT ===== */
        .page-content{padding:1.5rem 1.8rem;flex:1}

        /* ===== ALERT BANNER ===== */
        .alert-banner{background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:10px;padding:.8rem 1.1rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
        .alert-banner .icon{font-size:1.2rem}
        .alert-banner .txt{flex:1;font-size:.83rem;color:#92400E;min-width:200px}
        .alert-banner .txt strong{font-weight:700}
        .alert-btn{padding:.4rem .9rem;background:var(--orange);color:#fff;border:none;border-radius:7px;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;text-decoration:none}

        /* ===== METRICS ===== */
        .metrics-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
        .metric-card{background:#fff;border-radius:14px;padding:1.2rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.05);position:relative;overflow:hidden}
        .metric-card::before{content:'';position:absolute;top:0;right:0;width:60px;height:60px;border-radius:0 0 0 60px;opacity:.1}
        .mc-blue::before{background:var(--blue)}
        .mc-green::before{background:var(--green)}
        .mc-orange::before{background:var(--orange)}
        .mc-purple::before{background:var(--purple)}
        .mc-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:.8rem}
        .mc-blue .mc-icon{background:#EFF6FF}
        .mc-green .mc-icon{background:#F0FDF4}
        .mc-orange .mc-icon{background:#FFF7ED}
        .mc-purple .mc-icon{background:#F3E8FF}
        .mc-num{font-family:'Sora',sans-serif;font-size:1.9rem;font-weight:800;color:var(--dark);line-height:1}
        .mc-label{font-size:.78rem;color:var(--gray);margin-top:.3rem;font-weight:500}
        .mc-trend{font-size:.73rem;margin-top:.4rem;display:flex;align-items:center;gap:.3rem;flex-wrap:wrap}
        .trend-up{color:var(--green)}
        .trend-down{color:var(--red)}

        /* ===== CHARTS ===== */
        .charts-row{display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem}
        .chart-card{background:#fff;border-radius:14px;padding:1.2rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .chart-card h4{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray)}

        /* BAR CHART */
        .bar-chart{display:flex;align-items:flex-end;gap:.5rem;height:140px;margin-top:.5rem}
        .bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:.3rem}
        .bar{width:100%;border-radius:6px 6px 0 0;min-height:4px;transition:.3s}
        .bar-label{font-size:.65rem;color:var(--gray);font-weight:600}
        .bar-val{font-size:.65rem;font-weight:700;color:var(--dark)}

        /* DONUT CHART */
        .donut-wrap{display:flex;flex-direction:column;align-items:center;gap:.8rem}
        .donut{width:120px;height:120px;border-radius:50%;position:relative}
        .donut-hole{position:absolute;width:70px;height:70px;background:#fff;border-radius:50%;top:50%;left:50%;transform:translate(-50%,-50%);display:flex;align-items:center;justify-content:center;flex-direction:column}
        .donut-hole .num{font-family:'Sora',sans-serif;font-size:1rem;font-weight:800;color:var(--dark)}
        .donut-hole .lbl{font-size:.55rem;color:var(--gray)}
        .donut-legend{display:grid;grid-template-columns:1fr 1fr;gap:.3rem .8rem;width:100%}
        .dl-item{display:flex;align-items:center;gap:.3rem;font-size:.72rem;color:var(--gray)}
        .dl-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}

        /* ===== TABLES ===== */
        .tables-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .table-card{background:#fff;border-radius:14px;padding:1.2rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .table-card .tc-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray)}
        .table-card h4{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:var(--dark)}
        .see-all{font-size:.78rem;color:var(--blue);font-weight:600;cursor:pointer;background:none;border:none;text-decoration:none}
        .see-all:hover{color:var(--dark)}

        .data-table{width:100%;border-collapse:collapse;font-size:.8rem}
        .data-table th{padding:.4rem .5rem;text-align:left;color:var(--gray);font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--lgray)}
        .data-table td{padding:.45rem .5rem;border-bottom:1px solid #F8FAFC;color:#334155;vertical-align:middle}
        .data-table tr:last-child td{border:none}
        .data-table tr:hover td{background:#F8FAFF}
        .badge-sm{padding:.18rem .55rem;border-radius:15px;font-size:.7rem;font-weight:700;display:inline-block}
        .b-confirme{background:#F0FDF4;color:#15803D}
        .b-attente{background:#FFF7ED;color:#C2410C}
        .b-refuse{background:#FFF5F5;color:var(--red)}
        .b-termine{background:#EFF6FF;color:var(--blue)}
        .b-valide{background:#F0FDF4;color:#15803D}
        .b-en-attente{background:#FFF7ED;color:#C2410C}
        .action-btn{padding:.25rem .6rem;border-radius:5px;font-size:.7rem;font-weight:600;cursor:pointer;border:none;margin-right:.2rem}
        .ab-blue{background:var(--blue);color:#fff}
        .ab-green{background:var(--green);color:#fff}
        .ab-red{background:transparent;color:var(--red);border:1px solid var(--red)}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:15px 1.8rem;text-align:center;font-size:.78rem;flex-shrink:0}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .metrics-grid{grid-template-columns:repeat(2,1fr)}
            .charts-row{grid-template-columns:1fr}
            .tables-row{grid-template-columns:1fr}
        }

        @media(max-width:768px){
            .admin-sidebar{transform:translateX(-100%)}
            .admin-sidebar.open{transform:translateX(0)}
            .sidebar-overlay.active{display:block}
            .main-content{margin-left:0}
            .hamburger{display:block}
            .top-bar{padding:.7rem 1rem}
            .top-bar h2{font-size:.95rem}
            .page-content{padding:1rem}
            .metrics-grid{grid-template-columns:1fr 1fr;gap:.7rem}
            .metric-card{padding:.8rem}
            .metric-card .mc-num{font-size:1.4rem}
            .data-table{font-size:.72rem}
            .data-table th,.data-table td{padding:.3rem .3rem}
            .badge-sm{font-size:.6rem;padding:.1rem .4rem}
            .alert-banner{flex-direction:column;text-align:center}
            .alert-banner .txt{text-align:center}
        }

        @media(max-width:480px){
            .metrics-grid{grid-template-columns:1fr 1fr;gap:.5rem}
            .metric-card{padding:.6rem;text-align:center}
            .metric-card .mc-icon{width:32px;height:32px;font-size:.9rem;margin:0 auto .5rem}
            .metric-card .mc-num{font-size:1.2rem}
            .metric-card .mc-label{font-size:.65rem}
            .metric-card .mc-trend{font-size:.6rem;justify-content:center}
            .top-right .date-badge{font-size:.65rem;padding:.2rem .5rem}
            .bar-chart{height:100px;gap:.3rem}
            .bar-val{font-size:.55rem}
            .bar-label{font-size:.55rem}
            .donut{width:90px;height:90px}
            .donut-hole{width:55px;height:55px}
            .donut-hole .num{font-size:.8rem}
            .table-card{padding:.8rem}
            .table-card h4{font-size:.8rem}
            .data-table{font-size:.65rem}
            .data-table th,.data-table td{padding:.2rem .25rem}
            .action-btn{padding:.15rem .4rem;font-size:.6rem}
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR OVERLAY ===== -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<div class="admin-sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo">Medi<span>RDV</span></div>
        <div class="role">🛡 Espace Administrateur</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="dashboard_admin.php" class="nav-item active">
            <span class="icon">📊</span> Dashboard
        </a>
        <a href="gestion_users.php" class="nav-item">
            <span class="icon">👥</span> Utilisateurs
            <span class="badge"><?=$stats['medecins_attente']?></span>
        </a>
        <a href="validation_medecins.php" class="nav-item">
            <span class="icon">✅</span> Validation Médecins
            <span class="badge"><?=$stats['medecins_attente']?></span>
        </a>
        <a href="gestion_rdv.php" class="nav-item">
            <span class="icon">📅</span> Gestion RDV
        </a>
        <div class="nav-section">Sécurité</div>
        <a href="securite.php" class="nav-item">
            <span class="icon">🔒</span> Journal Accès
        </a>
        <a href="securite.php" class="nav-item">
            <span class="icon">📋</span> Rapports
        </a>
        <div class="nav-section">Compte</div>
        <a href="profil_admin.php" class="nav-item">
            <span class="icon">⚙️</span> Paramètres
        </a>
        <a href="logout.php" class="nav-item">
            <span class="icon">🚪</span> Déconnexion
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-av"><?=$initiales?></div>
            <div>
                <div class="admin-name"><?=htmlspecialchars($admin['nom'])?></div>
                <div class="admin-role">Administrateur système</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">

    <!-- ===== TOP BAR ===== -->
    <div class="top-bar">
        <div style="display:flex;align-items:center;gap:1rem;">
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2>📊 Tableau de Bord</h2>
        </div>
        <div class="top-right">
            <div class="date-badge">📅 <?=$jour_actuel?> <?=$date_actuelle?></div>
            <a href="notifications.php" class="notif-btn">
                🔔
                <?php if($notif_count > 0): ?>
                    <div class="nb"><?=$notif_count?></div>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- ===== PAGE CONTENT ===== -->
    <div class="page-content">

        <!-- ALERT BANNER -->
        <?php if($stats['medecins_attente'] > 0): ?>
        <div class="alert-banner">
            <div class="icon">⚠️</div>
            <div class="txt">
                <strong><?=$stats['medecins_attente']?> médecin<?=$stats['medecins_attente']>1?'s':''?></strong> en attente de validation.
                Action requise.
            </div>
            <a href="validation_medecins.php" class="alert-btn">Voir maintenant →</a>
        </div>
        <?php endif; ?>

        <!-- METRICS -->
        <div class="metrics-grid">
            <div class="metric-card mc-blue">
                <div class="mc-icon">👥</div>
                <div class="mc-num"><?=$stats['patients']?></div>
                <div class="mc-label">Total Patients</div>
                <div class="mc-trend trend-up">▲ +<?=$stats['patients_mois']?> ce mois</div>
            </div>
            <div class="metric-card mc-green">
                <div class="mc-icon">👨‍⚕️</div>
                <div class="mc-num"><?=$stats['medecins']?></div>
                <div class="mc-label">Médecins actifs</div>
                <div class="mc-trend trend-up">▲ +<?=$stats['medecins_mois']?> ce mois</div>
            </div>
            <div class="metric-card mc-orange">
                <div class="mc-icon">📅</div>
                <div class="mc-num"><?=$stats['rdv_aujourdhui']?></div>
                <div class="mc-label">RDV aujourd'hui</div>
                <div class="mc-trend">
                    <span style="color:var(--orange)">● <?=$stats['rdv_attente_aujourdhui']?> en attente</span>
                </div>
            </div>
            <div class="metric-card mc-purple">
                <div class="mc-icon">🏥</div>
                <div class="mc-num"><?=$stats['consultations_total']?></div>
                <div class="mc-label">Consultations total</div>
                <div class="mc-trend trend-up">▲ +<?=$stats['consultations_mois']?> ce mois</div>
            </div>
        </div>

        <!-- CHARTS -->
        <div class="charts-row">
            <!-- BAR CHART -->
            <div class="chart-card">
                <h4>📈 RDV par mois — 2024</h4>
                <div class="bar-chart">
                    <?php 
                    $max_rdv = max(array_column($rdv_par_mois, 'total')) ?: 1;
                    $couleurs_bar = ['#0077B6','#0077B6','#0077B6','#0077B6','#2DC653','#2DC653','#0077B6','#0077B6','#2DC653','#F77F00','#0077B6','#0077B6'];
                    $i = 0;
                    foreach($rdv_par_mois as $row): 
                        $hauteur = ($row['total'] / $max_rdv) * 100;
                        $couleur = $couleurs_bar[$i] ?? '#0077B6';
                    ?>
                        <div class="bar-wrap">
                            <div class="bar-val"><?=$row['total']?></div>
                            <div class="bar" style="height:<?=max($hauteur, 5)?>%;background:<?=$couleur?>"></div>
                            <div class="bar-label"><?=$row['mois']?></div>
                        </div>
                    <?php 
                        $i++;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- DONUT CHART -->
            <div class="chart-card">
                <h4>🏥 RDV par spécialité</h4>
                <div class="donut-wrap">
                    <?php 
                    $total_rdv = array_sum(array_column($rdv_par_specialite, 'total'));
                    $conic_gradients = [];
                    $pourcentages = [];
                    $angle = 0;
                    $couleurs_donut = ['#0077B6','#2DC653','#F77F00','#7209B7'];
                    $idx = 0;
                    foreach($rdv_par_specialite as $row):
                        $pct = $total_rdv > 0 ? round(($row['total'] / $total_rdv) * 100) : 0;
                        $pourcentages[] = $pct;
                        $conic_gradients[] = $couleurs_donut[$idx] . ' ' . $angle . '% ' . ($angle + $pct) . '%';
                        $angle += $pct;
                        $idx++;
                    endforeach;
                    ?>
                    <div class="donut" style="background:conic-gradient(<?=implode(', ', $conic_gradients)?>)">
                        <div class="donut-hole">
                            <div class="num"><?=$total_rdv?></div>
                            <div class="lbl">Total RDV</div>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <?php 
                        $idx = 0;
                        foreach($rdv_par_specialite as $row):
                            $pct = $pourcentages[$idx] ?? 0;
                        ?>
                            <div class="dl-item">
                                <div class="dl-dot" style="background:<?=$couleurs_donut[$idx]?>"></div>
                                <?=htmlspecialchars($row['specialite'])?> <?=$pct?>%
                            </div>
                        <?php 
                            $idx++;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLES -->
        <div class="tables-row">
            <!-- DERNIERS RDV -->
            <div class="table-card">
                <div class="tc-head">
                    <h4>📅 Derniers RDV</h4>
                    <a href="gestion_rdv.php" class="see-all">Voir tout →</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>Patient</th><th>Médecin</th><th>Date</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($derniers_rdv)): ?>
                            <tr><td colspan="4" style="text-align:center;color:var(--gray);padding:1rem;">Aucun RDV</td></tr>
                        <?php else: ?>
                            <?php foreach($derniers_rdv as $rdv): ?>
                                <tr>
                                    <td><strong><?=htmlspecialchars($rdv['p_prenom'].' '.$rdv['p_nom'])?></strong></td>
                                    <td>Dr. <?=htmlspecialchars($rdv['m_prenom'])?></td>
                                    <td><?=date('d/m', strtotime($rdv['date_rdv']))?></td>
                                    <td>
                                        <span class="badge-sm <?php
                                            if($rdv['statut'] === 'confirme') echo 'b-confirme';
                                            elseif($rdv['statut'] === 'en_attente') echo 'b-attente';
                                            elseif($rdv['statut'] === 'refuse') echo 'b-refuse';
                                            else echo 'b-termine';
                                        ?>">
                                            <?php if($rdv['statut'] === 'confirme'): ?>✅ OK
                                            <?php elseif($rdv['statut'] === 'en_attente'): ?>⏳ Att.
                                            <?php elseif($rdv['statut'] === 'refuse'): ?>❌ Ref.
                                            <?php else: ?>📋 Term.
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- DERNIERS INSCRITS -->
            <div class="table-card">
                <div class="tc-head">
                    <h4>👤 Derniers inscrits</h4>
                    <a href="gestion_users.php" class="see-all">Voir tout →</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>Nom</th><th>Rôle</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($derniers_inscrits)): ?>
                            <tr><td colspan="4" style="text-align:center;color:var(--gray);padding:1rem;">Aucun utilisateur</td></tr>
                        <?php else: ?>
                            <?php foreach($derniers_inscrits as $user): ?>
                                <tr>
                                    <td><strong><?=htmlspecialchars($user['prenom'].' '.$user['nom'])?></strong></td>
                                    <td>
                                        <?php if($user['type'] === 'medecin'): ?>
                                            <span style="color:var(--orange);font-weight:700">👨‍⚕️ Médecin</span>
                                        <?php else: ?>
                                            Patient
                                        <?php endif; ?>
                                    </td>
                                    <td><?=date('d/m', strtotime($user['date_inscription']))?></td>
                                    <td>
                                        <?php if($user['type'] === 'medecin'): ?>
                                            <a href="validation_medecins.php" class="action-btn ab-green">Valider</a>
                                        <?php else: ?>
                                            <button class="action-btn ab-blue">✓</button>
                                            <button class="action-btn ab-red">✗</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ===== FOOTER ===== -->
    <footer>
        <p>
            <strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025 
            &nbsp;|&nbsp; Page designer par : <strong>Aicha Djigo (E7)</strong> — dashboard_admin.php
        </p>
    </footer>
</div>

<!-- ============================================================
   SCRIPTS
============================================================ -->
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }
</script>
</body>
</html>