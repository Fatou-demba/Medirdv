<?php
// ================================================================
// prendre_rdv.php — Prise de rendez-vous en ligne
// Responsable : Diama Touré (Etudiant 3)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Protection : patient connecté uniquement
if (!isLoggedIn() || getRole() !== 'patient') {
    redirect('login.php?redirect=prendre_rdv.php' . (isset($_GET['medecin']) ? '%3Fmedecin%3D'.$_GET['medecin'] : ''));
}

$patient_id = $_SESSION['user_id'];

// ================================================================
// RÉCUPÉRER LE MÉDECIN (depuis GET ou POST)
// ================================================================
$medecin_id = (int)($_GET['medecin'] ?? $_POST['medecin_id'] ?? 0);

// Si pas de médecin spécifié → rediriger vers liste
if ($medecin_id === 0) redirect('liste_medecins.php');

$stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id AND statut = 'valide' LIMIT 1");
$stmt->execute([':id' => $medecin_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$medecin) redirect('liste_medecins.php');

// ================================================================
// MAPPING JOURS
// ================================================================
$jours_fr_num = [1=>'lundi',2=>'mardi',3=>'mercredi',4=>'jeudi',5=>'vendredi',6=>'samedi',7=>'dimanche'];
$mois_fr = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$jours_court = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

// ================================================================
// DISPONIBILITÉS DU MÉDECIN (jours actifs)
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM disponibilites WHERE id_medecin = :id AND actif = 1 ORDER BY FIELD(jour_semaine,'lundi','mardi','mercredi','jeudi','vendredi','samedi')");
$stmt->execute([':id' => $medecin_id]);
$dispos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jours disponibles (noms français)
$jours_dispo = array_column($dispos, 'jour_semaine');

// ================================================================
// CALENDRIER — Mois affiché
// ================================================================
$today     = new DateTime();
$today->setTime(0,0,0);
$nav_month = (int)($_GET['mois'] ?? $today->format('n'));
$nav_year  = (int)($_GET['annee'] ?? $today->format('Y'));

// Empêcher de naviguer dans le passé
if ($nav_year < (int)$today->format('Y') ||
    ($nav_year === (int)$today->format('Y') && $nav_month < (int)$today->format('n'))) {
    $nav_month = (int)$today->format('n');
    $nav_year  = (int)$today->format('Y');
}
// Max 3 mois à l'avance
$max_date = clone $today;
$max_date->modify('+3 months');

$cal_date = new DateTime("$nav_year-$nav_month-01");
$prev_month = (int)$cal_date->format('n') - 1;
$prev_year  = (int)$cal_date->format('Y');
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = (int)$cal_date->format('n') + 1;
$next_year  = (int)$cal_date->format('Y');
if ($next_month > 12) { $next_month = 1; $next_year++; }

$days_in_month = (int)$cal_date->format('t');
$first_dow     = (int)$cal_date->format('N'); // 1=lundi, 7=dimanche

// ================================================================
// DATE SÉLECTIONNÉE
// ================================================================
$date_sel = $_GET['date'] ?? '';
if ($date_sel !== '') {
    // Valider le format et que ce n'est pas dans le passé
    $dt_sel = DateTime::createFromFormat('Y-m-d', $date_sel);
    if (!$dt_sel || $dt_sel < $today) $date_sel = '';
}

// ================================================================
// CRÉNEAUX POUR LA DATE SÉLECTIONNÉE
// ================================================================
$creneaux_matin   = [];
$creneaux_apmidi  = [];
$date_sel_label   = '';

if ($date_sel !== '') {
    $dt_sel   = new DateTime($date_sel);
    $dow      = (int)$dt_sel->format('N'); // 1=lundi
    $jour_fr  = $jours_fr_num[$dow] ?? '';
    $date_sel_label = ucfirst($jours_court[$dow-1]) . '. ' . $dt_sel->format('j') . ' ' . $mois_fr[(int)$dt_sel->format('n')] . ' ' . $dt_sel->format('Y');

    // Disponibilités ce jour-là
    $dispo_jour = array_filter($dispos, fn($d) => $d['jour_semaine'] === $jour_fr);

    // RDV déjà pris ce jour-là chez ce médecin
    $stmt = $pdo->prepare("SELECT heure_rdv FROM rendezvous WHERE id_medecin=:m AND date_rdv=:d AND statut NOT IN ('annule','refuse')");
    $stmt->execute([':m'=>$medecin_id, ':d'=>$date_sel]);
    $rdv_pris = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'heure_rdv');

    foreach ($dispo_jour as $dispo) {
        $heure   = new DateTime($dispo['heure_debut']);
        $fin     = new DateTime($dispo['heure_fin']);
        $duree   = (int)($dispo['duree_rdv'] ?? 30);

        while ($heure < $fin) {
            $heure_str = $heure->format('H:i');
            $creneau   = [
                'heure'  => $heure_str,
                'booked' => in_array($heure->format('H:i:s'), $rdv_pris) || in_array($heure_str.':00', $rdv_pris),
            ];
            if ((int)$heure->format('H') < 13) {
                $creneaux_matin[] = $creneau;
            } else {
                $creneaux_apmidi[] = $creneau;
            }
            $heure->modify("+{$duree} minutes");
        }
    }
}

// ================================================================
// HEURE SÉLECTIONNÉE
// ================================================================
$heure_sel = $_GET['heure'] ?? '';

// ================================================================
// TRAITEMENT — SOUMISSION DU RDV
// ================================================================
$erreur_rdv = '';
$succes_rdv = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirmer_rdv') {
    $p_medecin = (int)($_POST['medecin_id'] ?? 0);
    $p_date    = trim($_POST['date_rdv']    ?? '');
    $p_heure   = trim($_POST['heure_rdv']   ?? '');
    $p_motif   = trim($_POST['motif']       ?? '');

    // Validations
    if ($p_date === '' || $p_heure === '') {
        $erreur_rdv = 'Veuillez sélectionner une date et un créneau horaire.';
    } elseif ($p_motif === '') {
        $erreur_rdv = 'Veuillez indiquer le motif de consultation.';
    } elseif (strlen($p_motif) < 10) {
        $erreur_rdv = 'Le motif doit contenir au moins 10 caractères.';
    } else {
        // Anti-doublon : vérifier que ce créneau n'est pas déjà pris
        $chk = $pdo->prepare("SELECT id FROM rendezvous WHERE id_medecin=:m AND date_rdv=:d AND heure_rdv=:h AND statut NOT IN ('annule','refuse') LIMIT 1");
        $chk->execute([':m'=>$p_medecin, ':d'=>$p_date, ':h'=>$p_heure.':00']);
        if ($chk->fetch()) {
            $erreur_rdv = 'Ce créneau vient d\'être pris. Veuillez en choisir un autre.';
        } else {
            // Vérifier que le patient n'a pas déjà un RDV ce jour avec ce médecin
            $chk2 = $pdo->prepare("SELECT id FROM rendezvous WHERE id_patient=:p AND id_medecin=:m AND date_rdv=:d AND statut NOT IN ('annule','refuse') LIMIT 1");
            $chk2->execute([':p'=>$patient_id, ':m'=>$p_medecin, ':d'=>$p_date]);
            if ($chk2->fetch()) {
                $erreur_rdv = 'Vous avez déjà un rendez-vous avec ce médecin ce jour.';
            } else {
                // Insérer le RDV
                $pdo->prepare("
                    INSERT INTO rendezvous (id_patient, id_medecin, date_rdv, heure_rdv, motif, statut)
                    VALUES (:p, :m, :d, :h, :motif, 'en_attente')
                ")->execute([
                    ':p'     => $patient_id,
                    ':m'     => $p_medecin,
                    ':d'     => $p_date,
                    ':h'     => $p_heure . ':00',
                    ':motif' => $p_motif,
                ]);
                $rdv_id = $pdo->lastInsertId();

                // Notification au patient
                $pdo->prepare("INSERT INTO notifications (id_destinataire, type_user, type_notif, message, id_rdv)
                               VALUES (:id,'patient','nouveau_rdv',:msg,:rdv)")
                    ->execute([':id'=>$patient_id, ':rdv'=>$rdv_id,
                               ':msg'=>"Votre demande de RDV avec Dr. {$medecin['prenom']} {$medecin['nom']} le $p_date à $p_heure a été envoyée."]);

                // Notification au médecin
                $patient_info = $pdo->prepare("SELECT nom,prenom FROM patients WHERE id=:id");
                $patient_info->execute([':id'=>$patient_id]);
                $pat = $patient_info->fetch();
                $pdo->prepare("INSERT INTO notifications (id_destinataire, type_user, type_notif, message, id_rdv)
                               VALUES (:id,'medecin','nouveau_rdv',:msg,:rdv)")
                    ->execute([':id'=>$medecin_id, ':rdv'=>$rdv_id,
                               ':msg'=>"Nouvelle demande de RDV de {$pat['prenom']} {$pat['nom']} le $p_date à $p_heure."]);

                $succes_rdv = true;
                redirect('mes_rdv.php?rdv_confirme=1');
            }
        }
    }
}

// ================================================================
// INFOS PATIENT (pour le récapitulatif)
// ================================================================
$stmt = $pdo->prepare("SELECT nom, prenom FROM patients WHERE id=:id");
$stmt->execute([':id'=>$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_destinataire=:id AND type_user='patient' AND lu=0");
$stmt->execute([':id'=>$patient_id]);
$notif_count = (int)$stmt->fetchColumn();

// Couleur selon spécialité
$couleurs = [
    'Cardiologie'   => 'linear-gradient(135deg,#0077B6,#023E8A)',
    'Pediatrie'     => 'linear-gradient(135deg,#7209B7,#B5179E)',
    'Dermatologie'  => 'linear-gradient(135deg,#F77F00,#D62828)',
    'Gynecologie'   => 'linear-gradient(135deg,#006D77,#2DC653)',
    'Neurologie'    => 'linear-gradient(135deg,#3A0CA3,#7B2FBE)',
    'Ophtalmologie' => 'linear-gradient(135deg,#0096C7,#48CAE4)',
    'default'       => 'linear-gradient(135deg,#0077B6,#023E8A)',
];
$grad = $couleurs[$medecin['specialite']] ?? $couleurs['default'];

// Tarifs
$tarifs = ['Cardiologie'=>'10 000','Pediatrie'=>'8 000','Dermatologie'=>'12 000',
           'Gynecologie'=>'9 000','Neurologie'=>'15 000','Ophtalmologie'=>'10 000','default'=>'10 000'];
$tarif = $tarifs[$medecin['specialite']] ?? $tarifs['default'];
$initiales = strtoupper(mb_substr($_SESSION['prenom'],0,1).mb_substr($_SESSION['nom']??'',0,1));

// Helper : est-ce qu'un jour du mois est disponible ?
function jourDispo($year, $month, $day, $jours_dispo, $jours_fr_num, $today) {
    $dt = new DateTime("$year-$month-$day");
    if ($dt < $today) return 'past';
    $dow = (int)$dt->format('N');
    $nom = $jours_fr_num[$dow] ?? '';
    if ($dow === 7) return 'unavailable'; // dimanche jamais dispo
    return in_array($nom, $jours_dispo) ? 'available' : 'unavailable';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Prendre un RDV</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--red:#D62828;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0}
body{font-family:'DM Sans',sans-serif;background:#F8FAFF;color:#1E293B}

/* ---- NAVBAR ---- */
nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(3,4,94,.4)}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
.nav-logo span{color:var(--green)}
.nav-links{display:flex;gap:1.8rem;list-style:none}
.nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;font-weight:500;transition:.2s}
.nav-links a:hover,.nav-links a.active{color:#fff}
.nav-right{display:flex;align-items:center;gap:1rem}
.notif-btn{color:#CBD5E1;font-size:.88rem;text-decoration:none;position:relative}
.notif-dot{position:absolute;top:-6px;right:-6px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;font-size:.58rem;display:flex;align-items:center;justify-content:center;font-weight:700}
.avatar-sm{width:34px;height:34px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-size:.82rem;color:#fff;font-weight:700}
.nav-user{display:flex;align-items:center;gap:.5rem;color:#CBD5E1;font-size:.85rem}
.nav-hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:.4rem}
.nav-hamburger span{display:block;width:24px;height:2px;background:#CBD5E1;border-radius:2px;transition:.3s}

/* ---- PAGE HEADER ---- */
.page-hdr{background:linear-gradient(135deg,var(--dark),var(--blue2));padding:1.8rem 5%;color:#fff}
.breadcrumb{font-size:.78rem;color:#94A3B8;margin-bottom:.5rem}
.breadcrumb a{color:#94A3B8;text-decoration:none}
.breadcrumb a:hover{color:#fff}
.breadcrumb span{color:var(--green)}
.page-hdr h1{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800}
.page-hdr p{color:#CBD5E1;font-size:.88rem;margin-top:.3rem}

/* ---- STEPS BAR ---- */
.steps-bar{background:#fff;border-bottom:1px solid var(--lgray);padding:1rem 5%;display:flex;gap:0;overflow-x:auto}
.step-item{display:flex;align-items:center;gap:.6rem;flex:1;position:relative;min-width:0}
.step-item:not(:last-child)::after{content:'';position:absolute;right:0;top:50%;transform:translateY(-50%);width:30px;height:2px;background:var(--lgray);z-index:0}
.step-item.done::after{background:var(--green)}
.s-num{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;z-index:1;position:relative}
.step-item.done .s-num{background:var(--green);color:#fff}
.step-item.active .s-num{background:var(--blue);color:#fff;box-shadow:0 0 0 4px rgba(0,119,182,.15)}
.step-item.inactive .s-num{background:var(--lgray);color:var(--gray)}
.s-lbl{font-size:.78rem;font-weight:600;color:var(--gray);white-space:nowrap}
.step-item.active .s-lbl{color:var(--blue)}
.step-item.done .s-lbl{color:var(--green)}

/* ---- MAIN LAYOUT ---- */
.main{display:flex;gap:1.5rem;padding:1.5rem 5%;max-width:1100px;margin:0 auto;align-items:flex-start}
.main-left{flex:1;min-width:0}

/* ---- DOCTOR PANEL ---- */
.doctor-panel{background:#fff;border-radius:14px;padding:1.2rem;border:1px solid var(--lgray);margin-bottom:1.2rem;display:flex;gap:1rem;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.05);flex-wrap:wrap}
.doc-av{width:60px;height:60px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;overflow:hidden}
.doc-av img{width:100%;height:100%;object-fit:cover;border-radius:12px}
.doc-inf h4{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:#1E293B}
.doc-spec{background:#EFF6FF;color:var(--blue);font-size:.73rem;font-weight:600;padding:.2rem .55rem;border-radius:5px;display:inline-block;margin-top:.2rem}
.doc-inf p{font-size:.82rem;color:var(--gray);margin-top:.3rem}
.change-btn{margin-left:auto;padding:.5rem 1rem;border:1.5px solid var(--blue);color:var(--blue);background:transparent;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:.2s;white-space:nowrap}
.change-btn:hover{background:#EFF6FF}

/* Alertes */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

/* ---- CALENDRIER ---- */
.cal-section{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:1.2rem}
.cal-section h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:1rem}
.cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
.cal-nav a{width:34px;height:34px;border-radius:8px;border:1px solid var(--lgray);background:#fff;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#334155;transition:.2s}
.cal-nav a:hover{border-color:var(--blue);color:var(--blue)}
.cal-nav a.disabled{opacity:.3;pointer-events:none}
.cal-nav span{font-weight:700;color:var(--dark);font-size:.9rem}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.3rem}
.cal-head{text-align:center;font-size:.72rem;font-weight:700;color:var(--gray);padding:.3rem}
.cal-day{text-align:center;padding:.5rem .2rem;border-radius:8px;font-size:.82rem;transition:.15s;line-height:1}
.cal-day.empty{background:transparent}
.cal-day.past{color:#CBD5E1}
.cal-day.available{color:#1E293B;background:#F0FDF4;cursor:pointer}
.cal-day.available:hover{background:var(--green);color:#fff}
.cal-day.selected{background:var(--blue);color:#fff;font-weight:700}
.cal-day.today-mark{border:2px solid var(--blue);font-weight:700}
.cal-day.unavailable{color:#CBD5E1;background:#FAFAFA}
.cal-legend{display:flex;gap:1rem;margin-top:1rem;font-size:.75rem;flex-wrap:wrap}
.cal-legend span{display:flex;align-items:center;gap:.4rem}
.leg-dot{width:12px;height:12px;border-radius:3px;display:inline-block}

/* ---- CRÉNEAUX ---- */
.slots-section{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:1.2rem}
.slots-section h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:1rem}
.slot-period{margin-bottom:.8rem}
.slot-period-title{font-size:.78rem;font-weight:600;color:var(--gray);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.5px}
.slots-grid{display:flex;gap:.5rem;flex-wrap:wrap}
.slot{padding:.45rem .9rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;transition:.15s;border:1.5px solid var(--lgray);background:#fff;color:#334155}
.slot:hover{border-color:var(--blue);color:var(--blue);background:#EFF6FF}
.slot.selected{background:var(--blue);color:#fff;border-color:var(--blue)}
.slot.booked{background:#F1F5F9;color:#CBD5E1;cursor:not-allowed;border-color:var(--lgray)}
.no-slots{color:var(--gray);font-size:.85rem;padding:1rem;text-align:center;background:var(--light);border-radius:10px}
.select-date-msg{color:var(--gray);font-size:.85rem;padding:1.5rem;text-align:center;background:var(--light);border-radius:10px}

/* ---- MOTIF ---- */
.motif-section{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:1.2rem}
.motif-section h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:.8rem}
.motif-section textarea{width:100%;padding:.8rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;font-size:.87rem;font-family:'DM Sans',sans-serif;resize:vertical;min-height:90px;outline:none;transition:.2s;color:#1E293B}
.motif-section textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.07)}
.char-count{font-size:.72rem;color:var(--gray);text-align:right;margin-top:.3rem}

/* ---- SIDEBAR RÉCAPITULATIF ---- */
.sidebar{width:280px;flex-shrink:0}
.summary-card{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 2px 10px rgba(0,0,0,.05);position:sticky;top:80px}
.summary-card h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid var(--lgray)}
.sum-row{display:flex;justify-content:space-between;margin-bottom:.7rem;font-size:.85rem;gap:.5rem}
.sum-row .label{color:var(--gray);flex-shrink:0}
.sum-row .value{font-weight:600;color:#1E293B;text-align:right;word-break:break-word}
.sum-divider{border:none;border-top:1px solid var(--lgray);margin:.8rem 0}
.sum-placeholder{color:#CBD5E1;font-size:.8rem;font-style:italic}
.confirm-btn{width:100%;padding:.85rem;background:var(--green);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 15px rgba(45,198,83,.3);transition:.2s;margin-top:1rem;font-family:'DM Sans',sans-serif}
.confirm-btn:hover{background:#25B347;transform:translateY(-1px)}
.confirm-btn:disabled{background:#CBD5E1;box-shadow:none;cursor:not-allowed;transform:none}
.confirm-note{font-size:.74rem;color:var(--gray);text-align:center;margin-top:.6rem;line-height:1.4}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
footer strong{color:#fff}

/* ================================================================
   RESPONSIVE
================================================================ */
@media(max-width:900px){
  .sidebar{width:240px}
}
@media(max-width:768px){
  .nav-links{display:none;position:fixed;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:1rem 0;z-index:99;box-shadow:0 8px 20px rgba(0,0,0,.3)}
  .nav-links.open{display:flex}
  .nav-links a{padding:.75rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
  .nav-hamburger{display:flex}
  .main{flex-direction:column;padding:1rem 4%}
  .sidebar{width:100%;position:static}
  .summary-card{position:static}
  .steps-bar{padding:.8rem 4%}
  .s-lbl{display:none}
  .cal-day{padding:.4rem .1rem;font-size:.78rem}
}
@media(max-width:480px){
  .page-hdr h1{font-size:1.3rem}
  .doctor-panel{gap:.7rem}
  .doc-av{width:48px;height:48px;font-size:1.3rem}
}
</style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav>
  <a href="../index.php" class="nav-logo">Medi<span>RDV</span></a>
  <button class="nav-hamburger" id="hamburger" onclick="toggleMenu()">
    <span></span><span></span><span></span>
  </button>
  <ul class="nav-links" id="navLinks">
    <li><a href="../index.php">Accueil</a></li>
    <li><a href="liste_medecins.php">Médecins</a></li>
    <li><a href="mes_rdv.php">Mes RDV</a></li>
    <li><a href="profil_patient.php">Mon Profil</a></li>
  </ul>
  <div class="nav-right">
    <a href="notifications.php" class="notif-btn">
      🔔<?php if($notif_count>0):?><span class="notif-dot"><?=$notif_count?></span><?php endif;?>
    </a>
    <div class="nav-user">
      <div class="avatar-sm"><?=$initiales?></div>
      <span><?=htmlspecialchars($_SESSION['prenom']??'')?></span>
    </div>
  </div>
</nav>

<!-- ============================================================
     PAGE HEADER
============================================================ -->
<div class="page-hdr">
  <div class="breadcrumb">
    <a href="../index.php">Accueil</a> →
    <a href="liste_medecins.php">Médecins</a> →
    <span>Prendre RDV</span>
  </div>
  <h1>Prendre un Rendez-Vous</h1>
  <p>Choisissez votre date et heure de consultation</p>
</div>

<!-- ============================================================
     STEPS
============================================================ -->
<div class="steps-bar">
  <div class="step-item done">
    <div class="s-num">✓</div>
    <div class="s-lbl">Médecin choisi</div>
  </div>
  <div class="step-item <?= $date_sel ? 'done' : 'active' ?>">
    <div class="s-num"><?= $date_sel ? '✓' : '2' ?></div>
    <div class="s-lbl">Date & Heure</div>
  </div>
  <div class="step-item <?= ($date_sel && $heure_sel) ? 'active' : 'inactive' ?>">
    <div class="s-num">3</div>
    <div class="s-lbl">Motif</div>
  </div>
  <div class="step-item inactive">
    <div class="s-num">4</div>
    <div class="s-lbl">Confirmation</div>
  </div>
</div>

<!-- ============================================================
     FORMULAIRE PRINCIPAL
============================================================ -->
<form method="POST" action="prendre_rdv.php" id="rdvForm">
<input type="hidden" name="action"      value="confirmer_rdv">
<input type="hidden" name="medecin_id"  value="<?= $medecin_id ?>">
<input type="hidden" name="date_rdv"    id="inp_date"  value="<?= htmlspecialchars($date_sel) ?>">
<input type="hidden" name="heure_rdv"   id="inp_heure" value="<?= htmlspecialchars($heure_sel) ?>">

<div class="main">
  <div class="main-left">

    <!-- ERREUR -->
    <?php if ($erreur_rdv !== ''): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($erreur_rdv) ?></div>
    <?php endif; ?>

    <!-- MÉDECIN SÉLECTIONNÉ -->
    <div class="doctor-panel">
      <div class="doc-av" style="background:<?= $grad ?>">
        <?php if(!empty($medecin['photo']) && file_exists(__DIR__.'/../assets/img/'.$medecin['photo'])): ?>
          <img src="../assets/img/<?= htmlspecialchars($medecin['photo']) ?>" alt="Dr">
        <?php else: ?>
          👨‍⚕️
        <?php endif; ?>
      </div>
      <div class="doc-inf">
        <h4>Dr. <?= htmlspecialchars($medecin['prenom'].' '.$medecin['nom']) ?></h4>
        <span class="doc-spec"><?= htmlspecialchars($medecin['specialite']) ?></span>
        <p>📍 <?= htmlspecialchars($medecin['cabinet'].', '.$medecin['ville']) ?> &nbsp;|&nbsp; ⏱ 30 min</p>
      </div>
      <a href="liste_medecins.php" class="change-btn">Changer →</a>
    </div>

    <!-- CALENDRIER -->
    <div class="cal-section">
      <h4>📅 Choisissez une date</h4>
      <div class="cal-nav">
        <?php
          $prev_url = "prendre_rdv.php?medecin=$medecin_id&mois=$prev_month&annee=$prev_year" . ($date_sel?"&date=$date_sel":'');
          $next_url = "prendre_rdv.php?medecin=$medecin_id&mois=$next_month&annee=$next_year" . ($date_sel?"&date=$date_sel":'');
          $can_prev = !($nav_year === (int)$today->format('Y') && $nav_month <= (int)$today->format('n'));
          $next_cal = new DateTime("$next_year-$next_month-01");
          $can_next = $next_cal <= $max_date;
        ?>
        <a href="<?= $can_prev ? $prev_url : '#' ?>" class="<?= $can_prev?'':'disabled'?>">←</a>
        <span><?= $mois_fr[$nav_month] . ' ' . $nav_year ?></span>
        <a href="<?= $can_next ? $next_url : '#' ?>" class="<?= $can_next?'':'disabled'?>">→</a>
      </div>

      <div class="cal-grid">
        <?php foreach ($jours_court as $jc): ?>
          <div class="cal-head"><?= $jc ?></div>
        <?php endforeach; ?>

        <?php
          // Cases vides avant le 1er
          for ($e = 1; $e < $first_dow; $e++):
        ?>
          <div class="cal-day empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $days_in_month; $d++):
          $date_str = sprintf('%04d-%02d-%02d', $nav_year, $nav_month, $d);
          $etat     = jourDispo($nav_year, $nav_month, $d, $jours_dispo, $jours_fr_num, $today);
          $is_today = ($date_str === $today->format('Y-m-d'));
          $is_sel   = ($date_str === $date_sel);

          $classes = 'cal-day';
          if ($is_sel) {
              $classes .= ' selected';
          } elseif ($etat === 'past') {
              $classes .= ' past';
          } elseif ($etat === 'available') {
              $classes .= ' available';
              if ($is_today) $classes .= ' today-mark';
          } else {
              $classes .= ($etat === 'past') ? ' past' : ' unavailable';
          }

          $url_day = ($etat === 'available' && !$is_sel)
            ? "prendre_rdv.php?medecin=$medecin_id&mois=$nav_month&annee=$nav_year&date=$date_str"
            : ($is_sel ? "prendre_rdv.php?medecin=$medecin_id&mois=$nav_month&annee=$nav_year" : '#');
        ?>
          <?php if ($etat === 'available'): ?>
            <a href="<?= $url_day ?>" class="<?= $classes ?>" title="<?= $date_str ?>"><?= $d ?></a>
          <?php else: ?>
            <div class="<?= $classes ?>"><?= $d ?></div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>

      <div class="cal-legend">
        <span><span class="leg-dot" style="background:#F0FDF4;border:1px solid #BBF7D0"></span>Disponible</span>
        <span><span class="leg-dot" style="background:var(--blue)"></span>Sélectionné</span>
        <span><span class="leg-dot" style="background:#FAFAFA;border:1px solid var(--lgray)"></span>Non disponible</span>
      </div>
    </div>

    <!-- CRÉNEAUX HORAIRES -->
    <div class="slots-section">
      <h4>🕐 Créneaux disponibles
        <?php if ($date_sel_label): ?>
          — <span style="color:var(--blue);font-weight:600"><?= $date_sel_label ?></span>
        <?php endif; ?>
      </h4>

      <?php if ($date_sel === ''): ?>
        <div class="select-date-msg">📅 Sélectionnez d'abord une date dans le calendrier</div>

      <?php elseif (empty($creneaux_matin) && empty($creneaux_apmidi)): ?>
        <div class="no-slots">😔 Aucun créneau disponible pour cette date. Essayez une autre date.</div>

      <?php else: ?>
        <?php if (!empty($creneaux_matin)): ?>
        <div class="slot-period">
          <div class="slot-period-title">☀️ Matin</div>
          <div class="slots-grid">
            <?php foreach ($creneaux_matin as $cr):
              $is_booked  = $cr['booked'];
              $is_sel_h   = ($cr['heure'] === $heure_sel);
              $cls = 'slot' . ($is_booked ? ' booked' : ($is_sel_h ? ' selected' : ''));
            ?>
              <?php if ($is_booked): ?>
                <div class="<?= $cls ?>"><?= $cr['heure'] ?></div>
              <?php else: ?>
                <div class="<?= $cls ?>" onclick="selectSlot('<?= $cr['heure'] ?>')"><?= $cr['heure'] ?></div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($creneaux_apmidi)): ?>
        <div class="slot-period">
          <div class="slot-period-title">🌙 Après-midi</div>
          <div class="slots-grid">
            <?php foreach ($creneaux_apmidi as $cr):
              $is_booked = $cr['booked'];
              $is_sel_h  = ($cr['heure'] === $heure_sel);
              $cls = 'slot' . ($is_booked ? ' booked' : ($is_sel_h ? ' selected' : ''));
            ?>
              <?php if ($is_booked): ?>
                <div class="<?= $cls ?>"><?= $cr['heure'] ?></div>
              <?php else: ?>
                <div class="<?= $cls ?>" onclick="selectSlot('<?= $cr['heure'] ?>')"><?= $cr['heure'] ?></div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- MOTIF -->
    <div class="motif-section">
      <h4>📝 Motif de consultation</h4>
      <textarea name="motif" id="motifText" placeholder="Décrivez brièvement la raison de votre consultation..." maxlength="500" oninput="updateCount()"><?= htmlspecialchars($_POST['motif'] ?? '') ?></textarea>
      <div class="char-count"><span id="charCount">0</span> / 500 caractères</div>
    </div>

  </div><!-- /main-left -->

  <!-- ============================================================
       SIDEBAR RÉCAPITULATIF
  ============================================================ -->
  <div class="sidebar">
    <div class="summary-card">
      <h4>📋 Récapitulatif</h4>

      <div class="sum-row">
        <span class="label">Médecin</span>
        <span class="value">Dr. <?= htmlspecialchars($medecin['prenom'].' '.$medecin['nom']) ?></span>
      </div>
      <div class="sum-row">
        <span class="label">Spécialité</span>
        <span class="value"><?= htmlspecialchars($medecin['specialite']) ?></span>
      </div>
      <div class="sum-row">
        <span class="label">Cabinet</span>
        <span class="value"><?= htmlspecialchars($medecin['cabinet'].', '.$medecin['ville']) ?></span>
      </div>

      <hr class="sum-divider">

      <div class="sum-row">
        <span class="label">Date</span>
        <span class="value" style="color:var(--blue);font-weight:700" id="sum_date">
          <?= $date_sel ? $date_sel_label : '<span class="sum-placeholder">À sélectionner</span>' ?>
        </span>
      </div>
      <div class="sum-row">
        <span class="label">Heure</span>
        <span class="value" style="color:var(--blue);font-weight:700" id="sum_heure">
          <?= $heure_sel ? $heure_sel : '<span class="sum-placeholder">À sélectionner</span>' ?>
        </span>
      </div>
      <div class="sum-row">
        <span class="label">Durée</span>
        <span class="value">30 minutes</span>
      </div>
      <div class="sum-row">
        <span class="label">Tarif</span>
        <span class="value"><?= $tarif ?> FCFA</span>
      </div>

      <hr class="sum-divider">

      <div class="sum-row">
        <span class="label">Patient</span>
        <span class="value"><?= htmlspecialchars($patient['prenom'].' '.$patient['nom']) ?></span>
      </div>

      <button type="submit" class="confirm-btn" id="confirmBtn"
              <?= ($date_sel && $heure_sel) ? '' : 'disabled' ?>>
        ✅ Confirmer le RDV
      </button>
      <div class="confirm-note">Vous recevrez une notification dès confirmation par le médecin</div>
    </div>
  </div>

</div><!-- /main -->
</form>

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Diama Touré (E3)</strong> — prendre_rdv.php</p>
</footer>

<script>
// --- Hamburger ---
function toggleMenu(){
  var nav=document.getElementById('navLinks'),icon=document.getElementById('hamburger');
  nav.classList.toggle('open');
  var s=icon.querySelectorAll('span');
  if(nav.classList.contains('open')){s[0].style.transform='rotate(45deg) translate(5px,5px)';s[1].style.opacity='0';s[2].style.transform='rotate(-45deg) translate(5px,-5px)';}
  else{s.forEach(function(x){x.style.transform='';x.style.opacity='';})}
}

// --- Sélection créneau ---
function selectSlot(heure) {
  // Mettre à jour le champ caché
  document.getElementById('inp_heure').value = heure;

  // Mettre à jour le récapitulatif
  document.getElementById('sum_heure').textContent = heure;

  // Styles visuels
  document.querySelectorAll('.slot:not(.booked)').forEach(function(el) {
    el.classList.remove('selected');
  });
  event.currentTarget.classList.add('selected');

  // Activer le bouton confirmer
  updateConfirmBtn();
}

// --- Activer/désactiver le bouton confirmer ---
function updateConfirmBtn() {
  var date  = document.getElementById('inp_date').value;
  var heure = document.getElementById('inp_heure').value;
  var btn   = document.getElementById('confirmBtn');
  if (date && heure) {
    btn.removeAttribute('disabled');
  } else {
    btn.setAttribute('disabled','disabled');
  }
}

// --- Compteur de caractères motif ---
function updateCount() {
  var v = document.getElementById('motifText').value.length;
  document.getElementById('charCount').textContent = v;
}
// Init
updateCount();
updateConfirmBtn();

// --- Confirmation avant soumission ---
document.getElementById('rdvForm').addEventListener('submit', function(e) {
  var date  = document.getElementById('inp_date').value;
  var heure = document.getElementById('inp_heure').value;
  var motif = document.getElementById('motifText').value.trim();
  if (!date || !heure) {
    e.preventDefault();
    alert('Veuillez sélectionner une date et un créneau horaire.');
    return;
  }
  if (motif.length < 10) {
    e.preventDefault();
    alert('Veuillez indiquer le motif de consultation (minimum 10 caractères).');
    return;
  }
});
</script>
</body>
</html>