<?php
// ================================================================
// gestion_users.php — Gestion des utilisateurs (Admin)
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
    'total' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut != 'supprime'")->fetchColumn() 
              + $pdo->query("SELECT COUNT(*) FROM medecins")->fetchColumn()
              + $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn(),
    'actifs' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif'")->fetchColumn()
              + $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide'")->fetchColumn(),
    'suspendus' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'suspendu'")->fetchColumn()
                 + $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'suspendu'")->fetchColumn(),
    'supprimes' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'supprime'")->fetchColumn(),
    'medecins' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide'")->fetchColumn(),
];

// ================================================================
// FILTRES ET PAGINATION
// ================================================================
$filtre_role = $_GET['role'] ?? 'patients';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Construction de la requête selon le rôle
if ($filtre_role === 'patients') {
    // La table patients n'a pas de colonne 'ville'
    $sql = "SELECT id, nom, prenom, email, telephone, date_naissance, statut, date_inscription, 'patient' AS type FROM patients";
    $count_sql = "SELECT COUNT(*) FROM patients";
    $params = [];
} elseif ($filtre_role === 'medecins') {
    $sql = "SELECT id, nom, prenom, email, telephone, specialite, ville, statut, date_inscription, 'medecin' AS type FROM medecins";
    $count_sql = "SELECT COUNT(*) FROM medecins";
    $params = [];
} else {
    $sql = "SELECT id, nom, email, 'admin' AS type, date_creation AS date_inscription, 'actif' AS statut, '' AS prenom, '' AS telephone, '' AS ville FROM admins";
    $count_sql = "SELECT COUNT(*) FROM admins";
    $params = [];
}

// Filtres supplémentaires
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    if ($filtre_role === 'patients') {
        $sql .= " WHERE (nom LIKE :search OR prenom LIKE :search OR email LIKE :search)";
        $count_sql .= " WHERE (nom LIKE :search OR prenom LIKE :search OR email LIKE :search)";
    } elseif ($filtre_role === 'medecins') {
        $sql .= " WHERE (nom LIKE :search OR prenom LIKE :search OR email LIKE :search OR specialite LIKE :search)";
        $count_sql .= " WHERE (nom LIKE :search OR prenom LIKE :search OR email LIKE :search OR specialite LIKE :search)";
    } else {
        $sql .= " WHERE (nom LIKE :search OR email LIKE :search)";
        $count_sql .= " WHERE (nom LIKE :search OR email LIKE :search)";
    }
    $params[':search'] = $search;
}

$sql .= " ORDER BY date_inscription DESC LIMIT :limit OFFSET :offset";

// Compter le total
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
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total / $limit);

// ================================================================
// TRAITEMENT DES ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id']) && isset($_POST['type'])) {
        $user_id = (int)$_POST['user_id'];
        $type = $_POST['type'];
        $action = $_POST['action'];
        
        if ($type === 'patient') {
            if ($action === 'suspendre') {
                $stmt = $pdo->prepare("UPDATE patients SET statut = 'suspendu' WHERE id = :id");
            } elseif ($action === 'activer') {
                $stmt = $pdo->prepare("UPDATE patients SET statut = 'actif' WHERE id = :id");
            } elseif ($action === 'supprimer') {
                $stmt = $pdo->prepare("UPDATE patients SET statut = 'supprime' WHERE id = :id");
            }
            $stmt->execute([':id' => $user_id]);
            $_SESSION['success'] = '✅ Action effectuée avec succès.';
        } elseif ($type === 'medecin') {
            if ($action === 'suspendre') {
                $stmt = $pdo->prepare("UPDATE medecins SET statut = 'suspendu' WHERE id = :id");
            } elseif ($action === 'activer') {
                $stmt = $pdo->prepare("UPDATE medecins SET statut = 'valide' WHERE id = :id");
            }
            $stmt->execute([':id' => $user_id]);
            $_SESSION['success'] = '✅ Action effectuée avec succès.';
        }
        redirect('gestion_users.php?role=' . $filtre_role);
    }
}

// Notifications
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type_user = 'admin' AND lu = 0")->fetchColumn();

$initiales = strtoupper(substr($admin['nom'], 0, 1));
$date_actuelle = date('d/m/Y');
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$jour_actuel = $jours_fr[date('l')];

// Compter les médecins en attente pour le badge
$en_attente = $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'en_attente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Gestion Utilisateurs</title>
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

        .stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .st-card{background:#fff;border-radius:12px;padding:1rem 1.3rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.04);display:flex;align-items:center;gap:.8rem;flex:1;min-width:140px}
        .st-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .st-num{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;color:var(--dark)}
        .st-lbl{font-size:.73rem;color:var(--gray)}

        .tabs{display:flex;gap:.3rem;background:#fff;border-radius:12px;padding:.3rem;margin-bottom:1.2rem;border:1px solid var(--lgray);flex-wrap:wrap}
        .tab{flex:1;padding:.6rem;text-align:center;border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray);min-width:80px;text-decoration:none}
        .tab.active{background:var(--blue);color:#fff}

        .toolbar{display:flex;gap:.7rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center}
        .toolbar input{flex:1;min-width:200px;padding:.55rem .9rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.83rem;outline:none;font-family:'DM Sans',sans-serif}
        .toolbar input:focus{border-color:var(--blue)}
        .toolbar select{padding:.55rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.83rem;outline:none;background:#fff}
        .toolbar-right{display:flex;gap:.5rem;margin-left:auto;flex-wrap:wrap}
        .tb-btn{padding:.5rem 1.1rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none}
        .tb-btn.blue{background:var(--blue);color:#fff}
        .tb-btn.red{background:var(--red);color:#fff}
        .tb-btn.outline{background:transparent;color:var(--gray);border:1.5px solid var(--lgray)}

        .table-card{background:#fff;border-radius:14px;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden}
        .table-head{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.2rem;border-bottom:1px solid var(--lgray);background:#F8FAFC;flex-wrap:wrap;gap:.5rem}
        .table-head h4{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:var(--dark)}
        .table-count{font-size:.78rem;color:var(--gray)}
        .data-table{width:100%;border-collapse:collapse;font-size:.82rem;overflow-x:auto;display:block}
        .data-table thead,.data-table tbody{display:table;width:100%;table-layout:fixed}
        .data-table th{padding:.6rem .9rem;text-align:left;color:var(--gray);font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--lgray);background:#F8FAFC;white-space:nowrap}
        .data-table td{padding:.6rem .9rem;border-bottom:1px solid #F1F5F9;color:#334155;vertical-align:middle;word-break:break-word}
        .data-table tr:last-child td{border:none}
        .data-table tr:hover td{background:#F8FAFC}

        .user-cell{display:flex;align-items:center;gap:.7rem}
        .u-av{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;flex-shrink:0;color:#fff}
        .u-name{font-weight:700;color:#1E293B;font-size:.85rem}
        .u-email{font-size:.73rem;color:var(--gray)}

        .badge{padding:.22rem .65rem;border-radius:15px;font-size:.71rem;font-weight:700;white-space:nowrap;display:inline-block}
        .b-actif{background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0}
        .b-suspendu{background:#FFF7ED;color:#C2410C;border:1px solid #FED7AA}
        .b-supprime{background:#FFF5F5;color:var(--red);border:1px solid #FECACA}
        .b-patient{background:#EFF6FF;color:var(--blue)}
        .b-medecin{background:#FFF7ED;color:var(--orange)}
        .b-admin{background:#F3E8FF;color:var(--purple)}

        .actions-cell{display:flex;gap:.3rem;align-items:center;flex-wrap:wrap}
        .a-btn{width:28px;height:28px;border-radius:6px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:.15s;text-decoration:none}
        .a-btn:hover{filter:brightness(.9)}
        .ab-view{background:#EFF6FF;color:var(--blue)}
        .ab-edit{background:#FFF7ED;color:var(--orange)}
        .ab-suspend{background:#FFF7ED;color:#C2410C}
        .ab-activate{background:#F0FDF4;color:#15803D}
        .ab-delete{background:#FFF5F5;color:var(--red)}

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
            .tabs{flex-wrap:wrap}
            .tab{min-width:60px;font-size:.75rem;padding:.4rem}
            .toolbar{flex-direction:column;align-items:stretch}
            .toolbar input{min-width:100%}
            .toolbar-right{margin-left:0}
            .data-table{font-size:.72rem}
            .data-table th,.data-table td{padding:.4rem .5rem}
            .badge{font-size:.6rem;padding:.15rem .4rem}
            .user-cell{gap:.4rem}
            .u-av{width:28px;height:28px;font-size:.7rem}
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
            .actions-cell{gap:.2rem}
            .a-btn{width:24px;height:24px;font-size:.65rem}
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
        <a href="gestion_users.php" class="nav-item active">
            <span class="icon">👥</span>Utilisateurs
            <span class="badge"><?=$stats['suspendus']?></span>
        </a>
        <a href="validation_medecins.php" class="nav-item">
            <span class="icon">✅</span>Validation Médecins
            <span class="badge"><?=$en_attente?></span>
        </a>
        <a href="gestion_rdv.php" class="nav-item">
            <span class="icon">📅</span>Gestion RDV
        </a>
        <div class="nav-section">Sécurité</div>
        <a href="securite.php" class="nav-item">
            <span class="icon">🔒</span>Journal Accès
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
            <h2>👥 Gestion des Utilisateurs</h2>
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

        <!-- STATS -->
        <div class="stats-row">
            <div class="st-card">
                <div class="st-icon" style="background:#EFF6FF">👥</div>
                <div>
                    <div class="st-num" style="color:var(--blue)"><?=$stats['total']?></div>
                    <div class="st-lbl">Total utilisateurs</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#F0FDF4">✅</div>
                <div>
                    <div class="st-num" style="color:var(--green)"><?=$stats['actifs']?></div>
                    <div class="st-lbl">Actifs</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF7ED">⏸</div>
                <div>
                    <div class="st-num" style="color:var(--orange)"><?=$stats['suspendus']?></div>
                    <div class="st-lbl">Suspendus</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF5F5">🗑</div>
                <div>
                    <div class="st-num" style="color:var(--red)"><?=$stats['supprimes']?></div>
                    <div class="st-lbl">Supprimés</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF7ED">👨‍⚕️</div>
                <div>
                    <div class="st-num" style="color:var(--orange)"><?=$stats['medecins']?></div>
                    <div class="st-lbl">Médecins</div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs">
            <a href="?role=patients" class="tab <?=$filtre_role==='patients'?'active':''?>">
                👤 Patients (<?=$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn()?>)
            </a>
            <a href="?role=medecins" class="tab <?=$filtre_role==='medecins'?'active':''?>">
                👨‍⚕️ Médecins (<?=$pdo->query("SELECT COUNT(*) FROM medecins")->fetchColumn()?>)
            </a>
            <a href="?role=admins" class="tab <?=$filtre_role==='admins'?'active':''?>">
                🛡 Admins (<?=$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn()?>)
            </a>
        </div>

        <!-- TOOLBAR -->
        <form method="GET" action="gestion_users.php" class="toolbar">
            <input type="hidden" name="role" value="<?=$filtre_role?>">
            <input type="text" name="search" placeholder="🔍 Rechercher par nom, email..." value="<?=htmlspecialchars($_GET['search'] ?? '')?>">
            <select name="statut">
                <option value="">Tous les statuts</option>
                <option value="actif">Actifs</option>
                <option value="suspendu">Suspendus</option>
                <option value="supprime">Supprimés</option>
            </select>
            <div class="toolbar-right">
                <button type="submit" class="tb-btn blue">🔍 Filtrer</button>
                <button type="button" class="tb-btn outline">📥 Exporter CSV</button>
            </div>
        </form>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-head">
                <h4>Liste des <?=ucfirst($filtre_role)?></h4>
                <span class="table-count"><?=$total?> <?=ucfirst($filtre_role)?> • Page <?=$page?>/<?=max(1,$total_pages)?></span>
            </div>

            <?php if(empty($users)): ?>
                <div style="padding:2rem;text-align:center;color:var(--gray);">
                    <div style="font-size:3rem;margin-bottom:0.5rem;">📭</div>
                    <p>Aucun utilisateur trouvé.</p>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:25%">Utilisateur</th>
                        <th style="width:10%">Rôle</th>
                        <th style="width:12%">Inscription</th>
                        <th style="width:10%">Statut</th>
                        <th style="width:38%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): 
                        $couleur_avatar = $user['type'] === 'patient' ? 'linear-gradient(135deg,var(--green),var(--teal))' : 
                                         ($user['type'] === 'medecin' ? 'linear-gradient(135deg,var(--orange),var(--red))' : 
                                         'linear-gradient(135deg,var(--purple),var(--blue))');
                        $init = strtoupper(substr($user['prenom'] ?? $user['nom'],0,1).substr($user['nom'],0,1));
                        $role_label = $user['type'] === 'patient' ? 'Patient' : ($user['type'] === 'medecin' ? 'Médecin' : 'Admin');
                        $role_class = $user['type'] === 'patient' ? 'b-patient' : ($user['type'] === 'medecin' ? 'b-medecin' : 'b-admin');
                        $statut_class = $user['statut'] === 'actif' || $user['statut'] === 'valide' ? 'b-actif' : 
                                       ($user['statut'] === 'suspendu' ? 'b-suspendu' : 'b-supprime');
                        $statut_label = $user['statut'] === 'actif' || $user['statut'] === 'valide' ? '✅ Actif' : 
                                       ($user['statut'] === 'suspendu' ? '⏸ Suspendu' : '🗑 Supprimé');
                    ?>
                    <tr>
                        <td style="color:var(--gray);font-weight:600"><?=$user['id']?></td>
                        <td>
                            <div class="user-cell">
                                <div class="u-av" style="background:<?=$couleur_avatar?>"><?=$init?></div>
                                <div>
                                    <div class="u-name"><?=htmlspecialchars($user['prenom'].' '.$user['nom'])?></div>
                                    <div class="u-email"><?=htmlspecialchars($user['email'])?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge <?=$role_class?>"><?=$role_label?></span></td>
                        <td><?=date('d/m/Y', strtotime($user['date_inscription']))?></td>
                        <td><span class="badge <?=$statut_class?>"><?=$statut_label?></span></td>
                        <td>
                            <div class="actions-cell">
                                <a href="profil_<?=$user['type']?>.php?id=<?=$user['id']?>" class="a-btn ab-view" title="Voir">👁</a>
                                <?php if($user['type'] === 'patient' && $user['statut'] !== 'supprime'): ?>
                                    <?php if($user['statut'] === 'suspendu'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?=$user['id']?>">
                                            <input type="hidden" name="type" value="patient">
                                            <input type="hidden" name="action" value="activer">
                                            <button type="submit" class="a-btn ab-activate" title="Activer">▶</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?=$user['id']?>">
                                            <input type="hidden" name="type" value="patient">
                                            <input type="hidden" name="action" value="suspendre">
                                            <button type="submit" class="a-btn ab-suspend" title="Suspendre">⏸</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')">
                                        <input type="hidden" name="user_id" value="<?=$user['id']?>">
                                        <input type="hidden" name="type" value="patient">
                                        <input type="hidden" name="action" value="supprimer">
                                        <button type="submit" class="a-btn ab-delete" title="Supprimer">🗑</button>
                                    </form>
                                <?php elseif($user['type'] === 'medecin'): ?>
                                    <?php if($user['statut'] === 'suspendu'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?=$user['id']?>">
                                            <input type="hidden" name="type" value="medecin">
                                            <input type="hidden" name="action" value="activer">
                                            <button type="submit" class="a-btn ab-activate" title="Activer">▶</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?=$user['id']?>">
                                            <input type="hidden" name="type" value="medecin">
                                            <input type="hidden" name="action" value="suspendre">
                                            <button type="submit" class="a-btn ab-suspend" title="Suspendre">⏸</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Affichage <?=$offset+1?>-<?=min($offset+$limit, $total)?> sur <?=$total?> <?=ucfirst($filtre_role)?></span>
                <div class="page-btns">
                    <?php if($page > 1): ?>
                        <a href="?role=<?=$filtre_role?>&page=<?=$page-1?><?=isset($_GET['search'])?'&search='.urlencode($_GET['search']):''?>" class="pg">←</a>
                    <?php endif; ?>
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?role=<?=$filtre_role?>&page=<?=$i?><?=isset($_GET['search'])?'&search='.urlencode($_GET['search']):''?>" class="pg <?=$i===$page?'active':''?>"><?=$i?></a>
                    <?php endfor; ?>
                    <?php if($page < $total_pages): ?>
                        <a href="?role=<?=$filtre_role?>&page=<?=$page+1?><?=isset($_GET['search'])?'&search='.urlencode($_GET['search']):''?>" class="pg">→</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p><strong>MediRDV</strong> — UNCHK | L3 IDA 2024-2025 &nbsp;|&nbsp; Page designer par : <strong>Aicha Djigo (E7)</strong> — gestion_users.php</p>
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