<?php
// ================================================================
// register_medecin.php — Inscription Médecin
// Responsable : Fatou Demba Mbaye (Etudiant 2)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Déjà connecté ?
if (isLoggedIn()) redirect('profil_medecin.php');

$erreurs = [];
$old     = [];

// Spécialités disponibles
$specialites = ['Cardiologie','Dermatologie','Gynécologie','Neurologie','Ophtalmologie',
                'Pédiatrie','Stomatologie','Chirurgie générale','Médecine interne',
                'Psychiatrie','Radiologie','Urologie','Autres'];

// Villes du Sénégal
$villes = ['Dakar','Thiès','Saint-Louis','Ziguinchor','Kaolack','Diourbel',
           'Tambacounda','Kolda','Matam','Fatick','Kaffrine','Kédougou','Louga','Sédhiou'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old = [
        'nom'           => trim($_POST['nom']           ?? ''),
        'prenom'        => trim($_POST['prenom']        ?? ''),
        'email'         => trim($_POST['email']         ?? ''),
        'telephone'     => trim($_POST['telephone']     ?? ''),
        'specialite'    => trim($_POST['specialite']    ?? ''),
        'num_ordre'     => trim($_POST['num_ordre']     ?? ''),
        'cabinet'       => trim($_POST['cabinet']       ?? ''),
        'ville'         => trim($_POST['ville']         ?? ''),
        'experience'    => trim($_POST['experience']    ?? ''),
        'biographie'    => trim($_POST['biographie']    ?? ''),
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // --- Validations ---
    if ($old['nom']        === '') $erreurs['nom']        = 'Le nom est obligatoire.';
    if ($old['prenom']     === '') $erreurs['prenom']     = 'Le prénom est obligatoire.';
    if ($old['email']      === '') {
        $erreurs['email'] = 'L\'email est obligatoire.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Adresse email invalide.';
    } else {
        $st = $pdo->prepare("SELECT id FROM medecins WHERE email = :e LIMIT 1");
        $st->execute([':e' => $old['email']]);
        if ($st->fetch()) $erreurs['email'] = 'Cet email est déjà utilisé.';
    }
    if ($old['telephone']  === '') $erreurs['telephone']  = 'Le téléphone est obligatoire.';
    if ($old['specialite'] === '' || $old['specialite'] === '--') $erreurs['specialite'] = 'La spécialité est obligatoire.';
    if ($old['num_ordre']  === '') $erreurs['num_ordre']  = 'Le numéro d\'ordre est obligatoire.';
    else {
        $st2 = $pdo->prepare("SELECT id FROM medecins WHERE num_ordre = :n LIMIT 1");
        $st2->execute([':n' => $old['num_ordre']]);
        if ($st2->fetch()) $erreurs['num_ordre'] = 'Ce numéro d\'ordre est déjà enregistré.';
    }
    if ($old['cabinet']    === '') $erreurs['cabinet']    = 'L\'adresse du cabinet est obligatoire.';
    if ($old['ville']      === '') $erreurs['ville']      = 'La ville est obligatoire.';
    if (strlen($password)  < 8)   $erreurs['password']   = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($password !== $password2)  $erreurs['password2']  = 'Les mots de passe ne correspondent pas.';

    // --- Upload photo de profil (optionnel) ---
    $photo_filename = null;
    if (!empty($_FILES['photo']['name'])) {
        $allowed_img = ['image/jpeg','image/jpg','image/png','image/webp'];
        $ftype = mime_content_type($_FILES['photo']['tmp_name']);
        if (!in_array($ftype, $allowed_img)) {
            $erreurs['photo'] = 'Format non accepté (JPG, PNG, WEBP uniquement).';
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $erreurs['photo'] = 'La photo ne doit pas dépasser 2 MB.';
        } else {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo_filename = 'dr_' . uniqid() . '.' . strtolower($ext);
        }
    }

    // --- Upload justificatif ordre médical (obligatoire) ---
    $justif_filename = null;
    if (empty($_FILES['justificatif']['name'])) {
        $erreurs['justificatif'] = 'Le justificatif de l\'ordre médical est obligatoire.';
    } else {
        $ftype_j = mime_content_type($_FILES['justificatif']['tmp_name']);
        if ($ftype_j !== 'application/pdf') {
            $erreurs['justificatif'] = 'Le justificatif doit être un fichier PDF.';
        } elseif ($_FILES['justificatif']['size'] > 5 * 1024 * 1024) {
            $erreurs['justificatif'] = 'Le fichier ne doit pas dépasser 5 MB.';
        } else {
            $justif_filename = 'justif_' . uniqid() . '.pdf';
        }
    }

    if (empty($erreurs)) {
        // Déplacer les fichiers uploadés
        $upload_dir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if ($photo_filename) {
            move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo_filename);
        }
        if ($justif_filename) {
            move_uploaded_file($_FILES['justificatif']['tmp_name'], $upload_dir . $justif_filename);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO medecins
              (nom, prenom, email, mot_de_passe, specialite, num_ordre, cabinet, ville, telephone, photo, biographie, statut)
            VALUES
              (:nom, :prenom, :email, :mdp, :spec, :ordre, :cabinet, :ville, :tel, :photo, :bio, 'en_attente')
        ");
        $stmt->execute([
            ':nom'     => $old['nom'],
            ':prenom'  => $old['prenom'],
            ':email'   => $old['email'],
            ':mdp'     => $hash,
            ':spec'    => $old['specialite'],
            ':ordre'   => $old['num_ordre'],
            ':cabinet' => $old['cabinet'],
            ':ville'   => $old['ville'],
            ':tel'     => $old['telephone'],
            ':photo'   => $photo_filename,
            ':bio'     => $old['biographie'] ?: null,
        ]);

        // Notification admin (si table notifications existe)
        try {
            $admin = $pdo->query("SELECT id FROM admins LIMIT 1")->fetch();
            if ($admin) {
                $pdo->prepare("INSERT INTO notifications (id_destinataire, type_user, type_notif, message)
                               VALUES (:id, 'admin', 'nouveau_rdv', :msg)")
                    ->execute([
                        ':id'  => $admin['id'],
                        ':msg' => "Nouvelle demande d'inscription médecin : Dr. {$old['prenom']} {$old['nom']} ({$old['specialite']})"
                    ]);
            }
        } catch(Exception $e) { /* non bloquant */ }

        // Rediriger vers login avec message succès
        redirect('login.php?role=medecin&inscrit=1');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Inscription Médecin</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--orange:#F77F00;--light:#F0F4FF;--gray:#64748B;--lgray:#E2E8F0;--red:#D62828}
body{font-family:'DM Sans',sans-serif;background:var(--light);min-height:100vh}

nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
.nav-logo span{color:var(--green)}
.back-link{color:#CBD5E1;font-size:.85rem;text-decoration:none;transition:.2s}
.back-link:hover{color:#fff}

.page-body{display:flex;justify-content:center;padding:2.5rem 1rem;background:linear-gradient(135deg,#EEF2FF,#FFF7ED 60%,#F0FDF4)}

.box{background:#fff;border-radius:20px;padding:2.5rem;width:100%;max-width:680px;box-shadow:0 16px 50px rgba(0,0,0,.1)}

.reg-header{text-align:center;margin-bottom:2rem}
.reg-icon{width:64px;height:64px;background:linear-gradient(135deg,var(--orange),#D62828);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1rem}
.reg-header h2{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:var(--dark)}
.reg-header p{color:var(--gray);font-size:.9rem;margin-top:.4rem}

/* Info box */
.info-box{background:#FFF7ED;border:1px solid #f1cda4;border-radius:10px;padding:.9rem 1rem;margin-bottom:1.5rem;font-size:.83rem;color:#92400E;display:flex;gap:.6rem;align-items:flex-start}

/* Alert erreur globale */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

/* Section title */
.section-title{font-family:'Sora',sans-serif;font-size:.95rem;font-weight:700;color:var(--dark);margin:1.5rem 0 1rem;padding-bottom:.5rem;border-bottom:2px solid var(--lgray);display:flex;align-items:center;gap:.5rem}

/* Form */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.83rem;font-weight:600;color:#334155;margin-bottom:.45rem}
.req{color:var(--red)}
.form-group input,.form-group select,.form-group textarea{
  width:100%;padding:.72rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
  font-size:.88rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;background:#fff;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
  border-color:var(--orange);box-shadow:0 0 0 3px rgba(240, 128, 8, 0.08);
}
.form-group input.field-error,.form-group select.field-error,.form-group textarea.field-error{
  border-color:var(--red);background:#FFF5F5;
}
.form-group textarea{resize:vertical;min-height:80px}
.field-msg{font-size:.75rem;margin-top:.3rem}
.field-msg.error{color:var(--red)}

/* Upload */
.upload-box{border:2px dashed var(--lgray);border-radius:10px;padding:1.4rem;text-align:center;cursor:pointer;transition:.2s;background:#FAFAFA;position:relative}
.upload-box:hover{border-color:var(--orange);background:#FFF7ED}
.upload-box.has-file{border-color:var(--green);background:#F0FDF4}
.upload-box .icon{font-size:2rem;margin-bottom:.5rem}
.upload-box p{font-size:.85rem;color:var(--gray)}
.upload-box span{color:var(--orange);font-weight:600}
.upload-box input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-box.has-file p{color:var(--green);font-weight:600}

/* Mot de passe */
.input-wrap{position:relative}
.toggle-pw{position:absolute;right:.9rem;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1rem;color:var(--gray);background:none;border:none;padding:0}
.pw-bars{display:flex;gap:.3rem;margin-top:.4rem;margin-bottom:.25rem}
.pw-bar{height:4px;flex:1;border-radius:2px;background:var(--lgray);transition:.4s}
.pw-label{font-size:.73rem;color:var(--gray)}

.submit-btn{width:100%;padding:.9rem;background:var(--orange);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 15px rgba(247,127,0,.3);transition:.2s;margin-top:.5rem}
.submit-btn:hover{background:#D97706;transform:translateY(-1px)}

.login-link{text-align:center;margin-top:1rem;font-size:.85rem;color:var(--gray)}
.login-link a{color:var(--blue);font-weight:600;text-decoration:none}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
footer strong{color:#fff}

/* RESPONSIVE */
@media(max-width:640px){
  .box{padding:1.8rem 1.2rem;border-radius:14px}
  .form-row{grid-template-columns:1fr}
  .reg-header h2{font-size:1.35rem}
}
@media(max-width:400px){
  .page-body{padding:1rem .3rem}
}
</style>
</head>
<body>

<nav>
  <a href="../index.php" class="nav-logo">Medi<span>RDV</span></a>
  <a href="login.php" class="back-link">← Retour à la connexion</a>
</nav>

<div class="page-body">
  <div class="box">

    <div class="reg-header">
      <div class="reg-icon">👨‍⚕️</div>
      <h2>Inscription Médecin</h2>
      <p>Rejoignez notre réseau de professionnels de santé</p>
    </div>

    <!-- INFO VALIDATION -->
    <div class="info-box">
      ⚠️ Votre compte sera <strong>activé après validation par un administrateur</strong>.
      Veuillez joindre votre justificatif d'ordre médical. Le délai de traitement est de 24 à 48h.
    </div>

    <!-- ERREUR GLOBALE -->
    <?php if (!empty($erreurs)): ?>
      <div class="alert alert-error">⚠️ Veuillez corriger les erreurs ci-dessous avant de continuer.</div>
    <?php endif; ?>

    <form method="POST" action="register_medecin.php" enctype="multipart/form-data" novalidate>

      <!-- ===================== INFOS PERSONNELLES ===================== -->
      <div class="section-title">👤 Informations personnelles</div>

      <div class="form-row">
        <div class="form-group">
          <label>Nom <span class="req">*</span></label>
          <input type="text" name="nom" placeholder="Ba"
                 value="<?=htmlspecialchars($old['nom']??'')?>"
                 class="<?=isset($erreurs['nom'])?'field-error':''?>">
          <?php if(isset($erreurs['nom'])): ?><span class="field-msg error">⚠ <?=$erreurs['nom']?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Prénom <span class="req">*</span></label>
          <input type="text" name="prenom" placeholder="Mamadou"
                 value="<?=htmlspecialchars($old['prenom']??'')?>"
                 class="<?=isset($erreurs['prenom'])?'field-error':''?>">
          <?php if(isset($erreurs['prenom'])): ?><span class="field-msg error">⚠ <?=$erreurs['prenom']?></span><?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Email <span class="req">*</span></label>
          <input type="email" name="email" placeholder="dr.ba@exemple.sn"
                 value="<?=htmlspecialchars($old['email']??'')?>"
                 class="<?=isset($erreurs['email'])?'field-error':''?>"
                 autocomplete="email">
          <?php if(isset($erreurs['email'])): ?><span class="field-msg error">⚠ <?=$erreurs['email']?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Téléphone <span class="req">*</span></label>
          <input type="tel" name="telephone" placeholder="77 123 45 67"
                 value="<?=htmlspecialchars($old['telephone']??'')?>"
                 class="<?=isset($erreurs['telephone'])?'field-error':''?>">
          <?php if(isset($erreurs['telephone'])): ?><span class="field-msg error">⚠ <?=$erreurs['telephone']?></span><?php endif; ?>
        </div>
      </div>

      <!-- ===================== INFOS PROFESSIONNELLES ===================== -->
      <div class="section-title">🏥 Informations professionnelles</div>

      <div class="form-row">
        <div class="form-group">
          <label>Spécialité <span class="req">*</span></label>
          <select name="specialite" class="<?=isset($erreurs['specialite'])?'field-error':''?>">
            <option value="">-- Choisir --</option>
            <?php foreach ($specialites as $spec): ?>
              <option value="<?=htmlspecialchars($spec)?>"
                <?=($old['specialite']??'')===$spec?'selected':''?>>
                <?=htmlspecialchars($spec)?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($erreurs['specialite'])): ?><span class="field-msg error">⚠ <?=$erreurs['specialite']?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Numéro d'ordre <span class="req">*</span></label>
          <input type="text" name="num_ordre" placeholder="ORD-2024-XXX"
                 value="<?=htmlspecialchars($old['num_ordre']??'')?>"
                 class="<?=isset($erreurs['num_ordre'])?'field-error':''?>">
          <?php if(isset($erreurs['num_ordre'])): ?><span class="field-msg error">⚠ <?=$erreurs['num_ordre']?></span><?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label>Adresse du cabinet <span class="req">*</span></label>
        <input type="text" name="cabinet" placeholder="Ex : Clinique du Lac, Rue 11, Dakar"
               value="<?=htmlspecialchars($old['cabinet']??'')?>"
               class="<?=isset($erreurs['cabinet'])?'field-error':''?>">
        <?php if(isset($erreurs['cabinet'])): ?><span class="field-msg error">⚠ <?=$erreurs['cabinet']?></span><?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Ville <span class="req">*</span></label>
          <select name="ville" class="<?=isset($erreurs['ville'])?'field-error':''?>">
            <option value="">-- Choisir --</option>
            <?php foreach ($villes as $v): ?>
              <option value="<?=htmlspecialchars($v)?>"
                <?=($old['ville']??'')===$v?'selected':''?>>
                <?=htmlspecialchars($v)?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($erreurs['ville'])): ?><span class="field-msg error">⚠ <?=$erreurs['ville']?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Années d'expérience <span style="color:var(--gray);font-weight:400">(optionnel)</span></label>
          <input type="number" name="experience" placeholder="Ex : 10" min="0" max="60"
                 value="<?=htmlspecialchars($old['experience']??'')?>">
        </div>
      </div>

      <div class="form-group">
        <label>Biographie / Présentation <span style="color:var(--gray);font-weight:400">(optionnel)</span></label>
        <textarea name="biographie" placeholder="Décrivez votre parcours, vos compétences et votre approche médicale..."><?=htmlspecialchars($old['biographie']??'')?></textarea>
      </div>

      <!-- ===================== JUSTIFICATIFS ===================== -->
      <div class="section-title">📎 Justificatifs</div>

      <div class="form-group">
        <label>Photo de profil <span style="color:var(--gray);font-weight:400">(optionnel)</span></label>
        <div class="upload-box" id="photoBox">
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                 onchange="previewUpload(this,'photoBox','photoInfo')">
          <div class="icon">📷</div>
          <p id="photoInfo">Glissez votre photo ici ou <span>cliquez pour parcourir</span></p>
          <p style="font-size:.75rem;margin-top:.3rem;color:#94A3B8">JPG, PNG — Max 2 MB</p>
        </div>
        <?php if(isset($erreurs['photo'])): ?><span class="field-msg error">⚠ <?=$erreurs['photo']?></span><?php endif; ?>
      </div>

      <div class="form-group">
        <label>Justificatif ordre médical <span class="req">*</span></label>
        <div class="upload-box" id="justifBox" style="border-color:#FED7AA;background:#FFF7ED">
          <input type="file" name="justificatif" accept=".pdf,application/pdf"
                 onchange="previewUpload(this,'justifBox','justifInfo')">
          <div class="icon">📄</div>
          <p id="justifInfo">Glissez votre certificat ici ou <span>cliquez pour parcourir</span></p>
          <p style="font-size:.75rem;margin-top:.3rem;color:#94A3B8">PDF — Max 5 MB</p>
        </div>
        <?php if(isset($erreurs['justificatif'])): ?><span class="field-msg error">⚠ <?=$erreurs['justificatif']?></span><?php endif; ?>
      </div>

      <!-- ===================== MOT DE PASSE ===================== -->
      <div class="section-title">🔒 Mot de passe</div>

      <div class="form-row">
        <div class="form-group">
          <label>Mot de passe <span class="req">*</span></label>
          <div class="input-wrap">
            <input type="password" name="password" id="pw1"
                   placeholder="Minimum 8 caractères"
                   class="<?=isset($erreurs['password'])?'field-error':''?>"
                   oninput="checkStrength(this.value)"
                   autocomplete="new-password">
            <button type="button" class="toggle-pw" onclick="togglePw('pw1')">👁</button>
          </div>
          <?php if(isset($erreurs['password'])): ?><span class="field-msg error">⚠ <?=$erreurs['password']?></span><?php endif; ?>
          <div class="pw-bars">
            <div class="pw-bar" id="bar1"></div>
            <div class="pw-bar" id="bar2"></div>
            <div class="pw-bar" id="bar3"></div>
            <div class="pw-bar" id="bar4"></div>
          </div>
          <div class="pw-label" id="pwLabel">Saisissez un mot de passe</div>
        </div>
        <div class="form-group">
          <label>Confirmer <span class="req">*</span></label>
          <div class="input-wrap">
            <input type="password" name="password2" id="pw2"
                   placeholder="Répétez le mot de passe"
                   class="<?=isset($erreurs['password2'])?'field-error':''?>"
                   oninput="checkMatch()"
                   autocomplete="new-password">
            <button type="button" class="toggle-pw" onclick="togglePw('pw2')">👁</button>
          </div>
          <?php if(isset($erreurs['password2'])): ?>
            <span class="field-msg error">⚠ <?=$erreurs['password2']?></span>
          <?php else: ?>
            <span class="field-msg" id="matchMsg" style="color:var(--green);display:none">✅ Mots de passe identiques</span>
          <?php endif; ?>
        </div>
      </div>

      <button type="submit" class="submit-btn">Soumettre ma demande d'inscription →</button>
    </form>

    <div class="login-link">Déjà inscrit ? <a href="login.php?role=medecin">Se connecter</a></div>
  </div>
</div>

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Fatou Demba Mbaye (E2)</strong> — register_medecin.php</p>
</footer>

<script>
// Force du mot de passe
function checkStrength(v) {
  var score = 0;
  if (v.length >= 8)           score++;
  if (/[A-Z]/.test(v))         score++;
  if (/[0-9]/.test(v))         score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  var colors = ['','#D62828','#FBBF24','#F77F00','#2DC653'];
  var labels = ['','🔴 Très faible','🟡 Faible','🟠 Moyen','🟢 Fort'];
  for (var i = 1; i <= 4; i++) {
    document.getElementById('bar'+i).style.background = i<=score ? colors[score] : 'var(--lgray)';
  }
  document.getElementById('pwLabel').textContent = v.length ? labels[score] : 'Saisissez un mot de passe';
  checkMatch();
}

function checkMatch() {
  var v1  = document.getElementById('pw1').value;
  var v2  = document.getElementById('pw2').value;
  var msg = document.getElementById('matchMsg');
  if (msg && v2.length > 0) msg.style.display = (v1===v2) ? 'block' : 'none';
}

function togglePw(id) {
  var inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Preview nom fichier uploadé
function previewUpload(input, boxId, infoId) {
  var box  = document.getElementById(boxId);
  var info = document.getElementById(infoId);
  if (input.files && input.files[0]) {
    var name = input.files[0].name;
    var size = (input.files[0].size / 1024).toFixed(0) + ' KB';
    info.innerHTML = '✅ ' + name + ' (' + size + ')';
    box.classList.add('has-file');
    box.style.borderColor = 'var(--green)';
    box.style.background  = '#F0FDF4';
  }
  // Tester
}
</script>
</body>
</html>