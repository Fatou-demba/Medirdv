<?php // HEADER COMMUN - Dieyna Diallo ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRDV - <?= $page_title ?? 'Gestion RDV Medicaux' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../pages/index.php">MediRDV</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="../pages/index.php">Accueil</a></li>
                <li class="nav-item"><a class="nav-link" href="../pages/liste_medecins.php">Medecins</a></li>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="../pages/notifications.php">Notifications</a></li>
                    <li class="nav-item"><a class="nav-link" href="../pages/logout.php">Deconnexion</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="../pages/login.php">Connexion</a></li>
                    <li class="nav-item"><a class="nav-link" href="../pages/register_patient.php">S inscrire</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
