// pages/logout.php (version avec journalisation)

<?php
/**
 * Page de déconnexion avec journalisation
 */

require_once '../includes/connexion.php';
require_once '../includes/fonctions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Journaliser la déconnexion si l'utilisateur est connecté
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_nom'] ?? 'Inconnu';
    $user_email = $_SESSION['user_email'] ?? 'Inconnu';
    
    // Journaliser la déconnexion (si vous avez une table de logs)
    try {
        $sql = "INSERT INTO logs (utilisateur_id, action, date, details) 
                VALUES (:user_id, 'DECONNEXION', NOW(), :details)";
        $stmt = $pdo->prepare($sql);
        $details = "Utilisateur $user_name ($user_email) s'est déconnecté";
        $stmt->execute([
            ':user_id' => $user_id,
            ':details' => $details
        ]);
    } catch (Exception $e) {
        // Ne pas bloquer la déconnexion si le log échoue
        error_log("Erreur de journalisation de déconnexion : " . $e->getMessage());
    }
}

// Vider la session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header("Location: login.php?message=" . urlencode("Vous avez été déconnecté avec succès.") . "&type=success");
exit();
?>