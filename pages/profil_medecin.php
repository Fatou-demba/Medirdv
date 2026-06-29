<?php
// ================================================================
// profil_medecin.php — Gestion du profil médecin
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn() || getRole() !== 'medecin') {
    redirect('login.php');
}

$medecin_id = $_SESSION['user_id'];

// Récupérer les infos médecin
$stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id AND statut = 'valide' LIMIT 1");
$stmt->execute([':id' => $medecin_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medecin) {
    session_destroy();
    redirect('login.php');
}

// ================================================================
// TRAITEMENT DES ACTIONS
// ================================================================

$erreur = '';
$succes = '';

// Modifier le profil (informations générales)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $specialite = trim($_POST['specialite'] ?? '');
    $num_ordre = trim($_POST['num_ordre'] ?? '');
    $cabinet = trim($_POST['cabinet'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $biographie = trim($_POST['biographie'] ?? '');
    
    if (empty($nom) || empty($prenom) || empty($email)) {
        $erreur = 'Le nom, prénom et email sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L\'adresse email n\'est pas valide.';
    } else {
        // Vérifier si l'email existe déjà (sauf pour ce médecin)
        $stmt = $pdo->prepare("SELECT id FROM medecins WHERE email = :email AND id != :id");
        $stmt->execute([':email' => $email, ':id' => $medecin_id]);
        if ($stmt->fetch()) {
            $erreur = 'Cet email est déjà utilisé par un autre médecin.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE medecins SET 
                    nom = :nom, prenom = :prenom, email = :email, telephone = :telephone,
                    specialite = :specialite, num_ordre = :num_ordre, cabinet = :cabinet,
                    ville = :ville, biographie = :biographie
                WHERE id = :id
            ");
            if ($stmt->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email,
                ':telephone' => $telephone,
                ':specialite' => $specialite,
                ':num_ordre' => $num_ordre,
                ':cabinet' => $cabinet,
                ':ville' => $ville,
                ':biographie' => $biographie,
                ':id' => $medecin_id
            ])) {
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['email'] = $email;
                $_SESSION['specialite'] = $specialite;
                $succes = '✅ Profil mis à jour avec succès !';
                // Recharger les données
                $medecin = array_merge($medecin, [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'specialite' => $specialite,
                    'num_ordre' => $num_ordre,
                    'cabinet' => $cabinet,
                    'ville' => $ville,
                    'biographie' => $biographie
                ]);
            } else {
                $erreur = '❌ Erreur lors de la mise à jour.';
            }
        }
    }
}

// Changer le mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $ancien_mdp = trim($_POST['ancien_mdp'] ?? '');
    $nouveau_mdp = trim($_POST['nouveau_mdp'] ?? '');
    $confirmer_mdp = trim($_POST['confirmer_mdp'] ?? '');
    
    if (empty($ancien_mdp) || empty($nouveau_mdp) || empty($confirmer_mdp)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (strlen($nouveau_mdp) < 6) {
        $erreur = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($nouveau_mdp !== $confirmer_mdp) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        // Vérifier l'ancien mot de passe
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM medecins WHERE id = :id");
        $stmt->execute([':id' => $medecin_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($ancien_mdp, $current['mot_de_passe'])) {
            $erreur = 'L\'ancien mot de passe est incorrect.';
        } else {
            $hashed = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE medecins SET mot_de_passe = :pwd WHERE id = :id");
            if ($stmt->execute([':pwd' => $hashed, ':id' => $medecin_id])) {
                $succes = '✅ Mot de passe modifié avec succès !';
            } else {
                $erreur = '❌ Erreur lors de la modification.';
            }
        }
    }
}

// Gérer les disponibilités
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_disponibilites') {
    $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    
    // Supprimer les anciennes disponibilités
    $pdo->prepare("DELETE FROM disponibilites WHERE id_medecin = :id")->execute([':id' => $medecin_id]);
    
    $inserted = 0;
    foreach ($jours as $jour) {
        $actif = isset($_POST['dispo_' . $jour]) && $_POST['dispo_' . $jour] === 'on';
        if ($actif) {
            $debut = trim($_POST['debut_' . $jour] ?? '');
            $fin = trim($_POST['fin_' . $jour] ?? '');
            $duree = (int)($_POST['duree_' . $jour] ?? 30);
            
            if (!empty($debut) && !empty($fin)) {
                $stmt = $pdo->prepare("
                    INSERT INTO disponibilites (id_medecin, jour_semaine, heure_debut, heure_fin, duree_rdv, actif)
                    VALUES (:id, :jour, :debut, :fin, :duree, 1)
                ");
                $stmt->execute([
                    ':id' => $medecin_id,
                    ':jour' => $jour,
                    ':debut' => $debut,
                    ':fin' => $fin,
                    ':duree' => $duree
                ]);
                $inserted++;
            }
        }
    }
    
    if ($inserted > 0) {
        $succes = '✅ ' . $inserted . ' disponibilité' . ($inserted > 1 ? 's' : '') . ' mises à jour avec succès !';
    } else {
        $succes = '✅ Aucune disponibilité activée. Tous les jours sont fermés.';
    }
}

// ================================================================
// RÉCUPÉRATION DES DISPONIBILITÉS
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM disponibilites WHERE id_medecin = :id AND actif = 1 ORDER BY FIELD(jour_semaine, 'lundi','mardi','mercredi','jeudi','vendredi','samedi')");
$stmt->execute([':id' => $medecin_id]);
$disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dispo_par_jour = [];
foreach ($disponibilites as $d) {
    $dispo_par_jour[$d['jour_semaine']] = $d;
}

// ================================================================
// STATISTIQUES POUR LE DASHBOARD MINI
// ================================================================
$stats = [
    'patients' => $pdo->query("SELECT COUNT(DISTINCT id_patient) FROM rendezvous WHERE id_medecin = $medecin_id AND statut = 'termine'")->fetchColumn(),
    'rdv_mois' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
    'consultations' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE id_medecin = $medecin_id")->fetchColumn(),
    'ordonnances' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE id_medecin = $medecin_id AND ordonnance IS NOT NULL AND ordonnance != ''")->fetchColumn(),
];

// Notifications
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type_user = 'medecin' AND lu = 0")->fetchColumn();

$initiales = strtoupper(substr($medecin['prenom'], 0, 1) . substr($medecin['nom'], 0, 1));
$date_actuelle = date('d/m/Y');
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$jour_actuel = $jours_fr[date('l')];

$en_attente = $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND statut = 'en_attente'")->fetchColumn();

$jours_semaine = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$jours_labels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Profil Médecin</title>
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
        .admin-av{width:32px;height:32px;border-radius:8px;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#fff;font-weight:700}
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
        .page-content{padding:1.5rem 1.8rem;flex:1}
        
        /* ===== ALERT ===== */
        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}
        .alert-info{background:#EFF6FF;border:1px solid #BFDBFE;color:var(--blue)}
        
        /* ===== PROFIL GRID ===== */
        .profil-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
        .profil-card{background:#fff;border-radius:14px;border:1px solid var(--lgray);padding:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .profil-card .card-header{display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem;padding-bottom:.6rem;border-bottom:1px solid var(--lgray)}
        .profil-card .card-header .icon{font-size:1.3rem;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center}
        .profil-card .card-header h3{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark)}
        .profil-card .card-header .sub{font-size:.75rem;color:var(--gray);font-weight:400}
        
        .form-group{margin-bottom:1rem}
        .form-group label{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:.3rem}
        .form-group label .req{color:var(--red)}
        .form-group input,.form-group select,.form-group textarea{
            width:100%;padding:.65rem .9rem;border:1.5px solid var(--lgray);border-radius:8px;
            font-size:.88rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B
        }
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{
            border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)
        }
        .form-group input.field-error{border-color:var(--red);background:#FFF5F5}
        .form-group .help-text{font-size:.7rem;color:var(--gray);margin-top:.2rem}
        .form-group textarea{resize:vertical;min-height:80px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        
        .btn{padding:.65rem 1.5rem;border-radius:8px;font-weight:600;font-size:.88rem;border:none;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:.5rem}
        .btn-primary{background:var(--blue);color:#fff}
        .btn-primary:hover{background:var(--dark);transform:translateY(-1px)}
        .btn-success{background:var(--green);color:#fff}
        .btn-success:hover{background:#25B347;transform:translateY(-1px)}
        .btn-danger{background:var(--red);color:#fff}
        .btn-danger:hover{background:#B91C1C;transform:translateY(-1px)}
        .btn-outline{background:transparent;color:var(--gray);border:1.5px solid var(--lgray)}
        .btn-outline:hover{border-color:var(--blue);color:var(--blue)}
        
        /* ===== STATS MINI ===== */
        .mini-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-top:1rem}
        .mini-stat{background:var(--light);border-radius:10px;padding:.8rem;text-align:center}
        .mini-stat .num{font-family:'Sora',sans-serif;font-size:1.3rem;font-weight:800;color:var(--dark)}
        .mini-stat .label{font-size:.7rem;color:var(--gray);margin-top:.1rem}
        
        /* ===== AVATAR ===== */
        .avatar-large{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--blue));display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:#fff;font-weight:700;flex-shrink:0;margin-bottom:.5rem}
        .avatar-section{display:flex;flex-direction:column;align-items:center;margin-bottom:1.2rem}
        .avatar-section .doc-role-badge{background:#FEF3C7;color:#D97706;padding:.2rem .8rem;border-radius:20px;font-size:.75rem;font-weight:700}
        .avatar-section .doc-spec-badge{background:#EFF6FF;color:var(--blue);padding:.2rem .8rem;border-radius:20px;font-size:.75rem;font-weight:700;margin-top:.2rem}
        
        /* ===== DISPONIBILITES ===== */
        .dispo-toggle{display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem}
        .dispo-toggle input[type="checkbox"]{width:18px;height:18px;accent-color:var(--teal);cursor:pointer}
        .dispo-toggle label{font-weight:600;font-size:.85rem;color:#1E293B;cursor:pointer}
        .dispo-times{display:flex;gap:.5rem;margin-left:2rem;margin-bottom:.5rem;flex-wrap:wrap}
        .dispo-times input[type="time"]{padding:.35rem .6rem;border:1.5px solid var(--lgray);border-radius:6px;font-size:.82rem;outline:none}
        .dispo-times input[type="time"]:focus{border-color:var(--teal)}
        .dispo-times select{padding:.35rem .6rem;border:1.5px solid var(--lgray);border-radius:6px;font-size:.82rem;outline:none}
        .dispo-divider{border-bottom:1px solid #F1F5F9;margin:.5rem 0}
        
        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:15px 1.8rem;text-align:center;font-size:.78rem;flex-shrink:0}
        footer strong{color:#fff}
        
        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .profil-grid{grid-template-columns:1fr}
            .mini-stats{grid-template-columns:1fr 1fr}
        }
        
        @media(max-width:768px){
            .admin-sidebar{transform:translateX(-100%)}
            .admin-sidebar.open{transform:translateX(0)}
            .sidebar-overlay.active{display:block}
            .main-content{margin-left:0}
            .hamburger{display:block}
            .top-bar{padding:.7rem 1rem}
            .page-content{padding:1rem}
            .form-row{grid-template-columns:1fr}
            .mini-stats{grid-template-columns:1fr 1fr}
            .dispo-times{margin-left:0}
        }
        
        @media(max-width:480px){
            .top-right .date-badge{font-size:.65rem;padding:.2rem .5rem}
            .profil-card{padding:1rem}
            .mini-stats{grid-template-columns:1fr}
            .btn{width:100%;justify-content:center}
            .form-actions{flex-direction:column}
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<div class="admin-sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo">Medi<span>RDV</span></div>
        <div class="role">👨‍⚕️ Espace Médecin</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="dashboard_medecin.php" class="nav-item">
            <span class="icon">📊</span>Dashboard
        </a>
        <a href="mon_agenda.php" class="nav-item">
            <span class="icon">📅</span>Mon Agenda
            <span class="badge"><?=$en_attente?></span>
        </a>
        <a href="gerer_rdv.php" class="nav-item">
            <span class="icon">📋</span>Gérer RDV
            <span class="badge"><?=$en_attente?></span>
        </a>
        <a href="historique_medecin.php" class="nav-item">
            <span class="icon">👥</span>Mes Patients
        </a>
        <div class="nav-section">Compte</div>
        <a href="profil_medecin.php" class="nav-item active">
            <span class="icon">👤</span>Mon Profil
        </a>
        <a href="logout.php" class="nav-item">
            <span class="icon">🚪</span>Déconnexion
        </a>
    </nav>
    <div class="sidebar-footer">
        <div style="display:flex;align-items:center;gap:.6rem">
            <div class="admin-av"><?=$initiales?></div>
            <div>
                <div style="font-size:.8rem;font-weight:600;color:#fff">Dr. <?=htmlspecialchars($medecin['nom'])?></div>
                <div style="font-size:.68rem;color:#94A3B8">Médecin</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    <div class="top-bar">
        <div style="display:flex;align-items:center;gap:1rem;">
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2>👤 Mon Profil</h2>
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

        <!-- MESSAGES -->
        <?php if($succes): ?>
            <div class="alert alert-success"><?=$succes?></div>
        <?php endif; ?>
        <?php if($erreur): ?>
            <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
        <?php endif; ?>

        <!-- ===== PROFIL GRID ===== -->
        <div class="profil-grid">

            <!-- ===== CARTE 1: INFOS PERSONNELLES ===== -->
            <div class="profil-card">
                <div class="card-header">
                    <div class="icon" style="background:#EFF6FF;color:var(--blue);">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <h3>Informations personnelles</h3>
                        <div class="sub">Modifiez vos informations de base</div>
                    </div>
                </div>

                <div class="avatar-section">
                    <div class="avatar-large"><?=$initiales?></div>
                    <span class="doc-role-badge">👨‍⚕️ Médecin</span>
                    <span class="doc-spec-badge">🫀 <?=htmlspecialchars($medecin['specialite'])?></span>
                </div>

                <form method="POST" action="profil_medecin.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom <span class="req">*</span></label>
                            <input type="text" id="nom" name="nom" 
                                   value="<?=htmlspecialchars($medecin['nom'])?>" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom <span class="req">*</span></label>
                            <input type="text" id="prenom" name="prenom"
                                   value="<?=htmlspecialchars($medecin['prenom'])?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse email <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               value="<?=htmlspecialchars($medecin['email'])?>" required>
                    </div>

                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone"
                               value="<?=htmlspecialchars($medecin['telephone'] ?? '')?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialite">Spécialité <span class="req">*</span></label>
                            <input type="text" id="specialite" name="specialite"
                                   value="<?=htmlspecialchars($medecin['specialite'])?>" required>
                        </div>
                        <div class="form-group">
                            <label for="num_ordre">Numéro d'ordre <span class="req">*</span></label>
                            <input type="text" id="num_ordre" name="num_ordre"
                                   value="<?=htmlspecialchars($medecin['num_ordre'])?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="cabinet">Cabinet <span class="req">*</span></label>
                            <input type="text" id="cabinet" name="cabinet"
                                   value="<?=htmlspecialchars($medecin['cabinet'])?>" required>
                        </div>
                        <div class="form-group">
                            <label for="ville">Ville <span class="req">*</span></label>
                            <input type="text" id="ville" name="ville"
                                   value="<?=htmlspecialchars($medecin['ville'])?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="biographie">Biographie</label>
                        <textarea id="biographie" name="biographie" placeholder="Présentez-vous aux patients..."><?=htmlspecialchars($medecin['biographie'] ?? '')?></textarea>
                        <div class="help-text">Une brève description de votre parcours et de votre pratique.</div>
                    </div>

                    <div class="form-group">
                        <label for="date_inscription">Date d'inscription</label>
                        <input type="text" id="date_inscription" 
                               value="<?=date('d/m/Y à H:i', strtotime($medecin['date_inscription']))?>"
                               disabled style="background:#F1F5F9;cursor:not-allowed;">
                        <div class="help-text">📅 Compte créé le <?=date('d/m/Y', strtotime($medecin['date_inscription']))?></div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Mettre à jour
                    </button>
                </form>
            </div>

            <!-- ===== CARTE 2: MOT DE PASSE + STATS ===== -->
            <div>
                <!-- Changer mot de passe -->
                <div class="profil-card">
                    <div class="card-header">
                        <div class="icon" style="background:#FFF7ED;color:var(--orange);">
                            <i class="fas fa-key"></i>
                        </div>
                        <div>
                            <h3>Changer le mot de passe</h3>
                            <div class="sub">Modifiez votre mot de passe de connexion</div>
                        </div>
                    </div>

                    <form method="POST" action="profil_medecin.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="ancien_mdp">Mot de passe actuel</label>
                            <input type="password" id="ancien_mdp" name="ancien_mdp"
                                   placeholder="••••••••" required>
                        </div>

                        <div class="form-group">
                            <label for="nouveau_mdp">Nouveau mot de passe</label>
                            <input type="password" id="nouveau_mdp" name="nouveau_mdp"
                                   placeholder="•••••••• (min 6 caractères)" required>
                            <div class="help-text">🔐 Minimum 6 caractères</div>
                        </div>

                        <div class="form-group">
                            <label for="confirmer_mdp">Confirmer le mot de passe</label>
                            <input type="password" id="confirmer_mdp" name="confirmer_mdp"
                                   placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-lock"></i> Changer le mot de passe
                        </button>
                    </form>
                </div>

                <!-- Mini Stats -->
                <div class="profil-card" style="margin-top:1.2rem;">
                    <div class="card-header">
                        <div class="icon" style="background:#F0FDF4;color:var(--green);">
                            <i class="fas fa-chart-simple"></i>
                        </div>
                        <div>
                            <h3>Statistiques rapides</h3>
                            <div class="sub">Aperçu de votre activité</div>
                        </div>
                    </div>

                    <div class="mini-stats">
                        <div class="mini-stat">
                            <div class="num" style="color:var(--blue);"><?=$stats['patients']?></div>
                            <div class="label">👤 Patients</div>
                        </div>
                        <div class="mini-stat">
                            <div class="num" style="color:var(--orange);"><?=$stats['rdv_mois']?></div>
                            <div class="label">📅 RDV ce mois</div>
                        </div>
                        <div class="mini-stat">
                            <div class="num" style="color:var(--green);"><?=$stats['consultations']?></div>
                            <div class="label">🏥 Consultations</div>
                        </div>
                        <div class="mini-stat">
                            <div class="num" style="color:var(--purple);"><?=$stats['ordonnances']?></div>
                            <div class="label">💊 Ordonnances</div>
                        </div>
                    </div>

                    <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;border-top:1px solid var(--lgray);padding-top:1rem;">
                        <a href="dashboard_medecin.php" class="btn btn-outline" style="flex:1;justify-content:center;font-size:.8rem;">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-danger" style="flex:1;justify-content:center;font-size:.8rem;">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== CARTE 3: DISPONIBILITÉS ===== -->
        <div class="profil-card" style="margin-top:1.5rem;">
            <div class="card-header">
                <div class="icon" style="background:#E0F2F1;color:var(--teal);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h3>Mes disponibilités</h3>
                    <div class="sub">Définissez vos créneaux de consultation</div>
                </div>
                <span style="margin-left:auto;font-size:.72rem;color:var(--gray);background:var(--light);padding:.2rem .6rem;border-radius:5px;">
                    <?=count($disponibilites)?> créneaux
                </span>
            </div>

            <form method="POST" action="profil_medecin.php#disponibilites">
                <input type="hidden" name="action" value="update_disponibilites">
                
                <?php foreach($jours_semaine as $i => $jour): 
                    $has_dispo = isset($dispo_par_jour[$jour]);
                    $dispo = $has_dispo ? $dispo_par_jour[$jour] : null;
                ?>
                <div class="dispo-toggle">
                    <input type="checkbox" id="dispo_<?=$jour?>" name="dispo_<?=$jour?>" <?=$has_dispo ? 'checked' : ''?> 
                           onchange="toggleDispo('<?=$jour?>')">
                    <label for="dispo_<?=$jour?>"><?=$jours_labels[$i]?></label>
                </div>
                <div class="dispo-times" id="times_<?=$jour?>" style="display:<?=$has_dispo ? 'flex' : 'none'?>;">
                    <input type="time" name="debut_<?=$jour?>" value="<?=$has_dispo ? $dispo['heure_debut'] : '09:00'?>">
                    <span style="color:var(--gray);font-weight:600;">à</span>
                    <input type="time" name="fin_<?=$jour?>" value="<?=$has_dispo ? $dispo['heure_fin'] : '17:00'?>">
                    <select name="duree_<?=$jour?>">
                        <option value="15" <?=$has_dispo && $dispo['duree_rdv'] == 15 ? 'selected' : ''?>>15 min</option>
                        <option value="20" <?=$has_dispo && $dispo['duree_rdv'] == 20 ? 'selected' : ''?>>20 min</option>
                        <option value="30" <?=(!$has_dispo || $dispo['duree_rdv'] == 30) ? 'selected' : ''?>>30 min</option>
                        <option value="45" <?=$has_dispo && $dispo['duree_rdv'] == 45 ? 'selected' : ''?>>45 min</option>
                        <option value="60" <?=$has_dispo && $dispo['duree_rdv'] == 60 ? 'selected' : ''?>>60 min</option>
                    </select>
                    <span style="font-size:.7rem;color:var(--gray);">durée</span>
                </div>
                <div class="dispo-divider"></div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary" style="margin-top:.5rem;">
                    <i class="fas fa-save"></i> Enregistrer les disponibilités
                </button>
            </form>
        </div>

        <!-- ===== CARTE 4: ACTIVITÉ RÉCENTE ===== -->
        <div class="profil-card" style="margin-top:1.5rem;">
            <div class="card-header">
                <div class="icon" style="background:#F3E8FF;color:var(--purple);">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h3>Dernières activités</h3>
                    <div class="sub">Vos dernières actions sur la plateforme</div>
                </div>
                <a href="securite.php" style="margin-left:auto;font-size:.78rem;color:var(--blue);font-weight:600;text-decoration:none;">
                    Voir tout →
                </a>
            </div>

            <?php
            $stmt = $pdo->prepare("SELECT * FROM journal_acces WHERE id_user = :id AND type_user = 'medecin' ORDER BY date_action DESC LIMIT 5");
            $stmt->execute([':id' => $medecin_id]);
            $activites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if(empty($activites)): ?>
                <div style="text-align:center;padding:1.5rem;color:var(--gray);">
                    <div style="font-size:2rem;margin-bottom:.3rem;">📭</div>
                    <p>Aucune activité récente.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <?php foreach($activites as $act): ?>
                        <div style="display:flex;align-items:center;gap:.8rem;padding:.5rem .8rem;background:var(--light);border-radius:8px;flex-wrap:wrap;">
                            <span style="font-size:1.1rem;">
                                <?php if(strpos($act['action'], 'connexion') !== false): ?>
                                    🔑
                                <?php elseif(strpos($act['action'], 'consultation') !== false): ?>
                                    📋
                                <?php elseif(strpos($act['action'], 'modification') !== false || strpos($act['action'], 'modif') !== false): ?>
                                    ✏️
                                <?php elseif(strpos($act['action'], 'validation') !== false): ?>
                                    ✅
                                <?php else: ?>
                                    📌
                                <?php endif; ?>
                            </span>
                            <span style="flex:1;font-size:.82rem;color:#1E293B;">
                                <?=htmlspecialchars(str_replace('_',' ',$act['action']))?>
                            </span>
                            <span style="font-size:.7rem;color:var(--gray);">
                                <?=date('d/m/Y H:i', strtotime($act['date_action']))?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ===== FOOTER ===== -->
    <footer>
        <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
        <p style="margin-top:.4rem">Page designer par : <strong>Mareme Faye (E5)</strong> — profil_medecin.php</p>
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

    function toggleDispo(jour) {
        const checkbox = document.getElementById('dispo_' + jour);
        const times = document.getElementById('times_' + jour);
        if (checkbox.checked) {
            times.style.display = 'flex';
        } else {
            times.style.display = 'none';
        }
    }
</script>
</body>
</html>