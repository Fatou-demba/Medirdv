<?php
// ================================================================
// contact.php — Page de contact MediRDV
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Vérifier si l'utilisateur est connecté
$is_logged_in = isLoggedIn();
$user_role = $is_logged_in ? getRole() : '';

$message_envoye = false;
$erreur = '';

// ================================================================
// TRAITEMENT DU FORMULAIRE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $satisfaction = (int)($_POST['satisfaction'] ?? 0);
    $type = trim($_POST['type'] ?? 'support');

    if (empty($nom) || empty($prenom) || empty($email) || empty($message)) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L\'adresse email n\'est pas valide.';
    } else {
        // Simuler l'envoi du message (dans un vrai projet, on enverrait un email)
        // Ici on pourrait enregistrer dans la base de données
        $message_envoye = true;
        
        // Journaliser le contact
        try {
            $stmt = $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, ip_address) 
                                   VALUES (:id, 'visiteur', 'contact_formulaire', :ip)");
            $stmt->execute([
                ':id' => $is_logged_in ? $_SESSION['user_id'] : 0,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch(Exception $e) {}
    }
}

// Récupérer les infos utilisateur si connecté
$nom = $prenom = $email = '';
if ($is_logged_in) {
    if ($user_role === 'patient') {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $nom = $user['nom'];
            $prenom = $user['prenom'];
            $email = $user['email'];
        }
    } elseif ($user_role === 'medecin') {
        $stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $nom = $user['nom'];
            $prenom = $user['prenom'];
            $email = $user['email'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Contact</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--teal:#006D77;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
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
        .page-hdr{background:linear-gradient(135deg,var(--dark),var(--teal));padding:3rem 5%;text-align:center;color:#fff;position:relative;overflow:hidden}
        .page-hdr::before{content:'';position:absolute;top:-60px;left:-60px;width:250px;height:250px;background:radial-gradient(circle,rgba(45,198,83,.15),transparent 70%);border-radius:50%}
        .page-hdr::after{content:'';position:absolute;bottom:-80px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(0,119,182,.2),transparent 70%);border-radius:50%}
        .page-hdr h1{font-family:'Sora',sans-serif;font-size:2rem;font-weight:800;position:relative;z-index:1}
        .page-hdr p{color:#A5F3FC;font-size:.95rem;margin-top:.5rem;position:relative;z-index:1}

        /* ===== MAIN ===== */
        .main{display:flex;gap:2rem;padding:2.5rem 5%;max-width:1050px;margin:0 auto;flex-wrap:wrap}

        /* ===== INFO COL ===== */
        .info-col{width:300px;flex-shrink:0}
        .info-card{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .info-card h4{font-family:'Sora',sans-serif;font-size:.92rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid var(--blue)}
        .contact-item{display:flex;gap:.8rem;align-items:flex-start;padding:.7rem 0;border-bottom:1px solid #F1F5F9}
        .contact-item:last-child{border:none}
        .c-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
        .c-lbl{font-size:.75rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.4px}
        .c-val{font-size:.87rem;font-weight:600;color:#1E293B;margin-top:.1rem}
        .c-sub{font-size:.75rem;color:var(--gray);margin-top:.1rem}

        /* ===== SOCIAL ===== */
        .social-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.5rem}
        .soc-btn{display:flex;align-items:center;gap:.5rem;padding:.55rem .7rem;border-radius:9px;font-size:.8rem;font-weight:600;cursor:pointer;border:1.5px solid var(--lgray);background:#fff;transition:.15s;color:#334155;width:100%;justify-content:center}
        .soc-btn:hover{background:var(--light);border-color:var(--blue);color:var(--blue)}

        /* ===== FAQ ===== */
        .faq-card{background:linear-gradient(135deg,var(--light),#E0F2F1);border-radius:14px;padding:1.2rem;border:1px solid #BFDBFE}
        .faq-card h5{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:var(--dark);margin-bottom:.7rem}
        .faq-link{display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--blue);font-weight:600;padding:.4rem 0;cursor:pointer;text-decoration:none}
        .faq-link:hover{text-decoration:underline}

        /* ===== FORM ===== */
        .form-col{flex:1;min-width:300px}
        .form-card{background:#fff;border-radius:14px;padding:2rem;border:1px solid var(--lgray);box-shadow:0 4px 20px rgba(0,0,0,.08)}
        .form-card h3{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark);margin-bottom:.4rem}
        .form-card .subtitle{color:var(--gray);font-size:.88rem;margin-bottom:1.5rem}

        /* ===== SUBJECT ===== */
        .subject-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.2rem}
        .subj-btn{padding:.6rem;border:1.5px solid var(--lgray);border-radius:10px;text-align:center;cursor:pointer;transition:.15s;background:#fff;font-size:.8rem;font-weight:600;color:#334155;border:none}
        .subj-btn:hover{border-color:var(--blue);color:var(--blue);border-style:solid;border-width:1.5px}
        .subj-btn.sel{border-color:var(--blue);background:#EFF6FF;color:var(--blue);border-style:solid;border-width:1.5px}
        .subj-icon{font-size:1.1rem;display:block;margin-bottom:.2rem}

        .form-group{margin-bottom:1rem}
        .form-group label{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:.4rem}
        .form-group label .req{color:var(--red,#D62828)}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:.72rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
        .form-group textarea{resize:vertical;min-height:110px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}

        /* ===== RATING ===== */
        .rating-row{display:flex;gap:.4rem;font-size:1.5rem;cursor:pointer;margin-bottom:.3rem}
        .star{color:var(--lgray);transition:.15s;cursor:pointer;background:none;border:none;font-size:1.5rem;padding:0}
        .star:hover,.star.sel{color:#FBBF24}

        /* ===== ALERT ===== */
        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:#D62828}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}

        .submit-btn{width:100%;padding:.88rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;box-shadow:0 4px 15px rgba(0,119,182,.3);transition:.2s;font-family:'DM Sans',sans-serif;display:flex;align-items:center;justify-content:center;gap:.5rem}
        .submit-btn:hover{background:var(--dark);transform:translateY(-1px)}

        /* ===== SUCCESS ===== */
        .success-state{text-align:center;padding:2rem;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;margin-top:1rem}
        .success-state .icon{font-size:3rem;margin-bottom:.8rem}
        .success-state h4{font-family:'Sora',sans-serif;color:var(--dark);margin-bottom:.4rem}
        .success-state p{font-size:.85rem;color:var(--gray)}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .main{flex-direction:column}
            .info-col{width:100%}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .page-hdr{padding:2rem 4%}
            .page-hdr h1{font-size:1.5rem}
            .main{padding:1.5rem 4%}
            .form-card{padding:1.5rem}
            .form-row{grid-template-columns:1fr}
            .subject-grid{grid-template-columns:1fr 1fr}
            .social-grid{grid-template-columns:1fr 1fr}
        }
        @media(max-width:480px){
            .page-hdr h1{font-size:1.2rem}
            .form-card{padding:1rem}
            .subject-grid{grid-template-columns:1fr 1fr}
            .subject-grid .subj-btn{font-size:.7rem;padding:.4rem}
            .subj-icon{font-size:.9rem}
            .info-card{padding:1rem}
            .contact-item{gap:.5rem}
            .c-icon{width:32px;height:32px;font-size:.9rem}
            .c-val{font-size:.8rem}
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
        <li><a href="a_propos.php">À propos</a></li>
        <li><a href="contact.php" class="active">Contact</a></li>
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
<div class="page-hdr">
    <h1>📬 Contactez-nous</h1>
    <p>Notre équipe est disponible du lundi au vendredi, de 8h à 18h</p>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <!-- ===== INFO COL ===== -->
    <div class="info-col">
        <div class="info-card">
            <h4>📞 Nos coordonnées</h4>
            <div class="contact-item">
                <div class="c-icon" style="background:#EFF6FF">📧</div>
                <div><div class="c-lbl">Email</div><div class="c-val">support@medirdv.sn</div><div class="c-sub">Réponse sous 24h</div></div>
            </div>
            <div class="contact-item">
                <div class="c-icon" style="background:#F0FDF4">📞</div>
                <div><div class="c-lbl">Téléphone</div><div class="c-val">+221 33 867 00 00</div><div class="c-sub">Lun-Ven 8h-18h</div></div>
            </div>
            <div class="contact-item">
                <div class="c-icon" style="background:#FFF7ED">📍</div>
                <div><div class="c-lbl">Adresse</div><div class="c-val">UNCHK — Dakar, Sénégal</div><div class="c-sub">Université Numérique Cheikh Hamidou Kane</div></div>
            </div>
            <div class="contact-item">
                <div class="c-icon" style="background:#FDF4FF">🕐</div>
                <div><div class="c-lbl">Horaires</div><div class="c-val">Lun — Ven : 8h00 - 18h00</div><div class="c-sub">Sam-Dim : Fermé</div></div>
            </div>
        </div>

        <div class="info-card">
            <h4>🌐 Réseaux sociaux</h4>
            <div class="social-grid">
                <button class="soc-btn" onclick="window.open('https://facebook.com','_blank')">📘 Facebook</button>
                <button class="soc-btn" onclick="window.open('https://twitter.com','_blank')">🐦 Twitter</button>
                <button class="soc-btn" onclick="window.open('https://instagram.com','_blank')">📸 Instagram</button>
                <button class="soc-btn" onclick="window.open('https://linkedin.com','_blank')">💼 LinkedIn</button>
            </div>
        </div>

        <div class="faq-card">
            <h5>❓ Questions fréquentes</h5>
            <a href="aide.php#rdv" class="faq-link">→ Comment prendre un RDV ?</a>
            <a href="aide.php#annuler" class="faq-link">→ Comment annuler un RDV ?</a>
            <a href="aide.php#dossier" class="faq-link">→ Comment voir mon dossier ?</a>
            <a href="aide.php" class="faq-link">→ Voir toute la FAQ →</a>
        </div>
    </div>

    <!-- ===== FORM ===== -->
    <div class="form-col">
        <div class="form-card">
            <h3>✉️ Envoyer un message</h3>
            <p class="subtitle">Remplissez le formulaire et nous vous répondrons dans les 24 heures</p>

            <?php if($erreur): ?>
                <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
            <?php endif; ?>

            <?php if($message_envoye): ?>
                <div class="success-state">
                    <div class="icon">✅</div>
                    <h4>Message envoyé avec succès !</h4>
                    <p>Nous avons bien reçu votre message. Notre équipe vous répondra dans les 24 heures à l'adresse <strong><?=htmlspecialchars($email)?></strong></p>
                </div>
            <?php else: ?>

            <form method="POST" action="contact.php">
                <!-- SUBJECT -->
                <div class="subject-grid">
                    <button type="button" class="subj-btn sel" data-type="support"><span class="subj-icon">🆘</span>Support technique</button>
                    <button type="button" class="subj-btn" data-type="medical"><span class="subj-icon">💊</span>Question médicale</button>
                    <button type="button" class="subj-btn" data-type="rdv"><span class="subj-icon">📅</span>Problème RDV</button>
                    <button type="button" class="subj-btn" data-type="suggestion"><span class="subj-icon">💡</span>Suggestion</button>
                </div>
                <input type="hidden" name="type" id="typeInput" value="support">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nom <span class="req">*</span></label>
                        <input type="text" name="nom" placeholder="Votre nom" value="<?=htmlspecialchars($nom)?>" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom <span class="req">*</span></label>
                        <input type="text" name="prenom" placeholder="Votre prénom" value="<?=htmlspecialchars($prenom)?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" placeholder="votre@email.sn" value="<?=htmlspecialchars($email)?>" required>
                </div>

                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" placeholder="+221 77 XXX XX XX">
                </div>

                <div class="form-group">
                    <label>Sujet <span class="req">*</span></label>
                    <select name="sujet" required>
                        <option value="">Sélectionnez un sujet</option>
                        <option value="probleme_connexion">Problème de connexion</option>
                        <option value="rdv_non_confirme">RDV non confirmé</option>
                        <option value="erreur_dossier">Erreur dans mon dossier</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Message <span class="req">*</span></label>
                    <textarea name="message" placeholder="Décrivez votre problème ou question en détail..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Satisfaction générale (optionnel)</label>
                    <div class="rating-row">
                        <button type="button" class="star sel" data-value="1">★</button>
                        <button type="button" class="star sel" data-value="2">★</button>
                        <button type="button" class="star sel" data-value="3">★</button>
                        <button type="button" class="star sel" data-value="4">★</button>
                        <button type="button" class="star" data-value="5">★</button>
                    </div>
                    <input type="hidden" name="satisfaction" id="satisfactionInput" value="4">
                </div>

                <button type="submit" class="submit-btn">📤 Envoyer le message</button>
            </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Aissatou Sow (E9)</strong> — contact.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    // Subject buttons
    document.querySelectorAll('.subj-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.subj-btn').forEach(function(b) { b.classList.remove('sel'); });
            this.classList.add('sel');
            document.getElementById('typeInput').value = this.getAttribute('data-type');
        });
    });

    // Rating stars
    document.querySelectorAll('.star').forEach(function(star) {
        star.addEventListener('click', function() {
            const value = parseInt(this.getAttribute('data-value'));
            document.querySelectorAll('.star').forEach(function(s) {
                const sVal = parseInt(s.getAttribute('data-value'));
                if (sVal <= value) {
                    s.classList.add('sel');
                } else {
                    s.classList.remove('sel');
                }
            });
            document.getElementById('satisfactionInput').value = value;
        });

        star.addEventListener('mouseenter', function() {
            const value = parseInt(this.getAttribute('data-value'));
            document.querySelectorAll('.star').forEach(function(s) {
                const sVal = parseInt(s.getAttribute('data-value'));
                if (sVal <= value) {
                    s.style.color = '#FBBF24';
                } else {
                    s.style.color = '';
                }
            });
        });

        star.addEventListener('mouseleave', function() {
            document.querySelectorAll('.star').forEach(function(s) {
                if (!s.classList.contains('sel')) {
                    s.style.color = '';
                }
            });
        });
    });
</script>
</body>
</html>