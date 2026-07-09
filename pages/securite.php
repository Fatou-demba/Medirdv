<?php
// ================================================================
// securite.php — Journal d'accès et sécurité (Admin)
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
// TRAITEMENT DES ACTIONS
// ================================================================

// Bloquer une IP (simulé - ajout dans une table ou session)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bloquer_ip') {
    $ip = trim($_POST['ip'] ?? '');
    if (!empty($ip)) {
        // Simulation : on stocke dans la session
        if (!isset($_SESSION['ips_bloquees'])) {
            $_SESSION['ips_bloquees'] = [];
        }
        if (!in_array($ip, $_SESSION['ips_bloquees'])) {
            $_SESSION['ips_bloquees'][] = $ip;
            $_SESSION['success'] = "✅ IP $ip bloquée avec succès !";
        } else {
            $_SESSION['error'] = "⚠️ IP $ip est déjà bloquée.";
        }
        redirect('securite.php');
    }
}

// Exporter CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("SELECT * FROM journal_acces ORDER BY date_action DESC");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=journal_acces_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'ID Utilisateur', 'Type', 'Action', 'ID Dossier', 'IP', 'Date']);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['id_user'],
            $log['type_user'],
            $log['action'],
            $log['id_dossier'],
            $log['ip_address'],
            $log['date_action']
        ]);
    }
    fclose($output);
    exit();
}

// Voir les détails d'une entrée
if (isset($_GET['voir']) && is_numeric($_GET['voir'])) {
    $id = (int)$_GET['voir'];
    $stmt = $pdo->prepare("SELECT * FROM journal_acces WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($detail) {
        // Stocker dans la session pour affichage
        $_SESSION['detail_log'] = $detail;
        redirect('securite.php#detail');
    }
}

// ================================================================
// STATISTIQUES
// ================================================================
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM journal_acces")->fetchColumn(),
    'normaux' => $pdo->query("SELECT COUNT(*) FROM journal_acces WHERE action NOT LIKE '%suspect%' AND action NOT LIKE '%tentative%'")->fetchColumn(),
    'suspects' => $pdo->query("SELECT COUNT(*) FROM journal_acces WHERE action LIKE '%suspect%' OR action LIKE '%tentative%'")->fetchColumn(),
    'dossiers' => $pdo->query("SELECT COUNT(*) FROM journal_acces WHERE action LIKE '%consultation%' OR action LIKE '%dossier%'")->fetchColumn(),
];

// ================================================================
// RÉCUPÉRATION DES ACCÈS
// ================================================================
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM journal_acces ORDER BY date_action DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$acces = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $pdo->query("SELECT COUNT(*) FROM journal_acces")->fetchColumn();
$total_pages = ceil($total / $limit);

// ================================================================
// IP SUSPECTE DYNAMIQUE
// ================================================================
// Chercher une IP suspecte dans les logs
$ip_suspecte = '192.168.1.45';
$nb_tentatives = 0;

$stmt = $pdo->prepare("
    SELECT ip_address, COUNT(*) as nb 
    FROM journal_acces 
    WHERE action LIKE '%tentative%' OR action LIKE '%échec%'
    GROUP BY ip_address 
    ORDER BY nb DESC 
    LIMIT 1
");
$stmt->execute();
$suspect = $stmt->fetch(PDO::FETCH_ASSOC);
if ($suspect) {
    $ip_suspecte = $suspect['ip_address'];
    $nb_tentatives = $suspect['nb'];
}

// Vérifier si l'IP est bloquée
$ip_bloquee = isset($_SESSION['ips_bloquees']) && in_array($ip_suspecte, $_SESSION['ips_bloquees']);

// Notifications
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type_user = 'admin' AND lu = 0")->fetchColumn();

$initiales = strtoupper(substr($admin['nom'], 0, 1));
$date_actuelle = date('d/m/Y');
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$jour_actuel = $jours_fr[date('l')];

// Récupérer les détails d'une entrée si disponible
$detail_log = $_SESSION['detail_log'] ?? null;
unset($_SESSION['detail_log']);

// Messages flash
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success']);
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Journal d'Accès Sécurité</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--teal:#006D77;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F0F4FF;display:flex;min-height:100vh}
        .admin-sidebar{width:220px;background:var(--dark);flex-shrink:0;position:fixed;top:0;left:0;height:100vh;z-index:100;display:flex;flex-direction:column;overflow-y:auto;transition:transform 0.3s ease}
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
        .admin-av{width:32px;height:32px;border-radius:8px;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#fff;font-weight:700}
        .main-content{margin-left:220px;flex:1;display:flex;flex-direction:column;min-height:100vh}
        .top-bar{background:#fff;padding:.9rem 1.8rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--lgray);box-shadow:0 1px 6px rgba(0,0,0,.05);flex-wrap:wrap;gap:.5rem}
        .top-bar h2{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark)}
        .top-right{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
        .notif-btn{position:relative;cursor:pointer;color:var(--gray);font-size:1.1rem;text-decoration:none}
        .notif-btn .nb{position:absolute;top:-6px;right:-7px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700}
        .date-badge{background:var(--light);color:var(--blue);padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:600}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:var(--dark);cursor:pointer;padding:.3rem}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:99}
        .page-content{padding:1.5rem 1.8rem;flex:1}

        /* ALERT */
        .alert-susp{background:#FFF5F5;border:1.5px solid #FECACA;border-radius:12px;padding:1rem 1.2rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
        .alert-susp .icon{font-size:1.4rem}
        .alert-susp .txt{flex:1;min-width:200px}
        .alert-susp .txt h5{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:800;color:var(--red);margin-bottom:.2rem}
        .alert-susp .txt p{font-size:.8rem;color:#7F1D1D}
        .alert-btn{padding:.5rem 1rem;background:var(--red);color:#fff;border:none;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-block}
        .alert-btn.bloque{background:var(--gray);cursor:not-allowed}

        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

        .stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .st-card{background:#fff;border-radius:12px;padding:1rem 1.3rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.04);display:flex;align-items:center;gap:.8rem;flex:1;min-width:140px}
        .st-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .st-num{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;color:var(--dark)}
        .st-lbl{font-size:.73rem;color:var(--gray)}

        .filter-bar{background:#fff;border-radius:12px;padding:.9rem 1.1rem;border:1px solid var(--lgray);margin-bottom:1.2rem;display:flex;gap:.7rem;align-items:center;flex-wrap:wrap;box-shadow:0 2px 6px rgba(0,0,0,.04)}
        .filter-bar select,.filter-bar input{padding:.5rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.83rem;outline:none;background:#fff;font-family:'DM Sans',sans-serif}
        .filter-bar select:focus,.filter-bar input:focus{border-color:var(--teal)}
        .export-btn{padding:.5rem 1.2rem;background:var(--teal);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
        .export-btn:hover{background:var(--dark);color:#fff}

        .table-card{background:#fff;border-radius:14px;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden}
        .table-head{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.2rem;border-bottom:1px solid var(--lgray);background:#F8FAFC;flex-wrap:wrap;gap:.5rem}
        .table-head h4{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:var(--dark)}
        .data-table{width:100%;border-collapse:collapse;font-size:.8rem;overflow-x:auto;display:block}
        .data-table thead,.data-table tbody{display:table;width:100%;table-layout:fixed}
        .data-table th{padding:.55rem .8rem;text-align:left;color:var(--gray);font-weight:700;font-size:.7rem;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--lgray);background:#F8FAFC;white-space:nowrap}
        .data-table td{padding:.55rem .8rem;border-bottom:1px solid #F1F5F9;color:#334155;vertical-align:middle;word-break:break-word}
        .data-table tr:last-child td{border:none}
        .data-table tr:hover td{background:#F8FAFC}
        .data-table tr.suspect td{background:#FFF5F5}

        .badge{padding:.2rem .6rem;border-radius:10px;font-size:.7rem;font-weight:700;white-space:nowrap;display:inline-block}
        .b-consult{background:#EFF6FF;color:var(--blue)}
        .b-modif{background:#FFF7ED;color:#C2410C}
        .b-suppr{background:#FFF5F5;color:var(--red)}
        .b-connexion{background:#F0FDF4;color:#15803D}
        .b-suspect{background:#FFF5F5;color:var(--red);border:1px solid #FECACA;animation:pulse 1.5s infinite}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}

        .ip-code{font-family:'Courier New',monospace;font-size:.78rem;color:#334155;background:#F1F5F9;padding:.15rem .4rem;border-radius:4px}
        .action-view{padding:.28rem .65rem;background:var(--blue);color:#fff;border:none;border-radius:6px;font-size:.72rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block}
        .action-view:hover{background:var(--dark)}
        .action-view.danger{background:var(--red)}
        .action-view.danger:hover{background:#B91C1C}

        /* DETAIL MODAL */
        .detail-modal{background:#fff;border-radius:12px;border:1px solid var(--lgray);padding:1.2rem;margin-bottom:1rem;box-shadow:0 4px 20px rgba(0,0,0,.08);border-left:4px solid var(--blue)}
        .detail-modal h5{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:var(--dark);margin-bottom:.5rem}
        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem 1rem}
        .detail-grid .dg-item{display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #F8FAFC;font-size:.83rem}
        .detail-grid .dg-item .label{color:var(--gray);font-weight:500}
        .detail-grid .dg-item .value{font-weight:600;color:var(--dark)}

        .pagination{display:flex;justify-content:space-between;align-items:center;padding:.9rem 1.2rem;border-top:1px solid var(--lgray);background:#F8FAFC;flex-wrap:wrap;gap:.5rem}
        .page-info{font-size:.8rem;color:var(--gray)}
        .page-btns{display:flex;gap:.3rem;flex-wrap:wrap}
        .pg{width:32px;height:32px;border-radius:7px;border:1px solid var(--lgray);background:#fff;font-size:.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#334155;text-decoration:none}
        .pg.active{background:var(--blue);color:#fff;border-color:var(--blue)}
        .pg:hover:not(.active){background:var(--light)}

        footer{background:var(--dark);color:#94A3B8;padding:15px 1.8rem;text-align:center;font-size:.78rem;flex-shrink:0}
        footer strong{color:#fff}

        @media(max-width:768px){
            .admin-sidebar{transform:translateX(-100%)}
            .admin-sidebar.open{transform:translateX(0)}
            .sidebar-overlay.active{display:block}
            .main-content{margin-left:0}
            .hamburger{display:block}
            .top-bar{padding:.7rem 1rem}
            .page-content{padding:1rem}
            .stats-row{gap:.7rem}
            .st-card{min-width:130px;padding:.8rem 1rem}
            .st-num{font-size:1.2rem}
            .filter-bar{flex-direction:column;align-items:stretch}
            .export-btn{margin-left:0}
            .data-table{font-size:.72rem}
            .data-table th,.data-table td{padding:.4rem .5rem}
            .badge{font-size:.6rem;padding:.15rem .4rem}
            .pagination{flex-direction:column;text-align:center}
            .alert-susp{flex-direction:column;text-align:center}
            .detail-grid{grid-template-columns:1fr}
        }
        @media(max-width:480px){
            .stats-row{flex-direction:column}
            .st-card{min-width:100%}
            .top-right .date-badge{font-size:.65rem;padding:.2rem .5rem}
            .table-head{flex-direction:column;text-align:center}
            .data-table{font-size:.65rem}
            .data-table th,.data-table td{padding:.3rem .3rem}
            .pg{width:28px;height:28px;font-size:.7rem}
            .ip-code{font-size:.65rem}
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo">Medi<span>RDV</span></div>
        <div class="role">🛡 Administrateur</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="dashboard_admin.php" class="nav-item">
            <span class="icon">📊</span>Dashboard
        </a>
        <a href="gestion_users.php" class="nav-item">
            <span class="icon">👥</span>Utilisateurs
            <span class="badge">3</span>
        </a>
        <a href="validation_medecins.php" class="nav-item">
            <span class="icon">✅</span>Validation Médecins
            <span class="badge">2</span>
        </a>
        <a href="gestion_rdv.php" class="nav-item">
            <span class="icon">📅</span>Gestion RDV
        </a>
        <div class="nav-section">Sécurité</div>
        <a href="securite.php" class="nav-item active">
            <span class="icon">🔒</span>Journal Accès
        </a>
        <a href="rapport.php" class="nav-item">
             <span class="icon">🖨️</span>Rapport
     </a>
         <div class="nav-section">Compte</div>
        <a href="logout.php" class="nav-item">
            <span class="icon">🚪</span>Déconnexion
        </a>
    </nav>
    <div class="sidebar-footer">
        <div style="display:flex;align-items:center;gap:.6rem">
            <div class="admin-av"><?=$initiales?></div>
            <div>
                <div style="font-size:.8rem;font-weight:600;color:#fff"><?=htmlspecialchars($admin['nom'])?></div>
                <div style="font-size:.68rem;color:#94A3B8">Administrateur</div>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="top-bar">
        <div style="display:flex;align-items:center;gap:1rem;">
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2>🔒 Journal d'Accès — Sécurité RGPD</h2>
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

    <div class="page-content">

        <!-- MESSAGES FLASH -->
        <?php if($success): ?>
            <div class="alert alert-success">✅ <?=htmlspecialchars($success)?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error">⚠️ <?=htmlspecialchars($error)?></div>
        <?php endif; ?>

        <!-- ALERT SUSPECT -->
        <?php if($nb_tentatives > 0): ?>
        <div class="alert-susp">
            <div class="icon">🚨</div>
            <div class="txt">
                <h5>Activité suspecte détectée !</h5>
                <p>L'IP <strong><?=$ip_suspecte?></strong> a tenté de se connecter <strong><?=$nb_tentatives?></strong> fois en échec. 
                <?php if($ip_bloquee): ?>
                    <span style="color:var(--green);font-weight:700;">✅ IP déjà bloquée</span>
                <?php else: ?>
                    Compte temporairement bloqué.
                <?php endif; ?>
                </p>
            </div>
            <?php if(!$ip_bloquee): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="bloquer_ip">
                    <input type="hidden" name="ip" value="<?=$ip_suspecte?>">
                    <button type="submit" class="alert-btn">🚫 Bloquer cette IP</button>
                </form>
            <?php else: ?>
                <span class="alert-btn bloque">✅ IP bloquée</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- DÉTAIL D'UNE ENTRÉE -->
        <?php if($detail_log): ?>
        <div class="detail-modal" id="detail">
            <h5>📋 Détails de l'entrée #<?=$detail_log['id']?></h5>
            <div class="detail-grid">
                <div class="dg-item"><span class="label">ID Utilisateur</span><span class="value"><?=$detail_log['id_user'] ?: '—'?></span></div>
                <div class="dg-item"><span class="label">Type d'utilisateur</span><span class="value"><?=ucfirst($detail_log['type_user'] ?: '—')?></span></div>
                <div class="dg-item"><span class="label">Action</span><span class="value"><?=htmlspecialchars($detail_log['action'])?></span></div>
                <div class="dg-item"><span class="label">Dossier consulté</span><span class="value"><?=$detail_log['id_dossier'] ? '#' . $detail_log['id_dossier'] : '—'?></span></div>
                <div class="dg-item"><span class="label">Adresse IP</span><span class="value"><?=htmlspecialchars($detail_log['ip_address'] ?: '—')?></span></div>
                <div class="dg-item"><span class="label">Date & Heure</span><span class="value"><?=date('d/m/Y H:i:s', strtotime($detail_log['date_action']))?></span></div>
            </div>
            <div style="margin-top:0.8rem;display:flex;gap:.5rem;">
                <a href="securite.php" class="action-view">← Retour</a>
                <?php if($detail_log['ip_address']): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="bloquer_ip">
                    <input type="hidden" name="ip" value="<?=$detail_log['ip_address']?>">
                    <button type="submit" class="action-view danger">🚫 Bloquer cette IP</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-row">
            <div class="st-card">
                <div class="st-icon" style="background:#EFF6FF">📋</div>
                <div>
                    <div class="st-num" style="color:var(--blue)"><?=$stats['total']?></div>
                    <div class="st-lbl">Accès total</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#F0FDF4">✅</div>
                <div>
                    <div class="st-num" style="color:var(--green)"><?=$stats['normaux']?></div>
                    <div class="st-lbl">Accès normaux</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF5F5">🚨</div>
                <div>
                    <div class="st-num" style="color:var(--red)"><?=$stats['suspects']?></div>
                    <div class="st-lbl">Suspects</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF7ED">🔓</div>
                <div>
                    <div class="st-num" style="color:var(--orange)"><?=$stats['dossiers']?></div>
                    <div class="st-lbl">Dossiers consultés</div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-bar">
            <select id="filterType">
                <option value="">Tous les types</option>
                <option value="consultation">Consultation dossier</option>
                <option value="modification">Modification</option>
                <option value="suppression">Suppression</option>
                <option value="connexion">Connexion</option>
                <option value="suspect">Suspect</option>
            </select>
            <select id="filterRole">
                <option value="">Tous les rôles</option>
                <option value="patient">Patient</option>
                <option value="medecin">Médecin</option>
                <option value="admin">Admin</option>
            </select>
            <input type="date" id="filterDate" value="<?=date('Y-m-d')?>">
            <input type="text" id="filterSearch" placeholder="Rechercher utilisateur ou IP...">
            <a href="securite.php?export=csv" class="export-btn">📥 Exporter CSV</a>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-head">
                <h4>📋 Journal des accès</h4>
                <span style="font-size:.78rem;color:var(--gray)"><?=$total?> entrées • Aujourd'hui : <?=$pdo->query("SELECT COUNT(*) FROM journal_acces WHERE DATE(date_action) = CURDATE()")->fetchColumn()?></span>
            </div>

            <?php if(empty($acces)): ?>
                <div style="padding:2rem;text-align:center;color:var(--gray);">
                    <div style="font-size:3rem;margin-bottom:0.5rem;">📭</div>
                    <p>Aucune entrée de journal.</p>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:12%">Utilisateur</th>
                        <th style="width:8%">Rôle</th>
                        <th style="width:15%">Action</th>
                        <th style="width:15%">Dossier consulté</th>
                        <th style="width:12%">Adresse IP</th>
                        <th style="width:16%">Date & Heure</th>
                        <th style="width:8%">Statut</th>
                        <th style="width:9%">Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($acces as $log): 
                        $is_suspect = strpos($log['action'], 'suspect') !== false || strpos($log['action'], 'tentative') !== false;
                        $badge_class = strpos($log['action'], 'consultation') !== false ? 'b-consult' : 
                                      (strpos($log['action'], 'modification') !== false || strpos($log['action'], 'ajout') !== false ? 'b-modif' :
                                      (strpos($log['action'], 'suppression') !== false ? 'b-suppr' :
                                      (strpos($log['action'], 'connexion') !== false ? 'b-connexion' : 'b-connexion')));
                        if ($is_suspect) $badge_class = 'b-suspect';
                        
                        $user_label = $log['id_user'] ? 'ID ' . $log['id_user'] : 'Inconnu';
                        $user_style = $is_suspect ? 'color:var(--red);font-weight:700' : '';
                        
                        $role_colors = ['patient' => 'var(--blue)', 'medecin' => 'var(--orange)', 'admin' => 'var(--purple)'];
                        $role_color = $role_colors[$log['type_user']] ?? 'var(--gray)';
                    ?>
                    <tr class="<?=$is_suspect?'suspect':''?>">
                        <td style="<?=$is_suspect?'color:var(--red);font-weight:700':'color:var(--gray)'?>"><?=$log['id']?></td>
                        <td><strong style="<?=$user_style?>"><?=$user_label?></strong></td>
                        <td>
                            <?php if($log['type_user']): ?>
                                <span style="color:<?=$role_color?>;font-weight:600;font-size:.75rem"><?=ucfirst($log['type_user'])?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?=$badge_class?>"><?=htmlspecialchars($log['action'])?></span></td>
                        <td><?=$log['id_dossier'] ? 'Dossier #' . $log['id_dossier'] : '—'?></td>
                        <td><span class="ip-code <?=$is_suspect?'style="color:var(--red)"':''?>"><?=htmlspecialchars($log['ip_address'] ?? '—')?></span></td>
                        <td><?=date('d/m/Y — H:i:s', strtotime($log['date_action']))?></td>
                        <td><span class="badge <?=$is_suspect?'b-suspect':'b-connexion'?>"><?=$is_suspect?'Suspect':'Normal'?></span></td>
                        <td>
                            <a href="securite.php?voir=<?=$log['id']?>" class="action-view" title="Voir les détails">
                                👁 Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Affichage <?=$offset+1?>-<?=min($offset+$limit, $total)?> sur <?=$total?> entrées</span>
                <div class="page-btns">
                    <?php if($page > 1): ?>
                        <a href="?page=<?=$page-1?>" class="pg">←</a>
                    <?php endif; ?>
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?page=<?=$i?>" class="pg <?=$i===$page?'active':''?>"><?=$i?></a>
                    <?php endfor; ?>
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?=$page+1?>" class="pg">→</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p><strong>MediRDV</strong> — UNCHK | L3 IDA 2024-2025 &nbsp;|&nbsp; Page designer par : <strong>Diynaba Diallo (E8)</strong> — securite.php</p>
    </footer>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }

    // Filtres dynamiques (recherche côté client)
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('filterSearch');
        const filterType = document.getElementById('filterType');
        const filterRole = document.getElementById('filterRole');
        const filterDate = document.getElementById('filterDate');
        
        function filterTable() {
            const search = searchInput.value.toLowerCase();
            const type = filterType.value.toLowerCase();
            const role = filterRole.value.toLowerCase();
            const date = filterDate.value;
            
            const rows = document.querySelectorAll('.data-table tbody tr');
            rows.forEach(row => {
                let show = true;
                const text = row.textContent.toLowerCase();
                const roleText = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const dateText = row.querySelector('td:nth-child(7)')?.textContent || '';
                const actionText = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                
                if (search && !text.includes(search)) show = false;
                if (type && !actionText.includes(type)) show = false;
                if (role && !roleText.includes(role)) show = false;
                if (date && !dateText.includes(date)) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        searchInput.addEventListener('input', filterTable);
        filterType.addEventListener('change', filterTable);
        filterRole.addEventListener('change', filterTable);
        filterDate.addEventListener('change', filterTable);
    });
</script>
</body>
</html>
