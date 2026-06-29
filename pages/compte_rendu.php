<?php
// ================================================================
// compte_rendu.php — Compte-rendu médical (Médecin)
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

$rdv_id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient'] ?? 0);

// Si un RDV est spécifié, récupérer ses infos
$rdv = null;
$patient = null;
$consultation = null;

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
        // Vérifier si une consultation existe déjà
        $stmt = $pdo->prepare("SELECT * FROM consultations WHERE id_rendezvous = :rdv_id");
        $stmt->execute([':rdv_id' => $rdv_id]);
        $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} elseif ($patient_id > 0) {
    // Récupérer les infos du patient
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
    $diagnostic = trim($_POST['diagnostic'] ?? '');
    $traitement = trim($_POST['traitement'] ?? '');
    $observations = trim($_POST['observations'] ?? '');
    $recommandations = trim($_POST['recommandations'] ?? '');
    $prochain_rdv = trim($_POST['prochain_rdv'] ?? '');
    $type_suivi = trim($_POST['type_suivi'] ?? '');
    $tension = trim($_POST['tension'] ?? '');
    $frequence = trim($_POST['frequence'] ?? '');
    $poids = trim($_POST['poids'] ?? '');
    $temperature = trim($_POST['temperature'] ?? '');
    $etat_general = trim($_POST['etat_general'] ?? 'stable');

    if (empty($diagnostic)) {
        $erreur = 'Le diagnostic est obligatoire.';
    } else {
        try {
            if ($consultation) {
                // Mise à jour
                $stmt = $pdo->prepare("
                    UPDATE consultations SET 
                        diagnostic = :diagnostic,
                        traitement = :traitement,
                        compte_rendu = :observations,
                        prochain_rdv = :prochain_rdv
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':diagnostic' => $diagnostic,
                    ':traitement' => $traitement,
                    ':observations' => $observations,
                    ':prochain_rdv' => $prochain_rdv ?: null,
                    ':id' => $consultation['id']
                ]);
            } else {
                // Création
                $stmt = $pdo->prepare("
                    INSERT INTO consultations 
                        (id_rendezvous, id_patient, id_medecin, diagnostic, traitement, compte_rendu, prochain_rdv, date_consultation)
                    VALUES 
                        (:rdv_id, :patient_id, :medecin_id, :diagnostic, :traitement, :observations, :prochain_rdv, NOW())
                ");
                $stmt->execute([
                    ':rdv_id' => $rdv_id ?: null,
                    ':patient_id' => $patient['id'],
                    ':medecin_id' => $medecin_id,
                    ':diagnostic' => $diagnostic,
                    ':traitement' => $traitement,
                    ':observations' => $observations,
                    ':prochain_rdv' => $prochain_rdv ?: null
                ]);
                
                // Mettre à jour le statut du RDV si existant
                if ($rdv_id) {
                    $pdo->prepare("UPDATE rendezvous SET statut = 'termine' WHERE id = :id")
                        ->execute([':id' => $rdv_id]);
                }
            }
            
            // Journaliser
            $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, id_dossier, ip_address) 
                           VALUES (:id, 'medecin', 'compte_rendu_ajoute', :dossier_id, :ip)")
                ->execute([
                    ':id' => $medecin_id,
                    ':dossier_id' => $patient['id'],
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
            
            $succes = '✅ Compte-rendu sauvegardé avec succès !';
            
            // Recharger les données
            if ($rdv_id) {
                $stmt = $pdo->prepare("SELECT * FROM consultations WHERE id_rendezvous = :rdv_id");
                $stmt->execute([':rdv_id' => $rdv_id]);
                $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
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
    <title>MediRDV — Compte-Rendu Médical</title>
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
        .doc-badge{background:rgba(45,198,83,.2);color:var(--green);padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:700}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== HEADER ===== */
        .page-hdr{background:linear-gradient(135deg,var(--teal),var(--dark));padding:1.5rem 5%;color:#fff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
        .page-hdr h1{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800}
        .page-hdr p{color:#A5F3FC;font-size:.85rem;margin-top:.2rem}
        .hdr-btns{display:flex;gap:.6rem;flex-wrap:wrap}
        .hdr-btn{padding:.55rem 1.1rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}
        .hdr-btn.green{background:var(--green);border-color:var(--green)}
        .hdr-btn.green:hover{background:#25B347}

        /* ===== MAIN ===== */
        .main{display:flex;gap:1.5rem;padding:1.5rem 5%;max-width:1100px;margin:0 auto}

        /* ===== SIDEBAR ===== */
        .sidebar{width:250px;flex-shrink:0}
        .s-card{background:#fff;border-radius:12px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .s-card h5{font-family:'Sora',sans-serif;font-size:.85rem;font-weight:700;color:var(--dark);margin-bottom:.8rem;padding-bottom:.4rem;border-bottom:1px solid var(--lgray)}
        .pat-av{width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:.6rem;color:#fff}
        .pat-name{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:#1E293B}
        .pat-meta{font-size:.78rem;color:var(--gray);margin-top:.2rem}
        .irow{display:flex;justify-content:space-between;padding:.3rem 0;font-size:.8rem;border-bottom:1px solid #F8FAFC}
        .irow:last-child{border:none}
        .irow .l{color:var(--gray)}.irow .v{font-weight:600;color:#1E293B}
        .warn{background:#FFF5F5;border:1px solid #FECACA;border-radius:7px;padding:.5rem .7rem;font-size:.76rem;color:var(--red);font-weight:600;margin-top:.5rem}

        .rdv-box{background:var(--light);border-radius:8px;padding:.7rem;margin-bottom:.5rem}
        .rdv-box .lbl{font-size:.7rem;color:var(--gray);font-weight:700;text-transform:uppercase;letter-spacing:.5px}
        .rdv-box .val{font-size:.85rem;font-weight:700;color:var(--dark);margin-top:.15rem}

        /* ===== FORM CARD ===== */
        .form-card{flex:1;background:#fff;border-radius:14px;padding:1.6rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .sec-title{font-family:'Sora',sans-serif;font-size:.92rem;font-weight:700;color:var(--dark);margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:2px solid var(--teal);display:flex;align-items:center;gap:.5rem}
        .form-group{margin-bottom:1.1rem}
        .form-group label{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:.4rem}
        .form-group textarea,.form-group input,.form-group select{width:100%;padding:.72rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;background:#fff}
        .form-group textarea:focus,.form-group input:focus,.form-group select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,109,119,.08)}
        .form-group textarea{resize:vertical;min-height:90px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}

        /* ===== QUICK DIAG ===== */
        .quick-diag{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.6rem}
        .qd-btn{padding:.35rem .8rem;border:1.5px solid var(--lgray);border-radius:20px;font-size:.75rem;font-weight:600;cursor:pointer;background:#fff;color:#334155;transition:.2s}
        .qd-btn:hover{border-color:var(--teal);color:var(--teal)}
        .qd-btn.sel{border-color:var(--teal);background:#E0F2F1;color:var(--teal)}

        /* ===== STATUS ROW ===== */
        .status-row{display:flex;gap:.6rem;margin-bottom:1.1rem;flex-wrap:wrap}
        .status-opt{flex:1;padding:.65rem;border:1.5px solid var(--lgray);border-radius:10px;text-align:center;cursor:pointer;transition:.2s;min-width:80px;background:#fff}
        .status-opt:hover{border-color:var(--teal)}
        .status-opt.sel{border-color:var(--green);background:#F0FDF4}
        .status-opt .s-icon{font-size:1.2rem;margin-bottom:.2rem}
        .status-opt .s-lbl{font-size:.75rem;font-weight:700;color:#334155}
        .status-opt.sel .s-lbl{color:var(--green)}

        /* ===== ALERT ===== */
        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:#D62828}

        .divider{border:none;border-top:1px solid var(--lgray);margin:1.2rem 0}
        .form-actions{display:flex;gap:.8rem;margin-top:1.5rem;flex-wrap:wrap}
        .btn-save{flex:1;padding:.82rem;background:var(--teal);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(0,109,119,.25);min-width:150px}
        .btn-save:hover{background:var(--dark)}
        .btn-cancel{padding:.82rem 1.2rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:10px;font-size:.9rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-cancel:hover{border-color:var(--teal);color:var(--teal)}

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
            .page-hdr{flex-direction:column;text-align:center}
            .hdr-btns{justify-content:center}
            .form-row{grid-template-columns:1fr}
            .status-row{flex-wrap:wrap}
            .status-opt{min-width:60px;padding:.4rem}
            .form-actions{flex-direction:column}
            .btn-save{width:100%}
        }
        @media(max-width:480px){
            .page-hdr{padding:1rem 4%}
            .page-hdr h1{font-size:1.1rem}
            .main{padding:1rem 4%}
            .form-card{padding:1rem}
            .sec-title{font-size:.8rem}
            .s-card{padding:.8rem}
            .quick-diag .qd-btn{font-size:.65rem;padding:.25rem .6rem}
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
        <li><a href="ordonnance.php">Ordonnances</a></li>
    </ul>
    <div class="doc-badge">👨‍⚕️ Dr. <?=htmlspecialchars($medecin['prenom'])?></div>
</nav>

<!-- ===== HEADER ===== -->
<div class="page-hdr">
    <div>
        <h1>📋 Compte-Rendu Médical</h1>
        <p>Consultation du <?=$date_rdv?> — <?=$heure_rdv?></p>
    </div>
    <div class="hdr-btns">
        <a href="gerer_rdv.php" class="hdr-btn">← Retour agenda</a>
        <button class="hdr-btn green" onclick="document.getElementById('crForm').submit()">💾 Sauvegarder</button>
    </div>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="s-card">
            <h5>👤 Patient</h5>
            <div class="pat-av"><?=$patient_init?></div>
            <div class="pat-name"><?=htmlspecialchars($patient['prenom'].' '.$patient['nom'])?></div>
            <div class="pat-meta"><?=$age?> ans • <?=htmlspecialchars($patient['ville'] ?? '—')?> • <?=$patient['groupe_sanguin'] ?: '—'?></div>
            <div style="margin-top:.8rem">
                <div class="irow"><span class="l">Tél</span><span class="v"><?=htmlspecialchars($patient['telephone'] ?? '—')?></span></div>
                <div class="irow"><span class="l">Taille</span><span class="v"><?=$patient['taille'] ? (int)$patient['taille'].' cm' : '—'?></span></div>
                <div class="irow"><span class="l">Poids</span><span class="v"><?=$patient['poids'] ? (int)$patient['poids'].' kg' : '—'?></span></div>
            </div>
            <?php if(!empty($patient['allergies'])): ?>
                <div class="warn">⚠️ ALLERGIE : <?=htmlspecialchars($patient['allergies'])?></div>
            <?php endif; ?>
        </div>

        <div class="s-card">
            <h5>📅 Cette consultation</h5>
            <div class="rdv-box"><div class="lbl">Date & Heure</div><div class="val"><?=$date_rdv?> — <?=$heure_rdv?></div></div>
            <div class="rdv-box"><div class="lbl">Motif déclaré</div><div class="val"><?=htmlspecialchars($motif)?></div></div>
            <div class="rdv-box"><div class="lbl">Durée</div><div class="val">30 minutes</div></div>
        </div>

        <?php if(!empty($patient['antecedents']) || !empty($patient['maladies_chroniques'])): ?>
        <div class="s-card">
            <h5>📋 Antécédents</h5>
            <?php if(!empty($patient['antecedents'])): ?>
                <div class="irow"><span class="l">Antécédents</span><span class="v" style="color:var(--orange)"><?=htmlspecialchars($patient['antecedents'])?></span></div>
            <?php endif; ?>
            <?php if(!empty($patient['maladies_chroniques'])): ?>
                <div class="irow"><span class="l">Maladies chroniques</span><span class="v" style="color:var(--orange)"><?=htmlspecialchars($patient['maladies_chroniques'])?></span></div>
            <?php endif; ?>
            <?php if(!empty($patient['traitements_en_cours'])): ?>
                <div class="irow"><span class="l">Traitement</span><span class="v"><?=htmlspecialchars($patient['traitements_en_cours'])?></span></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== FORM ===== -->
    <div class="form-card">
        <form method="POST" action="compte_rendu.php?id=<?=$rdv_id?>" id="crForm">

            <?php if($succes): ?>
                <div class="alert alert-success"><?=$succes?></div>
            <?php endif; ?>
            <?php if($erreur): ?>
                <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
            <?php endif; ?>

            <!-- ETAT PATIENT -->
            <div class="sec-title">🩺 État général du patient</div>
            <div class="status-row">
                <?php 
                $etats = [
                    'bon' => ['icon' => '😊', 'label' => 'Bon état'],
                    'stable' => ['icon' => '😐', 'label' => 'État stable'],
                    'preoccupant' => ['icon' => '😟', 'label' => 'Préoccupant'],
                    'urgent' => ['icon' => '🚨', 'label' => 'Urgent']
                ];
                $selected_etat = $_POST['etat_general'] ?? ($consultation['compte_rendu'] ? 'stable' : 'stable');
                foreach($etats as $key => $e): 
                ?>
                <button type="button" class="status-opt <?=$selected_etat===$key?'sel':''?>" data-value="<?=$key?>" onclick="selectEtat(this)">
                    <div class="s-icon"><?=$e['icon']?></div>
                    <div class="s-lbl"><?=$e['label']?></div>
                </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="etat_general" id="etat_general" value="<?=$selected_etat?>">

            <!-- DIAGNOSTIC -->
            <div class="sec-title">🔬 Diagnostic</div>
            <div class="form-group">
                <label>Diagnostic principal</label>
                <div class="quick-diag">
                    <?php 
                    $diags_rapides = ['HTA', 'Angine', 'Arythmie', 'Insuffisance cardiaque', 'Diabète', 'Autre...'];
                    $selected_diag = $_POST['diagnostic'] ?? ($consultation['diagnostic'] ?? '');
                    foreach($diags_rapides as $d): 
                    ?>
                    <button type="button" class="qd-btn <?=strpos($selected_diag, $d) !== false ? 'sel' : ''?>" onclick="selectDiag(this, '<?=addslashes($d)?>')"><?=$d?></button>
                    <?php endforeach; ?>
                </div>
                <textarea name="diagnostic" id="diagnostic" placeholder="Décrivez le diagnostic..."><?=htmlspecialchars($consultation['diagnostic'] ?? '')?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tension artérielle</label>
                    <input type="text" name="tension" placeholder="Ex: 135/85 mmHg" value="<?=htmlspecialchars($consultation['tension'] ?? '')?>">
                </div>
                <div class="form-group">
                    <label>Fréquence cardiaque</label>
                    <input type="text" name="frequence" placeholder="Ex: 78 bpm" value="<?=htmlspecialchars($consultation['frequence'] ?? '')?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Poids actuel</label>
                    <input type="text" name="poids" placeholder="Ex: 62 kg" value="<?=htmlspecialchars($consultation['poids'] ?? $patient['poids'] ?? '')?>">
                </div>
                <div class="form-group">
                    <label>Température</label>
                    <input type="text" name="temperature" placeholder="Ex: 36.8 °C" value="<?=htmlspecialchars($consultation['temperature'] ?? '')?>">
                </div>
            </div>

            <div class="divider"></div>

            <!-- TRAITEMENT -->
            <div class="sec-title">💊 Traitement prescrit</div>
            <div class="form-group">
                <label>Description du traitement</label>
                <textarea name="traitement" placeholder="Décrivez le traitement prescrit..."><?=htmlspecialchars($consultation['traitement'] ?? '')?></textarea>
            </div>

            <div class="divider"></div>

            <!-- OBSERVATIONS -->
            <div class="sec-title">📝 Observations & Recommandations</div>
            <div class="form-group">
                <label>Observations du médecin</label>
                <textarea name="observations" placeholder="Vos observations médicales..."><?=htmlspecialchars($consultation['compte_rendu'] ?? '')?></textarea>
            </div>

            <div class="form-group">
                <label>Recommandations au patient</label>
                <textarea name="recommandations" placeholder="Recommandations..."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Prochain rendez-vous recommandé</label>
                    <input type="date" name="prochain_rdv" value="<?=$consultation['prochain_rdv'] ?? date('Y-m-d', strtotime('+3 months'))?>">
                </div>
                <div class="form-group">
                    <label>Type de suivi</label>
                    <select name="type_suivi">
                        <option value="routine" <?=($consultation['type_suivi'] ?? '') === 'routine' ? 'selected' : ''?>>Contrôle de routine</option>
                        <option value="urgence" <?=($consultation['type_suivi'] ?? '') === 'urgence' ? 'selected' : ''?>>Urgence</option>
                        <option value="bilan" <?=($consultation['type_suivi'] ?? '') === 'bilan' ? 'selected' : ''?>>Bilan complet</option>
                        <option value="specialiste" <?=($consultation['type_suivi'] ?? '') === 'specialiste' ? 'selected' : ''?>>Spécialiste</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <a href="gerer_rdv.php" class="btn-cancel">Annuler</a>
                <button type="submit" class="btn-save">✅ Sauvegarder le compte-rendu</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Mareme Faye (E5)</strong> — compte_rendu.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    function selectEtat(el) {
        document.querySelectorAll('.status-opt').forEach(function(e) { e.classList.remove('sel'); });
        el.classList.add('sel');
        document.getElementById('etat_general').value = el.getAttribute('data-value');
    }

    function selectDiag(el, diag) {
        const textarea = document.getElementById('diagnostic');
        const current = textarea.value;
        if (current.includes(diag)) {
            el.classList.remove('sel');
            textarea.value = current.replace(diag, '').trim().replace(/^,/, '').replace(/,,/, ',');
        } else {
            el.classList.add('sel');
            textarea.value = current ? current + ', ' + diag : diag;
        }
    }
</script>
</body>
</html>