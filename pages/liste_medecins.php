<?php
// ================================================================
// liste_medecins.php — Liste complète des médecins (dynamique)
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
    'monday'    => 'lundi',
    'tuesday'   => 'mardi',
    'wednesday' => 'mercredi',
    'thursday'  => 'jeudi',
    'friday'    => 'vendredi',
    'saturday'  => 'samedi',
    'sunday'    => 'dimanche',
];
$today_fr    = $jours_fr[strtolower(date('l'))] ?? '';
$tomorrow_fr = $jours_fr[strtolower(date('l', strtotime('+1 day')))] ?? '';
$week_days   = array_values($jours_fr); // lundi → samedi

// ================================================================
// RÉCUPÉRER LES LISTES (spécialités / villes) POUR LES FILTRES
// ================================================================
$stmt = $pdo->query("SELECT specialite, COUNT(*) as nb FROM medecins WHERE statut='valide' GROUP BY specialite ORDER BY specialite");
$specs_count = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT ville, COUNT(*) as nb FROM medecins WHERE statut='valide' GROUP BY ville ORDER BY ville");
$villes_count = $stmt->fetchAll(PDO::FETCH_ASSOC);
//test
// ================================================================
// PARAMÈTRES DE FILTRAGE (GET)
// ================================================================
$filtre_nom       = trim($_GET['nom']        ?? '');
$filtre_specs     = isset($_GET['specialite']) && is_array($_GET['specialite']) ? $_GET['specialite'] : [];
$filtre_villes    = isset($_GET['ville'])     && is_array($_GET['ville'])     ? $_GET['ville']     : [];
$filtre_dispo     = $_GET['dispo']   ?? '';   // 'today' | 'week' | 'month' | ''
$tri              = $_GET['tri']     ?? 'dispo'; // dispo | note | nom

// Pagination
$par_page = 8;
$page_courante = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page_courante - 1) * $par_page;

// ================================================================
// CONSTRUCTION DE LA REQUÊTE PRINCIPALE
// ================================================================
// $params = paramètres des filtres WHERE uniquement (PAS :today/:tomorrow)
// $dispo_params = :today et :tomorrow pour le SELECT de la requête principale
// On les sépare pour éviter SQLSTATE HY093 (paramètre réutilisé dans des requêtes différentes)
$params      = [];
$dispo_params = [':today' => $today_fr, ':tomorrow' => $tomorrow_fr];

$where  = ["m.statut = 'valide'"];

if ($filtre_nom !== '') {
    $where[]           = "(m.nom LIKE :nom OR m.prenom LIKE :nom OR m.specialite LIKE :nom)";
    $params[':nom']    = '%' . $filtre_nom . '%';
}
if (!empty($filtre_specs)) {
    $placeholders = [];
    foreach ($filtre_specs as $k => $s) {
        $key = ':spec' . $k;
        $placeholders[] = $key;
        $params[$key]   = $s;
    }
    $where[] = "m.specialite IN (" . implode(',', $placeholders) . ")";
}
if (!empty($filtre_villes)) {
    $placeholders = [];
    foreach ($filtre_villes as $k => $v) {
        $key = ':ville' . $k;
        $placeholders[] = $key;
        $params[$key]   = $v;
    }
    $where[] = "m.ville IN (" . implode(',', $placeholders) . ")";
}
if ($filtre_dispo === 'today') {
    // On utilise une valeur littérale (interpolée) pour éviter conflit avec :today du SELECT
    $today_lit = $pdo->quote($today_fr);
    $where[] = "EXISTS (SELECT 1 FROM disponibilites dw WHERE dw.id_medecin = m.id AND dw.jour_semaine = $today_lit AND dw.actif = 1)";
} elseif ($filtre_dispo === 'week') {
    $where[] = "EXISTS (SELECT 1 FROM disponibilites dw WHERE dw.id_medecin = m.id AND dw.actif = 1)";
}

$where_sql = implode(' AND ', $where);

// Tri
$order_sql = match($tri) {
    'note' => 'nb_rdv DESC, dispo_today DESC',
    'nom'  => 'm.nom ASC, m.prenom ASC',
    default => 'dispo_today DESC, dispo_tomorrow DESC, nb_rdv DESC',
};

// ---- Requête COUNT (pagination) — utilise uniquement $params ----
$sql_count = "SELECT COUNT(DISTINCT m.id) FROM medecins m WHERE $where_sql";
$stmt = $pdo->prepare($sql_count);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$total       = (int) $stmt->fetchColumn();
$total_pages = (int) ceil($total / $par_page);

// ---- Requête principale — utilise $params + $dispo_params + :limit/:offset ----
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
// 1) paramètres filtres WHERE
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
// 2) paramètres dispo SELECT
foreach ($dispo_params as $k => $v) $stmt->bindValue($k, $v);
// 3) pagination
$stmt->bindValue(':limit',  $par_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// COULEURS PAR SPÉCIALITÉ
// ================================================================
$couleurs = [
    'Cardiologie'   => ['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','badge_bg'=>'#EFF6FF','badge_c'=>'#0077B6','btn'=>'#0077B6'],
    'Pediatrie'     => ['grad'=>'linear-gradient(135deg,#7209B7,#B5179E)','badge_bg'=>'#FDF4FF','badge_c'=>'#7209B7','btn'=>'#7209B7'],
    'Dermatologie'  => ['grad'=>'linear-gradient(135deg,#F77F00,#D62828)','badge_bg'=>'#FFF7ED','badge_c'=>'#F77F00','btn'=>'#F77F00'],
    'Gynecologie'   => ['grad'=>'linear-gradient(135deg,#006D77,#2DC653)','badge_bg'=>'#F0FDF4','badge_c'=>'#2DC653','btn'=>'#006D77'],
    'Neurologie'    => ['grad'=>'linear-gradient(135deg,#3A0CA3,#7B2FBE)','badge_bg'=>'#EEF0FF','badge_c'=>'#3A0CA3','btn'=>'#3A0CA3'],
    'Ophtalmologie' => ['grad'=>'linear-gradient(135deg,#0096C7,#48CAE4)','badge_bg'=>'#E0F7FA','badge_c'=>'#0096C7','btn'=>'#0096C7'],
    'Stomatologie'  => ['grad'=>'linear-gradient(135deg,#E63946,#C1121F)','badge_bg'=>'#FFF0F0','badge_c'=>'#E63946','btn'=>'#E63946'],
    'default'       => ['grad'=>'linear-gradient(135deg,#0077B6,#023E8A)','badge_bg'=>'#EFF6FF','badge_c'=>'#0077B6','btn'=>'#0077B6'],
];
$tarifs = ['Cardiologie'=>'10 000','Pediatrie'=>'8 000','Dermatologie'=>'12 000','Gynecologie'=>'9 000','Neurologie'=>'15 000','Ophtalmologie'=>'10 000','Stomatologie'=>'7 000','default'=>'10 000'];

function getCouleur($spec, $c){ return $c[$spec] ?? $c['default']; }
function getTarif($spec, $t){ return $t[$spec] ?? $t['default']; }

// Notifications
$notif_count = 0;
if (isLoggedIn()) {
    $type_user = (getRole() === 'medecin') ? 'medecin' : 'patient';
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user=:t AND lu=0");
    $s->execute([':id'=>$_SESSION['user_id'],':t'=>$type_user]);
    $notif_count = (int)$s->fetchColumn();
}

// Helper pagination URL
function pageUrl($p, $params) {
    $q = $_GET;
    $q['page'] = $p;
    return '?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Liste des Médecins</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--orange:#F77F00;--dark:#03045E;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0}
body{font-family:'DM Sans',sans-serif;background:#F8FAFF;color:#1E293B}

/* ---- NAVBAR ---- */
nav{background:var(--dark);padding:0 5%;height:68px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(3,4,94,.4)}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.5rem;color:#fff;letter-spacing:-1px;text-decoration:none}
.nav-logo span{color:var(--green)}
.nav-links{display:flex;gap:2rem;list-style:none}
.nav-links a{color:#CBD5E1;text-decoration:none;font-size:.9rem;font-weight:500;transition:.2s}
.nav-links a:hover{color:#fff}
.nav-links a.active{color:#fff;border-bottom:2px solid var(--green);padding-bottom:2px}
.nav-btns{display:flex;gap:.8rem;align-items:center}
.btn-outline{padding:.5rem 1.2rem;border:1.5px solid #CBD5E1;color:#CBD5E1;border-radius:8px;font-size:.85rem;cursor:pointer;background:transparent;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
.btn-outline:hover{border-color:#fff;color:#fff}
.btn-primary{padding:.5rem 1.4rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.btn-primary:hover{background:#25B347;color:#fff}
.notif-badge{display:inline-flex;align-items:center;justify-content:center;background:var(--orange);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.65rem;font-weight:700;margin-left:.3rem}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- PAGE HEADER ---- */
.page-header{background:linear-gradient(135deg,var(--dark),var(--blue2));padding:40px 5%;color:#fff}
.page-header h1{font-family:'Sora',sans-serif;font-size:2rem;font-weight:800}
.page-header p{color:#CBD5E1;margin-top:.5rem;font-size:.95rem}
.breadcrumb{font-size:.82rem;color:#94A3B8;margin-bottom:.8rem}
.breadcrumb a{color:#94A3B8;text-decoration:none}
.breadcrumb a:hover{color:#fff}
.breadcrumb span{color:var(--green)}

/* ---- LAYOUT ---- */
.main-layout{display:flex;gap:1.5rem;padding:2rem 5%;min-height:70vh;align-items:flex-start}

/* ---- SIDEBAR ---- */
.sidebar{width:260px;flex-shrink:0}
.filter-card{background:#fff;border-radius:14px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 10px rgba(0,0,0,.05)}
.filter-card h4{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:var(--dark);margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray)}
.filter-option{display:flex;align-items:center;gap:.6rem;padding:.35rem 0;cursor:pointer}
.filter-option input[type=checkbox]{accent-color:var(--blue);width:15px;height:15px;flex-shrink:0;cursor:pointer}
.filter-option label{font-size:.85rem;color:#334155;cursor:pointer;flex:1}
.filter-option .count{background:var(--light);color:var(--gray);font-size:.72rem;padding:.1rem .4rem;border-radius:5px;white-space:nowrap}
.filter-input{width:100%;padding:.6rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.85rem;outline:none;font-family:'DM Sans',sans-serif;transition:.2s}
.filter-input:focus{border-color:var(--blue)}
.filter-btn{width:100%;padding:.65rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;margin-top:.5rem;font-family:'DM Sans',sans-serif;transition:.2s}
.filter-btn:hover{background:var(--blue2)}
.reset-link{display:block;text-align:center;padding:.5rem;color:var(--gray);font-size:.82rem;text-decoration:none;margin-top:.4rem;border:1px solid var(--lgray);border-radius:8px}
.reset-link:hover{border-color:var(--gray);color:#334155}

/* ---- RÉSULTATS ---- */
.results{flex:1;min-width:0}
.results-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem}
.results-count{font-size:.9rem;color:var(--gray)}
.results-count strong{color:var(--dark);font-family:'Sora',sans-serif}
.sort-select{padding:.4rem .8rem;border:1px solid var(--lgray);border-radius:8px;font-size:.85rem;color:#334155;outline:none;background:#fff;font-family:'DM Sans',sans-serif}

/* ---- CARD MÉDECIN (liste horizontale) ---- */
.doctor-card{
  background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);
  margin-bottom:1rem;display:flex;gap:1.2rem;align-items:flex-start;
  box-shadow:0 2px 10px rgba(0,0,0,.04);transition:.2s;cursor:pointer;
}
.doctor-card:hover{border-color:#B8D4FF;box-shadow:0 6px 24px rgba(0,0,0,.1);transform:translateY(-2px)}
.doctor-avatar{width:70px;height:70px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;overflow:hidden}
.doctor-avatar img{width:100%;height:100%;object-fit:cover;border-radius:14px}
.doc-main{flex:1;min-width:0}
.doc-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;gap:.5rem;flex-wrap:wrap}
.doc-name{font-family:'Sora',sans-serif;font-size:1.05rem;font-weight:700;color:#1E293B}
.doc-spec{display:inline-block;font-size:.75rem;font-weight:600;padding:.2rem .6rem;border-radius:6px;margin-left:.4rem}
.doc-stars{color:#FBBF24;font-size:.85rem;margin-bottom:.3rem}
.doc-info{display:flex;gap:1rem;margin:.4rem 0 .6rem;flex-wrap:wrap}
.doc-info span{font-size:.83rem;color:var(--gray);display:flex;align-items:center;gap:.3rem}
.dispo-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.78rem;font-weight:600;padding:.3rem .7rem;border-radius:6px}
.dispo-today{background:#F0FDF4;color:var(--green);border:1px solid #BBF7D0}
.dispo-tomorrow{background:#FFF7ED;color:var(--orange);border:1px solid #FED7AA}
.dispo-none{background:#F1F5F9;color:var(--gray);border:1px solid var(--lgray)}
.doc-actions{display:flex;gap:.6rem;margin-top:.8rem;flex-wrap:wrap}
.btn-rdv{padding:.5rem 1.2rem;color:#fff;border:none;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:.2s}
.btn-rdv:hover{opacity:.88;color:#fff}
.btn-profil{padding:.5rem 1rem;background:transparent;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;border-width:1.5px;border-style:solid;transition:.2s}
.btn-profil:hover{opacity:.8}

/* Vide */
.empty-state{text-align:center;padding:60px 20px;color:var(--gray)}
.empty-state h4{font-size:1.1rem;font-weight:600;margin-bottom:.5rem;color:#334155}

/* ---- PAGINATION ---- */
.pagination{display:flex;justify-content:center;gap:.4rem;margin-top:2rem;flex-wrap:wrap}
.page-btn{min-width:36px;height:36px;padding:0 .6rem;border-radius:8px;border:1px solid var(--lgray);background:#fff;font-size:.85rem;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:#334155;text-decoration:none;transition:.2s}
.page-btn.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.page-btn:hover:not(.active){border-color:var(--blue);color:var(--blue)}
.page-btn.disabled{opacity:.4;pointer-events:none}

/* ---- BOUTON FILTRES MOBILE ---- */
.btn-filter-mobile{display:none;width:100%;padding:.7rem;background:var(--dark);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer;margin-bottom:1rem;font-family:'DM Sans',sans-serif}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:199}
.sidebar-overlay.open{display:block}
.sidebar-drawer{transition:.3s;z-index:200}

/* ---- FOOTER ---- */
footer{background:var(--dark);color:#94A3B8;padding:30px 5%;text-align:center;font-size:.85rem;margin-top:2rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE MOBILE
   ================================================================ */
@media(max-width:900px){
  .sidebar{width:220px}
}
@media(max-width:768px){
  /* Navbar */
  .nav-links{display:none;position:fixed;top:68px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;box-shadow:0 8px 20px rgba(0,0,0,.3);border-top:1px solid rgba(255,255,255,.08);z-index:99}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06);width:100%}
  .nav-btns{gap:.4rem}
  .btn-outline,.btn-primary{padding:.4rem .8rem;font-size:.78rem}
  .nav-hamburger{display:flex}

  /* Layout */
  .main-layout{flex-direction:column;padding:1rem 4%;gap:1rem}

  /* Sidebar en drawer sur mobile */
  .sidebar{
    width:280px;position:fixed;top:0;left:-300px;height:100vh;
    background:#fff;z-index:200;overflow-y:auto;padding:1.5rem 1rem;
    box-shadow:4px 0 20px rgba(0,0,0,.15);transition:.3s;
  }
  .sidebar.open{left:0}
  .btn-filter-mobile{display:block}
  .sidebar-close{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
  .sidebar-close h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:var(--dark)}
  .sidebar-close button{background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--gray)}

  /* Cards en version compacte */
  .doctor-card{flex-direction:row;gap:.8rem;padding:1rem}
  .doctor-avatar{width:54px;height:54px;font-size:1.5rem}
  .doc-name{font-size:.95rem}
  .doc-info{gap:.5rem}
  .doc-info span{font-size:.78rem}
  .doc-actions{gap:.4rem}
  .btn-rdv,.btn-profil{padding:.4rem .9rem;font-size:.78rem}
}
@media(max-width:480px){
  .page-header h1{font-size:1.5rem}
  .results-header{flex-direction:column;align-items:flex-start}
  .sort-select{width:100%}
  .doc-top{flex-direction:column}
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
    <li><a href="liste_medecins.php" class="active">Médecins</a></li>
    <li><a href="aide.php">Aide</a></li>
    <li><a href="contact.php">Contact</a></li>
  </ul>
  <div class="nav-btns">
    <?php if (isLoggedIn()):
      $profil_url = match(getRole()) { 'medecin'=>'profil_medecin.php','admin'=>'dashboard_admin.php',default=>'profil_patient.php' };
    ?>
      <a href="notifications.php" class="btn-outline">
        🔔<?php if($notif_count>0): ?><span class="notif-badge"><?=$notif_count?></span><?php endif;?>
      </a>
      <a href="<?=$profil_url?>" class="btn-primary">👤 <?=htmlspecialchars($_SESSION['prenom']??'Espace')?></a>
    <?php else: ?>
      <a href="login.php" class="btn-outline">Connexion</a>
      <a href="register_patient.php" class="btn-primary">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ============================================================
     PAGE HEADER
     ============================================================ -->
<div class="page-header">
  <div class="breadcrumb">
    <a href="../index.php">Accueil</a> → <span>Liste des Médecins</span>
  </div>
  <h1>Nos Médecins</h1>
  <p>Trouvez le spécialiste qu'il vous faut parmi nos praticiens qualifiés</p>
</div>

<!-- ============================================================
     LAYOUT PRINCIPAL
     ============================================================ -->
<div class="main-layout">

  <!-- Overlay mobile (ferme le drawer) -->
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR / DRAWER MOBILE -->
  <aside class="sidebar" id="sidebar">

    <!-- Bouton fermer (visible uniquement en mobile dans le drawer) -->
    <div class="sidebar-close" style="display:none" id="sidebarClose">
      <h3>Filtres</h3>
      <button onclick="closeSidebar()">✕</button>
    </div>

    <form method="GET" action="liste_medecins.php" id="filterForm">

      <!-- Recherche par nom -->
      <div class="filter-card">
        <h4>🔍 Recherche</h4>
        <input type="text" name="nom" class="filter-input"
               placeholder="Nom du médecin..."
               value="<?=htmlspecialchars($filtre_nom)?>">
      </div>

      <!-- Spécialités -->
      <div class="filter-card">
        <h4>🏥 Spécialité</h4>
        <?php foreach ($specs_count as $sc): ?>
        <div class="filter-option">
          <input type="checkbox" name="specialite[]"
                 id="spec_<?=md5($sc['specialite'])?>"
                 value="<?=htmlspecialchars($sc['specialite'])?>"
                 <?=in_array($sc['specialite'],$filtre_specs)?'checked':''?>>
          <label for="spec_<?=md5($sc['specialite'])?>"><?=htmlspecialchars($sc['specialite'])?></label>
          <span class="count"><?=$sc['nb']?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Villes -->
      <div class="filter-card">
        <h4>📍 Ville</h4>
        <?php foreach ($villes_count as $vc): ?>
        <div class="filter-option">
          <input type="checkbox" name="ville[]"
                 id="ville_<?=md5($vc['ville'])?>"
                 value="<?=htmlspecialchars($vc['ville'])?>"
                 <?=in_array($vc['ville'],$filtre_villes)?'checked':''?>>
          <label for="ville_<?=md5($vc['ville'])?>"><?=htmlspecialchars($vc['ville'])?></label>
          <span class="count"><?=$vc['nb']?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Disponibilité -->
      <div class="filter-card">
        <h4>📅 Disponibilité</h4>
        <div class="filter-option">
          <input type="radio" name="dispo" id="d0" value="" <?=$filtre_dispo===''?'checked':''?>>
          <label for="d0">Tous</label>
        </div>
        <div class="filter-option">
          <input type="radio" name="dispo" id="d1" value="today" <?=$filtre_dispo==='today'?'checked':''?>>
          <label for="d1">Disponible aujourd'hui</label>
        </div>
        <div class="filter-option">
          <input type="radio" name="dispo" id="d2" value="week" <?=$filtre_dispo==='week'?'checked':''?>>
          <label for="d2">Cette semaine</label>
        </div>

        <!-- Tri caché (synchronisé avec le select des résultats) -->
        <input type="hidden" name="tri" id="hiddenTri" value="<?=htmlspecialchars($tri)?>">

        <button type="submit" class="filter-btn">Appliquer les filtres</button>
        <a href="liste_medecins.php" class="reset-link">✕ Réinitialiser</a>
      </div>

    </form>
  </aside>

  <!-- ============================================================
       ZONE DES RÉSULTATS
       ============================================================ -->
  <div class="results">

    <!-- Bouton ouvrir filtres (mobile) -->
    <button class="btn-filter-mobile" onclick="openSidebar()">⚙️ Filtres & Recherche</button>

    <!-- En-tête des résultats -->
    <div class="results-header">
      <div class="results-count">
        <strong><?=$total?> médecin<?=$total>1?'s':''?></strong> trouvé<?=$total>1?'s':''?>
        <?php if(!empty($filtre_specs)||!empty($filtre_villes)||$filtre_nom!==''||$filtre_dispo!==''): ?>
          <span style="font-size:.8rem;color:var(--orange);margin-left:.4rem">(filtres actifs)</span>
        <?php endif; ?>
      </div>
      <select class="sort-select" onchange="changeTri(this.value)">
        <option value="dispo" <?=$tri==='dispo'?'selected':''?>>Trier par : Disponibilité</option>
        <option value="note"  <?=$tri==='note' ?'selected':''?>>Trier par : Popularité</option>
        <option value="nom"   <?=$tri==='nom'  ?'selected':''?>>Trier par : Nom A-Z</option>
      </select>
    </div>

    <!-- ---- LISTE DES MÉDECINS ---- -->
    <?php if (empty($medecins)): ?>
      <div class="empty-state">
        <p style="font-size:2.5rem;margin-bottom:1rem">🔍</p>
        <h4>Aucun médecin trouvé</h4>
        <p>Essayez d'autres critères ou <a href="liste_medecins.php" style="color:var(--blue)">réinitialisez les filtres</a>.</p>
      </div>
    <?php else: ?>
      <?php foreach ($medecins as $i => $m):
        $c      = getCouleur($m['specialite'], $couleurs);
        $tarif  = getTarif($m['specialite'], $tarifs);
        $avatar = ($i % 2 === 0) ? '👨‍⚕️' : '👩‍⚕️';
        $stars  = min(5, max(3, 3 + floor($m['nb_rdv'] / 10)));
        $stars_html = str_repeat('★',$stars).str_repeat('☆',5-$stars);
        $nb_avis = max(5, $m['nb_rdv'] + rand(3,20));
        $note    = number_format(3.5 + ($stars - 3) * 0.5, 1);
        $duree   = ($m['specialite']==='Pediatrie') ? 20 : (($m['specialite']==='Dermatologie') ? 25 : 30);

        if ($m['dispo_today']) {
          $dispo_class = 'dispo-today'; $dispo_txt = '🟢 Disponible';
        } elseif ($m['dispo_tomorrow']) {
          $dispo_class = 'dispo-tomorrow'; $dispo_txt = '🟡 Demain';
        } else {
          $dispo_class = 'dispo-none'; $dispo_txt = '⚪ Sur RDV';
        }
      ?>
      <div class="doctor-card"
           onclick="window.location='detail_medecin.php?id=<?=$m['id']?>'">

        <!-- Avatar -->
        <div class="doctor-avatar" style="background:<?=$c['grad']?>">
          <?php if(!empty($m['photo']) && file_exists(__DIR__.'/../assets/img/'.$m['photo'])): ?>
            <img src="../assets/img/<?=htmlspecialchars($m['photo'])?>"
                 alt="Dr. <?=htmlspecialchars($m['nom'])?>">
          <?php else: ?>
            <?=$avatar?>
          <?php endif; ?>
        </div>

        <!-- Infos -->
        <div class="doc-main">
          <div class="doc-top">
            <div>
              <span class="doc-name">Dr. <?=htmlspecialchars($m['prenom'].' '.$m['nom'])?></span>
              <span class="doc-spec"
                    style="background:<?=$c['badge_bg']?>;color:<?=$c['badge_c']?>">
                <?=htmlspecialchars($m['specialite'])?>
              </span>
            </div>
            <span class="dispo-badge <?=$dispo_class?>"><?=$dispo_txt?></span>
          </div>

          <div class="doc-stars">
            <?=$stars_html?>
            <span style="color:#94A3B8;font-size:.8rem">
              <?=$note?> (<?=$nb_avis?> avis)
            </span>
          </div>

          <div class="doc-info">
            <span>📍 <?=htmlspecialchars($m['cabinet'].', '.$m['ville'])?></span>
            <span>⏱ <?=$duree?> min / consultation</span>
            <span>💰 <?=$tarif?> FCFA</span>
          </div>

          <div class="doc-actions" onclick="event.stopPropagation()">
            <?php if (isLoggedIn()): ?>
              <a href="prendre_rdv.php?medecin=<?=$m['id']?>"
                 class="btn-rdv"
                 style="background:<?=$c['btn']?>">Prendre RDV</a>
            <?php else: ?>
              <a href="login.php?redirect=prendre_rdv.php?medecin=<?=$m['id']?>"
                 class="btn-rdv"
                 style="background:<?=$c['btn']?>">Prendre RDV</a>
            <?php endif; ?>
            <a href="detail_medecin.php?id=<?=$m['id']?>"
               class="btn-profil"
               style="color:<?=$c['btn']?>;border-color:<?=$c['btn']?>">Voir profil</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- ---- PAGINATION ---- -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <!-- Précédent -->
      <a href="<?=pageUrl($page_courante-1,$params)?>"
         class="page-btn <?=$page_courante<=1?'disabled':''?>">←</a>

      <?php
        // Afficher au max 7 boutons de pages
        $range = 2;
        $start = max(1, $page_courante - $range);
        $end   = min($total_pages, $page_courante + $range);
        if ($start > 1): ?>
          <a href="<?=pageUrl(1,$params)?>" class="page-btn">1</a>
          <?php if ($start > 2): ?><span class="page-btn" style="pointer-events:none">…</span><?php endif; ?>
        <?php endif;
        for ($p = $start; $p <= $end; $p++): ?>
          <a href="<?=pageUrl($p,$params)?>"
             class="page-btn <?=$p===$page_courante?'active':''?>"><?=$p?></a>
        <?php endfor;
        if ($end < $total_pages): ?>
          <?php if ($end < $total_pages - 1): ?><span class="page-btn" style="pointer-events:none">…</span><?php endif; ?>
          <a href="<?=pageUrl($total_pages,$params)?>" class="page-btn"><?=$total_pages?></a>
        <?php endif; ?>

      <!-- Suivant -->
      <a href="<?=pageUrl($page_courante+1,$params)?>"
         class="page-btn <?=$page_courante>=$total_pages?'disabled':''?>">→</a>
    </div>
    <?php endif; ?>

  </div><!-- /results -->
</div><!-- /main-layout -->

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.5rem">Page développée par : <strong>Babacar Dione (E1)</strong> — liste_medecins.php</p>
</footer>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
// --- Menu hamburger ---
function toggleMenu() {
  const nav = document.getElementById('navLinks');
  const icon = document.getElementById('hamburger');
  nav.classList.toggle('open');
  const s = icon.querySelectorAll('span');
  if (nav.classList.contains('open')) {
    s[0].style.transform='rotate(45deg) translate(5px,5px)';
    s[1].style.opacity='0';
    s[2].style.transform='rotate(-45deg) translate(5px,-5px)';
  } else {
    s.forEach(x=>{x.style.transform='';x.style.opacity='';});
  }
}
document.addEventListener('click', function(e) {
  const nav=document.getElementById('navLinks');
  const icon=document.getElementById('hamburger');
  if(!nav.contains(e.target)&&!icon.contains(e.target)){
    nav.classList.remove('open');
    icon.querySelectorAll('span').forEach(x=>{x.style.transform='';x.style.opacity='';});
  }
});

// --- Drawer sidebar mobile ---
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('overlay').classList.add('open');
  document.getElementById('sidebarClose').style.display='flex';
  document.body.style.overflow='hidden';
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
  document.body.style.overflow='';
}
// Afficher le bouton fermer sur mobile seulement
if(window.innerWidth <= 768) {
  document.getElementById('sidebarClose').style.display='flex';
}

// --- Changement de tri (synchronise avec le champ caché) ---
function changeTri(val) {
  document.getElementById('hiddenTri').value = val;
  document.getElementById('filterForm').submit();
}

// --- Soumission auto du formulaire au changement de checkbox ---
document.querySelectorAll('#filterForm input[type=checkbox], #filterForm input[type=radio]')
  .forEach(function(el) {
    el.addEventListener('change', function() {
      // Reset pagination à la page 1 lors d'un nouveau filtre
      const form = document.getElementById('filterForm');
      let pageInput = form.querySelector('input[name="page"]');
      if (!pageInput) {
        pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        form.appendChild(pageInput);
      }
      pageInput.value = 1;
      form.submit();
    });
  });
</script>
</body>
</html>