<?php
// ================================================================
// gerer_rdv.php — Gérer les rendez-vous (Médecin)
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
// STATISTIQUES
// ================================================================
$stats = [
    'en_attente' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND statut = 'en_attente'")->fetchColumn(),
    'confirmes_semaine' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND statut = 'confirme' AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
    'refuses_semaine' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND statut = 'refuse' AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
    'total_mois' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
];

// ================================================================
// FILTRES
// ================================================================
$filtre_statut = $_GET['statut'] ?? 'en_attente';
if (!in_array($filtre_statut, ['en_attente', 'confirme', 'refuse', 'tous'])) {
    $filtre_statut = 'en_attente';
}

$sql = "SELECT r.*, 
        p.nom AS p_nom, p.prenom AS p_prenom, p.telephone, p.date_naissance, p.groupe_sanguin,
        d.allergies, d.maladies_chroniques
        FROM rendezvous r
        JOIN patients p ON p.id = r.id_patient
        LEFT JOIN dossiers d ON d.id_patient = p.id
        WHERE r.id_medecin = :medecin_id";

if ($filtre_statut !== 'tous') {
    $sql .= " AND r.statut = :statut";
}
$sql .= " ORDER BY r.date_rdv ASC, r.heure_rdv ASC";

$stmt = $pdo->prepare($sql);
$params = [':medecin_id' => $medecin_id];
if ($filtre_statut !== 'tous') {
    $params[':statut'] = $filtre_statut;
}
$stmt->execute($params);
$rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// TRAITEMENT DES ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rdv_id = (int)($_POST['rdv_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $message = trim($_POST['message'] ?? '');
    
    if ($rdv_id > 0 && in_array($action, ['confirmer', 'refuser'])) {
        $new_statut = $action === 'confirmer' ? 'confirme' : 'refuse';
        $stmt = $pdo->prepare("UPDATE rendezvous SET statut = :statut, message_medecin = :message WHERE id = :id AND id_medecin = :medecin_id");
        $stmt->execute([
            ':statut' => $new_statut,
            ':message' => $message ?: null,
            ':id' => $rdv_id,
            ':medecin_id' => $medecin_id
        ]);
        
        // Récupérer l'ID du patient pour la notification
        $stmt2 = $pdo->prepare("SELECT id_patient FROM rendezvous WHERE id = :id");
        $stmt2->execute([':id' => $rdv_id]);
        $patient = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            $type_notif = $action === 'confirmer' ? 'rdv_confirme' : 'rdv_annule';
            $msg = $action === 'confirmer' 
                ? "Votre rendez-vous a été confirmé par le Dr. " . $medecin['prenom'] . " " . $medecin['nom']
                : "Votre rendez-vous a été refusé par le Dr. " . $medecin['prenom'] . " " . $medecin['nom'] . ($message ? " Motif : " . $message : "");
            
            $pdo->prepare("INSERT INTO notifications (id_destinataire, type_user, type_notif, message, id_rdv, date_envoi) 
                           VALUES (:id, 'patient', :type, :msg, :rdv_id, NOW())")
                ->execute([':id' => $patient['id_patient'], ':type' => $type_notif, ':msg' => $msg, ':rdv_id' => $rdv_id]);
        }
        
        $_SESSION['success'] = $action === 'confirmer' ? '✅ Rendez-vous confirmé !' : '❌ Rendez-vous refusé.';
        redirect('gerer_rdv.php');
    }
}

function getAge($date) {
    if (!$date) return '?';
    $now = new DateTime();
    $birth = new DateTime($date);
    return $now->diff($birth)->y;
}

function getInitiales($prenom, $nom) {
    return strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));
}

$initiales = strtoupper(substr($medecin['prenom'], 0, 1) . substr($medecin['nom'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Gérer les RDV</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--teal:#006D77;--red:#D62828;--orange:#F77F00;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .nav-links a.active{color:#fff;border-bottom:2px solid var(--green);padding-bottom:2px}
        .doc-badge{background:rgba(45,198,83,.2);color:var(--green);padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:700}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        .page-hdr{background:linear-gradient(135deg,var(--teal),var(--dark));padding:1.5rem 5%;color:#fff}
        .breadcrumb{font-size:.78rem;color:#A5F3FC;margin-bottom:.5rem}
        .breadcrumb span{color:#FDE68A}
        .page-hdr h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
        .page-hdr p{color:#A5F3FC;font-size:.85rem;margin-top:.3rem}

        .stats-bar{background:#fff;border-bottom:1px solid var(--lgray);padding:.8rem 5%;display:flex;gap:1.5rem;flex-wrap:wrap}
        .st{display:flex;align-items:center;gap:.6rem;background:var(--light);border-radius:10px;padding:.5rem 1rem}
        .st-num{font-family:'Sora',sans-serif;font-size:1.3rem;font-weight:800}
        .st-lbl{font-size:.72rem;color:var(--gray);line-height:1.3}

        .main{padding:1.5rem 5%;max-width:1000px;margin:0 auto}

        .filter-bar{display:flex;gap:.7rem;margin-bottom:1.2rem;flex-wrap:wrap;align-items:center;background:#fff;padding:.9rem 1rem;border-radius:12px;border:1px solid var(--lgray);box-shadow:0 2px 6px rgba(0,0,0,.04)}
        .filter-bar label{font-size:.8rem;font-weight:600;color:#334155;white-space:nowrap}
        .filter-bar select,.filter-bar input{padding:.5rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.83rem;outline:none;background:#fff;font-family:'DM Sans',sans-serif}
        .filter-bar select:focus,.filter-bar input:focus{border-color:var(--teal)}
        .filter-btn{padding:.5rem 1.2rem;background:var(--teal);color:#fff;border:none;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer;white-space:nowrap}

        .tabs{display:flex;gap:.3rem;background:#fff;border-radius:12px;padding:.3rem;margin-bottom:1.2rem;border:1px solid var(--lgray);flex-wrap:wrap}
        .tab{flex:1;padding:.6rem;text-align:center;border-radius:9px;font-size:.83rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray);min-width:80px;text-decoration:none}
        .tab.active{background:var(--teal);color:#fff}
        .tab .cnt{background:var(--red);color:#fff;border-radius:10px;padding:.05rem .4rem;font-size:.68rem;margin-left:.3rem}
        .tab.active .cnt{background:rgba(255,255,255,.3)}

        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:#D62828}

        .rdv-card{background:#fff;border-radius:14px;padding:1.3rem;border:1px solid var(--lgray);margin-bottom:.9rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .rdv-card.urgent{border-left:4px solid var(--orange)}
        .rdv-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.9rem;flex-wrap:wrap;gap:.5rem}
        .pat-info{display:flex;gap:.8rem;align-items:center}
        .pat-av{width:46px;height:46px;border-radius:10px;background:linear-gradient(135deg,var(--blue),var(--teal));display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff}
        .pat-name{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:#1E293B}
        .pat-meta{font-size:.76rem;color:var(--gray);margin-top:.15rem}
        .badge{padding:.3rem .8rem;border-radius:20px;font-size:.74rem;font-weight:700}
        .badge.attente{background:#FFF7ED;color:#C2410C;border:1px solid #FED7AA}
        .badge.confirme{background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0}
        .badge.refuse{background:#FFF5F5;color:var(--red);border:1px solid #FECACA}
        .badge.termine{background:#F1F5F9;color:var(--gray);border:1px solid var(--lgray)}

        .rdv-details{display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:.9rem}
        .rd{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:var(--gray)}
        .rd strong{color:#334155}

        .rdv-motif{background:var(--light);border-left:3px solid var(--teal);border-radius:0 8px 8px 0;padding:.55rem .9rem;font-size:.82rem;color:#334155;margin-bottom:.9rem}

        .rdv-actions{display:flex;gap:.6rem;flex-wrap:wrap}
        .btn-confirm{padding:.5rem 1.2rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.4rem}
        .btn-confirm:hover{background:#25B347}
        .btn-refuse{padding:.5rem 1.1rem;background:transparent;color:var(--red);border:1.5px solid var(--red);border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer}
        .btn-refuse:hover{background:#FFF5F5}
        .btn-dossier{padding:.5rem 1.1rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
        .btn-dossier:hover{background:var(--dark)}
        .btn-cr{padding:.5rem 1.1rem;background:transparent;color:var(--teal);border:1.5px solid var(--teal);border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
        .btn-cr:hover{background:var(--teal);color:#fff}
        .btn-ordo{padding:.5rem 1.1rem;background:transparent;color:var(--orange);border:1.5px solid var(--orange);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
        .btn-ordo:hover{background:var(--orange);color:#fff}

        .empty-state{text-align:center;padding:2rem;background:#fff;border-radius:12px;border:1px solid var(--lgray)}
        .empty-state .icon{font-size:3rem;margin-bottom:.5rem;opacity:.5}
        .empty-state p{color:var(--gray);font-size:.85rem}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .stats-bar{gap:.8rem}
            .st{padding:.4rem .8rem}
            .st-num{font-size:1.1rem}
            .filter-bar{flex-direction:column;align-items:stretch}
            .tabs{flex-wrap:wrap}
            .tab{min-width:60px;font-size:.75rem;padding:.4rem}
            .rdv-top{flex-direction:column}
            .rdv-actions{flex-direction:column}
            .rdv-actions button{width:100%;justify-content:center}
            .rdv-details{gap:.5rem}
        }
        @media(max-width:480px){
            .page-hdr{padding:1rem 4%}
            .page-hdr h1{font-size:1.1rem}
            .main{padding:1rem 4%}
            .rdv-card{padding:.8rem}
            .pat-av{width:36px;height:36px;font-size:.9rem}
            .pat-name{font-size:.82rem}
            .rdv-motif{font-size:.75rem;padding:.4rem .7rem}
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
        <li><a href="mon_agenda.php">Mon Agenda</a></li>
        <li><a href="gerer_rdv.php" class="active">Gérer RDV</a></li>
        <li><a href="historique_medecin.php">Mes Patients</a></li>
    </ul>
    <div class="doc-badge">👨‍⚕️ Dr. <?=htmlspecialchars($medecin['prenom'])?></div>
</nav>

<!-- ===== HEADER ===== -->
<div class="page-hdr">
    <div class="breadcrumb">Tableau de bord → <span>Gestion des RDV</span></div>
    <h1>Gérer mes Rendez-Vous</h1>
    <p>Confirmez, refusez ou gérez tous vos rendez-vous reçus</p>
</div>

<!-- ===== STATS ===== -->
<div class="stats-bar">
    <div class="st"><div class="st-num" style="color:var(--orange)"><?=$stats['en_attente']?></div><div class="st-lbl">En attente<br>de réponse</div></div>
    <div class="st"><div class="st-num" style="color:var(--green)"><?=$stats['confirmes_semaine']?></div><div class="st-lbl">Confirmés<br>cette semaine</div></div>
    <div class="st"><div class="st-num" style="color:var(--red)"><?=$stats['refuses_semaine']?></div><div class="st-lbl">Refusés<br>cette semaine</div></div>
    <div class="st"><div class="st-num" style="color:var(--teal)"><?=$stats['total_mois']?></div><div class="st-lbl">Total RDV<br>ce mois</div></div>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success">✅ <?=htmlspecialchars($_SESSION['success'])?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <label>Filtrer :</label>
        <input type="date" value="<?=date('Y-m-d')?>">
        <select>
            <option <?=$filtre_statut==='en_attente'?'selected':''?>>En attente</option>
            <option <?=$filtre_statut==='confirme'?'selected':''?>>Confirmés</option>
            <option <?=$filtre_statut==='refuse'?'selected':''?>>Refusés</option>
            <option <?=$filtre_statut==='tous'?'selected':''?>>Tous</option>
        </select>
        <input type="text" placeholder="Rechercher un patient...">
        <button class="filter-btn">🔍 Filtrer</button>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <a href="?statut=en_attente" class="tab <?=$filtre_statut==='en_attente'?'active':''?>">⏳ En attente <span class="cnt"><?=$stats['en_attente']?></span></a>
        <a href="?statut=confirme" class="tab <?=$filtre_statut==='confirme'?'active':''?>">✅ Confirmés <span class="cnt" style="background:rgba(0,0,0,.15)"><?=$stats['confirmes_semaine']?></span></a>
        <a href="?statut=refuse" class="tab <?=$filtre_statut==='refuse'?'active':''?>">❌ Refusés</a>
        <a href="?statut=tous" class="tab <?=$filtre_statut==='tous'?'active':''?>">📋 Tous</a>
    </div>

    <?php if(empty($rendezvous)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>Aucun rendez-vous trouvé.</p>
        </div>
    <?php else: ?>
        <?php foreach($rendezvous as $rdv): 
            $is_urgent = $rdv['statut'] === 'en_attente';
            $badge_class = $rdv['statut'] === 'en_attente' ? 'attente' : ($rdv['statut'] === 'confirme' ? 'confirme' : ($rdv['statut'] === 'refuse' ? 'refuse' : 'termine'));
            $badge_label = $rdv['statut'] === 'en_attente' ? '⏳ En attente' : ($rdv['statut'] === 'confirme' ? '✅ Confirmé' : ($rdv['statut'] === 'refuse' ? '❌ Refusé' : '📋 Terminé'));
            $age = getAge($rdv['date_naissance']);
            $init = getInitiales($rdv['p_prenom'], $rdv['p_nom']);
        ?>
        <div class="rdv-card <?=$is_urgent?'urgent':''?>">
            <div class="rdv-top">
                <div class="pat-info">
                    <div class="pat-av"><?=$init?></div>
                    <div>
                        <div class="pat-name"><?=htmlspecialchars($rdv['p_prenom'].' '.$rdv['p_nom'])?></div>
                        <div class="pat-meta"><?=$age?> ans • <?=$rdv['groupe_sanguin'] ? 'Groupe '.$rdv['groupe_sanguin'] : ''?> • <?=!empty($rdv['allergies']) ? '⚠️ Allergies' : 'Aucune allergie'?></div>
                    </div>
                </div>
                <span class="badge <?=$badge_class?>"><?=$badge_label?></span>
            </div>
            <div class="rdv-details">
                <div class="rd">📅 <strong><?=date('D. d M. Y', strtotime($rdv['date_rdv']))?></strong></div>
                <div class="rd">🕐 <strong><?=substr($rdv['heure_rdv'],0,5)?></strong> (30 min)</div>
                <div class="rd">📍 <?=htmlspecialchars($medecin['cabinet'])?>, <?=htmlspecialchars($medecin['ville'])?></div>
            </div>
            <div class="rdv-motif">📝 Motif : <?=htmlspecialchars($rdv['motif'])?></div>
            
            <div class="rdv-actions">
                <?php if($rdv['statut'] === 'en_attente'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="rdv_id" value="<?=$rdv['id']?>">
                        <input type="hidden" name="action" value="confirmer">
                        <button type="submit" class="btn-confirm">✅ Confirmer</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="rdv_id" value="<?=$rdv['id']?>">
                        <input type="hidden" name="action" value="refuser">
                        <input type="hidden" name="message" value="Désolé, je ne suis pas disponible à ce créneau.">
                        <button type="submit" class="btn-refuse" onclick="return confirm('Refuser ce rendez-vous ?')">❌ Refuser</button>
                    </form>
                <?php endif; ?>
                <a href="voir_dossier.php?id=<?=$rdv['id_patient']?>" class="btn-dossier">📋 Voir dossier</a>
                <?php if($rdv['statut'] === 'confirme' || $rdv['statut'] === 'termine'): ?>
                    <a href="compte_rendu.php?id=<?=$rdv['id']?>" class="btn-cr">📝 Compte-rendu</a>
                <?php endif; ?>
                <?php if($rdv['statut'] === 'confirme'): ?>
                    <a href="ordonnance.php?rdv=<?=$rdv['id']?>" class="btn-ordo">✏️ Ordonnance</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Mareme Faye (E5)</strong> — gerer_rdv.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }
</script>
</body>
</html>