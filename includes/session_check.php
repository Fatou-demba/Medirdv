<?php
// VERIFIER SESSION - inclure sur toutes les Pages privées

session_start();
if (!isset($_SESSION['user_id']))
     {
    header("Location: ../pages/login.php");
    exit();
      }

?>

