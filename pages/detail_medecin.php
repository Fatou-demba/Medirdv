<?php
// ================================================================
// detail_medecin.php — Détail d'un médecin
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

$medecin_id = (int)($_GET['id'] ?? 0);
if ($medecin_id <= 0) {
    redirect('liste_medecins.php');
}

// Récupérer les infos du médecin
$stmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id AND statut = 'valide' LIMIT 1");
$stmt->execute([':id' => $medecin_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medecin) {
    redirect('liste_medecins.php');
}

// Récupérer les disponibilités
$stmt = $pdo->prepare("SELECT * FROM disponibilites WHERE id_medecin = :id AND actif = 1 ORDER BY FIELD(jour_semaine, 'lundi','mardi','mercredi','jeudi','vendredi','samedi')");
$stmt->execute([':id' => $medecin_id]);
$disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les avis (simulés car pas de table avis)
$avis = [
    ['patient' => 'Fatou Diallo', 'date' => '2025-01-02', 'note' => 5, 'commentaire' => 'Excellent médecin, très à l\'écoute. Il a pris le temps d\'expliquer mon traitement clairement. Je recommande vivement !'],
    ['patient' => 'Omar Seck', 'date' => '2024-10-15', 'note' => 5, 'commentaire' => 'Très professionnel. Diagnostic précis et rapide. Cabinet bien équipé. Merci Docteur !'],
    ['patient' => 'Aissatou Niang', 'date' => '2024-07-03', 'note' => 4, 'commentaire' => 'Bon médecin, consultation rapide et efficace. Attente un peu longue mais ça valait le coup.'],
];

// Calculer la note moyenne
$notes = array_column($avis, 'note');
$note_moyenne = !empty($notes) ? round(array_sum($notes) / count($notes), 1) : 0;
$nb_avis = count($avis);

// Jours de la semaine en français
$jours_fr = ['lundi'=>'Lun','mardi'=>'Mar','mercredi'=>'Mer','jeudi'=>'Jeu','vendredi'=>'Ven','samedi'=>'Sam'];

// Vérifier si l'utilisateur est connecté
$is_logged_in = isLoggedIn();
$user_role = $is_logged_in ? getRole() : '';

// Fonction pour formater les heures
function formatHeure($heure) {
    return substr($heure, 0, 5);
}

// Obtenir l'initiale du médecin
$initiale = strtoupper(substr($medecin['prenom'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Profil Dr. <?=htmlspecialchars($medecin['nom'])?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--teal:#006D77;--purple:#7209B7;--gray:#64748B;--lgray:#E2E8F0;--light:#F0F4FF}
        body{font-family:'DM Sans',sans-serif;background:#F8FAFF}
        
        /* ===== NAV ===== */
        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .nav-links{display:flex;gap:1.8rem;list-style:none}
        .nav-links a{color:#CBD5E1;text-decoration:none;font-size:.88rem;transition:.2s}
        .nav-links a:hover{color:#fff}
        .nav-btns{display:flex;gap:.7rem}
        .btn-o{padding:.45rem 1.1rem;border:1.5px solid #CBD5E1;color:#CBD5E1;border-radius:8px;font-size:.82rem;cursor:pointer;background:transparent;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-o:hover{background:rgba(255,255,255,.1);color:#fff}
        .btn-p{padding:.45rem 1.2rem;background:var(--green);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .btn-p:hover{background:#25B347}
        .hamburger{display:none;background:none;border:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:.3rem}

        /* ===== HERO ===== */
        .profile-hero{background:linear-gradient(135deg,var(--dark) 0%,var(--blue2) 60%,var(--teal) 100%);padding:2.5rem 5%;position:relative;overflow:hidden}
        .profile-hero::before{content:'';position:absolute;top:-80px;right:-80px;width:300px;height:300px;background:radial-gradient(circle,rgba(45,198,83,.15),transparent 70%);border-radius:50%}
        .hero-inner{display:flex;gap:2rem;align-items:center;position:relative;z-index:1;flex-wrap:wrap}
        .doc-photo{width:120px;height:120px;border-radius:20px;background:linear-gradient(135deg,var(--teal),var(--blue2));display:flex;align-items:center;justify-content:center;font-size:3.5rem;border:3px solid rgba(255,255,255,.3);flex-shrink:0;color:#fff}
        .doc-hero-info{flex:1}
        .doc-hero-info h1{font-family:'Sora',sans-serif;font-size:1.8rem;font-weight:800;color:#fff}
        .doc-spec-badge{display:inline-block;background:rgba(45,198,83,.25);border:1px solid rgba(45,198,83,.4);color:var(--green);padding:.3rem .9rem;border-radius:20px;font-size:.82rem;font-weight:700;margin-top:.4rem}
        .doc-sub{color:#CBD5E1;font-size:.88rem;margin-top:.5rem;display:flex;gap:1.5rem;flex-wrap:wrap}
        .doc-sub span{display:flex;align-items:center;gap:.3rem}
        .stars-hero{color:#FBBF24;font-size:1rem;margin-top:.5rem}
        .hero-right{display:flex;flex-direction:column;gap:.6rem;align-items:flex-end;flex-shrink:0}
        .dispo-badge{background:rgba(45,198,83,.2);border:1px solid rgba(45,198,83,.3);color:var(--green);padding:.4rem .9rem;border-radius:20px;font-size:.8rem;font-weight:700}
        .price-tag{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);color:#fff;padding:.4rem .9rem;border-radius:20px;font-size:.82rem;font-weight:700}

        /* ===== STICKY BAR ===== */
        .sticky-rdv{background:#fff;border-bottom:1px solid var(--lgray);padding:.8rem 5%;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,.07);flex-wrap:wrap;gap:.8rem}
        .sticky-info{display:flex;gap:1.5rem;flex-wrap:wrap}
        .sticky-info span{font-size:.83rem;color:var(--gray);display:flex;align-items:center;gap:.3rem}
        .sticky-info strong{color:var(--dark)}
        .rdv-btn-big{padding:.75rem 2.5rem;background:var(--blue);color:#fff;border:none;border-radius:12px;font-size:1rem;font-weight:800;cursor:pointer;box-shadow:0 4px 15px rgba(0,119,182,.35);transition:.2s;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
        .rdv-btn-big:hover{background:var(--blue2);transform:translateY(-1px)}

        /* ===== MAIN ===== */
        .main{display:flex;gap:1.5rem;padding:1.8rem 5%;max-width:1100px;margin:0 auto}

        /* ===== CONTENT ===== */
        .content{flex:1}
        .section{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);margin-bottom:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .sec-title{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid var(--blue);display:flex;align-items:center;gap:.5rem}

        /* ===== TABS ===== */
        .tabs{display:flex;gap:.3rem;background:var(--light);border-radius:10px;padding:.3rem;margin-bottom:1.2rem;overflow-x:auto}
        .tab{flex:1;padding:.58rem;text-align:center;border-radius:8px;font-size:.83rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray);min-width:80px;transition:.2s}
        .tab.active{background:var(--blue);color:#fff}

        /* ===== BIO ===== */
        .bio-text{font-size:.88rem;color:#334155;line-height:1.7}
        .competences{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.8rem}
        .comp-tag{background:#EFF6FF;color:var(--blue);border:1px solid #BFDBFE;padding:.3rem .8rem;border-radius:20px;font-size:.76rem;font-weight:600}

        /* ===== INFOS ===== */
        .infos-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
        .info-item{background:var(--light);border-radius:10px;padding:.7rem .9rem}
        .info-item .lbl{font-size:.72rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.2rem}
        .info-item .val{font-size:.87rem;font-weight:700;color:#1E293B}

        /* ===== DISPO ===== */
        .dispo-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.5rem}
        .dispo-day{border-radius:10px;padding:.6rem;text-align:center;border:1.5px solid var(--lgray)}
        .dispo-day.open{border-color:var(--green);background:#F0FDF4}
        .dispo-day.closed{background:#FAFAFA;opacity:.6}
        .dispo-day .day-name{font-size:.72rem;font-weight:700;color:var(--gray);text-transform:uppercase}
        .dispo-day.open .day-name{color:var(--green)}
        .dispo-day .day-hours{font-size:.7rem;color:#334155;margin-top:.2rem}

        /* ===== AVIS ===== */
        .avis-header{display:flex;align-items:center;gap:1.5rem;margin-bottom:1rem;padding:1rem;background:var(--light);border-radius:10px;flex-wrap:wrap}
        .avis-score{text-align:center}
        .avis-score .num{font-family:'Sora',sans-serif;font-size:2.5rem;font-weight:800;color:var(--dark)}
        .avis-score .stars{color:#FBBF24;font-size:1.1rem}
        .avis-score .count{font-size:.75rem;color:var(--gray)}
        .avis-bars{flex:1;min-width:150px}
        .avis-bar-row{display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem;font-size:.75rem;color:var(--gray)}
        .avis-bar-bg{flex:1;height:6px;background:var(--lgray);border-radius:3px;overflow:hidden}
        .avis-bar-fill{height:100%;background:#FBBF24;border-radius:3px}

        .avis-item{padding:.9rem;border-bottom:1px solid var(--lgray)}
        .avis-item:last-child{border:none}
        .avis-top{display:flex;align-items:center;gap:.7rem;margin-bottom:.4rem;flex-wrap:wrap}
        .avis-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--teal));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0}
        .avis-name{font-weight:700;font-size:.85rem;color:#1E293B}
        .avis-date{font-size:.73rem;color:var(--gray);margin-left:auto}
        .avis-stars{color:#FBBF24;font-size:.82rem;margin-bottom:.3rem}
        .avis-text{font-size:.82rem;color:#334155;line-height:1.5}

        /* ===== SIDEBAR ===== */
        .sidebar{width:280px;flex-shrink:0}
        .rdv-card{background:#fff;border-radius:14px;padding:1.4rem;border:1px solid var(--lgray);box-shadow:0 4px 20px rgba(0,0,0,.08);position:sticky;top:1rem}
        .rdv-card h4{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:1rem;text-align:center}
        .rdv-dispo{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:.7rem;text-align:center;margin-bottom:1rem}
        .rdv-dispo .icon{font-size:1.3rem}
        .rdv-dispo .txt{font-size:.82rem;font-weight:700;color:var(--green);margin-top:.2rem}
        .rdv-price{display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.5rem}
        .rdv-price .lbl{color:var(--gray)}
        .rdv-price .val{font-weight:700;color:var(--dark)}
        .divider{border:none;border-top:1px solid var(--lgray);margin:.8rem 0}
        .btn-rdv-big{width:100%;padding:.85rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:800;cursor:pointer;margin-top:1rem;font-family:'DM Sans',sans-serif;box-shadow:0 4px 15px rgba(0,119,182,.3);transition:.2s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:.4rem}
        .btn-rdv-big:hover{background:var(--blue2);transform:translateY(-1px)}
        .share-btns{display:flex;gap:.5rem;margin-top:.8rem}
        .share-btn{flex:1;padding:.5rem;background:var(--light);color:var(--gray);border:1px solid var(--lgray);border-radius:8px;font-size:.75rem;cursor:pointer;text-align:center;font-weight:600;border:none}
        .share-btn:hover{background:#E2E8F0}

        /* ===== FOOTER ===== */
        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
        footer strong{color:#fff}

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media(max-width:992px){
            .main{flex-direction:column}
            .sidebar{width:100%}
            .rdv-card{position:static}
        }
        @media(max-width:768px){
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--dark);flex-direction:column;gap:0;padding:.5rem 0;z-index:99}
            .nav-links.open{display:flex}
            .nav-links a{padding:.6rem 5%;border-bottom:1px solid rgba(255,255,255,.06)}
            .hamburger{display:block}
            .hero-inner{flex-direction:column;text-align:center}
            .hero-right{align-items:center}
            .doc-hero-info{text-align:center}
            .doc-sub{justify-content:center}
            .sticky-rdv{flex-direction:column;text-align:center}
            .sticky-info{justify-content:center}
            .infos-grid{grid-template-columns:1fr}
            .dispo-grid{grid-template-columns:repeat(3,1fr)}
            .tabs{flex-wrap:nowrap}
            .tab{font-size:.72rem;padding:.4rem .5rem;min-width:60px}
            .avis-header{flex-direction:column;text-align:center}
            .avis-top{flex-direction:column;align-items:center}
            .avis-date{margin-left:0}
        }
        @media(max-width:480px){
            .profile-hero{padding:1.5rem 4%}
            .doc-photo{width:80px;height:80px;font-size:2.5rem}
            .doc-hero-info h1{font-size:1.3rem}
            .main{padding:1rem 4%}
            .section{padding:1rem}
            .dispo-grid{grid-template-columns:repeat(3,1fr)}
            .dispo-day{padding:.4rem}
            .dispo-day .day-hours{font-size:.6rem}
            .rdv-btn-big{padding:.6rem 1.5rem;font-size:.85rem;width:100%;justify-content:center}
            .share-btns{flex-direction:column}
        }
    </style>
</head>
<body>

<!-- ===== NAV ===== -->
<nav>
    <a href="index.php" class="nav-logo">Medi<span>RDV</span></a>
    <button class="hamburger" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Accueil</a></li>
        <li><a href="liste_medecins.php">Médecins</a></li>
        <li><a href="specialites.php">Spécialités</a></li>
    </ul>
    <div class="nav-btns">
        <?php if($is_logged_in): ?>
            <a href="<?=$user_role === 'patient' ? 'dashboard_patient.php' : ($user_role === 'medecin' ? 'dashboard_medecin.php' : 'dashboard_admin.php')?>" class="btn-p">Tableau de bord</a>
            <a href="logout.php" class="btn-o">Déconnexion</a>
        <?php else: ?>
            <a href="login.php" class="btn-o">Connexion</a>
            <a href="register_patient.php" class="btn-p">S'inscrire</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== HERO ===== -->
<div class="profile-hero">
    <div class="hero-inner">
        <div class="doc-photo"><?=$initiale?></div>
        <div class="doc-hero-info">
            <h1>Dr. <?=htmlspecialchars($medecin['prenom'].' '.$medecin['nom'])?></h1>
            <span class="doc-spec-badge">🫀 <?=htmlspecialchars($medecin['specialite'])?></span>
            <div class="doc-sub">
                <span>📍 <?=htmlspecialchars($medecin['cabinet'])?>, <?=htmlspecialchars($medecin['ville'])?></span>
                <span>📅 <?=!empty($medecin['date_inscription']) ? date('Y', strtotime($medecin['date_inscription'])) : '2024'?> ans d'expérience</span>
            </div>
            <div class="stars-hero">★★★★★ <span style="color:#CBD5E1;font-size:.85rem"><?=$note_moyenne?> (<?=$nb_avis?> avis)</span></div>
        </div>
        <div class="hero-right">
            <div class="dispo-badge">🟢 Disponible aujourd'hui</div>
            <div class="price-tag">💰 Consultation médicale</div>
        </div>
    </div>
</div>

<!-- ===== STICKY BAR ===== -->
<div class="sticky-rdv">
    <div class="sticky-info">
        <span>👨‍⚕️ <strong>Dr. <?=htmlspecialchars($medecin['prenom'].' '.$medecin['nom'])?></strong></span>
        <span>🫀 <strong><?=htmlspecialchars($medecin['specialite'])?></strong></span>
        <span>⭐ <strong><?=$note_moyenne?></strong> (<?=$nb_avis?> avis)</span>
    </div>
    <a href="<?=$is_logged_in ? 'prendre_rdv.php?medecin='.$medecin_id : 'login.php?redirect=prendre_rdv.php?medecin='.$medecin_id?>" class="rdv-btn-big">📅 Prendre RDV maintenant</a>
</div>

<!-- ===== MAIN ===== -->
<div class="main">
    <div class="content">
        <!-- ===== TABS ===== -->
        <div class="tabs">
            <button class="tab active" data-tab="presentation">📋 Présentation</button>
            <button class="tab" data-tab="disponibilites">📅 Disponibilités</button>
            <button class="tab" data-tab="avis">⭐ Avis (<?=$nb_avis?>)</button>
        </div>

        <!-- ===== PRESENTATION ===== -->
        <div class="section tab-content" id="presentation">
            <div class="sec-title">👨‍⚕️ Présentation du Médecin</div>
            <p class="bio-text"><?=!empty($medecin['biographie']) ? htmlspecialchars($medecin['biographie']) : 'Médecin spécialiste en '.htmlspecialchars($medecin['specialite']).' exerçant à '.htmlspecialchars($medecin['cabinet']).'. Il est reconnu pour son approche humaine et sa rigueur médicale.'?></p>
            <div style="margin-top:1rem">
                <div style="font-size:.8rem;font-weight:700;color:var(--gray);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.5px">Compétences</div>
                <div class="competences">
                    <span class="comp-tag">Diagnostic</span>
                    <span class="comp-tag">Consultation</span>
                    <span class="comp-tag">Suivi médical</span>
                    <span class="comp-tag">Prévention</span>
                </div>
            </div>
        </div>

        <!-- ===== INFOS ===== -->
        <div class="section">
            <div class="sec-title">ℹ️ Informations pratiques</div>
            <div class="infos-grid">
                <div class="info-item"><div class="lbl">Cabinet</div><div class="val"><?=htmlspecialchars($medecin['cabinet'])?></div></div>
                <div class="info-item"><div class="lbl">Ville</div><div class="val"><?=htmlspecialchars($medecin['ville'])?>, Sénégal</div></div>
                <div class="info-item"><div class="lbl">Téléphone</div><div class="val"><?=htmlspecialchars($medecin['telephone'] ?: 'Non renseigné')?></div></div>
                <div class="info-item"><div class="lbl">Spécialité</div><div class="val"><?=htmlspecialchars($medecin['specialite'])?></div></div>
                <div class="info-item"><div class="lbl">Numéro d'ordre</div><div class="val"><?=htmlspecialchars($medecin['num_ordre'])?></div></div>
                <div class="info-item"><div class="lbl">Langues</div><div class="val">Français, Wolof</div></div>
            </div>
        </div>

        <!-- ===== DISPONIBILITES ===== -->
        <div class="section tab-content" id="disponibilites" style="display:none;">
            <div class="sec-title">📅 Disponibilités hebdomadaires</div>
            <div class="dispo-grid">
                <?php
                $jours_semaine = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
                foreach($jours_semaine as $jour):
                    $dispo = array_filter($disponibilites, function($d) use ($jour) { return $d['jour_semaine'] === $jour; });
                    $has_dispo = !empty($dispo);
                    $heures = $has_dispo ? reset($dispo) : null;
                ?>
                <div class="dispo-day <?=$has_dispo ? 'open' : 'closed'?>">
                    <div class="day-name"><?=$jours_fr[$jour]?></div>
                    <?php if($has_dispo && $heures): ?>
                        <div class="day-hours"><?=formatHeure($heures['heure_debut'])?>-<?=formatHeure($heures['heure_fin'])?></div>
                    <?php else: ?>
                        <div class="day-hours">Fermé</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== AVIS ===== -->
        <div class="section tab-content" id="avis" style="display:none;">
            <div class="sec-title">⭐ Avis des patients</div>
            <div class="avis-header">
                <div class="avis-score">
                    <div class="num"><?=$note_moyenne?></div>
                    <div class="stars">★★★★★</div>
                    <div class="count"><?=$nb_avis?> avis</div>
                </div>
                <div class="avis-bars">
                    <?php
                    $notes_distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                    foreach($notes as $n) { $notes_distribution[$n]++; }
                    foreach(range(5, 1) as $i):
                        $pct = $nb_avis > 0 ? round(($notes_distribution[$i] / $nb_avis) * 100) : 0;
                    ?>
                    <div class="avis-bar-row">
                        <span><?=$i?>★</span>
                        <div class="avis-bar-bg">
                            <div class="avis-bar-fill" style="width:<?=$pct?>%"></div>
                        </div>
                        <span><?=$notes_distribution[$i]?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach($avis as $a): ?>
            <div class="avis-item">
                <div class="avis-top">
                    <div class="avis-av"><?=strtoupper(substr($a['patient'], 0, 1))?></div>
                    <div class="avis-name"><?=htmlspecialchars($a['patient'])?></div>
                    <div class="avis-date"><?=date('d M. Y', strtotime($a['date']))?></div>
                </div>
                <div class="avis-stars"><?=str_repeat('★', $a['note']).str_repeat('☆', 5 - $a['note'])?></div>
                <div class="avis-text"><?=htmlspecialchars($a['commentaire'])?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="rdv-card">
            <h4>📅 Prendre un RDV</h4>
            <div class="rdv-dispo"><div class="icon">🟢</div><div class="txt">Disponible aujourd'hui !</div></div>
            <div class="rdv-price"><span class="lbl">Consultation</span><span class="val">Gratuit</span></div>
            <div class="rdv-price"><span class="lbl">Durée</span><span class="val">30 minutes</span></div>
            <div class="rdv-price"><span class="lbl">Cabinet</span><span class="val"><?=htmlspecialchars($medecin['cabinet'])?></span></div>
            <hr class="divider">
            <a href="<?=$is_logged_in ? 'prendre_rdv.php?medecin='.$medecin_id : 'login.php?redirect=prendre_rdv.php?medecin='.$medecin_id?>" class="btn-rdv-big">📅 Réserver ce médecin</a>
            <div class="share-btns">
                <button class="share-btn" onclick="navigator.share({title:'Dr. <?=htmlspecialchars($medecin['nom'])?>', text:'Consultez Dr. <?=htmlspecialchars($medecin['nom'])?> sur MediRDV', url:window.location.href})">🔗 Partager</button>
                <button class="share-btn" onclick="alert('Ajouté aux favoris !')">❤️ Favoris</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    <p style="margin-top:.4rem">Page designer par : <strong>Aissatou Sow (E9)</strong> — detail_medecin.php</p>
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