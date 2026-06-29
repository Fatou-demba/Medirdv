<?php
// ================================================================
// historique_patient.php — Historique des consultations patient
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php');
}

$patient_id = $_SESSION['user_id'];

// Récupérer les infos patient
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    redirect('login.php');
}

// ================================================================
// STATISTIQUES
// ================================================================
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE id_patient = $patient_id")->fetchColumn(),
    'medecins' => $pdo->query("SELECT COUNT(DISTINCT id_medecin) FROM consultations WHERE id_patient = $patient_id")->fetchColumn(),
    'ordonnances' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE id_patient = $patient_id AND ordonnance IS NOT NULL AND ordonnance != ''")->fetchColumn(),
    'annee' => date('Y'),
];

// ================================================================
// FILTRES ET RÉCUPÉRATION
// ================================================================
$filtre_medecin = $_GET['medecin'] ?? '';
$filtre_specialite = $_GET['specialite'] ?? '';
$filtre_annee = $_GET['annee'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "
    SELECT c.*, 
           m.nom AS m_nom, m.prenom AS m_prenom, m.specialite, m.cabinet, m.ville,
           r.date_rdv, r.heure_rdv
    FROM consultations c
    JOIN medecins m ON m.id = c.id_medecin
    JOIN rendezvous r ON r.id = c.id_rendezvous
    WHERE c.id_patient = :patient_id
";
$params = [':patient_id' => $patient_id];

if (!empty($filtre_medecin)) {
    $sql .= " AND c.id_medecin = :medecin";
    $params[':medecin'] = $filtre_medecin;
}
if (!empty($filtre_specialite)) {
    $sql .= " AND m.specialite = :specialite";
    $params[':specialite'] = $filtre_specialite;
}
if (!empty($filtre_annee)) {
    $sql .= " AND YEAR(c.date_consultation) = :annee";
    $params[':annee'] = $filtre_annee;
}

$sql .= " ORDER BY c.date_consultation DESC LIMIT :limit OFFSET :offset";

// Compter le total
$count_sql = str_replace("SELECT c.*, m.nom AS m_nom, m.prenom AS m_prenom, m.specialite, m.cabinet, m.ville, r.date_rdv, r.heure_rdv", "SELECT COUNT(*)", $sql);
$count_sql = str_replace("ORDER BY c.date_consultation DESC LIMIT :limit OFFSET :offset", "", $count_sql);

$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total = $count_stmt->fetchColumn();
$total_pages = ceil($total / $limit);

// Récupérer les données
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LISTE DES MÉDECINS POUR FILTRES
// ================================================================
$medecins = $pdo->query("
    SELECT DISTINCT m.id, m.nom, m.prenom, m.specialite
    FROM consultations c
    JOIN medecins m ON m.id = c.id_medecin
    WHERE c.id_patient = $patient_id
    ORDER BY m.nom
")->fetchAll();

// Statistiques par médecin
$medecins_stats = $pdo->query("
    SELECT m.id, m.nom, m.prenom, COUNT(*) AS nb
    FROM consultations c
    JOIN medecins m ON m.id = c.id_medecin
    WHERE c.id_patient = $patient_id
    GROUP BY m.id
    ORDER BY nb DESC
")->fetchAll();

// ================================================================
// HELPERS
// ================================================================
function formatDate($date) {
    if (!$date) return '—';
    $dt = new DateTime($date);
    $mois = ['January'=>'Janv','February'=>'Févr','March'=>'Mars','April'=>'Avr',
             'May'=>'Mai','June'=>'Juin','July'=>'Juil','August'=>'Août',
             'September'=>'Sept','October'=>'Oct','November'=>'Nov','December'=>'Déc'];
    return strtoupper($dt->format('d')) . ' ' . $mois[$dt->format('F')] . ' ' . $dt->format('Y');
}

function formatDateShort($date) {
    if (!$date) return '—';
    $dt = new DateTime($date);
    $mois = ['January'=>'Janv','February'=>'Févr','March'=>'Mars','April'=>'Avr',
             'May'=>'Mai','June'=>'Juin','July'=>'Juil','August'=>'Août',
             'September'=>'Sept','October'=>'Oct','November'=>'Nov','December'=>'Déc'];
    return $dt->format('d') . ' ' . $mois[$dt->format('F')];
}

function getInitiales($prenom, $nom) {
    return strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));
}

function getCouleurSpecialite($spec) {
    $couleurs = [
        'Cardiologie' => ['bg' => '#EFF6FF', 'color' => '#0077B6'],
        'Pédiatrie' => ['bg' => '#FDF4FF', 'color' => '#7209B7'],
        'Dermatologie' => ['bg' => '#FFF7ED', 'color' => '#F77F00'],
        'Gynécologie' => ['bg' => '#F0FDF4', 'color' => '#006D77'],
        'Neurologie' => ['bg' => '#EEF0FF', 'color' => '#3A0CA3'],
        'Ophtalmologie' => ['bg' => '#F0FDFA', 'color' => '#0D9488'],
        'ORL' => ['bg' => '#F5F3FF', 'color' => '#7C3AED'],
        'Urologie' => ['bg' => '#EFF6FF', 'color' => '#2563EB'],
        'Psychiatrie' => ['bg' => '#FDF2F8', 'color' => '#BE185D'],
        'Dentiste' => ['bg' => '#ECFDF5', 'color' => '#059669'],
    ];
    return $couleurs[$spec] ?? ['bg' => '#F1F5F9', 'color' => '#64748B'];
}

$initiales = getInitiales($patient['prenom'], $patient['nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Historique Consultations</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--teal:#006D77;--purple:#7209B7;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .nav-links a.active{color:#fff;border-bottom:2px solid var(--green);padding-bottom:2px}
        .avatar{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;overflow:hidden;text-decoration:none}
        .avatar img{width:100%;height:100%;object-fit:cover}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== PAGE HEADER ===== */
        .page-hdr{background:linear-gradient(135deg,var(--dark),var(--blue));padding:1.8rem 5%;color:#fff}
        .breadcrumb{font-size:.78rem;color:#94A3B8;margin-bottom:.5rem}
        .breadcrumb span{color:var(--green)}
        .hdr-inner{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem}
        .page-hdr h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
        .page-hdr p{color:#CBD5E1;font-size:.85rem;margin-top:.3rem}
        .hdr-btn{padding:.55rem 1.2rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}
        .hdr-btn.green{background:var(--green);border-color:var(--green)}
        .hdr-btn.green:hover{background:#25B347}

        /* ===== STATS ===== */
        .stats-bar{background:#fff;border-bottom:1px solid var(--lgray);padding:.9rem 5%;display:flex;gap:1.5rem;flex-wrap:wrap}
        .st{display:flex;align-items:center;gap:.7rem;background:var(--light);border-radius:10px;padding:.55rem 1rem}
        .st-num{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800}
        .st-lbl{font-size:.72rem;color:var(--gray);line-height:1.3}

        /* ===== MAIN ===== */
        .main{display:flex;gap:1.5rem;padding:1.5rem 5%;max-width:1050px;margin:0 auto}

        /* ===== SIDEBAR ===== */
        .sidebar{width:240px;flex-shrink:0}
        .s-card{background:#fff;border-radius:12px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .s-card h5{font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:var(--dark);margin-bottom:.8rem;padding-bottom:.4rem;border-bottom:1px solid var(--lgray)}
        .filter-group{margin-bottom:.8rem}
        .filter-group label{display:block;font-size:.78rem;font-weight:600;color:#334155;margin-bottom:.35rem}
        .filter-group input,.filter-group select{width:100%;padding:.55rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.82rem;outline:none;font-family:'DM Sans',sans-serif}
        .filter-group input:focus,.filter-group select:focus{border-color:var(--blue)}
        .filter-btn{width:100%;padding:.6rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.83rem;font-weight:700;cursor:pointer;margin-top:.2rem}
        .filter-btn:hover{background:var(--dark)}
        .reset-btn{width:100%;padding:.5rem;background:transparent;color:var(--gray);border:1px solid var(--lgray);border-radius:8px;font-size:.8rem;cursor:pointer;margin-top:.4rem}
        .reset-btn:hover{border-color:var(--blue);color:var(--blue)}

        /* ===== TIMELINE ===== */
        .timeline-wrap{flex:1}
        .t-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:.6rem}
        .t-header h3{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark)}
        .t-sort{padding:.4rem .8rem;border:1px solid var(--lgray);border-radius:7px;font-size:.82rem;outline:none;background:#fff}
        .t-count{font-size:.82rem;color:var(--gray)}
        .t-count strong{color:var(--dark)}
        .export-btn{padding:.45rem 1rem;background:var(--teal);color:#fff;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .export-btn:hover{background:var(--dark)}

        /* ===== YEAR GROUP ===== */
        .year-group{font-family:'Sora',sans-serif;font-size:.82rem;font-weight:800;color:var(--dark);background:var(--light);padding:.4rem .8rem;border-radius:8px;margin:.8rem 0 .6rem;display:inline-block}

        /* ===== CONSULT CARD ===== */
        .c-card{background:#fff;border-radius:14px;border:1px solid var(--lgray);margin-bottom:.8rem;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:.2s}
        .c-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.1);transform:translateY(-1px)}
        .c-header{display:flex;align-items:center;gap:.9rem;padding:1rem 1.2rem;cursor:pointer;flex-wrap:wrap}
        .c-date-box{background:var(--blue);color:#fff;border-radius:10px;padding:.5rem .7rem;text-align:center;flex-shrink:0;min-width:52px}
        .c-date-box .day{font-family:'Sora',sans-serif;font-size:1.3rem;font-weight:800;line-height:1}
        .c-date-box .mon{font-size:.65rem;font-weight:600;opacity:.85;margin-top:.1rem}
        .c-main{flex:1;min-width:150px}
        .c-doc-name{font-family:'Sora',sans-serif;font-size:.92rem;font-weight:700;color:#1E293B}
        .c-spec{display:inline-block;padding:.18rem .55rem;border-radius:5px;font-size:.72rem;font-weight:600;margin-left:.4rem}
        .c-cabinet{font-size:.78rem;color:var(--gray);margin-top:.2rem;display:flex;align-items:center;gap:.3rem;flex-wrap:wrap}
        .c-status{display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;flex-shrink:0}
        .badge-ok{background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0;padding:.2rem .6rem;border-radius:5px;font-size:.72rem;font-weight:700}
        .c-toggle{font-size:.8rem;color:var(--blue);cursor:pointer;font-weight:600;background:none;border:none}

        /* ===== EXPANDED DETAIL ===== */
        .c-detail{display:none;padding:0 1.2rem 1rem;border-top:1px solid var(--lgray);grid-template-columns:1fr 1fr;gap:1rem}
        .c-detail.show{display:grid}
        .detail-block h6{font-size:.78rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem}
        .detail-block p{font-size:.83rem;color:#334155;line-height:1.5;background:var(--light);padding:.5rem .7rem;border-radius:8px}
        .c-actions{padding:.7rem 1.2rem;background:var(--light);border-top:1px solid var(--lgray);display:flex;gap:.5rem;flex-wrap:wrap}
        .c-btn{padding:.4rem .9rem;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
        .c-btn.blue{background:var(--blue);color:#fff}
        .c-btn.blue:hover{background:var(--dark)}
        .c-btn.outline{background:transparent;color:var(--teal);border:1.5px solid var(--teal)}
        .c-btn.outline:hover{background:var(--teal);color:#fff}
        .c-btn.ordo{background:transparent;color:var(--purple);border:1.5px solid var(--purple)}
        .c-btn.ordo:hover{background:var(--purple);color:#fff}

        /* ===== EMPTY ===== */
        .empty-state{text-align:center;padding:2rem;background:#fff;border-radius:12px;border:1px solid var(--lgray)}
        .empty-state .icon{font-size:3rem;margin-bottom:.5rem;opacity:.5}
        .empty-state p{color:var(--gray);font-size:.85rem}

        /* ===== PAGINATION ===== */
        .pagination{display:flex;justify-content:center;gap:.4rem;margin-top:1.5rem;flex-wrap:wrap}
        .pg{width:34px;height:34px;border-radius:8px;border:1px solid var(--lgray);background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;text-decoration:none;color:#334155}
        .pg.active{background:var(--blue);color:#fff;border-color:var(--blue)}
        .pg:hover:not(.active){background:var(--light)}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .main{flex-direction:column}
            .sidebar{width:100%}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .hdr-inner{flex-direction:column}
            .stats-bar{gap:.8rem}
            .st{padding:.4rem .8rem}
            .st-num{font-size:1.1rem}
            .c-detail{grid-template-columns:1fr}
            .c-header{flex-direction:column;align-items:stretch;text-align:center}
            .c-date-box{align-self:center}
            .c-status{align-items:center}
            .c-actions{justify-content:center}
        }
        @media(max-width:480px){
            .page-hdr{padding:1.2rem 4%}
            .page-hdr h1{font-size:1.1rem}
            .main{padding:1rem 4%}
            .s-card{padding:.8rem}
            .c-header{padding:.8rem}
            .c-doc-name{font-size:.82rem}
            .c-date-box{min-width:44px;padding:.3rem .5rem}
            .c-date-box .day{font-size:1rem}
            .hdr-btn{font-size:.72rem;padding:.4rem .8rem}
        }
    </style>
</head>
<body>

<!-- ===== NAV ===== -->
<nav>
    <a href="dashboard_patient.php" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="dashboard_patient.php">Accueil</a></li>
        <li><a href="mes_rdv.php">Mes RDV</a></li>
        <li><a href="historique_patient.php" class="active">Mon Historique</a></li>
        <li><a href="dossier_patient.php">Mon Dossier</a></li>
    </ul>
    <a href="profil_patient.php" class="avatar">
        <?php if(!empty($patient['photo']) && file_exists(__DIR__.'/../assets/uploads/'.$patient['photo'])): ?>
            <img src="../assets/uploads/<?=htmlspecialchars($patient['photo'])?>" alt="">
        <?php else: ?>
            <?=$initiales?>
        <?php endif; ?>
    </a>
</nav>

<!-- ===== PAGE HEADER ===== -->
<div class="page-hdr">
    <div class="breadcrumb">Accueil → <span>Historique Consultations</span></div>
    <div class="hdr-inner">
        <div>
            <h1>📅 Mon Historique de Consultations</h1>
            <p>Retrouvez tous vos comptes-rendus, diagnostics et ordonnances</p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="imprimer_dossier.php?id=<?=$patient_id?>" class="hdr-btn green">📥 Exporter PDF</a>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-bar">
    <div class="st"><div class="st-num" style="color:var(--blue)"><?=$stats['total']?></div><div class="st-lbl">Total<br>consultations</div></div>
    <div class="st"><div class="st-num" style="color:var(--green)"><?=$stats['medecins']?></div><div class="st-lbl">Médecins<br>différents</div></div>
    <div class="st"><div class="st-num" style="color:var(--purple)"><?=$stats['ordonnances']?></div><div class="st-lbl">Ordonnances<br>reçues</div></div>
    <div class="st"><div class="st-num" style="color:var(--teal)"><?=$stats['annee']?></div><div class="st-lbl">Depuis<br>l'année</div></div>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <!-- ===== SIDEBAR FILTERS ===== -->
    <div class="sidebar">
        <form method="GET" action="historique_patient.php" class="s-card">
            <h5>🔍 Filtres</h5>
            <div class="filter-group">
                <label>Date début</label>
                <input type="date" name="debut" value="<?=$_GET['debut'] ?? ''?>">
            </div>
            <div class="filter-group">
                <label>Date fin</label>
                <input type="date" name="fin" value="<?=$_GET['fin'] ?? ''?>">
            </div>
            <div class="filter-group">
                <label>Médecin</label>
                <select name="medecin">
                    <option value="">Tous les médecins</option>
                    <?php foreach($medecins as $m): ?>
                        <option value="<?=$m['id']?>" <?=$filtre_medecin == $m['id'] ? 'selected' : ''?>>
                            Dr. <?=htmlspecialchars($m['prenom'].' '.$m['nom'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Année</label>
                <select name="annee">
                    <option value="">Toutes les années</option>
                    <?php for($a = date('Y'); $a >= 2020; $a--): ?>
                        <option value="<?=$a?>" <?=$filtre_annee == $a ? 'selected' : ''?>><?=$a?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="filter-btn">Appliquer les filtres</button>
            <a href="historique_patient.php" class="reset-btn" style="display:block;text-align:center;text-decoration:none;">Réinitialiser</a>
        </form>

        <!-- STATS MÉDECINS -->
        <div class="s-card">
            <h5>📊 Mes médecins</h5>
            <?php 
            $max_visites = $medecins_stats[0]['nb'] ?? 1;
            foreach($medecins_stats as $m): 
                $pct = round(($m['nb'] / $max_visites) * 100);
                $couleurs = ['#0077B6','#2DC653','#7209B7','#F77F00','#D62828'];
                $idx = array_search($m, $medecins_stats);
                $couleur = $couleurs[$idx % count($couleurs)];
            ?>
            <div style="margin-bottom:.5rem;font-size:.82rem">
                <div style="display:flex;justify-content:space-between;margin-bottom:.2rem">
                    <span style="color:var(--gray)">Dr. <?=htmlspecialchars($m['prenom'].' '.$m['nom'])?></span>
                    <strong><?=$m['nb']?> cons.</strong>
                </div>
                <div style="height:6px;background:var(--lgray);border-radius:3px;overflow:hidden">
                    <div style="width:<?=$pct?>%;height:100%;background:<?=$couleur?>;border-radius:3px"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== TIMELINE ===== -->
    <div class="timeline-wrap">
        <div class="t-header">
            <div>
                <h3>Consultations</h3>
                <div class="t-count"><strong><?=$total?> consultations</strong> trouvées</div>
            </div>
            <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
                <select class="t-sort">
                    <option>Plus récent d'abord</option>
                    <option>Plus ancien d'abord</option>
                </select>
                <a href="imprimer_dossier.php?id=<?=$patient_id?>" class="export-btn">📥 Exporter</a>
            </div>
        </div>

        <?php if(empty($consultations)): ?>
            <div class="empty-state">
                <div class="icon">📅</div>
                <p>Aucune consultation trouvée pour cette période.</p>
            </div>
        <?php else: ?>
            <?php 
            $annee_courante = '';
            foreach($consultations as $c): 
                $annee_consult = date('Y', strtotime($c['date_consultation']));
                if($annee_consult != $annee_courante): 
                    $annee_courante = $annee_consult; ?>
                    <div class="year-group">📅 <?=$annee_courante?></div>
                <?php endif; 
                $couleur_spec = getCouleurSpecialite($c['specialite']);
            ?>
            <div class="c-card">
                <div class="c-header" onclick="toggleDetail(this)">
                    <div class="c-date-box">
                        <div class="day"><?=date('d', strtotime($c['date_consultation']))?></div>
                        <div class="mon"><?=strtoupper(date('M', strtotime($c['date_consultation'])))?></div>
                    </div>
                    <div class="c-main">
                        <div>
                            <span class="c-doc-name">Dr. <?=htmlspecialchars($c['m_prenom'].' '.$c['m_nom'])?></span>
                            <span class="c-spec" style="background:<?=$couleur_spec['bg']?>;color:<?=$couleur_spec['color']?>">
                                <?=htmlspecialchars($c['specialite'])?>
                            </span>
                        </div>
                        <div class="c-cabinet">
                            📍 <?=htmlspecialchars($c['cabinet'] ?? 'Cabinet')?>, <?=htmlspecialchars($c['ville'] ?? '')?>
                            &nbsp;|&nbsp; ⏱ <?=$c['heure_rdv'] ? substr($c['heure_rdv'],0,5) : '30 min'?>
                        </div>
                    </div>
                    <div class="c-status">
                        <span class="badge-ok">✅ Terminé</span>
                        <button class="c-toggle" onclick="event.stopPropagation();toggleDetail(this.closest('.c-header'))">▼ Voir détails</button>
                    </div>
                </div>
                <div class="c-detail">
                    <?php if(!empty($c['diagnostic'])): ?>
                    <div class="detail-block">
                        <h6>🔬 Diagnostic</h6>
                        <p><?=htmlspecialchars($c['diagnostic'])?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($c['traitement'])): ?>
                    <div class="detail-block">
                        <h6>💊 Traitement prescrit</h6>
                        <p><?=htmlspecialchars($c['traitement'])?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($c['compte_rendu'])): ?>
                    <div class="detail-block">
                        <h6>📝 Compte-rendu</h6>
                        <p><?=htmlspecialchars(mb_substr($c['compte_rendu'], 0, 150)).(mb_strlen($c['compte_rendu'])>150?'...':'')?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($c['prochain_rdv'])): ?>
                    <div class="detail-block">
                        <h6>📅 Prochain RDV</h6>
                        <p><?=formatDate($c['prochain_rdv'])?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="c-actions">
                    <a href="detail_rdv.php?id=<?=$c['id_rendezvous']?>" class="c-btn blue">📋 Voir compte-rendu complet</a>
                    <?php if(!empty($c['ordonnance'])): ?>
                        <a href="ordonnance.php?id=<?=$c['id_rendezvous']?>" class="c-btn ordo">💊 Voir ordonnance</a>
                    <?php endif; ?>
                    <a href="prendre_rdv.php?medecin=<?=$c['id_medecin']?>" class="c-btn outline">🔄 Reprendre RDV</a>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- ===== PAGINATION ===== -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?=$page-1?><?=!empty($filtre_medecin)?'&medecin='.$filtre_medecin:''?><?=!empty($filtre_specialite)?'&specialite='.$filtre_specialite:''?><?=!empty($filtre_annee)?'&annee='.$filtre_annee:''?>" class="pg">←</a>
                <?php endif; ?>
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="?page=<?=$i?><?=!empty($filtre_medecin)?'&medecin='.$filtre_medecin:''?><?=!empty($filtre_specialite)?'&specialite='.$filtre_specialite:''?><?=!empty($filtre_annee)?'&annee='.$filtre_annee:''?>" class="pg <?=$i===$page?'active':''?>"><?=$i?></a>
                <?php endfor; ?>
                <?php if($page < $total_pages): ?>
                    <a href="?page=<?=$page+1?><?=!empty($filtre_medecin)?'&medecin='.$filtre_medecin:''?><?=!empty($filtre_specialite)?'&specialite='.$filtre_specialite:''?><?=!empty($filtre_annee)?'&annee='.$filtre_annee:''?>" class="pg">→</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Aminata Layi Gueye (E6)</strong> — historique_patient.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    function toggleDetail(header) {
        const detail = header.nextElementSibling;
        const toggleBtn = header.querySelector('.c-toggle');
        if (detail.classList.contains('show')) {
            detail.classList.remove('show');
            toggleBtn.textContent = '▼ Voir détails';
        } else {
            detail.classList.add('show');
            toggleBtn.textContent = '▲ Replier';
        }
    }
</script>
</body>
</html>