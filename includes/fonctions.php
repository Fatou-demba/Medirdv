<?php
// FONCTIONS UTILITAIRES COMMUNES
function redirect($url) { header("Location: $url"); exit(); }
function sanitize($d) { return htmlspecialchars(strip_tags(trim($d))); }
function hashPassword($p) { return password_hash($p, PASSWORD_BCRYPT); }
function verifyPassword($p, $h) { return password_verify($p, $h); }
function isLoggedIn() { return isset($_SESSION['user_id']); }
function getRole() { return $_SESSION['role'] ?? null; }
?>
