<?php
function estSuperAdmin() {
    if (!isset($_SESSION['utilisateur'])) return false;

    $fonction = strtolower(trim($_SESSION['utilisateur']['fonction'] ?? ''));
    $role = strtolower(trim($_SESSION['utilisateur']['role'] ?? ''));

    return ($fonction === 'Super utilisateur' || $role === 'admin');
}
