<?php
// ================================================================
// mon_agenda.php — Agenda du médecin
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn() || getRole() !== 'medecin') {
    redirect('login.php');
}

$medecin_id = $_SESSION['user_id'];

// Récupérer les infos médecin
$stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id AND statut = 'valide' LIMIT 1");
$stmt->execute([':id' => $medecin_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$medecin) { session_destroy(); redirect('login.php'); }

// ================================================================
// STATISTIQUES DU JOUR
// ================================================================
$aujourdhui = date('Y-m-d');
$stats = [
    'rdv_aujourdhui' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND date_rdv = '$aujourdhui'")->fetchColumn(),
    'en_attente' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND date_rdv = '$aujourdhui' AND statut = 'en_attente'")->fetchColumn(),
    'confirmes' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND date_rdv = '$aujourdhui' AND statut = 'confirme'")->fetchColumn(),
    'semaine' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
];

// ================================================================
// RÉCUPÉRATION DES RDV DE LA SEMAINE
// ================================================================
$debut_semaine = date('Y-m-d', strtotime('monday this week'));
$fin_semaine = date('Y-m-d', strtotime('saturday this week'));

$stmt = $pdo->prepare("
    SELECT r.*, 
           p.nom AS p_nom, p.prenom AS p_prenom, p.telephone,
           DATE_FORMAT(r.date_rdv, '%a') AS jour_court,
           DAYOFWEEK(r.date_rdv) AS jour_num
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    WHERE r.id_medecin = :medecin_id 
      AND r.date_rdv BETWEEN :debut AND :fin
      AND r.statut IN ('confirme', 'en_attente')
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
");
$stmt->execute([
    ':medecin_id' => $medecin_id,
    ':debut' => $debut_semaine,
    ':fin' => $fin_semaine
]);
$rdv_semaine = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// RDV DU JOUR
// ================================================================
$stmt = $pdo->prepare("
    SELECT r.*, 
           p.nom AS p_nom, p.prenom AS p_prenom, p.telephone
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    WHERE r.id_medecin = :medecin_id AND r.date_rdv = :date
      AND r.statut IN ('confirme', 'en_attente', 'termine')
    ORDER BY r.heure_rdv ASC
");
$stmt->execute([':medecin_id' => $medecin_id, ':date' => $aujourdhui]);
$rdv_aujourdhui = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// JOURS DE LA SEMAINE
// ================================================================
$jours_semaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
$jours_complets = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

function getInitiales($prenom, $nom) {
    return strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));
}

$initiales = strtoupper(substr($medecin['prenom'], 0, 1) . substr($medecin['nom'], 0, 1));
$num_semaine = date('W');
$annee = date('Y');
$date_debut = date('d', strtotime($debut_semaine));
$date_fin = date('d', strtotime($fin_semaine));
$mois = date('F Y', strtotime($debut_semaine));

// Regrouper les RDV par jour
$rdv_par_jour = [];
foreach($rdv_semaine as $rdv) {
    $jour = date('Y-m-d', strtotime($rdv['date_rdv']));
    if (!isset($rdv_par_jour[$jour])) {
        $rdv_par_jour[$jour] = [];
    }
    $rdv_par_jour[$jour][] = $rdv;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Mon Agenda</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--teal:#006D77;--light:#F0F4FF;--gray:#64748B;--lgray:#E2E8F0}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .nav-links a.active{color:#fff;border-bottom:2px solid var(--green);padding-bottom:2px}
        .nav-right{display:flex;align-items:center;gap:.8rem}
        .doc-badge{background:rgba(45,198,83,.2);color:var(--green);padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:700}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        .page-hdr{background:linear-gradient(135deg,var(--teal),var(--dark));padding:1.5rem 5%;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
        .hdr-left h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
        .hdr-left p{color:#A5F3FC;font-size:.85rem;margin-top:.3rem}
        .hdr-right{display:flex;gap:.6rem;flex-wrap:wrap}
        .hdr-btn{padding:.55rem 1.1rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}
        .hdr-btn.green{background:var(--green);border-color:var(--green)}
        .hdr-btn.green:hover{background:#25B347}

        .mini-stats{background:#fff;border-bottom:1px solid var(--lgray);padding:.8rem 5%;display:flex;gap:1.5rem;flex-wrap:wrap}
        .ms{display:flex;align-items:center;gap:.6rem}
        .ms-num{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800}
        .ms-lbl{font-size:.73rem;color:var(--gray);line-height:1.3}

        .agenda-wrap{padding:1.2rem 5%;max-width:1100px;margin:0 auto}

        .agenda-controls{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.8rem}
        .week-nav{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
        .week-nav button{width:34px;height:34px;border-radius:8px;border:1px solid var(--lgray);background:#fff;font-size:1rem;cursor:pointer;border:none}
        .week-nav button:hover{background:var(--light)}
        .week-nav h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:var(--dark)}
        .view-tabs{display:flex;gap:.3rem;background:#fff;border-radius:8px;padding:.25rem;border:1px solid var(--lgray)}
        .view-tab{padding:.45rem .9rem;border-radius:6px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray)}
        .view-tab.active{background:var(--teal);color:#fff}
        .filter-select{padding:.45rem .8rem;border:1px solid var(--lgray);border-radius:7px;font-size:.82rem;outline:none;background:#fff}

        .week-grid{display:flex;gap:0;border:1px solid var(--lgray);border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow-x:auto}
        .time-col{width:60px;flex-shrink:0;border-right:1px solid var(--lgray)}
        .time-col .time-hdr{height:52px;border-bottom:1px solid var(--lgray);background:#F8FAFF}
        .time-slot-label{height:60px;display:flex;align-items:flex-start;justify-content:center;padding-top:4px;font-size:.7rem;color:var(--gray);border-bottom:1px solid #F1F5F9}
        .day-col{flex:1;min-width:80px;border-right:1px solid var(--lgray)}
        .day-col:last-child{border-right:none}
        .day-hdr{height:52px;border-bottom:1px solid var(--lgray);display:flex;flex-direction:column;align-items:center;justify-content:center;background:#F8FAFF}
        .day-name{font-size:.72rem;font-weight:700;color:var(--gray);text-transform:uppercase}
        .day-num{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark)}
        .day-num.today{background:var(--blue);color:#fff;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center}
        .time-block{height:60px;border-bottom:1px solid #F1F5F9;position:relative;padding:2px;min-height:60px}
        .time-block.working{background:#fff}
        .time-block.off{background:#FAFAFA}

        .rdv-block{border-radius:6px;padding:.25rem .5rem;font-size:.72rem;cursor:pointer;border-left:3px solid;position:absolute;left:2px;right:2px;top:2px;overflow:hidden;transition:.15s;height:54px}
        .rdv-block:hover{filter:brightness(.95);z-index:2}
        .rdv-block.confirme{background:#EFF6FF;border-color:var(--blue);color:#1E40AF}
        .rdv-block.attente{background:#FFF7ED;border-color:var(--orange);color:#92400E}
        .rdv-block.termine{background:#F0FDF4;border-color:var(--green);color:#166534}
        .rdv-block .rb-name{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .rdv-block .rb-time{font-size:.65rem;opacity:.8}

        .today-list{background:#fff;border-radius:12px;padding:1.2rem;border:1px solid var(--lgray);margin-top:1.2rem;box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .today-list h4{font-family:'Sora',sans-serif;font-size:.92rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray)}
        .rdv-row{display:flex;align-items:center;gap:1rem;padding:.7rem;border-radius:10px;background:var(--light);margin-bottom:.5rem;cursor:pointer;transition:.15s;flex-wrap:wrap}
        .rdv-row:hover{background:#EFF6FF}
        .rdv-time{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:800;color:var(--blue);min-width:50px}
        .rdv-pat{flex:1;min-width:150px}
        .rdv-pat-name{font-size:.88rem;font-weight:700;color:#1E293B}
        .rdv-pat-info{font-size:.76rem;color:var(--gray);margin-top:.1rem}
        .rdv-status{font-size:.75rem;font-weight:700;padding:.25rem .65rem;border-radius:20px}
        .rdv-status.conf{background:#F0FDF4;color:#15803D}
        .rdv-status.att{background:#FFF7ED;color:#C2410C}
        .rdv-status.term{background:#F1F5F9;color:var(--gray)}
        .rdv-row-btns{display:flex;gap:.4rem;flex-wrap:wrap}
        .rb{padding:.3rem .7rem;border-radius:6px;font-size:.73rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:.2rem}
        .rb.blue{background:var(--blue);color:#fff}
        .rb.blue:hover{background:var(--dark)}
        .rb.g{background:var(--green);color:#fff}
        .rb.g:hover{background:#25B347}
        .rb.r{background:transparent;color:var(--red);border:1px solid var(--red)}
        .rb.r:hover{background:#FFF5F5}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        .empty-state{text-align:center;padding:1rem;color:var(--gray);font-size:.8rem}

        @media(max-width:992px){
            .week-grid{overflow-x:auto}
            .day-col{min-width:70px}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .page-hdr{flex-direction:column;text-align:center}
            .hdr-right{justify-content:center}
            .mini-stats{gap:.8rem}
            .ms{padding:.4rem .8rem;background:var(--light);border-radius:8px}
            .ms-num{font-size:1.1rem}
            .agenda-controls{flex-direction:column;align-items:stretch}
            .week-nav{justify-content:center}
            .time-col{width:40px}
            .time-slot-label{font-size:.6rem}
            .day-col{min-width:50px}
            .day-num{font-size:.8rem}
            .day-name{font-size:.6rem}
            .rdv-block{font-size:.6rem;padding:.15rem .3rem}
            .rdv-block .rb-name{font-size:.55rem}
            .rdv-row{flex-direction:column;text-align:center}
            .rdv-time{min-width:auto}
            .rdv-row-btns{justify-content:center}
        }
        @media(max-width:480px){
            .page-hdr{padding:1rem 4%}
            .page-hdr h1{font-size:1.1rem}
            .agenda-wrap{padding:.8rem 4%}
            .day-col{min-width:40px}
            .time-col{width:30px}
            .time-slot-label{font-size:.5rem;padding-top:2px}
            .day-num{font-size:.7rem;width:22px;height:22px}
            .rdv-block{height:44px;font-size:.5rem}
            .rdv-block .rb-name{font-size:.5rem}
            .today-list{padding:.8rem}
            .rdv-row{padding:.5rem}
            .rdv-pat-name{font-size:.8rem}
        }
    </style>
</head>
<body>

<!-- ===== NAV ===== -->
<nav>
    <a href="dashboard_medecin.php" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="dashboard_medecin.php">Accueil</a></li>
        <li><a href="mon_agenda.php" class="active">Mon Agenda</a></li>
        <li><a href="historique_medecin.php">Mes Patients</a></li>
        <li><a href="ordonnance.php">Ordonnances</a></li>
    </ul>
    <div class="nav-right">
        <a href="notifications.php" style="color:#CBD5E1;font-size:.85rem;text-decoration:none;">🔔 <sup style="background:#D62828;color:#fff;border-radius:50%;padding:1px 4px;font-size:.65rem">3</sup></a>
        <div class="doc-badge">👨‍⚕️ Dr. <?=htmlspecialchars($medecin['prenom'])?></div>
    </div>
</nav>

<!-- ===== HEADER ===== -->
<div class="page-hdr">
    <div class="hdr-left">
        <h1>Mon Agenda</h1>
        <p><?=htmlspecialchars($medecin['specialite'])?> — <?=htmlspecialchars($medecin['cabinet'])?>, <?=htmlspecialchars($medecin['ville'])?></p>
    </div>
    <div class="hdr-right">
        <a href="profil_medecin.php#disponibilites" class="hdr-btn">⚙️ Disponibilités</a>
        <a href="gerer_rdv.php" class="hdr-btn green">+ Gérer RDV</a>
    </div>
</div>

<!-- ===== MINI STATS ===== -->
<div class="mini-stats">
    <div class="ms"><div class="ms-num" style="color:var(--blue)"><?=$stats['rdv_aujourdhui']?></div><div class="ms-lbl">RDV<br>aujourd'hui</div></div>
    <div class="ms"><div class="ms-num" style="color:var(--orange)"><?=$stats['en_attente']?></div><div class="ms-lbl">En<br>attente</div></div>
    <div class="ms"><div class="ms-num" style="color:var(--green)"><?=$stats['confirmes']?></div><div class="ms-lbl">Confirmés<br>aujourd'hui</div></div>
    <div class="ms"><div class="ms-num" style="color:var(--teal)"><?=$stats['semaine']?></div><div class="ms-lbl">Cette<br>semaine</div></div>
</div>

<!-- ===== AGENDA ===== -->
<div class="agenda-wrap">
    <div class="agenda-controls">
        <div class="week-nav">
            <button onclick="changeWeek(-1)">←</button>
            <h3><?=$date_debut?> — <?=$date_fin?> <?=$mois?></h3>
            <button onclick="changeWeek(1)">→</button>
            <button onclick="goToday()" style="padding:.4rem .9rem;background:var(--light);color:var(--blue);border:1px solid var(--blue);border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;margin-left:.5rem;border:none">Aujourd'hui</button>
        </div>
        <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
            <div class="view-tabs">
                <button class="view-tab">Jour</button>
                <button class="view-tab active">Semaine</button>
                <button class="view-tab">Mois</button>
            </div>
            <select class="filter-select">
                <option>Tous les statuts</option>
                <option>Confirmés</option>
                <option>En attente</option>
            </select>
        </div>
    </div>

    <!-- WEEK GRID -->
    <div class="week-grid">
        <div class="time-col">
            <div class="time-hdr"></div>
            <?php for($h = 8; $h <= 16; $h++): ?>
                <div class="time-slot-label"><?=$h?>h00</div>
            <?php endfor; ?>
        </div>

        <?php 
        $date_courante = strtotime($debut_semaine);
        for($j = 0; $j < 6; $j++): 
            $date_jour = date('Y-m-d', $date_courante);
            $est_aujourdhui = ($date_jour == $aujourdhui);
            $jour_num = date('j', $date_courante);
        ?>
        <div class="day-col">
            <div class="day-hdr">
                <div class="day-name"><?=$jours_semaine[$j]?></div>
                <div class="day-num <?=$est_aujourdhui?'today':''?>"><?=$jour_num?></div>
            </div>
            <?php 
            for($h = 8; $h <= 16; $h++): 
                $heure_formatee = sprintf('%02d:00:00', $h);
                // Vérifier si un RDV existe à cette heure
                $rdv_a_heure = null;
                if (isset($rdv_par_jour[$date_jour])) {
                    foreach($rdv_par_jour[$date_jour] as $rdv) {
                        $heure_rdv = substr($rdv['heure_rdv'], 0, 5);
                        if ($heure_rdv == sprintf('%02d:00', $h)) {
                            $rdv_a_heure = $rdv;
                            break;
                        }
                    }
                }
                $est_dispo = true; // Par défaut disponible
            ?>
            <div class="time-block <?=$est_dispo ? 'working' : 'off'?>">
                <?php if($rdv_a_heure): 
                    $statut_class = $rdv_a_heure['statut'] === 'confirme' ? 'confirme' : ($rdv_a_heure['statut'] === 'en_attente' ? 'attente' : 'termine');
                    $nom_patient = $rdv_a_heure['p_prenom'].' '.$rdv_a_heure['p_nom'];
                ?>
                <div class="rdv-block <?=$statut_class?>" style="height:54px" onclick="window.location='voir_dossier.php?id=<?=$rdv_a_heure['id_patient']?>'">
                    <div class="rb-name"><?=htmlspecialchars($nom_patient)?></div>
                    <div class="rb-time"><?=substr($rdv_a_heure['heure_rdv'],0,5)?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
        <?php 
            $date_courante = strtotime('+1 day', $date_courante);
        endfor; 
        ?>
    </div>

    <!-- LEGENDE -->
    <div style="display:flex;gap:1rem;margin-top:.8rem;flex-wrap:wrap;font-size:.76rem">
        <span style="display:flex;align-items:center;gap:.4rem"><span style="width:12px;height:12px;border-radius:3px;background:#EFF6FF;border-left:3px solid var(--blue);display:inline-block"></span>Confirmé</span>
        <span style="display:flex;align-items:center;gap:.4rem"><span style="width:12px;height:12px;border-radius:3px;background:#FFF7ED;border-left:3px solid var(--orange);display:inline-block"></span>En attente</span>
        <span style="display:flex;align-items:center;gap:.4rem"><span style="width:12px;height:12px;border-radius:3px;background:#F0FDF4;border-left:3px solid var(--green);display:inline-block"></span>Terminé</span>
        <span style="display:flex;align-items:center;gap:.4rem"><span style="width:12px;height:12px;border-radius:3px;background:#FAFAFA;display:inline-block;border:1px solid var(--lgray)"></span>Non disponible</span>
    </div>

    <!-- TODAY LIST -->
    <div class="today-list">
        <h4>📋 RDV du jour — <?=date('l d F Y', strtotime($aujourdhui))?></h4>
        <?php if(empty($rdv_aujourdhui)): ?>
            <div class="empty-state">Aucun rendez-vous aujourd'hui.</div>
        <?php else: ?>
            <?php foreach($rdv_aujourdhui as $rdv): 
                $statut_class = $rdv['statut'] === 'confirme' ? 'conf' : ($rdv['statut'] === 'en_attente' ? 'att' : 'term');
                $statut_label = $rdv['statut'] === 'confirme' ? '✅ Confirmé' : ($rdv['statut'] === 'en_attente' ? '⏳ En attente' : '📋 Terminé');
            ?>
            <div class="rdv-row">
                <div class="rdv-time"><?=substr($rdv['heure_rdv'],0,5)?></div>
                <div class="rdv-pat">
                    <div class="rdv-pat-name"><?=htmlspecialchars($rdv['p_prenom'].' '.$rdv['p_nom'])?></div>
                    <div class="rdv-pat-info"><?=htmlspecialchars($medecin['specialite'])?> • 30 min • <?=htmlspecialchars($rdv['motif'])?></div>
                </div>
                <span class="rdv-status <?=$statut_class?>"><?=$statut_label?></span>
                <div class="rdv-row-btns">
                    <a href="voir_dossier.php?id=<?=$rdv['id_patient']?>" class="rb blue">Voir dossier</a>
                    <?php if($rdv['statut'] === 'confirme' || $rdv['statut'] === 'termine'): ?>
                        <a href="compte_rendu.php?id=<?=$rdv['id']?>" class="rb g">Compte-rendu</a>
                    <?php endif; ?>
                    <?php if($rdv['statut'] === 'en_attente'): ?>
                        <form method="POST" action="gerer_rdv.php" style="display:inline;">
                            <input type="hidden" name="rdv_id" value="<?=$rdv['id']?>">
                            <input type="hidden" name="action" value="confirmer">
                            <button type="submit" class="rb g">✅ Confirmer</button>
                        </form>
                        <form method="POST" action="gerer_rdv.php" style="display:inline;">
                            <input type="hidden" name="rdv_id" value="<?=$rdv['id']?>">
                            <input type="hidden" name="action" value="refuser">
                            <button type="submit" class="rb r" onclick="return confirm('Refuser ce rendez-vous ?')">❌ Refuser</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Mareme Faye (E5)</strong> — mon_agenda.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    function changeWeek(delta) {
        const currentDate = new Date();
        currentDate.setDate(currentDate.getDate() + delta * 7);
        // Redirection simple pour la démonstration
        window.location.reload();
    }

    function goToday() {
        window.location.href = 'mon_agenda.php';
    }
</script>
</body>
</html>