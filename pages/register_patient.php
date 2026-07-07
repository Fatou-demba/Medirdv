<?php
// ================================================================
// register_patient.php — Inscription Patient
// Responsable : Fatou Demba Mbaye (Etudiant 2)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Déjà connecté ?
if (isLoggedIn()) redirect('profil_patient.php');

$erreurs  = [];
$succes   = false;
$old      = []; // re-remplir les champs en cas d'erreur

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'nom'            => trim($_POST['nom']            ?? ''),
        'prenom'         => trim($_POST['prenom']         ?? ''),
        'email'          => trim($_POST['email']          ?? ''),
        'telephone'      => trim($_POST['telephone']      ?? ''),
        'date_naissance' => trim($_POST['date_naissance'] ?? ''),
        'groupe_sanguin' => $_POST['groupe_sanguin']      ?? '',
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $terms     = isset($_POST['terms']);

    // --- Validations ---
    if ($old['nom']       === '') $erreurs['nom']       = 'Le nom est obligatoire.';
    if ($old['prenom']    === '') $erreurs['prenom']    = 'Le prénom est obligatoire.';
    if ($old['email']     === '') {
        $erreurs['email'] = 'L\'email est obligatoire.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Email invalide.';
    } else {
        $st = $pdo->prepare("SELECT id FROM patients WHERE email = :e LIMIT 1");
        $st->execute([':e' => $old['email']]);
        if ($st->fetch()) $erreurs['email'] = 'Cet email est déjà utilisé.';
    }
    if ($old['telephone'] === '') $erreurs['telephone'] = 'Le téléphone est obligatoire.';
    if ($old['date_naissance'] === '') $erreurs['date_naissance'] = 'La date de naissance est obligatoire.';
    if (strlen($password) < 8)   $erreurs['password']  = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($password !== $password2) $erreurs['password2'] = 'Les mots de passe ne correspondent pas.';
    if (!$terms)                 $erreurs['terms']      = 'Vous devez accepter les conditions d\'utilisation.';

    if (empty($erreurs)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO patients (nom, prenom, email, telephone, date_naissance, groupe_sanguin, mot_de_passe, statut)
            VALUES (:nom, :prenom, :email, :tel, :ddn, :gs, :mdp, 'actif')
        ");
        $stmt->execute([
            ':nom'    => $old['nom'],
            ':prenom' => $old['prenom'],
            ':email'  => $old['email'],
            ':tel'    => $old['telephone'],
            ':ddn'    => $old['date_naissance'],
            ':gs'     => $old['groupe_sanguin'] ?: null,
            ':mdp'    => $hash,
        ]);
        $new_id = $pdo->lastInsertId();

        // Créer un dossier médical vide automatiquement
        $pdo->prepare("INSERT INTO dossiers (id_patient) VALUES (:id)")->execute([':id' => $new_id]);

        // Connexion automatique après inscription
        $_SESSION['user_id'] = $new_id;
        $_SESSION['role']    = 'patient';
        $_SESSION['email']   = $old['email'];
        $_SESSION['prenom']  = $old['prenom'];
        $_SESSION['nom']     = $old['nom'];

        redirect('profil_patient.php?nouveau=1');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Inscription Patient</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0;--red:#D62828}
body{font-family:'DM Sans',sans-serif;background:var(--light);min-height:100vh}

nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
.nav-logo span{color:var(--green)}
.back-link{color:#CBD5E1;font-size:.85rem;text-decoration:none;transition:.2s}
.back-link:hover{color:#fff}

.page-body{display:flex;justify-content:center;padding:2.5rem 1rem;background:linear-gradient(135deg,#EEF2FF,#F0F9FF 60%,#F0FDF4)}

.register-box{background:#fff;border-radius:20px;padding:2.5rem;width:100%;max-width:600px;box-shadow:0 16px 50px rgba(0,0,0,.1)}

.reg-header{text-align:center;margin-bottom:2rem}
.reg-icon{width:64px;height:64px;background:linear-gradient(135deg,var(--blue),var(--blue2));border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1rem}
.reg-header h2{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:var(--dark)}
.reg-header p{color:var(--gray);font-size:.9rem;margin-top:.4rem}

/* STEPS */
.steps{display:flex;align-items:center;margin-bottom:2rem}
.step{display:flex;align-items:center;gap:.5rem;flex:1;min-width:0}
.step-circle{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;transition:.3s}
.step.done .step-circle{background:var(--green);color:#fff}
.step.active .step-circle{background:var(--blue);color:#fff;box-shadow:0 0 0 4px rgba(5, 132, 201, 0.15)}
.step.inactive .step-circle{background:var(--lgray);color:var(--gray)}
.step-label{font-size:.76rem;font-weight:600;color:var(--gray);white-space:nowrap}
.step.active .step-label{color:var(--blue)}
.step.done .step-label{color:var(--green)}
.step-line{flex:1;height:2px;background:var(--lgray);margin:0 .4rem}
.step-line.done{background:var(--green)}

/* FORMULAIRE */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:0}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.83rem;font-weight:600;color:#334155;margin-bottom:.45rem}
.req{color:var(--red)}
.form-group input,.form-group select{
  width:100%;padding:.72rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
  font-size:.88rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;background:#fff;
}
.form-group input:focus,.form-group select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
.form-group input.valid{border-color:var(--green)}
.form-group input.field-error,.form-group select.field-error{border-color:var(--red);background:#FFF5F5}
.field-msg{font-size:.75rem;margin-top:.3rem}
.field-msg.error{color:var(--red)}
.field-msg.ok{color:var(--green)}

/* Alert globale */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}

/* Force mot de passe */
.input-wrap{position:relative}
.toggle-pw{position:absolute;right:.9rem;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1rem;color:var(--gray);background:none;border:none;padding:0}
.pw-strength{margin-top:.4rem}
.pw-bars{display:flex;gap:.3rem;margin-bottom:.3rem}
.pw-bar{height:4px;flex:1;border-radius:2px;background:var(--lgray);transition:.4s}
.pw-label{font-size:.73rem;color:var(--gray)}

/* Termes */
.terms{display:flex;align-items:flex-start;gap:.6rem;margin-bottom:1.5rem;font-size:.83rem;color:var(--gray)}
.terms input{margin-top:3px;accent-color:var(--blue);width:15px;height:15px;flex-shrink:0;cursor:pointer}
.terms a{color:var(--blue);font-weight:600}
.terms-error{font-size:.78rem;color:var(--red);margin-top:.3rem;display:block}

.submit-btn{width:100%;padding:.9rem;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 15px rgba(0,119,182,.3);transition:.2s}
.submit-btn:hover{background:var(--blue2);transform:translateY(-1px)}

.login-link{text-align:center;margin-top:1.2rem;font-size:.85rem;color:var(--gray)}
.login-link a{color:var(--blue);font-weight:600;text-decoration:none}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;margin-top:2rem}
footer strong{color:#fff}

/* RESPONSIVE */
@media(max-width:600px){
  .register-box{padding:1.8rem 1.2rem;border-radius:14px}
  .form-row{grid-template-columns:1fr}
  .reg-header h2{font-size:1.35rem}
  .step-label{display:none}
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
  <div class="register-box">

    <div class="reg-header">
      <div class="reg-icon">👤</div>
      <h2>Créer mon compte patient</h2>
      <p>Rejoignez MediRDV et prenez vos rendez-vous en ligne</p>
    </div>

    <!-- STEPS VISUELS -->
    <div class="steps">
      <div class="step <?=empty($erreurs)?'active':'done'?>">
        <div class="step-circle"><?=empty($erreurs)?'1':'✓'?></div>
        <div class="step-label">Identité</div>
      </div>
      <div class="step-line <?=empty($erreurs)?'':'done'?>"></div>
      <div class="step <?=empty($erreurs)?'inactive':'active'?>">
        <div class="step-circle">2</div>
        <div class="step-label">Contact</div>
      </div>
      <div class="step-line"></div>
      <div class="step inactive">
        <div class="step-circle">3</div>
        <div class="step-label">Sécurité</div>
      </div>
    </div>

    <!-- ERREUR GLOBALE -->
    <?php if (!empty($erreurs)): ?>
      <div class="alert alert-error">⚠️ Veuillez corriger les erreurs ci-dessous avant de continuer.</div>
    <?php endif; ?>

    <form method="POST" action="register_patient.php" novalidate>

      <!-- NOM / PRÉNOM -->
      <div class="form-row">
        <div class="form-group">
          <label>Nom <span class="req">*</span></label>
          <input type="text" name="nom" placeholder="Diallo"
                 value="<?=htmlspecialchars($old['nom']??'')?>"
                 class="<?=isset($erreurs['nom'])?'field-error':($old['nom']??''?'valid':'')?>">
          <?php if(isset($erreurs['nom'])): ?><span class="field-msg error">⚠ <?=$erreurs['nom']?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Prénom <span class="req">*</span></label>
          <input type="text" name="prenom" placeholder="Fatou"
                 value="<?=htmlspecialchars($old['prenom']??'')?>"
                 class="<?=isset($erreurs['prenom'])?'field-error':($old['prenom']??''?'valid':'')?>">
          <?php if(isset($erreurs['prenom'])): ?><span class="field-msg error">⚠ <?=$erreurs['prenom']?></span><?php endif; ?>
        </div>
      </div>

      <!-- EMAIL -->
      <div class="form-group">
        <label>Adresse email <span class="req">*</span></label>
        <input type="email" name="email" placeholder="fatoudemba.mbaye@exemple.sn"
               value="<?=htmlspecialchars($old['email']??'')?>"
               class="<?=isset($erreurs['email'])?'field-error':''?>"
               autocomplete="email">
        <?php if(isset($erreurs['email'])): ?><span class="field-msg error">⚠ <?=$erreurs['email']?></span><?php endif; ?>
      </div>

      <!-- TÉLÉPHONE / DATE NAISSANCE -->
      <div class="form-row">
        <div class="form-group">
          <label>Téléphone <span class="req">*</span></label>
          <input type="tel" name="telephone" placeholder="77 123 45 67"
                 value="<?=htmlspecialchars($old['telephone']??'')?>"
                 class="<?=isset($erreurs['telephone'])?'field-error':''?>">
          <?php if(isset($erreurs['telephone'])): ?><span class="field-msg error">⚠ <?=$erreurs['telephone']?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Date de naissance <span class="req">*</span></label>
          <input type="date" name="date_naissance"
                 value="<?=htmlspecialchars($old['date_naissance']??'')?>"
                 max="<?=date('Y-m-d', strtotime('-5 years'))?>"
                 class="<?=isset($erreurs['date_naissance'])?'field-error':''?>">
          <?php if(isset($erreurs['date_naissance'])): ?><span class="field-msg error">⚠ <?=$erreurs['date_naissance']?></span><?php endif; ?>
        </div>
      </div>

      <!-- GROUPE SANGUIN -->
      <div class="form-group">
        <label>Groupe sanguin <span style="color:var(--gray);font-weight:400">(optionnel)</span></label>
        <select name="groupe_sanguin">
          <option value="">-- Optionnel --</option>
          <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $gs): ?>
            <option value="<?=$gs?>" <?=($old['groupe_sanguin']??'')===$gs?'selected':''?>><?=$gs?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- MOT DE PASSE -->
      <div class="form-group">
        <label>Mot de passe <span class="req">*</span></label>
        <div class="input-wrap">
          <input type="password" name="password" id="pw1" placeholder="Minimum 8 caractères"
                 class="<?=isset($erreurs['password'])?'field-error':''?>"
                 oninput="checkStrength(this.value)" autocomplete="new-password">
          <button type="button" class="toggle-pw" onclick="togglePw('pw1')">👁</button>
        </div>
        <?php if(isset($erreurs['password'])): ?><span class="field-msg error">⚠ <?=$erreurs['password']?></span><?php endif; ?>
        <div class="pw-strength">
          <div class="pw-bars">
            <div class="pw-bar" id="bar1"></div>
            <div class="pw-bar" id="bar2"></div>
            <div class="pw-bar" id="bar3"></div>
            <div class="pw-bar" id="bar4"></div>
          </div>
          <div class="pw-label" id="pwLabel">Saisissez un mot de passe</div>
        </div>
      </div>

      <!-- CONFIRMER MOT DE PASSE -->
      <div class="form-group">
        <label>Confirmer le mot de passe <span class="req">*</span></label>
        <div class="input-wrap">
          <input type="password" name="password2" id="pw2" placeholder="Répétez votre mot de passe"
                 class="<?=isset($erreurs['password2'])?'field-error':''?>"
                 oninput="checkMatch()" autocomplete="new-password">
          <button type="button" class="toggle-pw" onclick="togglePw('pw2')">👁</button>
        </div>
        <?php if(isset($erreurs['password2'])): ?>
          <span class="field-msg error">⚠ <?=$erreurs['password2']?></span>
        <?php else: ?>
          <span class="field-msg ok" id="matchMsg" style="display:none">✅ Les mots de passe correspondent</span>
        <?php endif; ?>
      </div>

      <!-- CONDITIONS -->
      <div class="terms">
        <input type="checkbox" id="terms" name="terms" <?=isset($_POST['terms'])?'checked':''?>>
        <label for="terms">J'accepte les <a href="#">conditions d'utilisation</a> et la <a href="#">politique de confidentialité</a> de MediRDV</label>
      </div>
      <?php if(isset($erreurs['terms'])): ?><span class="terms-error">⚠ <?=$erreurs['terms']?></span><?php endif; ?>

      <button type="submit" class="submit-btn">Créer mon compte →</button>
    </form>

    <div class="login-link">Déjà inscrit ? <a href="login.php">Se connecter</a></div>
  </div>
</div>

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">Page développée par : <strong>Fatou Demba Mbaye (E2)</strong> — register_patient.php</p>
</footer>

<script>
// Force du mot de passe
function checkStrength(v) {
  var score = 0;
  if (v.length >= 8)  score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  var colors = ['', '#d32525', '#f1bb30', '#f1820a', '#36d85f'];
  var labels = ['', '🔴 Très faible', '🟡 Faible', '🟠 Moyen', '🟢 Fort'];
  for (var i = 1; i <= 4; i++) {
    var bar = document.getElementById('bar' + i);
    bar.style.background = i <= score ? colors[score] : 'var(--lgray)';
  }
  document.getElementById('pwLabel').textContent = v.length ? labels[score] : 'Saisissez un mot de passe';
  checkMatch();
}
function checkMatch() {
  var v1 = document.getElementById('pw1').value;
  var v2 = document.getElementById('pw2').value;
  var msg = document.getElementById('matchMsg');
  if (msg && v2.length > 0) {
    msg.style.display = (v1 === v2) ? 'block' : 'none';
  }
}
function togglePw(id) {
  var inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
// Teste
</script>
</body>
</html>