<?php
// ================================================================
// ordonnance.php — Rédiger une ordonnance
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

$rdv_id = (int)($_GET['rdv'] ?? 0);
$patient_id = (int)($_GET['patient'] ?? 0);

// Si un RDV est spécifié, récupérer les infos
$rdv = null;
$patient = null;

if ($rdv_id > 0) {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               p.id AS p_id, p.nom AS p_nom, p.prenom AS p_prenom, p.email, p.telephone, p.date_naissance, p.groupe_sanguin, p.taille, p.poids,
               d.id AS d_id, d.allergies, d.antecedents, d.maladies_chroniques, d.traitements_en_cours
        FROM rendezvous r
        JOIN patients p ON p.id = r.id_patient
        LEFT JOIN dossiers d ON d.id_patient = p.id
        WHERE r.id = :id AND r.id_medecin = :medecin_id
    ");
    $stmt->execute([':id' => $rdv_id, ':medecin_id' => $medecin_id]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rdv) {
        $patient = $rdv;
    }
} elseif ($patient_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
    $stmt->execute([':id' => $patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$patient) {
    redirect('gerer_rdv.php');
}

// Calculer l'âge
$age = !empty($patient['date_naissance']) ? getAge($patient['date_naissance']) : '?';

// ================================================================
// TRAITEMENT DU FORMULAIRE
// ================================================================
$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicaments = $_POST['medicaments'] ?? [];
    $dosages = $_POST['dosages'] ?? [];
    $frequences = $_POST['frequences'] ?? [];
    $durees = $_POST['durees'] ?? [];
    $voies = $_POST['voies'] ?? [];
    $validite = $_POST['validite'] ?? '3 mois';
    $prochain_rdv = $_POST['prochain_rdv'] ?? '';
    $instructions = trim($_POST['instructions'] ?? '');
    $diagnostic = trim($_POST['diagnostic'] ?? '');

    if (empty($medicaments) || empty($medicaments[0])) {
        $erreur = 'Ajoutez au moins un médicament.';
    } else {
        // Construire l'ordonnance
        $ordonnance_text = "Ordonnance médicale\n\n";
        $ordonnance_text .= "Patient : " . $patient['prenom'] . " " . $patient['nom'] . "\n";
        $ordonnance_text .= "Date : " . date('d/m/Y') . "\n";
        $ordonnance_text .= "Médecin : Dr. " . $medecin['prenom'] . " " . $medecin['nom'] . "\n";
        $ordonnance_text .= "Spécialité : " . $medecin['specialite'] . "\n\n";
        $ordonnance_text .= "--- Médicaments prescrits ---\n";
        
        for ($i = 0; $i < count($medicaments); $i++) {
            if (!empty($medicaments[$i])) {
                $ordonnance_text .= "- " . $medicaments[$i] . " " . ($dosages[$i] ?? '') . " : " . ($frequences[$i] ?? '') . " pendant " . ($durees[$i] ?? '') . " - Voie " . ($voies[$i] ?? 'orale') . "\n";
            }
        }
        
        $ordonnance_text .= "\nValidité : " . $validite . "\n";
        if (!empty($instructions)) {
            $ordonnance_text .= "\nInstructions : " . $instructions . "\n";
        }
        if (!empty($prochain_rdv)) {
            $ordonnance_text .= "Prochain RDV : " . date('d/m/Y', strtotime($prochain_rdv)) . "\n";
        }
        
        try {
            // Mettre à jour ou créer la consultation
            if ($rdv_id) {
                // Vérifier si une consultation existe déjà
                $stmt = $pdo->prepare("SELECT id FROM consultations WHERE id_rendezvous = :rdv_id");
                $stmt->execute([':rdv_id' => $rdv_id]);
                $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($consultation) {
                    $stmt = $pdo->prepare("UPDATE consultations SET ordonnance = :ordo, diagnostic = :diag WHERE id = :id");
                    $stmt->execute([
                        ':ordo' => $ordonnance_text,
                        ':diag' => $diagnostic ?: null,
                        ':id' => $consultation['id']
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO consultations (id_rendezvous, id_patient, id_medecin, ordonnance, diagnostic, date_consultation)
                        VALUES (:rdv_id, :patient_id, :medecin_id, :ordo, :diag, NOW())
                    ");
                    $stmt->execute([
                        ':rdv_id' => $rdv_id,
                        ':patient_id' => $patient['id'],
                        ':medecin_id' => $medecin_id,
                        ':ordo' => $ordonnance_text,
                        ':diag' => $diagnostic ?: null
                    ]);
                }
            }
            
            $_SESSION['success'] = '✅ Ordonnance sauvegardée avec succès !';
            redirect('gerer_rdv.php');
            
        } catch(Exception $e) {
            $erreur = '❌ Erreur : ' . $e->getMessage();
        }
    }
}

function getAge($date) {
    $now = new DateTime();
    $birth = new DateTime($date);
    return $now->diff($birth)->y;
}

$initiales = strtoupper(substr($medecin['prenom'], 0, 1) . substr($medecin['nom'], 0, 1));
$patient_init = strtoupper(substr($patient['prenom'] ?? '', 0, 1) . substr($patient['nom'] ?? '', 0, 1));

$date_rdv = $rdv ? date('d/m/Y', strtotime($rdv['date_rdv'])) : date('d/m/Y');
$heure_rdv = $rdv ? substr($rdv['heure_rdv'], 0, 5) : '--:--';
$motif = $rdv ? $rdv['motif'] : 'Consultation';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Rédiger Ordonnance</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--teal:#006D77;--purple:#7209B7;--light:#F0F4FF;--gray:#64748B;--lgray:#E2E8F0}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .doc-badge{background:rgba(45,198,83,.2);color:var(--green);padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:700}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        .page-hdr{background:linear-gradient(135deg,var(--teal),var(--blue));padding:1.5rem 5%;color:#fff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
        .page-hdr h1{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800}
        .page-hdr p{color:#A5F3FC;font-size:.85rem;margin-top:.2rem}
        .hdr-btns{display:flex;gap:.6rem;flex-wrap:wrap}
        .hdr-btn{padding:.55rem 1.1rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}
        .hdr-btn.green{background:var(--green);border-color:var(--green)}
        .hdr-btn.green:hover{background:#25B347}

        .main{display:flex;gap:1.5rem;padding:1.5rem 5%;max-width:1100px;margin:0 auto}

        .sidebar{width:250px;flex-shrink:0}
        .pat-card{background:#fff;border-radius:12px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .pat-card h5{font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:var(--dark);margin-bottom:.8rem;padding-bottom:.4rem;border-bottom:1px solid var(--lgray)}
        .pat-av-sm{width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:.7rem;color:#fff}
        .pat-name{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:#1E293B}
        .pat-meta{font-size:.78rem;color:var(--gray);margin-top:.2rem}
        .irow{display:flex;justify-content:space-between;padding:.3rem 0;font-size:.8rem;border-bottom:1px solid #F8FAFC}
        .irow:last-child{border:none}
        .irow .l{color:var(--gray)}.irow .v{font-weight:600;color:#1E293B}
        .warn-allerg{background:#FFF5F5;border:1px solid #FECACA;border-radius:8px;padding:.6rem .8rem;font-size:.78rem;color:var(--red);font-weight:600;margin-top:.5rem}

        .rdv-info{background:var(--light);border-radius:8px;padding:.7rem;margin-bottom:.5rem}
        .rdv-info .lbl{font-size:.72rem;color:var(--gray);font-weight:600;text-transform:uppercase;letter-spacing:.5px}
        .rdv-info .val{font-size:.85rem;font-weight:700;color:var(--dark);margin-top:.2rem}

        .form-card{flex:1;background:#fff;border-radius:14px;padding:1.6rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .form-card h3{font-family:'Sora',sans-serif;font-size:.98rem;font-weight:700;color:var(--dark);margin-bottom:1.2rem;padding-bottom:.6rem;border-bottom:1px solid var(--lgray)}

        .meds-table{width:100%;border-collapse:collapse;margin-bottom:1rem;display:block;overflow-x:auto}
        .meds-table thead,.meds-table tbody{display:table;width:100%;table-layout:fixed}
        .meds-table th{background:#F8FAFF;padding:.55rem .7rem;text-align:left;font-size:.78rem;font-weight:700;color:var(--dark);border-bottom:2px solid var(--lgray)}
        .meds-table td{padding:.45rem .5rem;border-bottom:1px solid #F1F5F9;vertical-align:middle}
        .meds-table input,.meds-table select{width:100%;padding:.4rem .55rem;border:1.5px solid var(--lgray);border-radius:7px;font-size:.8rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;background:#fff}
        .meds-table input:focus,.meds-table select:focus{border-color:var(--blue)}
        .del-btn{width:28px;height:28px;background:transparent;color:var(--red);border:1px solid var(--red);border-radius:6px;cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center}
        .del-btn:hover{background:#FFF5F5}
        .add-med-btn{padding:.55rem 1.2rem;background:transparent;color:var(--blue);border:1.5px dashed var(--blue);border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;width:100%;margin-bottom:1.2rem;transition:.2s}
        .add-med-btn:hover{background:var(--light)}

        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:#D62828}

        .preview-box{background:linear-gradient(135deg,#F0F9FF,#F0FDF4);border:1.5px solid #BFDBFE;border-radius:10px;padding:1rem;margin-bottom:1.2rem}
        .preview-box h5{font-size:.82rem;font-weight:700;color:var(--blue);margin-bottom:.6rem;display:flex;align-items:center;gap:.4rem}
        .preview-med{display:flex;align-items:center;gap:.6rem;padding:.3rem 0;font-size:.8rem;border-bottom:1px solid #DBEAFE}
        .preview-med:last-child{border:none}
        .preview-med .dot{width:6px;height:6px;border-radius:50%;background:var(--green);flex-shrink:0}

        .form-group{margin-bottom:1rem}
        .form-group label{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:.4rem}
        .form-group textarea,.form-group input,.form-group select{width:100%;padding:.7rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;background:#fff}
        .form-group textarea:focus,.form-group input:focus{border-color:var(--blue)}
        .form-group textarea{resize:vertical;min-height:80px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}

        .form-actions{display:flex;gap:.8rem;margin-top:1.5rem;flex-wrap:wrap}
        .btn-save{flex:1;padding:.82rem;background:var(--teal);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(0,109,119,.25);min-width:150px}
        .btn-save:hover{background:var(--dark)}
        .btn-print{padding:.82rem 1.3rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer}
        .btn-print:hover{background:var(--dark)}
        .btn-cancel{padding:.82rem 1.2rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:10px;font-size:.9rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-cancel:hover{border-color:var(--teal);color:var(--teal)}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        .med-row-highlight:nth-child(even){background:#F0FDF4}
        .med-row-highlight:nth-child(odd){background:#fff}

        @media(max-width:992px){
            .main{flex-direction:column}
            .sidebar{width:100%}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .page-hdr{flex-direction:column;text-align:center}
            .hdr-btns{justify-content:center}
            .form-row{grid-template-columns:1fr}
            .form-actions{flex-direction:column}
            .btn-save{width:100%}
            .meds-table{font-size:.72rem}
            .meds-table th,.meds-table td{padding:.3rem .4rem}
            .meds-table input,.meds-table select{font-size:.7rem;padding:.3rem .4rem}
        }
        @media(max-width:480px){
            .page-hdr{padding:1rem 4%}
            .page-hdr h1{font-size:1.1rem}
            .main{padding:1rem 4%}
            .form-card{padding:1rem}
            .pat-card{padding:.8rem}
            .preview-box{padding:.8rem}
            .meds-table{font-size:.65rem}
            .meds-table th,.meds-table td{padding:.2rem .3rem}
            .del-btn{width:22px;height:22px;font-size:.7rem}
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
        <li><a href="gerer_rdv.php">Mes Patients</a></li>
        <li><a href="ordonnance.php" class="active">Ordonnances</a></li>
    </ul>
    <div class="doc-badge">👨‍⚕️ Dr. <?=htmlspecialchars($medecin['prenom'])?></div>
</nav>

<!-- ===== HEADER ===== -->
<div class="page-hdr">
    <div>
        <h1>📝 Rédiger une Ordonnance</h1>
        <p>Consultation du <?=$date_rdv?> — <?=$heure_rdv?></p>
    </div>
    <div class="hdr-btns">
        <a href="gerer_rdv.php" class="hdr-btn">← Retour agenda</a>
        <button class="hdr-btn green" onclick="window.print()">🖨️ Imprimer</button>
    </div>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="pat-card">
            <h5>👤 Patient</h5>
            <div class="pat-av-sm"><?=$patient_init?></div>
            <div class="pat-name"><?=htmlspecialchars($patient['prenom'].' '.$patient['nom'])?></div>
            <div class="pat-meta"><?=$age?> ans • <?=htmlspecialchars($patient['ville'] ?? '—')?></div>
            <div style="margin-top:.8rem">
                <div class="irow"><span class="l">Groupe</span><span class="v"><?=$patient['groupe_sanguin'] ?: '—'?></span></div>
                <div class="irow"><span class="l">Poids</span><span class="v"><?=$patient['poids'] ? (int)$patient['poids'].' kg' : '—'?></span></div>
                <div class="irow"><span class="l">Taille</span><span class="v"><?=$patient['taille'] ? (int)$patient['taille'].' cm' : '—'?></span></div>
            </div>
            <?php if(!empty($patient['allergies'])): ?>
                <div class="warn-allerg">⚠️ ALLERGIE : <?=htmlspecialchars($patient['allergies'])?></div>
            <?php endif; ?>
        </div>

        <div class="pat-card">
            <h5>📅 RDV en cours</h5>
            <div class="rdv-info">
                <div class="lbl">Date</div>
                <div class="val"><?=$date_rdv?> — <?=$heure_rdv?></div>
            </div>
            <div class="rdv-info">
                <div class="lbl">Motif</div>
                <div class="val"><?=htmlspecialchars($motif)?></div>
            </div>
            <div class="rdv-info">
                <div class="lbl">Cabinet</div>
                <div class="val"><?=htmlspecialchars($medecin['cabinet'])?>, <?=htmlspecialchars($medecin['ville'])?></div>
            </div>
        </div>

        <?php if(!empty($patient['traitements_en_cours'])): ?>
        <div class="pat-card">
            <h5>💊 Traitements en cours</h5>
            <div class="irow"><span class="l"><?=htmlspecialchars($patient['traitements_en_cours'])?></span></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== FORM ===== -->
    <div class="form-card">
        <form method="POST" action="ordonnance.php<?=$rdv_id ? '?rdv='.$rdv_id : ($patient_id ? '?patient='.$patient_id : '')?>" id="ordoForm">
            <h3>💊 Médicaments prescrits</h3>

            <?php if($erreur): ?>
                <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
            <?php endif; ?>

            <table class="meds-table" id="medsTable">
                <thead>
                    <tr>
                        <th style="width:28%">Médicament</th>
                        <th style="width:15%">Dosage</th>
                        <th style="width:18%">Fréquence</th>
                        <th style="width:18%">Durée</th>
                        <th style="width:10%">Voie</th>
                        <th style="width:6%"></th>
                    </tr>
                </thead>
                <tbody id="medsBody">
                    <tr class="med-row-highlight">
                        <td><input type="text" name="medicaments[]" placeholder="Nom du médicament" value="Amlodipine"></td>
                        <td><input type="text" name="dosages[]" placeholder="Dosage" value="5mg"></td>
                        <td><select name="frequences[]"><option selected>1 fois / jour</option><option>2 fois / jour</option><option>3 fois / jour</option></select></td>
                        <td><select name="durees[]"><option>30 jours</option><option selected>90 jours</option><option>6 mois</option></select></td>
                        <td><select name="voies[]"><option selected>Oral</option><option>Inject.</option></select></td>
                        <td><button type="button" class="del-btn" onclick="removeRow(this)">🗑</button></td>
                    </tr>
                    <tr>
                        <td><input type="text" name="medicaments[]" placeholder="Nom du médicament" value="Aspegic"></td>
                        <td><input type="text" name="dosages[]" placeholder="Dosage" value="100mg"></td>
                        <td><select name="frequences[]"><option selected>1 fois / jour</option><option>2 fois / jour</option></select></td>
                        <td><select name="durees[]"><option>30 jours</option><option selected>90 jours</option></select></td>
                        <td><select name="voies[]"><option selected>Oral</option></select></td>
                        <td><button type="button" class="del-btn" onclick="removeRow(this)">🗑</button></td>
                    </tr>
                </tbody>
            </table>

            <button type="button" class="add-med-btn" onclick="addRow()">+ Ajouter un médicament</button>

            <!-- PREVIEW -->
            <div class="preview-box" id="previewBox">
                <h5>👁 Aperçu ordonnance</h5>
                <div id="previewContent">
                    <div class="preview-med"><div class="dot"></div><span><strong>Amlodipine 5mg</strong> — 1x/jour — 90 jours — Oral</span></div>
                    <div class="preview-med"><div class="dot"></div><span><strong>Aspegic 100mg</strong> — 1x/jour — 90 jours — Oral</span></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Durée de validité</label>
                    <select name="validite">
                        <option>1 mois</option>
                        <option selected>3 mois</option>
                        <option>6 mois</option>
                        <option>1 an</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Prochain rendez-vous</label>
                    <input type="date" name="prochain_rdv" value="<?=date('Y-m-d', strtotime('+3 months'))?>">
                </div>
            </div>

            <div class="form-group">
                <label>Instructions générales au patient</label>
                <textarea name="instructions">Prendre les médicaments régulièrement. Éviter l'alcool et le tabac. Contrôler la tension chaque semaine. Revenir en consultation dans 3 mois ou en cas de symptômes inhabituels.</textarea>
            </div>

            <div class="form-group">
                <label>Diagnostic (pour le dossier)</label>
                <textarea name="diagnostic" style="min-height:60px">Hypertension artérielle stable sous traitement. Légère dyspnée d'effort. Ajustement du traitement.</textarea>
            </div>

            <div class="form-actions">
                <a href="gerer_rdv.php" class="btn-cancel">Annuler</a>
                <button type="button" class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
                <button type="submit" class="btn-save">💾 Sauvegarder dans le dossier</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Mareme Faye (E5)</strong> — ordonnance.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    function addRow() {
        const tbody = document.getElementById('medsBody');
        const row = document.createElement('tr');
        row.className = 'med-row-highlight';
        row.innerHTML = `
            <td><input type="text" name="medicaments[]" placeholder="Nom du médicament"></td>
            <td><input type="text" name="dosages[]" placeholder="Dosage"></td>
            <td><select name="frequences[]"><option selected>1 fois / jour</option><option>2 fois / jour</option><option>3 fois / jour</option></select></td>
            <td><select name="durees[]"><option selected>30 jours</option><option>90 jours</option><option>6 mois</option></select></td>
            <td><select name="voies[]"><option selected>Oral</option><option>Inject.</option></select></td>
            <td><button type="button" class="del-btn" onclick="removeRow(this)">🗑</button></td>
        `;
        tbody.appendChild(row);
        updatePreview();
    }

    function removeRow(btn) {
        const tbody = document.getElementById('medsBody');
        if (tbody.children.length <= 1) {
            alert('Ajoutez au moins un médicament.');
            return;
        }
        btn.closest('tr').remove();
        updatePreview();
    }

    function updatePreview() {
        const rows = document.querySelectorAll('#medsBody tr');
        const preview = document.getElementById('previewContent');
        preview.innerHTML = '';
        rows.forEach(function(row) {
            const inputs = row.querySelectorAll('input');
            const selects = row.querySelectorAll('select');
            if (inputs.length >= 2) {
                const med = inputs[0].value || 'Médicament';
                const dosage = inputs[1].value || '';
                const freq = selects[0] ? selects[0].value : '1x/jour';
                const duree = selects[1] ? selects[1].value : '30 jours';
                const voie = selects[2] ? selects[2].value : 'Oral';
                if (med) {
                    const div = document.createElement('div');
                    div.className = 'preview-med';
                    div.innerHTML = `<div class="dot"></div><span><strong>${med} ${dosage}</strong> — ${freq} — ${duree} — ${voie}</span>`;
                    preview.appendChild(div);
                }
            }
        });
        if (preview.children.length === 0) {
            preview.innerHTML = '<div style="color:var(--gray);font-size:.8rem;padding:.3rem 0;">Aucun médicament ajouté.</div>';
        }
    }

    // Mettre à jour l'aperçu à chaque changement
    document.addEventListener('input', updatePreview);
    document.addEventListener('change', updatePreview);
</script>
</body>
</html>