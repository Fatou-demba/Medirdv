<?php
// ================================================================
// index.php — Page d'accueil dynamique MediRDV
// Responsable : Babacar Dione (Etudiant 1) — Chef de Projet
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/includes/connexion.php';
require_once __DIR__ . '/includes/fonctions.php';

// ================================================================
// 1. STATS DYNAMIQUES (compteurs pour la section stats)
// ================================================================
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) FROM medecins WHERE statut = 'valide'");
$stats['medecins'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif'");
$stats['patients'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE statut IN ('confirme','termine')");
$stats['rdv'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT specialite) FROM medecins WHERE statut = 'valide'");
$stats['specialites'] = (int) $stmt->fetchColumn();

// ================================================================
// 2. LISTE DES SPÉCIALITÉS ET VILLES (pour le menu de recherche)
// ================================================================
$stmt = $pdo->query("SELECT DISTINCT specialite FROM medecins WHERE statut = 'valide' ORDER BY specialite ASC");
$specialites = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("SELECT DISTINCT ville FROM medecins WHERE statut = 'valide' ORDER BY ville ASC");
$villes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ================================================================
// 3. RECHERCHE / FILTRAGE
// ================================================================
$filtre_specialite = isset($_GET['specialite']) ? trim($_GET['specialite']) : '';
$filtre_ville      = isset($_GET['ville'])      ? trim($_GET['ville'])      : '';
$filtre_nom        = isset($_GET['nom'])         ? trim($_GET['nom'])        : '';

// Construction de la requête dynamique
$where  = ["m.statut = 'valide'"];
$params = [];

if ($filtre_specialite !== '') {
    $where[]  = "m.specialite = :specialite";
    $params[':specialite'] = $filtre_specialite;
}
if ($filtre_ville !== '') {
    $where[]  = "m.ville = :ville";
    $params[':ville'] = $filtre_ville;
}
if ($filtre_nom !== '') {
    $where[]  = "(m.nom LIKE :nom OR m.prenom LIKE :nom)";
    $params[':nom'] = '%' . $filtre_nom . '%';
}

$where_sql = implode(' AND ', $where);

// ================================================================
// 4. MÉDECINS DISPONIBLES AUJOURD'HUI (+ résultats de recherche)
// ================================================================
// On récupère les médecins avec leur prochain créneau dispo aujourd'hui ou demain
$today_day = strtolower(date('l', strtotime('today')));
// Mapping anglais -> français pour jour_semaine
$jours_fr = [
    'monday'    => 'lundi',
    'tuesday'   => 'mardi',
    'wednesday' => 'mercredi',
    'thursday'  => 'jeudi',
    'friday'    => 'vendredi',
    'saturday'  => 'samedi',
    'sunday'    => 'dimanche',
];
$today_fr    = $jours_fr[$today_day]    ?? '';
$tomorrow_fr = $jours_fr[strtolower(date('l', strtotime('tomorrow')))] ?? '';

$sql = "
    SELECT
        m.id,
        m.nom,
        m.prenom,
        m.specialite,
        m.cabinet,
        m.ville,
        m.photo,
        m.biographie,
        m.telephone,
        -- Disponible aujourd'hui ?
        MAX(CASE WHEN d.jour_semaine = :today AND d.actif = 1 THEN 1 ELSE 0 END) AS dispo_today,
        -- Disponible demain ?
        MAX(CASE WHEN d.jour_semaine = :tomorrow AND d.actif = 1 THEN 1 ELSE 0 END) AS dispo_tomorrow,
        -- Nombre de RDV confirmés (pour l'évaluation fictive)
        COUNT(DISTINCT r.id) AS nb_rdv
    FROM medecins m
    LEFT JOIN disponibilites d ON d.id_medecin = m.id
    LEFT JOIN rendezvous r ON r.id_medecin = m.id AND r.statut IN ('confirme','termine')
    WHERE $where_sql
    GROUP BY m.id
    ORDER BY dispo_today DESC, dispo_tomorrow DESC, nb_rdv DESC
    LIMIT 8
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':today',    $today_fr);
$stmt->bindValue(':tomorrow', $tomorrow_fr);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 5. COULEURS PAR SPÉCIALITÉ (cohérence visuelle identique au HTML)
// ================================================================
$couleurs_spec = [
    'Cardiologie'    => ['grad' => 'linear-gradient(135deg,#0077B6,#023E8A)', 'badge_bg' => '#EFF6FF', 'badge_c' => '#0077B6', 'btn' => '#0077B6'],
    'Pediatrie'      => ['grad' => 'linear-gradient(135deg,#7209B7,#B5179E)', 'badge_bg' => '#FDF4FF', 'badge_c' => '#7209B7', 'btn' => '#7209B7'],
    'Dermatologie'   => ['grad' => 'linear-gradient(135deg,#F77F00,#D62828)', 'badge_bg' => '#FFF7ED', 'badge_c' => '#F77F00', 'btn' => '#F77F00'],
    'Gynecologie'    => ['grad' => 'linear-gradient(135deg,#006D77,#2DC653)', 'badge_bg' => '#F0FDF4', 'badge_c' => '#2DC653', 'btn' => '#006D77'],
    'Neurologie'     => ['grad' => 'linear-gradient(135deg,#3A0CA3,#7B2FBE)', 'badge_bg' => '#EEF0FF', 'badge_c' => '#3A0CA3', 'btn' => '#3A0CA3'],
    'Ophtalmologie'  => ['grad' => 'linear-gradient(135deg,#0096C7,#48CAE4)', 'badge_bg' => '#E0F7FA', 'badge_c' => '#0096C7', 'btn' => '#0096C7'],
    'Stomatologie'   => ['grad' => 'linear-gradient(135deg,#E63946,#C1121F)', 'badge_bg' => '#FFF0F0', 'badge_c' => '#E63946', 'btn' => '#E63946'],
    'default'        => ['grad' => 'linear-gradient(135deg,#0077B6,#023E8A)', 'badge_bg' => '#EFF6FF', 'badge_c' => '#0077B6', 'btn' => '#0077B6'],
];

function getCouleur($specialite, $couleurs) {
    return $couleurs[$specialite] ?? $couleurs['default'];
}

// Icônes émoji par genre (on alterne si pas de photo)
$avatars = ['👨‍⚕️', '👩‍⚕️'];

// ================================================================
// 6. TARIFS FICTIFS PAR SPÉCIALITÉ (affiché si pas de tarif en BDD)
// ================================================================
$tarifs = [
    'Cardiologie'   => '10 000',
    'Pediatrie'     => '8 000',
    'Dermatologie'  => '12 000',
    'Gynecologie'   => '9 000',
    'Neurologie'    => '15 000',
    'Ophtalmologie' => '10 000',
    'Stomatologie'  => '7 000',
    'default'       => '10 000',
];
function getTarif($specialite, $tarifs) {
    return $tarifs[$specialite] ?? $tarifs['default'];
}

// ================================================================
// 7. NOTIFICATIONS non lues (si connecté)
// ================================================================
$notif_count = 0;
if (isLoggedIn()) {
    $role_sess = getRole();
    $type_user = ($role_sess === 'medecin') ? 'medecin' : 'patient';
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire = :id AND type_user = :type AND lu = 0");
    $stmt2->execute([':id' => $_SESSION['user_id'], ':type' => $type_user]);
    $notif_count = (int) $stmt2->fetchColumn();
}

// Détecter si une recherche a été effectuée
$recherche_active = ($filtre_specialite !== '' || $filtre_ville !== '' || $filtre_nom !== '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Accueil</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ============================================================
   RESET & VARIABLES — identique au fichier HTML de référence
   ============================================================ */
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--orange:#F77F00;
  --dark:#03045E;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0;
}
body{font-family:'DM Sans',sans-serif;background:var(--light);color:#1E293B}

/* ============================================================
   NAVBAR
   ============================================================ */
nav{
  background:var(--dark);padding:0 5%;height:68px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100;
  box-shadow:0 2px 20px rgba(3,4,94,.4);
}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.5rem;color:var(--white);letter-spacing:-1px;text-decoration:none}
.nav-logo span{color:var(--green)}
.nav-links{display:flex;gap:2rem;list-style:none}
.nav-links a{color:#CBD5E1;text-decoration:none;font-size:.9rem;font-weight:500;transition:.2s}
.nav-links a:hover{color:var(--white)}
.nav-links a.active{color:var(--white);border-bottom:2px solid var(--green);padding-bottom:2px}
.nav-btns{display:flex;gap:.8rem;align-items:center}
.btn-outline{padding:.5rem 1.2rem;border:1.5px solid #CBD5E1;color:#CBD5E1;border-radius:8px;font-size:.85rem;cursor:pointer;background:transparent;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
.btn-outline:hover{border-color:var(--white);color:var(--white)}
.btn-primary{padding:.5rem 1.4rem;background:var(--green);color:var(--white);border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none;display:inline-flex;align-items:center}
.btn-primary:hover{background:#25B347;transform:translateY(-1px)}
.notif-badge{
  display:inline-flex;align-items:center;justify-content:center;
  background:var(--orange);color:var(--white);border-radius:50%;
  width:18px;height:18px;font-size:.65rem;font-weight:700;
  margin-left:.3rem;
}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ============================================================
   HERO
   ============================================================ */
.hero{
  background:linear-gradient(135deg,var(--dark) 0%,var(--blue2) 60%,#0096C7 100%);
  padding:80px 5% 60px;position:relative;overflow:hidden;
}
.hero::before{
  content:'';position:absolute;top:-80px;right:-80px;width:500px;height:500px;
  background:radial-gradient(circle,rgba(45,198,83,.15) 0%,transparent 70%);
  border-radius:50%;
}
.hero::after{
  content:'';position:absolute;bottom:-100px;left:-60px;width:400px;height:400px;
  background:radial-gradient(circle,rgba(0,150,199,.2) 0%,transparent 70%);
  border-radius:50%;
}
.hero-inner{max-width:640px;position:relative;z-index:1}
.hero-badge{
  display:inline-flex;align-items:center;gap:.5rem;
  background:rgba(45,198,83,.15);border:1px solid rgba(45,198,83,.3);
  color:var(--green);padding:.4rem 1rem;border-radius:20px;font-size:.8rem;font-weight:600;
  margin-bottom:1.5rem;
}
.hero h1{
  font-family:'Sora',sans-serif;font-size:3rem;font-weight:800;
  color:var(--white);line-height:1.15;margin-bottom:1.2rem;
}
.hero h1 em{color:var(--green);font-style:normal}
.hero p{color:#CBD5E1;font-size:1.05rem;line-height:1.7;margin-bottom:2rem;max-width:520px}
.hero-btns{display:flex;gap:1rem;flex-wrap:wrap}
.hero-btn-main{
  padding:.85rem 2rem;background:var(--green);color:var(--white);
  border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;
  box-shadow:0 4px 20px rgba(45,198,83,.4);transition:.2s;text-decoration:none;display:inline-block;
}
.hero-btn-main:hover{transform:translateY(-2px);box-shadow:0 6px 25px rgba(45,198,83,.5);color:var(--white)}
.hero-btn-sec{
  padding:.85rem 2rem;background:transparent;color:var(--white);
  border:2px solid rgba(255,255,255,.3);border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;
  transition:.2s;text-decoration:none;display:inline-block;
}
.hero-btn-sec:hover{border-color:var(--white);background:rgba(255,255,255,.08);color:var(--white)}

/* ============================================================
   STATS
   ============================================================ */
.stats{
  background:var(--white);padding:28px 5%;
  display:flex;justify-content:center;gap:4rem;flex-wrap:wrap;
  border-bottom:1px solid var(--lgray);
  box-shadow:0 4px 20px rgba(0,0,0,.06);
}
.stat{text-align:center}
.stat-num{font-family:'Sora',sans-serif;font-size:2rem;font-weight:800;color:var(--blue2)}
.stat-num span{color:var(--green)}
.stat-label{font-size:.82rem;color:var(--gray);font-weight:500;margin-top:.2rem}

/* ============================================================
   RECHERCHE
   ============================================================ */
.search-section{padding:50px 5% 30px;max-width:900px;margin:0 auto}
.search-section h2{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:700;color:var(--dark);margin-bottom:1.2rem;text-align:center}
.search-box{
  background:var(--white);border-radius:16px;padding:1.2rem;
  box-shadow:0 4px 30px rgba(0,0,0,.1);display:flex;gap:1rem;flex-wrap:wrap;
  border:1px solid var(--lgray);
}
.search-box select,.search-box input{
  flex:1;min-width:150px;padding:.7rem 1rem;border:1.5px solid var(--lgray);
  border-radius:8px;font-size:.9rem;font-family:'DM Sans',sans-serif;
  color:#334155;outline:none;transition:.2s;background:var(--light);
}
.search-box select:focus,.search-box input:focus{border-color:var(--blue);background:var(--white)}
.search-btn{
  padding:.7rem 2rem;background:var(--blue);color:var(--white);
  border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;
  white-space:nowrap;transition:.2s;
}
.search-btn:hover{background:var(--blue2)}
.reset-btn{
  padding:.7rem 1.2rem;background:transparent;color:var(--gray);
  border:1.5px solid var(--lgray);border-radius:8px;font-size:.9rem;cursor:pointer;
  white-space:nowrap;transition:.2s;
}
.reset-btn:hover{border-color:var(--gray);color:#334155}

/* ============================================================
   GRILLE MÉDECINS
   ============================================================ */
.doctors-section{padding:20px 5% 60px}
.section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:.5rem}
.section-title h3{font-family:'Sora',sans-serif;font-size:1.3rem;font-weight:700;color:var(--dark)}
.see-all{color:var(--blue);font-size:.9rem;font-weight:600;text-decoration:none}
.see-all:hover{color:var(--blue2)}
.doctors-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.2rem}

.doctor-card{
  background:var(--white);border-radius:16px;padding:1.5rem;
  border:1px solid var(--lgray);transition:.25s;cursor:pointer;
  box-shadow:0 2px 12px rgba(0,0,0,.06);
}
.doctor-card:hover{transform:translateY(-4px);box-shadow:0 8px 30px rgba(0,0,0,.12);border-color:#B8D4FF}
.doctor-head{display:flex;gap:1rem;align-items:center;margin-bottom:1rem}
.doctor-avatar{
  width:56px;height:56px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;
  overflow:hidden;
}
.doctor-avatar img{width:100%;height:100%;object-fit:cover;border-radius:12px}
.doctor-info h4{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:#1E293B}
.doctor-spec{
  display:inline-block;font-size:.75rem;font-weight:600;
  padding:.2rem .6rem;border-radius:6px;margin-top:.25rem;
}
.doctor-details{font-size:.85rem;color:var(--gray);line-height:1.8;margin-bottom:1rem}
.doctor-details span{display:flex;align-items:center;gap:.4rem}
.stars{color:#FBBF24;font-size:.85rem;margin-bottom:.2rem}
.dispo-badge{
  display:inline-flex;align-items:center;gap:.4rem;
  font-size:.78rem;font-weight:600;
  padding:.3rem .7rem;border-radius:6px;margin-bottom:.8rem;
}
.dispo-today{background:#F0FDF4;color:var(--green);border:1px solid #BBF7D0}
.dispo-tomorrow{background:#FFF7ED;color:var(--orange);border:1px solid #FED7AA}
.dispo-none{background:#F1F5F9;color:var(--gray);border:1px solid var(--lgray)}
.card-btn{
  width:100%;padding:.6rem;color:var(--white);
  border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;
  transition:.2s;font-family:'DM Sans',sans-serif;text-decoration:none;
  display:block;text-align:center;
}
.card-btn:hover{opacity:.88;color:var(--white);transform:translateY(-1px)}

/* Message vide */
.empty-state{
  text-align:center;padding:60px 20px;color:var(--gray);
  grid-column:1/-1;
}
.empty-state h4{font-size:1.1rem;font-weight:600;margin-bottom:.5rem;color:#334155}
.empty-state p{font-size:.9rem}

/* ============================================================
   FOOTER
   ============================================================ */
footer{background:var(--dark);color:#94A3B8;padding:40px 5%;text-align:center;font-size:.85rem}
footer strong{color:var(--white)}

/* ============================================================
   RESPONSIVE MOBILE
   ============================================================ */
@media(max-width:900px){
  .stats{gap:2rem}
  .hero h1{font-size:2.2rem}
}
@media(max-width:768px){
  /* Nav mobile */
  .nav-links{
    display:none;position:fixed;top:68px;left:0;right:0;
    background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;
    box-shadow:0 8px 20px rgba(0,0,0,.3);
    border-top:1px solid rgba(255,255,255,.08);
    z-index:99;
  }
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06);width:100%}
  .nav-btns{gap:.5rem}
  .btn-outline{padding:.45rem .9rem;font-size:.8rem}
  .btn-primary{padding:.45rem 1rem;font-size:.8rem}
  .nav-hamburger{display:flex}

  /* Hero */
  .hero{padding:50px 5% 40px}
  .hero h1{font-size:1.8rem}
  .hero p{font-size:.95rem}
  .hero-btn-main,.hero-btn-sec{padding:.75rem 1.4rem;font-size:.9rem;width:100%;text-align:center}
  .hero-btns{flex-direction:column;gap:.75rem}

  /* Stats */
  .stats{padding:20px 5%;gap:1.5rem}
  .stat-num{font-size:1.6rem}

  /* Search */
  .search-section{padding:30px 5% 20px}
  .search-section h2{font-size:1.3rem}
  .search-box{flex-direction:column;gap:.75rem}
  .search-box select,.search-box input{min-width:100%;width:100%}
  .search-btn,.reset-btn{width:100%}

  /* Grille */
  .doctors-section{padding:15px 5% 40px}
  .doctors-grid{grid-template-columns:1fr}
  .section-title h3{font-size:1.1rem}
}
@media(max-width:400px){
  nav{padding:0 4%}
  .nav-logo{font-size:1.3rem}
  .hero h1{font-size:1.55rem}
}
</style>
</head>
<body>

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav>
  <a href="index.php" class="nav-logo">Medi<span>RDV</span></a>

  <!-- Hamburger (mobile) -->
  <button class="nav-hamburger" id="hamburger" aria-label="Menu" onclick="toggleMenu()">
    <span></span><span></span><span></span>
  </button>

  <ul class="nav-links" id="navLinks">
    <li><a href="index.php" class="active">Accueil</a></li>
    <li><a href="pages/liste_medecins.php">Médecins</a></li>
    <li><a href="pages/aide.php">Aide</a></li>
    <li><a href="pages/contact.php">Contact</a></li>
  </ul>

  <div class="nav-btns">
    <?php if (isLoggedIn()): ?>
      <?php
        $role_sess = getRole();
        $profil_url = match($role_sess) {
            'medecin' => 'pages/profil_medecin.php',
            'admin'   => 'pages/dashboard_admin.php',
            default   => 'pages/profil_patient.php',
        };
      ?>
      <a href="pages/notifications.php" class="btn-outline">
        🔔 Notifications
        <?php if ($notif_count > 0): ?>
          <span class="notif-badge"><?= $notif_count ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= $profil_url ?>" class="btn-primary">
        👤 <?= htmlspecialchars($_SESSION['prenom'] ?? 'Mon espace') ?>
      </a>
    <?php else: ?>
      <a href="pages/login.php" class="btn-outline">Connexion</a>
      <a href="pages/register_patient.php" class="btn-primary">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ============================================================
     HERO
     ============================================================ -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-badge">🏥 Plateforme de Santé Numérique — UNCHK</div>
    <h1>Prenez <em>rendez-vous</em> avec votre médecin en ligne</h1>
    <p>Plus besoin d'attendre au téléphone. Trouvez le médecin idéal, consultez ses disponibilités et réservez votre RDV en quelques clics.</p>
    <div class="hero-btns">
      <?php if (isLoggedIn()): ?>
        <a href="pages/prendre_rdv.php" class="hero-btn-main">Prendre un RDV →</a>
        <a href="pages/liste_medecins.php" class="hero-btn-sec">Trouver un médecin</a>
      <?php else: ?>
        <a href="pages/register_patient.php" class="hero-btn-main">Prendre un RDV →</a>
        <a href="pages/liste_medecins.php" class="hero-btn-sec">Trouver un médecin</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     STATS DYNAMIQUES
     ============================================================ -->
<div class="stats">
  <div class="stat">
    <div class="stat-num"><?= $stats['medecins'] > 0 ? $stats['medecins'] : '120' ?><span>+</span></div>
    <div class="stat-label">Médecins</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= $stats['patients'] > 0 ? number_format($stats['patients'], 0, ',', '.') : '3<span>.</span>500' ?><span>+</span></div>
    <div class="stat-label">Patients</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= $stats['rdv'] > 0 ? number_format($stats['rdv'], 0, ',', '.') : '8<span>.</span>200' ?><span>+</span></div>
    <div class="stat-label">RDV réalisés</div>
  </div>
  <div class="stat">
    <div class="stat-num"><?= $stats['specialites'] > 0 ? $stats['specialites'] : '15' ?><span>+</span></div>
    <div class="stat-label">Spécialités</div>
  </div>
</div>

<!-- ============================================================
     BARRE DE RECHERCHE
     ============================================================ -->
<div class="search-section">
  <h2>Trouvez le médecin idéal</h2>
  <form method="GET" action="index.php">
    <div class="search-box">
      <!-- Spécialités (issues de la BDD) -->
      <select name="specialite">
        <option value="">Toutes les spécialités</option>
        <?php foreach ($specialites as $spec): ?>
          <option value="<?= htmlspecialchars($spec) ?>"
            <?= ($filtre_specialite === $spec) ? 'selected' : '' ?>>
            <?= htmlspecialchars($spec) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Villes (issues de la BDD) -->
      <select name="ville">
        <option value="">Toutes les villes</option>
        <?php foreach ($villes as $ville): ?>
          <option value="<?= htmlspecialchars($ville) ?>"
            <?= ($filtre_ville === $ville) ? 'selected' : '' ?>>
            <?= htmlspecialchars($ville) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Nom -->
      <input type="text" name="nom" placeholder="Nom du médecin..."
             value="<?= htmlspecialchars($filtre_nom) ?>">

      <button type="submit" class="search-btn">🔍 Rechercher</button>

      <?php if ($recherche_active): ?>
        <a href="index.php" class="reset-btn">✕ Réinitialiser</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ============================================================
     GRILLE DES MÉDECINS
     ============================================================ -->
<div class="doctors-section">
  <div class="section-title">
    <h3>
      <?php if ($recherche_active): ?>
        Résultats de votre recherche
        <span style="font-size:.85rem;color:var(--gray);font-weight:500;margin-left:.5rem">
          (<?= count($medecins) ?> médecin<?= count($medecins) > 1 ? 's' : '' ?> trouvé<?= count($medecins) > 1 ? 's' : '' ?>)
        </span>
      <?php else: ?>
        Médecins disponibles aujourd'hui
      <?php endif; ?>
    </h3>
    <a class="see-all" href="pages/liste_medecins.php">Voir tous →</a>
  </div>

  <div class="doctors-grid">
    <?php if (empty($medecins)): ?>
      <div class="empty-state">
        <h4>Aucun médecin trouvé</h4>
        <p>Essayez d'autres critères de recherche ou <a href="index.php" style="color:var(--blue)">réinitialisez les filtres</a>.</p>
      </div>
    <?php else: ?>
      <?php foreach ($medecins as $i => $m):
        $couleur = getCouleur($m['specialite'], $couleurs_spec);
        $tarif   = getTarif($m['specialite'], $tarifs);
        $avatar  = ($i % 2 === 0) ? '👨‍⚕️' : '👩‍⚕️';

        // Badge disponibilité
        if ($m['dispo_today']) {
          $dispo_class = 'dispo-today';
          $dispo_txt   = '🟢 Disponible aujourd\'hui';
        } elseif ($m['dispo_tomorrow']) {
          $dispo_class = 'dispo-tomorrow';
          $dispo_txt   = '🟡 Demain matin';
        } else {
          $dispo_class = 'dispo-none';
          $dispo_txt   = '⚪ Sur rendez-vous';
        }

        // Étoiles fictives basées sur le nb de RDV
        $stars = min(5, max(3, 3 + floor($m['nb_rdv'] / 10)));
        $stars_html = str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
        $nb_avis = max(5, $m['nb_rdv'] + rand(3, 20));
      ?>
      <div class="doctor-card" onclick="window.location='pages/detail_medecin.php?id=<?= $m['id'] ?>'">

        <div class="doctor-head">
          <!-- Avatar : photo ou emoji -->
          <div class="doctor-avatar" style="background:<?= $couleur['grad'] ?>">
            <?php if (!empty($m['photo']) && file_exists(__DIR__ . '/assets/img/' . $m['photo'])): ?>
              <img src="assets/img/<?= htmlspecialchars($m['photo']) ?>" alt="Dr. <?= htmlspecialchars($m['nom']) ?>">
            <?php else: ?>
              <?= $avatar ?>
            <?php endif; ?>
          </div>

          <div class="doctor-info">
            <div class="stars">
              <?= $stars_html ?>
              <span style="color:#94A3B8;font-size:.8rem">(<?= $nb_avis ?>)</span>
            </div>
            <h4>Dr. <?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></h4>
            <span class="doctor-spec"
                  style="background:<?= $couleur['badge_bg'] ?>;color:<?= $couleur['badge_c'] ?>">
              <?= htmlspecialchars($m['specialite']) ?>
            </span>
          </div>
        </div>

        <!-- Badge dispo -->
        <div class="dispo-badge <?= $dispo_class ?>"><?= $dispo_txt ?></div>

        <!-- Détails -->
        <div class="doctor-details">
          <span>📍 <?= htmlspecialchars($m['cabinet'] . ', ' . $m['ville']) ?></span>
          <span>⏱ Durée : 30 min</span>
          <span>💰 Consultation : <?= $tarif ?> FCFA</span>
        </div>

        <!-- Bouton CTA -->
        <?php if (isLoggedIn()): ?>
          <a href="pages/prendre_rdv.php?medecin=<?= $m['id'] ?>"
             class="card-btn"
             style="background:<?= $couleur['btn'] ?>"
             onclick="event.stopPropagation()">
            Prendre RDV
          </a>
        <?php else: ?>
          <a href="pages/login.php?redirect=prendre_rdv.php?medecin=<?= $m['id'] ?>"
             class="card-btn"
             style="background:<?= $couleur['btn'] ?>"
             onclick="event.stopPropagation()">
            Prendre RDV
          </a>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.5rem">
    Développé par : <strong>Groupe 1 — L3 IDA</strong> |
    Chef de projet : <strong>Babacar Dione (E1)</strong>
  </p>
</footer>

<!-- ============================================================
     JAVASCRIPT — Menu mobile + comportement formulaire
     ============================================================ -->
<script>
// --- Menu hamburger mobile ---
function toggleMenu() {
  const nav  = document.getElementById('navLinks');
  const icon = document.getElementById('hamburger');
  nav.classList.toggle('open');
  // Animation hamburger → X
  const spans = icon.querySelectorAll('span');
  if (nav.classList.contains('open')) {
    spans[0].style.transform = 'rotate(45deg) translate(5px,5px)';
    spans[1].style.opacity   = '0';
    spans[2].style.transform = 'rotate(-45deg) translate(5px,-5px)';
  } else {
    spans[0].style.transform = '';
    spans[1].style.opacity   = '';
    spans[2].style.transform = '';
  }
}

// Fermer menu si clic ailleurs
document.addEventListener('click', function(e) {
  const nav  = document.getElementById('navLinks');
  const icon = document.getElementById('hamburger');
  if (!nav.contains(e.target) && !icon.contains(e.target)) {
    nav.classList.remove('open');
    icon.querySelectorAll('span').forEach(s => { s.style.transform=''; s.style.opacity=''; });
  }
});

// --- Scroll fluide sur le CTA hero (si même page) ---
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', function(e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) target.scrollIntoView({ behavior: 'smooth' });
  });
});
</script>
</body>
</html>