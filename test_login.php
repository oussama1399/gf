<?php
// Connexion à la base de données
$pdo = new PDO('mysql:host=localhost;dbname=gestion_flotte;charset=utf8', 'root', 'Spijotan');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tester la connexion utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Récupérer l'utilisateur depuis la base de données
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $hash = $user['password_hash'];
        echo "🔐 Hash en base : <code>$hash</code><br>";

        if (password_verify($password, $hash)) {
            echo "<p style='color:green;'>✅ Connexion réussie</p>";
        } else {
            echo "<p style='color:red;'>❌ Mot de passe incorrect</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ Utilisateur introuvable</p>";
    }

    exit;
}
?>

<form method="POST">
    <label>Nom d'utilisateur : <input type="text" name="username" required></label><br><br>
    <label>Mot de passe : <input type="password" name="password" required></label><br><br>
    <button type="submit">Tester la connexion</button>
</form>
