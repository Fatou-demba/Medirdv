<?php
// ================================================================
// a_propos.php — Page À propos de MediRDV
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Récupérer les statistiques pour la page
$stats = [
    'patients' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif'")->fetchColumn(),
    'medecins' => $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide'")->fetchColumn(),
    'rdv' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE statut = 'termine'")->fetchColumn(),
    'specialites' => $pdo->query("SELECT COUNT(DISTINCT specialite) FROM medecins WHERE statut = 'valide'")->fetchColumn(),
];

// Vérifier si l'utilisateur est connecté
$is_logged_in = isLoggedIn();
$user_role = $is_logged_in ? getRole() : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — À Propos</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--teal:#006D77;--purple:#7209B7;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .nav-links a.active{color:#fff;border-bottom:2px solid var(--green);padding-bottom:2px}
        .nav-btns{display:flex;gap:.7rem}
        .btn-o{padding:.45rem 1.1rem;border:1.5px solid #CBD5E1;color:#CBD5E1;border-radius:8px;font-size:.82rem;cursor:pointer;background:transparent;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-o:hover{background:rgba(255,255,255,.1);color:#fff}
        .btn-p{padding:.45rem 1.2rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-p:hover{background:#25B347}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== HERO ===== */
        .hero{background:linear-gradient(135deg,var(--dark) 0%,var(--blue) 60%,var(--teal) 100%);padding:4rem 5%;text-align:center;color:#fff;position:relative;overflow:hidden}
        .hero::before{content:'';position:absolute;top:-80px;right:-80px;width:350px;height:350px;background:radial-gradient(circle,rgba(45,198,83,.15),transparent 70%);border-radius:50%}
        .hero::after{content:'';position:absolute;bottom:-80px;left:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(0,150,199,.2),transparent 70%);border-radius:50%}
        .hero-badge{display:inline-flex;align-items:center;gap:.5rem;background:rgba(45,198,83,.2);border:1px solid rgba(45,198,83,.3);color:var(--green);padding:.4rem 1rem;border-radius:20px;font-size:.8rem;font-weight:600;margin-bottom:1.2rem;position:relative;z-index:1}
        .hero h1{font-family:'Sora',sans-serif;font-size:2.5rem;font-weight:800;line-height:1.2;margin-bottom:1rem;position:relative;z-index:1}
        .hero h1 em{color:var(--green);font-style:normal}
        .hero p{color:#CBD5E1;font-size:1rem;max-width:600px;margin:0 auto 2rem;line-height:1.7;position:relative;z-index:1}
        .hero-stats{display:flex;justify-content:center;gap:3rem;flex-wrap:wrap;position:relative;z-index:1}
        .h-stat .num{font-family:'Sora',sans-serif;font-size:2rem;font-weight:800;color:#fff}
        .h-stat .num span{color:var(--green)}
        .h-stat .lbl{font-size:.78rem;color:#CBD5E1;margin-top:.2rem}

        /* ===== SECTION ===== */
        .section{padding:3rem 5%;max-width:1050px;margin:0 auto}
        .section-hdr{text-align:center;margin-bottom:2.5rem}
        .section-hdr h2{font-family:'Sora',sans-serif;font-size:1.7rem;font-weight:800;color:var(--dark)}
        .section-hdr p{color:var(--gray);font-size:.95rem;margin-top:.5rem;max-width:560px;margin-left:auto;margin-right:auto;line-height:1.6}

        /* ===== VALEURS ===== */
        .values-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem;margin-bottom:3rem}
        .value-card{background:#fff;border-radius:16px;padding:1.5rem;border:1px solid var(--lgray);text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:.2s}
        .value-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.1)}
        .v-icon{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 1rem}
        .value-card h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:.5rem}
        .value-card p{font-size:.82rem;color:var(--gray);line-height:1.6}

        /* ===== TIMELINE ===== */
        .timeline{position:relative;padding:0 0 2rem}
        .timeline::before{content:'';position:absolute;left:50%;top:0;bottom:0;width:2px;background:var(--lgray);transform:translateX(-50%)}
        .tl-item{display:flex;gap:2rem;align-items:flex-start;margin-bottom:2rem;position:relative}
        .tl-item:nth-child(even){flex-direction:row-reverse}
        .tl-content{flex:1;background:#fff;border-radius:14px;padding:1.2rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .tl-dot{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;border:3px solid #fff;box-shadow:0 0 0 3px var(--lgray);z-index:1;margin-top:.5rem;color:#fff}
        .tl-year{font-family:'Sora',sans-serif;font-size:.75rem;font-weight:800;color:var(--blue);margin-bottom:.3rem}
        .tl-content h4{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:var(--dark);margin-bottom:.3rem}
        .tl-content p{font-size:.8rem;color:var(--gray);line-height:1.5}

        /* ===== TEAM ===== */
        .team-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:3rem}
        .team-card{background:#fff;border-radius:14px;padding:1.3rem;border:1px solid var(--lgray);text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:.2s}
        .team-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,.1)}
        .team-av{width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto .8rem;color:#fff;font-weight:700}
        .team-name{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:800;color:var(--dark)}
        .team-role{font-size:.75rem;color:var(--blue);font-weight:600;background:#EFF6FF;padding:.2rem .6rem;border-radius:5px;display:inline-block;margin-top:.2rem}
        .team-num{font-size:.72rem;color:var(--gray);margin-top:.4rem}

        /* ===== TECH ===== */
        .tech-section{background:var(--dark);padding:2.5rem 5%;text-align:center}
        .tech-section h2{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:.5rem}
        .tech-section p{color:#94A3B8;font-size:.88rem;margin-bottom:1.5rem}
        .tech-grid{display:flex;justify-content:center;gap:1rem;flex-wrap:wrap}
        .tech-pill{display:flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;padding:.5rem 1.1rem;border-radius:20px;font-size:.82rem;font-weight:600}

        /* ===== CTA ===== */
        .cta-section{background:linear-gradient(135deg,var(--blue),var(--teal));padding:3rem 5%;text-align:center;color:#fff}
        .cta-section h2{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;margin-bottom:.8rem}
        .cta-section p{color:#CBD5E1;font-size:.92rem;margin-bottom:1.5rem}
        .cta-btns{display:flex;justify-content:center;gap:1rem;flex-wrap:wrap}
        .cta-btn{padding:.8rem 2rem;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .cta-btn.primary{background:#fff;color:var(--blue)}
        .cta-btn.primary:hover{background:#f0f0f0}
        .cta-btn.outline{background:transparent;color:#fff;border:2px solid rgba(255,255,255,.5)}
        .cta-btn.outline:hover{background:rgba(255,255,255,.1)}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .values-grid{grid-template-columns:1fr 1fr}
            .team-grid{grid-template-columns:1fr 1fr}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .nav-btns{display:none}
            .hero{padding:2.5rem 4%}
            .hero h1{font-size:1.8rem}
            .hero-stats{gap:1.5rem}
            .h-stat .num{font-size:1.5rem}
            .section{padding:2rem 4%}
            .values-grid{grid-template-columns:1fr}
            .team-grid{grid-template-columns:1fr}
            .timeline::before{left:20px}
            .tl-item,.tl-item:nth-child(even){flex-direction:column;padding-left:50px}
            .tl-dot{position:absolute;left:0;top:0}
            .tl-content{width:100%}
            .cta-btns{flex-direction:column;align-items:center}
            .cta-btn{width:100%;max-width:300px;justify-content:center}
        }
        @media(max-width:480px){
            .hero h1{font-size:1.4rem}
            .section-hdr h2{font-size:1.3rem}
            .tech-grid{gap:.5rem}
            .tech-pill{font-size:.7rem;padding:.3rem .8rem}
        }
    </style>
</head>
<body>

<!-- ===== NAV ===== -->
<nav>
    <a href="index.php" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Accueil</a></li>
        <li><a href="liste_medecins.php">Médecins</a></li>
        <li><a href="a_propos.php" class="active">À Propos</a></li>
        <li><a href="contact.php">Contact</a></li>
        <li><a href="aide.php">Aide</a></li>
    </ul>
    <div class="nav-btns">
        <?php if($is_logged_in): ?>
            <a href="<?=$user_role === 'patient' ? 'dashboard_patient.php' : ($user_role === 'medecin' ? 'dashboard_medecin.php' : 'dashboard_admin.php')?>" class="btn-p">Tableau de bord</a>
            <a href="logout.php" class="btn-o">Déconnexion</a>
        <?php else: ?>
            <a href="login.php" class="btn-o">Connexion</a>
            <a href="register_patient.php" class="btn-p">S'inscrire</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== HERO ===== -->
<div class="hero">
    <div class="hero-badge">🏥 Projet Professionnel L3 IDA — UNCHK</div>
    <h1>La plateforme de santé <em>numérique</em><br>du Sénégal</h1>
    <p>MediRDV connecte les patients sénégalais avec les meilleurs professionnels de santé. Prenez vos rendez-vous en ligne, gérez vos dossiers médicaux et suivez votre santé facilement.</p>
    <div class="hero-stats">
        <div class="h-stat"><div class="num"><?=$stats['patients']?><span>+</span></div><div class="lbl">Patients inscrits</div></div>
        <div class="h-stat"><div class="num"><?=$stats['medecins']?><span>+</span></div><div class="lbl">Médecins partenaires</div></div>
        <div class="h-stat"><div class="num"><?=$stats['rdv']?></div><div class="lbl">RDV réalisés</div></div>
        <div class="h-stat"><div class="num"><?=$stats['specialites']?><span>+</span></div><div class="lbl">Spécialités médicales</div></div>
    </div>
</div>

<!-- ===== MISSION & VALEURS ===== -->
<div class="section">
    <div class="section-hdr">
        <h2>🎯 Notre Mission & Nos Valeurs</h2>
        <p>Rendre les soins de santé accessibles à tous les Sénégalais grâce au numérique</p>
    </div>
    <div class="values-grid">
        <div class="value-card">
            <div class="v-icon" style="background:#EFF6FF">♿</div>
            <h4>Accessibilité</h4>
            <p>Permettre à chaque Sénégalais de prendre RDV avec un médecin depuis son téléphone, sans se déplacer.</p>
        </div>
        <div class="value-card">
            <div class="v-icon" style="background:#F0FDF4">🔒</div>
            <h4>Sécurité</h4>
            <p>Les données médicales de nos patients sont protégées et confidentielles. Votre santé, c'est privé.</p>
        </div>
        <div class="value-card">
            <div class="v-icon" style="background:#FFF7ED">⚡</div>
            <h4>Efficacité</h4>
            <p>Finies les longues attentes. Trouvez le bon médecin et réservez votre créneau en moins de 3 minutes.</p>
        </div>
        <div class="value-card">
            <div class="v-icon" style="background:#FDF4FF">🤝</div>
            <h4>Confiance</h4>
            <p>Tous nos médecins sont vérifiés et validés par notre équipe. Consultez en toute confiance.</p>
        </div>
        <div class="value-card">
            <div class="v-icon" style="background:#E0F2F1">🌍</div>
            <h4>Impact Local</h4>
            <p>Un projet conçu au Sénégal, pour le Sénégal. Nous comprenons les besoins de notre communauté.</p>
        </div>
        <div class="value-card">
            <div class="v-icon" style="background:#FFFDE7">💡</div>
            <h4>Innovation</h4>
            <p>Nous utilisons les dernières technologies pour offrir la meilleure expérience utilisateur possible.</p>
        </div>
    </div>

    <!-- ===== TIMELINE ===== -->
    <div class="section-hdr">
        <h2>📅 Notre Histoire</h2>
        <p>De l'idée au projet professionnel L3 IDA</p>
    </div>
    <div class="timeline">
        <div class="tl-item">
            <div class="tl-content">
                <div class="tl-year">Septembre 2024</div>
                <h4>🌱 Naissance du projet</h4>
                <p>Le groupe L3 IDA de l'UNCHK identifie le problème de gestion manuelle des RDV dans les hôpitaux sénégalais.</p>
            </div>
            <div class="tl-dot" style="background:var(--blue)">🌱</div>
            <div style="flex:1"></div>
        </div>
        <div class="tl-item">
            <div style="flex:1"></div>
            <div class="tl-dot" style="background:var(--green)">🎨</div>
            <div class="tl-content">
                <div class="tl-year">Octobre 2024</div>
                <h4>🎨 Conception & Design</h4>
                <p>Réalisation des maquettes Figma, schéma BDD, diagrammes UML et cahier des charges complet.</p>
            </div>
        </div>
        <div class="tl-item">
            <div class="tl-content">
                <div class="tl-year">Novembre 2024</div>
                <h4>💻 Développement</h4>
                <p>Développement de l'application avec PHP, MySQL et Bootstrap. Chaque membre code sa partie sur GitHub.</p>
            </div>
            <div class="tl-dot" style="background:var(--orange)">💻</div>
            <div style="flex:1"></div>
        </div>
        <div class="tl-item">
            <div style="flex:1"></div>
            <div class="tl-dot" style="background:var(--purple)">🚀</div>
            <div class="tl-content">
                <div class="tl-year">Janvier 2025</div>
                <h4>🚀 Lancement & Soutenance</h4>
                <p>Mise en ligne de MediRDV et présentation devant le jury de l'UNCHK. Projet professionnel S6 validé !</p>
            </div>
        </div>
    </div>

    <!-- ===== TEAM ===== -->
    <div class="section-hdr">
        <h2>👥 Notre Équipe — Groupe L3 IDA</h2>
        <p>9 étudiants passionnés de l'Université Numérique Cheikh Hamidou Kane</p>
    </div>
    <div class="team-grid">
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#0077B6,#023E8A)">B</div><div class="team-name">Babacar Dione</div><div class="team-role">Chef de Projet</div><div class="team-num">N04849520221</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#2DC653,#006D77)">F</div><div class="team-name">Fatou Demba Mbaye</div><div class="team-role">Authentification</div><div class="team-num">N05510920222</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#F77F00,#D62828)">D</div><div class="team-name">Diama Touré</div><div class="team-role">Espace Patient</div><div class="team-num">N05481420222</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#D62828,#7209B7)">F</div><div class="team-name">Fatou Yankaty</div><div class="team-role">Dossier Médical</div><div class="team-num">N06679120222</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#7209B7,#B5179E)">M</div><div class="team-name">Mareme Faye</div><div class="team-role">Espace Médecin</div><div class="team-num">N01076020222</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#006D77,#0077B6)">A</div><div class="team-name">Aminata Layi Gueye</div><div class="team-role">Notifications</div><div class="team-num">N06404720222</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#4361EE,#3A0CA3)">A</div><div class="team-name">Aicha Djigo</div><div class="team-role">Administration</div><div class="team-num">N01152420222</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#023E8A,#0077B6)">D</div><div class="team-name">Dieynaba Diallo</div><div class="team-role">Base de Données</div><div class="team-num">N02225720222</div></div>
        <div class="team-card"><div class="team-av" style="background:linear-gradient(135deg,#B5179E,#F77F00)">A</div><div class="team-name">Aissatou Sow</div><div class="team-role">Design UI/UX</div><div class="team-num">N02685720222</div></div>
    </div>
</div>

<!-- ===== TECH STACK ===== -->
<div class="tech-section">
    <h2>⚙️ Technologies utilisées</h2>
    <p>Un stack simple, moderne et efficace pour un projet professionnel de qualité</p>
    <div class="tech-grid">
        <div class="tech-pill">🌐 HTML5 + CSS3</div>
        <div class="tech-pill">🎨 Bootstrap 5</div>
        <div class="tech-pill">⚡ JavaScript</div>
        <div class="tech-pill">🐘 PHP 8</div>
        <div class="tech-pill">🗄 MySQL</div>
        <div class="tech-pill">🖥 XAMPP/WAMP</div>
        <div class="tech-pill">🐙 GitHub</div>
        <div class="tech-pill">🎯 Figma</div>
        <div class="tech-pill">📋 Trello</div>
    </div>
</div>

<!-- ===== CTA ===== -->
<div class="cta-section">
    <h2>Prêt à rejoindre MediRDV ?</h2>
    <p>Inscrivez-vous gratuitement et prenez votre premier RDV en moins de 3 minutes</p>
    <div class="cta-btns">
        <a href="register_patient.php" class="cta-btn primary">👤 S'inscrire gratuitement</a>
        <a href="register_medecin.php" class="cta-btn outline">👨‍⚕️ Je suis médecin</a>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Aissatou Sow (E9)</strong> — a_propos.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }
</script>
</body>
</html>