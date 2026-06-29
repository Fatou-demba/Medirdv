<?php
// ================================================================
// imprimer_dossier.php — Export PDF / Impression du dossier
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('login.php');
}

$patient_id = (int)($_GET['id'] ?? 0);
if ($patient_id <= 0) {
    redirect('dashboard_patient.php');
}

$role = getRole();

// Récupérer les infos du patient
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    redirect('dashboard_patient.php');
}

// Vérifier les droits d'accès
if ($role === 'patient' && $_SESSION['user_id'] != $patient_id) {
    redirect('dashboard_patient.php');
}

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
");
$stmt->execute([':id' => $patient_id]);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les RDV terminés
$stmt = $pdo->prepare("
    SELECT r.*, m.nom AS m_nom, m.prenom AS m_prenom, m.specialite
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    WHERE r.id_patient = :id AND r.statut = 'termine'
    ORDER BY r.date_rdv DESC
");
$stmt->execute([':id' => $patient_id]);
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculs
$date_naissance = new DateTime($patient['date_naissance']);
$today = new DateTime();
$age = $date_naissance->diff($today)->y;

$taille_m = $patient['taille'] ? $patient['taille'] / 100 : null;
$imc = ($patient['taille'] && $patient['poids']) ? round($patient['poids'] / ($taille_m * $taille_m), 1) : null;

// Formater les données
$allergies = !empty($dossier['allergies']) ? explode(',', $dossier['allergies']) : [];
$maladies_chroniques = !empty($dossier['maladies_chroniques']) ? explode(',', $dossier['maladies_chroniques']) : [];
$antecedents = !empty($dossier['antecedents']) ? explode(',', $dossier['antecedents']) : [];
$chirurgies = !empty($dossier['chirurgies']) ? explode(',', $dossier['chirurgies']) : [];
$traitements = !empty($dossier['traitements_en_cours']) ? explode(',', $dossier['traitements_en_cours']) : [];

function formatDate($date) {
    if (!$date) return '—';
    $dt = new DateTime($date);
    $mois = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril',
             'May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août',
             'September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
    return $dt->format('d') . ' ' . $mois[$dt->format('F')] . ' ' . $dt->format('Y');
}

// Si export PDF est demandé
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../fpdf/fpdf.php';
    
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(0, 119, 182);
            $this->Cell(0, 10, 'MediRDV', 0, 1, 'L');
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 6, 'Plateforme de Sante Numerique - UNCHK', 0, 1, 'L');
            $this->Ln(5);
            $this->SetDrawColor(0, 119, 182);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(8);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(150);
            $this->Cell(0, 10, 'Document genere par MediRDV - Confidentiel - Usage medical uniquement', 0, 0, 'C');
            $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'R');
        }
    }
    
    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 11);
    
    // Titre
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'DOSSIER MEDICAL PATIENT', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100);
    $pdf->Cell(0, 6, 'Date d\'impression : ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Cell(0, 6, 'Ref : DMR-' . date('Y') . '-' . str_pad($patient['id'], 5, '0', STR_PAD_LEFT), 0, 1, 'C');
    $pdf->Ln(8);
    
    // Infos patient
    $pdf->SetFillColor(3, 4, 94);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 12, 'INFORMATIONS PATIENT', 0, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(240, 244, 255);
    $pdf->SetFont('Arial', '', 11);
    
    $infos = [
        ['Nom', $patient['nom']],
        ['Prenom', $patient['prenom']],
        ['Date de naissance', date('d/m/Y', strtotime($patient['date_naissance']))],
        ['Age', $age . ' ans'],
        ['Telephone', $patient['telephone']],
        ['Email', $patient['email']],
        ['Groupe sanguin', $patient['groupe_sanguin'] ?: 'Non renseigne'],
        ['Taille', $patient['taille'] ? (int)$patient['taille'] . ' cm' : 'Non renseigne'],
        ['Poids', $patient['poids'] ? (int)$patient['poids'] . ' kg' : 'Non renseigne'],
        ['IMC', $imc ?: 'Non calcule'],
    ];
    
    foreach ($infos as $i => $info) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(45, 8, $info[0] . ' :', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 8, $info[1], 0, 1);
    }
    $pdf->Ln(4);
    
    // Allergies
    if (!empty($allergies)) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(214, 40, 40);
        $pdf->Cell(0, 8, '⚠️ ALLERGIES :', 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 10);
        foreach ($allergies as $a) {
            $pdf->Cell(10, 6, '', 0, 0);
            $pdf->Cell(0, 6, '- ' . trim($a), 0, 1);
        }
        $pdf->Ln(4);
    }
    
    // Antecedents
    if (!empty($antecedents) || !empty($maladies_chroniques) || !empty($chirurgies)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, 'ANTECEDENTS MEDICAUX', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        
        foreach ($antecedents as $a) {
            if (trim($a)) $pdf->Cell(0, 6, '- ' . trim($a), 0, 1);
        }
        foreach ($maladies_chroniques as $m) {
            if (trim($m)) $pdf->Cell(0, 6, '- ' . trim($m) . ' (Maladie chronique)', 0, 1);
        }
        foreach ($chirurgies as $c) {
            if (trim($c)) $pdf->Cell(0, 6, '- ' . trim($c) . ' (Chirurgie)', 0, 1);
        }
        $pdf->Ln(4);
    }
    
    // Traitements
    if (!empty($traitements)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'TRAITEMENTS EN COURS', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        foreach ($traitements as $t) {
            if (trim($t)) $pdf->Cell(0, 6, '- ' . trim($t), 0, 1);
        }
        $pdf->Ln(4);
    }
    
    // Consultations
    if (!empty($consultations)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'HISTORIQUE DES CONSULTATIONS', 0, 1);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(35, 7, 'Date', 1, 0, 'C');
        $pdf->Cell(50, 7, 'Medecin', 1, 0, 'C');
        $pdf->Cell(40, 7, 'Specialite', 1, 0, 'C');
        $pdf->Cell(65, 7, 'Diagnostic', 1, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        
        foreach (array_slice($consultations, 0, 10) as $c) {
            $pdf->Cell(35, 6, formatDate($c['date_consultation']), 1, 0);
            $pdf->Cell(50, 6, 'Dr. ' . $c['m_prenom'] . ' ' . $c['m_nom'], 1, 0);
            $pdf->Cell(40, 6, $c['specialite'] ?: '—', 1, 0);
            $pdf->Cell(65, 6, mb_substr($c['diagnostic'] ?: '—', 0, 40), 1, 1);
        }
        $pdf->Ln(4);
    }
    
    // Signature
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(80, 10, 'Signature du patient : _________________', 0, 0);
    $pdf->Cell(0, 10, 'Cachet / Signature medecin : _________________', 0, 1);
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(100);
    $pdf->Cell(0, 6, 'Document genere par MediRDV - ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Cell(0, 6, 'Universite Numerique Cheikh Hamidou Kane - Confidentiel - Usage medical uniquement', 0, 1, 'C');
    
    $pdf->Output('D', 'Dossier_Medical_' . $patient['nom'] . '_' . $patient['prenom'] . '.pdf');
    exit();
}

// Récupérer les infos de l'utilisateur connecté pour la navbar
if ($role === 'patient') {
    $user = $patient;
} else {
    $stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$initiales = strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Export Dossier PDF</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--purple:#7209B7;--red:#D62828;--gray:#64748B;--lgray:#E2E8F0}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== CONTROLS ===== */
        .controls{background:#fff;border-bottom:1px solid var(--lgray);padding:1rem 5%;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
        .controls h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:var(--dark);margin-right:auto}
        .btn-print{padding:.6rem 1.4rem;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.4rem;text-decoration:none}
        .btn-print:hover{background:var(--dark)}
        .btn-dl{padding:.6rem 1.4rem;background:var(--purple);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.4rem;text-decoration:none}
        .btn-dl:hover{background:#5B0FA0}
        .btn-back{padding:.6rem 1.2rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:8px;font-size:.85rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-back:hover{border-color:var(--blue);color:var(--blue)}

        /* ===== PREVIEW ===== */
        .preview-wrap{max-width:800px;margin:2rem auto;padding:0 5%}

        /* ===== DOSSIER DOC ===== */
        .dossier-doc{background:#fff;border:1px solid var(--lgray);border-radius:4px;box-shadow:0 4px 30px rgba(0,0,0,.12);padding:2.5rem;font-size:.88rem}
        @media print{.controls{display:none}body{background:#fff}.dossier-doc{box-shadow:none;border:none;padding:1rem}}

        /* ===== DOC HEADER ===== */
        .doc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:1.2rem;border-bottom:3px solid var(--blue);flex-wrap:wrap;gap:.5rem}
        .doc-logo{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:var(--blue)}
        .doc-logo span{color:var(--green)}
        .doc-logo p{font-size:.7rem;font-weight:400;color:var(--gray);margin-top:.2rem;font-family:'DM Sans',sans-serif}
        .doc-title{text-align:right}
        .doc-title h2{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark)}
        .doc-title p{font-size:.75rem;color:var(--gray);margin-top:.3rem}
        .confidential{background:#FFF5F5;color:var(--red);border:1px solid #FECACA;padding:.2rem .6rem;border-radius:5px;font-size:.72rem;font-weight:700;display:inline-block;margin-top:.4rem}

        /* ===== PATIENT BANNER ===== */
        .patient-banner{background:linear-gradient(135deg,var(--dark),var(--blue));color:#fff;border-radius:10px;padding:1.2rem;margin-bottom:1.5rem;display:flex;gap:1.5rem;flex-wrap:wrap}
        .pat-av{width:60px;height:60px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0}
        .pat-details{flex:1}
        .pat-details h3{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800}
        .pat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin-top:.6rem}
        .pat-item{font-size:.72rem}
        .pat-item .val{font-weight:700;font-size:.82rem;color:#A5F3FC}
        .pat-item .lbl{color:#A5F3FC;opacity:.8}

        /* ===== VITAL STRIP ===== */
        .vital-strip{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .v-box{flex:1;min-width:80px;text-align:center;background:#F8FAFF;border:1px solid var(--lgray);border-radius:8px;padding:.7rem .5rem}
        .v-box .num{font-family:'Sora',sans-serif;font-size:1.1rem;font-weight:800;color:var(--dark)}
        .v-box .lbl{font-size:.68rem;color:var(--gray);margin-top:.2rem}
        .v-box.alert{background:#FFF5F5;border-color:#FECACA}
        .v-box.alert .num{color:var(--red)}

        /* ===== SECTIONS ===== */
        .doc-section{margin-bottom:1.5rem}
        .doc-section h4{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:800;color:var(--dark);background:#F0F4FF;padding:.5rem .8rem;border-radius:6px;margin-bottom:.8rem;border-left:3px solid var(--blue)}
        .doc-table{width:100%;border-collapse:collapse;font-size:.82rem}
        .doc-table th{background:#F8FAFF;padding:.5rem .7rem;text-align:left;font-weight:700;color:var(--dark);border-bottom:2px solid var(--lgray)}
        .doc-table td{padding:.5rem .7rem;border-bottom:1px solid #F1F5F9;color:#334155}
        .doc-table tr:last-child td{border:none}
        .badge-type{padding:.15rem .5rem;border-radius:4px;font-size:.7rem;font-weight:700;display:inline-block}

        /* ===== SIGNATURE ===== */
        .signature-section{display:flex;justify-content:space-between;margin-top:2rem;padding-top:1rem;border-top:1px solid var(--lgray);flex-wrap:wrap;gap:1rem}
        .sig-box{text-align:center;flex:1;min-width:200px}
        .sig-line{border-bottom:1px solid var(--dark);margin-bottom:.4rem;height:40px}
        .sig-label{font-size:.72rem;color:var(--gray)}

        .doc-footer{text-align:center;font-size:.7rem;color:var(--gray);margin-top:1.5rem;padding-top:.8rem;border-top:1px solid var(--lgray)}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .controls{flex-direction:column;align-items:stretch}
            .controls h3{margin-right:0;text-align:center}
            .btn-print,.btn-dl,.btn-back{justify-content:center}
            .dossier-doc{padding:1.5rem}
            .patient-banner{flex-direction:column;align-items:center;text-align:center}
            .pat-grid{grid-template-columns:1fr 1fr;gap:.3rem}
            .doc-header{flex-direction:column;align-items:center;text-align:center}
            .doc-title{text-align:center}
            .signature-section{flex-direction:column;align-items:center}
            .sig-box{width:100%}
            .doc-table{font-size:.72rem}
            .doc-table th,.doc-table td{padding:.3rem .4rem}
        }
        @media(max-width:480px){
            .dossier-doc{padding:1rem}
            .pat-grid{grid-template-columns:1fr}
            .vital-strip{gap:.5rem}
            .v-box{min-width:60px;padding:.4rem .3rem}
            .v-box .num{font-size:.9rem}
            .v-box .lbl{font-size:.6rem}
            .doc-table{font-size:.65rem;display:block;overflow-x:auto}
        }
    </style>
</head>
<body>

<!-- ===== NAV ===== -->
<nav>
    <a href="<?=$role === 'patient' ? 'dashboard_patient.php' : 'dashboard_medecin.php'?>" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="<?=$role === 'patient' ? 'dashboard_patient.php' : 'dashboard_medecin.php'?>">Accueil</a></li>
        <li><a href="<?=$role === 'patient' ? 'mes_rdv.php' : 'mon_agenda.php'?>">Mes RDV</a></li>
        <li><a href="<?=$role === 'patient' ? 'dossier_patient.php' : 'mes_patients.php'?>">Mon Dossier</a></li>
    </ul>
</nav>

<!-- ===== CONTROLS ===== -->
<div class="controls">
    <h3>📄 Aperçu du dossier avant impression / export</h3>
    <a href="<?=$role === 'patient' ? 'dossier_patient.php' : 'voir_dossier.php?id='.$patient_id?>" class="btn-back">← Retour</a>
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <a href="?id=<?=$patient_id?>&export=pdf" class="btn-dl">📥 Télécharger PDF</a>
</div>

<!-- ===== PREVIEW ===== -->
<div class="preview-wrap">
    <div class="dossier-doc">

        <!-- HEADER -->
        <div class="doc-header">
            <div class="doc-logo">
                Medi<span>RDV</span>
                <p>Plateforme de Santé Numérique<br>Université Numérique Cheikh Hamidou Kane</p>
            </div>
            <div class="doc-title">
                <h2>DOSSIER MÉDICAL PATIENT</h2>
                <p>Date d'impression : <?=date('d/m/Y H:i')?></p>
                <p>Ref : DMR-<?=date('Y')?>-<?=str_pad($patient['id'], 5, '0', STR_PAD_LEFT)?></p>
                <span class="confidential">🔒 CONFIDENTIEL</span>
            </div>
        </div>

        <!-- PATIENT -->
        <div class="patient-banner">
            <div class="pat-av">👤</div>
            <div class="pat-details">
                <h3><?=htmlspecialchars($patient['prenom'].' '.$patient['nom'])?></h3>
                <div class="pat-grid">
                    <div class="pat-item"><div class="lbl">Date naiss.</div><div class="val"><?=date('d/m/Y', strtotime($patient['date_naissance']))?></div></div>
                    <div class="pat-item"><div class="lbl">Âge</div><div class="val"><?=$age?> ans</div></div>
                    <div class="pat-item"><div class="lbl">Téléphone</div><div class="val"><?=htmlspecialchars($patient['telephone'])?></div></div>
                    <div class="pat-item"><div class="lbl">Groupe sanguin</div><div class="val"><?=$patient['groupe_sanguin'] ?: '—'?></div></div>
                </div>
            </div>
        </div>

        <!-- VITALS -->
        <div class="vital-strip">
            <div class="v-box"><div class="num"><?=$patient['groupe_sanguin'] ?: '—'?></div><div class="lbl">Groupe sanguin</div></div>
            <div class="v-box"><div class="num"><?=$patient['taille'] ? (int)$patient['taille'].' cm' : '—'?></div><div class="lbl">Taille</div></div>
            <div class="v-box"><div class="num"><?=$patient['poids'] ? (int)$patient['poids'].' kg' : '—'?></div><div class="lbl">Poids</div></div>
            <div class="v-box"><div class="num"><?=$imc ?: '—'?></div><div class="lbl">IMC</div></div>
            <?php if(!empty($allergies)): ?>
            <div class="v-box alert"><div class="num">⚠️ <?=htmlspecialchars($allergies[0])?></div><div class="lbl">ALLERGIE</div></div>
            <?php endif; ?>
        </div>

        <!-- ANTECEDENTS -->
        <div class="doc-section">
            <h4>🦠 Antécédents médicaux</h4>
            <table class="doc-table">
                <tr><th>Type</th><th>Intitulé</th></tr>
                <?php 
                $has_ant = false;
                foreach($allergies as $a): 
                    if(trim($a)): $has_ant = true; ?>
                    <tr><td><span class="badge-type" style="background:#FFF5F5;color:#D62828">⚠️ Allergie</span></td><td><strong><?=htmlspecialchars(trim($a))?></strong></td></tr>
                <?php endif; endforeach; ?>
                <?php foreach($maladies_chroniques as $m): 
                    if(trim($m)): $has_ant = true; ?>
                    <tr><td><span class="badge-type" style="background:#FFF7ED;color:#C2410C">🔄 Chronique</span></td><td><strong><?=htmlspecialchars(trim($m))?></strong></td></tr>
                <?php endif; endforeach; ?>
                <?php foreach($chirurgies as $c): 
                    if(trim($c)): $has_ant = true; ?>
                    <tr><td><span class="badge-type" style="background:#EFF6FF;color:#1D4ED8">🔪 Chirurgie</span></td><td><strong><?=htmlspecialchars(trim($c))?></strong></td></tr>
                <?php endif; endforeach; ?>
                <?php foreach($antecedents as $a): 
                    if(trim($a)): $has_ant = true; ?>
                    <tr><td><span class="badge-type" style="background:#F3E8FF;color:#7209B7">📋 Antécédent</span></td><td><strong><?=htmlspecialchars(trim($a))?></strong></td></tr>
                <?php endif; endforeach; ?>
                <?php if(!$has_ant): ?>
                    <tr><td colspan="2" style="text-align:center;color:var(--gray);padding:.8rem;">Aucun antécédent enregistré</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- TRAITEMENTS -->
        <?php if(!empty($traitements)): ?>
        <div class="doc-section">
            <h4>💊 Traitements en cours</h4>
            <table class="doc-table">
                <tr><th>Traitement</th></tr>
                <?php foreach($traitements as $t): ?>
                    <?php if(trim($t)): ?>
                    <tr><td><strong><?=htmlspecialchars(trim($t))?></strong></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- CONSULTATIONS -->
        <div class="doc-section">
            <h4>📅 Historique des consultations</h4>
            <?php if(empty($consultations)): ?>
                <p style="text-align:center;color:var(--gray);padding:.8rem;">Aucune consultation</p>
            <?php else: ?>
            <table class="doc-table">
                <tr><th>Date</th><th>Médecin</th><th>Spécialité</th><th>Diagnostic</th></tr>
                <?php foreach(array_slice($consultations, 0, 10) as $c): ?>
                <tr>
                    <td><?=formatDate($c['date_consultation'])?></td>
                    <td>Dr. <?=htmlspecialchars($c['m_prenom'].' '.$c['m_nom'])?></td>
                    <td><?=htmlspecialchars($c['specialite'])?></td>
                    <td><?=htmlspecialchars(mb_substr($c['diagnostic'] ?: '—', 0, 50))?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($consultations) > 10): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--gray);font-style:italic;">... et <?=count($consultations)-10?> autres consultations</td></tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
        </div>

        <!-- SIGNATURE -->
        <div class="signature-section">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Signature du patient : <?=htmlspecialchars($patient['prenom'].' '.$patient['nom'])?></div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Cachet / Signature médecin traitant</div>
            </div>
        </div>

        <div class="doc-footer">
            Document généré par MediRDV — Université Numérique Cheikh Hamidou Kane — Confidentiel — Usage médical uniquement
        </div>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Fatou Yankaty (E4)</strong> — imprimer_dossier.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }
</script>
</body>
</html>