<?php
// ================================================================
// gestion_rdv.php — Gestion des rendez-vous (Admin)
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
// STATISTIQUES
// ================================================================
$stats = [
    'aujourdhui' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE date_rdv = CURDATE()")->fetchColumn(),
    'en_attente' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE statut = 'en_attente'")->fetchColumn(),
    'confirme' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE statut = 'confirme'")->fetchColumn(),
    'annule' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE statut = 'annule'")->fetchColumn(),
    'total' => $pdo->query("SELECT COUNT(*) FROM rendezvous")->fetchColumn(),
];

// ================================================================
// FILTRES ET PAGINATION
// ================================================================
$filtre_statut = $_GET['statut'] ?? 'tous';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Construction de la requête
$sql = "SELECT r.*, 
        p.nom AS p_nom, p.prenom AS p_prenom, p.email AS p_email,
        m.nom AS m_nom, m.prenom AS m_prenom, m.specialite
        FROM rendezvous r
        JOIN patients p ON p.id = r.id_patient
        JOIN medecins m ON m.id = r.id_medecin";

$where = [];
$params = [];

if ($filtre_statut !== 'tous') {
    $where[] = "r.statut = :statut";
    $params[':statut'] = $filtre_statut;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY r.date_rdv DESC, r.heure_rdv DESC LIMIT :limit OFFSET :offset";

// Compter le total
$count_sql = "SELECT COUNT(*) FROM rendezvous r";
if (!empty($where)) {
    $count_sql .= " WHERE " . implode(" AND ", $where);
}
$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total = $count_stmt->fetchColumn();

// Récupérer les données
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total / $limit);

// ================================================================
// STATUTS DES ONGLETS
// ================================================================
$onglets = [
    'tous' => ['label' => '📋 Tous', 'count' => $stats['total']],
    'en_attente' => ['label' => '⏳ En attente', 'count' => $stats['en_attente']],
    'confirme' => ['label' => '✅ Confirmés', 'count' => $stats['confirme']],
    'annule' => ['label' => '❌ Annulés', 'count' => $stats['annule']],
];

// ================================================================
// TRAITEMENT DES ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'annuler') {
        $rdv_id = (int)$_POST['rdv_id'];
        $stmt = $pdo->prepare("UPDATE rendezvous SET statut = 'annule' WHERE id = :id");
        if ($stmt->execute([':id' => $rdv_id])) {
            $_SESSION['success'] = '✅ RDV annulé avec succès.';
        } else {
            $_SESSION['error'] = '❌ Erreur lors de l\'annulation.';
        }
        redirect('gestion_rdv.php');
    }
}

// Notifications
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type_user = 'admin' AND lu = 0")->fetchColumn();

$initiales = strtoupper(substr($admin['nom'], 0, 1));
$date_actuelle = date('d/m/Y');
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$jour_actuel = $jours_fr[date('l')];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Gestion RDV Admin</title>
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
        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

        /* STATS */
        .stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .st-card{background:#fff;border-radius:12px;padding:1rem 1.3rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.04);display:flex;align-items:center;gap:.8rem;flex:1;min-width:140px}
        .st-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .st-num{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;color:var(--dark)}
        .st-lbl{font-size:.73rem;color:var(--gray)}

        /* TABS */
        .tabs{display:flex;gap:.3rem;background:#fff;border-radius:12px;padding:.3rem;margin-bottom:1.2rem;border:1px solid var(--lgray);flex-wrap:wrap}
        .tab{flex:1;padding:.6rem;text-align:center;border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray);min-width:80px;text-decoration:none}
        .tab.active{background:var(--teal);color:#fff}
        .tab .cnt{background:rgba(0,0,0,.1);color:inherit;border-radius:10px;padding:.05rem .4rem;font-size:.68rem;margin-left:.3rem;display:inline-block}
        .tab.active .cnt{background:rgba(255,255,255,.2)}

        /* TOOLBAR */
        .toolbar{background:#fff;border-radius:12px;padding:.9rem 1.1rem;border:1px solid var(--lgray);margin-bottom:1.2rem;display:flex;gap:.7rem;align-items:center;flex-wrap:wrap}
        .toolbar input,.toolbar select{padding:.5rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.83rem;outline:none;background:#fff;font-family:'DM Sans',sans-serif}
        .toolbar input{flex:1;min-width:180px}
        .toolbar input:focus,.toolbar select:focus{border-color:var(--teal)}
        .export-btn{padding:.5rem 1.1rem;background:var(--teal);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;margin-left:auto}

        /* TABLE */
        .table-card{background:#fff;border-radius:14px;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden}
        .table-head{padding:.9rem 1.2rem;border-bottom:1px solid var(--lgray);background:#F8FAFC;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem}
        .table-head h4{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:var(--dark)}
        .data-table{width:100%;border-collapse:collapse;font-size:.8rem;overflow-x:auto;display:block}
        .data-table thead,.data-table tbody{display:table;width:100%;table-layout:fixed}
        .data-table th{padding:.55rem .8rem;text-align:left;color:var(--gray);font-weight:700;font-size:.7rem;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--lgray);background:#F8FAFC;white-space:nowrap}
        .data-table td{padding:.55rem .8rem;border-bottom:1px solid #F1F5F9;color:#334155;vertical-align:middle;word-break:break-word}
        .data-table tr:last-child td{border:none}
        .data-table tr:hover td{background:#F8FAFC}
        .badge{padding:.2rem .6rem;border-radius:10px;font-size:.7rem;font-weight:700;white-space:nowrap;display:inline-block}
        .b-conf{background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0}
        .b-att{background:#FFF7ED;color:#C2410C;border:1px solid #FED7AA}
        .b-ann{background:#FFF5F5;color:var(--red);border:1px solid #FECACA}
        .b-term{background:#F1F5F9;color:var(--gray);border:1px solid var(--lgray)}
        .a-btn{padding:.28rem .65rem;border-radius:6px;font-size:.72rem;font-weight:600;cursor:pointer;border:none;margin-right:.2rem;text-decoration:none;display:inline-block}
        .ab-v{background:var(--blue);color:#fff}
        .ab-ann{background:transparent;color:var(--red);border:1px solid var(--red)}
        .ab-del{background:transparent;color:var(--gray);border:1px solid var(--lgray)}

        .pagination{display:flex;justify-content:space-between;align-items:center;padding:.9rem 1.2rem;border-top:1px solid var(--lgray);background:#F8FAFC;flex-wrap:wrap;gap:.5rem}
        .page-info{font-size:.8rem;color:var(--gray)}
        .page-btns{display:flex;gap:.3rem;flex-wrap:wrap}
        .pg{width:32px;height:32px;border-radius:7px;border:1px solid var(--lgray);background:#fff;font-size:.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#334155;text-decoration:none}
        .pg.active{background:var(--teal);color:#fff;border-color:var(--teal)}
        .pg:hover:not(.active){background:var(--light)}

        footer{background:var(--dark);color:#94A3B8;padding:15px 1.8rem;text-align:center;font-size:.78rem;flex-shrink:0}
        footer strong{color:#fff}

        /* RESPONSIVE */
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
            .tabs{flex-wrap:wrap}
            .tab{min-width:60px;font-size:.75rem;padding:.4rem}
            .toolbar{flex-direction:column;align-items:stretch}
            .toolbar input{min-width:100%}
            .export-btn{margin-left:0}
            .data-table{font-size:.72rem}
            .data-table th,.data-table td{padding:.4rem .5rem}
            .badge{font-size:.6rem;padding:.15rem .4rem}
            .a-btn{font-size:.6rem;padding:.2rem .4rem}
            .pagination{flex-direction:column;text-align:center}
        }
        @media(max-width:480px){
            .stats-row{flex-direction:column}
            .st-card{min-width:100%}
            .top-right .date-badge{font-size:.65rem;padding:.2rem .5rem}
            .table-head{flex-direction:column;text-align:center}
            .data-table{font-size:.65rem}
            .data-table th,.data-table td{padding:.3rem .3rem}
            .pg{width:28px;height:28px;font-size:.7rem}
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
            <span class="badge"><?=$stats['en_attente']?></span>
        </a>
        <a href="validation_medecins.php" class="nav-item">
            <span class="icon">✅</span>Validation Médecins
            <span class="badge"><?=$stats['en_attente']?></span>
        </a>
        <a href="gestion_rdv.php" class="nav-item active">
            <span class="icon">📅</span>Gestion RDV
        </a>
        <div class="nav-section">Sécurité</div>
        <a href="securite.php" class="nav-item">
            <span class="icon">🔒</span>Journal Accès
        </a>
        <a href="securite.php" class="nav-item">
            <span class="icon">⚙️</span>Paramètres
        </a>
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
            <h2>📅 Gestion de tous les RDV</h2>
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
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">✅ <?=htmlspecialchars($_SESSION['success'])?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">❌ <?=htmlspecialchars($_SESSION['error'])?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-row">
            <div class="st-card">
                <div class="st-icon" style="background:#EFF6FF">📅</div>
                <div>
                    <div class="st-num" style="color:var(--blue)"><?=$stats['aujourdhui']?></div>
                    <div class="st-lbl">Aujourd'hui</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF7ED">⏳</div>
                <div>
                    <div class="st-num" style="color:var(--orange)"><?=$stats['en_attente']?></div>
                    <div class="st-lbl">En attente</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#F0FDF4">✅</div>
                <div>
                    <div class="st-num" style="color:var(--green)"><?=$stats['confirme']?></div>
                    <div class="st-lbl">Confirmés</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF5F5">❌</div>
                <div>
                    <div class="st-num" style="color:var(--red)"><?=$stats['annule']?></div>
                    <div class="st-lbl">Annulés</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#F1F5F9">✔</div>
                <div>
                    <div class="st-num" style="color:var(--gray)"><?=$stats['total']?></div>
                    <div class="st-lbl">Total</div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs">
            <?php foreach($onglets as $key => $onglet): ?>
                <a href="?statut=<?=$key?>" class="tab <?=$filtre_statut===$key?'active':''?>">
                    <?=$onglet['label']?> <span class="cnt"><?=$onglet['count']?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <input type="date" value="<?=date('Y-m-d')?>">
            <select>
                <option>Tous les médecins</option>
                <?php 
                $medecins = $pdo->query("SELECT id, prenom, nom FROM medecins WHERE statut = 'valide' ORDER BY nom")->fetchAll();
                foreach($medecins as $m): 
                ?>
                    <option value="<?=$m['id']?>">Dr. <?=htmlspecialchars($m['prenom'].' '.$m['nom'])?></option>
                <?php endforeach; ?>
            </select>
            <select>
                <option>Toutes les spécialités</option>
                <?php 
                $specs = $pdo->query("SELECT DISTINCT specialite FROM medecins WHERE statut = 'valide' ORDER BY specialite")->fetchAll(PDO::FETCH_COLUMN);
                foreach($specs as $spec): 
                ?>
                    <option><?=htmlspecialchars($spec)?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" placeholder="Rechercher patient ou médecin...">
            <button class="export-btn">📥 Exporter</button>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-head">
                <h4>Tous les rendez-vous</h4>
                <span style="font-size:.78rem;color:var(--gray)"><?=$total?> RDV • Page <?=$page?>/<?=max(1,$total_pages)?></span>
            </div>

            <?php if(empty($rendezvous)): ?>
                <div style="padding:2rem;text-align:center;color:var(--gray);">
                    <div style="font-size:3rem;margin-bottom:0.5rem;">📭</div>
                    <p>Aucun rendez-vous trouvé.</p>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:15%">Patient</th>
                        <th style="width:12%">Médecin</th>
                        <th style="width:12%">Spécialité</th>
                        <th style="width:14%">Date & Heure</th>
                        <th style="width:15%">Motif</th>
                        <th style="width:10%">Statut</th>
                        <th style="width:17%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($rendezvous as $rdv): 
                        $badge_class = $rdv['statut'] === 'confirme' ? 'b-conf' : ($rdv['statut'] === 'en_attente' ? 'b-att' : ($rdv['statut'] === 'annule' ? 'b-ann' : 'b-term'));
                        $badge_icon = $rdv['statut'] === 'confirme' ? '✅' : ($rdv['statut'] === 'en_attente' ? '⏳' : ($rdv['statut'] === 'annule' ? '❌' : '✔'));
                        $badge_label = $rdv['statut'] === 'confirme' ? 'Confirmé' : ($rdv['statut'] === 'en_attente' ? 'En attente' : ($rdv['statut'] === 'annule' ? 'Annulé' : 'Terminé'));
                    ?>
                    <tr>
                        <td style="color:var(--gray);font-weight:600"><?=$rdv['id']?></td>
                        <td>
                            <strong><?=htmlspecialchars($rdv['p_prenom'].' '.$rdv['p_nom'])?></strong>
                            <div style="font-size:.72rem;color:var(--gray)"><?=htmlspecialchars($rdv['p_email'])?></div>
                        </td>
                        <td><strong>Dr. <?=htmlspecialchars($rdv['m_prenom'])?></strong></td>
                        <td>
                            <span style="background:#EFF6FF;color:var(--blue);padding:.15rem .45rem;border-radius:4px;font-size:.72rem;font-weight:600">
                                <?=htmlspecialchars($rdv['specialite'])?>
                            </span>
                        </td>
                        <td>
                            <strong><?=date('d/m/Y', strtotime($rdv['date_rdv']))?></strong>
                            <div style="font-size:.72rem;color:var(--gray)"><?=substr($rdv['heure_rdv'],0,5)?></div>
                        </td>
                        <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--gray)">
                            <?=htmlspecialchars(mb_substr($rdv['motif'],0,30)).(mb_strlen($rdv['motif'])>30?'...':'')?>
                        </td>
                        <td><span class="badge <?=$badge_class?>"><?=$badge_icon?> <?=$badge_label?></span></td>
                        <td>
                            <a href="detail_rdv.php?id=<?=$rdv['id']?>" class="a-btn ab-v">Voir</a>
                            <?php if($rdv['statut'] !== 'annule' && $rdv['statut'] !== 'termine'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Annuler ce rendez-vous ?')">
                                    <input type="hidden" name="rdv_id" value="<?=$rdv['id']?>">
                                    <input type="hidden" name="action" value="annuler">
                                    <button type="submit" class="a-btn ab-ann">Annuler</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Affichage <?=$offset+1?>-<?=min($offset+$limit, $total)?> sur <?=$total?> RDV</span>
                <div class="page-btns">
                    <?php if($page > 1): ?>
                        <a href="?statut=<?=$filtre_statut?>&page=<?=$page-1?>" class="pg">←</a>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?statut=<?=$filtre_statut?>&page=<?=$i?>" class="pg <?=$i===$page?'active':''?>"><?=$i?></a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?statut=<?=$filtre_statut?>&page=<?=$page+1?>" class="pg">→</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p><strong>MediRDV</strong> — UNCHK | L3 IDA 2024-2025 &nbsp;|&nbsp; Page designer par : <strong>Aicha Djigo (E7)</strong> — gestion_rdv.php</p>
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
</script>
</body>
</html>