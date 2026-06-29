<?php
// CONNEXION BASE DE DONNEES
// Responsable : Dieyna Diallo (Etudiante 8)
$host = "localhost";
$dbname = "gestion_rdv_medical";
$username = "root";
$password = ""; // Vide par defaut sur WAMP/XAMPP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion BDD : " . $e->getMessage());
}
?>
