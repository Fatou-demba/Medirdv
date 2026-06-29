<?php
// ================================================================
// voir_dossier.php — Vue du dossier patient par le médecin
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!isLoggedIn() || getRole() !== 'medecin') {
    redirect('login.php');
}

$medecin_id = $_SESSION['user_id'];

// Récupérer l'ID du patient depuis l'URL
$patient_id = (int)($_GET['id'] ?? 0);
if ($patient_id <= 0) {
    redirect('mes_patients.php');
}

// Récupérer les infos du médecin
$stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id AND statut = 'valide' LIMIT 1");
$stmt->execute([':id' => $medecin_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$medecin) {
    session_destroy();
    redirect('login.php');
}

// Récupérer les infos du patient
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    redirect('mes_patients.php?error=Patient introuvable');
}

// Journaliser l'accès
try {
    $stmt = $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, id_dossier, ip_address) 
                           VALUES (:id, 'medecin', 'consultation_dossier', :dossier_id, :ip)");
    $dossier_id = $pdo->query("SELECT id FROM dossiers WHERE id_patient = $patient_id")->fetchColumn() ?: 0;
    $stmt->execute([
        ':id' => $medecin_id,
        ':dossier_id' => $dossier_id,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
} catch(Exception $e) {}

// Récupérer le dossier médical
$stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id_patient = :id LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les consultations
$stmt = $pdo->prepare("
    SELECT c.*, 
           m.nom AS m_nom, m.prenom AS m_prenom, m.specialite,
           r.date_rdv, r.heure_rdv
    FROM consultations c
    JOIN medecins m ON m.id = c.id_medecin
    JOIN rendezvous r ON r.id = c.id_rendezvous
    WHERE c.id_patient = :id
    ORDER BY c.date_consultation DESC
    LIMIT 10
");
$stmt->execute([':id' => $patient_id]);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les RDV
$stmt = $pdo->prepare("
    SELECT r.*, m.nom AS m_nom, m.prenom AS m_prenom, m.specialite
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    WHERE r.id_patient = :id
    ORDER BY r.date_rdv DESC
    LIMIT 5
");
$stmt->execute([':id' => $patient_id]);
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer l'âge
$date_naissance = new DateTime($patient['date_naissance']);
$today = new DateTime();
$age = $date_naissance->diff($today)->y;

// Calculer l'IMC
$imc = null;
if ($patient['taille'] && $patient['poids']) {
    $taille_m = $patient['taille'] / 100;
    $imc = round($patient['poids'] / ($taille_m * $taille_m), 1);
}

// Formater les données du dossier
$allergies = !empty($dossier['allergies']) ? explode(',', $dossier['allergies']) : [];
$maladies_chroniques = !empty($dossier['maladies_chroniques']) ? explode(',', $dossier['maladies_chroniques']) : [];
$antecedents = !empty($dossier['antecedents']) ? explode(',', $dossier['antecedents']) : [];
$chirurgies = !empty($dossier['chirurgies']) ? explode(',', $dossier['chirurgies']) : [];
$traitements = !empty($dossier['traitements_en_cours']) ? explode(',', $dossier['traitements_en_cours']) : [];

// Fonctions helpers
function getInitiales($prenom, $nom) {
    return strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));
}
function formatDate($date) {
    if (!$date) return '—';
    $dt = new DateTime($date);
    $mois = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril',
             'May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août',
             'September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
    return $dt->format('d') . ' ' . $mois[$dt->format('F')] . ' ' . $dt->format('Y');
}
function getAge($dateNaissance) {
    $now = new DateTime();
    $birth = new DateTime($dateNaissance);
    return $now->diff($birth)->y;
}

$medecin_initiales = getInitiales($medecin['prenom'], $medecin['nom']);
$patient_initiales = getInitiales($patient['prenom'], $patient['nom']);

// Compteur de RDV
$nb_consultations = count($consultations);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Dossier Patient (Médecin)</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--light:#F0F4FF;--gray:#64748B;--lgray:#E2E8F0}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .badge-role{background:rgba(45,198,83,.2);color:var(--green);padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:700}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== HEADER ===== */
        .page-hdr{background:linear-gradient(135deg,#006D77,var(--blue));padding:1.8rem 5%;color:#fff}
        .breadcrumb{font-size:.78rem;color:#A5F3FC;margin-bottom:.5rem}
        .breadcrumb span{color:#FDE68A}
        .hdr-inner{display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap}
        .pat-avatar{width:68px;height:68px;border-radius:16px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;border:2px solid rgba(255,255,255,.3);overflow:hidden}
        .pat-avatar img{width:100%;height:100%;object-fit:cover}
        .pat-info h1{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800}
        .pat-info p{color:#A5F3FC;font-size:.85rem;margin-top:.3rem}
        .access-badge{display:inline-flex;align-items:center;gap:.4rem;background:rgba(249,168,37,.2);border:1px solid rgba(249,168,37,.3);color:#FDE68A;padding:.3rem .8rem;border-radius:20px;font-size:.75rem;font-weight:600;margin-top:.5rem}
        .hdr-btns{margin-left:auto;display:flex;gap:.6rem;flex-wrap:wrap}
        .hdr-btn{padding:.55rem 1.1rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}
        .hdr-btn.primary{background:var(--green);border-color:var(--green)}
        .hdr-btn.primary:hover{background:#25B347}

        /* ===== VITALS ===== */
        .vitals{background:#fff;border-bottom:1px solid var(--lgray);padding:.8rem 5%;display:flex;gap:2rem;overflow-x:auto}
        .v{display:flex;align-items:center;gap:.6rem;white-space:nowrap}
        .v-ic{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
        .v-val{font-family:'Sora',sans-serif;font-size:1rem;font-weight:800;color:var(--dark)}
        .v-lbl{font-size:.7rem;color:var(--gray)}

        /* ===== ALERT ALLERGIE ===== */
        .alert-allerg{background:#FFF5F5;border:1.5px solid #FECACA;border-radius:10px;padding:.8rem 1rem;display:flex;align-items:center;gap:.7rem;margin:1rem 5%;font-size:.85rem;color:var(--red);font-weight:600;flex-wrap:wrap}

        /* ===== MAIN ===== */
        .main{display:flex;gap:1.5rem;padding:1rem 5% 2rem;max-width:1100px;margin:0 auto}

        /* ===== LEFT ===== */
        .left{width:270px;flex-shrink:0}
        .info-box{background:#fff;border-radius:12px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .info-box h5{font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:var(--dark);margin-bottom:.8rem;padding-bottom:.4rem;border-bottom:1px solid var(--lgray)}
        .irow{display:flex;justify-content:space-between;padding:.3rem 0;font-size:.82rem;border-bottom:1px solid #F8FAFC}
        .irow:last-child{border:none}
        .irow .lbl{color:var(--gray)}.irow .val{font-weight:600;color:#1E293B}

        .warn-box{background:#FFF5F5;border:1px solid #FECACA;border-radius:10px;padding:.8rem;margin-bottom:1rem}
        .warn-box h5{font-size:.82rem;font-weight:700;color:var(--red);margin-bottom:.5rem}
        .warn-item{font-size:.78rem;color:#7F1D1D;padding:.25rem 0;border-bottom:1px solid #FECACA}
        .warn-item:last-child{border:none}

        /* ===== RIGHT ===== */
        .right{flex:1}
        .tabs{display:flex;gap:.3rem;background:#fff;border-radius:12px;padding:.3rem;margin-bottom:1.2rem;border:1px solid var(--lgray);overflow-x:auto}
        .tab{flex:1;padding:.58rem .5rem;text-align:center;border-radius:9px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray);min-width:80px;transition:.2s}
        .tab.active{background:var(--blue);color:#fff}
        .tab:hover:not(.active){background:var(--light)}

        .sec{background:#fff;border-radius:12px;padding:1.3rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .sec-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray);flex-wrap:wrap;gap:.5rem}
        .sec-hd h4{font-family:'Sora',sans-serif;font-size:.92rem;font-weight:700;color:var(--dark)}
        .btn-sm{padding:.38rem .8rem;border:none;border-radius:7px;font-size:.77rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
        .btn-sm.blue{background:var(--blue);color:#fff}
        .btn-sm.green{background:var(--green);color:#fff}
        .btn-sm.outline{background:transparent;color:var(--blue);border:1.5px solid var(--blue)}

        /* ===== ANTECEDENTS ===== */
        .ant{border-left:3px solid var(--purple);padding:.7rem .9rem;background:#FDF4FF;border-radius:0 9px 9px 0;margin-bottom:.6rem}
        .ant.red{border-color:var(--red);background:#FFF5F5}
        .ant.blue2{border-color:var(--blue);background:#EFF6FF}
        .ant.orange{border-color:var(--orange);background:#FFF7ED}
        .ant-t{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--purple);margin-bottom:.2rem}
        .ant.red .ant-t{color:var(--red)}
        .ant.blue2 .ant-t{color:var(--blue)}
        .ant.orange .ant-t{color:var(--orange)}
        .ant-n{font-size:.85rem;font-weight:700;color:#1E293B}
        .ant-d{font-size:.78rem;color:var(--gray);margin-top:.2rem}

        /* ===== CONSULTATIONS ===== */
        .c-item{display:flex;gap:.8rem;align-items:flex-start;padding:.8rem;background:var(--light);border-radius:10px;margin-bottom:.5rem;cursor:pointer;transition:.15s}
        .c-item:hover{background:#EFF6FF}
        .c-dot{width:10px;height:10px;border-radius:50%;background:var(--blue);margin-top:4px;flex-shrink:0}
        .c-date{font-size:.75rem;font-weight:700;color:var(--blue)}
        .c-doc{font-size:.84rem;font-weight:600;color:#1E293B}
        .c-diag{font-size:.78rem;color:var(--gray);margin-top:.1rem}
        .c-arr{margin-left:auto;color:var(--gray);font-size:.85rem}

        /* ===== NOTE MEDECIN ===== */
        .note-area{width:100%;padding:.8rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;resize:vertical;min-height:90px;outline:none}
        .note-area:focus{border-color:var(--green)}

        /* ===== EMPTY STATE ===== */
        .empty-state{text-align:center;padding:1.5rem;color:var(--gray)}
        .empty-state .icon{font-size:2.5rem;margin-bottom:.5rem;opacity:.5}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:1024px){
            .main{flex-direction:column}
            .left{width:100%}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .hdr-btns{margin-left:0;width:100%}
            .hdr-btn{flex:1;justify-content:center}
            .vitals{gap:1rem}
            .tabs{flex-wrap:nowrap}
            .tab{font-size:.72rem;padding:.4rem .5rem;min-width:70px}
            .sec{padding:1rem}
            .hdr-inner{flex-direction:column;text-align:center}
            .pat-avatar{margin:0 auto}
            .pat-info{text-align:center}
        }
        @media(max-width:480px){
            .page-hdr{padding:1.2rem 4%}
            .pat-info h1{font-size:1.1rem}
            .vitals{padding:.6rem 4%;gap:.8rem}
            .v{gap:.4rem}
            .v-ic{width:30px;height:30px;font-size:.8rem}
            .v-val{font-size:.85rem}
            .v-lbl{font-size:.6rem}
            .alert-allerg{font-size:.78rem;padding:.6rem .8rem}
            .ant{padding:.5rem .7rem}
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
        <li><a href="mon_agenda.php">Mon Agenda</a></li>
        <li><a href="mes_patients.php">Mes Patients</a></li>
    </ul>
    <div class="badge-role">👨‍⚕️ Dr. <?=htmlspecialchars($medecin['prenom'])?></div>
</nav>

<!-- ===== HEADER ===== -->
<div class="page-hdr">
    <div class="breadcrumb">Agenda → <span>Dossier Patient</span></div>
    <div class="hdr-inner">
        <div class="pat-avatar">
            <?php if(!empty($patient['photo']) && file_exists(__DIR__.'/../assets/uploads/'.$patient['photo'])): ?>
                <img src="../assets/uploads/<?=htmlspecialchars($patient['photo'])?>" alt="">
            <?php else: ?>
                👤
            <?php endif; ?>
        </div>
        <div class="pat-info">
            <h1>Dossier de <?=htmlspecialchars($patient['prenom'].' '.$patient['nom'])?></h1>
            <p>Patient depuis <?=date('Y', strtotime($patient['date_inscription']))?> • <?=$nb_consultations?> consultations</p>
            <div class="access-badge">👨‍⚕️ Accès médecin — lecture + écriture</div>
        </div>
        <div class="hdr-btns">
            <a href="imprimer_dossier.php?id=<?=$patient_id?>" class="hdr-btn">📄 Exporter PDF</a>
            <a href="compte_rendu.php?patient=<?=$patient_id?>" class="hdr-btn primary">+ Nouvelle ordonnance</a>
        </div>
    </div>
</div>

<!-- ===== VITALS ===== -->
<div class="vitals">
    <div class="v">
        <div class="v-ic" style="background:#EFF6FF">🩸</div>
        <div><div class="v-val"><?=$patient['groupe_sanguin'] ?: '—'?></div><div class="v-lbl">Groupe sanguin</div></div>
    </div>
    <div class="v">
        <div class="v-ic" style="background:#F0FDF4">📏</div>
        <div><div class="v-val"><?=$patient['taille'] ? (int)$patient['taille'].' cm' : '—'?></div><div class="v-lbl">Taille</div></div>
    </div>
    <div class="v">
        <div class="v-ic" style="background:#FFF7ED">⚖️</div>
        <div><div class="v-val"><?=$patient['poids'] ? (int)$patient['poids'].' kg' : '—'?></div><div class="v-lbl">Poids</div></div>
    </div>
    <div class="v">
        <div class="v-ic" style="background:#FDF4FF">📊</div>
        <div><div class="v-val"><?=$imc ?: '—'?></div><div class="v-lbl">IMC</div></div>
    </div>
    <?php if(!empty($allergies)): ?>
    <div class="v">
        <div class="v-ic" style="background:#FFF5F5">⚠️</div>
        <div><div class="v-val" style="color:var(--red)"><?=htmlspecialchars($allergies[0])?></div><div class="v-lbl">⚠️ ALLERGIE</div></div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== ALLERGIE ALERT ===== -->
<?php if(!empty($allergies)): ?>
<div class="alert-allerg">
    ⚠️ ATTENTION : Patient allergique <?php foreach($allergies as $a): ?> à la <strong><?=htmlspecialchars(trim($a))?></strong> <?php endforeach; ?> — Ne pas prescrire !
</div>
<?php endif; ?>

<!-- ===== MAIN ===== -->
<div class="main">
    <!-- ===== LEFT ===== -->
    <div class="left">
        <div class="info-box">
            <h5>👤 Patient</h5>
            <div class="irow"><span class="lbl">Nom</span><span class="val"><?=htmlspecialchars($patient['nom'])?></span></div>
            <div class="irow"><span class="lbl">Prénom</span><span class="val"><?=htmlspecialchars($patient['prenom'])?></span></div>
            <div class="irow"><span class="lbl">Âge</span><span class="val"><?=$age?> ans</span></div>
            <div class="irow"><span class="lbl">Tél</span><span class="val"><?=htmlspecialchars($patient['telephone'])?></span></div>
            <div class="irow"><span class="lbl">Email</span><span class="val" style="font-size:.72rem;"><?=htmlspecialchars($patient['email'])?></span></div>
            <div class="irow"><span class="lbl">Dernier RDV</span><span class="val"><?=!empty($rdvs) ? formatDate($rdvs[0]['date_rdv']) : '—'?></span></div>
        </div>

        <?php if(!empty($allergies)): ?>
        <div class="warn-box">
            <h5>⚠️ Allergies connues</h5>
            <?php foreach($allergies as $a): ?>
                <div class="warn-item">🚫 <?=htmlspecialchars(trim($a))?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if(!empty($traitements)): ?>
        <div class="info-box">
            <h5>🔄 Traitements en cours</h5>
            <?php foreach($traitements as $t): ?>
                <div class="irow"><span class="lbl"><?=htmlspecialchars(trim($t))?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== RIGHT ===== -->
    <div class="right">
        <div class="tabs">
            <button class="tab active" data-tab="antecedents">🦠 Antécédents</button>
            <button class="tab" data-tab="consultations">📅 Consultations</button>
            <button class="tab" data-tab="ordonnances">💊 Ordonnances</button>
            <button class="tab" data-tab="notes">📝 Mes notes</button>
        </div>

        <!-- ===== ANTECEDENTS ===== -->
        <div class="sec tab-content" id="antecedents">
            <div class="sec-hd">
                <h4>🦠 Antécédents médicaux</h4>
                <a href="ajouter_antecedent.php?patient=<?=$patient_id?>" class="btn-sm blue">+ Ajouter</a>
            </div>

            <?php if(empty($antecedents) && empty($allergies) && empty($maladies_chroniques) && empty($chirurgies)): ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <p>Aucun antécédent enregistré.</p>
                </div>
            <?php else: ?>
                <?php foreach($allergies as $a): ?>
                    <?php if(trim($a)): ?>
                    <div class="ant red">
                        <div class="ant-t">⚠️ ALLERGIE</div>
                        <div class="ant-n"><?=htmlspecialchars(trim($a))?></div>
                        <div class="ant-d">Allergie médicamenteuse ou alimentaire</div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php foreach($maladies_chroniques as $m): ?>
                    <?php if(trim($m)): ?>
                    <div class="ant orange">
                        <div class="ant-t">🔄 Maladie chronique</div>
                        <div class="ant-n"><?=htmlspecialchars(trim($m))?></div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php foreach($chirurgies as $c): ?>
                    <?php if(trim($c)): ?>
                    <div class="ant blue2">
                        <div class="ant-t">🔪 Chirurgie</div>
                        <div class="ant-n"><?=htmlspecialchars(trim($c))?></div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php foreach($antecedents as $a): ?>
                    <?php if(trim($a)): ?>
                    <div class="ant">
                        <div class="ant-t">📋 Antécédent</div>
                        <div class="ant-n"><?=htmlspecialchars(trim($a))?></div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ===== CONSULTATIONS ===== -->
        <div class="sec tab-content" id="consultations" style="display:none;">
            <div class="sec-hd">
                <h4>📅 Historique consultations</h4>
                <a href="historique_medecin.php?patient=<?=$patient_id?>" style="font-size:.8rem;color:var(--blue);font-weight:600;text-decoration:none;">Tout voir →</a>
            </div>

            <?php if(empty($consultations)): ?>
                <div class="empty-state">
                    <div class="icon">📅</div>
                    <p>Aucune consultation.</p>
                </div>
            <?php else: ?>
                <?php foreach($consultations as $c): ?>
                <div class="c-item" onclick="window.location='detail_rdv.php?id=<?=$c['id_rendezvous']?>'">
                    <div class="c-dot"></div>
                    <div style="flex:1">
                        <div class="c-date"><?=formatDate($c['date_consultation'])?></div>
                        <div class="c-doc">Dr. <?=htmlspecialchars($c['m_prenom'].' '.$c['m_nom'])?> — <?=htmlspecialchars($c['specialite'])?></div>
                        <?php if(!empty($c['diagnostic'])): ?>
                            <div class="c-diag"><?=htmlspecialchars(mb_substr($c['diagnostic'],0,80)).(mb_strlen($c['diagnostic'])>80?'...':'')?></div>
                        <?php endif; ?>
                    </div>
                    <div class="c-arr">›</div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ===== ORDONNANCES ===== -->
        <div class="sec tab-content" id="ordonnances" style="display:none;">
            <div class="sec-hd">
                <h4>💊 Ordonnances</h4>
            </div>

            <?php if(empty($rdvs)): ?>
                <div class="empty-state">
                    <div class="icon">💊</div>
                    <p>Aucune ordonnance.</p>
                </div>
            <?php else: ?>
                <?php foreach($rdvs as $rdv): ?>
                    <?php if($rdv['statut'] === 'termine' || $rdv['statut'] === 'confirme'): ?>
                    <div style="border:1px solid var(--lgray);border-radius:10px;padding:.8rem;margin-bottom:.6rem;">
                        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.3rem;">
                            <span style="font-weight:700;font-size:.82rem;">Ordonnance — Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?></span>
                            <small style="font-size:.75rem;color:var(--gray);"><?=formatDate($rdv['date_rdv'])?></small>
                        </div>
                        <?php if(!empty($rdv['motif'])): ?>
                            <div style="font-size:.78rem;color:var(--gray);margin:.3rem 0;">Motif : <?=htmlspecialchars(mb_substr($rdv['motif'],0,60)).(mb_strlen($rdv['motif'])>60?'...':'')?></div>
                        <?php endif; ?>
                        <div style="display:flex;gap:.4rem;margin-top:.5rem;flex-wrap:wrap;">
                            <a href="ordonnance.php?id=<?=$rdv['id']?>" class="btn-sm blue" style="font-size:.7rem;">📄 Voir PDF</a>
                            <button class="btn-sm outline" style="font-size:.7rem;" onclick="window.print()">🖨️ Imprimer</button>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ===== NOTES ===== -->
        <div class="sec tab-content" id="notes" style="display:none;">
            <div class="sec-hd">
                <h4>📝 Ajouter une note médicale</h4>
            </div>
            <form method="POST" action="ajouter_note.php">
                <input type="hidden" name="patient_id" value="<?=$patient_id?>">
                <textarea class="note-area" name="note" placeholder="Ajoutez vos observations, remarques médicales pour ce patient..."></textarea>
                <div style="display:flex;gap:.6rem;margin-top:.8rem;flex-wrap:wrap;">
                    <button type="submit" class="btn-sm green">💾 Sauvegarder la note</button>
                    <button type="reset" class="btn-sm outline">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Fatou Yankaty (E4)</strong> — voir_dossier.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    // TABS
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(function(s) { s.style.display = 'none'; });
            document.getElementById(this.getAttribute('data-tab')).style.display = 'block';
        });
    });
</script>
</body>
</html>