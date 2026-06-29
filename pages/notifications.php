<?php
// ================================================================
// notifications.php — Gestion des notifications
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$role = getRole();

// Déterminer le type d'utilisateur pour les notifications
$type_user = $role === 'patient' ? 'patient' : ($role === 'medecin' ? 'medecin' : 'admin');

// ================================================================
// STATISTIQUES
// ================================================================
$stats = [
    'non_lues' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $user_id AND type_user = '$type_user' AND lu = 0")->fetchColumn(),
    'rdv_confirme' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $user_id AND type_user = '$type_user' AND type_notif = 'rdv_confirme' AND lu = 0")->fetchColumn(),
    'rappel' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $user_id AND type_user = '$type_user' AND type_notif = 'rappel' AND lu = 0")->fetchColumn(),
    'total_mois' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $user_id AND type_user = '$type_user' AND date_envoi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
];

// ================================================================
// FILTRES
// ================================================================
$filtre_type = $_GET['type'] ?? 'toutes';
$filtre_periode = $_GET['periode'] ?? 'aujourdhui';

$sql = "SELECT * FROM notifications WHERE id_destinataire = :user_id AND type_user = :type_user";
$params = [':user_id' => $user_id, ':type_user' => $type_user];

if ($filtre_type !== 'toutes') {
    $sql .= " AND type_notif = :type_notif";
    $params[':type_notif'] = $filtre_type;
}

if ($filtre_periode === 'aujourdhui') {
    $sql .= " AND DATE(date_envoi) = CURDATE()";
} elseif ($filtre_periode === 'semaine') {
    $sql .= " AND date_envoi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filtre_periode === 'mois') {
    $sql .= " AND date_envoi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY date_envoi DESC";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// TRAITEMENT DES ACTIONS
// ================================================================

// Marquer tout comme lu
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id_destinataire = :user_id AND type_user = :type_user")
        ->execute([':user_id' => $user_id, ':type_user' => $type_user]);
    redirect('notifications.php');
}

// Marquer une notification comme lue
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = :id AND id_destinataire = :user_id")
        ->execute([':id' => $id, ':user_id' => $user_id]);
    redirect('notifications.php');
}

// Supprimer toutes les notifications
if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
    $pdo->prepare("DELETE FROM notifications WHERE id_destinataire = :user_id AND type_user = :type_user")
        ->execute([':user_id' => $user_id, ':type_user' => $type_user]);
    redirect('notifications.php');
}

// ================================================================
// STATISTIQUES PAR TYPE
// ================================================================
$types_stats = $pdo->query("
    SELECT type_notif, COUNT(*) AS nb 
    FROM notifications 
    WHERE id_destinataire = $user_id AND type_user = '$type_user'
    GROUP BY type_notif
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ================================================================
// HELPERS
// ================================================================
function timeAgo($date) {
    $diff = (new DateTime())->diff(new DateTime($date));
    if ($diff->days > 0) {
        if ($diff->days == 1) return 'Hier';
        if ($diff->days < 7) return 'Il y a ' . $diff->days . ' jours';
        return 'Il y a ' . $diff->days . ' jours';
    }
    if ($diff->h > 0) return 'Il y a ' . $diff->h . 'h' . ($diff->i > 0 ? $diff->i . 'min' : '');
    if ($diff->i > 0) return 'Il y a ' . $diff->i . ' min';
    return 'À l\'instant';
}

function getNotifIcon($type) {
    $icons = [
        'rdv_confirme' => ['icon' => '✅', 'bg' => '#DCFCE7'],
        'rdv_annule' => ['icon' => '❌', 'bg' => '#FEE2E2'],
        'rappel' => ['icon' => '⏰', 'bg' => '#FFEDD5'],
        'nouveau_rdv' => ['icon' => '📅', 'bg' => '#DBEAFE'],
        'message' => ['icon' => '💬', 'bg' => '#F3E8FF'],
    ];
    return $icons[$type] ?? ['icon' => '📌', 'bg' => '#F1F5F9'];
}

function getNotifLabel($type) {
    $labels = [
        'rdv_confirme' => 'RDV confirmé',
        'rdv_annule' => 'RDV annulé',
        'rappel' => 'Rappel',
        'nouveau_rdv' => 'Nouveau RDV',
        'message' => 'Message',
    ];
    return $labels[$type] ?? 'Notification';
}

// Récupérer les infos utilisateur pour la navbar
if ($role === 'patient') {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($role === 'medecin') {
    $stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$initiales = strtoupper(substr($user['prenom'] ?? $user['nom'], 0, 1) . substr($user['nom'] ?? '', 0, 1));
$dashboard = $role === 'patient' ? 'dashboard_patient.php' : ($role === 'medecin' ? 'dashboard_medecin.php' : 'dashboard_admin.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Notifications</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--teal:#006D77;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .nav-links a.active{color:#fff;border-bottom:2px solid var(--green);padding-bottom:2px}
        .nav-right{display:flex;align-items:center;gap:1rem}
        .notif-bell{position:relative;cursor:pointer;color:#CBD5E1;font-size:1.1rem;text-decoration:none}
        .notif-bell .badge{position:absolute;top:-6px;right:-8px;background:var(--red);color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:700}
        .avatar{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;overflow:hidden;text-decoration:none}
        .avatar img{width:100%;height:100%;object-fit:cover}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== PAGE HEADER ===== */
        .page-hdr{background:linear-gradient(135deg,var(--dark),var(--blue));padding:1.8rem 5%;color:#fff}
        .breadcrumb{font-size:.78rem;color:#94A3B8;margin-bottom:.5rem}
        .breadcrumb span{color:var(--green)}
        .hdr-inner{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
        .page-hdr h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
        .page-hdr p{color:#CBD5E1;font-size:.85rem;margin-top:.3rem}
        .hdr-actions{display:flex;gap:.6rem;flex-wrap:wrap}
        .hdr-btn{padding:.5rem 1.1rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}
        .hdr-btn.red{background:rgba(214,40,40,.3);border-color:rgba(214,40,40,.5)}
        .hdr-btn.red:hover{background:rgba(214,40,40,.5)}

        /* ===== STATS ===== */
        .stats-row{background:#fff;border-bottom:1px solid var(--lgray);padding:.8rem 5%;display:flex;gap:1.5rem;flex-wrap:wrap}
        .st{display:flex;align-items:center;gap:.6rem;background:var(--light);border-radius:10px;padding:.5rem 1rem}
        .st-num{font-family:'Sora',sans-serif;font-size:1.3rem;font-weight:800}
        .st-lbl{font-size:.72rem;color:var(--gray)}

        /* ===== MAIN ===== */
        .main{display:flex;gap:1.5rem;padding:1.5rem 5%;max-width:1000px;margin:0 auto}

        /* ===== SIDEBAR ===== */
        .sidebar{width:220px;flex-shrink:0}
        .f-card{background:#fff;border-radius:12px;padding:1.1rem;border:1px solid var(--lgray);margin-bottom:.8rem;box-shadow:0 2px 6px rgba(0,0,0,.04)}
        .f-card h5{font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:var(--dark);margin-bottom:.7rem;padding-bottom:.4rem;border-bottom:1px solid var(--lgray)}
        .f-opt{display:flex;align-items:center;justify-content:space-between;padding:.3rem 0;cursor:pointer;font-size:.83rem;color:#334155;border-radius:6px;transition:.15s;text-decoration:none}
        .f-opt:hover{color:var(--blue)}
        .f-opt.active{font-weight:700;color:var(--blue)}
        .f-opt .cnt{background:var(--light);color:var(--gray);font-size:.7rem;padding:.1rem .4rem;border-radius:5px;font-weight:600}
        .f-opt.active .cnt{background:var(--blue);color:#fff}

        /* ===== NOTIF LIST ===== */
        .notif-list{flex:1}
        .list-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
        .list-header h3{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark)}
        .mark-all{font-size:.82rem;color:var(--blue);font-weight:600;cursor:pointer;background:none;border:none}
        .mark-all:hover{color:var(--dark)}

        /* ===== NOTIF ITEM ===== */
        .notif-item{display:flex;gap:.9rem;padding:1rem 1.1rem;background:#fff;border-radius:12px;border:1px solid var(--lgray);margin-bottom:.6rem;cursor:pointer;transition:.15s;box-shadow:0 2px 6px rgba(0,0,0,.04);text-decoration:none;color:inherit}
        .notif-item:hover{border-color:#B8D4FF;box-shadow:0 4px 16px rgba(0,0,0,.08)}
        .notif-item.unread{background:#EFF6FF;border-color:#BFDBFE}
        .notif-item.unread-orange{background:#FFF7ED;border-color:#FED7AA}
        .notif-item.unread-green{background:#F0FDF4;border-color:#BBF7D0}
        .notif-item.unread-red{background:#FFF5F5;border-color:#FECACA}
        .notif-item.read{opacity:.75}

        .notif-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .notif-body{flex:1}
        .notif-title{font-size:.88rem;font-weight:700;color:#1E293B;margin-bottom:.2rem}
        .notif-msg{font-size:.8rem;color:var(--gray);line-height:1.4}
        .notif-time{font-size:.73rem;color:var(--gray);margin-top:.4rem;display:flex;align-items:center;gap:.4rem}
        .unread-dot{width:8px;height:8px;border-radius:50%;background:var(--blue);flex-shrink:0;margin-top:4px}

        .notif-action{display:flex;flex-direction:column;gap:.4rem;align-items:flex-end;flex-shrink:0}
        .n-btn{padding:.3rem .7rem;border-radius:6px;font-size:.73rem;font-weight:600;cursor:pointer;border:none;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:.2rem}
        .n-btn.blue{background:var(--blue);color:#fff}
        .n-btn.blue:hover{background:var(--dark)}
        .n-btn.outline{background:transparent;color:var(--gray);border:1px solid var(--lgray)}
        .n-btn.outline:hover{border-color:var(--blue);color:var(--blue)}
        .read-mark{width:8px;height:8px;border-radius:50%;background:var(--blue)}
        .read-mark.read{background:var(--lgray)}

        /* ===== DATE GROUP ===== */
        .date-group{font-size:.75rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.8px;margin:.8rem 0 .4rem;display:flex;align-items:center;gap:.5rem}
        .date-group::after{content:'';flex:1;height:1px;background:var(--lgray)}

        /* ===== EMPTY ===== */
        .empty{text-align:center;padding:2.5rem;background:#fff;border-radius:12px;border:1px solid var(--lgray)}
        .empty-icon{font-size:3rem;margin-bottom:.8rem;opacity:.5}
        .empty h3{font-family:'Sora',sans-serif;color:var(--dark);margin-bottom:.4rem}
        .empty p{color:var(--gray);font-size:.85rem}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .main{flex-direction:column}
            .sidebar{width:100%}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .hdr-inner{flex-direction:column}
            .stats-row{gap:.8rem}
            .st{padding:.4rem .8rem}
            .st-num{font-size:1.1rem}
            .notif-item{flex-direction:column;align-items:stretch}
            .notif-action{flex-direction:row;justify-content:flex-start;margin-top:.3rem}
            .unread-dot{display:none}
            .list-header{flex-direction:column;text-align:center}
        }
        @media(max-width:480px){
            .page-hdr{padding:1.2rem 4%}
            .page-hdr h1{font-size:1.1rem}
            .main{padding:1rem 4%}
            .notif-item{padding:.8rem}
            .notif-icon{width:32px;height:32px;font-size:.9rem}
            .notif-title{font-size:.8rem}
            .notif-msg{font-size:.75rem}
        }
    </style>
</head>
<body>

<!-- ===== NAV ===== -->
<nav>
    <a href="<?=$dashboard?>" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="<?=$dashboard?>">Accueil</a></li>
        <?php if($role === 'patient'): ?>
            <li><a href="mes_rdv.php">Mes RDV</a></li>
        <?php elseif($role === 'medecin'): ?>
            <li><a href="mon_agenda.php">Mon Agenda</a></li>
        <?php endif; ?>
        <li><a href="notifications.php" class="active">Notifications</a></li>
        <li><a href="<?=$role === 'patient' ? 'profil_patient.php' : ($role === 'medecin' ? 'profil_medecin.php' : 'profil_admin.php')?>">Mon Profil</a></li>
    </ul>
    <div class="nav-right">
        <a href="notifications.php" class="notif-bell">
            🔔
            <?php if($stats['non_lues'] > 0): ?>
                <div class="badge"><?=$stats['non_lues']?></div>
            <?php endif; ?>
        </a>
        <a href="<?=$role === 'patient' ? 'profil_patient.php' : ($role === 'medecin' ? 'profil_medecin.php' : 'profil_admin.php')?>" class="avatar">
            <?php if(!empty($user['photo']) && file_exists(__DIR__.'/../assets/uploads/'.$user['photo'])): ?>
                <img src="../assets/uploads/<?=htmlspecialchars($user['photo'])?>" alt="">
            <?php else: ?>
                <?=$initiales?>
            <?php endif; ?>
        </a>
    </div>
</nav>

<!-- ===== PAGE HEADER ===== -->
<div class="page-hdr">
    <div class="breadcrumb">Accueil → <span>Notifications</span></div>
    <div class="hdr-inner">
        <div>
            <h1>🔔 Mes Notifications</h1>
            <p>Restez informé de l'état de vos rendez-vous</p>
        </div>
        <div class="hdr-actions">
            <a href="?action=mark_all_read" class="hdr-btn">✅ Tout marquer comme lu</a>
            <a href="?action=delete_all" class="hdr-btn red" onclick="return confirm('Supprimer toutes les notifications ?')">🗑 Vider les notifications</a>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-row">
    <div class="st"><div class="st-num" style="color:var(--red)"><?=$stats['non_lues']?></div><div class="st-lbl">Non lues</div></div>
    <div class="st"><div class="st-num" style="color:var(--green)"><?=$stats['rdv_confirme']?></div><div class="st-lbl">RDV confirmés</div></div>
    <div class="st"><div class="st-num" style="color:var(--orange)"><?=$stats['rappel']?></div><div class="st-lbl">Rappels</div></div>
    <div class="st"><div class="st-num" style="color:var(--blue)"><?=$stats['total_mois']?></div><div class="st-lbl">Total ce mois</div></div>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="f-card">
            <h5>🔽 Filtrer par type</h5>
            <a href="?type=toutes" class="f-opt <?=$filtre_type==='toutes'?'active':''?>">📋 Toutes <span class="cnt"><?=array_sum($types_stats)?></span></a>
            <a href="?type=rdv_confirme" class="f-opt <?=$filtre_type==='rdv_confirme'?'active':''?>">✅ RDV confirmés <span class="cnt"><?=$types_stats['rdv_confirme'] ?? 0?></span></a>
            <a href="?type=rdv_annule" class="f-opt <?=$filtre_type==='rdv_annule'?'active':''?>">❌ RDV annulés <span class="cnt"><?=$types_stats['rdv_annule'] ?? 0?></span></a>
            <a href="?type=rappel" class="f-opt <?=$filtre_type==='rappel'?'active':''?>">⏰ Rappels <span class="cnt"><?=$types_stats['rappel'] ?? 0?></span></a>
            <a href="?type=nouveau_rdv" class="f-opt <?=$filtre_type==='nouveau_rdv'?'active':''?>">📅 Nouveaux RDV <span class="cnt"><?=$types_stats['nouveau_rdv'] ?? 0?></span></a>
            <a href="?type=message" class="f-opt <?=$filtre_type==='message'?'active':''?>">💬 Messages <span class="cnt"><?=$types_stats['message'] ?? 0?></span></a>
        </div>
        <div class="f-card">
            <h5>📅 Période</h5>
            <a href="?periode=aujourdhui" class="f-opt <?=$filtre_periode==='aujourdhui'?'active':''?>">Aujourd'hui <span class="cnt"><?=$pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $user_id AND type_user = '$type_user' AND DATE(date_envoi) = CURDATE()")->fetchColumn()?></span></a>
            <a href="?periode=semaine" class="f-opt <?=$filtre_periode==='semaine'?'active':''?>">Cette semaine <span class="cnt"><?=$pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $user_id AND type_user = '$type_user' AND date_envoi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn()?></span></a>
            <a href="?periode=mois" class="f-opt <?=$filtre_periode==='mois'?'active':''?>">Ce mois <span class="cnt"><?=$pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $user_id AND type_user = '$type_user' AND date_envoi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn()?></span></a>
        </div>
    </div>

    <!-- ===== NOTIF LIST ===== -->
    <div class="notif-list">
        <div class="list-header">
            <h3><?=ucfirst($filtre_periode)?> — <?=count($notifications)?> notification<?=count($notifications)>1?'s':''?></h3>
            <?php if(count($notifications) > 0): ?>
                <a href="?action=mark_all_read" class="mark-all">✅ Tout marquer comme lu</a>
            <?php endif; ?>
        </div>

        <?php if(empty($notifications)): ?>
            <div class="empty">
                <div class="empty-icon">🔔</div>
                <h3>Aucune notification</h3>
                <p>Vous n'avez pas de nouvelles notifications pour le moment.</p>
            </div>
        <?php else: ?>
            <?php 
            $date_courante = '';
            foreach($notifications as $n): 
                $date_notif = date('Y-m-d', strtotime($n['date_envoi']));
                if($date_notif != $date_courante): 
                    $date_courante = $date_notif;
                    $dt = new DateTime($n['date_envoi']);
                    $label = $dt->format('d/m/Y');
                    if($date_notif == date('Y-m-d')) $label = "Aujourd'hui";
                    elseif($date_notif == date('Y-m-d', strtotime('-1 day'))) $label = "Hier";
            ?>
                <div class="date-group"><?=$label?></div>
                <?php endif; 
                $is_unread = $n['lu'] == 0;
                $icon = getNotifIcon($n['type_notif']);
                $bg_class = $is_unread ? 'unread' : 'read';
                if($n['type_notif'] === 'rappel' && $is_unread) $bg_class = 'unread-orange';
                elseif($n['type_notif'] === 'rdv_confirme' && $is_unread) $bg_class = 'unread-green';
                elseif($n['type_notif'] === 'rdv_annule' && $is_unread) $bg_class = 'unread-red';
                elseif($n['type_notif'] === 'nouveau_rdv' && $is_unread) $bg_class = 'unread';
            ?>
            <div class="notif-item <?=$bg_class?>">
                <?php if($is_unread): ?>
                    <div class="unread-dot"></div>
                <?php endif; ?>
                <div class="notif-icon" style="background:<?=$icon['bg']?>"><?=$icon['icon']?></div>
                <div class="notif-body">
                    <div class="notif-title"><?=getNotifLabel($n['type_notif'])?></div>
                    <div class="notif-msg"><?=htmlspecialchars($n['message'])?></div>
                    <div class="notif-time">🕐 <?=timeAgo($n['date_envoi'])?></div>
                </div>
                <div class="notif-action">
                    <?php if($is_unread): ?>
                        <a href="?action=mark_read&id=<?=$n['id']?>" class="n-btn outline">Marquer lu</a>
                    <?php endif; ?>
                    <?php if($n['id_rdv']): ?>
                        <a href="detail_rdv.php?id=<?=$n['id_rdv']?>" class="n-btn blue">Voir RDV</a>
                    <?php endif; ?>
                    <div class="read-mark <?=$is_unread?'':'read'?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Aminata Layi Gueye (E6)</strong> — notifications.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }
</script>
</body>
</html>