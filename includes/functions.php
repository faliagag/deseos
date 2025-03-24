<?php
// includes/functions.php
function sendEmail($to, $subject, $body) {
    require_once __DIR__ . "/../vendor/autoload.php";
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    // Configuración SMTP (ajusta estos valores)
    $mail->isSMTP();
    $mail->Host       = "smtp.example.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "your_email@example.com";
    $mail->Password   = "your_password";
    $mail->SMTPSecure = "tls";
    $mail->Port       = 587;
    
    $mail->setFrom("your_email@example.com", "GiftList App");
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    
    return $mail->send();
}
