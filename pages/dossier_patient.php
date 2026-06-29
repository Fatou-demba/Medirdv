<?php
// ================================================================
// dossier_patient.php — Dossier Médical du Patient
// Responsable : Fatou Yankaty (Etudiant 4)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Protection : patient connecté uniquement
if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php?redirect=dossier_patient.php');
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
// RÉCUPÉRER OU CRÉER LE DOSSIER
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dossier) {
    // Créer le dossier s'il n'existe pas
    $pdo->prepare("INSERT INTO dossiers (id_patient) VALUES (:id)")->execute([':id' => $patient_id]);
    $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id LIMIT 1");
    $stmt->execute([':id' => $patient_id]);
    $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// JOURNALISER L'ACCÈS
// ================================================================
try {
    $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, id_dossier, ip_address)
                   VALUES (:id, 'patient', 'consultation_dossier', :did, :ip)")
        ->execute([':id'=>$patient_id, ':did'=>$dossier['id'], ':ip'=>$_SERVER['REMOTE_ADDR']??'']);
} catch(Exception $e) {}

// ================================================================
// TRAITEMENT — SUPPRESSION ANTÉCÉDENT (champ dossiers)
// ================================================================
$msg_succes = '';
$msg_erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Mettre à jour un champ du dossier (antécédents, allergies, etc.) ---
    if ($action === 'update_dossier') {
        $champs_autorises = ['antecedents','allergies','maladies_chroniques','chirurgies','traitements_en_cours'];
        $champ = $_POST['champ'] ?? '';
        $valeur = trim($_POST['valeur'] ?? '');
        if (in_array($champ, $champs_autorises)) {
            $pdo->prepare("UPDATE dossiers SET $champ = :v WHERE id_patient = :id")
                ->execute([':v' => $valeur ?: null, ':id' => $patient_id]);
            $msg_succes = 'Dossier mis à jour avec succès.';
            // Recharger
            $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id");
            $stmt->execute([':id' => $patient_id]);
            $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// ================================================================
// RÉCUPÉRER LES CONSULTATIONS (historique)
// ================================================================
$stmt = $pdo->prepare("
    SELECT c.*, r.date_rdv, r.heure_rdv, r.motif AS motif_rdv,
           m.nom AS m_nom, m.prenom AS m_prenom, m.specialite, m.photo AS m_photo
    FROM consultations c
    JOIN rendezvous r ON r.id = c.id_rendezvous
    JOIN medecins m ON m.id = c.id_medecin
    WHERE c.id_patient = :id
    ORDER BY c.date_consultation DESC
    LIMIT 10
");
$stmt->execute([':id' => $patient_id]);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RÉCUPÉRER LES ORDONNANCES EN COURS
// ================================================================
$stmt = $pdo->prepare("
    SELECT c.ordonnance, c.traitement, c.date_consultation, r.date_rdv,
           m.nom AS m_nom, m.prenom AS m_prenom, m.specialite
    FROM consultations c
    JOIN rendezvous r ON r.id = c.id_rendezvous
    JOIN medecins m ON m.id = c.id_medecin
    WHERE c.id_patient = :id AND c.ordonnance IS NOT NULL AND c.ordonnance != ''
    ORDER BY c.date_consultation DESC
    LIMIT 5
");
$stmt->execute([':id' => $patient_id]);
$ordonnances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULS
// ================================================================
$age = '';
if (!empty($patient['date_naissance'])) {
    $age = (new DateTime())->diff(new DateTime($patient['date_naissance']))->y . ' ans';
}
$imc = '';
if (!empty($patient['taille']) && !empty($patient['poids']) && $patient['taille'] > 0) {
    $tm = $patient['taille'] / 100;
    $imc = round($patient['poids'] / ($tm * $tm), 1);
}

// Dates formatées
$mois_fr = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril',
            'May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août',
            'September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
function fmtDate($d,$m){if(!$d)return '—';$dt=new DateTime($d);return $dt->format('d').' '.($m[$dt->format('F')]??$dt->format('F')).' '.$dt->format('Y');}

$date_creation   = fmtDate(substr($dossier['date_creation']??'',0,10), $mois_fr);
$date_modif      = fmtDate(substr($dossier['date_modification']??'',0,10), $mois_fr);

// Onglet actif
$onglet = $_GET['onglet'] ?? 'antecedents';

// Notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user='patient' AND lu=0");
$stmt->execute([':id'=>$patient_id]);
$notif_count = (int)$stmt->fetchColumn();

$initiales = strtoupper(mb_substr($patient['prenom'],0,1).mb_substr($patient['nom'],0,1));

// Couleurs spécialités
$couleurs_spec = ['Cardiologie'=>'#0077B6','Pediatrie'=>'#7209B7','Dermatologie'=>'#F77F00',
                  'Gynecologie'=>'#006D77','Neurologie'=>'#3A0CA3','default'=>'#0077B6'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Mon Dossier Médical</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0}
body{font-family:'DM Sans',sans-serif;background:#F8FAFF;color:#1E293B}

/* ---- NAVBAR ---- */
nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(3,4,94,.4)}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
.nav-logo span{color:var(--green)}
.nav-links{display:flex;gap:1.8rem;list-style:none}
.nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;font-weight:500;transition:.2s}
.nav-links a:hover,.nav-links a.active{color:#fff}
.nav-right{display:flex;align-items:center;gap:1rem}
.notif-btn{color:#CBD5E1;font-size:.88rem;text-decoration:none;position:relative}
.notif-dot{position:absolute;top:-6px;right:-6px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;font-size:.58rem;display:flex;align-items:center;justify-content:center;font-weight:700}
.avatar-sm{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;font-weight:700;overflow:hidden}
.avatar-sm img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.nav-user{display:flex;align-items:center;gap:.5rem;color:#CBD5E1;font-size:.85rem}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- DOSSIER HERO ---- */
.dossier-hero{background:linear-gradient(135deg,var(--purple),var(--blue2));padding:1.8rem 5%;color:#fff;position:relative;overflow:hidden}
.dossier-hero::before{content:'🔒';position:absolute;right:4%;top:50%;transform:translateY(-50%);font-size:6rem;opacity:.07;pointer-events:none}
.breadcrumb{font-size:.78rem;color:#C4B5FD;margin-bottom:.5rem}
.breadcrumb a{color:#C4B5FD;text-decoration:none}
.breadcrumb a:hover{color:#fff}
.breadcrumb span{color:var(--green)}
.d-banner{display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap}
.d-avatar{width:72px;height:72px;border-radius:16px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:2rem;border:2px solid rgba(255,255,255,.3);flex-shrink:0}
.d-info h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
.d-info p{color:#C4B5FD;font-size:.85rem;margin-top:.3rem}
.d-badge{display:inline-flex;align-items:center;gap:.4rem;background:rgba(45,198,83,.2);border:1px solid rgba(45,198,83,.3);color:var(--green);padding:.3rem .8rem;border-radius:20px;font-size:.75rem;font-weight:600;margin-top:.5rem}
.d-actions{margin-left:auto;display:flex;gap:.6rem;flex-wrap:wrap}
.btn-pdf{padding:.55rem 1.2rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);border-radius:8px;font-size:.83rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
.btn-pdf:hover{background:rgba(255,255,255,.25);color:#fff}

/* ---- VITALS BAR ---- */
.vitals-bar{background:#fff;border-bottom:1px solid var(--lgray);padding:.9rem 5%;display:flex;gap:2rem;overflow-x:auto}
.vital{display:flex;align-items:center;gap:.7rem;white-space:nowrap}
.v-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.v-num{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark)}
.v-lbl{font-size:.72rem;color:var(--gray)}

/* ---- MAIN ---- */
.main{max-width:1000px;margin:0 auto;padding:1.5rem 5%}

/* Alertes */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
.alert-warn{background:#FFF7ED;border:1px solid #FED7AA;color:#92400E}

.access-note{background:#FFF7ED;border:1px solid #FED7AA;border-radius:10px;padding:.8rem 1rem;font-size:.82rem;color:#92400E;display:flex;gap:.6rem;align-items:center;margin-bottom:1rem}

/* ---- ONGLETS ---- */
.tabs{display:flex;gap:.3rem;background:#fff;border-radius:12px;padding:.3rem;margin-bottom:1.5rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.04);overflow-x:auto}
.tab{flex:1;padding:.6rem .5rem;text-align:center;border-radius:9px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;color:var(--gray);transition:.2s;white-space:nowrap;display:block}
.tab.active{background:var(--purple);color:#fff;box-shadow:0 2px 8px rgba(114,9,183,.3)}
.tab:hover:not(.active){color:var(--purple)}

/* ---- SECTIONS ---- */
.section{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);margin-bottom:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.sec-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid var(--lgray);flex-wrap:wrap;gap:.5rem}
.sec-head h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark)}
.btn-add{padding:.4rem .9rem;background:var(--purple);color:#fff;border:none;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;transition:.2s}
.btn-add:hover{background:#5B0099;color:#fff}
.btn-edit-sec{padding:.4rem .9rem;background:transparent;color:var(--blue);border:1.5px solid var(--blue);border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;transition:.2s}
.btn-edit-sec:hover{background:#EFF6FF}

/* ---- ANTÉCÉDENT ITEMS ---- */
.ant-list{display:flex;flex-direction:column;gap:.6rem}
.ant-item{display:flex;align-items:flex-start;gap:.8rem;background:var(--light);border-radius:10px;padding:.8rem;border-left:4px solid var(--purple)}
.ant-item.allergie{border-left-color:var(--red);background:#FFF5F5}
.ant-item.chronique{border-left-color:var(--orange);background:#FFF7ED}
.ant-item.chirurgie{border-left-color:var(--blue);background:#EFF6FF}
.ant-item.traitement{border-left-color:var(--green);background:#F0FDF4}
.ant-icon{font-size:1.2rem;flex-shrink:0;margin-top:.1rem}
.ant-body{flex:1;min-width:0}
.ant-body h6{font-size:.85rem;font-weight:700;color:#1E293B}
.ant-body p{font-size:.8rem;color:var(--gray);margin-top:.2rem;line-height:1.5}
.ant-badge{font-size:.72rem;font-weight:600;padding:.2rem .55rem;border-radius:5px;display:inline-block;margin-top:.3rem}
.ant-actions{display:flex;gap:.4rem;flex-shrink:0;align-self:flex-start;margin-top:.2rem}
.btn-edit-sm{padding:.3rem .6rem;background:transparent;color:var(--blue);border:1px solid var(--blue);border-radius:6px;font-size:.72rem;cursor:pointer;transition:.2s}
.btn-edit-sm:hover{background:#EFF6FF}
.btn-del-sm{padding:.3rem .6rem;background:transparent;color:var(--red);border:1px solid var(--red);border-radius:6px;font-size:.72rem;cursor:pointer;transition:.2s}
.btn-del-sm:hover{background:#FFF5F5}

/* Champ texte (mode édition dossier) */
.dossier-field{width:100%;padding:.75rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;resize:vertical;min-height:80px;outline:none;transition:.2s;color:#1E293B}
.dossier-field:focus{border-color:var(--purple);box-shadow:0 0 0 3px rgba(114,9,183,.07)}
.field-actions{display:flex;gap:.6rem;margin-top:.6rem}
.btn-save-f{padding:.55rem 1.2rem;background:var(--purple);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.btn-save-f:hover{background:#5B0099}
.btn-cancel-f{padding:.55rem 1rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:8px;font-size:.82rem;cursor:pointer;font-family:'DM Sans',sans-serif}

/* Vide */
.empty-field{color:var(--gray);font-size:.85rem;font-style:italic;padding:.5rem 0}

/* ---- CONSULTATIONS ---- */
.consult-item{border-left:3px solid var(--blue);padding:.8rem 1rem;background:var(--light);border-radius:0 10px 10px 0;margin-bottom:.7rem;transition:.15s;cursor:pointer}
.consult-item:hover{background:#EFF6FF;border-left-color:var(--blue2)}
.c-date{font-size:.75rem;font-weight:700;color:var(--blue);margin-bottom:.3rem}
.c-doc{font-size:.85rem;font-weight:700;color:#1E293B}
.c-diag{font-size:.82rem;color:var(--gray);margin-top:.3rem;line-height:1.5}
.c-btn{padding:.35rem .8rem;background:var(--blue);color:#fff;border:none;border-radius:6px;font-size:.75rem;cursor:pointer;margin-top:.5rem;font-weight:600;text-decoration:none;display:inline-block;transition:.2s}
.c-btn:hover{background:var(--blue2);color:#fff}

/* ---- ORDONNANCES ---- */
.ordo-card{border:1px solid var(--lgray);border-radius:10px;padding:1rem;margin-bottom:.7rem;background:#fff;transition:.15s}
.ordo-card:hover{border-color:#C4B5FD;box-shadow:0 4px 15px rgba(114,9,183,.08)}
.ordo-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.6rem;flex-wrap:wrap;gap:.3rem}
.ordo-head span{font-size:.82rem;font-weight:700;color:var(--dark)}
.ordo-head small{font-size:.75rem;color:var(--gray)}
.ordo-content{background:#F8FAFF;border-radius:8px;padding:.7rem;font-size:.82rem;color:#334155;line-height:1.7;white-space:pre-wrap;border:1px solid var(--lgray);margin-bottom:.7rem}
.ordo-btns{display:flex;gap:.5rem;flex-wrap:wrap}
.btn-ordo{padding:.38rem .85rem;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
.btn-ordo-purple{background:var(--purple);color:#fff;border:none}
.btn-ordo-purple:hover{background:#5B0099;color:#fff}
.btn-ordo-outline{background:transparent;color:var(--purple);border:1.5px solid var(--purple)}
.btn-ordo-outline:hover{background:#FDF4FF}

/* Section vide */
.empty-section{text-align:center;padding:2.5rem;color:var(--gray)}
.empty-section .icon{font-size:2.5rem;margin-bottom:.8rem}
.empty-section p{font-size:.85rem}

/* Notes */
.notes-tab textarea{width:100%;padding:.8rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;resize:vertical;min-height:120px;outline:none;transition:.2s;color:#1E293B}
.notes-tab textarea:focus{border-color:var(--purple);box-shadow:0 0 0 3px rgba(114,9,183,.07)}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:768px){
  .nav-links{display:none;position:fixed;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;z-index:99}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
  .nav-hamburger{display:flex}
  .d-banner{gap:1rem}
  .d-info h1{font-size:1.2rem}
  .d-actions{width:100%;justify-content:flex-start}
  .btn-pdf{font-size:.78rem;padding:.45rem .9rem}
  .vitals-bar{gap:1.2rem;padding:.8rem 4%}
  .main{padding:1rem 4%}
  .ant-item{gap:.6rem}
  .ant-actions{flex-direction:column}
}
@media(max-width:480px){
  .tabs{gap:.2rem}
  .tab{font-size:.72rem;padding:.5rem .3rem}
  .ordo-head{flex-direction:column}
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
    <li><a href="mes_rdv.php">Mes RDV</a></li>
    <li><a href="profil_patient.php">Mon Profil</a></li>
    <li><a href="dossier_patient.php" class="active">Mon Dossier</a></li>
  </ul>
  <div class="nav-right">
    <a href="notifications.php" class="notif-btn">
      🔔<?php if($notif_count>0):?><span class="notif-dot"><?=$notif_count?></span><?php endif;?>
    </a>
    <div class="nav-user">
      <div class="avatar-sm">
        <?php if(!empty($patient['photo']) && file_exists(__DIR__.'/../assets/img/'.$patient['photo'])): ?>
          <img src="../assets/img/<?=htmlspecialchars($patient['photo'])?>" alt="">
        <?php else: ?>
          <?=$initiales?>
        <?php endif; ?>
      </div>
      <span><?=htmlspecialchars($patient['prenom'])?></span>
    </div>
  </div>
</nav>

<!-- ============================================================
     DOSSIER HERO
============================================================ -->
<div class="dossier-hero">
  <div class="breadcrumb">
    <a href="../index.php">Accueil</a> →
    <a href="profil_patient.php">Profil</a> →
    <span>Dossier Médical</span>
  </div>
  <div class="d-banner">
    <div class="d-avatar">📋</div>
    <div class="d-info">
      <h1>Dossier Médical — <?=htmlspecialchars($patient['prenom'].' '.$patient['nom'])?></h1>
      <p>Dossier créé le <?=$date_creation?> • Dernière mise à jour : <?=$date_modif?></p>
      <div class="d-badge">🔒 Accès sécurisé — confidentiel</div>
    </div>
    <div class="d-actions">
      <a href="imprimer_dossier.php" class="btn-pdf">📄 Exporter PDF</a>
      <a href="imprimer_dossier.php?print=1" class="btn-pdf">🖨️ Imprimer</a>
    </div>
  </div>
</div>

<!-- ============================================================
     VITALS BAR
============================================================ -->
<div class="vitals-bar">
  <div class="vital">
    <div class="v-icon" style="background:#EFF6FF">🩸</div>
    <div>
      <div class="v-num"><?=htmlspecialchars($patient['groupe_sanguin']??'—')?></div>
      <div class="v-lbl">Groupe sanguin</div>
    </div>
  </div>
  <div class="vital">
    <div class="v-icon" style="background:#F0FDF4">📏</div>
    <div>
      <div class="v-num"><?=!empty($patient['taille'])?(int)$patient['taille'].' cm':'—'?></div>
      <div class="v-lbl">Taille</div>
    </div>
  </div>
  <div class="vital">
    <div class="v-icon" style="background:#FFF7ED">⚖️</div>
    <div>
      <div class="v-num"><?=!empty($patient['poids'])?(int)$patient['poids'].' kg':'—'?></div>
      <div class="v-lbl">Poids</div>
    </div>
  </div>
  <div class="vital">
    <div class="v-icon" style="background:#FDF4FF">📊</div>
    <div>
      <div class="v-num"><?=$imc?$imc:'—'?></div>
      <div class="v-lbl">IMC</div>
    </div>
  </div>
  <div class="vital">
    <div class="v-icon" style="background:#F0FDF4">📅</div>
    <div>
      <div class="v-num"><?=$age?$age:'—'?></div>
      <div class="v-lbl">Âge</div>
    </div>
  </div>
  <?php if(!empty($dossier['allergies'])): ?>
  <div class="vital">
    <div class="v-icon" style="background:#FFF5F5">⚠️</div>
    <div>
      <div class="v-num" style="color:var(--red);font-size:.9rem">Allergies</div>
      <div class="v-lbl">⚠️ Voir dossier</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">

  <?php if($msg_succes):?>
    <div class="alert alert-success">✅ <?=htmlspecialchars($msg_succes)?></div>
  <?php endif; ?>
  <?php if($msg_erreur):?>
    <div class="alert alert-warn">⚠️ <?=htmlspecialchars($msg_erreur)?></div>
  <?php endif; ?>

  <div class="access-note">
    ⚠️ Ce dossier est <strong>confidentiel</strong>. Accès autorisé uniquement à vous et vos médecins traitants.
  </div>

  <!-- ONGLETS -->
  <div class="tabs">
    <a href="?onglet=antecedents" class="tab <?=$onglet==='antecedents'?'active':''?>">🦠 Antécédents</a>
    <a href="?onglet=consultations" class="tab <?=$onglet==='consultations'?'active':''?>">📅 Consultations (<?=count($consultations)?>)</a>
    <a href="?onglet=ordonnances" class="tab <?=$onglet==='ordonnances'?'active':''?>">💊 Ordonnances</a>
    <a href="?onglet=notes" class="tab <?=$onglet==='notes'?'active':''?>">📝 Notes</a>
  </div>

  <!-- ============================================================
       ONGLET ANTÉCÉDENTS
  ============================================================ -->
  <?php if($onglet === 'antecedents'): ?>

    <?php
      // Allergies
      $allergies = trim($dossier['allergies'] ?? '');
      // Maladies chroniques
      $chroniques = trim($dossier['maladies_chroniques'] ?? '');
      // Chirurgies
      $chirurgies = trim($dossier['chirurgies'] ?? '');
      // Traitements
      $traitements = trim($dossier['traitements_en_cours'] ?? '');
      // Antécédents généraux
      $antecedents = trim($dossier['antecedents'] ?? '');
    ?>

    <!-- ALLERGIES -->
    <div class="section">
      <div class="sec-head">
        <h4>🤧 Allergies</h4>
        <button class="btn-edit-sec" onclick="toggleEdit('edit_allergies')">✏️ Modifier</button>
      </div>

      <div id="view_allergies">
        <?php if($allergies): ?>
          <div class="ant-item allergie">
            <div class="ant-icon">🤧</div>
            <div class="ant-body">
              <h6>Allergies connues</h6>
              <p><?=nl2br(htmlspecialchars($allergies))?></p>
              <span class="ant-badge" style="background:#FFF5F5;color:var(--red);border:1px solid #FECACA">⚠️ Allergie</span>
            </div>
          </div>
        <?php else: ?>
          <p class="empty-field">Aucune allergie enregistrée. Cliquez sur Modifier pour en ajouter.</p>
        <?php endif; ?>
      </div>

      <div id="edit_allergies" style="display:none">
        <form method="POST">
          <input type="hidden" name="action" value="update_dossier">
          <input type="hidden" name="champ" value="allergies">
          <textarea class="dossier-field" name="valeur" placeholder="Ex: Allergie à la Pénicilline — réaction sévère (urticaire, œdème). Découverte en 2018."><?=htmlspecialchars($allergies)?></textarea>
          <div class="field-actions">
            <button type="submit" class="btn-save-f">💾 Sauvegarder</button>
            <button type="button" class="btn-cancel-f" onclick="toggleEdit('edit_allergies')">Annuler</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MALADIES CHRONIQUES -->
    <div class="section">
      <div class="sec-head">
        <h4>🫀 Maladies chroniques</h4>
        <button class="btn-edit-sec" onclick="toggleEdit('edit_chroniques')">✏️ Modifier</button>
      </div>

      <div id="view_chroniques">
        <?php if($chroniques): ?>
          <div class="ant-list">
            <?php foreach(explode("\n", $chroniques) as $ligne):
              $ligne = trim($ligne);
              if(!$ligne) continue;
            ?>
            <div class="ant-item chronique">
              <div class="ant-icon">🫀</div>
              <div class="ant-body">
                <h6><?=htmlspecialchars($ligne)?></h6>
                <span class="ant-badge" style="background:#FFF7ED;color:#C2410C;border:1px solid #FED7AA">🔄 Chronique</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="empty-field">Aucune maladie chronique enregistrée.</p>
        <?php endif; ?>
      </div>

      <div id="edit_chroniques" style="display:none">
        <form method="POST">
          <input type="hidden" name="action" value="update_dossier">
          <input type="hidden" name="champ" value="maladies_chroniques">
          <small style="display:block;color:var(--gray);font-size:.78rem;margin-bottom:.4rem">Entrez une maladie par ligne.</small>
          <textarea class="dossier-field" name="valeur" placeholder="Ex: Hypertension artérielle — diagnostiquée en 2020&#10;Diabète de type 2 — depuis 2019"><?=htmlspecialchars($chroniques)?></textarea>
          <div class="field-actions">
            <button type="submit" class="btn-save-f">💾 Sauvegarder</button>
            <button type="button" class="btn-cancel-f" onclick="toggleEdit('edit_chroniques')">Annuler</button>
          </div>
        </form>
      </div>
    </div>

    <!-- CHIRURGIES -->
    <div class="section">
      <div class="sec-head">
        <h4>🔪 Chirurgies antérieures</h4>
        <button class="btn-edit-sec" onclick="toggleEdit('edit_chirurgies')">✏️ Modifier</button>
      </div>

      <div id="view_chirurgies">
        <?php if($chirurgies): ?>
          <div class="ant-list">
            <?php foreach(explode("\n", $chirurgies) as $ligne):
              $ligne = trim($ligne);
              if(!$ligne) continue;
            ?>
            <div class="ant-item chirurgie">
              <div class="ant-icon">🔪</div>
              <div class="ant-body">
                <h6><?=htmlspecialchars($ligne)?></h6>
                <span class="ant-badge" style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE">🔪 Chirurgie</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="empty-field">Aucune chirurgie enregistrée.</p>
        <?php endif; ?>
      </div>

      <div id="edit_chirurgies" style="display:none">
        <form method="POST">
          <input type="hidden" name="action" value="update_dossier">
          <input type="hidden" name="champ" value="chirurgies">
          <small style="display:block;color:var(--gray);font-size:.78rem;margin-bottom:.4rem">Entrez une chirurgie par ligne.</small>
          <textarea class="dossier-field" name="valeur" placeholder="Ex: Appendicectomie — Mars 2015 — Hôpital Principal Dakar"><?=htmlspecialchars($chirurgies)?></textarea>
          <div class="field-actions">
            <button type="submit" class="btn-save-f">💾 Sauvegarder</button>
            <button type="button" class="btn-cancel-f" onclick="toggleEdit('edit_chirurgies')">Annuler</button>
          </div>
        </form>
      </div>
    </div>

    <!-- TRAITEMENTS EN COURS -->
    <div class="section">
      <div class="sec-head">
        <h4>💊 Traitements en cours</h4>
        <button class="btn-edit-sec" onclick="toggleEdit('edit_traitements')">✏️ Modifier</button>
      </div>

      <div id="view_traitements">
        <?php if($traitements): ?>
          <div class="ant-list">
            <?php foreach(explode("\n", $traitements) as $ligne):
              $ligne = trim($ligne);
              if(!$ligne) continue;
            ?>
            <div class="ant-item traitement">
              <div class="ant-icon">💊</div>
              <div class="ant-body">
                <h6><?=htmlspecialchars($ligne)?></h6>
                <span class="ant-badge" style="background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0">✅ Traitement actif</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="empty-field">Aucun traitement en cours enregistré.</p>
        <?php endif; ?>
      </div>

      <div id="edit_traitements" style="display:none">
        <form method="POST">
          <input type="hidden" name="action" value="update_dossier">
          <input type="hidden" name="champ" value="traitements_en_cours">
          <small style="display:block;color:var(--gray);font-size:.78rem;margin-bottom:.4rem">Entrez un traitement par ligne.</small>
          <textarea class="dossier-field" name="valeur" placeholder="Ex: Amlodipine 5mg — 1 comprimé / matin — 90 jours&#10;Aspirine 100mg — 1 comprimé / soir"><?=htmlspecialchars($traitements)?></textarea>
          <div class="field-actions">
            <button type="submit" class="btn-save-f">💾 Sauvegarder</button>
            <button type="button" class="btn-cancel-f" onclick="toggleEdit('edit_traitements')">Annuler</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Lien vers ajouter antécédent détaillé -->
    <div style="text-align:center;margin-top:1rem">
      <a href="ajouter_antecedent.php" class="btn-add" style="display:inline-flex;padding:.7rem 1.5rem;font-size:.88rem">
        + Ajouter un antécédent détaillé
      </a>
    </div>

  <?php endif; ?>

  <!-- ============================================================
       ONGLET CONSULTATIONS
  ============================================================ -->
  <?php if($onglet === 'consultations'): ?>
    <div class="section">
      <div class="sec-head">
        <h4>📅 Historique des consultations</h4>
        <a href="historique_patient.php" style="font-size:.82rem;color:var(--blue);font-weight:600;text-decoration:none">Voir tout →</a>
      </div>

      <?php if(empty($consultations)): ?>
        <div class="empty-section">
          <div class="icon">📅</div>
          <p>Aucune consultation enregistrée pour le moment.</p>
        </div>
      <?php else: ?>
        <?php foreach($consultations as $c):
          $coul = $couleurs_spec[$c['specialite']] ?? $couleurs_spec['default'];
          $date_c = fmtDate(substr($c['date_consultation'],0,10),$mois_fr);
        ?>
        <div class="consult-item">
          <div class="c-date"><?=$date_c?></div>
          <div class="c-doc" style="color:<?=$coul?>">Dr. <?=htmlspecialchars($c['m_prenom'].' '.$c['m_nom'])?> — <?=htmlspecialchars($c['specialite'])?></div>
          <?php if(!empty($c['diagnostic'])): ?>
            <div class="c-diag">🔬 Diagnostic : <?=htmlspecialchars(mb_substr($c['diagnostic'],0,150)).(mb_strlen($c['diagnostic'])>150?'...':'')?></div>
          <?php endif; ?>
          <a href="detail_rdv.php?id=<?=$c['id_rendezvous']?>" class="c-btn">Voir compte-rendu →</a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- ============================================================
       ONGLET ORDONNANCES
  ============================================================ -->
  <?php if($onglet === 'ordonnances'): ?>
    <div class="section">
      <div class="sec-head"><h4>💊 Ordonnances</h4></div>

      <?php if(empty($ordonnances)): ?>
        <div class="empty-section">
          <div class="icon">💊</div>
          <p>Aucune ordonnance enregistrée.</p>
        </div>
      <?php else: ?>
        <?php foreach($ordonnances as $o):
          $date_o = fmtDate(substr($o['date_consultation'],0,10),$mois_fr);
        ?>
        <div class="ordo-card">
          <div class="ordo-head">
            <span>Ordonnance — Dr. <?=htmlspecialchars($o['m_prenom'].' '.$o['m_nom'])?></span>
            <small><?=$date_o?> • <?=htmlspecialchars($o['specialite'])?></small>
          </div>
          <div class="ordo-content"><?=htmlspecialchars($o['ordonnance'])?></div>
          <div class="ordo-btns">
            <a href="imprimer_dossier.php?rdv=<?=$o['id_rendezvous']??''?>" class="btn-ordo btn-ordo-purple" target="_blank">📄 Voir PDF</a>
            <a href="imprimer_dossier.php?rdv=<?=$o['id_rendezvous']??''?>&print=1" class="btn-ordo btn-ordo-outline">🖨️ Imprimer</a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- ============================================================
       ONGLET NOTES
  ============================================================ -->
  <?php if($onglet === 'notes'): ?>
    <div class="section notes-tab">
      <div class="sec-head"><h4>📝 Notes personnelles</h4></div>
      <p style="font-size:.83rem;color:var(--gray);margin-bottom:.8rem">
        Espace personnel pour noter vos symptômes, questions à poser à votre médecin, etc.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="update_dossier">
        <input type="hidden" name="champ" value="notes_admin">
        <textarea class="dossier-field" name="valeur"
                  placeholder="Notez vos symptômes, vos observations, vos questions pour votre prochain rendez-vous..."
                  style="min-height:150px"><?=htmlspecialchars($dossier['notes_admin']??'')?></textarea>
        <div class="field-actions" style="margin-top:.8rem">
          <button type="submit" class="btn-save-f">💾 Sauvegarder mes notes</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

</div><!-- /main -->

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Fatou Yankaty (E4)</strong> — dossier_patient.php</p>
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

// --- Toggle edit mode ---
function toggleEdit(id) {
  var editEl = document.getElementById(id);
  var viewId = id.replace('edit_','view_');
  var viewEl = document.getElementById(viewId);
  var isOpen = editEl.style.display !== 'none';
  editEl.style.display = isOpen ? 'none' : 'block';
  if(viewEl) viewEl.style.display = isOpen ? 'block' : 'none';
}

<?php if($msg_succes): ?>
// Auto-fermer les zones d'édition après succès
document.querySelectorAll('[id^="edit_"]').forEach(function(el){el.style.display='none';});
document.querySelectorAll('[id^="view_"]').forEach(function(el){el.style.display='block';});
<?php endif; ?>
</script>
</body>
</html>