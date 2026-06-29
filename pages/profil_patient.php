<?php
// ================================================================
// profil_patient.php — Espace profil patient (dynamique)
// Responsable : Diama Touré (Etudiant 3)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Protection : patient connecté uniquement
if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php?redirect=profil_patient.php');
}

$patient_id = $_SESSION['user_id'];

// ================================================================
// RÉCUPÉRER LES DONNÉES DU PATIENT
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) { session_destroy(); redirect('../index.php'); }

// ================================================================
// STATISTIQUES RDV
// ================================================================
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN statut IN ('confirme','en_attente') AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS a_venir,
        SUM(CASE WHEN statut = 'en_attente'  THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut = 'termine'     THEN 1 ELSE 0 END) AS termines,
        SUM(CASE WHEN statut = 'annule'      THEN 1 ELSE 0 END) AS annules
    FROM rendezvous WHERE id_patient = :id
");
$stmt->execute([':id' => $patient_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Notifications non lues
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire = :id AND type_user = 'patient' AND lu = 0");
$stmt->execute([':id' => $patient_id]);
$notif_count = (int) $stmt->fetchColumn();

// ================================================================
// CALCUL IMC
// ================================================================
$imc = '';
if (!empty($patient['taille']) && !empty($patient['poids']) && $patient['taille'] > 0) {
    $taille_m = $patient['taille'] / 100;
    $imc = round($patient['poids'] / ($taille_m * $taille_m), 1);
}

// ================================================================
// AGE
// ================================================================
$age = '';
if (!empty($patient['date_naissance'])) {
    $dob = new DateTime($patient['date_naissance']);
    $age = (new DateTime())->diff($dob)->y . ' ans';
}

// Date inscription formatée
$date_inscription = !empty($patient['date_inscription'])
    ? (new DateTime($patient['date_inscription']))->format('F Y')
    : '';
$mois_fr = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril',
            'May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août',
            'September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
$date_inscription = strtr($date_inscription, $mois_fr);

// ================================================================
// TRAITEMENT FORMULAIRE MODIFICATION
// ================================================================
$erreurs  = [];
$succes   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Mise à jour des informations personnelles ---
    if ($action === 'update_infos') {
        $nom            = trim($_POST['nom']            ?? '');
        $prenom         = trim($_POST['prenom']         ?? '');
        $email          = trim($_POST['email']          ?? '');
        $telephone      = trim($_POST['telephone']      ?? '');
        $date_naissance = trim($_POST['date_naissance'] ?? '');
        $groupe_sanguin = $_POST['groupe_sanguin']      ?? '';
        $taille         = trim($_POST['taille']         ?? '');
        $poids          = trim($_POST['poids']          ?? '');

        if ($nom    === '') $erreurs['nom']    = 'Le nom est obligatoire.';
        if ($prenom === '') $erreurs['prenom'] = 'Le prénom est obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Email invalide.';
        else {
            $chk = $pdo->prepare("SELECT id FROM patients WHERE email = :e AND id != :id LIMIT 1");
            $chk->execute([':e' => $email, ':id' => $patient_id]);
            if ($chk->fetch()) $erreurs['email'] = 'Cet email est déjà utilisé.';
        }

        if (empty($erreurs)) {
            $pdo->prepare("
                UPDATE patients SET
                    nom=:nom, prenom=:prenom, email=:email, telephone=:tel,
                    date_naissance=:ddn, groupe_sanguin=:gs, taille=:t, poids=:p
                WHERE id=:id
            ")->execute([
                ':nom'=>$nom, ':prenom'=>$prenom, ':email'=>$email, ':tel'=>$telephone,
                ':ddn'=>$date_naissance, ':gs'=>$groupe_sanguin?:null,
                ':t'=>$taille?:null, ':p'=>$poids?:null, ':id'=>$patient_id,
            ]);
            $_SESSION['prenom'] = $prenom;
            $_SESSION['nom']    = $nom;
            $_SESSION['email']  = $email;
            $succes = 'Vos informations ont été mises à jour avec succès.';
            // Recharger le patient
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE id=:id LIMIT 1");
            $stmt->execute([':id'=>$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // --- Upload photo de profil ---
    if ($action === 'upload_photo') {
        if (!empty($_FILES['photo']['name'])) {
            $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
            $ftype   = mime_content_type($_FILES['photo']['tmp_name']);
            if (!in_array($ftype, $allowed)) {
                $erreurs['photo'] = 'Format non accepté (JPG, PNG, WEBP).';
            } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                $erreurs['photo'] = 'La photo ne doit pas dépasser 2 MB.';
            } else {
                $ext       = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $filename  = 'patient_' . $patient_id . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/../assets/img/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                // Supprimer l'ancienne photo
                if (!empty($patient['photo']) && file_exists($upload_dir . $patient['photo'])) {
                    unlink($upload_dir . $patient['photo']);
                }
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename);
                $pdo->prepare("UPDATE patients SET photo=:p WHERE id=:id")
                    ->execute([':p'=>$filename, ':id'=>$patient_id]);
                $succes  = 'Photo de profil mise à jour.';
                $patient['photo'] = $filename;
            }
        }
    }

    // --- Supprimer photo ---
    if ($action === 'delete_photo') {
        if (!empty($patient['photo'])) {
            $path = __DIR__ . '/../assets/img/' . $patient['photo'];
            if (file_exists($path)) unlink($path);
            $pdo->prepare("UPDATE patients SET photo=NULL WHERE id=:id")->execute([':id'=>$patient_id]);
            $patient['photo'] = null;
        }
        $succes = 'Photo supprimée.';
    }

    // --- Changer mot de passe ---
    if ($action === 'change_password') {
        $current  = $_POST['current_pw']  ?? '';
        $new_pw   = $_POST['new_pw']      ?? '';
        $confirm  = $_POST['confirm_pw']  ?? '';
        if (!password_verify($current, $patient['mot_de_passe'])) {
            $erreurs['current_pw'] = 'Mot de passe actuel incorrect.';
        } elseif (strlen($new_pw) < 8) {
            $erreurs['new_pw'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        } elseif ($new_pw !== $confirm) {
            $erreurs['confirm_pw'] = 'Les mots de passe ne correspondent pas.';
        } else {
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE patients SET mot_de_passe=:h WHERE id=:id")
                ->execute([':h'=>$hash, ':id'=>$patient_id]);
            $succes = 'Mot de passe modifié avec succès.';
        }
    }
}

$villes = ['Dakar','Thiès','Saint-Louis','Ziguinchor','Kaolack','Diourbel',
           'Tambacounda','Kolda','Matam','Fatick','Kaffrine','Kédougou','Louga','Sédhiou'];
$groupes = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

// Initiales pour l'avatar
$initiales = strtoupper(mb_substr($patient['prenom'],0,1).mb_substr($patient['nom'],0,1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Mon Profil</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0}
body{font-family:'DM Sans',sans-serif;background:#F8FAFF;color:#1E293B}

/* ---- NAVBAR ---- */
nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(3,4,94,.4)}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
.nav-logo span{color:var(--green)}
.nav-links{display:flex;gap:1.8rem;list-style:none}
.nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;font-weight:500;transition:.2s}
.nav-links a:hover,.nav-links a.active{color:#fff}
.nav-right{display:flex;align-items:center;gap:1rem}
.notif-btn{color:#CBD5E1;font-size:.88rem;text-decoration:none;position:relative;display:flex;align-items:center}
.notif-dot{position:absolute;top:-6px;right:-6px;background:var(--red);color:#fff;border-radius:50%;width:17px;height:17px;font-size:.6rem;display:flex;align-items:center;justify-content:center;font-weight:700}
.avatar-sm{width:36px;height:36px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;font-weight:700;flex-shrink:0;overflow:hidden}
.avatar-sm img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.nav-user{display:flex;align-items:center;gap:.5rem;color:#CBD5E1;font-size:.85rem}
.logout-btn{padding:.35rem .8rem;border:1px solid rgba(255,255,255,.25);color:#CBD5E1;background:transparent;border-radius:6px;font-size:.78rem;cursor:pointer;text-decoration:none;transition:.2s}
.logout-btn:hover{border-color:#fff;color:#fff}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- PROFILE HERO ---- */
.profile-hero{background:linear-gradient(135deg,var(--dark),var(--blue2));padding:2rem 5% 0}
.profile-banner{display:flex;gap:1.5rem;align-items:flex-end;flex-wrap:wrap}
.avatar-big{width:90px;height:90px;border-radius:20px;background:linear-gradient(135deg,var(--green),#006D77);display:flex;align-items:center;justify-content:center;font-size:2.5rem;border:3px solid rgba(255,255,255,.3);margin-bottom:-20px;flex-shrink:0;overflow:hidden}
.avatar-big img{width:100%;height:100%;object-fit:cover;border-radius:18px}
.profile-info{padding-bottom:1.2rem;flex:1}
.profile-info h2{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;color:#fff}
.profile-info p{color:#CBD5E1;font-size:.88rem;margin-top:.3rem}
.profile-info .green{color:var(--green)}
.edit-btn{margin-left:auto;padding:.5rem 1.2rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);border-radius:8px;font-size:.83rem;cursor:pointer;margin-bottom:1.2rem;align-self:flex-start;transition:.2s}
.edit-btn:hover{background:rgba(255,255,255,.25)}

/* ---- MAIN LAYOUT ---- */
.main{display:flex;gap:1.5rem;padding:1.8rem 5%;max-width:1000px;margin:0 auto;align-items:flex-start}

/* ---- LEFT SIDEBAR ---- */
.left-col{width:260px;flex-shrink:0}
.info-card{background:#fff;border-radius:14px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.info-card h5{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:var(--dark);margin-bottom:.9rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray)}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:.35rem 0;border-bottom:1px solid #F8FAFC;font-size:.83rem}
.info-row:last-child{border:none}
.info-row .lbl{color:var(--gray)}
.info-row .val{font-weight:600;color:#1E293B;text-align:right;word-break:break-word;max-width:55%}
.vital-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:.5rem}
.vital-item{background:var(--light);border-radius:10px;padding:.7rem;text-align:center}
.vital-num{font-family:'Sora',sans-serif;font-size:1.2rem;font-weight:800;color:var(--blue)}
.vital-lbl{font-size:.7rem;color:var(--gray);margin-top:.1rem}

/* ---- RIGHT COL ---- */
.right-col{flex:1;min-width:0}
.section-card{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);margin-bottom:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.sec-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid var(--lgray)}
.sec-head h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark)}
.sec-edit{font-size:.82rem;color:var(--blue);font-weight:600;cursor:pointer;background:none;border:none;font-family:'DM Sans',sans-serif;transition:.2s}
.sec-edit:hover{color:var(--blue2)}

/* Alertes */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

/* Photo */
.photo-section{display:flex;gap:1.2rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
.photo-prev{width:80px;height:80px;border-radius:16px;background:linear-gradient(135deg,var(--green),#006D77);display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;overflow:hidden}
.photo-prev img{width:100%;height:100%;object-fit:cover;border-radius:14px}
.photo-actions{display:flex;flex-direction:column;gap:.5rem}
.btn-upload{padding:.5rem 1rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.btn-upload:hover{background:var(--blue2)}
.btn-delete{padding:.5rem 1rem;background:transparent;color:var(--red);border:1.5px solid var(--red);border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.btn-delete:hover{background:#FFF5F5}
.photo-hint{font-size:.73rem;color:var(--gray);margin-top:.2rem}
.file-input{display:none}

/* Formulaire */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.9rem;margin-bottom:.9rem}
.form-group{margin-bottom:.9rem}
.form-group label{display:block;font-size:.8rem;font-weight:600;color:#475569;margin-bottom:.35rem}
.form-group input,.form-group select{width:100%;padding:.65rem .9rem;border:1.5px solid var(--lgray);border-radius:9px;font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;background:#F8FAFF}
.form-group input:focus,.form-group select:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(0,119,182,.07)}
.form-group input.field-error{border-color:var(--red);background:#FFF5F5}
.field-msg{font-size:.75rem;color:var(--red);margin-top:.25rem}

.save-btn{padding:.8rem 2rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(0,119,182,.25);transition:.2s;font-family:'DM Sans',sans-serif}
.save-btn:hover{background:var(--blue2);transform:translateY(-1px)}
.cancel-btn{padding:.8rem 1.5rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:10px;font-size:.9rem;cursor:pointer;margin-left:.6rem;font-family:'DM Sans',sans-serif;transition:.2s}
.cancel-btn:hover{border-color:var(--gray)}

/* Liens rapides */
.quick-links{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
.ql{background:var(--light);border-radius:10px;padding:.9rem;display:flex;align-items:center;gap:.6rem;cursor:pointer;border:1px solid transparent;transition:.2s;text-decoration:none}
.ql:hover{border-color:var(--blue);background:#EFF6FF}
.ql-icon{font-size:1.3rem}
.ql-text{font-size:.82rem;font-weight:600;color:#334155}

/* Section mot de passe */
.pw-toggle{font-size:.82rem;color:var(--blue);font-weight:600;cursor:pointer;background:none;border:none;font-family:'DM Sans',sans-serif;margin-top:.5rem;display:inline-block}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE MOBILE
================================================================ */
@media(max-width:768px){
  .nav-links{display:none;position:fixed;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;z-index:99;box-shadow:0 8px 20px rgba(0,0,0,.3)}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
  .nav-hamburger{display:flex}
  .logout-btn{display:none}

  .main{flex-direction:column;padding:1rem 4%}
  .left-col{width:100%}
  .form-row{grid-template-columns:1fr}
  .quick-links{grid-template-columns:1fr 1fr}
  .profile-banner{gap:1rem}
  .profile-info h2{font-size:1.25rem}
  .edit-btn{padding:.4rem .9rem;font-size:.78rem}
}
@media(max-width:480px){
  .quick-links{grid-template-columns:1fr}
  .photo-section{flex-direction:column;align-items:flex-start}
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
    <li><a href="mes_rdv.php">Mes RDV</a></li>
    <li><a href="profil_patient.php" class="active">Mon Profil</a></li>
  </ul>
  <div class="nav-right">
    <a href="notifications.php" class="notif-btn">
      🔔
      <?php if ($notif_count > 0): ?>
        <span class="notif-dot"><?= $notif_count ?></span>
      <?php endif; ?>
    </a>
    <div class="nav-user">
      <div class="avatar-sm">
        <?php if (!empty($patient['photo']) && file_exists(__DIR__.'/../assets/img/'.$patient['photo'])): ?>
          <img src="../assets/img/<?= htmlspecialchars($patient['photo']) ?>" alt="photo">
        <?php else: ?>
          <?= $initiales ?>
        <?php endif; ?>
      </div>
      <span><?= htmlspecialchars($patient['prenom']) ?></span>
    </div>
    <a href="logout.php" class="logout-btn">Déconnexion</a>
  </div>
</nav>

<!-- ============================================================
     PROFILE HERO
============================================================ -->
<div class="profile-hero">
  <div class="profile-banner">
    <div class="avatar-big">
      <?php if (!empty($patient['photo']) && file_exists(__DIR__.'/../assets/img/'.$patient['photo'])): ?>
        <img src="../assets/img/<?= htmlspecialchars($patient['photo']) ?>" alt="photo profil">
      <?php else: ?>
        👤
      <?php endif; ?>
    </div>
    <div class="profile-info">
      <h2><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></h2>
      <p>
        Patient • Inscrit depuis <?= $date_inscription ?>
        • <span class="green">Compte actif</span>
      </p>
    </div>
    <button class="edit-btn" onclick="document.getElementById('secInfos').scrollIntoView({behavior:'smooth'})">
      ✏️ Modifier le profil
    </button>
  </div>
</div>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">

  <!-- ---- LEFT SIDEBAR ---- -->
  <div class="left-col">

    <div class="info-card">
      <h5>👤 Informations</h5>
      <div class="info-row">
        <span class="lbl">Email</span>
        <span class="val" style="font-size:.78rem"><?= htmlspecialchars($patient['email']) ?></span>
      </div>
      <div class="info-row">
        <span class="lbl">Téléphone</span>
        <span class="val"><?= htmlspecialchars($patient['telephone']) ?></span>
      </div>
      <div class="info-row">
        <span class="lbl">Naissance</span>
        <span class="val">
          <?= !empty($patient['date_naissance'])
              ? (new DateTime($patient['date_naissance']))->format('d/m/Y')
              : '—' ?>
        </span>
      </div>
      <div class="info-row">
        <span class="lbl">Âge</span>
        <span class="val"><?= $age ?: '—' ?></span>
      </div>
    </div>

    <div class="info-card">
      <h5>🩺 Informations médicales</h5>
      <div class="vital-grid">
        <div class="vital-item">
          <div class="vital-num"><?= htmlspecialchars($patient['groupe_sanguin'] ?: '—') ?></div>
          <div class="vital-lbl">Groupe sanguin</div>
        </div>
        <div class="vital-item">
          <div class="vital-num"><?= !empty($patient['taille']) ? (int)$patient['taille'] : '—' ?></div>
          <div class="vital-lbl">Taille (cm)</div>
        </div>
        <div class="vital-item">
          <div class="vital-num"><?= !empty($patient['poids']) ? (int)$patient['poids'] : '—' ?></div>
          <div class="vital-lbl">Poids (kg)</div>
        </div>
        <div class="vital-item">
          <div class="vital-num"><?= $imc ?: '—' ?></div>
          <div class="vital-lbl">IMC</div>
        </div>
      </div>
    </div>

    <div class="info-card">
      <h5>📊 Mon activité</h5>
      <div class="info-row">
        <span class="lbl">Total RDV</span>
        <span class="val" style="color:var(--blue)"><?= (int)$stats['total'] ?></span>
      </div>
      <div class="info-row">
        <span class="lbl">À venir</span>
        <span class="val" style="color:var(--green)"><?= (int)$stats['a_venir'] ?></span>
      </div>
      <div class="info-row">
        <span class="lbl">En attente</span>
        <span class="val" style="color:var(--orange)"><?= (int)$stats['en_attente'] ?></span>
      </div>
      <div class="info-row">
        <span class="lbl">Terminés</span>
        <span class="val"><?= (int)$stats['termines'] ?></span>
      </div>
      <div class="info-row">
        <span class="lbl">Annulés</span>
        <span class="val" style="color:var(--red)"><?= (int)$stats['annules'] ?></span>
      </div>
    </div>

  </div><!-- /left-col -->

  <!-- ---- RIGHT COL ---- -->
  <div class="right-col">

    <?php if ($succes !== ''): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($succes) ?></div>
    <?php endif; ?>
    <?php if (!empty($erreurs) && empty($erreurs['current_pw']) && empty($erreurs['new_pw']) && empty($erreurs['confirm_pw']) && empty($erreurs['photo'])): ?>
      <div class="alert alert-error">⚠️ Veuillez corriger les erreurs ci-dessous.</div>
    <?php endif; ?>

    <!-- PHOTO -->
    <div class="section-card">
      <div class="sec-head"><h4>📷 Photo de profil</h4></div>
      <div class="photo-section">
        <div class="photo-prev">
          <?php if (!empty($patient['photo']) && file_exists(__DIR__.'/../assets/img/'.$patient['photo'])): ?>
            <img src="../assets/img/<?= htmlspecialchars($patient['photo']) ?>" alt="profil">
          <?php else: ?>
            👤
          <?php endif; ?>
        </div>
        <div class="photo-actions">
          <form method="POST" enctype="multipart/form-data" style="display:inline">
            <input type="hidden" name="action" value="upload_photo">
            <input type="file" name="photo" id="photoInput" class="file-input"
                   accept="image/jpeg,image/png,image/webp"
                   onchange="this.form.submit()">
            <button type="button" class="btn-upload" onclick="document.getElementById('photoInput').click()">
              Changer la photo
            </button>
          </form>
          <?php if (!empty($patient['photo'])): ?>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Supprimer la photo de profil ?')">
            <input type="hidden" name="action" value="delete_photo">
            <button type="submit" class="btn-delete">Supprimer</button>
          </form>
          <?php endif; ?>
          <div class="photo-hint">JPG, PNG — max 2MB — 200×200px recommandé</div>
          <?php if(isset($erreurs['photo'])): ?><div class="field-msg">⚠ <?=$erreurs['photo']?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- INFORMATIONS PERSONNELLES -->
    <div class="section-card" id="secInfos">
      <div class="sec-head">
        <h4>📝 Informations personnelles</h4>
        <button class="sec-edit" onclick="toggleEdit()">✏️ Modifier</button>
      </div>
      <form method="POST" id="infoForm">
        <input type="hidden" name="action" value="update_infos">
        <div class="form-row">
          <div class="form-group">
            <label>Nom</label>
            <input type="text" name="nom" id="f_nom"
                   value="<?= htmlspecialchars($patient['nom']) ?>"
                   class="<?= isset($erreurs['nom'])?'field-error':'' ?>" readonly>
            <?php if(isset($erreurs['nom'])): ?><span class="field-msg">⚠ <?=$erreurs['nom']?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Prénom</label>
            <input type="text" name="prenom" id="f_prenom"
                   value="<?= htmlspecialchars($patient['prenom']) ?>"
                   class="<?= isset($erreurs['prenom'])?'field-error':'' ?>" readonly>
            <?php if(isset($erreurs['prenom'])): ?><span class="field-msg">⚠ <?=$erreurs['prenom']?></span><?php endif; ?>
          </div>
        </div>
        <div class="form-group">
          <label>Adresse email</label>
          <input type="email" name="email" id="f_email"
                 value="<?= htmlspecialchars($patient['email']) ?>"
                 class="<?= isset($erreurs['email'])?'field-error':'' ?>" readonly>
          <?php if(isset($erreurs['email'])): ?><span class="field-msg">⚠ <?=$erreurs['email']?></span><?php endif; ?>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Téléphone</label>
            <input type="tel" name="telephone" id="f_tel"
                   value="<?= htmlspecialchars($patient['telephone']) ?>" readonly>
          </div>
          <div class="form-group">
            <label>Date de naissance</label>
            <input type="date" name="date_naissance" id="f_ddn"
                   value="<?= htmlspecialchars($patient['date_naissance'] ?? '') ?>"
                   max="<?= date('Y-m-d', strtotime('-5 years')) ?>" readonly>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Groupe sanguin</label>
            <select name="groupe_sanguin" id="f_gs" disabled>
              <option value="">—</option>
              <?php foreach ($groupes as $g): ?>
                <option value="<?=$g?>" <?= ($patient['groupe_sanguin']??'')===$g?'selected':'' ?>><?=$g?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Taille (cm)</label>
            <input type="number" name="taille" id="f_taille" min="50" max="250"
                   value="<?= htmlspecialchars($patient['taille'] ?? '') ?>" readonly>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Poids (kg)</label>
            <input type="number" name="poids" id="f_poids" min="10" max="300"
                   value="<?= htmlspecialchars($patient['poids'] ?? '') ?>" readonly>
          </div>
          <div class="form-group"></div>
        </div>
        <div id="saveButtons" style="display:none">
          <button type="submit" class="save-btn">💾 Sauvegarder</button>
          <button type="button" class="cancel-btn" onclick="cancelEdit()">Annuler</button>
        </div>
      </form>
    </div>

    <!-- CHANGER MOT DE PASSE -->
    <div class="section-card">
      <div class="sec-head">
        <h4>🔒 Sécurité</h4>
        <button class="sec-edit" onclick="togglePwSection()">Changer le mot de passe</button>
      </div>
      <div id="pwSection" style="display:none">
        <?php if(isset($erreurs['current_pw'])||isset($erreurs['new_pw'])||isset($erreurs['confirm_pw'])): ?>
          <div class="alert alert-error">⚠️ <?= htmlspecialchars($erreurs['current_pw']??$erreurs['new_pw']??$erreurs['confirm_pw']??'') ?></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label>Mot de passe actuel</label>
            <input type="password" name="current_pw" placeholder="••••••••"
                   class="<?=isset($erreurs['current_pw'])?'field-error':''?>"
                   autocomplete="current-password">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Nouveau mot de passe</label>
              <input type="password" name="new_pw" placeholder="Minimum 8 caractères"
                     class="<?=isset($erreurs['new_pw'])?'field-error':''?>"
                     autocomplete="new-password">
            </div>
            <div class="form-group">
              <label>Confirmer</label>
              <input type="password" name="confirm_pw" placeholder="Répétez"
                     class="<?=isset($erreurs['confirm_pw'])?'field-error':''?>"
                     autocomplete="new-password">
            </div>
          </div>
          <button type="submit" class="save-btn">🔒 Modifier le mot de passe</button>
        </form>
      </div>
    </div>

    <!-- LIENS RAPIDES -->
    <div class="section-card">
      <div class="sec-head"><h4>🔗 Accès rapide</h4></div>
      <div class="quick-links">
        <a class="ql" href="mes_rdv.php"><div class="ql-icon">📅</div><div class="ql-text">Mes Rendez-Vous</div></a>
        <a class="ql" href="dossier_patient.php"><div class="ql-icon">📋</div><div class="ql-text">Mon Dossier Médical</div></a>
        <a class="ql" href="notifications.php"><div class="ql-icon">🔔</div><div class="ql-text">Notifications <?= $notif_count>0?"($notif_count)":'' ?></div></a>
        <a class="ql" href="historique_patient.php"><div class="ql-icon">📜</div><div class="ql-text">Historique consultations</div></a>
      </div>
    </div>

  </div><!-- /right-col -->
</div><!-- /main -->

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Diama Touré (E3)</strong> — profil_patient.php</p>
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

// --- Mode édition informations ---
var editMode = false;
var editFields = ['f_nom','f_prenom','f_email','f_tel','f_ddn','f_taille','f_poids'];
var editSelects = ['f_gs'];

function toggleEdit(){
  editMode = !editMode;
  editFields.forEach(function(id){
    var el = document.getElementById(id);
    if(el) el.readOnly = !editMode;
  });
  editSelects.forEach(function(id){
    var el = document.getElementById(id);
    if(el) el.disabled = !editMode;
  });
  document.getElementById('saveButtons').style.display = editMode ? 'block' : 'none';
  if(editMode) document.getElementById('f_nom').focus();
}

function cancelEdit(){
  editMode = false;
  document.getElementById('infoForm').reset();
  editFields.forEach(function(id){
    var el = document.getElementById(id);
    if(el) el.readOnly = true;
  });
  editSelects.forEach(function(id){
    var el = document.getElementById(id);
    if(el) el.disabled = true;
  });
  document.getElementById('saveButtons').style.display = 'none';
}

// Si erreur de formulaire infos, ouvrir le mode édition automatiquement
<?php if (!empty($erreurs) && isset($_POST['action']) && $_POST['action']==='update_infos'): ?>
toggleEdit();
<?php endif; ?>

// --- Section mot de passe ---
function togglePwSection(){
  var sec = document.getElementById('pwSection');
  sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
}
<?php if (!empty($erreurs) && isset($_POST['action']) && $_POST['action']==='change_password'): ?>
document.getElementById('pwSection').style.display = 'block';
<?php endif; ?>
</script>
</body>
</html>