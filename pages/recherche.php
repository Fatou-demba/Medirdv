<?php
// ================================================================
// recherche.php — Résultats de recherche dynamique MediRDV
// Responsable : Babacar Dione (Etudiant 1) — Chef de Projet
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// ================================================================
// JOURS (mapping anglais -> français)
// ================================================================
$jours_fr = [
    'monday'=>'lundi','tuesday'=>'mardi','wednesday'=>'mercredi',
    'thursday'=>'jeudi','friday'=>'vendredi','saturday'=>'samedi','sunday'=>'dimanche',
];
$today_fr    = $jours_fr[strtolower(date('l'))] ?? '';
$tomorrow_fr = $jours_fr[strtolower(date('l', strtotime('+1 day')))] ?? '';

// ================================================================
// PARAMÈTRES GET
// ================================================================
$q              = trim($_GET['q']           ?? '');
$filtre_specs   = isset($_GET['specialite']) && is_array($_GET['specialite']) ? $_GET['specialite'] : [];
$filtre_villes  = isset($_GET['ville'])      && is_array($_GET['ville'])      ? $_GET['ville']      : [];
$filtre_dispo   = $_GET['dispo']   ?? '';   // 'today' | 'week' | ''
$filtre_note    = $_GET['note']    ?? '';   // '4' | '4.5' | ''
$filtre_spec_s  = trim($_GET['specialite_s'] ?? ''); // select rapide hero
$filtre_ville_s = trim($_GET['ville_s']      ?? ''); // select rapide hero
$tri            = $_GET['tri']     ?? 'pertinence';
$par_page       = 6;
$page_courante  = max(1, (int)($_GET['page'] ?? 1));
$offset         = ($page_courante - 1) * $par_page;

// ================================================================
// LISTES POUR LES SELECTS / FILTRES (depuis BDD)
// ================================================================
$stmt = $pdo->query("SELECT specialite, COUNT(*) as nb FROM medecins WHERE statut='valide' GROUP BY specialite ORDER BY specialite");
$specs_count = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT ville, COUNT(*) as nb FROM medecins WHERE statut='valide' GROUP BY ville ORDER BY ville");
$villes_count = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CONSTRUCTION DES FILTRES WHERE
// — $params : filtres WHERE (pas :today/:tomorrow)
// — $dispo_params : :today/:tomorrow pour le SELECT principal
// ================================================================
$where        = ["m.statut = 'valide'"];
$params       = [];
$dispo_params = [':today' => $today_fr, ':tomorrow' => $tomorrow_fr];

// Mot-clé libre (nom, prénom, spécialité, cabinet, ville)
if ($q !== '') {
    $where[] = "(m.nom LIKE :q OR m.prenom LIKE :q OR m.specialite LIKE :q
                 OR m.cabinet LIKE :q OR m.ville LIKE :q OR m.biographie LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

// Select rapide depuis le hero
if ($filtre_spec_s !== '') {
    $where[] = "m.specialite = :spec_s";
    $params[':spec_s'] = $filtre_spec_s;
} elseif (!empty($filtre_specs)) {
    $phs = [];
    foreach ($filtre_specs as $k => $s) {
        $key = ':spec' . $k; $phs[] = $key; $params[$key] = $s;
    }
    $where[] = "m.specialite IN (" . implode(',', $phs) . ")";
}

if ($filtre_ville_s !== '') {
    $where[] = "m.ville = :ville_s";
    $params[':ville_s'] = $filtre_ville_s;
} elseif (!empty($filtre_villes)) {
    $phs = [];
    foreach ($filtre_villes as $k => $v) {
        $key = ':ville' . $k; $phs[] = $key; $params[$key] = $v;
    }
    $where[] = "m.ville IN (" . implode(',', $phs) . ")";
}

// Disponibilité (valeur littérale dans le WHERE pour éviter conflit :today du SELECT)
if ($filtre_dispo === 'today') {
    $today_lit = $pdo->quote($today_fr);
    $where[] = "EXISTS (SELECT 1 FROM disponibilites dw WHERE dw.id_medecin = m.id AND dw.jour_semaine = $today_lit AND dw.actif = 1)";
} elseif ($filtre_dispo === 'week') {
    $where[] = "EXISTS (SELECT 1 FROM disponibilites dw WHERE dw.id_medecin = m.id AND dw.actif = 1)";
}

$where_sql = implode(' AND ', $where);

// Tri
$order_sql = match($tri) {
    'note'  => 'nb_rdv DESC, dispo_today DESC',
    'dispo' => 'dispo_today DESC, dispo_tomorrow DESC, nb_rdv DESC',
    default => 'dispo_today DESC, nb_rdv DESC, m.nom ASC',
};

// ================================================================
// COUNT pour pagination
// ================================================================
$sql_count = "SELECT COUNT(DISTINCT m.id) FROM medecins m WHERE $where_sql";
$stmt = $pdo->prepare($sql_count);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$total       = (int) $stmt->fetchColumn();
$total_pages = (int) ceil($total / $par_page);

// ================================================================
// REQUÊTE PRINCIPALE
// ================================================================
$sql = "
    SELECT
        m.id, m.nom, m.prenom, m.specialite, m.cabinet, m.ville, m.photo, m.biographie,
        MAX(CASE WHEN d.jour_semaine = :today    AND d.actif=1 THEN 1 ELSE 0 END) AS dispo_today,
        MAX(CASE WHEN d.jour_semaine = :tomorrow AND d.actif=1 THEN 1 ELSE 0 END) AS dispo_tomorrow,
        COUNT(DISTINCT r.id) AS nb_rdv
    FROM medecins m
    LEFT JOIN disponibilites d ON d.id_medecin = m.id
    LEFT JOIN rendezvous r ON r.id_medecin = m.id AND r.statut IN ('confirme','termine')
    WHERE $where_sql
    GROUP BY m.id
    ORDER BY $order_sql
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v)       $stmt->bindValue($k, $v);
foreach ($dispo_params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $par_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HELPERS
// ================================================================
$couleurs = [
    'Cardiologie'   => ['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','badge_bg'=>'#EFF6FF','badge_c'=>'#0077B6','btn'=>'#0077B6'],
    'Pediatrie'     => ['grad'=>'linear-gradient(135deg,#7209B7,#B5179E)','badge_bg'=>'#FDF4FF','badge_c'=>'#7209B7','btn'=>'#7209B7'],
    'Dermatologie'  => ['grad'=>'linear-gradient(135deg,#F77F00,#D62828)','badge_bg'=>'#FFF7ED','badge_c'=>'#F77F00','btn'=>'#F77F00'],
    'Gynecologie'   => ['grad'=>'linear-gradient(135deg,#006D77,#2DC653)','badge_bg'=>'#F0FDF4','badge_c'=>'#2DC653','btn'=>'#006D77'],
    'Neurologie'    => ['grad'=>'linear-gradient(135deg,#3A0CA3,#7B2FBE)','badge_bg'=>'#EEF0FF','badge_c'=>'#3A0CA3','btn'=>'#3A0CA3'],
    'Ophtalmologie' => ['grad'=>'linear-gradient(135deg,#0096C7,#48CAE4)','badge_bg'=>'#E0F7FA','badge_c'=>'#0096C7','btn'=>'#0096C7'],
    'default'       => ['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','badge_bg'=>'#EFF6FF','badge_c'=>'#0077B6','btn'=>'#0077B6'],
];
$tarifs = ['Cardiologie'=>'10 000','Pediatrie'=>'8 000','Dermatologie'=>'12 000','Gynecologie'=>'9 000','Neurologie'=>'15 000','Ophtalmologie'=>'10 000','default'=>'10 000'];

function getCouleur($s,$c){return $c[$s]??$c['default'];}
function getTarif($s,$t){return $t[$s]??$t['default'];}

// Highlight des mots-clés dans le texte
function highlight($text, $keyword) {
    if ($keyword === '') return htmlspecialchars($text);
    $safe_kw  = preg_quote($keyword, '/');
    $safe_txt = htmlspecialchars($text);
    return preg_replace('/(' . preg_quote(htmlspecialchars($keyword), '/') . ')/i',
        '<mark>$1</mark>', $safe_txt);
}

// Tags de filtres actifs (pour la barre de tags)
$tags_actifs = [];
if ($q !== '') $tags_actifs[] = ['label' => '"'.$q.'"', 'param' => 'q'];
foreach ($filtre_specs as $s)  $tags_actifs[] = ['label' => $s,  'param' => 'specialite'];
foreach ($filtre_villes as $v) $tags_actifs[] = ['label' => $v,  'param' => 'ville'];
if ($filtre_spec_s !== '')   $tags_actifs[] = ['label' => $filtre_spec_s,  'param' => 'specialite_s'];
if ($filtre_ville_s !== '')  $tags_actifs[] = ['label' => $filtre_ville_s, 'param' => 'ville_s'];
if ($filtre_dispo === 'today') $tags_actifs[] = ['label' => "Disponible aujourd'hui", 'param' => 'dispo'];
if ($filtre_dispo === 'week')  $tags_actifs[] = ['label' => 'Cette semaine',           'param' => 'dispo'];

// Notifications
$notif_count = 0;
if (isLoggedIn()) {
    $type_u = (getRole()==='medecin') ? 'medecin' : 'patient';
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user=:t AND lu=0");
    $s->execute([':id'=>$_SESSION['user_id'],':t'=>$type_u]);
    $notif_count = (int)$s->fetchColumn();
}

function pageUrl($p) {
    $q = $_GET; $q['page'] = $p;
    return '?' . http_build_query($q);
}

// Label de la recherche pour l'affichage
$label_recherche = $q !== '' ? $q : (($filtre_spec_s !== '' ? $filtre_spec_s : '') . ($filtre_ville_s !== '' ? ' '.$filtre_ville_s : ''));
$label_recherche = trim($label_recherche) ?: 'tous les médecins';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Résultats de Recherche</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--orange:#F77F00;--dark:#03045E;--light:#F0F4FF;--gray:#64748B;--lgray:#E2E8F0;--white:#fff}
body{font-family:'DM Sans',sans-serif;background:#F8FAFF;color:#1E293B}

/* ---- NAVBAR ---- */
nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(3,4,94,.4)}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none;letter-spacing:-1px}
.nav-logo span{color:var(--green)}
.nav-links{display:flex;gap:2rem;list-style:none}
.nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;font-weight:500;transition:.2s}
.nav-links a:hover,.nav-links a.active{color:#fff}
.nav-btns{display:flex;gap:.8rem;align-items:center}
.btn-o{padding:.45rem 1.1rem;border:1.5px solid #CBD5E1;color:#CBD5E1;border-radius:7px;font-size:.83rem;cursor:pointer;background:transparent;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
.btn-o:hover{border-color:#fff;color:#fff}
.btn-p{padding:.45rem 1.2rem;background:var(--green);color:#fff;border:none;border-radius:7px;font-size:.83rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.btn-p:hover{background:#25B347;color:#fff}
.notif-badge{display:inline-flex;align-items:center;justify-content:center;background:var(--orange);color:#fff;border-radius:50%;width:17px;height:17px;font-size:.62rem;font-weight:700;margin-left:.3rem}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- SEARCH HERO ---- */
.search-hero{background:linear-gradient(135deg,var(--dark),var(--blue2));padding:28px 5%}
.search-hero h2{font-family:'Sora',sans-serif;font-size:1.25rem;font-weight:700;color:#fff;margin-bottom:.9rem}
.search-bar{display:flex;gap:.7rem;background:#fff;border-radius:12px;padding:.55rem;box-shadow:0 4px 20px rgba(0,0,0,.2);flex-wrap:wrap}
.search-bar input{flex:2;min-width:140px;border:none;outline:none;font-size:.92rem;font-family:'DM Sans',sans-serif;color:#1E293B;padding:.4rem .6rem}
.search-bar select{border:none;outline:none;font-size:.85rem;font-family:'DM Sans',sans-serif;color:var(--gray);padding:.4rem .5rem;border-left:1px solid var(--lgray);background:transparent;min-width:120px}
.search-bar button{padding:.55rem 1.4rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer;white-space:nowrap;transition:.2s}
.search-bar button:hover{background:var(--blue2)}

/* ---- META BAR ---- */
.meta-bar{padding:.8rem 5%;display:flex;justify-content:space-between;align-items:center;background:#fff;border-bottom:1px solid var(--lgray);flex-wrap:wrap;gap:.4rem}
.breadcrumb{font-size:.8rem;color:var(--gray)}
.breadcrumb a{color:var(--gray);text-decoration:none}
.breadcrumb a:hover{color:var(--blue)}
.breadcrumb span{color:var(--blue);font-weight:600}
.results-meta{font-size:.85rem;color:var(--gray)}
.results-meta strong{color:var(--dark);font-family:'Sora',sans-serif}

/* ---- TAGS BAR ---- */
.tags-bar{padding:.65rem 5%;background:#EFF6FF;border-bottom:1px solid #DBEAFE;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
.tags-bar > span{font-size:.8rem;color:var(--blue);font-weight:600;white-space:nowrap}
.tag{background:#fff;border:1.5px solid #BFDBFE;color:var(--blue);padding:.2rem .65rem;border-radius:20px;font-size:.77rem;font-weight:600;display:flex;align-items:center;gap:.3rem}
.tag .remove{cursor:pointer;color:#94A3B8;font-size:.7rem;transition:.2s}
.tag .remove:hover{color:var(--blue)}
.clear-all{margin-left:auto;color:var(--gray);font-size:.8rem;cursor:pointer;text-decoration:none;white-space:nowrap}
.clear-all:hover{color:var(--blue)}

/* ---- LAYOUT ---- */
.layout{display:flex;gap:1.5rem;padding:1.5rem 5%;align-items:flex-start}

/* ---- SIDEBAR ---- */
.sidebar{width:240px;flex-shrink:0}
.fcard{background:#fff;border-radius:12px;padding:1rem;border:1px solid var(--lgray);margin-bottom:.8rem;box-shadow:0 1px 6px rgba(0,0,0,.04)}
.fcard h4{font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:var(--dark);margin-bottom:.7rem;padding-bottom:.4rem;border-bottom:1px solid var(--lgray)}
.fopt{display:flex;align-items:center;gap:.5rem;padding:.28rem 0;cursor:pointer}
.fopt input{accent-color:var(--blue);width:14px;height:14px;flex-shrink:0;cursor:pointer}
.fopt label{font-size:.82rem;color:#334155;cursor:pointer;flex:1}
.fopt .cnt{background:var(--light);color:var(--gray);font-size:.7rem;padding:.1rem .35rem;border-radius:4px;white-space:nowrap}
.apply-btn{width:100%;padding:.6rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.83rem;font-weight:700;cursor:pointer;margin-top:.5rem;font-family:'DM Sans',sans-serif;transition:.2s}
.apply-btn:hover{background:var(--blue2)}
.reset-link{display:block;text-align:center;padding:.4rem;color:var(--gray);font-size:.8rem;text-decoration:none;margin-top:.3rem;border:1px solid var(--lgray);border-radius:7px}
.reset-link:hover{border-color:var(--gray);color:#334155}

/* ---- RÉSULTATS ---- */
.results-area{flex:1;min-width:0}
.sort-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
.sort-bar p{font-size:.85rem;color:var(--gray)}
.sort-bar p strong{color:var(--dark)}
.sort-select{padding:.4rem .7rem;border:1px solid var(--lgray);border-radius:7px;font-size:.83rem;outline:none;background:#fff;color:#334155;font-family:'DM Sans',sans-serif}

/* ---- CARD ---- */
.dcard{background:#fff;border-radius:12px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:.9rem;display:flex;gap:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:.2s;cursor:pointer}
.dcard:hover{border-color:#93C5FD;box-shadow:0 6px 20px rgba(0,0,0,.09);transform:translateY(-2px)}
.davatar{width:62px;height:62px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0;overflow:hidden}
.davatar img{width:100%;height:100%;object-fit:cover;border-radius:12px}
.dmain{flex:1;min-width:0}
.dtop{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.3rem;gap:.4rem;flex-wrap:wrap}
.dname{font-family:'Sora',sans-serif;font-size:.98rem;font-weight:700;color:#1E293B}
.dname mark{background:#FEF08A;color:#1E293B;border-radius:3px;padding:0 2px}
.dspec{display:inline-block;font-size:.73rem;font-weight:600;padding:.18rem .55rem;border-radius:5px;margin-left:.4rem}
.dstars{color:#FBBF24;font-size:.8rem;margin-bottom:.3rem}
.dinfo{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:.2rem}
.dinfo span{font-size:.8rem;color:var(--gray);display:flex;align-items:center;gap:.25rem}
.dinfo mark{background:#FEF08A;color:#1E293B;border-radius:3px;padding:0 2px}
.dbadge{display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;font-weight:600;padding:.25rem .6rem;border-radius:5px;white-space:nowrap}
.dbadge.avail{background:#F0FDF4;color:var(--green);border:1px solid #BBF7D0}
.dbadge.tmrw{background:#FFF7ED;color:var(--orange);border:1px solid #FED7AA}
.dbadge.none{background:#F1F5F9;color:var(--gray);border:1px solid var(--lgray)}
.dbtns{display:flex;gap:.5rem;margin-top:.7rem;flex-wrap:wrap;onclick:event.stopPropagation()}
.btn-rdv{padding:.45rem 1.1rem;color:#fff;border:none;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:.2s}
.btn-rdv:hover{opacity:.88;color:#fff}
.btn-see{padding:.45rem .9rem;background:transparent;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;border-width:1.5px;border-style:solid;transition:.2s}
.btn-see:hover{opacity:.8}

/* Etat vide */
.empty-state{text-align:center;padding:50px 20px;color:var(--gray)}
.empty-state .icon{font-size:3rem;margin-bottom:1rem}
.empty-state h4{font-size:1rem;font-weight:600;color:#334155;margin-bottom:.4rem}

/* ---- PAGINATION ---- */
.pagination{display:flex;justify-content:center;gap:.4rem;margin-top:1.5rem;flex-wrap:wrap}
.pg{min-width:34px;height:34px;padding:0 .5rem;border-radius:7px;border:1px solid var(--lgray);background:#fff;font-size:.83rem;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:#334155;text-decoration:none;transition:.2s}
.pg.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.pg:hover:not(.active){border-color:var(--blue);color:var(--blue)}
.pg.disabled{opacity:.4;pointer-events:none}

/* Bouton mobile filtres */
.btn-filter-mobile{display:none;width:100%;padding:.65rem;background:var(--dark);color:#fff;border:none;border-radius:10px;font-size:.88rem;font-weight:600;cursor:pointer;margin-bottom:1rem;font-family:'DM Sans',sans-serif}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:199}
.sidebar-overlay.open{display:block}

footer{background:var(--dark);color:#94A3B8;padding:25px 5%;text-align:center;font-size:.82rem;margin-top:1rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE MOBILE
================================================================ */
@media(max-width:768px){
  /* Navbar */
  .nav-links{display:none;position:fixed;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;box-shadow:0 8px 20px rgba(0,0,0,.3);border-top:1px solid rgba(255,255,255,.08);z-index:99}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06);width:100%}
  .nav-btns{gap:.4rem}
  .btn-o,.btn-p{padding:.38rem .75rem;font-size:.78rem}
  .nav-hamburger{display:flex}

  /* Hero search */
  .search-bar{flex-direction:column;gap:.5rem}
  .search-bar select{border-left:none;border-top:1px solid var(--lgray)}
  .search-bar button{width:100%}

  /* Meta + tags */
  .meta-bar{flex-direction:column;align-items:flex-start;gap:.3rem}
  .tags-bar{padding:.6rem 4%}

  /* Layout */
  .layout{flex-direction:column;padding:1rem 4%}

  /* Sidebar → drawer */
  .sidebar{width:270px;position:fixed;top:0;left:-290px;height:100vh;background:#fff;z-index:200;overflow-y:auto;padding:1.2rem .8rem;box-shadow:4px 0 20px rgba(0,0,0,.15);transition:.3s}
  .sidebar.open{left:0}
  .btn-filter-mobile{display:block}
  .sidebar-close{display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem}
  .sidebar-close h3{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark)}
  .sidebar-close button{background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--gray)}

  /* Cards compact */
  .dcard{flex-direction:row;gap:.7rem;padding:1rem}
  .davatar{width:50px;height:50px;font-size:1.5rem}
  .dname{font-size:.9rem}
  .dinfo span{font-size:.76rem}
  .dbtns{gap:.4rem}
  .btn-rdv,.btn-see{padding:.38rem .85rem;font-size:.76rem}
  .sort-bar{flex-direction:column;align-items:flex-start}
  .sort-select{width:100%}
}
@media(max-width:400px){
  .search-hero{padding:20px 4%}
  .search-hero h2{font-size:1.1rem}
  .layout{padding:.8rem 4%}
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
    <li><a href="aide.php">Aide</a></li>
    <li><a href="contact.php">Contact</a></li>
  </ul>
  <div class="nav-btns">
    <?php if (isLoggedIn()):
      $profil_url = match(getRole()){ 'medecin'=>'profil_medecin.php','admin'=>'dashboard_admin.php',default=>'profil_patient.php' };
    ?>
      <a href="notifications.php" class="btn-o">🔔<?php if($notif_count>0):?><span class="notif-badge"><?=$notif_count?></span><?php endif;?></a>
      <a href="<?=$profil_url?>" class="btn-p">👤 <?=htmlspecialchars($_SESSION['prenom']??'Espace')?></a>
    <?php else: ?>
      <a href="login.php" class="btn-o">Connexion</a>
      <a href="register_patient.php" class="btn-p">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ============================================================
     BARRE DE RECHERCHE HERO
============================================================ -->
<div class="search-hero">
  <h2>Résultats de recherche</h2>
  <form method="GET" action="recherche.php" id="heroForm">
    <div class="search-bar">
      <input type="text" name="q"
             value="<?=htmlspecialchars($q)?>"
             placeholder="Spécialité, médecin, ville...">
      <select name="specialite_s">
        <option value="">Toutes spécialités</option>
        <?php foreach ($specs_count as $sc): ?>
          <option value="<?=htmlspecialchars($sc['specialite'])?>"
            <?=$filtre_spec_s===$sc['specialite']?'selected':''?>>
            <?=htmlspecialchars($sc['specialite'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="ville_s">
        <option value="">Toutes les villes</option>
        <?php foreach ($villes_count as $vc): ?>
          <option value="<?=htmlspecialchars($vc['ville'])?>"
            <?=$filtre_ville_s===$vc['ville']?'selected':''?>>
            <?=htmlspecialchars($vc['ville'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit">🔍 Rechercher</button>
    </div>
  </form>
</div>

<!-- ============================================================
     META BAR
============================================================ -->
<div class="meta-bar">
  <div class="breadcrumb">
    <a href="../index.php">Accueil</a> →
    <a href="liste_medecins.php">Médecins</a> →
    <span>Résultats pour "<?=htmlspecialchars($label_recherche)?>"</span>
  </div>
  <div class="results-meta">
    <strong><?=$total?></strong> résultat<?=$total>1?'s':''?> trouvé<?=$total>1?'s':''?>
  </div>
</div>

<!-- ============================================================
     BARRE DE TAGS ACTIFS
============================================================ -->
<?php if (!empty($tags_actifs)): ?>
<div class="tags-bar">
  <span>Filtres actifs :</span>
  <?php foreach ($tags_actifs as $tag):
    // URL pour retirer ce tag
    $remove_get = $_GET;
    unset($remove_get[$tag['param']]);
    $remove_url = '?' . http_build_query($remove_get);
  ?>
    <div class="tag">
      <?=htmlspecialchars($tag['label'])?>
      <a href="<?=$remove_url?>" class="remove" title="Retirer ce filtre">✕</a>
    </div>
  <?php endforeach; ?>
  <a href="recherche.php" class="clear-all">Tout effacer</a>
</div>
<?php endif; ?>

<!-- ============================================================
     LAYOUT PRINCIPAL
============================================================ -->
<div class="layout">

  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR / DRAWER -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-close" id="sidebarClose" style="display:none">
      <h3>Filtres</h3>
      <button onclick="closeSidebar()">✕</button>
    </div>

    <form method="GET" action="recherche.php" id="filterForm">
      <!-- Conserver le mot-clé et les selects rapides -->
      <input type="hidden" name="q"           value="<?=htmlspecialchars($q)?>">
      <input type="hidden" name="specialite_s" value="<?=htmlspecialchars($filtre_spec_s)?>">
      <input type="hidden" name="ville_s"      value="<?=htmlspecialchars($filtre_ville_s)?>">
      <input type="hidden" name="tri"          id="hiddenTri" value="<?=htmlspecialchars($tri)?>">

      <!-- Spécialité -->
      <div class="fcard">
        <h4>🏥 Spécialité</h4>
        <?php foreach ($specs_count as $sc): ?>
        <div class="fopt">
          <input type="checkbox" name="specialite[]"
                 id="s_<?=md5($sc['specialite'])?>"
                 value="<?=htmlspecialchars($sc['specialite'])?>"
                 <?=in_array($sc['specialite'],$filtre_specs)?'checked':''?>>
          <label for="s_<?=md5($sc['specialite'])?>"><?=htmlspecialchars($sc['specialite'])?></label>
          <span class="cnt"><?=$sc['nb']?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Ville -->
      <div class="fcard">
        <h4>📍 Ville</h4>
        <?php foreach ($villes_count as $vc): ?>
        <div class="fopt">
          <input type="checkbox" name="ville[]"
                 id="v_<?=md5($vc['ville'])?>"
                 value="<?=htmlspecialchars($vc['ville'])?>"
                 <?=in_array($vc['ville'],$filtre_villes)?'checked':''?>>
          <label for="v_<?=md5($vc['ville'])?>"><?=htmlspecialchars($vc['ville'])?></label>
          <span class="cnt"><?=$vc['nb']?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Disponibilité -->
      <div class="fcard">
        <h4>📅 Disponibilité</h4>
        <div class="fopt">
          <input type="radio" name="dispo" id="d0" value="" <?=$filtre_dispo===''?'checked':''?>>
          <label for="d0">Tous</label>
        </div>
        <div class="fopt">
          <input type="radio" name="dispo" id="d1" value="today" <?=$filtre_dispo==='today'?'checked':''?>>
          <label for="d1">Disponible aujourd'hui</label>
        </div>
        <div class="fopt">
          <input type="radio" name="dispo" id="d2" value="week" <?=$filtre_dispo==='week'?'checked':''?>>
          <label for="d2">Cette semaine</label>
        </div>
        <button type="submit" class="apply-btn">Appliquer les filtres</button>
        <a href="recherche.php<?=$q!==''?'?q='.urlencode($q):''?>" class="reset-link">✕ Réinitialiser</a>
      </div>
    </form>
  </aside>

  <!-- ============================================================
       ZONE RÉSULTATS
  ============================================================ -->
  <div class="results-area">

    <!-- Bouton filtres mobile -->
    <button class="btn-filter-mobile" onclick="openSidebar()">⚙️ Filtres</button>

    <div class="sort-bar">
      <p>
        <strong><?=$total?></strong>
        médecin<?=$total>1?'s':''?> trouvé<?=$total>1?'s':''?>
        <?php if($label_recherche !== 'tous les médecins'): ?>
          pour "<strong><?=htmlspecialchars($label_recherche)?></strong>"
        <?php endif; ?>
      </p>
      <select class="sort-select" onchange="changeTri(this.value)">
        <option value="pertinence" <?=$tri==='pertinence'?'selected':''?>>Trier : Pertinence</option>
        <option value="note"       <?=$tri==='note'      ?'selected':''?>>Trier : Popularité</option>
        <option value="dispo"      <?=$tri==='dispo'     ?'selected':''?>>Trier : Disponibilité</option>
      </select>
    </div>

    <?php if (empty($medecins)): ?>
      <div class="empty-state">
        <div class="icon">🔍</div>
        <h4>Aucun médecin trouvé</h4>
        <p>Essayez d'autres mots-clés ou <a href="liste_medecins.php" style="color:var(--blue)">parcourez tous les médecins</a>.</p>
      </div>
    <?php else: ?>
      <?php foreach ($medecins as $i => $m):
        $c      = getCouleur($m['specialite'], $couleurs);
        $tarif  = getTarif($m['specialite'], $tarifs);
        $avatar = ($i % 2 === 0) ? '👨‍⚕️' : '👩‍⚕️';
        $stars  = min(5, max(3, 3 + floor($m['nb_rdv'] / 10)));
        $stars_html = str_repeat('★',$stars).str_repeat('☆',5-$stars);
        $nb_avis = max(5, $m['nb_rdv'] + rand(3,20));
        $note_val = number_format(3.5 + ($stars-3)*0.5, 1);
        $duree = match($m['specialite']) { 'Pediatrie'=>20,'Dermatologie'=>25, default=>30 };

        if ($m['dispo_today']) {
          $dispo_class='avail'; $dispo_txt='🟢 Disponible';
        } elseif ($m['dispo_tomorrow']) {
          $dispo_class='tmrw'; $dispo_txt='🟡 Demain matin';
        } else {
          $dispo_class='none'; $dispo_txt='⚪ Sur RDV';
        }

        // Terme à highlighter : mot-clé OU spécialité rapide
        $kw = $q !== '' ? $q : $filtre_spec_s;
      ?>
      <div class="dcard" onclick="window.location='detail_medecin.php?id=<?=$m['id']?>'">

        <div class="davatar" style="background:<?=$c['grad']?>">
          <?php if(!empty($m['photo']) && file_exists(__DIR__.'/../assets/img/'.$m['photo'])): ?>
            <img src="../assets/img/<?=htmlspecialchars($m['photo'])?>"
                 alt="Dr. <?=htmlspecialchars($m['nom'])?>">
          <?php else: ?>
            <?=$avatar?>
          <?php endif; ?>
        </div>

        <div class="dmain">
          <div class="dtop">
            <div>
              <span class="dname">
                Dr. <?=highlight($m['prenom'].' '.$m['nom'], $kw)?>
              </span>
              <span class="dspec" style="background:<?=$c['badge_bg']?>;color:<?=$c['badge_c']?>">
                <?=htmlspecialchars($m['specialite'])?>
              </span>
            </div>
            <span class="dbadge <?=$dispo_class?>"><?=$dispo_txt?></span>
          </div>

          <div class="dstars">
            <?=$stars_html?>
            <span style="color:#94A3B8;font-size:.75rem"><?=$note_val?> (<?=$nb_avis?> avis)</span>
          </div>

          <div class="dinfo">
            <span>📍 <?=highlight($m['cabinet'].', '.$m['ville'], $kw)?></span>
            <span>⏱ <?=$duree?> min</span>
            <span>💰 <?=$tarif?> FCFA</span>
          </div>

          <div class="dbtns" onclick="event.stopPropagation()">
            <?php if (isLoggedIn()): ?>
              <a href="prendre_rdv.php?medecin=<?=$m['id']?>"
                 class="btn-rdv" style="background:<?=$c['btn']?>">Prendre RDV</a>
            <?php else: ?>
              <a href="login.php?redirect=prendre_rdv.php%3Fmedecin%3D<?=$m['id']?>"
                 class="btn-rdv" style="background:<?=$c['btn']?>">Prendre RDV</a>
            <?php endif; ?>
            <a href="detail_medecin.php?id=<?=$m['id']?>"
               class="btn-see" style="color:<?=$c['btn']?>;border-color:<?=$c['btn']?>">Voir profil</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <a href="<?=pageUrl($page_courante-1)?>" class="pg <?=$page_courante<=1?'disabled':''?>">←</a>
      <?php
        $range=2; $start=max(1,$page_courante-$range); $end=min($total_pages,$page_courante+$range);
        if($start>1){ echo '<a href="'.pageUrl(1).'" class="pg">1</a>'; if($start>2) echo '<span class="pg" style="pointer-events:none">…</span>'; }
        for($p=$start;$p<=$end;$p++) echo '<a href="'.pageUrl($p).'" class="pg '.($p===$page_courante?'active':'').'">'.$p.'</a>';
        if($end<$total_pages){ if($end<$total_pages-1) echo '<span class="pg" style="pointer-events:none">…</span>'; echo '<a href="'.pageUrl($total_pages).'" class="pg">'.$total_pages.'</a>'; }
      ?>
      <a href="<?=pageUrl($page_courante+1)?>" class="pg <?=$page_courante>=$total_pages?'disabled':''?>">→</a>
    </div>
    <?php endif; ?>

  </div><!-- /results-area -->
</div><!-- /layout -->

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Babacar Dione (E1)</strong> — recherche.php</p>
</footer>

<script>
// --- Hamburger ---
function toggleMenu(){
  const nav=document.getElementById('navLinks'),icon=document.getElementById('hamburger');
  nav.classList.toggle('open');
  const s=icon.querySelectorAll('span');
  if(nav.classList.contains('open')){s[0].style.transform='rotate(45deg) translate(5px,5px)';s[1].style.opacity='0';s[2].style.transform='rotate(-45deg) translate(5px,-5px)';}
  else{s.forEach(x=>{x.style.transform='';x.style.opacity='';})}
}
document.addEventListener('click',function(e){
  const nav=document.getElementById('navLinks'),icon=document.getElementById('hamburger');
  if(!nav.contains(e.target)&&!icon.contains(e.target)){nav.classList.remove('open');icon.querySelectorAll('span').forEach(x=>{x.style.transform='';x.style.opacity='';})}
});

// --- Drawer sidebar mobile ---
function openSidebar(){
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('overlay').classList.add('open');
  document.getElementById('sidebarClose').style.display='flex';
  document.body.style.overflow='hidden';
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
  document.body.style.overflow='';
}
if(window.innerWidth<=768) document.getElementById('sidebarClose').style.display='flex';

// --- Tri ---
function changeTri(val){
  document.getElementById('hiddenTri').value=val;
  document.getElementById('filterForm').submit();
}

// --- Soumission auto sur checkbox / radio ---
document.querySelectorAll('#filterForm input[type=checkbox],#filterForm input[type=radio]').forEach(function(el){
  el.addEventListener('change',function(){
    let pi=document.querySelector('#filterForm input[name="page"]');
    if(!pi){pi=document.createElement('input');pi.type='hidden';pi.name='page';document.getElementById('filterForm').appendChild(pi);}
    pi.value=1;
    document.getElementById('filterForm').submit();
  });
});
</script>
</body>
</html>