<?php
// logout.php
session_start();

// Détruit toutes les données de session
$_SESSION = array();

// Efface le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruit la session
session_destroy();

// Redirige vers la page de connexion
header("Location: index.php");
exit();
?>