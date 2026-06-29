<?php
// ================================================================
// detail_rdv.php — Détail d'un rendez-vous patient
// Responsable : Diama Touré (Etudiant 3)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Protection : patient connecté uniquement
if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php?redirect=mes_rdv.php');
}

$patient_id = $_SESSION['user_id'];
$rdv_id     = (int)($_GET['id'] ?? 0);

if ($rdv_id === 0) redirect('mes_rdv.php');

// ================================================================
// RÉCUPÉRER LE RDV (avec médecin + consultation)
// ================================================================
$stmt = $pdo->prepare("
    SELECT
        r.*,
        m.nom        AS m_nom,
        m.prenom     AS m_prenom,
        m.specialite,
        m.cabinet,
        m.ville,
        m.telephone  AS m_tel,
        m.email      AS m_email,
        m.photo      AS m_photo,
        m.biographie AS m_bio,
        c.id         AS c_id,
        c.diagnostic,
        c.traitement,
        c.ordonnance,
        c.compte_rendu,
        c.prochain_rdv,
        c.date_consultation
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    LEFT JOIN consultations c ON c.id_rendezvous = r.id
    WHERE r.id = :rid AND r.id_patient = :pid
    LIMIT 1
");
$stmt->execute([':rid' => $rdv_id, ':pid' => $patient_id]);
$rdv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rdv) {
    // RDV introuvable ou n'appartient pas au patient
    redirect('mes_rdv.php');
}

// ================================================================
// JOURNALISER L'ACCÈS AU DOSSIER
// ================================================================
try {
    $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, ip_address)
                   VALUES (:id, 'patient', 'consultation_detail_rdv_#".$rdv_id."', :ip)")
        ->execute([':id' => $patient_id, ':ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
} catch(Exception $e) { /* non bloquant */ }

// ================================================================
// TRAITEMENT — ANNULATION
// ================================================================
$msg_succes = '';
$msg_erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'annuler') {
    $motif_annul = trim($_POST['motif_annulation'] ?? '');
    $is_futur    = (new DateTime($rdv['date_rdv'])) >= new DateTime('today');

    if (!in_array($rdv['statut'], ['en_attente','confirme']) || !$is_futur) {
        $msg_erreur = 'Ce rendez-vous ne peut plus être annulé.';
    } else {
        $pdo->prepare("UPDATE rendezvous SET statut='annule', motif_annulation=:m WHERE id=:id")
            ->execute([':m' => $motif_annul ?: 'Annulé par le patient', ':id' => $rdv_id]);

        // Notification au médecin
        $pdo->prepare("INSERT INTO notifications (id_destinataire,type_user,type_notif,message,id_rdv)
                       VALUES (:id,'medecin','rdv_annule',:msg,:rdv)")
            ->execute([':id' => $rdv['id_medecin'], ':rdv' => $rdv_id,
                       ':msg' => "Le patient {$_SESSION['prenom']} {$_SESSION['nom']} a annulé son RDV du {$rdv['date_rdv']} à ".substr($rdv['heure_rdv'],0,5)."."]);

        redirect('mes_rdv.php?annule=1');
    }
}

// ================================================================
// HELPERS
// ================================================================
$mois_fr = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril',
            'May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août',
            'September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];

function fmtDate($d, $j, $m) {
    if (!$d) return '—';
    $dt = new DateTime($d);
    return ($j[$dt->format('l')]??$dt->format('l')).' '.$dt->format('j').' '.($m[$dt->format('F')]??$dt->format('F')).' '.$dt->format('Y');
}
function fmtHeure($h) { return $h ? substr($h,0,5) : '—'; }

$date_fmt   = fmtDate($rdv['date_rdv'], $jours_fr, $mois_fr);
$heure_fmt  = fmtHeure($rdv['heure_rdv']);
$created_fmt= fmtDate(substr($rdv['date_creation'],0,10), $jours_fr, $mois_fr);

// Badge statut
$badges = [
    'confirme'   => ['class'=>'badge-confirme',  'label'=>'✅ Confirmé',    'color'=>'#15803D'],
    'en_attente' => ['class'=>'badge-attente',   'label'=>'⏳ En attente',  'color'=>'#C2410C'],
    'annule'     => ['class'=>'badge-annule',    'label'=>'❌ Annulé',      'color'=>'#D62828'],
    'refuse'     => ['class'=>'badge-refuse',    'label'=>'🚫 Refusé',     'color'=>'#D62828'],
    'termine'    => ['class'=>'badge-termine',   'label'=>'✔ Terminé',     'color'=>'#64748B'],
];
$badge = $badges[$rdv['statut']] ?? $badges['termine'];

// Couleurs spécialité
$couleurs = [
    'Cardiologie'  =>['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','bg'=>'#EFF6FF','c'=>'#0077B6'],
    'Pediatrie'    =>['grad'=>'linear-gradient(135deg,#7209B7,#B5179E)','bg'=>'#FDF4FF','c'=>'#7209B7'],
    'Dermatologie' =>['grad'=>'linear-gradient(135deg,#F77F00,#D62828)','bg'=>'#FFF7ED','c'=>'#F77F00'],
    'Gynecologie'  =>['grad'=>'linear-gradient(135deg,#006D77,#2DC653)','bg'=>'#F0FDF4','c'=>'#2DC653'],
    'Neurologie'   =>['grad'=>'linear-gradient(135deg,#3A0CA3,#7B2FBE)','bg'=>'#EEF0FF','c'=>'#3A0CA3'],
    'Ophtalmologie'=>['grad'=>'linear-gradient(135deg,#0096C7,#48CAE4)','bg'=>'#E0F7FA','c'=>'#0096C7'],
    'default'      =>['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','bg'=>'#EFF6FF','c'=>'#0077B6'],
];
$coul = $couleurs[$rdv['specialite']] ?? $couleurs['default'];

$is_futur   = (new DateTime($rdv['date_rdv'])) >= new DateTime('today');
$can_cancel = in_array($rdv['statut'], ['en_attente','confirme']) && $is_futur;

// Notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user='patient' AND lu=0");
$stmt->execute([':id'=>$patient_id]);
$notif_count = (int)$stmt->fetchColumn();

$initiales = strtoupper(mb_substr($_SESSION['prenom'],0,1).mb_substr($_SESSION['nom']??'',0,1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Détail RDV #<?= $rdv_id ?></title>
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
.notif-btn{color:#CBD5E1;font-size:.88rem;text-decoration:none;position:relative}
.notif-dot{position:absolute;top:-6px;right:-6px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;font-size:.58rem;display:flex;align-items:center;justify-content:center;font-weight:700}
.avatar-sm{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;font-weight:700}
.nav-user{display:flex;align-items:center;gap:.5rem;color:#CBD5E1;font-size:.85rem}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- PAGE HEADER ---- */
.page-hdr{background:linear-gradient(135deg,var(--dark),var(--blue2));padding:1.8rem 5%;color:#fff}
.breadcrumb{font-size:.78rem;color:#94A3B8;margin-bottom:.5rem}
.breadcrumb a{color:#94A3B8;text-decoration:none;transition:.2s}
.breadcrumb a:hover{color:#fff}
.breadcrumb span{color:var(--green)}
.page-hdr h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}

/* Badges */
.badge{padding:.3rem .9rem;border-radius:20px;font-size:.78rem;font-weight:700;white-space:nowrap}
.badge-confirme{background:rgba(34,197,94,.2);color:#86EFAC;border:1px solid rgba(34,197,94,.3)}
.badge-attente{background:rgba(251,146,60,.2);color:#FED7AA;border:1px solid rgba(251,146,60,.3)}
.badge-annule,.badge-refuse{background:rgba(239,68,68,.2);color:#FCA5A5;border:1px solid rgba(239,68,68,.3)}
.badge-termine{background:rgba(148,163,184,.2);color:#CBD5E1;border:1px solid rgba(148,163,184,.3)}

/* ---- MAIN ---- */
.main{display:flex;gap:1.5rem;padding:1.8rem 5%;max-width:1000px;margin:0 auto;align-items:flex-start}
.left-col{flex:1;min-width:0}
.right-col{width:290px;flex-shrink:0}

/* ---- CARDS ---- */
.card{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:1.2rem}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:.7rem;border-bottom:1px solid var(--lgray)}
.card-head h3{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark)}

/* Alertes */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

/* ---- MÉDECIN CARD ---- */
.doc-banner{display:flex;gap:1rem;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap}
.doc-avatar{width:70px;height:70px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;overflow:hidden}
.doc-avatar img{width:100%;height:100%;object-fit:cover;border-radius:14px}
.doc-name{font-family:'Sora',sans-serif;font-size:1.05rem;font-weight:700;color:#1E293B}
.doc-spec-badge{display:inline-block;font-size:.75rem;font-weight:600;padding:.22rem .6rem;border-radius:6px;margin:.3rem 0 .4rem}
.doc-contact{font-size:.83rem;color:var(--gray);line-height:1.8}
.doc-contact a{color:var(--blue);text-decoration:none}
.doc-contact a:hover{color:var(--blue2)}
.doc-bio{font-size:.82rem;color:var(--gray);margin-top:.6rem;line-height:1.6;padding-top:.6rem;border-top:1px solid var(--lgray)}

/* ---- DÉTAILS RDV ---- */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
.info-item{background:var(--light);border-radius:10px;padding:.8rem 1rem}
.info-item .lbl{font-size:.73rem;font-weight:600;color:var(--gray);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem}
.info-item .val{font-size:.92rem;font-weight:700;color:#1E293B}
.info-item .val.blue{color:var(--blue)}
.info-item .val.green{color:var(--green)}

/* ---- MOTIF ---- */
.motif-box{background:var(--light);border-left:3px solid var(--blue);border-radius:0 10px 10px 0;padding:.9rem 1rem;font-size:.87rem;color:#334155;line-height:1.6;margin-top:1rem}

/* ---- MESSAGE MÉDECIN ---- */
.msg-box{background:#FFF7ED;border-left:3px solid var(--orange);border-radius:0 10px 10px 0;padding:.9rem 1rem;font-size:.87rem;color:#92400E;line-height:1.6;margin-top:1rem}
.msg-box.refuse{background:#FFF5F5;border-color:var(--red);color:#7F1D1D}

/* ---- CONSULTATION (compte-rendu + ordonnance) ---- */
.cr-section{margin-top:1rem}
.cr-block{background:#F8FAFF;border:1px solid var(--lgray);border-radius:10px;padding:1rem;margin-bottom:.8rem}
.cr-block h5{font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:var(--dark);margin-bottom:.6rem;display:flex;align-items:center;gap:.4rem}
.cr-block p{font-size:.85rem;color:#334155;line-height:1.7;white-space:pre-wrap}

/* ---- TIMELINE STATUT ---- */
.timeline{margin-top:.5rem}
.tl-item{display:flex;gap:.8rem;align-items:flex-start;padding:.5rem 0;position:relative}
.tl-item:not(:last-child)::after{content:'';position:absolute;left:11px;top:28px;width:2px;height:calc(100% - 8px);background:var(--lgray)}
.tl-dot{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;flex-shrink:0;z-index:1}
.tl-dot.done{background:var(--green);color:#fff}
.tl-dot.active{background:var(--blue);color:#fff;box-shadow:0 0 0 4px rgba(0,119,182,.15)}
.tl-dot.pending{background:var(--lgray);color:var(--gray)}
.tl-text h6{font-size:.83rem;font-weight:600;color:#334155}
.tl-text p{font-size:.75rem;color:var(--gray);margin-top:.1rem}

/* ---- ACTIONS SIDEBAR ---- */
.action-card{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:1rem;position:sticky;top:80px}
.action-card h4{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid var(--lgray)}
.btn-action{display:block;width:100%;padding:.75rem 1rem;border-radius:9px;font-size:.85rem;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;transition:.2s;margin-bottom:.6rem;font-family:'DM Sans',sans-serif;border:none}
.btn-action:last-child{margin-bottom:0}
.btn-primary-a{background:var(--blue);color:#fff;box-shadow:0 3px 10px rgba(0,119,182,.25)}
.btn-primary-a:hover{background:var(--blue2);color:#fff}
.btn-green-a{background:var(--green);color:#fff;box-shadow:0 3px 10px rgba(45,198,83,.25)}
.btn-green-a:hover{background:#25B347;color:#fff}
.btn-danger-a{background:transparent;color:var(--red);border:1.5px solid var(--red)}
.btn-danger-a:hover{background:#FFF5F5}
.btn-gray-a{background:transparent;color:var(--gray);border:1.5px solid var(--lgray)}
.btn-gray-a:hover{border-color:var(--gray);color:#334155}

/* ---- MODAL ANNULATION ---- */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:2rem;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:700;color:var(--dark);margin-bottom:.5rem}
.modal p{font-size:.85rem;color:var(--gray);margin-bottom:1.2rem;line-height:1.5}
.modal-info{background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:.7rem .9rem;font-size:.82rem;color:#92400E;margin-bottom:1rem}
.modal textarea{width:100%;padding:.7rem .9rem;border:1.5px solid var(--lgray);border-radius:9px;font-size:.85rem;font-family:'DM Sans',sans-serif;resize:vertical;min-height:70px;outline:none;margin-bottom:1.2rem;transition:.2s}
.modal textarea:focus{border-color:var(--red)}
.modal-btns{display:flex;gap:.7rem;justify-content:flex-end}
.btn-cancel-m{padding:.6rem 1.2rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:8px;font-size:.85rem;cursor:pointer;font-family:'DM Sans',sans-serif}
.btn-confirm-a{padding:.6rem 1.4rem;background:var(--red);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif}

/* ---- ID RDV ---- */
.rdv-id-tag{display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:.3rem .7rem;font-size:.78rem;color:#CBD5E1;margin-bottom:.6rem}

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
  .main{flex-direction:column;padding:1rem 4%}
  .right-col{width:100%;position:static}
  .action-card{position:static}
  .info-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:480px){
  .info-grid{grid-template-columns:1fr}
  .page-hdr h1{font-size:1.2rem}
  .doc-avatar{width:54px;height:54px;font-size:1.5rem}
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
    <li><a href="mes_rdv.php" class="active">Mes RDV</a></li>
    <li><a href="profil_patient.php">Mon Profil</a></li>
  </ul>
  <div class="nav-right">
    <a href="notifications.php" class="notif-btn">
      🔔<?php if($notif_count>0):?><span class="notif-dot"><?=$notif_count?></span><?php endif;?>
    </a>
    <div class="nav-user">
      <div class="avatar-sm"><?=$initiales?></div>
      <span><?=htmlspecialchars($_SESSION['prenom']??'')?></span>
    </div>
  </div>
</nav>

<!-- ============================================================
     PAGE HEADER
============================================================ -->
<div class="page-hdr">
  <div class="breadcrumb">
    <a href="../index.php">Accueil</a> →
    <a href="mes_rdv.php">Mes RDV</a> →
    <span>Détail RDV #<?=$rdv_id?></span>
  </div>
  <div class="rdv-id-tag">🎫 RDV #<?=str_pad($rdv_id,4,'0',STR_PAD_LEFT)?></div>
  <h1>
    Rendez-vous — Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?>
    <span class="badge <?=$badge['class']?>"><?=$badge['label']?></span>
  </h1>
</div>

<!-- ============================================================
     MODAL ANNULATION
============================================================ -->
<div class="modal-overlay" id="modalAnnul">
  <div class="modal">
    <h3>❌ Annuler ce rendez-vous</h3>
    <p>Cette action est irréversible. Le médecin sera notifié de l'annulation.</p>
    <div class="modal-info">
      📅 <?=$date_fmt?> à <?=$heure_fmt?><br>
      👨‍⚕️ Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="annuler">
      <textarea name="motif_annulation" placeholder="Motif d'annulation (optionnel)..."></textarea>
      <div class="modal-btns">
        <button type="button" class="btn-cancel-m" onclick="closeModal()">Retour</button>
        <button type="submit" class="btn-confirm-a">Confirmer l'annulation</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">

  <!-- ========== COLONNE GAUCHE ========== -->
  <div class="left-col">

    <?php if($msg_erreur):?>
      <div class="alert alert-error">⚠️ <?=htmlspecialchars($msg_erreur)?></div>
    <?php endif;?>

    <!-- MÉDECIN -->
    <div class="card">
      <div class="card-head">
        <h3>👨‍⚕️ Médecin</h3>
        <a href="detail_medecin.php?id=<?=$rdv['id_medecin']?>" style="font-size:.82rem;color:var(--blue);text-decoration:none;font-weight:600">Voir profil →</a>
      </div>
      <div class="doc-banner">
        <div class="doc-avatar" style="background:<?=$coul['grad']?>">
          <?php if(!empty($rdv['m_photo']) && file_exists(__DIR__.'/../assets/img/'.$rdv['m_photo'])): ?>
            <img src="../assets/img/<?=htmlspecialchars($rdv['m_photo'])?>" alt="Dr">
          <?php else: ?>
            👨‍⚕️
          <?php endif; ?>
        </div>
        <div>
          <div class="doc-name">Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?></div>
          <span class="doc-spec-badge" style="background:<?=$coul['bg']?>;color:<?=$coul['c']?>">
            <?=htmlspecialchars($rdv['specialite'])?>
          </span>
          <div class="doc-contact">
            📍 <?=htmlspecialchars($rdv['cabinet'].', '.$rdv['ville'])?>
            <?php if(!empty($rdv['m_tel'])): ?>
              <br>📞 <?=htmlspecialchars($rdv['m_tel'])?>
            <?php endif;?>
            <?php if(!empty($rdv['m_email'])): ?>
              <br>✉️ <a href="mailto:<?=htmlspecialchars($rdv['m_email'])?>"><?=htmlspecialchars($rdv['m_email'])?></a>
            <?php endif;?>
          </div>
          <?php if(!empty($rdv['m_bio'])): ?>
            <div class="doc-bio"><?=htmlspecialchars(mb_substr($rdv['m_bio'],0,180)).(mb_strlen($rdv['m_bio'])>180?'...':'')?></div>
          <?php endif;?>
        </div>
      </div>
    </div>

    <!-- DÉTAILS DU RDV -->
    <div class="card">
      <div class="card-head"><h3>📅 Détails du rendez-vous</h3></div>
      <div class="info-grid">
        <div class="info-item">
          <div class="lbl">Date</div>
          <div class="val blue"><?=$date_fmt?></div>
        </div>
        <div class="info-item">
          <div class="lbl">Heure</div>
          <div class="val blue"><?=$heure_fmt?></div>
        </div>
        <div class="info-item">
          <div class="lbl">Durée</div>
          <div class="val">30 minutes</div>
        </div>
        <div class="info-item">
          <div class="lbl">Statut</div>
          <div class="val" style="color:<?=$badge['color']?>"><?=$badge['label']?></div>
        </div>
        <div class="info-item">
          <div class="lbl">Spécialité</div>
          <div class="val"><?=htmlspecialchars($rdv['specialite'])?></div>
        </div>
        <div class="info-item">
          <div class="lbl">Cabinet</div>
          <div class="val"><?=htmlspecialchars($rdv['ville'])?></div>
        </div>
      </div>

      <!-- Motif -->
      <?php if(!empty($rdv['motif'])): ?>
        <div class="motif-box">
          <strong>📝 Motif de consultation :</strong><br>
          <?=htmlspecialchars($rdv['motif'])?>
        </div>
      <?php endif; ?>

      <!-- Message médecin (confirmer/refuser) -->
      <?php if(!empty($rdv['message_medecin'])): ?>
        <div class="msg-box <?=$rdv['statut']==='refuse'?'refuse':''?>">
          <strong>💬 Message du médecin :</strong><br>
          <?=htmlspecialchars($rdv['message_medecin'])?>
        </div>
      <?php endif; ?>

      <!-- Motif annulation -->
      <?php if(in_array($rdv['statut'],['annule','refuse']) && !empty($rdv['motif_annulation'])): ?>
        <div class="msg-box refuse">
          <strong>❌ Motif d'annulation :</strong><br>
          <?=htmlspecialchars($rdv['motif_annulation'])?>
        </div>
      <?php endif; ?>
    </div>

    <!-- COMPTE-RENDU / ORDONNANCE (si consultation terminée) -->
    <?php if($rdv['statut'] === 'termine' && $rdv['c_id']): ?>
    <div class="card">
      <div class="card-head">
        <h3>📋 Compte-rendu de consultation</h3>
        <span style="font-size:.78rem;color:var(--gray)">
          <?= $rdv['date_consultation'] ? fmtDate(substr($rdv['date_consultation'],0,10),$jours_fr,$mois_fr) : '' ?>
        </span>
      </div>

      <div class="cr-section">
        <?php if(!empty($rdv['diagnostic'])): ?>
        <div class="cr-block">
          <h5>🔬 Diagnostic</h5>
          <p><?=htmlspecialchars($rdv['diagnostic'])?></p>
        </div>
        <?php endif; ?>

        <?php if(!empty($rdv['traitement'])): ?>
        <div class="cr-block">
          <h5>💊 Traitement prescrit</h5>
          <p><?=htmlspecialchars($rdv['traitement'])?></p>
        </div>
        <?php endif; ?>

        <?php if(!empty($rdv['compte_rendu'])): ?>
        <div class="cr-block">
          <h5>📄 Compte-rendu détaillé</h5>
          <p><?=htmlspecialchars($rdv['compte_rendu'])?></p>
        </div>
        <?php endif; ?>

        <?php if(!empty($rdv['ordonnance'])): ?>
        <div class="cr-block" style="border-color:#BBF7D0">
          <h5>📃 Ordonnance</h5>
          <p><?=htmlspecialchars($rdv['ordonnance'])?></p>
        </div>
        <?php endif; ?>

        <?php if(!empty($rdv['prochain_rdv'])): ?>
        <div class="cr-block" style="background:#F0FDF4;border-color:#BBF7D0">
          <h5>📅 Prochain rendez-vous recommandé</h5>
          <p style="color:var(--green);font-weight:700">
            <?=fmtDate($rdv['prochain_rdv'],$jours_fr,$mois_fr)?>
          </p>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /left-col -->

  <!-- ========== COLONNE DROITE ========== -->
  <div class="right-col">

    <!-- ACTIONS -->
    <div class="action-card">
      <h4>⚡ Actions</h4>

      <?php if($can_cancel): ?>
        <button class="btn-action btn-danger-a" onclick="document.getElementById('modalAnnul').classList.add('open')">
          ❌ Annuler ce RDV
        </button>
      <?php endif; ?>

      <?php if($rdv['statut'] === 'termine'): ?>
        <a href="prendre_rdv.php?medecin=<?=$rdv['id_medecin']?>" class="btn-action btn-green-a">
          🔄 Reprendre RDV
        </a>
        <?php if($rdv['c_id'] && !empty($rdv['ordonnance'])): ?>
          <a href="imprimer_dossier.php?rdv=<?=$rdv_id?>" class="btn-action btn-primary-a" target="_blank">
            🖨️ Imprimer l'ordonnance
          </a>
        <?php endif; ?>
      <?php endif; ?>

      <a href="detail_medecin.php?id=<?=$rdv['id_medecin']?>" class="btn-action btn-primary-a">
        👨‍⚕️ Voir le profil du médecin
      </a>

      <a href="mes_rdv.php" class="btn-action btn-gray-a">
        ← Retour à mes RDV
      </a>
    </div>

    <!-- TIMELINE STATUT -->
    <div class="card">
      <div class="card-head"><h3>📊 Suivi du statut</h3></div>
      <div class="timeline">

        <div class="tl-item">
          <div class="tl-dot done">✓</div>
          <div class="tl-text">
            <h6>Demande envoyée</h6>
            <p><?=$created_fmt?></p>
          </div>
        </div>

        <div class="tl-item">
          <div class="tl-dot <?=in_array($rdv['statut'],['confirme','termine'])?'done':($rdv['statut']==='en_attente'?'active':'pending')?>">
            <?=in_array($rdv['statut'],['confirme','termine'])?'✓':($rdv['statut']==='en_attente'?'⏳':'✕')?>
          </div>
          <div class="tl-text">
            <h6>
              <?php
                if($rdv['statut']==='en_attente') echo 'En attente de confirmation';
                elseif($rdv['statut']==='confirme') echo 'Confirmé par le médecin';
                elseif($rdv['statut']==='refuse')   echo 'Refusé par le médecin';
                elseif($rdv['statut']==='annule')   echo 'Annulé';
                else echo 'Confirmé';
              ?>
            </h6>
          </div>
        </div>

        <div class="tl-item">
          <div class="tl-dot <?=$rdv['statut']==='termine'?'done':'pending'?>">
            <?=$rdv['statut']==='termine'?'✓':'3'?>
          </div>
          <div class="tl-text">
            <h6>Consultation effectuée</h6>
            <?php if($rdv['statut']==='termine' && $rdv['date_consultation']): ?>
              <p><?=fmtDate(substr($rdv['date_consultation'],0,10),$jours_fr,$mois_fr)?></p>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- INFOS RAPIDES -->
    <div class="card">
      <div class="card-head"><h3>ℹ️ Infos rapides</h3></div>
      <div style="font-size:.83rem;color:var(--gray);line-height:2">
        <div style="display:flex;justify-content:space-between">
          <span>N° RDV</span>
          <strong style="color:#334155">#<?=str_pad($rdv_id,4,'0',STR_PAD_LEFT)?></strong>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span>Date demande</span>
          <strong style="color:#334155"><?=(new DateTime(substr($rdv['date_creation'],0,10)))->format('d/m/Y')?></strong>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span>Spécialité</span>
          <strong style="color:#334155"><?=htmlspecialchars($rdv['specialite'])?></strong>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span>Lieu</span>
          <strong style="color:#334155;text-align:right;max-width:55%"><?=htmlspecialchars($rdv['ville'])?></strong>
        </div>
      </div>
    </div>

  </div><!-- /right-col -->
</div><!-- /main -->

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Diama Touré (E3)</strong> — detail_rdv.php</p>
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
// --- Modal ---
function closeModal(){
  document.getElementById('modalAnnul').classList.remove('open');
}
document.getElementById('modalAnnul').addEventListener('click',function(e){
  if(e.target===this) closeModal();
});
</script>
</body>
</html>