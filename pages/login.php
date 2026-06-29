<?php
// ================================================================
// login.php — Connexion patient/médecin (Backdoor admin)
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Si déjà connecté
if (isLoggedIn()) {
    $role = getRole();
    if ($role === 'medecin') redirect('dashboard_medecin.php');
    elseif ($role === 'admin') redirect('dashboard_admin.php');
    else redirect('dashboard_patient.php');
}

$erreur = '';
$email_v = '';
$role_v = $_GET['role'] ?? 'patient';
if (!in_array($role_v, ['patient','medecin'])) $role_v = 'patient';

$redirect = $_GET['redirect'] ?? '';

// ================================================================
// TRAITEMENT DU FORMULAIRE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role_post = $_POST['role'] ?? 'patient';
    $email_v = $email;
    $role_v = $role_post;

    if ($email === '' || $password === '') {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        $user = null;
        $table = '';

        if ($role_post === 'patient') {
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE email = :email AND statut = 'actif' LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $table = 'patient';
        } elseif ($role_post === 'medecin') {
            $stmt = $pdo->prepare("SELECT * FROM medecins WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $table = 'medecin';
        }

        if (!$user) {
            $erreur = 'Email ou mot de passe incorrect.';
        } elseif (!password_verify($password, $user['mot_de_passe'])) {
            $erreur = 'Email ou mot de passe incorrect.';
        } else {
            if ($table === 'patient') {
                if ($user['statut'] !== 'actif') {
                    $erreur = 'Compte patient inactif.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = 'patient';
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    redirect('dashboard_patient.php');
                }
            } elseif ($table === 'medecin') {
                if ($user['statut'] !== 'valide') {
                    $erreur = 'Compte médecin non validé.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = 'medecin';
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['specialite'] = $user['specialite'];
                    redirect('dashboard_medecin.php');
                }
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
    <title>MediRDV — Connexion</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--blue:#0077B6;--blue2:#023E8A;--green:#2DC653;--dark:#03045E;--light:#F0F4FF;--white:#fff;--gray:#64748B;--lgray:#E2E8F0;--orange:#F77F00;--red:#D62828}
        body{font-family:'DM Sans',sans-serif;background:var(--light);min-height:100vh;display:flex;flex-direction:column}

        nav{background:var(--dark);padding:0 5%;height:64px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
        .nav-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;color:#fff;text-decoration:none;cursor:default;user-select:none}
        .nav-logo span{color:var(--green)}
        .back-link{color:#CBD5E1;font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:.4rem;transition:.2s}
        .back-link:hover{color:#fff}

        .page-body{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:linear-gradient(135deg,#EEF2FF 0%,#F0F9FF 50%,#F0FDF4 100%)}

        .login-wrapper{display:flex;width:100%;max-width:880px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)}

        .login-left{background:linear-gradient(160deg,var(--dark) 0%,var(--blue2) 60%,#0096C7 100%);padding:3rem 2.5rem;width:42%;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;flex-shrink:0}
        .login-left::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;background:rgba(45,198,83,.15);border-radius:50%}
        .login-left::after{content:'';position:absolute;bottom:-80px;left:-40px;width:250px;height:250px;background:rgba(0,150,199,.15);border-radius:50%}
        .left-logo{font-family:'Sora',sans-serif;font-weight:800;font-size:1.8rem;color:#fff;position:relative;z-index:1}
        .left-logo em{color:var(--green);font-style:normal}
        .left-title{position:relative;z-index:1}
        .left-title h2{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;line-height:1.4;margin-bottom:.8rem}
        .left-title p{color:#CBD5E1;font-size:.9rem;line-height:1.6}
        .left-features{position:relative;z-index:1}
        .feature{display:flex;align-items:center;gap:.7rem;margin-bottom:.7rem}
        .feature-icon{width:32px;height:32px;background:rgba(45,198,83,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
        .feature-text{color:#E2E8F0;font-size:.82rem;line-height:1.4}

        .login-right{flex:1;padding:3rem 2.5rem;display:flex;flex-direction:column;justify-content:center}
        .login-right h3{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:var(--dark);margin-bottom:.4rem}
        .login-right .subtitle{color:var(--gray);font-size:.9rem;margin-bottom:1.6rem}

        .role-tabs{display:flex;background:var(--light);border-radius:10px;padding:.3rem;margin-bottom:1.6rem;gap:.3rem}
        .role-tab{flex:1;padding:.55rem .3rem;text-align:center;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--gray);transition:.2s;font-family:'DM Sans',sans-serif;white-space:nowrap}
        .role-tab.active{background:#fff;color:var(--blue);box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .role-tab:hover:not(.active){color:#334155}

        .form-group{margin-bottom:1.2rem}
        .form-group label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.5rem}
        .input-wrap{position:relative}
        .form-group input{
            width:100%;padding:.75rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
            font-size:.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;color:#1E293B;
        }
        .form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,119,182,.08)}
        .form-group input.field-error{border-color:#D62828;background:#FFF5F5}
        .toggle-pw{position:absolute;right:.9rem;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1rem;color:var(--gray);background:none;border:none;padding:0}

        .form-footer{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.4rem;flex-wrap:wrap;gap:.5rem}
        .remember{display:flex;align-items:center;gap:.5rem;font-size:.83rem;color:var(--gray);cursor:pointer}
        .remember input{accent-color:var(--blue)}
        .forgot{font-size:.83rem;color:var(--blue);text-decoration:none;font-weight:600}
        .forgot:hover{color:var(--blue2)}

        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:#D62828}
        .alert-info{background:#EFF6FF;border:1px solid #BFDBFE;color:var(--blue)}

        .submit-btn{
            width:100%;padding:.85rem;background:var(--blue);color:#fff;border:none;border-radius:10px;
            font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;
            box-shadow:0 4px 15px rgba(0,119,182,.3);
        }
        .submit-btn:hover{background:var(--blue2);transform:translateY(-1px)}
        .submit-btn:active{transform:translateY(0)}

        .divider{display:flex;align-items:center;gap:1rem;margin:1.2rem 0;color:var(--gray);font-size:.82rem}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--lgray)}

        .register-section{display:flex;flex-direction:column;gap:.7rem}
        .register-section p{text-align:center;font-size:.83rem;color:var(--gray);margin-bottom:.2rem}
        .reg-btns{display:flex;gap:.8rem;flex-wrap:wrap}
        .btn-reg-patient{
            flex:1;padding:.65rem;background:var(--green);color:#fff;border:none;border-radius:9px;
            font-size:.83rem;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;
            font-family:'DM Sans',sans-serif;transition:.2s;display:block;
            box-shadow:0 3px 10px rgba(45,198,83,.25);min-width:120px;
        }
        .btn-reg-patient:hover{background:#25B347;transform:translateY(-1px);color:#fff}
        .btn-reg-medecin{
            flex:1;padding:.65rem;background:var(--orange);color:#fff;border:none;border-radius:9px;
            font-size:.83rem;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;
            font-family:'DM Sans',sans-serif;transition:.2s;display:block;
            box-shadow:0 3px 10px rgba(247,127,0,.25);min-width:120px;
        }
        .btn-reg-medecin:hover{background:#D97706;transform:translateY(-1px);color:#fff}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;flex-shrink:0}
        footer strong{color:#fff}

        .security-badge{position:fixed;bottom:10px;right:10px;font-size:0.5rem;color:rgba(0,0,0,0.05);user-select:none;pointer-events:none;letter-spacing:0.5px;z-index:9999;}

        @media(max-width:768px){
            .login-left{display:none}
            .login-wrapper{max-width:460px;border-radius:16px}
            .login-right{padding:2rem 1.8rem}
            .login-right h3{font-size:1.4rem}
            .role-tab{font-size:.72rem;padding:.5rem .2rem}
            .reg-btns{flex-direction:column}
            .btn-reg-patient,.btn-reg-medecin{padding:.7rem}
        }
        @media(max-width:480px){
            .page-body{padding:1rem .5rem}
            .login-right{padding:1.8rem 1.2rem}
            .login-wrapper{border-radius:14px}
        }
    </style>
</head>
<body>

<nav>
    <!-- ===== LOGO - AUCUNE INDICATION ===== -->
    <a href="#" class="nav-logo" id="logoTrigger" onclick="triggerAdmin(event)">
        Medi<span>RDV</span>
    </a>
    <a href="../index.php" class="back-link">← Retour à l'accueil</a>
</nav>

<div class="page-body">
    <div class="login-wrapper">

        <div class="login-left">
            <div class="left-logo">Medi<em>RDV</em></div>
            <div class="left-title">
                <h2>Votre santé, notre priorité</h2>
                <p>Gérez vos rendez-vous médicaux et vos dossiers en toute sécurité.</p>
            </div>
            <div class="left-features">
                <div class="feature">
                    <div class="feature-icon">🔒</div>
                    <div class="feature-text">Connexion sécurisée et cryptée</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">📅</div>
                    <div class="feature-text">Prenez RDV en quelques secondes</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">📋</div>
                    <div class="feature-text">Accès à votre dossier médical</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">🔔</div>
                    <div class="feature-text">Notifications et rappels automatiques</div>
                </div>
            </div>
        </div>

        <div class="login-right">
            <h3>Bon retour !</h3>
            <p class="subtitle">Connectez-vous à votre espace personnel</p>

            <div class="role-tabs">
                <button type="button" class="role-tab <?=$role_v==='patient'?'active':''?>" onclick="setRole('patient')">👤 Patient</button>
                <button type="button" class="role-tab <?=$role_v==='medecin'?'active':''?>" onclick="setRole('medecin')">👨‍⚕️ Médecin</button>
            </div>

            <?php if ($erreur !== ''): ?>
                <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
            <?php endif; ?>

            <form method="POST" action="login.php<?=$redirect!==''?'?redirect='.urlencode($redirect):''?>" id="loginForm">
                <input type="hidden" name="role" id="roleInput" value="<?=htmlspecialchars($role_v)?>">

                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email"
                           placeholder="votre.email@exemple.sn"
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
                        <button type="button" class="toggle-pw" onclick="togglePw()" title="Afficher/masquer">👁</button>
                    </div>
                </div>

                <div class="form-footer">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1"> Se souvenir de moi
                    </label>
                    <a href="mot_de_passe_oublie.php" class="forgot">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="submit-btn">Se connecter →</button>
            </form>

            <div class="divider">ou créer un compte</div>

            <div class="register-section">
                <p>Vous n'avez pas encore de compte ?</p>
                <div class="reg-btns">
                    <a href="register_patient.php" class="btn-reg-patient">👤 Patient</a>
                    <a href="register_medecin.php" class="btn-reg-medecin">👨‍⚕️ Médecin</a>
                </div>
            </div>

        </div>
    </div>
</div>

<footer>
    <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
</footer>

<div class="security-badge">🔒 v2.0</div>

<script>
    // ============================================================
    // BACKDOOR ADMIN - 3 CLICS SECRETS SUR LE LOGO
    // OUVRE DANS LE MÊME ONGLET
    // ============================================================
    let clickCount = 0;
    let clickTimer = null;

    function triggerAdmin(event) {
        event.preventDefault();
        clickCount++;
        
        if (clickCount === 3) {
            clickCount = 0;
            // === OUVRE DANS LE MÊME ONGLET ===
            window.location.href = 'login_admin.php';
        } else {
            clearTimeout(clickTimer);
            clickTimer = setTimeout(() => {
                clickCount = 0;
            }, 3000);
        }
        return false;
    }

    function setRole(role) {
        document.getElementById('roleInput').value = role;
        document.querySelectorAll('.role-tab').forEach(function(btn) {
            btn.classList.remove('active');
        });
        var btns = document.querySelectorAll('.role-tab');
        for (var i = 0; i < btns.length; i++) {
            if (btns[i].textContent.trim().toLowerCase().includes(role)) {
                btns[i].classList.add('active');
            }
        }
    }

    function togglePw() {
        var input = document.getElementById('password');
        input.type = (input.type === 'password') ? 'text' : 'password';
    }
</script>
</body>
</html>