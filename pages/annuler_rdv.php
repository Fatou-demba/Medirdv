<?php
// ================================================================
// annuler_rdv.php — Annulation de rendez-vous (Patient)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// Page développée par : Dieynaba Diallo (E8)
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Vérifier si l'utilisateur est connecté et est un patient
if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php');
}

$patient_id = $_SESSION['user_id'];

// ================================================================
// RÉCUPÉRATION DES INFOS PATIENT
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id AND statut = 'actif' LIMIT 1");
$stmt->execute([':id' => $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    session_destroy();
    redirect('login.php');
}

// ================================================================
// RÉCUPÉRATION DU RDV À ANNULER
// ================================================================
$rdv_id = (int)($_GET['id'] ?? 0);
$rdv = null;
$erreur = '';
$succes = '';
$confirm_annulation = false;

if ($rdv_id <= 0) {
    redirect('mes_rdv.php');
}

// Récupérer les détails du rendez-vous
$stmt = $pdo->prepare("
    SELECT r.*, 
           m.nom AS m_nom, m.prenom AS m_prenom, m.specialite, m.cabinet, m.ville, m.telephone,
           p.nom AS p_nom, p.prenom AS p_prenom
    FROM rendezvous r
    JOIN medecins m ON m.id = r.id_medecin
    JOIN patients p ON p.id = r.id_patient
    WHERE r.id = :id AND r.id_patient = :patient_id
");
$stmt->execute([':id' => $rdv_id, ':patient_id' => $patient_id]);
$rdv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rdv) {
    redirect('mes_rdv.php');
}

// Vérifier si le RDV peut être annulé (pas déjà annulé ou terminé)
if ($rdv['statut'] === 'annule') {
    redirect('mes_rdv.php?msg=deja_annule');
}
if ($rdv['statut'] === 'termine') {
    redirect('mes_rdv.php?msg=deja_termine');
}

// ================================================================
// TRAITEMENT DE L'ANNULATION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motif = trim($_POST['motif'] ?? '');
    $confirmation = isset($_POST['confirmation']) && $_POST['confirmation'] === 'on';
    
    if (!$confirmation) {
        $erreur = 'Veuillez confirmer l\'annulation en cochant la case.';
    } elseif (empty($motif)) {
        $erreur = 'Veuillez indiquer un motif d\'annulation.';
    } else {
        try {
            // Mettre à jour le statut du rendez-vous
            $stmt = $pdo->prepare("
                UPDATE rendezvous 
                SET statut = 'annule', 
                    motif_annulation = :motif 
                WHERE id = :id AND id_patient = :patient_id
            ");
            $stmt->execute([
                ':motif' => $motif,
                ':id' => $rdv_id,
                ':patient_id' => $patient_id
            ]);
            
            // Envoyer une notification au médecin
            $message = "Le patient " . $patient['prenom'] . " " . $patient['nom'] . 
                       " a annulé son rendez-vous du " . date('d/m/Y', strtotime($rdv['date_rdv'])) . 
                       " à " . substr($rdv['heure_rdv'], 0, 5) . 
                       ". Motif : " . $motif;
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (id_destinataire, type_user, type_notif, message, id_rdv, date_envoi)
                VALUES (:id, 'medecin', 'rdv_annule', :msg, :rdv_id, NOW())
            ");
            $stmt->execute([
                ':id' => $rdv['id_medecin'],
                ':msg' => $message,
                ':rdv_id' => $rdv_id
            ]);
            
            // Journaliser l'action
            $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, ip_address) 
                           VALUES (:id, 'patient', 'annulation_rdv_' . :rdv_id, :ip)")
                ->execute([
                    ':id' => $patient_id,
                    ':rdv_id' => $rdv_id,
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
            
            $_SESSION['success'] = '✅ Votre rendez-vous a été annulé avec succès. Une notification a été envoyée au médecin.';
            redirect('mes_rdv.php');
            
        } catch (Exception $e) {
            $erreur = '❌ Une erreur est survenue lors de l\'annulation. Veuillez réessayer.';
        }
    }
}

// ================================================================
// FORMATAGE DES DONNÉES
// ================================================================
$jours_fr = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
             'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$mois_fr = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril',
            'May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août',
            'September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];

function formatDateFr($date, $jours, $mois) {
    if (!$date) return '—';
    $dt = new DateTime($date);
    return $jours[$dt->format('l')] . ' ' . $dt->format('d') . ' ' . $mois[$dt->format('F')] . ' ' . $dt->format('Y');
}

$date_rdv_formatee = formatDateFr($rdv['date_rdv'], $jours_fr, $mois_fr);
$heure_rdv = substr($rdv['heure_rdv'], 0, 5);

$initiales = strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1));

// Récupérer les notifications non lues
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE id_destinataire = $patient_id AND type_user = 'patient' AND lu = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Annuler Rendez-vous</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--purple:#7209B7;--teal:#006D77;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF;min-height:100vh;display:flex;flex-direction:column}

        /* ===== NAVBAR ===== */
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
        .notif-btn{position:relative;color:#CBD5E1;font-size:1.1rem;text-decoration:none}
        .notif-btn .nb{position:absolute;top:-6px;right:-7px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700}

        /* ===== PAGE HEADER ===== */
        .page-hdr{background:linear-gradient(135deg,var(--red),#B91C1C);padding:1.8rem 5%;color:#fff;position:relative;overflow:hidden}
        .page-hdr::before{content:'🚫';position:absolute;right:5%;top:50%;transform:translateY(-50%);font-size:6rem;opacity:.08}
        .breadcrumb{font-size:.78rem;color:#FCA5A5;margin-bottom:.5rem}
        .breadcrumb span{color:var(--green)}
        .hdr-inner{display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap}
        .hdr-icon{font-size:2.5rem;flex-shrink:0}
        .hdr-text h1{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800}
        .hdr-text p{color:#FCA5A5;font-size:.85rem;margin-top:.3rem}
        .hdr-btns{margin-left:auto;display:flex;gap:.6rem;flex-wrap:wrap}
        .hdr-btn{padding:.55rem 1.2rem;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .hdr-btn:hover{background:rgba(255,255,255,.25)}

        /* ===== MAIN ===== */
        .main{max-width:800px;margin:0 auto;padding:1.5rem 5%;flex:1}

        /* ===== ALERT ===== */
        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}
        .alert-warning{background:#FFF7ED;border:1px solid #FED7AA;color:#C2410C}
        .alert-info{background:#EFF6FF;border:1px solid #BFDBFE;color:var(--blue)}

        /* ===== RDV CARD ===== */
        .rdv-summary{background:#fff;border-radius:14px;padding:1.5rem;border:1px solid var(--lgray);margin-bottom:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .rdv-summary .rdv-title{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--lgray);display:flex;align-items:center;gap:.5rem}
        .rdv-summary .rdv-title .badge-status{padding:.2rem .6rem;border-radius:20px;font-size:.7rem;font-weight:700;margin-left:.5rem}
        .rdv-summary .rdv-title .badge-status.en_attente{background:#FFF7ED;color:#C2410C;border:1px solid #FED7AA}
        .rdv-summary .rdv-title .badge-status.confirme{background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0}
        .rdv-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
        .rdv-item{display:flex;flex-direction:column;padding:.5rem .8rem;background:var(--light);border-radius:8px}
        .rdv-item .label{font-size:.7rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.5px}
        .rdv-item .value{font-size:.9rem;font-weight:600;color:#1E293B;margin-top:.1rem}

        /* ===== FORM ===== */
        .form-card{background:#fff;border-radius:14px;padding:1.8rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .form-card h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:.5rem}
        .form-card .subtitle{color:var(--gray);font-size:.85rem;margin-bottom:1.2rem}

        .form-group{margin-bottom:1.2rem}
        .form-group label{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:.4rem}
        .form-group label .req{color:var(--red)}
        .form-group textarea{width:100%;padding:.72rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;resize:vertical;min-height:100px}
        .form-group textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
        .form-group textarea.field-error{border-color:var(--red);background:#FFF5F5}

        .quick-motifs{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem}
        .qm-btn{padding:.3rem .8rem;border:1.5px solid var(--lgray);border-radius:20px;font-size:.75rem;font-weight:600;cursor:pointer;background:#fff;color:#334155;transition:.2s}
        .qm-btn:hover{border-color:var(--red);color:var(--red)}
        .qm-btn.sel{border-color:var(--red);background:#FFF5F5;color:var(--red)}

        .confirm-check{display:flex;align-items:center;gap:.6rem;padding:.8rem;background:#FFF5F5;border:1px solid #FECACA;border-radius:10px;margin-bottom:1.2rem}
        .confirm-check input[type="checkbox"]{width:18px;height:18px;accent-color:var(--red);cursor:pointer}
        .confirm-check label{font-size:.85rem;color:#7F1D1D;font-weight:600;cursor:pointer}

        .form-actions{display:flex;gap:.8rem;margin-top:1.5rem;flex-wrap:wrap}
        .btn-cancel{padding:.8rem 1.5rem;background:transparent;color:var(--gray);border:1.5px solid var(--lgray);border-radius:10px;font-size:.9rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .btn-cancel:hover{border-color:var(--blue);color:var(--blue)}
        .btn-annuler{padding:.8rem 2rem;background:var(--red);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 15px rgba(214,40,40,.3);transition:.2s;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:.4rem;flex:1;justify-content:center}
        .btn-annuler:hover{background:#B91C1C;transform:translateY(-1px);box-shadow:0 6px 25px rgba(214,40,40,.4)}
        .btn-annuler:disabled{opacity:.5;cursor:not-allowed}

        /* ===== WARNING BOX ===== */
        .warning-box{background:#FFF5F5;border:1px solid #FECACA;border-radius:10px;padding:1rem;margin-bottom:1.2rem}
        .warning-box .icon{font-size:1.5rem;margin-bottom:.3rem}
        .warning-box h5{font-family:'Sora',sans-serif;font-size:.9rem;font-weight:800;color:var(--red);margin-bottom:.3rem}
        .warning-box p{font-size:.82rem;color:#7F1D1D;line-height:1.5}
        .warning-box ul{margin:.5rem 0 0 1.2rem;font-size:.8rem;color:#7F1D1D}
        .warning-box ul li{margin-bottom:.2rem}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem;flex-shrink:0}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .page-hdr{padding:1.2rem 4%}
            .hdr-inner{flex-direction:column;text-align:center}
            .hdr-btns{margin-left:0;justify-content:center}
            .rdv-grid{grid-template-columns:1fr}
            .form-actions{flex-direction:column}
            .btn-annuler{width:100%}
            .btn-cancel{justify-content:center}
        }
        @media(max-width:480px){
            .page-hdr{padding:1rem 4%}
            .hdr-text h1{font-size:1.2rem}
            .hdr-icon{font-size:2rem}
            .main{padding:1rem 4%}
            .form-card{padding:1.2rem}
            .rdv-summary{padding:1rem}
            .rdv-item .value{font-size:.82rem}
            .warning-box{padding:.8rem}
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav>
    <a href="dashboard_patient.php" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="dashboard_patient.php">Accueil</a></li>
        <li><a href="mes_rdv.php">Mes RDV</a></li>
        <li><a href="dossier_patient.php">Mon Dossier</a></li>
        <li><a href="profil_patient.php">Mon Profil</a></li>
    </ul>
    <div style="display:flex;align-items:center;gap:1rem;">
        <a href="notifications.php" class="notif-btn">
            🔔
            <?php if($notif_count > 0): ?>
                <span class="nb"><?=$notif_count?></span>
            <?php endif; ?>
        </a>
        <a href="profil_patient.php" class="avatar">
            <?php if(!empty($patient['photo']) && file_exists(__DIR__.'/../assets/uploads/'.$patient['photo'])): ?>
                <img src="../assets/uploads/<?=htmlspecialchars($patient['photo'])?>" alt="">
            <?php else: ?>
                <?=$initiales?>
            <?php endif; ?>
        </a>
    </div>
</nav>

<!-- ===== PAGE HEADER ===== -->
<div class="page-hdr">
    <div class="breadcrumb">Mes RDV → <span>Annuler Rendez-vous</span></div>
    <div class="hdr-inner">
        <div class="hdr-icon">🚫</div>
        <div class="hdr-text">
            <h1>Annuler le rendez-vous</h1>
            <p>Vous êtes sur le point d'annuler votre rendez-vous avec Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?></p>
        </div>
        <div class="hdr-btns">
            <a href="mes_rdv.php" class="hdr-btn">← Retour</a>
        </div>
    </div>
</div>

<!-- ===== MAIN ===== -->
<div class="main">

    <?php if($erreur): ?>
        <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
    <?php endif; ?>

    <!-- RÉCAPITULATIF DU RDV -->
    <div class="rdv-summary">
        <div class="rdv-title">
            📅 Rendez-vous du <?=$date_rdv_formatee?>
            <span class="badge-status <?=$rdv['statut']?>">
                <?php if($rdv['statut'] === 'en_attente'): ?>
                    ⏳ En attente
                <?php elseif($rdv['statut'] === 'confirme'): ?>
                    ✅ Confirmé
                <?php endif; ?>
            </span>
        </div>
        <div class="rdv-grid">
            <div class="rdv-item">
                <span class="label">👨‍⚕️ Médecin</span>
                <span class="value">Dr. <?=htmlspecialchars($rdv['m_prenom'].' '.$rdv['m_nom'])?></span>
            </div>
            <div class="rdv-item">
                <span class="label">🫀 Spécialité</span>
                <span class="value"><?=htmlspecialchars($rdv['specialite'])?></span>
            </div>
            <div class="rdv-item">
                <span class="label">📅 Date</span>
                <span class="value"><?=$date_rdv_formatee?></span>
            </div>
            <div class="rdv-item">
                <span class="label">⏰ Heure</span>
                <span class="value"><?=$heure_rdv?></span>
            </div>
            <div class="rdv-item">
                <span class="label">📍 Cabinet</span>
                <span class="value"><?=htmlspecialchars($rdv['cabinet'])?>, <?=htmlspecialchars($rdv['ville'])?></span>
            </div>
            <div class="rdv-item">
                <span class="label">📝 Motif</span>
                <span class="value"><?=htmlspecialchars($rdv['motif'])?></span>
            </div>
        </div>
    </div>

    <!-- WARNING -->
    <div class="warning-box">
        <div class="icon">⚠️</div>
        <h5>Attention : Annulation définitive</h5>
        <p>L'annulation de ce rendez-vous est <strong>définitive</strong>. Une fois annulé, vous ne pourrez pas le réactiver.</p>
        <ul>
            <li>Vous devrez prendre un nouveau rendez-vous si vous souhaitez consulter à nouveau ce médecin.</li>
            <li>Une notification sera envoyée au médecin pour l'informer de votre annulation.</li>
            <?php if($rdv['statut'] === 'confirme'): ?>
                <li><strong style="color:var(--red);">⚠️ Ce rendez-vous est déjà confirmé. Son annulation pourrait entraîner des frais selon la politique du médecin.</strong></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- FORMULAIRE D'ANNULATION -->
    <div class="form-card">
        <h3>✏️ Motif d'annulation</h3>
        <p class="subtitle">Aidez-nous à comprendre pourquoi vous annulez ce rendez-vous.</p>

        <form method="POST" action="annuler_rdv.php?id=<?=$rdv_id?>" id="annulationForm">
            <div class="form-group">
                <label>Motif d'annulation <span class="req">*</span></label>
                <div class="quick-motifs">
                    <button type="button" class="qm-btn" onclick="selectMotif(this, 'Je ne suis plus disponible à cette date.')">📅 Indisponibilité</button>
                    <button type="button" class="qm-btn" onclick="selectMotif(this, 'Je me sens mieux, la consultation n\'est plus nécessaire.')">😊 Plus nécessaire</button>
                    <button type="button" class="qm-btn" onclick="selectMotif(this, 'J\'ai trouvé un autre médecin.')">🔍 Autre médecin</button>
                    <button type="button" class="qm-btn" onclick="selectMotif(this, 'Problème de transport / déplacement.')">🚗 Transport</button>
                    <button type="button" class="qm-btn" onclick="selectMotif(this, 'Autre motif')">📌 Autre</button>
                </div>
                <textarea name="motif" id="motif" placeholder="Décrivez le motif de votre annulation..." class="<?=$erreur && strpos($erreur, 'motif') !== false ? 'field-error' : ''?>" required><?=htmlspecialchars($_POST['motif'] ?? '')?></textarea>
            </div>

            <div class="confirm-check">
                <input type="checkbox" id="confirmation" name="confirmation" required>
                <label for="confirmation">Je confirme l'annulation de ce rendez-vous. Je comprends que cette action est <strong style="color:var(--red);">définitive</strong>.</label>
            </div>

            <div class="form-actions">
                <a href="mes_rdv.php" class="btn-cancel">
                    <i class="fas fa-arrow-left"></i> Annuler
                </a>
                <button type="submit" class="btn-annuler" id="btnAnnuler" disabled>
                    <i class="fas fa-trash-alt"></i> Confirmer l'annulation
                </button>
            </div>
        </form>
    </div>

</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page développée par : <strong>Dieynaba Diallo (E8)</strong> — annuler_rdv.php</p>
</footer>

<script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    function selectMotif(btn, motif) {
        // Désélectionner tous les boutons
        document.querySelectorAll('.qm-btn').forEach(function(b) {
            b.classList.remove('sel');
        });
        // Sélectionner le bouton cliqué
        btn.classList.add('sel');
        // Remplir le textarea
        document.getElementById('motif').value = motif;
        // Déclencher l'événement input pour valider
        document.getElementById('motif').dispatchEvent(new Event('input'));
    }

    // Validation du formulaire
    document.addEventListener('DOMContentLoaded', function() {
        const motif = document.getElementById('motif');
        const confirmation = document.getElementById('confirmation');
        const btnAnnuler = document.getElementById('btnAnnuler');

        function validateForm() {
            const motifValide = motif.value.trim().length > 0;
            const confirmationValide = confirmation.checked;
            btnAnnuler.disabled = !(motifValide && confirmationValide);
        }

        motif.addEventListener('input', validateForm);
        confirmation.addEventListener('change', validateForm);
    });
</script>
</body>
</html>