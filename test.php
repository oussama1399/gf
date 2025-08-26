<?php
$motdepasse = 'Test@1234'; // remplace par celui que tu tests
$hash = '$2y$10$AnG.Fj1NgueCmrs57aZwm.Pd1IYCIyKBVAdWaflVwJikTKDhWAdcS';

if (password_verify($motdepasse, $hash)) {
    echo "✅ Mot de passe correct";
} else {
    echo "❌ Mot de passe incorrect";
}
?>
