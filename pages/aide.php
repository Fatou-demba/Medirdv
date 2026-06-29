<?php
// ================================================================
// aide.php — Centre d'aide / FAQ
// Responsable : Aissatou Sow (Etudiant 9)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// ================================================================
// NOTIFICATIONS (si connecté)
// ================================================================
$notif_count = 0;
$initiales = '';
if (isLoggedIn()) {
    $type_user = (getRole() === 'medecin') ? 'medecin' : 'patient';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user=:t AND lu=0");
    $stmt->execute([':id'=>$_SESSION['user_id'], ':t'=>$type_user]);
    $notif_count = (int)$stmt->fetchColumn();
    $initiales = strtoupper(mb_substr($_SESSION['prenom']??'U',0,1).mb_substr($_SESSION['nom']??'',0,1));
}

// ================================================================
// STATS RAPIDES (pour donner du contexte dynamique, ex: nb médecins)
// ================================================================
$nb_medecins = (int) $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut='valide'")->fetchColumn();
$nb_specs    = (int) $pdo->query("SELECT COUNT(DISTINCT specialite) FROM medecins WHERE statut='valide'")->fetchColumn();

// ================================================================
// CONTENU FAQ — organisé par catégorie
// (Contenu structuré en PHP ; pourrait être déplacé en BDD plus tard)
// ================================================================
$faq = [
    'patient' => [
        'label' => '👤 Espace Patient',
        'icon'  => '👤',
        'color' => '#0077B6',
        'bg'    => '#EFF6FF',
        'questions' => [
            [
                'q' => "Comment créer un compte patient sur MediRDV ?",
                'r' => "Cliquez sur \"S'inscrire\" en haut de la page d'accueil, puis choisissez \"S'inscrire en tant que patient\". Remplissez le formulaire avec votre nom, email, téléphone et date de naissance, puis créez un mot de passe sécurisé. Votre compte est activé immédiatement."
            ],
            [
                'q' => "Comment prendre un rendez-vous ?",
                'r' => "Connectez-vous à votre compte, recherchez un médecin par spécialité ou ville sur la page \"Médecins\", cliquez sur \"Prendre RDV\", choisissez une date et un créneau disponible dans le calendrier, indiquez le motif de consultation, puis confirmez."
            ],
            [
                'q' => "Comment annuler ou modifier un rendez-vous ?",
                'r' => "Rendez-vous sur \"Mes RDV\", trouvez le rendez-vous concerné dans l'onglet \"À venir\" et cliquez sur \"Annuler le RDV\". Vous pouvez indiquer un motif d'annulation optionnel. Il n'est pas possible d'annuler un RDV déjà passé ou terminé."
            ],
            [
                'q' => "Comment consulter mon dossier médical ?",
                'r' => "Depuis votre profil, cliquez sur \"Mon Dossier Médical\". Vous y trouverez vos antécédents, allergies, l'historique de vos consultations et vos ordonnances. Ce dossier est strictement confidentiel et accessible uniquement par vous et vos médecins traitants."
            ],
            [
                'q' => "Mes données médicales sont-elles sécurisées ?",
                'r' => "Oui. Toutes les données sensibles sont protégées et chaque accès à votre dossier est enregistré dans un journal de sécurité consultable par l'administration en cas de besoin. Aucun tiers non autorisé ne peut consulter vos informations médicales."
            ],
        ],
    ],
    'medecin' => [
        'label' => '👨‍⚕️ Espace Médecin',
        'icon'  => '👨‍⚕️',
        'color' => '#F77F00',
        'bg'    => '#FFF7ED',
        'questions' => [
            [
                'q' => "Comment m'inscrire en tant que médecin ?",
                'r' => "Cliquez sur \"S'inscrire en tant que médecin\" depuis la page de connexion. Remplissez vos informations professionnelles (spécialité, numéro d'ordre, cabinet) et joignez votre justificatif d'ordre médical au format PDF. Votre compte sera examiné par un administrateur avant activation."
            ],
            [
                'q' => "Pourquoi mon compte n'est-il pas encore actif ?",
                'r' => "Chaque inscription médecin est vérifiée manuellement par un administrateur, généralement sous 24 à 48h, afin de garantir l'authenticité des professionnels de santé sur la plateforme. Vous recevrez l'accès dès validation."
            ],
            [
                'q' => "Comment définir mes créneaux de disponibilité ?",
                'r' => "Depuis votre espace médecin, accédez à \"Mon Agenda\" puis configurez vos jours et horaires de consultation. Les patients ne pourront réserver que sur les créneaux que vous avez ouverts."
            ],
            [
                'q' => "Comment confirmer ou refuser un rendez-vous ?",
                'r' => "Dans votre agenda, chaque nouvelle demande apparaît avec le statut \"En attente\". Vous pouvez la confirmer ou la refuser, avec un message personnalisé optionnel destiné au patient."
            ],
            [
                'q' => "Comment rédiger une ordonnance ou un compte-rendu ?",
                'r' => "Après une consultation, accédez au rendez-vous concerné puis cliquez sur \"Rédiger l'ordonnance\" ou \"Compte-rendu\". Ces documents sont automatiquement sauvegardés dans le dossier médical du patient."
            ],
        ],
    ],
    'compte' => [
        'label' => '🔒 Compte & Sécurité',
        'icon'  => '🔒',
        'color' => '#7209B7',
        'bg'    => '#FDF4FF',
        'questions' => [
            [
                'q' => "J'ai oublié mon mot de passe, que faire ?",
                'r' => "Sur la page de connexion, cliquez sur \"Mot de passe oublié ?\" et suivez les instructions. Si le problème persiste, contactez notre équipe support via le formulaire de contact."
            ],
            [
                'q' => "Comment modifier mes informations personnelles ?",
                'r' => "Connectez-vous, allez dans \"Mon Profil\", cliquez sur \"✏️ Modifier\" dans la section informations personnelles, effectuez vos changements puis sauvegardez."
            ],
            [
                'q' => "Comment changer mon mot de passe ?",
                'r' => "Depuis \"Mon Profil\", section Sécurité, cliquez sur \"Changer le mot de passe\". Vous devrez saisir votre mot de passe actuel puis le nouveau, deux fois pour confirmation."
            ],
            [
                'q' => "Puis-je supprimer mon compte ?",
                'r' => "Pour toute demande de suppression de compte, veuillez contacter l'administration via le formulaire de contact ci-dessous. Votre demande sera traitée conformément à notre politique de confidentialité."
            ],
        ],
    ],
    'general' => [
        'label' => 'ℹ️ Général',
        'icon'  => 'ℹ️',
        'color' => '#2DC653',
        'bg'    => '#F0FDF4',
        'questions' => [
            [
                'q' => "Qu'est-ce que MediRDV ?",
                'r' => "MediRDV est une plateforme numérique développée par les étudiants de Licence 3 IDA de l'Université Numérique Cheikh Hamidou Kane, permettant de simplifier la prise de rendez-vous médicaux et la gestion des dossiers patients au Sénégal."
            ],
            [
                'q' => "La plateforme est-elle gratuite ?",
                'r' => "L'inscription et la prise de rendez-vous sur MediRDV sont entièrement gratuites pour les patients. Les frais de consultation affichés sont ceux facturés directement par le cabinet médical."
            ],
            [
                'q' => "Dans quelles villes MediRDV est-il disponible ?",
                'r' => "MediRDV référence des médecins dans plusieurs villes du Sénégal, notamment Dakar, Thiès, Saint-Louis et Ziguinchor, avec un réseau en expansion continue."
            ],
            [
                'q' => "Comment contacter le support technique ?",
                'r' => "Utilisez le formulaire de contact ci-dessous ou rendez-vous sur la page \"Contact\" depuis le menu principal. Notre équipe vous répondra dans les plus brefs délais."
            ],
        ],
    ],
];

// ================================================================
// RECHERCHE FAQ
// ================================================================
$recherche = trim($_GET['q'] ?? '');
$resultats_recherche = [];

if ($recherche !== '') {
    $needle = mb_strtolower($recherche);
    foreach ($faq as $cat_key => $cat) {
        foreach ($cat['questions'] as $item) {
            if (mb_stripos($item['q'], $needle) !== false || mb_stripos($item['r'], $needle) !== false) {
                $resultats_recherche[] = array_merge($item, ['cat' => $cat['label'], 'color' => $cat['color'], 'bg' => $cat['bg']]);
            }
        }
    }
}

// Catégorie active (onglet)
$cat_active = $_GET['cat'] ?? 'patient';
if (!isset($faq[$cat_active])) $cat_active = 'patient';

// ================================================================
// TRAITEMENT FORMULAIRE CONTACT RAPIDE
// ================================================================
$msg_succes = '';
$erreurs    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'contact_rapide') {
    $nom     = trim($_POST['nom']     ?? '');
    $email   = trim($_POST['email']   ?? '');
    $sujet   = trim($_POST['sujet']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($nom === '')     $erreurs['nom']     = 'Le nom est obligatoire.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Email invalide.';
    if ($message === '') $erreurs['message'] = 'Le message est obligatoire.';

    if (empty($erreurs)) {
        // Enregistrer dans le journal d'accès comme trace (table dédiée "contacts" non présente dans le schéma)
        try {
            $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, ip_address)
                           VALUES (0,'patient', :action, :ip)")
                ->execute([
                    ':action' => "Contact aide : $nom <$email> — Sujet: $sujet — " . mb_substr($message,0,150),
                    ':ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                ]);
        } catch(Exception $e) {}
        $msg_succes = "Votre message a bien été envoyé. Notre équipe vous répondra sous peu à l'adresse $email.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Centre d'aide</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0}
body{font-family:'DM Sans',sans-serif;background:#F8FAFF;color:#1E293B}

/* ---- NAVBAR ---- */
nav{background:var(--dark);padding:0 5%;height:68px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(3,4,94,.4)}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.5rem;color:#fff;text-decoration:none;letter-spacing:-1px}
.nav-logo span{color:var(--green)}
.nav-links{display:flex;gap:2rem;list-style:none}
.nav-links a{color:#CBD5E1;text-decoration:none;font-size:.9rem;font-weight:500;transition:.2s}
.nav-links a:hover,.nav-links a.active{color:#fff}
.nav-links a.active{border-bottom:2px solid var(--green);padding-bottom:2px}
.nav-btns{display:flex;gap:.8rem;align-items:center}
.btn-outline{padding:.5rem 1.2rem;border:1.5px solid #CBD5E1;color:#CBD5E1;border-radius:8px;font-size:.85rem;cursor:pointer;background:transparent;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
.btn-outline:hover{border-color:#fff;color:#fff}
.btn-primary{padding:.5rem 1.4rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;transition:.2s}
.btn-primary:hover{background:#25B347;color:#fff}
.notif-badge{display:inline-flex;align-items:center;justify-content:center;background:var(--orange);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.65rem;font-weight:700;margin-left:.3rem}
.avatar-sm{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;font-weight:700}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- HERO AIDE ---- */
.help-hero{background:linear-gradient(135deg,var(--dark) 0%,var(--blue2) 60%,#0096C7 100%);padding:60px 5% 50px;text-align:center;position:relative;overflow:hidden}
.help-hero::before{content:'';position:absolute;top:-80px;right:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(45,198,83,.15) 0%,transparent 70%);border-radius:50%}
.help-hero h1{font-family:'Sora',sans-serif;font-size:2.2rem;font-weight:800;color:#fff;margin-bottom:.8rem;position:relative;z-index:1}
.help-hero p{color:#CBD5E1;font-size:1rem;max-width:560px;margin:0 auto 1.8rem;position:relative;z-index:1}

/* Barre de recherche */
.search-faq{max-width:560px;margin:0 auto;position:relative;z-index:1}
.search-faq form{display:flex;background:#fff;border-radius:14px;padding:.5rem;box-shadow:0 8px 30px rgba(0,0,0,.2);gap:.5rem}
.search-faq input{flex:1;border:none;outline:none;padding:.7rem 1rem;font-size:.95rem;font-family:'DM Sans',sans-serif;color:#1E293B}
.search-faq button{padding:.7rem 1.6rem;background:var(--blue);color:#fff;border:none;border-radius:9px;font-weight:700;cursor:pointer;font-size:.9rem;transition:.2s}
.search-faq button:hover{background:var(--blue2)}

/* Stats rapides */
.help-stats{display:flex;justify-content:center;gap:2rem;margin-top:1.5rem;position:relative;z-index:1;flex-wrap:wrap}
.hstat{color:#CBD5E1;font-size:.82rem;display:flex;align-items:center;gap:.4rem}
.hstat strong{color:var(--green);font-family:'Sora',sans-serif;font-size:1.1rem}

/* ---- MAIN ---- */
.main{max-width:900px;margin:0 auto;padding:2rem 5% 1rem}

/* Résultats recherche */
.search-results-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
.search-results-bar p{font-size:.88rem;color:var(--gray)}
.search-results-bar p strong{color:var(--dark)}
.clear-search{font-size:.83rem;color:var(--blue);text-decoration:none;font-weight:600}

/* ---- CATÉGORIES TABS ---- */
.cat-tabs{display:flex;gap:.6rem;margin-bottom:1.8rem;flex-wrap:wrap;justify-content:center}
.cat-tab{padding:.65rem 1.3rem;border-radius:10px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;color:var(--gray);background:#fff;border:1.5px solid var(--lgray);transition:.2s;display:inline-flex;align-items:center;gap:.4rem}
.cat-tab:hover{border-color:var(--blue);color:var(--blue)}
.cat-tab.active{color:#fff;border-color:transparent}

/* ---- ACCORDÉON FAQ ---- */
.faq-section{margin-bottom:2rem}
.faq-cat-title{display:flex;align-items:center;gap:.6rem;margin-bottom:1.2rem}
.faq-cat-title h2{font-family:'Sora',sans-serif;font-size:1.2rem;font-weight:700;color:var(--dark)}

.faq-item{background:#fff;border-radius:12px;border:1px solid var(--lgray);margin-bottom:.7rem;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.03);transition:.2s}
.faq-item:hover{box-shadow:0 4px 16px rgba(0,0,0,.06)}
.faq-question{padding:1.1rem 1.3rem;display:flex;justify-content:space-between;align-items:center;cursor:pointer;gap:1rem}
.faq-question h4{font-size:.92rem;font-weight:600;color:#1E293B;line-height:1.4}
.faq-toggle{font-size:1.2rem;color:var(--gray);flex-shrink:0;transition:.3s;width:24px;text-align:center}
.faq-item.open .faq-toggle{transform:rotate(45deg);color:var(--blue)}
.faq-answer{max-height:0;overflow:hidden;transition:max-height .3s ease}
.faq-item.open .faq-answer{max-height:400px}
.faq-answer-inner{padding:0 1.3rem 1.1rem;font-size:.87rem;color:var(--gray);line-height:1.7;border-top:1px solid #F8FAFC;padding-top:.8rem}

/* Résultat recherche : tag catégorie */
.result-cat-tag{display:inline-block;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:5px;margin-bottom:.6rem}

/* Pas de résultat */
.no-results{text-align:center;padding:3rem 1rem;color:var(--gray)}
.no-results .icon{font-size:2.5rem;margin-bottom:1rem}

/* ---- CONTACT RAPIDE ---- */
.contact-section{background:#fff;border-radius:16px;padding:2rem;border:1px solid var(--lgray);box-shadow:0 4px 20px rgba(0,0,0,.05);margin-top:1rem;margin-bottom:2rem}
.contact-section h3{font-family:'Sora',sans-serif;font-size:1.2rem;font-weight:800;color:var(--dark);margin-bottom:.4rem;text-align:center}
.contact-section > p{text-align:center;color:var(--gray);font-size:.88rem;margin-bottom:1.6rem}

.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem}
.alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.83rem;font-weight:600;color:#334155;margin-bottom:.4rem}
.form-group input,.form-group select,.form-group textarea{
  width:100%;padding:.7rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
  font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
.form-group input.field-error,.form-group textarea.field-error{border-color:var(--red);background:#FFF5F5}
.field-msg{font-size:.75rem;color:var(--red);margin-top:.25rem}
.form-group textarea{resize:vertical;min-height:100px}

.btn-send{width:100%;padding:.85rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 15px rgba(0,119,182,.3);transition:.2s;font-family:'DM Sans',sans-serif}
.btn-send:hover{background:var(--blue2);transform:translateY(-1px)}

/* Contact alternatives */
.contact-alt{display:flex;justify-content:center;gap:2rem;margin-top:1.5rem;flex-wrap:wrap;padding-top:1.5rem;border-top:1px solid var(--lgray)}
.alt-item{text-align:center;font-size:.83rem;color:var(--gray)}
.alt-item .ic{font-size:1.5rem;display:block;margin-bottom:.4rem}
.alt-item a{color:var(--blue);text-decoration:none;font-weight:600}

footer{background:var(--dark);color:#94A3B8;padding:30px 5%;text-align:center;font-size:.85rem;margin-top:2rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:768px){
  .nav-links{display:none;position:fixed;top:68px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;z-index:99;box-shadow:0 8px 20px rgba(0,0,0,.3)}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06);width:100%}
  .nav-btns{gap:.5rem}
  .btn-outline,.btn-primary{padding:.45rem .9rem;font-size:.8rem}
  .nav-hamburger{display:flex}

  .help-hero{padding:40px 5% 35px}
  .help-hero h1{font-size:1.5rem}
  .help-hero p{font-size:.88rem}
  .search-faq form{flex-direction:column}
  .search-faq button{width:100%}
  .help-stats{gap:1.2rem}

  .main{padding:1.5rem 4% 1rem}
  .cat-tabs{gap:.4rem}
  .cat-tab{font-size:.78rem;padding:.55rem .9rem}
  .form-row{grid-template-columns:1fr}
  .contact-section{padding:1.4rem}
  .contact-alt{gap:1.2rem}
}
@media(max-width:480px){
  .help-hero h1{font-size:1.3rem}
  .faq-question{padding:.9rem 1rem}
  .faq-question h4{font-size:.85rem}
  .faq-answer-inner{font-size:.83rem;padding:0 1rem .9rem}
}
</style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav>
  <a href="../index.php" class="nav-logo">Medi<span>RDV</span></a>
  <button class="nav-hamburger" id="hamburger" onclick="toggleMenu()">
    <span></span><span></span><span></span>
  </button>
  <ul class="nav-links" id="navLinks">
    <li><a href="../index.php">Accueil</a></li>
    <li><a href="liste_medecins.php">Médecins</a></li>
    <li><a href="aide.php" class="active">Aide</a></li>
    <li><a href="contact.php">Contact</a></li>
  </ul>
  <div class="nav-btns">
    <?php if (isLoggedIn()):
      $profil_url = match(getRole()){ 'medecin'=>'profil_medecin.php','admin'=>'dashboard_admin.php',default=>'profil_patient.php' };
    ?>
      <a href="notifications.php" class="btn-outline">
        🔔<?php if($notif_count>0):?><span class="notif-badge"><?=$notif_count?></span><?php endif;?>
      </a>
      <a href="<?=$profil_url?>" class="btn-primary">👤 <?=htmlspecialchars($_SESSION['prenom']??'Espace')?></a>
    <?php else: ?>
      <a href="login.php" class="btn-outline">Connexion</a>
      <a href="register_patient.php" class="btn-primary">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ============================================================
     HERO AIDE
============================================================ -->
<div class="help-hero">
  <h1>💬 Comment pouvons-nous vous aider ?</h1>
  <p>Trouvez rapidement des réponses à vos questions sur la prise de rendez-vous, votre dossier médical et votre compte MediRDV.</p>

  <div class="search-faq">
    <form method="GET" action="aide.php">
      <input type="text" name="q" placeholder="Rechercher une question... (ex: annuler un RDV)"
             value="<?=htmlspecialchars($recherche)?>">
      <button type="submit">🔍 Rechercher</button>
    </form>
  </div>

  <div class="help-stats">
    <div class="hstat">👨‍⚕️ <strong><?=$nb_medecins?></strong>&nbsp;médecins inscrits</div>
    <div class="hstat">🏥 <strong><?=$nb_specs?></strong>&nbsp;spécialités</div>
    <div class="hstat">⏱️ <strong>24-48h</strong>&nbsp;réponse support</div>
  </div>
</div>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">

  <?php if ($recherche !== ''): ?>
    <!-- ========== RÉSULTATS DE RECHERCHE ========== -->
    <div class="search-results-bar">
      <p><strong><?=count($resultats_recherche)?></strong> résultat<?=count($resultats_recherche)>1?'s':''?> pour "<strong><?=htmlspecialchars($recherche)?></strong>"</p>
      <a href="aide.php" class="clear-search">✕ Effacer la recherche</a>
    </div>

    <?php if (empty($resultats_recherche)): ?>
      <div class="no-results">
        <div class="icon">🔍</div>
        <h4 style="margin-bottom:.5rem;color:#334155">Aucun résultat trouvé</h4>
        <p>Essayez d'autres mots-clés ou consultez les catégories ci-dessous, ou contactez-nous directement.</p>
      </div>
    <?php else: ?>
      <?php foreach ($resultats_recherche as $i => $r): ?>
        <div class="faq-item" id="result-<?=$i?>">
          <div class="faq-question" onclick="toggleFaq('result-<?=$i?>')">
            <div>
              <span class="result-cat-tag" style="background:<?=$r['bg']?>;color:<?=$r['color']?>"><?=htmlspecialchars($r['cat'])?></span>
              <h4><?=htmlspecialchars($r['q'])?></h4>
            </div>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner"><?=htmlspecialchars($r['r'])?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php else: ?>
    <!-- ========== CATÉGORIES + ACCORDÉON ========== -->
    <div class="cat-tabs">
      <?php foreach ($faq as $key => $cat): ?>
        <a href="?cat=<?=$key?>"
           class="cat-tab <?=$cat_active===$key?'active':''?>"
           style="<?= $cat_active===$key ? 'background:'.$cat['color'].';box-shadow:0 3px 10px '.$cat['color'].'40' : '' ?>">
          <?=$cat['icon']?> <?=htmlspecialchars(str_replace($cat['icon'].' ','',$cat['label']))?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php $cat = $faq[$cat_active]; ?>
    <div class="faq-section">
      <div class="faq-cat-title">
        <h2><?=$cat['label']?></h2>
      </div>

      <?php foreach ($cat['questions'] as $i => $item): ?>
        <div class="faq-item" id="faq-<?=$cat_active?>-<?=$i?>">
          <div class="faq-question" onclick="toggleFaq('faq-<?=$cat_active?>-<?=$i?>')">
            <h4><?=htmlspecialchars($item['q'])?></h4>
            <span class="faq-toggle">+</span>
          </div>
          <div class="faq-answer">
            <div class="faq-answer-inner"><?=htmlspecialchars($item['r'])?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ============================================================
       FORMULAIRE CONTACT RAPIDE
  ============================================================ -->
  <div class="contact-section">
    <h3>🤝 Vous n'avez pas trouvé votre réponse ?</h3>
    <p>Notre équipe support est là pour vous aider</p>

    <?php if($msg_succes): ?>
      <div class="alert alert-success">✅ <?=htmlspecialchars($msg_succes)?></div>
    <?php endif; ?>
    <?php if(!empty($erreurs)): ?>
      <div class="alert alert-error">⚠️ Veuillez corriger les erreurs ci-dessous.</div>
    <?php endif; ?>

    <form method="POST" action="aide.php<?=$recherche?'?q='.urlencode($recherche):''?>">
      <input type="hidden" name="action" value="contact_rapide">
      <div class="form-row">
        <div class="form-group">
          <label>Votre nom <span style="color:var(--red)">*</span></label>
          <input type="text" name="nom" placeholder="Votre nom complet"
                 value="<?=htmlspecialchars($_POST['nom']??($_SESSION['prenom']??'').' '.($_SESSION['nom']??''))?>"
                 class="<?=isset($erreurs['nom'])?'field-error':''?>">
          <?php if(isset($erreurs['nom'])): ?><span class="field-msg">⚠ <?=$erreurs['nom']?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Votre email <span style="color:var(--red)">*</span></label>
          <input type="email" name="email" placeholder="votre.email@exemple.sn"
                 value="<?=htmlspecialchars($_POST['email']??($_SESSION['email']??''))?>"
                 class="<?=isset($erreurs['email'])?'field-error':''?>">
          <?php if(isset($erreurs['email'])): ?><span class="field-msg">⚠ <?=$erreurs['email']?></span><?php endif; ?>
        </div>
      </div>
      <div class="form-group">
        <label>Sujet</label>
        <select name="sujet">
          <option value="Question générale">Question générale</option>
          <option value="Problème de rendez-vous">Problème de rendez-vous</option>
          <option value="Problème technique">Problème technique</option>
          <option value="Question sur mon dossier médical">Question sur mon dossier médical</option>
          <option value="Inscription médecin">Inscription médecin</option>
          <option value="Autre">Autre</option>
        </select>
      </div>
      <div class="form-group">
        <label>Votre message <span style="color:var(--red)">*</span></label>
        <textarea name="message" placeholder="Décrivez votre problème ou votre question en détail..."
                  class="<?=isset($erreurs['message'])?'field-error':''?>"><?=htmlspecialchars($_POST['message']??'')?></textarea>
        <?php if(isset($erreurs['message'])): ?><span class="field-msg">⚠ <?=$erreurs['message']?></span><?php endif; ?>
      </div>
      <button type="submit" class="btn-send">✉️ Envoyer le message</button>
    </form>

    <div class="contact-alt">
      <div class="alt-item">
        <span class="ic">📧</span>
        Email<br><a href="mailto:contact@medirdv.sn">contact@medirdv.sn</a>
      </div>
      <div class="alt-item">
        <span class="ic">📞</span>
        Téléphone<br><a href="tel:+221330000000">+221 33 000 00 00</a>
      </div>
      <div class="alt-item">
        <span class="ic">💬</span>
        Page Contact<br><a href="contact.php">Formulaire complet →</a>
      </div>
    </div>
  </div>

</div><!-- /main -->

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.5rem">Page développée par : <strong>Aissatou Sow (E9)</strong> — aide.php</p>
</footer>

<script>
// --- Hamburger ---
function toggleMenu(){
  var nav=document.getElementById('navLinks'),icon=document.getElementById('hamburger');
  nav.classList.toggle('open');
  var s=icon.querySelectorAll('span');
  if(nav.classList.contains('open')){s[0].style.transform='rotate(45deg) translate(5px,5px)';s[1].style.opacity='0';s[2].style.transform='rotate(-45deg) translate(5px,-5px)';}
  else{s.forEach(function(x){x.style.transform='';x.style.opacity='';})}
}

// --- Accordéon FAQ ---
function toggleFaq(id) {
  var item = document.getElementById(id);
  var wasOpen = item.classList.contains('open');
  // Fermer tous les autres (accordéon exclusif)
  document.querySelectorAll('.faq-item.open').forEach(function(el) {
    if (el.id !== id) el.classList.remove('open');
  });
  item.classList.toggle('open', !wasOpen);
}

// Ouvrir automatiquement le premier résultat de recherche
<?php if($recherche !== '' && !empty($resultats_recherche)): ?>
document.getElementById('result-0').classList.add('open');
<?php endif; ?>
</script>
</body>
</html>