<?php
// ================================================================
// reinitialiser_mdp.php — Réinitialisation du mot de passe via token
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$erreur = '';
$succes = false;
$token_valide = false;
$reset_row = null;

// ================================================================
// VALIDER LE TOKEN
// ================================================================
if ($token === '') {
    $erreur = 'Lien de réinitialisation invalide ou incomplet.';
} else {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :t LIMIT 1");
    $stmt->execute([':t' => $token]);
    $reset_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset_row) {
        $erreur = 'Ce lien de réinitialisation est invalide.';
    } elseif ((int)$reset_row['utilise'] === 1) {
        $erreur = 'Ce lien a déjà été utilisé. Veuillez refaire une demande.';
    } elseif (new DateTime() > new DateTime($reset_row['expire_at'])) {
        $erreur = 'Ce lien a expiré (validité 1 heure). Veuillez refaire une demande.';
    } else {
        $token_valide = true;
    }
}

// ================================================================
// TRAITEMENT DU FORMULAIRE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valide) {
    $new_pw  = $_POST['new_pw']  ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($new_pw) < 8) {
        $erreur = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($new_pw !== $confirm) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($new_pw, PASSWORD_BCRYPT);
        $type_user = $reset_row['type_user'];
        $email     = $reset_row['email'];

        // Mettre à jour le bon compte selon le type
        $table_map = ['patient'=>'patients', 'medecin'=>'medecins', 'admin'=>'admins'];
        $table = $table_map[$type_user] ?? null;

        if ($table) {
            $pdo->prepare("UPDATE $table SET mot_de_passe = :h WHERE email = :e")
                ->execute([':h' => $hash, ':e' => $email]);

            // Marquer le token comme utilisé
            $pdo->prepare("UPDATE password_resets SET utilise = 1 WHERE id = :id")
                ->execute([':id' => $reset_row['id']]);

            $succes = true;
        } else {
            $erreur = 'Erreur interne : type de compte invalide.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Réinitialiser le mot de passe</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0;--red:#D62828}
body{font-family:'DM Sans',sans-serif;background:var(--light);min-height:100vh;display:flex;flex-direction:column}

nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
.nav-logo span{color:var(--green)}
.back-link{color:#CBD5E1;font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:.4rem;transition:.2s}
.back-link:hover{color:#fff}

.page-body{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:linear-gradient(135deg,#EEF2FF 0%,#F0F9FF 50%,#F0FDF4 100%)}

.card{background:#fff;border-radius:20px;padding:2.5rem;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,.12);text-align:center}

.icon-box{width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.2rem}
.icon-box.blue{background:linear-gradient(135deg,var(--blue),var(--blue2))}
.icon-box.green{background:linear-gradient(135deg,var(--green),#25B347)}
.icon-box.red{background:linear-gradient(135deg,var(--red),#B91C1C)}

.card h2{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800;color:var(--dark);margin-bottom:.5rem}
.card .subtitle{color:var(--gray);font-size:.88rem;margin-bottom:1.8rem;line-height:1.5}

.form-group{margin-bottom:1.2rem;text-align:left}
.form-group label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.5rem}
.input-wrap{position:relative}
.form-group input{
  width:100%;padding:.75rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
  font-size:.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;
}
.form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
.form-group input.field-error{border-color:var(--red);background:#FFF5F5}
.toggle-pw{position:absolute;right:.9rem;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1rem;color:var(--gray);background:none;border:none;padding:0}

.pw-bars{display:flex;gap:.3rem;margin-top:.4rem;margin-bottom:.2rem}
.pw-bar{height:4px;flex:1;border-radius:2px;background:var(--lgray);transition:.4s}
.pw-label{font-size:.73rem;color:var(--gray);text-align:left}

.alert{padding:.85rem 1rem;border-radius:10px;font-size:.84rem;margin-bottom:1.2rem;line-height:1.5;text-align:left}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red);display:flex;align-items:center;gap:.5rem}

.submit-btn{
  width:100%;padding:.85rem;background:var(--blue);color:#fff;border:none;border-radius:10px;
  font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;
  box-shadow:0 4px 15px rgba(0,119,182,.3);
}
.submit-btn:hover{background:var(--blue2);transform:translateY(-1px)}

.btn-login-now{
  display:inline-block;width:100%;padding:.85rem;background:var(--green);color:#fff;text-decoration:none;
  border-radius:10px;font-size:1rem;font-weight:700;box-shadow:0 4px 15px rgba(45,198,83,.3);transition:.2s;
}
.btn-login-now:hover{background:#25B347;transform:translateY(-1px)}

.link-back{display:inline-block;margin-top:1rem;color:var(--blue);font-size:.85rem;text-decoration:none;font-weight:600}
.link-back:hover{color:var(--blue2)}

footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;flex-shrink:0}
footer strong{color:#fff}

@media(max-width:480px){
  .card{padding:1.8rem 1.3rem;border-radius:14px}
  .page-body{padding:1rem .5rem}
}
</style>
</head>
<body>

<nav>
  <a href="../index.php" class="nav-logo">Medi<span>RDV</span></a>
  <a href="login.php" class="back-link">← Retour à la connexion</a>
</nav>

<div class="page-body">
  <div class="card">

    <?php if ($succes): ?>
      <!-- ========== SUCCÈS ========== -->
      <div class="icon-box green">✅</div>
      <h2>Mot de passe modifié !</h2>
      <p class="subtitle">Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous reconnecter avec votre nouveau mot de passe.</p>
      <a href="login.php" class="btn-login-now">Se connecter →</a>

    <?php elseif (!$token_valide): ?>
      <!-- ========== TOKEN INVALIDE / EXPIRÉ ========== -->
      <div class="icon-box red">⚠️</div>
      <h2>Lien invalide</h2>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($erreur) ?></div>
      <a href="mot_de_passe_oublie.php" class="btn-login-now">Refaire une demande →</a>
      <br>
      <a href="login.php" class="link-back">← Retour à la connexion</a>

    <?php else: ?>
      <!-- ========== FORMULAIRE NOUVEAU MOT DE PASSE ========== -->
      <div class="icon-box blue">🔒</div>
      <h2>Nouveau mot de passe</h2>
      <p class="subtitle">
        Compte : <strong><?= htmlspecialchars($reset_row['email']) ?></strong><br>
        Choisissez un nouveau mot de passe sécurisé.
      </p>

      <?php if ($erreur !== ''): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <form method="POST" action="reinitialiser_mdp.php?token=<?= urlencode($token) ?>" id="resetForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
          <label for="new_pw">Nouveau mot de passe</label>
          <div class="input-wrap">
            <input type="password" id="new_pw" name="new_pw"
                   placeholder="Minimum 8 caractères"
                   oninput="checkStrength(this.value)"
                   autocomplete="new-password" required>
            <button type="button" class="toggle-pw" onclick="togglePw('new_pw')">👁</button>
          </div>
          <div class="pw-bars">
            <div class="pw-bar" id="bar1"></div>
            <div class="pw-bar" id="bar2"></div>
            <div class="pw-bar" id="bar3"></div>
            <div class="pw-bar" id="bar4"></div>
          </div>
          <div class="pw-label" id="pwLabel">Saisissez un mot de passe</div>
        </div>

        <div class="form-group">
          <label for="confirm">Confirmer le mot de passe</label>
          <div class="input-wrap">
            <input type="password" id="confirm" name="confirm"
                   placeholder="Répétez votre mot de passe"
                   oninput="checkMatch()"
                   autocomplete="new-password" required>
            <button type="button" class="toggle-pw" onclick="togglePw('confirm')">👁</button>
          </div>
          <div class="pw-label" id="matchMsg" style="display:none;color:var(--green)">✅ Les mots de passe correspondent</div>
        </div>

        <button type="submit" class="submit-btn">Réinitialiser le mot de passe →</button>
      </form>

      <a href="login.php" class="link-back">← Retour à la connexion</a>
    <?php endif; ?>

  </div>
</div>

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">reinitialiser_mdp.php</p>
</footer>

<script>
function togglePw(id) {
  var inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function checkStrength(v) {
  var score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  var colors = ['', '#D62828', '#FBBF24', '#F77F00', '#2DC653'];
  var labels = ['', '🔴 Très faible', '🟡 Faible', '🟠 Moyen', '🟢 Fort'];
  for (var i = 1; i <= 4; i++) {
    document.getElementById('bar'+i).style.background = i<=score ? colors[score] : '#E2E8F0';
  }
  document.getElementById('pwLabel').textContent = v.length ? labels[score] : 'Saisissez un mot de passe';
  checkMatch();
}

function checkMatch() {
  var v1 = document.getElementById('new_pw').value;
  var v2 = document.getElementById('confirm').value;
  var msg = document.getElementById('matchMsg');
  msg.style.display = (v2.length > 0 && v1 === v2) ? 'block' : 'none';
}

<?php if ($token_valide && !$succes): ?>
document.getElementById('resetForm').addEventListener('submit', function(e) {
  var v1 = document.getElementById('new_pw').value;
  var v2 = document.getElementById('confirm').value;
  if (v1.length < 8) {
    e.preventDefault();
    alert('Le mot de passe doit contenir au moins 8 caractères.');
  } else if (v1 !== v2) {
    e.preventDefault();
    alert('Les mots de passe ne correspondent pas.');
  }
});
<?php endif; ?>
</script>
</body>
</html>