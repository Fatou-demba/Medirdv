<?php
// ================================================================
// ajouter_antecedent.php — Ajouter / Modifier un Antécédent Médical
// Responsable : Fatou Yankaty (Etudiant 4)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Protection : patient connecté uniquement
if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php?redirect=ajouter_antecedent.php');
}

$patient_id = $_SESSION['user_id'];

// ================================================================
// RÉCUPÉRER LE DOSSIER (créer si inexistant)
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dossier) {
    $pdo->prepare("INSERT INTO dossiers (id_patient) VALUES (:id)")->execute([':id' => $patient_id]);
    $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id");
    $stmt->execute([':id' => $patient_id]);
    $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
}

$dossier_id = $dossier['id'];

// ================================================================
// MODE ÉDITION (si ?edit=champ passé depuis dossier_patient)
// Ici on gère les antécédents comme des lignes dans les champs TEXT
// On propose aussi un formulaire structuré qui ajoute au champ approprié
// ================================================================
$mode_edit  = false;
$edit_champ = $_GET['champ'] ?? ''; // ex: allergies, chirurgies, etc.
$edit_index = (int)($_GET['idx'] ?? -1); // index de la ligne à éditer

// Valeurs pré-remplies en édition
$pre = [
    'type'        => 'allergies',
    'intitule'    => '',
    'description' => '',
    'date_decouverte' => '',
    'statut'      => 'En cours / Actif',
    'severite'    => 'modere',
    'traitement'  => '',
    'medecin'     => '',
];

if ($edit_champ && $edit_index >= 0) {
    $mode_edit = true;
    $pre['type'] = $edit_champ;
    $lignes = explode("\n", $dossier[$edit_champ] ?? '');
    $pre['intitule'] = trim($lignes[$edit_index] ?? '');
}

// ================================================================
// TRAITEMENT FORMULAIRE
// ================================================================
$erreurs  = [];
$msg_succes = '';

// Champs du dossier et leurs libellés
$champs_map = [
    'allergies'            => 'allergies',
    'maladies_chroniques'  => 'maladies_chroniques',
    'chirurgies'           => 'chirurgies',
    'traitements_en_cours' => 'traitements_en_cours',
    'antecedents'          => 'antecedents',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- AJOUTER / MODIFIER ---
    if ($_POST['action'] === 'sauvegarder') {
        $type        = $_POST['type']        ?? 'allergies';
        $intitule    = trim($_POST['intitule']    ?? '');
        $description = trim($_POST['description'] ?? '');
        $date_dec    = trim($_POST['date_decouverte'] ?? '');
        $statut      = trim($_POST['statut']      ?? '');
        $severite    = trim($_POST['severite']    ?? '');
        $traitement  = trim($_POST['traitement']  ?? '');
        $medecin     = trim($_POST['medecin']     ?? '');
        $idx         = (int)($_POST['edit_index'] ?? -1);

        if (!isset($champs_map[$type])) $erreurs['type'] = 'Type invalide.';
        if ($intitule === '') $erreurs['intitule'] = 'L\'intitulé est obligatoire.';

        if (empty($erreurs)) {
            // Construire la ligne formatée
            $parts = [$intitule];
            if ($description) $parts[] = $description;
            if ($date_dec)    $parts[] = "Depuis : $date_dec";
            if ($statut)      $parts[] = "Statut : $statut";
            if ($severite)    $parts[] = "Sévérité : $severite";
            if ($traitement)  $parts[] = "Traitement : $traitement";
            if ($medecin)     $parts[] = "Médecin : $medecin";

            $nouvelle_ligne = implode(' — ', $parts);

            // Récupérer le champ actuel
            $champ = $champs_map[$type];
            $valeur_actuelle = trim($dossier[$champ] ?? '');
            $lignes = $valeur_actuelle ? explode("\n", $valeur_actuelle) : [];

            if ($idx >= 0 && isset($lignes[$idx])) {
                // Mode édition : remplacer la ligne
                $lignes[$idx] = $nouvelle_ligne;
            } else {
                // Mode ajout : ajouter à la fin
                $lignes[] = $nouvelle_ligne;
            }

            $nouvelle_valeur = implode("\n", array_filter(array_map('trim', $lignes)));
            $pdo->prepare("UPDATE dossiers SET $champ = :v WHERE id_patient = :id")
                ->execute([':v' => $nouvelle_valeur, ':id' => $patient_id]);

            // Journaliser
            try {
                $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, id_dossier, ip_address)
                               VALUES (:id,'patient','ajout_antecedent_$champ',:did,:ip)")
                    ->execute([':id'=>$patient_id,':did'=>$dossier_id,':ip'=>$_SERVER['REMOTE_ADDR']??'']);
            } catch(Exception $e) {}

            $msg_succes = $idx >= 0
                ? 'Antécédent modifié avec succès.'
                : 'Antécédent ajouté avec succès.';

            // Recharger le dossier
            $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id");
            $stmt->execute([':id' => $patient_id]);
            $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
            $mode_edit = false;

            // Reset pré-remplissage
            $pre = ['type'=>'allergies','intitule'=>'','description'=>'','date_decouverte'=>'',
                    'statut'=>'En cours / Actif','severite'=>'modere','traitement'=>'','medecin'=>''];
        }
    }

    // --- SUPPRIMER UNE LIGNE ---
    if ($_POST['action'] === 'supprimer') {
        $champ = $champs_map[$_POST['type'] ?? ''] ?? '';
        $idx   = (int)($_POST['idx'] ?? -1);
        if ($champ && $idx >= 0) {
            $lignes = explode("\n", trim($dossier[$champ] ?? ''));
            array_splice($lignes, $idx, 1);
            $nouvelle_valeur = implode("\n", array_filter(array_map('trim',$lignes)));
            $pdo->prepare("UPDATE dossiers SET $champ = :v WHERE id_patient = :id")
                ->execute([':v' => $nouvelle_valeur ?: null, ':id' => $patient_id]);
            $msg_succes = 'Antécédent supprimé.';
            $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id");
            $stmt->execute([':id' => $patient_id]);
            $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// ================================================================
// PRÉPARER LA LISTE DES ANTÉCÉDENTS EXISTANTS (pour la sidebar)
// ================================================================
$tous_antecedents = [];
$mapping_labels = [
    'allergies'           => ['label'=>'Allergie','icon'=>'🤧','color'=>'var(--red)','bg'=>'#FFF5F5','class'=>'allergie'],
    'maladies_chroniques' => ['label'=>'Chronique','icon'=>'🫀','color'=>'var(--orange)','bg'=>'#FFF7ED','class'=>'chronique'],
    'chirurgies'          => ['label'=>'Chirurgie','icon'=>'🔪','color'=>'var(--blue)','bg'=>'#EFF6FF','class'=>'chirurgie'],
    'traitements_en_cours'=> ['label'=>'Traitement','icon'=>'💊','color'=>'var(--green)','bg'=>'#F0FDF4','class'=>'traitement'],
    'antecedents'         => ['label'=>'Autre','icon'=>'📋','color'=>'var(--purple)','bg'=>'#FDF4FF','class'=>''],
];
foreach ($mapping_labels as $champ => $meta) {
    $val = trim($dossier[$champ] ?? '');
    if (!$val) continue;
    foreach (explode("\n", $val) as $idx => $ligne) {
        $ligne = trim($ligne);
        if (!$ligne) continue;
        $tous_antecedents[] = [
            'champ' => $champ,
            'idx'   => $idx,
            'ligne' => $ligne,
            'meta'  => $meta,
        ];
    }
}
$nb_total = count($tous_antecedents);

// Notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user='patient' AND lu=0");
$stmt->execute([':id' => $patient_id]);
$notif_count = (int)$stmt->fetchColumn();

$initiales = strtoupper(mb_substr($_SESSION['prenom'],0,1).mb_substr($_SESSION['nom']??'',0,1));

// Types sélecteur
$types_form = [
    'allergies'           => ['icon'=>'🤧','label'=>'Allergie'],
    'maladies_chroniques' => ['icon'=>'🫀','label'=>'Maladie chronique'],
    'chirurgies'          => ['icon'=>'🔪','label'=>'Chirurgie'],
    'traitements_en_cours'=> ['icon'=>'💊','label'=>'Traitement'],
    'antecedents'         => ['icon'=>'🧬','label'=>'Héréditaire / Autre'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Ajouter un Antécédent</title>
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
.avatar-sm{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;font-weight:700}
.nav-user{display:flex;align-items:center;gap:.5rem;color:#CBD5E1;font-size:.85rem}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- PAGE HEADER ---- */
.page-hdr{background:linear-gradient(135deg,var(--purple),#B5179E);padding:1.8rem 5%;color:#fff}
.breadcrumb{font-size:.78rem;color:#E9D5FF;margin-bottom:.5rem}
.breadcrumb a{color:#E9D5FF;text-decoration:none;transition:.2s}
.breadcrumb a:hover{color:#fff}
.breadcrumb span{color:var(--green)}
.page-hdr h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
.page-hdr p{color:#E9D5FF;font-size:.86rem;margin-top:.3rem}

/* ---- MAIN LAYOUT ---- */
.main{display:flex;gap:1.5rem;padding:1.8rem 5%;max-width:1000px;margin:0 auto;align-items:flex-start}

/* ---- FORMULAIRE ---- */
.form-card{flex:1;min-width:0;background:#fff;border-radius:14px;padding:1.8rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05)}
.form-card h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:1.2rem;padding-bottom:.6rem;border-bottom:1px solid var(--lgray)}

/* Alertes */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

/* Sélecteur de type */
.type-label-title{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:.6rem}
.type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1.4rem}
.type-btn{border:2px solid var(--lgray);border-radius:12px;padding:.9rem .5rem;text-align:center;cursor:pointer;transition:.2s;background:#fff;display:block;width:100%}
.type-btn:hover{border-color:var(--purple);background:#FDF4FF}
.type-btn.selected{border-color:var(--purple);background:#FDF4FF}
.type-icon{font-size:1.5rem;margin-bottom:.4rem;display:block}
.type-label{font-size:.75rem;font-weight:700;color:#334155;display:block}
.type-btn.selected .type-label{color:var(--purple)}

/* Champs formulaire */
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:.4rem}
.req{color:var(--red)}
.form-group input,.form-group select,.form-group textarea{
  width:100%;padding:.72rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
  font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;background:#fff;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
  border-color:var(--purple);box-shadow:0 0 0 3px rgba(114,9,183,.08);
}
.form-group input.field-error,.form-group select.field-error{border-color:var(--red);background:#FFF5F5}
.field-msg{font-size:.75rem;color:var(--red);margin-top:.25rem}
.form-group textarea{resize:vertical;min-height:100px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}

/* Sévérité */
.severity{display:flex;gap:.5rem;margin-top:.4rem}
.sev-btn{flex:1;padding:.55rem;border-radius:8px;border:1.5px solid var(--lgray);font-size:.78rem;font-weight:600;cursor:pointer;background:#fff;text-align:center;transition:.2s;font-family:'DM Sans',sans-serif}
.sev-btn.active-faible{border-color:var(--green);background:#F0FDF4;color:var(--green)}
.sev-btn.active-modere{border-color:var(--orange);background:#FFF7ED;color:var(--orange)}
.sev-btn.active-severe{border-color:var(--red);background:#FFF5F5;color:var(--red)}

/* Actions formulaire */
.form-actions{display:flex;gap:.8rem;margin-top:1.5rem;flex-wrap:wrap}
.btn-save{flex:1;min-width:140px;padding:.82rem;background:var(--purple);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(114,9,183,.25);transition:.2s;font-family:'DM Sans',sans-serif}
.btn-save:hover{background:#5B0099;transform:translateY(-1px)}
.btn-cancel{padding:.82rem 1.5rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:10px;font-size:.9rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;font-family:'DM Sans',sans-serif;transition:.2s}
.btn-cancel:hover{border-color:var(--gray);color:#334155}

/* Input hidden type */
input[type=hidden]#selected_type{}

/* ---- SIDEBAR LISTE ---- */
.sidebar{width:300px;flex-shrink:0}
.list-card{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);position:sticky;top:80px}
.list-card h4{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray)}

.ant-item{border-left:3px solid var(--purple);padding:.7rem .9rem;background:var(--light);border-radius:0 10px 10px 0;margin-bottom:.6rem}
.ant-item.allergie{border-color:var(--red);background:#FFF5F5}
.ant-item.chirurgie{border-color:var(--blue);background:#EFF6FF}
.ant-item.chronique{border-color:var(--orange);background:#FFF7ED}
.ant-item.traitement{border-color:var(--green);background:#F0FDF4}
.ant-type{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.2rem;color:var(--purple)}
.ant-item.allergie .ant-type{color:var(--red)}
.ant-item.chirurgie .ant-type{color:var(--blue)}
.ant-item.chronique .ant-type{color:var(--orange)}
.ant-item.traitement .ant-type{color:var(--green)}
.ant-name{font-size:.84rem;font-weight:700;color:#1E293B;word-break:break-word}
.ant-acts{display:flex;gap:.4rem;margin-top:.4rem;flex-wrap:wrap}
.a-edit{padding:.25rem .6rem;background:transparent;color:var(--blue);border:1px solid var(--blue);border-radius:5px;font-size:.7rem;cursor:pointer;transition:.2s;text-decoration:none;display:inline-block}
.a-edit:hover{background:#EFF6FF;color:var(--blue)}
.a-del{padding:.25rem .6rem;background:transparent;color:var(--red);border:1px solid var(--red);border-radius:5px;font-size:.7rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.a-del:hover{background:#FFF5F5}

.dossier-ok{margin-top:1rem;padding:.7rem;background:#F0FDF4;border-radius:10px;font-size:.78rem;color:#15803D;border:1px solid #BBF7D0;text-align:center}
.dossier-vide{margin-top:1rem;padding:.7rem;background:var(--light);border-radius:10px;font-size:.78rem;color:var(--gray);text-align:center}

/* Modal suppression */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:14px;padding:1.8rem;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:.5rem}
.modal p{font-size:.85rem;color:var(--gray);margin-bottom:1.2rem}
.modal-btns{display:flex;gap:.6rem;justify-content:flex-end}
.btn-cancel-m{padding:.55rem 1rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:8px;font-size:.83rem;cursor:pointer;font-family:'DM Sans',sans-serif}
.btn-del-m{padding:.55rem 1.2rem;background:var(--red);color:#fff;border:none;border-radius:8px;font-size:.83rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:800px){
  .main{flex-direction:column;padding:1rem 4%}
  .sidebar{width:100%;position:static}
  .list-card{position:static}
  .type-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
  .nav-links{display:none;position:fixed;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;z-index:99}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
  .nav-hamburger{display:flex}
  .form-row{grid-template-columns:1fr}
}
@media(max-width:480px){
  .type-grid{grid-template-columns:repeat(2,1fr)}
  .form-actions{flex-direction:column}
  .btn-save{min-width:auto}
}
</style>
</head>
<body>

<!-- MODAL SUPPRESSION -->
<div class="modal-overlay" id="modalDel">
  <div class="modal">
    <h3>🗑️ Supprimer cet antécédent</h3>
    <p>Cette action est irréversible. Voulez-vous vraiment supprimer cet élément du dossier ?</p>
    <form method="POST" id="delForm">
      <input type="hidden" name="action" value="supprimer">
      <input type="hidden" name="type"   id="del_type" value="">
      <input type="hidden" name="idx"    id="del_idx"  value="">
      <div class="modal-btns">
        <button type="button" class="btn-cancel-m" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn-del-m">🗑️ Supprimer</button>
      </div>
    </form>
  </div>
</div>

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
    <li><a href="dossier_patient.php" class="active">Mon Dossier</a></li>
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
    <a href="dossier_patient.php">Dossier Médical</a> → <span>Ajouter Antécédent</span>
  </div>
  <h1><?= $mode_edit ? '✏️ Modifier l\'antécédent' : '+ Ajouter un Antécédent Médical' ?></h1>
  <p>Complétez votre dossier médical pour un meilleur suivi</p>
</div>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">

  <!-- ========== FORMULAIRE ========== -->
  <div class="form-card">
    <h3>📝 <?= $mode_edit ? 'Modifier l\'antécédent' : 'Nouvel antécédent' ?></h3>

    <?php if($msg_succes):?>
      <div class="alert alert-success">✅ <?=htmlspecialchars($msg_succes)?></div>
    <?php endif; ?>
    <?php if(!empty($erreurs)):?>
      <div class="alert alert-error">⚠️ Veuillez corriger les erreurs ci-dessous.</div>
    <?php endif; ?>

    <form method="POST" action="ajouter_antecedent.php" id="antForm">
      <input type="hidden" name="action" value="sauvegarder">
      <input type="hidden" name="edit_index" value="<?=(int)($edit_index??-1)?>">
      <input type="hidden" name="type" id="selected_type" value="<?=htmlspecialchars($pre['type'])?>">

      <!-- SÉLECTEUR TYPE -->
      <label class="type-label-title">Type d'antécédent <span class="req">*</span></label>
      <div class="type-grid">
        <?php foreach($types_form as $key => $t): ?>
          <button type="button"
                  class="type-btn <?= $pre['type']===$key ? 'selected' : '' ?>"
                  data-type="<?=$key?>"
                  onclick="selectType('<?=$key?>', this)">
            <span class="type-icon"><?=$t['icon']?></span>
            <span class="type-label"><?=$t['label']?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <?php if(isset($erreurs['type'])): ?><span class="field-msg" style="display:block;margin-top:-.8rem;margin-bottom:.8rem">⚠ <?=$erreurs['type']?></span><?php endif; ?>

      <!-- INTITULÉ -->
      <div class="form-group">
        <label>Nom / Intitulé <span class="req">*</span></label>
        <input type="text" name="intitule"
               placeholder="Ex : Diabète de type 2, Asthme, Appendicectomie..."
               value="<?=htmlspecialchars($pre['intitule']??$_POST['intitule']??'')?>"
               class="<?=isset($erreurs['intitule'])?'field-error':''?>">
        <?php if(isset($erreurs['intitule'])): ?><span class="field-msg">⚠ <?=$erreurs['intitule']?></span><?php endif; ?>
      </div>

      <!-- DESCRIPTION -->
      <div class="form-group">
        <label>Description détaillée</label>
        <textarea name="description"
                  placeholder="Décrivez les symptômes, le contexte, les traitements associés..."><?=htmlspecialchars($_POST['description']??'')?></textarea>
      </div>

      <!-- DATE + STATUT -->
      <div class="form-row">
        <div class="form-group">
          <label>Date de découverte</label>
          <input type="date" name="date_decouverte"
                 value="<?=htmlspecialchars($_POST['date_decouverte']??'')?>"
                 max="<?=date('Y-m-d')?>">
        </div>
        <div class="form-group">
          <label>Statut actuel</label>
          <select name="statut">
            <?php foreach(['En cours / Actif','Guéri / Résolu','Sous traitement','En surveillance'] as $s): ?>
              <option value="<?=$s?>" <?=($_POST['statut']??$pre['statut'])===$s?'selected':''?>><?=$s?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- SÉVÉRITÉ -->
      <div class="form-group">
        <label>Sévérité</label>
        <div class="severity">
          <?php $sev_cur = $_POST['severite'] ?? $pre['severite'] ?? 'modere'; ?>
          <button type="button" class="sev-btn <?=$sev_cur==='faible'?'active-faible':''?>"
                  onclick="setSev('faible',this)" id="sev_faible">⬜ Faible</button>
          <button type="button" class="sev-btn <?=$sev_cur==='modere'?'active-modere':''?>"
                  onclick="setSev('modere',this)" id="sev_modere">🟡 Modérée</button>
          <button type="button" class="sev-btn <?=$sev_cur==='severe'?'active-severe':''?>"
                  onclick="setSev('severe',this)" id="sev_severe">🔴 Sévère</button>
        </div>
        <input type="hidden" name="severite" id="sev_input" value="<?=$sev_cur?>">
      </div>

      <!-- TRAITEMENT + MÉDECIN -->
      <div class="form-group">
        <label>Traitement en cours (si applicable)</label>
        <input type="text" name="traitement"
               placeholder="Ex : Amlodipine 5mg/jour"
               value="<?=htmlspecialchars($_POST['traitement']??'')?>">
      </div>

      <div class="form-group">
        <label>Médecin traitant (si connu)</label>
        <input type="text" name="medecin"
               placeholder="Nom du médecin qui a posé le diagnostic"
               value="<?=htmlspecialchars($_POST['medecin']??'')?>">
      </div>

      <!-- ACTIONS -->
      <div class="form-actions">
        <a href="dossier_patient.php?onglet=antecedents" class="btn-cancel">Annuler</a>
        <button type="submit" class="btn-save">
          ✅ <?= $mode_edit ? 'Modifier l\'antécédent' : 'Sauvegarder l\'antécédent' ?>
        </button>
      </div>
    </form>
  </div>

  <!-- ========== SIDEBAR LISTE ========== -->
  <div class="sidebar">
    <div class="list-card">
      <h4>📋 Antécédents existants (<?=$nb_total?>)</h4>

      <?php if(empty($tous_antecedents)): ?>
        <div class="dossier-vide">📂 Aucun antécédent enregistré pour le moment.</div>
      <?php else: ?>
        <?php foreach($tous_antecedents as $ant): ?>
          <div class="ant-item <?=$ant['meta']['class']?>">
            <div class="ant-type"><?=$ant['meta']['icon']?> <?=$ant['meta']['label']?></div>
            <div class="ant-name"><?=htmlspecialchars(mb_substr($ant['ligne'],0,80)).(mb_strlen($ant['ligne'])>80?'...':'')?></div>
            <div class="ant-acts">
              <a href="ajouter_antecedent.php?champ=<?=$ant['champ']?>&idx=<?=$ant['idx']?>"
                 class="a-edit">✏️ Modifier</a>
              <button type="button" class="a-del"
                      onclick="openDel('<?=$ant['champ']?>',<?=$ant['idx']?>)">
                🗑 Suppr.
              </button>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="dossier-ok">
          ✅ Dossier à jour — <?=$nb_total?> antécédent<?=$nb_total>1?'s':''?> enregistré<?=$nb_total>1?'s':''?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /main -->

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Fatou Yankaty (E4)</strong> — ajouter_antecedent.php</p>
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

// --- Sélection type ---
function selectType(type, btn) {
  document.getElementById('selected_type').value = type;
  document.querySelectorAll('.type-btn').forEach(function(b){ b.classList.remove('selected'); });
  btn.classList.add('selected');
}

// --- Sévérité ---
function setSev(sev, btn) {
  document.getElementById('sev_input').value = sev;
  ['faible','modere','severe'].forEach(function(s){
    var el = document.getElementById('sev_'+s);
    el.classList.remove('active-faible','active-modere','active-severe');
  });
  btn.classList.add('active-'+sev);
}

// --- Modal suppression ---
function openDel(type, idx) {
  document.getElementById('del_type').value = type;
  document.getElementById('del_idx').value  = idx;
  document.getElementById('modalDel').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('modalDel').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('modalDel').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>