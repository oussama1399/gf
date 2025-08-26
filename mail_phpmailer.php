<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'include/PHPMailer/src/Exception.php';
require 'include/PHPMailer/src/PHPMailer.php';
require 'include/PHPMailer/src/SMTP.php';

function envoyerMailAffectation($agentEmail, $agentNom, $nd, $etablissement, $fonction) {
    $mail = new PHPMailer(true);
    try {
        // Paramètres SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Serveur SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'ton.email@gmail.com'; // Ton email
        $mail->Password = 'ton_mot_de_passe'; // Ton mot de passe ou mot de passe d'application
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('ton.email@gmail.com', 'Gestion Flotte');
        $mail->addAddress($agentEmail, $agentNom);
        $mail->Subject = "Affectation d'un numéro de flotte";
        $mail->Body = "Bonjour $agentNom,\n\nVous venez d'être affecté au numéro : $nd\nÉtablissement : $etablissement\nFonction : $fonction\n\nMerci de prendre connaissance de cette affectation.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erreur envoi mail: ' . $mail->ErrorInfo);
        return false;
    }
}







