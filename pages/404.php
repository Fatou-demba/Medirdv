<?php
// ================================================================
// 404.php — Page d'erreur 404 (Design Figma)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Récupérer l'URL demandée
$requested_url = $_SERVER['REQUEST_URI'] ?? '/';
$error_code = 404;

// Si déjà connecté, adapter les liens
$is_logged_in = isLoggedIn();
$user_role = $is_logged_in ? getRole() : '';

// Déterminer le lien d'accueil selon le rôle
if ($is_logged_in) {
    if ($user_role === 'admin') {
        $home_link = 'dashboard_admin.php';
        $home_label = 'Tableau de bord Admin';
    } elseif ($user_role === 'medecin') {
        $home_link = 'dashboard_medecin.php';
        $home_label = 'Tableau de bord Médecin';
    } else {
        $home_link = 'dashboard_patient.php';
        $home_label = 'Tableau de bord Patient';
    }
} else {
    $home_link = 'login.php';
    $home_label = 'Se connecter';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Page 404</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:var(--light);min-height:100vh;display:flex;flex-direction:column}

        /* ===== NAVBAR ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;flex-wrap:wrap;gap:.5rem}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none;flex-wrap:wrap}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .nav-btns{display:flex;gap:.7rem;flex-wrap:wrap}
        .btn-o{padding:.45rem 1.1rem;border:1.5px solid #CBD5E1;color:#CBD5E1;border-radius:8px;font-size:.82rem;cursor:pointer;background:transparent;transition:.2s;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-o:hover{background:rgba(255,255,255,.1);color:#fff}
        .btn-p{padding:.45rem 1.2rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-p:hover{background:#25B347;transform:translateY(-1px)}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== ERROR BODY ===== */
        .error-body{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem 1.5rem;background:linear-gradient(135deg,#EEF2FF 0%,#F0F9FF 50%,#F0FDF4 100%)}

        .error-card{background:#fff;border-radius:24px;padding:3rem 2.5rem;max-width:650px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.08);border:1px solid var(--lgray);position:relative;overflow:hidden}
        .error-card::before{content:'';position:absolute;top:-100px;right:-100px;width:200px;height:200px;background:radial-gradient(circle,rgba(45,198,83,.05) 0%,transparent 70%);border-radius:50%}
        .error-card::after{content:'';position:absolute;bottom:-80px;left:-80px;width:180px;height:180px;background:radial-gradient(circle,rgba(0,119,182,.05) 0%,transparent 70%);border-radius:50%}

        /* ===== BIG 404 ===== */
        .big-404{font-family:'Sora',sans-serif;font-size:8rem;font-weight:800;line-height:1;background:linear-gradient(135deg,var(--blue),var(--green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;position:relative;letter-spacing:-4px}
        .big-404::after{content:'';position:absolute;bottom:-8px;left:50%;transform:translateX(-50%);width:60%;height:4px;background:linear-gradient(90deg,var(--blue),var(--green));border-radius:2px}

        /* ===== DOCTOR ICON ===== */
        .doc-icon{font-size:4rem;margin:1rem 0;display:block;animation:float 3s ease-in-out infinite}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}

        /* ===== DOTS ===== */
        .dots{display:flex;justify-content:center;gap:.6rem;margin:0.8rem 0 1.2rem}
        .dot{width:10px;height:10px;border-radius:50%;animation:bounce 1.4s ease-in-out infinite}
        .dot:nth-child(1){background:var(--blue);animation-delay:0s}
        .dot:nth-child(2){background:var(--green);animation-delay:.2s}
        .dot:nth-child(3){background:var(--orange);animation-delay:.4s}
        @keyframes bounce{0%,80%,100%{transform:scale(0.8);opacity:.6}40%{transform:scale(1.2);opacity:1}}

        /* ===== TEXTS ===== */
        .error-title{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:var(--dark);margin-bottom:.8rem}
        .error-msg{font-size:.95rem;color:var(--gray);line-height:1.7;margin-bottom:.5rem}
        .error-msg strong{color:var(--dark)}

        /* ===== ERROR CODE ===== */
        .error-code{background:var(--light);border:1px solid var(--lgray);border-radius:10px;padding:.6rem 1.2rem;display:inline-block;font-family:'Courier New',monospace;font-size:.78rem;color:var(--red);font-weight:700;margin-bottom:1.8rem;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

        /* ===== SEARCH BAR ===== */
        .search-bar{display:flex;margin-bottom:1.5rem;gap:.5rem;flex-wrap:wrap}
        .search-bar input{flex:1;min-width:200px;padding:.7rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.88rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s}
        .search-bar input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
        .search-bar button{padding:.7rem 1.2rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s;display:flex;align-items:center;gap:.4rem}
        .search-bar button:hover{background:var(--dark)}

        /* ===== SUGGESTIONS ===== */
        .suggestions{background:var(--light);border-radius:14px;padding:1.2rem;margin-bottom:2rem;text-align:left}
        .suggestions h4{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:var(--dark);margin-bottom:.8rem;display:flex;align-items:center;gap:.4rem}
        .sugg-list{display:flex;flex-direction:column;gap:.5rem}
        .sugg-item{display:flex;align-items:center;gap:.7rem;padding:.5rem .8rem;background:#fff;border-radius:9px;border:1px solid var(--lgray);cursor:pointer;transition:.15s;text-decoration:none}
        .sugg-item:hover{border-color:var(--blue);background:#EFF6FF;transform:translateX(4px)}
        .sugg-icon{font-size:1.1rem;flex-shrink:0}
        .sugg-text{font-size:.83rem;font-weight:600;color:#334155}
        .sugg-text small{display:block;font-weight:400;color:var(--gray);font-size:.75rem;margin-top:.1rem}
        .sugg-arrow{margin-left:auto;color:var(--blue);font-size:.85rem;transition:.2s}
        .sugg-item:hover .sugg-arrow{transform:translateX(4px)}

        /* ===== BUTTONS ===== */
        .btn-group{display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap}
        .btn-home{padding:.82rem 2rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 15px rgba(0,119,182,.3);transition:.2s;display:flex;align-items:center;gap:.5rem;text-decoration:none}
        .btn-home:hover{background:var(--dark);transform:translateY(-2px);box-shadow:0 6px 25px rgba(0,119,182,.4);color:#fff}
        .btn-back{padding:.82rem 1.5rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:10px;font-size:.92rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s;display:flex;align-items:center;gap:.4rem;text-decoration:none}
        .btn-back:hover{border-color:var(--blue);color:var(--blue)}
        .btn-support{padding:.82rem 1.5rem;background:var(--light);color:var(--blue);border:1.5px solid var(--blue);border-radius:10px;font-size:.92rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s;display:flex;align-items:center;gap:.4rem;text-decoration:none}
        .btn-support:hover{background:var(--blue);color:#fff}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;flex-shrink:0}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:768px){
            .nav-links{display:none;width:100%;flex-direction:column;align-items:center;gap:.5rem;padding:.5rem 0}
            .nav-links.open{display:flex}
            .nav-links a{padding:.3rem 0}
            .hamburger{display:block}
            .nav-btns{flex-wrap:wrap;justify-content:center}
            
            .error-card{padding:2rem 1.5rem}
            .big-404{font-size:5rem}
            .doc-icon{font-size:3rem}
            .error-title{font-size:1.3rem}
            .error-msg{font-size:.85rem}
            .error-code{font-size:.7rem;padding:.4rem .8rem;white-space:normal;word-break:break-all}
            .search-bar{flex-direction:column}
            .search-bar input{min-width:100%}
            .btn-group{flex-direction:column;align-items:stretch}
            .btn-home,.btn-back,.btn-support{justify-content:center}
            .sugg-item{padding:.4rem .6rem}
            .sugg-text{font-size:.78rem}
        }

        @media(max-width:480px){
            .error-body{padding:1.5rem .8rem}
            .error-card{padding:1.5rem 1rem}
            .big-404{font-size:4rem}
            .big-404::after{width:40%}
            .doc-icon{font-size:2.5rem;margin:.5rem 0}
            .dots{gap:.4rem}
            .dot{width:8px;height:8px}
            .error-title{font-size:1.1rem}
            .error-msg{font-size:.8rem}
            .suggestions{padding:.8rem}
            .sugg-list{gap:.3rem}
            .sugg-item{padding:.3rem .5rem}
            .sugg-text{font-size:.72rem}
            .sugg-text small{font-size:.65rem}
            .btn-home,.btn-back,.btn-support{padding:.65rem 1rem;font-size:.82rem}
            nav{padding:0 3%;height:56px}
            .nav-logo{font-size:1.1rem}
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav>
    <a href="<?=$home_link?>" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="<?=$home_link?>">Accueil</a></li>
        <li><a href="liste_medecins.php">Médecins</a></li>
        <li><a href="contact.php">Contact</a></li>
        <li><a href="aide.php">Aide</a></li>
    </ul>
    <div class="nav-btns">
        <?php if(!$is_logged_in): ?>
            <a href="login.php" class="btn-o"><i class="fas fa-sign-in-alt"></i> Connexion</a>
            <a href="register_patient.php" class="btn-p"><i class="fas fa-user-plus"></i> S'inscrire</a>
        <?php else: ?>
            <a href="<?=$home_link?>" class="btn-p"><i class="fas fa-home"></i> Tableau de bord</a>
            <a href="logout.php" class="btn-o"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== ERROR BODY ===== -->
<div class="error-body">
    <div class="error-card">

        <!-- BIG 404 -->
        <div class="big-404">404</div>

        <!-- FLOATING DOCTOR -->
        <span class="doc-icon">🏥</span>

        <!-- DOTS ANIMATION -->
        <div class="dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>

        <h2 class="error-title">Oups ! Page introuvable</h2>
        <p class="error-msg">
            La page que vous cherchez n'existe pas ou a été déplacée.<br>
            Vérifiez l'URL ou retournez à l'accueil.
        </p>

        <!-- ERROR CODE -->
        <div class="error-code">
            Erreur <?=$error_code?> — Page Not Found — <?=htmlspecialchars($requested_url)?>
        </div>

        <!-- SEARCH BAR -->
        <form action="recherche.php" method="GET" class="search-bar">
            <input type="text" name="q" placeholder="Rechercher un médecin, une spécialité..." aria-label="Rechercher">
            <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
        </form>

        <!-- SUGGESTIONS -->
        <div class="suggestions">
            <h4>💡 Peut-être cherchez-vous ?</h4>
            <div class="sugg-list">
                <a class="sugg-item" href="<?=$home_link?>">
                    <span class="sugg-icon">🏠</span>
                    <div class="sugg-text">
                        Page d'accueil
                        <small><?=$is_logged_in ? 'Retourner à votre tableau de bord' : 'Retourner à la page d\'accueil'?></small>
                    </div>
                    <span class="sugg-arrow">→</span>
                </a>
                <a class="sugg-item" href="liste_medecins.php">
                    <span class="sugg-icon">👨‍⚕️</span>
                    <div class="sugg-text">
                        Liste des médecins
                        <small>Trouver un spécialiste disponible</small>
                    </div>
                    <span class="sugg-arrow">→</span>
                </a>
                <?php if($is_logged_in): ?>
                    <?php if($user_role === 'patient'): ?>
                        <a class="sugg-item" href="mes_rdv.php">
                            <span class="sugg-icon">📅</span>
                            <div class="sugg-text">
                                Mes rendez-vous
                                <small>Voir et gérer vos RDV</small>
                            </div>
                            <span class="sugg-arrow">→</span>
                        </a>
                        <a class="sugg-item" href="dossier_patient.php">
                            <span class="sugg-icon">📋</span>
                            <div class="sugg-text">
                                Mon dossier médical
                                <small>Accéder à vos informations médicales</small>
                            </div>
                            <span class="sugg-arrow">→</span>
                        </a>
                    <?php elseif($user_role === 'medecin'): ?>
                        <a class="sugg-item" href="mon_agenda.php">
                            <span class="sugg-icon">📅</span>
                            <div class="sugg-text">
                                Mon agenda
                                <small>Gérer vos rendez-vous</small>
                            </div>
                            <span class="sugg-arrow">→</span>
                        </a>
                    <?php elseif($user_role === 'admin'): ?>
                        <a class="sugg-item" href="validation_medecins.php">
                            <span class="sugg-icon">✅</span>
                            <div class="sugg-text">
                                Validation médecins
                                <small>Gérer les demandes d'inscription</small>
                            </div>
                            <span class="sugg-arrow">→</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="sugg-item" href="login.php">
                        <span class="sugg-icon">🔑</span>
                        <div class="sugg-text">
                            Connexion
                            <small>Accéder à votre compte</small>
                        </div>
                        <span class="sugg-arrow">→</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- BUTTONS -->
        <div class="btn-group">
            <button class="btn-back" onclick="history.back()">
                <i class="fas fa-arrow-left"></i> Retour
            </button>
            <a href="<?=$home_link?>" class="btn-home">
                <i class="fas fa-home"></i> Aller à l'accueil
            </a>
            <a href="contact.php" class="btn-support">
                <i class="fas fa-envelope"></i> Contacter support
            </a>
        </div>

    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Dieynaba Diallo (E8)</strong> — 404.php</p>
</footer>

<!-- ============================================================
   SCRIPTS
============================================================ -->
<script>
    function toggleMenu() {
        const navLinks = document.getElementById('navLinks');
        navLinks.classList.toggle('open');
    }

    // Fermer le menu au clic sur un lien (mobile)
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('navLinks').classList.remove('open');
        });
    });
</script>
</body>
</html>