<?php
// ================================================================
// register_admin.php — Inscription Administrateur
// Université Numérique Cheikh Hamidou Kane — L3 IDA 2024-2025
// ================================================================
session_start();
require_once __DIR__ . '/../includes/connexion.php';
require_once __DIR__ . '/../includes/fonctions.php';

// Vérifier l'accès admin
$admin_access = isset($_GET['admin_access']) && $_GET['admin_access'] === 'secret2025';
if (!$admin_access) {
    header('Location: login.php');
    exit();
}

if (isLoggedIn()) {
    redirect('dashboard_admin.php');
}

$erreur = '';
$succes = '';
$nom = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');

    if (empty($nom) || empty($email) || empty($password)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L\'adresse email n\'est pas valide.';
    } elseif (strlen($password) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password_confirm) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $erreur = 'Cet email est déjà utilisé.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (nom, email, mot_de_passe, date_creation) VALUES (:nom, :email, :password, NOW())");
            if ($stmt->execute([':nom' => $nom, ':email' => $email, ':password' => $hashed_password])) {
                $succes = '✅ Compte admin créé ! Redirection...';
                echo '<meta http-equiv="refresh" content="2;url=login_admin.php">';
            } else {
                $erreur = 'Erreur lors de la création.';
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
    <title>MediRDV — Inscription Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
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

        .register-wrapper{width:100%;max-width:460px;background:#fff;border-radius:20px;padding:2.5rem;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative;overflow:hidden}
        .register-wrapper::before{content:'🔐';position:absolute;top:-10px;right:-10px;font-size:5rem;opacity:0.05;transform:rotate(20deg)}
        
        .register-wrapper h2{font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:var(--dark);margin-bottom:.3rem}
        .register-wrapper .subtitle{color:var(--gray);font-size:.9rem;margin-bottom:1.5rem}
        .admin-badge{display:inline-block;background:#FEF3C7;color:#D97706;padding:.2rem .8rem;border-radius:20px;font-size:.7rem;font-weight:700;margin-bottom:1rem}

        .alert{padding:.75rem 1rem;border-radius:8px;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .alert-error{background:#FFF5F5;border:1px solid #FECACA;color:var(--red)}
        .alert-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#15803D}

        .form-group{margin-bottom:1.2rem}
        .form-group label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.5rem}
        .form-group input{
            width:100%;padding:.75rem 1rem;border:1.5px solid var(--lgray);border-radius:10px;
            font-size:.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:.2s
        }
        .form-group input:focus{border-color:var(--dark);box-shadow:0 0 0 3px rgba(3,4,94,.08)}
        .form-group input.field-error{border-color:var(--red);background:#FFF5F5}

        .submit-btn{
            width:100%;padding:.85rem;background:var(--dark);color:#fff;border:none;border-radius:10px;
            font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;
            box-shadow:0 4px 15px rgba(3,4,94,.3);
        }
        .submit-btn:hover{background:#1A1A4E;transform:translateY(-1px)}

        .login-link{text-align:center;margin-top:1.2rem;font-size:.85rem;color:var(--gray)}
        .login-link a{color:var(--blue);text-decoration:none;font-weight:600}
        .login-link a:hover{color:var(--blue2)}

        footer{background:var(--dark);color:#94A3B8;padding:20px 5%;text-align:center;font-size:.82rem;flex-shrink:0}
        footer strong{color:#fff}

        .security-badge{position:fixed;bottom:10px;right:10px;font-size:0.5rem;color:rgba(255,255,255,0.1);user-select:none;letter-spacing:0.5px;z-index:9999;}

        @media(max-width:480px){
            .register-wrapper{padding:1.8rem 1.2rem}
            .register-wrapper h2{font-size:1.3rem}
        }
    </style>
</head>
<body>
    <nav>
        <a href="../index.php" class="nav-logo">Medi<span>RDV</span></a>
        <a href="login_admin.php" class="back-link">← Retour</a>
    </nav>

    <div class="page-body">
        <div class="register-wrapper">
            <div class="admin-badge">🛡 Inscription Admin</div>
            <h2>Créer un compte Admin</h2>
            <p class="subtitle">Espace réservé aux administrateurs</p>

            <?php if ($erreur !== ''): ?>
                <div class="alert alert-error">⚠️ <?=htmlspecialchars($erreur)?></div>
            <?php endif; ?>

            <?php if ($succes !== ''): ?>
                <div class="alert alert-success">✅ <?=htmlspecialchars($succes)?></div>
            <?php endif; ?>

            <form method="POST" action="register_admin.php?admin_access=secret2025">
                <div class="form-group">
                    <label for="nom">Nom complet</label>
                    <input type="text" id="nom" name="nom" 
                           placeholder="Ex: Admin Principal"
                           value="<?=htmlspecialchars($nom)?>"
                           class="<?=$erreur!==''?'field-error':''?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email"
                           placeholder="admin@exemple.sn"
                           value="<?=htmlspecialchars($email)?>"
                           class="<?=$erreur!==''?'field-error':''?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password"
                           placeholder="•••••••• (min 6 caractères)"
                           class="<?=$erreur!==''?'field-error':''?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirmer</label>
                    <input type="password" id="password_confirm" name="password_confirm"
                           placeholder="••••••••"
                           class="<?=$erreur!==''?'field-error':''?>"
                           required>
                </div>

                <button type="submit" class="submit-btn">Créer l'admin →</button>
            </form>

            <div class="login-link">
                Déjà un compte admin ? <a href="login_admin.php">Se connecter</a>
            </div>
        </div>
    </div>

    <footer>
        <p><strong>MediRDV</strong> — Université Numérique Cheikh Hamidou Kane | L3 IDA 2024-2025</p>
    </footer>

    <div class="security-badge">🔒 v2.0</div>
</body>
</html>