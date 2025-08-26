
<?php
// resetfunc.php
function reset_or_create_user_password($pdo, $username, $email, $password, $full_name, $role, $profile_picture, $created_at, $fonction = null) {
    // Vérifie si l'utilisateur existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => "Nom d'utilisateur ou email déjà utilisé"];
    }
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Mise à jour de la requête SQL pour inclure la fonction
    $sql = "INSERT INTO users (username, email, password_hash, full_name, role, profile_picture, created_at, fonction) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$username, $email, $password_hash, $full_name, $role, $profile_picture, $created_at, $fonction])) {
        return ['success' => true, 'message' => "Utilisateur créé"];
    } else {
        return ['success' => false, 'message' => "Erreur lors de la création de l'utilisateur"];
    }
}
?>