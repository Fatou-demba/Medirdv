<?php
// ================================================================
// historique_medecin.php — Historique des patients (Médecin)
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

if (!$medecin) {
    session_destroy();
    redirect('login.php');
}

// ================================================================
// STATISTIQUES
// ================================================================
$stats = [
    'patients_total' => $pdo->query("SELECT COUNT(DISTINCT id_patient) FROM rendezvous WHERE id_medecin = $medecin_id")->fetchColumn(),
    'patients_mois' => $pdo->query("SELECT COUNT(DISTINCT id_patient) FROM rendezvous WHERE id_medecin = $medecin_id AND date_rdv >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
    'consultations' => $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE id_medecin = $medecin_id AND statut = 'termine'")->fetchColumn(),
    'ordonnances' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE id_medecin = $medecin_id AND ordonnance IS NOT NULL AND ordonnance != ''")->fetchColumn(),
];

// ================================================================
// PATIENT SÉLECTIONNÉ
// ================================================================
$patient_id = (int)($_GET['patient'] ?? 0);
$patient_selected = null;
$consultations_patient = [];

if ($patient_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
    $stmt->execute([':id' => $patient_id]);
    $patient_selected = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient_selected) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   r.date_rdv, r.heure_rdv
            FROM consultations c
            JOIN rendezvous r ON r.id = c.id_rendezvous
            WHERE c.id_patient = :patient_id AND c.id_medecin = :medecin_id
            ORDER BY c.date_consultation DESC
            LIMIT 20
        ");
        $stmt->execute([':patient_id' => $patient_id, ':medecin_id' => $medecin_id]);
        $consultations_patient = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// LISTE DES PATIENTS
// ================================================================
$patients = $pdo->query("
    SELECT DISTINCT p.id, p.nom, p.prenom, p.telephone, p.date_naissance,
           COUNT(r.id) AS nb_rdv,
           MAX(r.date_rdv) AS dernier_rdv
    FROM rendezvous r
    JOIN patients p ON p.id = r.id_patient
    WHERE r.id_medecin = $medecin_id AND r.statut = 'termine'
    GROUP BY p.id
    ORDER BY MAX(r.date_rdv) DESC
    LIMIT 20
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
    return $dt->format('d') . ' ' . $mois[$dt->format('F')] . ' ' . $dt->format('Y');
}

function getAge($dateNaissance) {
    if (!$dateNaissance) return '?';
    $now = new DateTime();
    $birth = new DateTime($dateNaissance);
    return $now->diff($birth)->y;
}

function getInitiales($prenom, $nom) {
    return strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));
}

$medecin_initiales = getInitiales($medecin['prenom'], $medecin['nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Historique Patients (Médecin)</title>
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
        .doc-badge{background:rgba(45,198,83,.2);color:var(--green);padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:700}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== PAGE HEADER ===== */
        .page-hdr{background:linear-gradient(135deg,var(--teal),var(--dark));padding:1.8rem 5%;color:#fff}
        .breadcrumb{font-size:.78rem;color:#A5F3FC;margin-bottom:.5rem}
        .breadcrumb span{color:#FDE68A}
        .hdr-inner{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem}
        .page-hdr h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
        .page-hdr p{color:#A5F3FC;font-size:.85rem;margin-top:.3rem}
        .hdr-btn{padding:.55rem 1.2rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}

        /* ===== STATS ===== */
        .stats-bar{background:#fff;border-bottom:1px solid var(--lgray);padding:.9rem 5%;display:flex;gap:1.5rem;flex-wrap:wrap}
        .st{display:flex;align-items:center;gap:.6rem;background:var(--light);border-radius:10px;padding:.55rem 1rem}
        .st-num{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800}
        .st-lbl{font-size:.72rem;color:var(--gray);line-height:1.3}

        /* ===== MAIN ===== */
        .main{display:flex;gap:1.5rem;padding:1.5rem 5%;max-width:1100px;margin:0 auto}

        /* ===== PATIENTS LIST ===== */
        .patients-col{width:300px;flex-shrink:0}
        .search-input{width:100%;padding:.65rem .9rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;margin-bottom:.8rem}
        .search-input:focus{border-color:var(--teal)}
        .pat-item{display:flex;align-items:center;gap:.8rem;padding:.8rem 1rem;background:#fff;border-radius:10px;border:1px solid var(--lgray);margin-bottom:.5rem;cursor:pointer;transition:.15s;text-decoration:none;color:inherit;box-shadow:0 1px 4px rgba(0,0,0,.04)}
        .pat-item:hover{border-color:var(--teal);box-shadow:0 4px 12px rgba(0,0,0,.08)}
        .pat-item.selected{border-color:var(--teal);background:#E0F2F1;box-shadow:0 4px 12px rgba(0,109,119,.15)}
        .pat-av{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;color:#fff}
        .pat-name{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;color:#1E293B}
        .pat-meta{font-size:.75rem;color:var(--gray);margin-top:.15rem}
        .pat-cnt{margin-left:auto;background:var(--light);color:var(--blue);font-size:.72rem;font-weight:700;padding:.2rem .5rem;border-radius:7px;flex-shrink:0}
        .pat-item.selected .pat-cnt{background:var(--teal);color:#fff}

        /* ===== DETAIL COL ===== */
        .detail-col{flex:1}
        .detail-header{background:#fff;border-radius:12px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04);display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
        .d-av{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,var(--teal),var(--blue));display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;color:#fff}
        .d-info h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:800;color:#1E293B}
        .d-info p{font-size:.8rem;color:var(--gray);margin-top:.2rem}
        .d-actions{margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap}
        .d-btn{padding:.45rem 1rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
        .d-btn.blue{background:var(--blue);color:#fff}
        .d-btn.blue:hover{background:var(--dark)}
        .d-btn.green{background:var(--green);color:#fff}
        .d-btn.green:hover{background:#25B347}
        .d-btn.outline{background:transparent;color:var(--teal);border:1.5px solid var(--teal)}
        .d-btn.outline:hover{background:var(--teal);color:#fff}

        /* ===== CONSULT ROW ===== */
        .c-row{background:#fff;border-radius:12px;padding:1rem 1.2rem;border:1px solid var(--lgray);margin-bottom:.7rem;box-shadow:0 2px 6px rgba(0,0,0,.04);transition:.2s}
        .c-row:hover{border-color:#B8D4FF;box-shadow:0 5px 15px rgba(0,0,0,.08)}
        .c-row-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.6rem;flex-wrap:wrap;gap:.4rem}
        .c-date-badge{background:var(--teal);color:#fff;border-radius:8px;padding:.3rem .7rem;font-size:.75rem;font-weight:700;white-space:nowrap}
        .c-row-diag{font-size:.85rem;color:#334155;line-height:1.4;margin-bottom:.5rem;background:var(--light);padding:.5rem .7rem;border-radius:8px;border-left:3px solid var(--teal)}
        .c-row-footer{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
        .c-tag{display:flex;align-items:center;gap:.3rem;font-size:.76rem;color:var(--gray)}
        .c-row-btns{display:flex;gap:.4rem;margin-left:auto;flex-wrap:wrap}
        .cb{padding:.3rem .7rem;border-radius:6px;font-size:.74rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:.2rem}
        .cb.blue{background:var(--blue);color:#fff}
        .cb.blue:hover{background:var(--dark)}
        .cb.purple{background:var(--purple);color:#fff}
        .cb.purple:hover{background:#5B0FA0}
        .cb.teal{background:var(--teal);color:#fff}
        .cb.teal:hover{background:var(--dark)}

        /* ===== EMPTY ===== */
        .empty-state{text-align:center;padding:2rem;background:#fff;border-radius:12px;border:1px solid var(--lgray)}
        .empty-state .icon{font-size:3rem;margin-bottom:.5rem;opacity:.5}
        .empty-state p{color:var(--gray);font-size:.85rem}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .main{flex-direction:column}
            .patients-col{width:100%}
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
            .detail-header{flex-direction:column;text-align:center}
            .d-actions{margin-left:0;justify-content:center}
            .c-row-top{flex-direction:column;align-items:stretch}
            .c-row-footer{flex-direction:column;align-items:stretch}
            .c-row-btns{margin-left:0;justify-content:center}
        }
        @media(max-width:480px){
            .page-hdr{padding:1.2rem 4%}
            .page-hdr h1{font-size:1.1rem}
            .main{padding:1rem 4%}
            .pat-item{padding:.6rem .8rem}
            .pat-av{width:32px;height:32px;font-size:.9rem}
            .pat-name{font-size:.78rem}
            .d-av{width:44px;height:44px;font-size:1.2rem}
            .d-info h3{font-size:.85rem}
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
        <li><a href="dashboard_medecin.php">Mon Agenda</a></li>
        <li><a href="gerer_rdv.php">Gérer RDV</a></li>
        <li><a href="historique_medecin.php" class="active">Mes Patients</a></li>
        <li><a href="ordonnance.php">Ordonnances</a></li>
    </ul>
    <div class="doc-badge">👨‍⚕️ Dr. <?=htmlspecialchars($medecin['prenom'])?></div>
</nav>

<!-- ===== PAGE HEADER ===== -->
<div class="page-hdr">
    <div class="breadcrumb">Tableau de bord → <span>Historique Patients</span></div>
    <div class="hdr-inner">
        <div>
            <h1>👥 Historique de mes Patients</h1>
            <p>Consultez l'historique complet de tous vos patients et leurs consultations</p>
        </div>
        <a href="export_patients.php" class="hdr-btn">📥 Exporter liste patients</a>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-bar">
    <div class="st"><div class="st-num" style="color:var(--blue)"><?=$stats['patients_total']?></div><div class="st-lbl">Patients<br>total</div></div>
    <div class="st"><div class="st-num" style="color:var(--green)"><?=$stats['patients_mois']?></div><div class="st-lbl">Patients<br>ce mois</div></div>
    <div class="st"><div class="st-num" style="color:var(--teal)"><?=$stats['consultations']?></div><div class="st-lbl">Consultations<br>total</div></div>
    <div class="st"><div class="st-num" style="color:var(--purple)"><?=$stats['ordonnances']?></div><div class="st-lbl">Ordonnances<br>émises</div></div>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <!-- ===== PATIENTS LIST ===== -->
    <div class="patients-col">
        <input type="text" class="search-input" id="searchPatient" placeholder="🔍 Rechercher un patient..." onkeyup="filterPatients()">

        <div style="font-size:.78rem;font-weight:700;color:var(--gray);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.5px">Patients récents</div>

        <?php foreach($patients as $p): 
            $is_selected = $patient_id == $p['id'];
            $couleurs_avatar = ['#2DC653','#0077B6','#7209B7','#F77F00','#D62828','#006D77'];
            $idx = $p['id'] % count($couleurs_avatar);
        ?>
        <a href="?patient=<?=$p['id']?>" class="pat-item <?=$is_selected?'selected':''?>" data-name="<?=strtolower($p['prenom'].' '.$p['nom'])?>">
            <div class="pat-av" style="background:<?=$couleurs_avatar[$idx]?>">👤</div>
            <div style="flex:1">
                <div class="pat-name"><?=htmlspecialchars($p['prenom'].' '.$p['nom'])?></div>
                <div class="pat-meta"><?=getAge($p['date_naissance'])?> ans • Dernière: <?=formatDate($p['dernier_rdv'])?></div>
            </div>
            <div class="pat-cnt"><?=$p['nb_rdv']?></div>
        </a>
        <?php endforeach; ?>
        
        <?php if(empty($patients)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <p>Aucun patient pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== DETAIL — PATIENT SELECTIONNÉ ===== -->
    <div class="detail-col">
        <?php if($patient_selected): ?>
            <!-- PATIENT HEADER -->
            <div class="detail-header">
                <div class="d-av">👤</div>
                <div class="d-info">
                    <h3><?=htmlspecialchars($patient_selected['prenom'].' '.$patient_selected['nom'])?> — <?=count($consultations_patient)?> consultations</h3>
                    <p>
                        <?=getAge($patient_selected['date_naissance'])?> ans • <?=htmlspecialchars($patient_selected['telephone'])?> • 
                        <?=$patient_selected['groupe_sanguin'] ? 'Groupe '.$patient_selected['groupe_sanguin'] : ''?>
                        <?php 
                        $dossier = $pdo->query("SELECT allergies FROM dossiers WHERE id_patient = ".$patient_selected['id'])->fetch();
                        if($dossier && !empty($dossier['allergies'])): 
                        ?>
                            • ⚠️ Allergie <?=htmlspecialchars($dossier['allergies'])?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-actions">
                    <a href="voir_dossier.php?id=<?=$patient_selected['id']?>" class="d-btn blue">📋 Voir dossier</a>
                    <a href="prendre_rdv.php?patient=<?=$patient_selected['id']?>" class="d-btn green">➕ Nouvelle cons.</a>
                    <a href="ordonnance.php?patient=<?=$patient_selected['id']?>" class="d-btn outline">💊 Ordonnance</a>
                </div>
            </div>

            <!-- FILTER ROW -->
            <div style="display:flex;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center">
                <select style="padding:.45rem .8rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.82rem;outline:none;background:#fff">
                    <option>Toutes les années</option>
                    <option selected><?=date('Y')?></option>
                    <option><?=date('Y')-1?></option>
                </select>
                <input type="text" placeholder="Rechercher dans les consultations..." style="flex:1;min-width:200px;padding:.45rem .9rem;border:1.5px solid var(--lgray);border-radius:8px;font-size:.82rem;outline:none">
                <span style="font-size:.82rem;color:var(--gray)"><strong><?=count($consultations_patient)?></strong> consultations</span>
            </div>

            <?php if(empty($consultations_patient)): ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <p>Aucune consultation pour ce patient.</p>
                </div>
            <?php else: ?>
                <?php foreach($consultations_patient as $c): ?>
                <div class="c-row">
                    <div class="c-row-top">
                        <div>
                            <span class="c-date-badge"><?=formatDate($c['date_consultation'])?></span>
                            <span style="font-size:.78rem;color:var(--gray);margin-left:.6rem">⏱ <?=$c['heure_rdv'] ? substr($c['heure_rdv'],0,5) : '30 min'?></span>
                        </div>
                        <span style="font-size:.78rem;font-weight:700;color:var(--green);background:#F0FDF4;padding:.2rem .6rem;border-radius:5px;border:1px solid #BBF7D0">✅ Consultée</span>
                    </div>
                    <?php if(!empty($c['diagnostic'])): ?>
                    <div class="c-row-diag">🔬 <strong>Diagnostic :</strong> <?=htmlspecialchars($c['diagnostic'])?></div>
                    <?php endif; ?>
                    <div class="c-row-footer">
                        <?php if(!empty($c['traitement'])): ?>
                            <span class="c-tag">💊 <?=htmlspecialchars($c['traitement'])?></span>
                        <?php endif; ?>
                        <div class="c-row-btns">
                            <a href="detail_rdv.php?id=<?=$c['id_rendezvous']?>" class="cb blue">Voir CR</a>
                            <?php if(!empty($c['ordonnance'])): ?>
                                <a href="ordonnance.php?id=<?=$c['id_rendezvous']?>" class="cb purple">Ordonnance</a>
                            <?php endif; ?>
                            <a href="prendre_rdv.php?patient=<?=$patient_selected['id']?>" class="cb teal">+ Nouvelle cons.</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php else: ?>
            <!-- Aucun patient sélectionné -->
            <div class="empty-state">
                <div class="icon">👤</div>
                <h3 style="font-family:'Sora',sans-serif;color:var(--dark);margin-bottom:.4rem;">Sélectionnez un patient</h3>
                <p>Choisissez un patient dans la liste à gauche pour voir son historique.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Aminata Layi Gueye (E6)</strong> — historique_medecin.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    function filterPatients() {
        const search = document.getElementById('searchPatient').value.toLowerCase();
        const items = document.querySelectorAll('.pat-item');
        items.forEach(function(item) {
            const name = item.getAttribute('data-name') || '';
            if (name.includes(search)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>
</body>
</html>