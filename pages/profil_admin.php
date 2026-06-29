<?php
// ================================================================
// profil_admin.php — Gestion du profil administrateur
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

if (!$admin) {
    session_destroy();
    redirect('login.php');
}

// ================================================================
// TRAITEMENT DES ACTIONS
// ================================================================

$erreur = '';
$succes = '';

// Modifier le profil (nom, email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($nom) || empty($email)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L\'adresse email n\'est pas valide.';
    } else {
        // Vérifier si l'email existe déjà (sauf pour cet admin)
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = :email AND id != :id");
        $stmt->execute([':email' => $email, ':id' => $admin_id]);
        if ($stmt->fetch()) {
            $erreur = 'Cet email est déjà utilisé par un autre administrateur.';
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET nom = :nom, email = :email WHERE id = :id");
            if ($stmt->execute([':nom' => $nom, ':email' => $email, ':id' => $admin_id])) {
                $_SESSION['nom'] = $nom;
                $_SESSION['email'] = $email;
                $succes = '✅ Profil mis à jour avec succès !';
                // Recharger les données
                $admin['nom'] = $nom;
                $admin['email'] = $email;
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
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM admins WHERE id = :id");
        $stmt->execute([':id' => $admin_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($ancien_mdp, $current['mot_de_passe'])) {
            $erreur = 'L\'ancien mot de passe est incorrect.';
        } else {
            $hashed = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET mot_de_passe = :pwd WHERE id = :id");
            if ($stmt->execute([':pwd' => $hashed, ':id' => $admin_id])) {
                $succes = '✅ Mot de passe modifié avec succès !';
            } else {
                $erreur = '❌ Erreur lors de la modification.';
            }
        }
    }
}

// ================================================================
// STATISTIQUES POUR LE DASHBOARD MINI
// ================================================================
$stats = [
    'admins' => $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn(),
    'medecins' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide'")->fetchColumn(),
    'patients' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif'")->fetchColumn(),
];

// Notifications
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type_user = 'admin' AND lu = 0")->fetchColumn();

$initiales = strtoupper(substr($admin['nom'], 0, 1));
$date_actuelle = date('d/m/Y');
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$jour_actuel = $jours_fr[date('l')];

$en_attente = $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'en_attente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Profil Administrateur</title>
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
        .form-group input,.form-group select{
            width:100%;padding:.65rem .9rem;border:1.5px solid var(--lgray);border-radius:8px;
            font-size:.88rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B
        }
        .form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
        .form-group input.field-error{border-color:var(--red);background:#FFF5F5}
        .form-group .help-text{font-size:.7rem;color:var(--gray);margin-top:.2rem}
        
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
        .mini-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;margin-top:1rem}
        .mini-stat{background:var(--light);border-radius:10px;padding:.8rem;text-align:center}
        .mini-stat .num{font-family:'Sora',sans-serif;font-size:1.3rem;font-weight:800;color:var(--dark)}
        .mini-stat .label{font-size:.7rem;color:var(--gray);margin-top:.1rem}
        
        /* ===== AVATAR ===== */
        .avatar-large{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--purple));display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:#fff;font-weight:700;flex-shrink:0;margin-bottom:.5rem}
        .avatar-section{display:flex;flex-direction:column;align-items:center;margin-bottom:1.2rem}
        .avatar-section .admin-role-badge{background:#FEF3C7;color:#D97706;padding:.2rem .8rem;border-radius:20px;font-size:.75rem;font-weight:700}
        
        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:15px 1.8rem;text-align:center;font-size:.78rem;flex-shrink:0}
        footer strong{color:#fff}
        
        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .profil-grid{grid-template-columns:1fr}
        }
        
        @media(max-width:768px){
            .admin-sidebar{transform:translateX(-100%)}
            .admin-sidebar.open{transform:translateX(0)}
            .sidebar-overlay.active{display:block}
            .main-content{margin-left:0}
            .hamburger{display:block}
            .top-bar{padding:.7rem 1rem}
            .page-content{padding:1rem}
            .mini-stats{grid-template-columns:1fr 1fr}
        }
        
        @media(max-width:480px){
            .top-right .date-badge{font-size:.65rem;padding:.2rem .5rem}
            .profil-card{padding:1rem}
            .mini-stats{grid-template-columns:1fr}
            .btn{width:100%;justify-content:center}
        }
    </style>
</head>
<body>

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
            <span class="icon">📊</span>Dashboard
        </a>
        <a href="gestion_users.php" class="nav-item">
            <span class="icon">👥</span>Utilisateurs
            <span class="badge"><?=$en_attente?></span>
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
        <div class="nav-section">Compte</div>
        <a href="profil_admin.php" class="nav-item active">
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
                <div style="font-size:.8rem;font-weight:600;color:#fff"><?=htmlspecialchars($admin['nom'])?></div>
                <div style="font-size:.68rem;color:#94A3B8">Administrateur</div>
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
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div>
                        <h3>Informations personnelles</h3>
                        <div class="sub">Modifiez vos informations de base</div>
                    </div>
                </div>

                <div class="avatar-section">
                    <div class="avatar-large"><?=$initiales?></div>
                    <span class="admin-role-badge">🛡 Administrateur</span>
                </div>

                <form method="POST" action="profil_admin.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="nom">Nom complet</label>
                        <input type="text" id="nom" name="nom" 
                               value="<?=htmlspecialchars($admin['nom'])?>"
                               class="<?=$erreur && strpos($erreur, 'nom')!==false?'field-error':''?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email"
                               value="<?=htmlspecialchars($admin['email'])?>"
                               class="<?=$erreur && strpos($erreur, 'email')!==false?'field-error':''?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="date_creation">Date de création</label>
                        <input type="text" id="date_creation" 
                               value="<?=date('d/m/Y à H:i', strtotime($admin['date_creation']))?>"
                               disabled style="background:#F1F5F9;cursor:not-allowed;">
                        <div class="help-text">📅 Compte créé le <?=date('d/m/Y', strtotime($admin['date_creation']))?></div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Mettre à jour
                    </button>
                </form>
            </div>

            <!-- ===== CARTE 2: CHANGER MOT DE PASSE + STATS ===== -->
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

                    <form method="POST" action="profil_admin.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="ancien_mdp">Mot de passe actuel</label>
                            <input type="password" id="ancien_mdp" name="ancien_mdp"
                                   placeholder="••••••••"
                                   class="<?=$erreur && strpos($erreur, 'ancien')!==false?'field-error':''?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="nouveau_mdp">Nouveau mot de passe</label>
                            <input type="password" id="nouveau_mdp" name="nouveau_mdp"
                                   placeholder="•••••••• (min 6 caractères)"
                                   class="<?=$erreur && strpos($erreur, 'nouveau')!==false?'field-error':''?>"
                                   required>
                            <div class="help-text">🔐 Minimum 6 caractères</div>
                        </div>

                        <div class="form-group">
                            <label for="confirmer_mdp">Confirmer le mot de passe</label>
                            <input type="password" id="confirmer_mdp" name="confirmer_mdp"
                                   placeholder="••••••••"
                                   class="<?=$erreur && strpos($erreur, 'correspondent')!==false?'field-error':''?>"
                                   required>
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
                            <div class="sub">Aperçu de la plateforme</div>
                        </div>
                    </div>

                    <div class="mini-stats">
                        <div class="mini-stat">
                            <div class="num" style="color:var(--blue);"><?=$stats['admins']?></div>
                            <div class="label">👤 Admins</div>
                        </div>
                        <div class="mini-stat">
                            <div class="num" style="color:var(--orange);"><?=$stats['medecins']?></div>
                            <div class="label">👨‍⚕️ Médecins</div>
                        </div>
                        <div class="mini-stat">
                            <div class="num" style="color:var(--green);"><?=$stats['patients']?></div>
                            <div class="label">👥 Patients</div>
                        </div>
                    </div>

                    <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;border-top:1px solid var(--lgray);padding-top:1rem;">
                        <a href="dashboard_admin.php" class="btn btn-outline" style="flex:1;justify-content:center;font-size:.8rem;">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-danger" style="flex:1;justify-content:center;font-size:.8rem;">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== CARTE 3: ACTIVITÉ RÉCENTE ===== -->
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
            $stmt = $pdo->prepare("SELECT * FROM journal_acces WHERE id_user = :id AND type_user = 'admin' ORDER BY date_action DESC LIMIT 5");
            $stmt->execute([':id' => $admin_id]);
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
                        <div style="display:flex;align-items:center;gap:.8rem;padding:.5rem .8rem;background:var(--light);border-radius:8px;">
                            <span style="font-size:1.1rem;">
                                <?php if(strpos($act['action'], 'connexion') !== false): ?>
                                    🔑
                                <?php elseif(strpos($act['action'], 'consultation') !== false): ?>
                                    📋
                                <?php elseif(strpos($act['action'], 'modification') !== false || strpos($act['action'], 'modif') !== false): ?>
                                    ✏️
                                <?php elseif(strpos($act['action'], 'suppression') !== false || strpos($act['action'], 'suppr') !== false): ?>
                                    🗑️
                                <?php else: ?>
                                    📌
                                <?php endif; ?>
                            </span>
                            <span style="flex:1;font-size:.82rem;color:#1E293B;">
                                <?=htmlspecialchars($act['action'])?>
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
        <p><strong>MediRDV</strong> — UNCHK | L3 IDA 2024-2025 &nbsp;|&nbsp; Page designer par : <strong>Aicha Djigo (E7)</strong> — profil_admin.php</p>
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