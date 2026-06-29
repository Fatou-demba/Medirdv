<?php
// ================================================================
// mot_de_passe_oublie.php — Demande de réinitialisation
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (isLoggedIn()) {
    $role = getRole();
    if ($role === 'medecin') redirect('profil_medecin.php');
    elseif ($role === 'admin') redirect('dashboard_admin.php');
    else redirect('profil_patient.php');
}

$erreur      = '';
$email_v     = '';
$lien_genere = '';

// ================================================================
// TRAITEMENT DU FORMULAIRE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = trim($_POST['email'] ?? '');
    $email_v = $email;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Veuillez saisir une adresse email valide.';
    } else {
        // Chercher l'email dans les 3 tables (patients, medecins, admins)
        $user      = null;
        $type_user = '';

        $stmt = $pdo->prepare("SELECT id, email FROM patients WHERE email = :e AND statut='actif' LIMIT 1");
        $stmt->execute([':e' => $email]);
        if ($u = $stmt->fetch()) { $user = $u; $type_user = 'patient'; }

        if (!$user) {
            $stmt = $pdo->prepare("SELECT id, email FROM medecins WHERE email = :e LIMIT 1");
            $stmt->execute([':e' => $email]);
            if ($u = $stmt->fetch()) { $user = $u; $type_user = 'medecin'; }
        }

        if (!$user) {
            $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE email = :e LIMIT 1");
            $stmt->execute([':e' => $email]);
            if ($u = $stmt->fetch()) { $user = $u; $type_user = 'admin'; }
        }

        // Toujours afficher le même message générique pour ne pas révéler
        // si un email existe ou non dans la base (bonne pratique sécurité).
        if ($user) {
            // Générer un token unique
            $token      = bin2hex(random_bytes(32));
            $expire_at  = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            // Invalider les anciens tokens non utilisés pour cet email
            $pdo->prepare("UPDATE password_resets SET utilise = 1 WHERE email = :e AND utilise = 0")
                ->execute([':e' => $email]);

            // Créer le nouveau token
            $pdo->prepare("INSERT INTO password_resets (email, type_user, token, expire_at)
                           VALUES (:e, :t, :tok, :exp)")
                ->execute([':e'=>$email, ':t'=>$type_user, ':tok'=>$token, ':exp'=>$expire_at]);

            // Construire le lien (affiché sur la page, pas d'envoi email configuré)
            $base_url    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $script_dir  = rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])), '/');
            $lien_genere = $base_url . $script_dir . '/reinitialiser_mdp.php?token=' . $token;
        } else {
            // Email inconnu : même message que succès pour ne pas exposer l'info
            $lien_genere = '';
        }

        // Message générique affiché dans tous les cas
        if (!$user) {
            $erreur = ''; // pas d'erreur affichée non plus, juste pas de lien
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediRDV — Mot de passe oublié</title>
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

.card{background:#fff;border-radius:20px;padding:2.5rem;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,.12)}

.icon-box{width:64px;height:64px;background:linear-gradient(135deg,var(--blue),var(--blue2));border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1.2rem}
.card h2{font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800;color:var(--dark);text-align:center;margin-bottom:.5rem}
.card .subtitle{color:var(--gray);font-size:.88rem;text-align:center;margin-bottom:1.8rem;line-height:1.5}

.form-group{margin-bottom:1.2rem}
.form-group label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.5rem}
.form-group input{
  width:100%;padding:.75rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
  font-size:.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;
}
.form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
.form-group input.field-error{border-color:var(--red);background:#FFF5F5}
.field-msg{font-size:.75rem;color:var(--red);margin-top:.3rem}

.submit-btn{
  width:100%;padding:.85rem;background:var(--blue);color:#fff;border:none;border-radius:10px;
  font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;
  box-shadow:0 4px 15px rgba(0,119,182,.3);
}
.submit-btn:hover{background:var(--blue2);transform:translateY(-1px)}

.alert{padding:.85rem 1rem;border-radius:10px;font-size:.84rem;margin-bottom:1.2rem;line-height:1.5}
.alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red);display:flex;align-items:center;gap:.5rem}
.alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
.alert-success strong{display:block;margin-bottom:.5rem}

/* Boîte du lien généré */
.link-box{background:#F8FAFF;border:1.5px dashed var(--blue);border-radius:10px;padding:1rem;margin-top:.8rem;word-break:break-all}
.link-box a{color:var(--blue);font-size:.83rem;font-weight:600;text-decoration:none}
.link-box a:hover{text-decoration:underline}
.copy-btn{display:inline-flex;align-items:center;gap:.4rem;margin-top:.7rem;padding:.45rem .9rem;background:var(--blue);color:#fff;border:none;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.2s}
.copy-btn:hover{background:var(--blue2)}
.copy-btn.copied{background:var(--green)}

.expire-note{font-size:.78rem;color:var(--gray);margin-top:.8rem;display:flex;align-items:center;gap:.4rem}

.login-link{text-align:center;margin-top:1.5rem;font-size:.85rem;color:var(--gray)}
.login-link a{color:var(--blue);font-weight:600;text-decoration:none}

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

    <div class="icon-box">🔑</div>
    <h2>Mot de passe oublié ?</h2>
    <p class="subtitle">Saisissez l'adresse email utilisée lors de votre inscription. Nous générerons un lien de réinitialisation.</p>

    <?php if ($erreur !== ''): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erreur === ''): ?>
      <!-- Message générique (succès dans tous les cas, lien affiché seulement si compte trouvé) -->
      <div class="alert alert-success">
        <strong>✅ Demande traitée</strong>
        Si un compte existe avec l'adresse <strong><?= htmlspecialchars($email_v) ?></strong>, un lien de réinitialisation a été généré ci-dessous.

        <?php if ($lien_genere !== ''): ?>
          <div class="link-box">
            <a href="<?= htmlspecialchars($lien_genere) ?>" id="resetLink"><?= htmlspecialchars($lien_genere) ?></a>
          </div>
          <button type="button" class="copy-btn" id="copyBtn" onclick="copyLink()">📋 Copier le lien</button>
          <div class="expire-note">⏱️ Ce lien est valable pendant <strong>1 heure</strong> et ne peut être utilisé qu'une seule fois.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="mot_de_passe_oublie.php">
      <div class="form-group">
        <label for="email">Adresse email</label>
        <input type="email" id="email" name="email"
               placeholder="votre.email@exemple.sn"
               value="<?= htmlspecialchars($email_v) ?>"
               class="<?= $erreur!==''?'field-error':'' ?>"
               autocomplete="email" required>
      </div>
      <button type="submit" class="submit-btn">Envoyer le lien →</button>
    </form>

    <div class="login-link">
      Vous vous souvenez de votre mot de passe ? <a href="login.php">Se connecter</a>
    </div>

  </div>
</div>

<footer>
  <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
  <p style="margin-top:.4rem">mot_de_passe_oublie.php</p>
</footer>

<script>
function copyLink() {
  var link = document.getElementById('resetLink').textContent;
  navigator.clipboard.writeText(link).then(function() {
    var btn = document.getElementById('copyBtn');
    btn.textContent = '✅ Lien copié !';
    btn.classList.add('copied');
    setTimeout(function() {
      btn.textContent = '📋 Copier le lien';
      btn.classList.remove('copied');
    }, 2000);
  });
}
</script>
</body>
</html>