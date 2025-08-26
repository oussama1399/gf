<?php
$password_en_clair = "Test@1234"; // change ici pour tester ton vrai mot de passe
$hash = password_hash($password_en_clair, PASSWORD_DEFAULT);

echo "<h3>Test de password_hash + password_verify</h3>";
echo "<p>Mot de passe : <strong>$password_en_clair</strong></p>";
echo "<p>Hash généré : <code>$hash</code></p>";

if (password_verify($password_en_clair, $hash)) {
    echo "<p style='color:green;'>✔️ password_verify fonctionne</p>";
} else {
    echo "<p style='color:red;'>❌ password_verify échoue</p>";
}
?>
