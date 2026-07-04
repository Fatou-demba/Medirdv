<?php
// ================================================================
// validation_medecins.php — Validation des Médecins
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

// Valider un médecin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'valider') {
    $medecin_id = (int)$_POST['medecin_id'];
    $message = trim($_POST['message'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE medecins SET statut = 'valide' WHERE id = :id AND statut = 'en_attente'");
    if ($stmt->execute([':id' => $medecin_id])) {
        // Ajouter une notification
        $stmt2 = $pdo->prepare("INSERT INTO notifications (id_destinataire, type_user, type_notif, message, date_envoi) 
                                VALUES (:id, 'medecin', 'rdv_confirme', :msg, NOW())");
        $msg = "Votre compte médecin a été validé par l'administrateur. Vous pouvez maintenant vous connecter.";
        $stmt2->execute([':id' => $medecin_id, ':msg' => $msg]);
        
        $_SESSION['success'] = ' Médecin validé avec succès !';
    } else {
        $_SESSION['error'] = '❌ Erreur lors de la validation.';
    }
    redirect('validation_medecins.php');
}
//tester

// Refuser un médecin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'refuser') {
    $medecin_id = (int)$_POST['medecin_id'];
    $motif = trim($_POST['motif_refus'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE medecins SET statut = 'refuse' WHERE id = :id AND statut = 'en_attente'");
    if ($stmt->execute([':id' => $medecin_id])) {
        if (!empty($motif)) {
            $stmt2 = $pdo->prepare("INSERT INTO notifications (id_destinataire, type_user, type_notif, message, date_envoi) 
                                    VALUES (:id, 'medecin', 'rdv_annule', :msg, NOW())");
            $msg = "Votre compte médecin a été refusé. Motif : " . $motif;
            $stmt2->execute([':id' => $medecin_id, ':msg' => $msg]);
        }
        $_SESSION['success'] = ' Médecin refusé.';
    } else {
        $_SESSION['error'] = ' Erreur lors du refus.';
    }
    redirect('validation_medecins.php');
}

// ================================================================
// RÉCUPÉRATION DES DONNÉES
// ================================================================

$filtre_statut = $_GET['statut'] ?? 'en_attente';
if (!in_array($filtre_statut, ['en_attente', 'valide', 'refuse', 'tous'])) {
    $filtre_statut = 'en_attente';
}

// Statistiques
$stats = [
    'en_attente' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'en_attente'")->fetchColumn(),
    'valide' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide'")->fetchColumn(),
    'refuse' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'refuse'")->fetchColumn(),
    'total' => $pdo->query("SELECT COUNT(*) FROM medecins")->fetchColumn(),
];

// Liste des médecins
$sql = "SELECT * FROM medecins";
if ($filtre_statut !== 'tous') {
    $sql .= " WHERE statut = :statut";
}
$sql .= " ORDER BY date_inscription DESC";

$stmt = $pdo->prepare($sql);
if ($filtre_statut !== 'tous') {
    $stmt->execute([':statut' => $filtre_statut]);
} else {
    $stmt->execute();
}
$medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Notifications non lues
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type_user = 'admin' AND lu = 0")->fetchColumn();

$initiales = strtoupper(substr($admin['nom'], 0, 1));
$date_actuelle = date('d/m/Y');

$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$jour_actuel = $jours_fr[date('l')];

$couleurs_spec = [
    'Cardiologie' => ['bg' => '#EFF6FF', 'color' => '#0077B6'],
    'Pédiatrie' => ['bg' => '#FDF4FF', 'color' => '#7209B7'],
    'Dermatologie' => ['bg' => '#FFF7ED', 'color' => '#F77F00'],
    'Gynécologie' => ['bg' => '#F0FDF4', 'color' => '#006D77'],
    'Neurologie' => ['bg' => '#EEF0FF', 'color' => '#3A0CA3'],
    'default' => ['bg' => '#F1F5F9', 'color' => '#64748B'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Validation Médecins</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--teal:#006D77;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F0F4FF;display:flex;min-height:100vh}

        /* ===== SIDEBAR ===== */
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
        .admin-av{width:32px;height:32px;border-radius:8px;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#fff;font-weight:700;flex-shrink:0}
        .admin-name{font-size:.8rem;font-weight:600;color:#fff}
        .admin-role{font-size:.68rem;color:#94A3B8}

        /* ===== MAIN ===== */
        .main-content{margin-left:220px;flex:1;display:flex;flex-direction:column;min-height:100vh}

        /* ===== TOP BAR ===== */
        .top-bar{background:#fff;padding:.9rem 1.8rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--lgray);box-shadow:0 1px 6px rgba(0,0,0,.05);flex-wrap:wrap;gap:.5rem}
        .top-bar h2{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark)}
        .top-right{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
        .notif-btn{position:relative;cursor:pointer;color:var(--gray);font-size:1.1rem;text-decoration:none}
        .notif-btn .nb{position:absolute;top:-6px;right:-7px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700}
        .date-badge{background:var(--light);color:var(--blue);padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:600}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:var(--dark);cursor:pointer;padding:.3rem}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:99}

        /* ===== PAGE CONTENT ===== */
        .page-content{padding:1.5rem 1.8rem;flex:1}

        /* ===== ALERT MESSAGES ===== */
        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

        /* ===== STATS ===== */
        .stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .st-card{background:#fff;border-radius:12px;padding:1rem 1.3rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.04);display:flex;align-items:center;gap:.8rem;flex:1;min-width:160px}
        .st-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .st-num{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;color:var(--dark)}
        .st-lbl{font-size:.75rem;color:var(--gray)}

        /* ===== TABS ===== */
        .tabs{display:flex;gap:.3rem;background:#fff;border-radius:12px;padding:.3rem;margin-bottom:1.2rem;border:1px solid var(--lgray);box-shadow:0 2px 6px rgba(0,0,0,.04);flex-wrap:wrap}
        .tab{flex:1;padding:.6rem;text-align:center;border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray);min-width:80px;text-decoration:none}
        .tab.active{background:var(--orange);color:#fff}
        .tab .cnt{background:rgba(0,0,0,.15);color:#fff;border-radius:10px;padding:.05rem .4rem;font-size:.68rem;margin-left:.3rem;display:inline-block}
        .tab.active .cnt{background:rgba(255,255,255,.3)}

        /* ===== FILTERS ===== */
        .filter-bar{background:#fff;border-radius:12px;padding:.9rem 1.1rem;border:1px solid var(--lgray);margin-bottom:1.2rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;box-shadow:0 2px 6px rgba(0,0,0,.04)}
        .filter-bar select,.filter-bar input{padding:.5rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.83rem;outline:none;background:#fff;font-family:'DM Sans',sans-serif}
        .filter-bar select:focus,.filter-bar input:focus{border-color:var(--orange)}
        .filter-btn{padding:.5rem 1.2rem;background:var(--orange);color:#fff;border:none;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer}

        /* ===== MEDECIN CARDS ===== */
        .med-card{background:#fff;border-radius:14px;border:1px solid var(--lgray);margin-bottom:1rem;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .med-card.urgent{border-left:4px solid var(--orange)}
        .med-header{display:flex;gap:1rem;align-items:flex-start;padding:1.2rem;flex-wrap:wrap}
        .med-avatar{width:62px;height:62px;border-radius:14px;background:linear-gradient(135deg,var(--teal),var(--blue));display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0}
        .med-info{flex:1;min-width:200px}
        .med-name{font-family:'Sora',sans-serif;font-size:1rem;font-weight:800;color:#1E293B}
        .med-spec{display:inline-block;background:#FFF7ED;color:var(--orange);font-size:.73rem;font-weight:700;padding:.2rem .55rem;border-radius:5px;margin-left:.4rem}
        .med-meta{display:flex;gap:1rem;margin-top:.4rem;flex-wrap:wrap}
        .med-meta span{font-size:.78rem;color:var(--gray);display:flex;align-items:center;gap:.3rem}
        .med-status{margin-left:auto;flex-shrink:0}
        .badge-att{background:#FFF7ED;color:#C2410C;border:1px solid #FED7AA;padding:.25rem .65rem;border-radius:15px;font-size:.73rem;font-weight:700;display:inline-block}
        .badge-val{background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0;padding:.25rem .65rem;border-radius:15px;font-size:.73rem;font-weight:700;display:inline-block}
        .badge-ref{background:#FFF5F5;color:var(--red);border:1px solid #FECACA;padding:.25rem .65rem;border-radius:15px;font-size:.73rem;font-weight:700;display:inline-block}

        /* ===== DETAIL ===== */
        .med-detail{padding:0 1.2rem 1.2rem;border-top:1px solid var(--lgray)}
        .detail-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:1rem}
        .d-block h6{font-size:.72rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem}
        .d-block p{font-size:.83rem;color:#334155;background:var(--light);padding:.45rem .65rem;border-radius:7px}

        /* ===== DOCS ===== */
        .docs-row{display:flex;gap:.6rem;margin-top:.8rem;flex-wrap:wrap}
        .doc-preview{display:flex;align-items:center;gap:.5rem;background:#F8FAFF;border:1px solid var(--lgray);border-radius:8px;padding:.5rem .8rem;font-size:.78rem;color:var(--blue);font-weight:600;cursor:pointer;transition:.15s}
        .doc-preview:hover{background:#EFF6FF;border-color:var(--blue)}

        /* ===== ACTION PANEL ===== */
        .action-panel{display:flex;gap:.7rem;align-items:flex-start;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--lgray);flex-wrap:wrap}
        .action-panel textarea{flex:1;min-width:250px;padding:.65rem .9rem;border:1.5px solid var(--lgray);border-radius:9px;font-size:.83rem;font-family:'DM Sans',sans-serif;resize:none;height:70px;outline:none}
        .action-panel textarea:focus{border-color:var(--teal)}
        .panel-btns{display:flex;flex-direction:column;gap:.5rem}
        .btn-val{padding:.65rem 1.5rem;background:var(--green);color:#fff;border:none;border-radius:9px;font-size:.88rem;font-weight:700;cursor:pointer;white-space:nowrap;box-shadow:0 3px 10px rgba(45,198,83,.3)}
        .btn-val:hover{background:#25B347}
        .btn-ref{padding:.65rem 1.5rem;background:var(--red);color:#fff;border:none;border-radius:9px;font-size:.88rem;font-weight:700;cursor:pointer;white-space:nowrap}
        .btn-ref:hover{background:#B91C1C}
        .btn-info{padding:.65rem 1.3rem;background:transparent;color:var(--blue);border:1.5px solid var(--blue);border-radius:9px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;text-align:center}
        .btn-info:hover{background:#EFF6FF}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:15px 1.8rem;text-align:center;font-size:.78rem;flex-shrink:0}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .detail-grid{grid-template-columns:1fr 1fr}
        }

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
            .detail-grid{grid-template-columns:1fr}
            .action-panel{flex-direction:column}
            .action-panel textarea{min-width:100%}
            .panel-btns{flex-direction:row;flex-wrap:wrap;width:100%}
            .panel-btns button{flex:1;min-width:100px}
        }

        @media(max-width:480px){
            .stats-row{flex-direction:column}
            .st-card{min-width:100%}
            .tabs{flex-direction:column}
            .tab{min-width:100%}
            .filter-bar{flex-direction:column;align-items:stretch}
            .filter-bar select,.filter-bar input{width:100%}
            .med-header{flex-direction:column;align-items:center;text-align:center}
            .med-avatar{width:50px;height:50px;font-size:1.2rem}
            .med-meta{justify-content:center}
            .med-status{margin-left:0}
            .top-right .date-badge{font-size:.65rem;padding:.2rem .5rem}
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
        <div class="role">🛡 Administrateur</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="dashboard_admin.php" class="nav-item">
            <span class="icon">📊</span> Dashboard
        </a>
        <a href="gestion_users.php" class="nav-item">
            <span class="icon">👥</span> Utilisateurs
            <span class="badge"><?=$stats['en_attente']?></span>
        </a>
        <a href="validation_medecins.php" class="nav-item active">
            <span class="icon">✅</span> Validation Médecins
            <span class="badge"><?=$stats['en_attente']?></span>
        </a>
        <a href="gestion_rdv.php" class="nav-item">
            <span class="icon">📅</span> Gestion RDV
        </a>
        <div class="nav-section">Sécurité</div>
        <a href="securite.php" class="nav-item">
            <span class="icon">🔒</span> Journal Accès
        </a>
        <a href="securite.php" class="nav-item">
            <span class="icon">⚙️</span> Paramètres
        </a>
        <a href="logout.php" class="nav-item">
            <span class="icon">🚪</span> Déconnexion
        </a>
    </nav>
    <div class="sidebar-footer">
        <div style="display:flex;align-items:center;gap:.6rem">
            <div class="admin-av"><?=$initiales?></div>
            <div>
                <div class="admin-name"><?=htmlspecialchars($admin['nom'])?></div>
                <div class="admin-role">Administrateur</div>
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
            <h2>✅ Validation des Médecins</h2>
        </div>
        <div class="top-right">
            <?php if($stats['en_attente'] > 0): ?>
                <span style="background:#FFF7ED;color:var(--orange);padding:.3rem .8rem;border-radius:7px;font-size:.78rem;font-weight:700">
                    ⚠️ <?=$stats['en_attente']?> en attente
                </span>
            <?php endif; ?>
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

        <!-- ALERT MESSAGES -->
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
                <div class="st-icon" style="background:#FFF7ED">⏳</div>
                <div>
                    <div class="st-num" style="color:var(--orange)"><?=$stats['en_attente']?></div>
                    <div class="st-lbl">En attente</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#F0FDF4">✅</div>
                <div>
                    <div class="st-num" style="color:var(--green)"><?=$stats['valide']?></div>
                    <div class="st-lbl">Validés</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#FFF5F5">❌</div>
                <div>
                    <div class="st-num" style="color:var(--red)"><?=$stats['refuse']?></div>
                    <div class="st-lbl">Refusés</div>
                </div>
            </div>
            <div class="st-card">
                <div class="st-icon" style="background:#EFF6FF">📋</div>
                <div>
                    <div class="st-num" style="color:var(--blue)"><?=$stats['total']?></div>
                    <div class="st-lbl">Total demandes</div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs">
            <a href="?statut=en_attente" class="tab <?=$filtre_statut==='en_attente'?'active':''?>">
                ⏳ En attente <span class="cnt"><?=$stats['en_attente']?></span>
            </a>
            <a href="?statut=valide" class="tab <?=$filtre_statut==='valide'?'active':''?>">
                ✅ Validés <span class="cnt" style="background:rgba(0,0,0,.1)"><?=$stats['valide']?></span>
            </a>
            <a href="?statut=refuse" class="tab <?=$filtre_statut==='refuse'?'active':''?>">
                ❌ Refusés <span class="cnt" style="background:rgba(0,0,0,.1)"><?=$stats['refuse']?></span>
            </a>
            <a href="?statut=tous" class="tab <?=$filtre_statut==='tous'?'active':''?>">
                📋 Tous <span class="cnt" style="background:rgba(0,0,0,.1)"><?=$stats['total']?></span>
            </a>
        </div>

        <!-- FILTERS -->
        <div class="filter-bar">
            <span style="font-size:.82rem;font-weight:600;color:#334155">Filtrer :</span>
            <select>
                <option>Toutes les spécialités</option>
                <?php 
                $specs = $pdo->query("SELECT DISTINCT specialite FROM medecins ORDER BY specialite")->fetchAll(PDO::FETCH_COLUMN);
                foreach($specs as $spec): 
                ?>
                    <option><?=htmlspecialchars($spec)?></option>
                <?php endforeach; ?>
            </select>
            <select>
                <option>Toutes les villes</option>
                <?php 
                $villes = $pdo->query("SELECT DISTINCT ville FROM medecins ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);
                foreach($villes as $ville): 
                ?>
                    <option><?=htmlspecialchars($ville)?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" value="<?=date('Y-m-d')?>">
            <input type="text" placeholder="Rechercher un médecin...">
            <button class="filter-btn">🔍 Filtrer</button>
        </div>

        <!-- LISTE DES MÉDECINS -->
        <?php if(empty($medecins)): ?>
            <div style="background:#fff;border-radius:14px;padding:2rem;text-align:center;color:var(--gray);border:1px solid var(--lgray);">
                <div style="font-size:3rem;margin-bottom:1rem;">📭</div>
                <p style="font-size:1rem;font-weight:600;">Aucun médecin dans cette catégorie</p>
                <p style="font-size:.85rem;margin-top:.3rem;">Aucune demande en attente pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach($medecins as $medecin): 
                $is_urgent = $medecin['statut'] === 'en_attente';
                $couleur_spec = $couleurs_spec[$medecin['specialite']] ?? $couleurs_spec['default'];
                $avatar_initiales = strtoupper(substr($medecin['prenom'],0,1).substr($medecin['nom'],0,1));
                $badge_class = $medecin['statut'] === 'en_attente' ? 'badge-att' : ($medecin['statut'] === 'valide' ? 'badge-val' : 'badge-ref');
                $badge_label = $medecin['statut'] === 'en_attente' ? '⏳ En attente' : ($medecin['statut'] === 'valide' ? '✅ Validé' : '❌ Refusé');
            ?>
                <div class="med-card <?=$is_urgent?'urgent':''?>">
                    <div class="med-header">
                        <div class="med-avatar" style="background:<?=$couleur_spec['color']?>">👨‍⚕️</div>
                        <div class="med-info">
                            <div>
                                <span class="med-name">Dr. <?=htmlspecialchars($medecin['prenom'].' '.$medecin['nom'])?></span>
                                <span class="med-spec" style="background:<?=$couleur_spec['bg']?>;color:<?=$couleur_spec['color']?>">
                                    <?=htmlspecialchars($medecin['specialite'])?>
                                </span>
                            </div>
                            <div class="med-meta">
                                <span>📧 <?=htmlspecialchars($medecin['email'])?></span>
                                <span>📍 <?=htmlspecialchars($medecin['ville'])?></span>
                                <?php if(!empty($medecin['cabinet'])): ?>
                                    <span>🏥 <?=htmlspecialchars($medecin['cabinet'])?></span>
                                <?php endif; ?>
                                <span>📅 Inscrit le <?=date('d/m/Y', strtotime($medecin['date_inscription']))?></span>
                            </div>
                        </div>
                        <div class="med-status">
                            <span class="<?=$badge_class?>"><?=$badge_label?></span>
                        </div>
                    </div>

                    <!-- DETAIL EXPANDED (seulement si en attente) -->
                    <?php if($medecin['statut'] === 'en_attente'): ?>
                    <div class="med-detail">
                        <div class="detail-grid">
                            <div class="d-block">
                                <h6>Numéro d'ordre</h6>
                                <p><?=htmlspecialchars($medecin['num_ordre'])?></p>
                            </div>
                            <div class="d-block">
                                <h6>Téléphone</h6>
                                <p><?=htmlspecialchars($medecin['telephone'] ?: 'Non renseigné')?></p>
                            </div>
                            <div class="d-block">
                                <h6>Cabinet</h6>
                                <p><?=htmlspecialchars($medecin['cabinet'] ?: 'Non renseigné')?></p>
                            </div>
                            <?php if(!empty($medecin['biographie'])): ?>
                            <div class="d-block">
                                <h6>Biographie</h6>
                                <p><?=htmlspecialchars(mb_substr($medecin['biographie'], 0, 100)).(mb_strlen($medecin['biographie'])>100?'...':'')?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($medecin['photo'])): ?>
                        <div class="docs-row">
                            <div class="doc-preview">📄 Photo de profil</div>
                        </div>
                        <?php endif; ?>

                        <!-- ACTION PANEL -->
                        <div class="action-panel">
                            <form method="POST" action="validation_medecins.php" style="flex:1;min-width:250px;">
                                <input type="hidden" name="medecin_id" value="<?=$medecin['id']?>">
                                <textarea name="message" placeholder="Message optionnel pour le médecin (raison validation ou refus)...">Bonjour Dr. <?=htmlspecialchars($medecin['prenom'])?>, votre dossier a été examiné.</textarea>
                            </form>
                            <div class="panel-btns">
                                <form method="POST" action="validation_medecins.php" style="display:inline;">
                                    <input type="hidden" name="medecin_id" value="<?=$medecin['id']?>">
                                    <input type="hidden" name="action" value="valider">
                                    <button type="submit" class="btn-val">✅ Valider le compte</button>
                                </form>
                                <form method="POST" action="validation_medecins.php" style="display:inline;">
                                    <input type="hidden" name="medecin_id" value="<?=$medecin['id']?>">
                                    <input type="hidden" name="action" value="refuser">
                                    <input type="hidden" name="motif_refus" value="Dossier incomplet">
                                    <button type="submit" class="btn-ref" onclick="return confirm('Êtes-vous sûr de vouloir refuser ce médecin ?')">❌ Refuser</button>
                                </form>
                                <a href="profil_medecin.php?id=<?=$medecin['id']?>" class="btn-info">📋 Voir profil complet</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- ===== FOOTER ===== -->
    <footer>
        <p>
            <strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025 
            &nbsp;|&nbsp; Page designer par : <strong>Aicha Djigo (E7)</strong> — validation_medecins.php
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