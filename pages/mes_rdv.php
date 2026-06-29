<?php
// ================================================================
// mes_rdv.php — Mes Rendez-Vous (espace patient)
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

// ================================================================
// TRAITEMENT — ANNULATION D'UN RDV
// ================================================================
$msg_succes = '';
$msg_erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Annuler un RDV ---
    if ($_POST['action'] === 'annuler') {
        $rdv_id = (int)($_POST['rdv_id'] ?? 0);
        $motif_annul = trim($_POST['motif_annulation'] ?? '');

        // Vérifier que ce RDV appartient bien au patient et peut être annulé
        $chk = $pdo->prepare("SELECT id, statut, date_rdv FROM rendezvous WHERE id=:id AND id_patient=:p LIMIT 1");
        $chk->execute([':id'=>$rdv_id, ':p'=>$patient_id]);
        $rdv = $chk->fetch();

        if (!$rdv) {
            $msg_erreur = 'Rendez-vous introuvable.';
        } elseif (in_array($rdv['statut'], ['annule','termine','refuse'])) {
            $msg_erreur = 'Ce rendez-vous ne peut plus être annulé.';
        } elseif (new DateTime($rdv['date_rdv']) < new DateTime('today')) {
            $msg_erreur = 'Impossible d\'annuler un rendez-vous passé.';
        } else {
            $pdo->prepare("UPDATE rendezvous SET statut='annule', motif_annulation=:m WHERE id=:id")
                ->execute([':m' => $motif_annul ?: 'Annulé par le patient', ':id' => $rdv_id]);

            // Notification au médecin
            $info = $pdo->prepare("SELECT id_medecin, date_rdv, heure_rdv FROM rendezvous WHERE id=:id");
            $info->execute([':id'=>$rdv_id]);
            $rdv_info = $info->fetch();
            $pdo->prepare("INSERT INTO notifications (id_destinataire,type_user,type_notif,message,id_rdv)
                           VALUES (:id,'medecin','rdv_annule',:msg,:rdv)")
                ->execute([':id'=>$rdv_info['id_medecin'],':rdv'=>$rdv_id,
                           ':msg'=>"Le patient {$_SESSION['prenom']} {$_SESSION['nom']} a annulé son RDV du {$rdv_info['date_rdv']} à {$rdv_info['heure_rdv']}."]);

            $msg_succes = 'Votre rendez-vous a été annulé avec succès.';
        }
    }
}

// ================================================================
// STATISTIQUES
// ================================================================
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN statut IN ('confirme','en_attente') AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS a_venir,
        SUM(CASE WHEN statut = 'en_attente' AND date_rdv >= CURDATE() THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termines,
        SUM(CASE WHEN statut IN ('annule','refuse') THEN 1 ELSE 0 END) AS annules
    FROM rendezvous WHERE id_patient = :id
");
$stmt->execute([':id'=>$patient_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// PARAMÈTRES DE FILTRAGE / TRI / PAGINATION
// ================================================================
$onglet    = $_GET['onglet']     ?? 'avenir';    // avenir | termines | annules
$filtre_nom = trim($_GET['nom']  ?? '');
$filtre_spec = trim($_GET['spec'] ?? '');
$tri       = $_GET['tri']        ?? 'desc';       // desc | asc
$par_page  = 5;
$page      = max(1,(int)($_GET['page'] ?? 1));
$offset    = ($page-1)*$par_page;

// ================================================================
// CONSTRUCTION DE LA REQUÊTE
// ================================================================
$where = ["r.id_patient = :pid"];
$params = [':pid' => $patient_id];

// Onglet
if ($onglet === 'avenir') {
    $where[] = "r.statut IN ('confirme','en_attente')";
    $where[] = "r.date_rdv >= CURDATE()";
} elseif ($onglet === 'termines') {
    $where[] = "r.statut = 'termine'";
} else {
    $where[] = "r.statut IN ('annule','refuse')";
}

// Filtres
if ($filtre_nom !== '') {
    $where[] = "(m.nom LIKE :nom OR m.prenom LIKE :nom)";
    $params[':nom'] = '%'.$filtre_nom.'%';
}
if ($filtre_spec !== '') {
    $where[] = "m.specialite = :spec";
    $params[':spec'] = $filtre_spec;
}

$where_sql = implode(' AND ', $where);
$order_sql = "r.date_rdv " . ($tri === 'asc' ? 'ASC' : 'DESC') . ", r.heure_rdv " . ($tri === 'asc' ? 'ASC' : 'DESC');

// COUNT
$sql_count = "SELECT COUNT(*) FROM rendezvous r JOIN medecins m ON m.id=r.id_medecin WHERE $where_sql";
$stmt = $pdo->prepare($sql_count);
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->execute();
$total = (int)$stmt->fetchColumn();
$total_pages = (int)ceil($total/$par_page);

// Requête principale
$sql = "
    SELECT r.*, m.nom AS m_nom, m.prenom AS m_prenom, m.specialite, m.cabinet, m.ville, m.photo AS m_photo,
           c.diagnostic, c.compte_rendu, c.ordonnance
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    LEFT JOIN consultations c ON c.id_rendezvous = r.id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$par_page,PDO::PARAM_INT);
$stmt->bindValue(':off',$offset,PDO::PARAM_INT);
$stmt->execute();
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Spécialités pour le filtre
$specs = $pdo->query("SELECT DISTINCT m.specialite FROM rendezvous r JOIN medecins m ON m.id=r.id_medecin WHERE r.id_patient=$patient_id ORDER BY m.specialite")->fetchAll(PDO::FETCH_COLUMN);

// Notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user='patient' AND lu=0");
$stmt->execute([':id'=>$patient_id]);
$notif_count = (int)$stmt->fetchColumn();

// Jours et mois FR
$jours_fr = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
$mois_fr  = ['Jan'=>'Janv','Feb'=>'Févr','Mar'=>'Mars','Apr'=>'Avr','May'=>'Mai','Jun'=>'Juin',
             'Jul'=>'Juil','Aug'=>'Août','Sep'=>'Sept','Oct'=>'Oct','Nov'=>'Nov','Dec'=>'Déc'];

function formatDate($date_str, $jours, $mois) {
    $dt  = new DateTime($date_str);
    $j   = $jours[$dt->format('D')] ?? $dt->format('D');
    $m   = $mois[$dt->format('M')]  ?? $dt->format('M');
    return $j . '. ' . $dt->format('j') . ' ' . $m . '. ' . $dt->format('Y');
}
function formatHeure($h) {
    return substr($h,0,5);
}

// Couleurs spécialités
$couleurs = [
    'Cardiologie'   => ['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','spec_bg'=>'#EFF6FF','spec_c'=>'#0077B6','border'=>'#0077B6'],
    'Pediatrie'     => ['grad'=>'linear-gradient(135deg,#7209B7,#B5179E)','spec_bg'=>'#FDF4FF','spec_c'=>'#7209B7','border'=>'#7209B7'],
    'Dermatologie'  => ['grad'=>'linear-gradient(135deg,#F77F00,#D62828)','spec_bg'=>'#FFF7ED','spec_c'=>'#F77F00','border'=>'#F77F00'],
    'Gynecologie'   => ['grad'=>'linear-gradient(135deg,#006D77,#2DC653)','spec_bg'=>'#F0FDF4','spec_c'=>'#2DC653','border'=>'#006D77'],
    'Neurologie'    => ['grad'=>'linear-gradient(135deg,#3A0CA3,#7B2FBE)','spec_bg'=>'#EEF0FF','spec_c'=>'#3A0CA3','border'=>'#3A0CA3'],
    'default'       => ['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','spec_bg'=>'#EFF6FF','spec_c'=>'#0077B6','border'=>'#0077B6'],
];
function getCoul($s,$c){return $c[$s]??$c['default'];}

$initiales = strtoupper(mb_substr($_SESSION['prenom'],0,1).mb_substr($_SESSION['nom']??'',0,1));

function pageUrl($p){$q=$_GET;$q['page']=$p;return '?'.http_build_query($q);}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Mes Rendez-Vous</title>
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
.nav-links a.active{border-bottom:2px solid var(--green);padding-bottom:2px}
.nav-right{display:flex;align-items:center;gap:1rem}
.notif-btn{color:#CBD5E1;font-size:.88rem;text-decoration:none;position:relative}
.notif-dot{position:absolute;top:-6px;right:-6px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;font-size:.58rem;display:flex;align-items:center;justify-content:center;font-weight:700}
.avatar-sm{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;font-weight:700}
.nav-user{display:flex;align-items:center;gap:.5rem;color:#CBD5E1;font-size:.85rem}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- PAGE HEADER ---- */
.page-hdr{background:linear-gradient(135deg,var(--dark),var(--blue2));padding:1.8rem 5%}
.breadcrumb{font-size:.78rem;color:#94A3B8;margin-bottom:.5rem}
.breadcrumb a{color:#94A3B8;text-decoration:none}
.breadcrumb a:hover{color:#fff}
.breadcrumb span{color:var(--green)}
.page-hdr h1{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:#fff}
.page-hdr p{color:#CBD5E1;font-size:.88rem;margin-top:.3rem}

/* ---- MINI STATS ---- */
.mini-stats{display:flex;gap:1rem;padding:1rem 5%;background:#fff;border-bottom:1px solid var(--lgray);flex-wrap:wrap}
.m-stat{display:flex;align-items:center;gap:.6rem;background:var(--light);border-radius:10px;padding:.6rem 1.2rem}
.m-num{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800}
.m-lbl{font-size:.74rem;color:var(--gray);font-weight:500}

/* ---- MAIN ---- */
.main{padding:1.5rem 5%;max-width:900px;margin:0 auto}

/* Alertes */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

/* ---- ONGLETS ---- */
.tabs{display:flex;gap:.3rem;background:#fff;border-radius:12px;padding:.3rem;margin-bottom:1.5rem;border:1px solid var(--lgray);box-shadow:0 2px 8px rgba(0,0,0,.04)}
.tab{flex:1;padding:.65rem .5rem;text-align:center;border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;text-decoration:none;color:var(--gray);transition:.2s;display:block;white-space:nowrap}
.tab.active{background:var(--blue);color:#fff;box-shadow:0 2px 8px rgba(0,119,182,.3)}
.tab:hover:not(.active){color:var(--blue)}

/* ---- FILTRES ---- */
.filter-bar{display:flex;gap:.7rem;margin-bottom:1.2rem;flex-wrap:wrap;align-items:center}
.filter-bar input{flex:1;min-width:160px;padding:.55rem .9rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.85rem;outline:none;font-family:'DM Sans',sans-serif;transition:.2s}
.filter-bar input:focus{border-color:var(--blue)}
.filter-bar select{padding:.55rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.85rem;outline:none;background:#fff;font-family:'DM Sans',sans-serif;transition:.2s}
.filter-bar select:focus{border-color:var(--blue)}
.filter-bar button{padding:.55rem 1.1rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer;white-space:nowrap;font-family:'DM Sans',sans-serif}

/* ---- CARD RDV ---- */
.rdv-card{background:#fff;border-radius:14px;padding:1.3rem;border:1px solid var(--lgray);margin-bottom:.9rem;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:.2s}
.rdv-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.09);transform:translateY(-1px)}
.rdv-card.border-attente{border-left:4px solid var(--orange)}
.rdv-card.border-confirme{border-left:4px solid var(--green)}
.rdv-card.border-annule{border-left:4px solid var(--red);opacity:.85}
.rdv-card.border-refuse{border-left:4px solid var(--red);opacity:.85}
.rdv-card.border-termine{border-left:4px solid var(--gray);opacity:.9}

.rdv-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.9rem;flex-wrap:wrap;gap:.5rem}
.rdv-doc{display:flex;gap:.8rem;align-items:center}
.rdv-av{width:50px;height:50px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;overflow:hidden}
.rdv-av img{width:100%;height:100%;object-fit:cover;border-radius:10px}
.rdv-doc-name{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:#1E293B}
.rdv-spec{font-size:.75rem;font-weight:600;padding:.15rem .5rem;border-radius:5px;display:inline-block;margin-top:.2rem}

/* Badges statuts */
.badge{padding:.3rem .8rem;border-radius:20px;font-size:.75rem;font-weight:700;white-space:nowrap}
.badge-confirme{background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0}
.badge-attente{background:#FFF7ED;color:#C2410C;border:1px solid #FED7AA}
.badge-annule,.badge-refuse{background:#FFF5F5;color:var(--red);border:1px solid #FECACA}
.badge-termine{background:#F1F5F9;color:var(--gray);border:1px solid var(--lgray)}

.rdv-details{display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:.8rem}
.rdv-detail{display:flex;align-items:center;gap:.4rem;font-size:.83rem;color:var(--gray)}
.rdv-detail strong{color:#334155}
.rdv-motif{background:var(--light);border-radius:8px;padding:.6rem .9rem;font-size:.82rem;color:#334155;margin-bottom:.8rem;border-left:3px solid var(--blue)}
.rdv-msg-medecin{background:#FFF7ED;border-radius:8px;padding:.6rem .9rem;font-size:.82rem;color:#92400E;margin-bottom:.8rem;border-left:3px solid var(--orange)}

.rdv-actions{display:flex;gap:.6rem;flex-wrap:wrap}
.btn-detail{padding:.45rem 1rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:.2s}
.btn-detail:hover{background:var(--blue2);color:#fff}
.btn-annuler{padding:.45rem 1rem;background:transparent;color:var(--red);border:1.5px solid var(--red);border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;transition:.2s}
.btn-annuler:hover{background:#FFF5F5}
.btn-reprendre{padding:.45rem 1rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:.2s}
.btn-reprendre:hover{background:#25B347;color:#fff}

/* Nouveau RDV message */
.rdv-success-banner{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:1rem 1.2rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.8rem;color:#15803D;font-size:.88rem;font-weight:600}

/* État vide */
.empty{text-align:center;padding:3rem;background:#fff;border-radius:14px;border:1px solid var(--lgray)}
.empty-icon{font-size:3.5rem;margin-bottom:1rem}
.empty h3{font-family:'Sora',sans-serif;color:var(--dark);margin-bottom:.5rem}
.empty p{color:var(--gray);font-size:.88rem;margin-bottom:1.2rem}

/* Résultats + tri */
.results-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
.results-bar p{font-size:.85rem;color:var(--gray)}
.results-bar p strong{color:var(--dark)}
.sort-select{padding:.4rem .7rem;border:1px solid var(--lgray);border-radius:7px;font-size:.83rem;outline:none;background:#fff;font-family:'DM Sans',sans-serif}

/* Pagination */
.pagination{display:flex;justify-content:center;gap:.4rem;margin-top:1.5rem;flex-wrap:wrap}
.pg{min-width:34px;height:34px;padding:0 .5rem;border-radius:7px;border:1px solid var(--lgray);background:#fff;font-size:.83rem;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:#334155;text-decoration:none;transition:.2s}
.pg.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.pg:hover:not(.active){border-color:var(--blue);color:var(--blue)}
.pg.disabled{opacity:.4;pointer-events:none}

/* MODAL ANNULATION */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:2rem;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:700;color:var(--dark);margin-bottom:.5rem}
.modal p{font-size:.85rem;color:var(--gray);margin-bottom:1.2rem}
.modal textarea{width:100%;padding:.7rem .9rem;border:1.5px solid var(--lgray);border-radius:9px;font-size:.85rem;font-family:'DM Sans',sans-serif;resize:vertical;min-height:70px;outline:none;margin-bottom:1.2rem;transition:.2s}
.modal textarea:focus{border-color:var(--red)}
.modal-btns{display:flex;gap:.7rem;justify-content:flex-end}
.btn-cancel-modal{padding:.6rem 1.2rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:8px;font-size:.85rem;cursor:pointer;font-family:'DM Sans',sans-serif}
.btn-confirm-annul{padding:.6rem 1.4rem;background:var(--red);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:768px){
  .nav-links{display:none;position:fixed;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;z-index:99;box-shadow:0 8px 20px rgba(0,0,0,.3)}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
  .nav-hamburger{display:flex}
  .main{padding:1rem 4%}
  .mini-stats{gap:.6rem;padding:.8rem 4%}
  .m-num{font-size:1.2rem}
  .tabs{overflow-x:auto}
  .tab{font-size:.75rem;padding:.55rem .4rem}
  .filter-bar{gap:.5rem}
  .rdv-details{gap:.7rem}
  .rdv-detail{font-size:.78rem}
  .results-bar{flex-direction:column;align-items:flex-start}
  .sort-select{width:100%}
}
@media(max-width:480px){
  .page-hdr h1{font-size:1.3rem}
  .rdv-actions{gap:.4rem}
  .btn-detail,.btn-annuler,.btn-reprendre{font-size:.75rem;padding:.38rem .8rem}
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
    <a href="../index.php">Accueil</a> → <span>Mes Rendez-Vous</span>
  </div>
  <h1>Mes Rendez-Vous</h1>
  <p>Suivez et gérez tous vos rendez-vous médicaux</p>
</div>

<!-- ============================================================
     MINI STATS
============================================================ -->
<div class="mini-stats">
  <div class="m-stat">
    <span class="m-num" style="color:var(--blue)"><?=(int)$stats['a_venir']?></span>
    <div><div class="m-lbl">À venir</div></div>
  </div>
  <div class="m-stat">
    <span class="m-num" style="color:var(--orange)"><?=(int)$stats['en_attente']?></span>
    <div><div class="m-lbl">En attente</div></div>
  </div>
  <div class="m-stat">
    <span class="m-num" style="color:var(--green)"><?=(int)$stats['termines']?></span>
    <div><div class="m-lbl">Terminés</div></div>
  </div>
  <div class="m-stat">
    <span class="m-num" style="color:var(--red)"><?=(int)$stats['annules']?></span>
    <div><div class="m-lbl">Annulés</div></div>
  </div>
</div>

<!-- ============================================================
     MODAL ANNULATION
============================================================ -->
<div class="modal-overlay" id="modalAnnul">
  <div class="modal">
    <h3>❌ Annuler le rendez-vous</h3>
    <p>Cette action est irréversible. Voulez-vous vraiment annuler ce rendez-vous ?</p>
    <form method="POST" id="annulForm">
      <input type="hidden" name="action"  value="annuler">
      <input type="hidden" name="rdv_id"  id="annulRdvId" value="">
      <textarea name="motif_annulation" placeholder="Motif d'annulation (optionnel)..."></textarea>
      <div class="modal-btns">
        <button type="button" class="btn-cancel-modal" onclick="closeModal()">Retour</button>
        <button type="submit" class="btn-confirm-annul">Confirmer l'annulation</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">

  <!-- Alertes -->
  <?php if ($msg_succes !== ''): ?>
    <div class="alert alert-success">✅ <?=htmlspecialchars($msg_succes)?></div>
  <?php endif; ?>
  <?php if ($msg_erreur !== ''): ?>
    <div class="alert alert-error">⚠️ <?=htmlspecialchars($msg_erreur)?></div>
  <?php endif; ?>

  <!-- Bannière nouveau RDV confirmé -->
  <?php if (isset($_GET['rdv_confirme'])): ?>
    <div class="rdv-success-banner">
      🎉 Votre demande de rendez-vous a été envoyée ! Vous recevrez une notification dès confirmation par le médecin.
    </div>
  <?php endif; ?>

  <!-- ONGLETS -->
  <div class="tabs">
    <?php
      $onglet_url = function($o) use ($onglet) {
        $q = $_GET; $q['onglet']=$o; unset($q['page']); return '?'.http_build_query($q);
      };
    ?>
    <a href="<?=$onglet_url('avenir')?>" class="tab <?=$onglet==='avenir'?'active':''?>">
      📅 À venir (<?=(int)$stats['a_venir']?>)
    </a>
    <a href="<?=$onglet_url('termines')?>" class="tab <?=$onglet==='termines'?'active':''?>">
      ✅ Terminés (<?=(int)$stats['termines']?>)
    </a>
    <a href="<?=$onglet_url('annules')?>" class="tab <?=$onglet==='annules'?'active':''?>">
      ❌ Annulés (<?=(int)$stats['annules']?>)
    </a>
  </div>

  <!-- FILTRES -->
  <form method="GET" action="mes_rdv.php">
    <input type="hidden" name="onglet" value="<?=htmlspecialchars($onglet)?>">
    <div class="filter-bar">
      <input type="text" name="nom" placeholder="Rechercher un médecin..."
             value="<?=htmlspecialchars($filtre_nom)?>">
      <select name="spec">
        <option value="">Toutes les spécialités</option>
        <?php foreach($specs as $sp): ?>
          <option value="<?=htmlspecialchars($sp)?>" <?=$filtre_spec===$sp?'selected':''?>>
            <?=htmlspecialchars($sp)?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="tri">
        <option value="desc" <?=$tri==='desc'?'selected':''?>>Date ↓ (plus récent)</option>
        <option value="asc"  <?=$tri==='asc' ?'selected':''?>>Date ↑ (plus ancien)</option>
      </select>
      <button type="submit">🔍 Filtrer</button>
      <?php if($filtre_nom||$filtre_spec): ?>
        <a href="mes_rdv.php?onglet=<?=$onglet?>" style="padding:.55rem .9rem;color:var(--gray);font-size:.82rem;text-decoration:none;border:1px solid var(--lgray);border-radius:8px">✕</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- RÉSULTATS -->
  <?php if (empty($rdvs)): ?>
    <div class="empty">
      <div class="empty-icon">
        <?= $onglet==='avenir' ? '📅' : ($onglet==='termines' ? '✅' : '❌') ?>
      </div>
      <h3>
        <?php if($onglet==='avenir') echo 'Aucun rendez-vous à venir';
              elseif($onglet==='termines') echo 'Aucune consultation terminée';
              else echo 'Aucun rendez-vous annulé'; ?>
      </h3>
      <p>
        <?php if($onglet==='avenir') echo 'Prenez votre premier rendez-vous en quelques clics.'; ?>
      </p>
      <?php if($onglet==='avenir'): ?>
        <a href="liste_medecins.php" style="display:inline-block;padding:.7rem 1.5rem;background:var(--blue);color:#fff;border-radius:10px;text-decoration:none;font-size:.9rem;font-weight:600">
          Trouver un médecin
        </a>
      <?php endif; ?>
    </div>

  <?php else: ?>

    <div class="results-bar">
      <p><strong><?=$total?></strong> rendez-vous trouvé<?=$total>1?'s':''?></p>
    </div>

    <?php foreach($rdvs as $rdv):
      $c = getCoul($rdv['specialite'],$couleurs);
      $statut = $rdv['statut'];
      $border_class = 'border-'.$statut;
      $date_fmt  = formatDate($rdv['date_rdv'], $jours_fr, $mois_fr);
      $heure_fmt = formatHeure($rdv['heure_rdv']);
      $is_futur  = (new DateTime($rdv['date_rdv'])) >= new DateTime('today');
      $can_cancel = in_array($statut,['en_attente','confirme']) && $is_futur;
    ?>
    <div class="rdv-card <?=$border_class?>">
      <div class="rdv-top">
        <div class="rdv-doc">
          <div class="rdv-av" style="background:<?=$c['grad']?>">
            <?php if(!empty($rdv['m_photo']) && file_exists(__DIR__.'/../assets/img/'.$rdv['m_photo'])): ?>
              <img src="../assets/img/<?=htmlspecialchars($rdv['m_photo'])?>" alt="Dr">
            <?php else: ?>
              👨‍⚕️
            <?php endif; ?>
          </div>
          <div>
            <div class="rdv-doc-name">Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?></div>
            <span class="rdv-spec" style="background:<?=$c['spec_bg']?>;color:<?=$c['spec_c']?>">
              <?=htmlspecialchars($rdv['specialite'])?>
            </span>
          </div>
        </div>

        <!-- Badge statut -->
        <?php
          $badge_map = [
            'confirme'   => ['class'=>'badge-confirme',  'label'=>'✅ Confirmé'],
            'en_attente' => ['class'=>'badge-attente',   'label'=>'⏳ En attente'],
            'annule'     => ['class'=>'badge-annule',    'label'=>'❌ Annulé'],
            'refuse'     => ['class'=>'badge-refuse',    'label'=>'🚫 Refusé'],
            'termine'    => ['class'=>'badge-termine',   'label'=>'✔ Terminé'],
          ];
          $badge = $badge_map[$statut] ?? ['class'=>'badge-termine','label'=>$statut];
        ?>
        <span class="badge <?=$badge['class']?>"><?=$badge['label']?></span>
      </div>

      <!-- Détails -->
      <div class="rdv-details">
        <div class="rdv-detail">📅 <strong><?=$date_fmt?></strong></div>
        <div class="rdv-detail">🕐 <strong><?=$heure_fmt?></strong></div>
        <div class="rdv-detail">📍 <?=htmlspecialchars($rdv['cabinet'].', '.$rdv['ville'])?></div>
      </div>

      <!-- Motif -->
      <?php if(!empty($rdv['motif'])): ?>
        <div class="rdv-motif">📝 Motif : <?=htmlspecialchars(mb_substr($rdv['motif'],0,120)).(mb_strlen($rdv['motif'])>120?'...':'')?></div>
      <?php endif; ?>

      <!-- Message du médecin -->
      <?php if(!empty($rdv['message_medecin'])): ?>
        <div class="rdv-msg-medecin">💬 Message du médecin : <?=htmlspecialchars($rdv['message_medecin'])?></div>
      <?php endif; ?>

      <!-- Motif annulation -->
      <?php if($statut==='annule' && !empty($rdv['motif_annulation'])): ?>
        <div class="rdv-msg-medecin" style="background:#FFF5F5;border-color:var(--red);color:#7F1D1D">
          ❌ Motif d'annulation : <?=htmlspecialchars($rdv['motif_annulation'])?>
        </div>
      <?php endif; ?>

      <!-- Actions -->
      <div class="rdv-actions">
        <?php if($statut==='termine' && !empty($rdv['compte_rendu'])): ?>
          <a href="voir_dossier.php?rdv=<?=$rdv['id']?>" class="btn-detail">📋 Voir le compte-rendu</a>
        <?php else: ?>
          <a href="detail_rdv.php?id=<?=$rdv['id']?>" class="btn-detail">Voir détails</a>
        <?php endif; ?>

        <?php if($can_cancel): ?>
          <button class="btn-annuler" onclick="openModal(<?=$rdv['id']?>)">Annuler le RDV</button>
        <?php endif; ?>

        <?php if($statut==='termine'): ?>
          <a href="prendre_rdv.php?medecin=<?=$rdv['id_medecin']?>" class="btn-reprendre">🔄 Reprendre RDV</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- PAGINATION -->
    <?php if($total_pages>1): ?>
    <div class="pagination">
      <a href="<?=pageUrl($page-1)?>" class="pg <?=$page<=1?'disabled':''?>">←</a>
      <?php
        $rs=max(1,$page-2);$re=min($total_pages,$page+2);
        if($rs>1){echo '<a href="'.pageUrl(1).'" class="pg">1</a>';if($rs>2)echo '<span class="pg" style="pointer-events:none">…</span>';}
        for($p=$rs;$p<=$re;$p++) echo '<a href="'.pageUrl($p).'" class="pg '.($p===$page?'active':'').'">'.$p.'</a>';
        if($re<$total_pages){if($re<$total_pages-1)echo '<span class="pg" style="pointer-events:none">…</span>';echo '<a href="'.pageUrl($total_pages).'" class="pg">'.$total_pages.'</a>';}
      ?>
      <a href="<?=pageUrl($page+1)?>" class="pg <?=$page>=$total_pages?'disabled':''?>">→</a>
    </div>
    <?php endif; ?>

  <?php endif; ?>

  <!-- Bouton prendre RDV -->
  <div style="text-align:center;margin-top:1.5rem">
    <a href="liste_medecins.php" style="display:inline-block;padding:.75rem 2rem;background:var(--green);color:#fff;border-radius:10px;text-decoration:none;font-size:.9rem;font-weight:700;box-shadow:0 4px 15px rgba(45,198,83,.3)">
      + Prendre un nouveau RDV
    </a>
  </div>

</div><!-- /main -->

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Diama Touré (E3)</strong> — mes_rdv.php</p>
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

// --- Modal annulation ---
function openModal(rdvId) {
  document.getElementById('annulRdvId').value = rdvId;
  document.getElementById('modalAnnul').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('modalAnnul').classList.remove('open');
  document.body.style.overflow = '';
}
// Fermer en cliquant sur l'overlay
document.getElementById('modalAnnul').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>