<?php
// ================================================================
// login_admin.php — Connexion ADMIN (avec mot de passe oublié)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Si déjà connecté en tant qu'admin
if (isLoggedIn() && getRole() === 'admin') {
    redirect('dashboard_admin.php');
}

$erreur = '';
$email_v = '';
$succes = '';

// ================================================================
// TRAITEMENT DU FORMULAIRE DE CONNEXION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email_v = $email;

    if ($email === '' || $password === '') {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $erreur = 'Email ou mot de passe incorrect.';
        } elseif (!password_verify($password, $user['mot_de_passe'])) {
            $erreur = 'Email ou mot de passe incorrect.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['email'] = $user['email'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['nom'];

            try {
                $pdo->prepare("INSERT INTO journal_acces (id_user, type_user, action, ip_address) VALUES (:id, 'admin', 'connexion', :ip)")
                    ->execute([':id'=>$user['id'], ':ip'=>$_SERVER['REMOTE_ADDR'] ?? '']);
            } catch(Exception $e) {}

            redirect('dashboard_admin.php');
        }
    }
}

// ================================================================
// TRAITEMENT DU FORMULAIRE DE RÉINITIALISATION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $email = trim($_POST['reset_email'] ?? '');
    $nouveau_mdp = trim($_POST['nouveau_mdp'] ?? '');
    $confirmer_mdp = trim($_POST['confirmer_mdp'] ?? '');

    if ($email === '' || $nouveau_mdp === '' || $confirmer_mdp === '') {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (strlen($nouveau_mdp) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($nouveau_mdp !== $confirmer_mdp) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $erreur = 'Aucun administrateur trouvé avec cet email.';
        } else {
            $hashed = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET mot_de_passe = :pwd WHERE email = :email");
            if ($stmt->execute([':pwd' => $hashed, ':email' => $email])) {
                $succes = '✅ Mot de passe réinitialisé avec succès ! Connectez-vous.';
                $email_v = $email;
            } else {
                $erreur = 'Erreur lors de la réinitialisation.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0;--orange:#F77F00;--red:#D62828}
        body{font-family:'DM Sans',sans-serif;background:var(--light);min-height:100vh;display:flex;flex-direction:column}

        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none}
        .nav-logo span{color:var(--green)}
        .back-link{color:#CBD5E1;font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:.4rem;transition:.2s}
        .back-link:hover{color:#fff}

        .page-body{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%)}

        .login-wrapper{width:100%;max-width:460px;background:#fff;border-radius:20px;padding:2.5rem;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative;overflow:hidden}
        .login-wrapper::before{content:'🔐';position:absolute;top:-20px;right:-20px;font-size:6rem;opacity:0.05;transform:rotate(20deg)}
        
        .login-wrapper .admin-badge{display:inline-block;background:#FEF3C7;color:#D97706;padding:.2rem .8rem;border-radius:20px;font-size:.7rem;font-weight:700;margin-bottom:0.5rem}
        .login-wrapper h2{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:var(--dark);margin-bottom:.3rem}
        .login-wrapper .subtitle{color:var(--gray);font-size:.9rem;margin-bottom:1.5rem}

        .form-group{margin-bottom:1.2rem}
        .form-group label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.5rem}
        .input-wrap{position:relative}
        .form-group input{
            width:100%;padding:.75rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
            font-size:.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;
        }
        .form-group input:focus{border-color:var(--dark);box-shadow:0 0 0 3px rgba(3,4,94,.08)}
        .form-group input.field-error{border-color:#D62828;background:#FFF5F5}
        .toggle-pw{position:absolute;right:.9rem;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1rem;color:var(--gray);background:none;border:none;padding:0}

        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:#D62828}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}
        .alert-info{background:#EFF6FF;border:1px solid #BFDBFE;color:var(--blue)}

        .submit-btn{
            width:100%;padding:.85rem;background:var(--dark);color:#fff;border:none;border-radius:10px;
            font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;
            box-shadow:0 4px 15px rgba(3,4,94,.3);
        }
        .submit-btn:hover{background:#1A1A4E;transform:translateY(-1px)}
        .submit-btn-secondary{
            width:100%;padding:.75rem;background:transparent;color:var(--gray);border:2px solid var(--lgray);border-radius:10px;
            font-size:.85rem;font-weight:600;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;
        }
        .submit-btn-secondary:hover{background:var(--light);border-color:var(--gray)}

        .divider{display:flex;align-items:center;gap:1rem;margin:1.2rem 0;color:var(--gray);font-size:.82rem}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--lgray)}

        .register-section{text-align:center;margin-top:0.5rem}
        .register-section a{color:var(--blue);text-decoration:none;font-weight:600;font-size:.85rem}
        .register-section a:hover{color:var(--blue2)}

        .forgot-link{display:block;text-align:center;margin-top:0.8rem;font-size:.82rem;color:var(--gray);cursor:pointer;text-decoration:none}
        .forgot-link:hover{color:var(--dark)}

        .toggle-section{display:<?= isset($_POST['action']) && $_POST['action'] === 'reset' ? 'block' : 'none' ?>}
        .login-section{display:<?= isset($_POST['action']) && $_POST['action'] === 'reset' ? 'none' : 'block' ?>}

        .security-badge{position:fixed;bottom:10px;right:10px;font-size:0.5rem;color:rgba(255,255,255,0.1);user-select:none;letter-spacing:0.5px;z-index:9999;}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;flex-shrink:0}
        footer strong{color:#fff}

        @media(max-width:480px){
            .login-wrapper{padding:1.8rem 1.2rem}
            .login-wrapper h2{font-size:1.3rem}
        }
    </style>
</head>
<body>

<nav>
    <a href="../index.php" class="nav-logo">Medi<span>RDV</span></a>
    <a href="login.php" class="back-link">← Retour</a>
</nav>

<div class="page-body">
    <div class="login-wrapper">
        <div class="admin-badge">🛡 Accès Administrateur</div>
        <h2>Connexion Admin</h2>
        <p class="subtitle">Espace réservé aux administrateurs</p>

        <?php if ($erreur !== ''): ?>
            <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
        <?php endif; ?>

        <?php if ($succes !== ''): ?>
            <div class="alert alert-success">✅ <?=htmlspecialchars($succes)?></div>
        <?php endif; ?>

        <!-- ===== SECTION CONNEXION ===== -->
        <div class="login-section">
            <form method="POST" action="login_admin.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email"
                           placeholder="admin@exemple.sn"
                           value="<?=htmlspecialchars($email_v)?>"
                           class="<?=$erreur!==''?'field-error':''?>"
                           autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password"
                               placeholder="••••••••"
                               class="<?=$erreur!==''?'field-error':''?>"
                               autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('password')" title="Afficher/masquer">👁</button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Se connecter →</button>
            </form>

            <a href="#" class="forgot-link" onclick="showResetForm(event)">🔑 Mot de passe oublié ?</a>

            <div class="divider">ou</div>

            <div class="register-section">
                <a href="register_admin.php?admin_access=secret2025">Créer un compte administrateur</a>
            </div>
        </div>

        <!-- ===== SECTION RÉINITIALISATION ===== -->
        <div class="toggle-section" id="resetSection">
            <div class="alert alert-info">🔑 Renseignez votre email et votre nouveau mot de passe</div>
            <form method="POST" action="login_admin.php">
                <input type="hidden" name="action" value="reset">
                <div class="form-group">
                    <label for="reset_email">Adresse email</label>
                    <input type="email" id="reset_email" name="reset_email"
                           placeholder="admin@exemple.sn"
                           value="<?=htmlspecialchars($email_v)?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="nouveau_mdp">Nouveau mot de passe</label>
                    <div class="input-wrap">
                        <input type="password" id="nouveau_mdp" name="nouveau_mdp"
                               placeholder="•••••••• (min 6 caractères)"
                               required>
                        <button type="button" class="toggle-pw" onclick="togglePw('nouveau_mdp')" title="Afficher/masquer">👁</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmer_mdp">Confirmer le mot de passe</label>
                    <div class="input-wrap">
                        <input type="password" id="confirmer_mdp" name="confirmer_mdp"
                               placeholder="••••••••"
                               required>
                        <button type="button" class="toggle-pw" onclick="togglePw('confirmer_mdp')" title="Afficher/masquer">👁</button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Réinitialiser le mot de passe</button>
            </form>

            <a href="#" class="forgot-link" onclick="hideResetForm(event)" style="margin-top:0.8rem;">← Retour à la connexion</a>
        </div>

    </div>
</div>

<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
</footer>

<div class="security-badge">🔒 v2.0</div>

<script>
    // ============================================================
    // AFFICHER / CACHER LE FORMULAIRE DE RÉINITIALISATION
    // ============================================================
    function showResetForm(event) {
        event.preventDefault();
        document.querySelector('.login-section').style.display = 'none';
        document.getElementById('resetSection').style.display = 'block';
        // Mettre à jour le titre
        document.querySelector('.login-wrapper h2').textContent = 'Réinitialisation';
        document.querySelector('.login-wrapper .subtitle').textContent = 'Modifiez votre mot de passe';
    }

    function hideResetForm(event) {
        event.preventDefault();
        document.querySelector('.login-section').style.display = 'block';
        document.getElementById('resetSection').style.display = 'none';
        document.querySelector('.login-wrapper h2').textContent = 'Connexion Admin';
        document.querySelector('.login-wrapper .subtitle').textContent = 'Espace réservé aux administrateurs';
    }

    // ============================================================
    // AFFICHER / CACHER LE MOT DE PASSE
    // ============================================================
    function togglePw(id) {
        var input = document.getElementById(id);
        if (input) {
            input.type = (input.type === 'password') ? 'text' : 'password';
        }
    }

    // ============================================================
    // SI ERREUR SUR LE FORMULAIRE DE RÉINIT, RESTER SUR CE FORMULAIRE
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_POST['action']) && $_POST['action'] === 'reset' && $erreur !== ''): ?>
            document.querySelector('.login-section').style.display = 'none';
            document.getElementById('resetSection').style.display = 'block';
            document.querySelector('.login-wrapper h2').textContent = 'Réinitialisation';
            document.querySelector('.login-wrapper .subtitle').textContent = 'Modifiez votre mot de passe';
        <?php endif; ?>
    });
</script>
</body>
</html>