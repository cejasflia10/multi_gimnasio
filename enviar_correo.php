<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function enviarCorreoNuevoUsuario($destinatario, $usuario, $claveTemporal) {
    $mail = new PHPMailer(true);

    // CONFIGURACIÓN SMTP
    $smtp_usuario = ''; // agregar cuando tengas
    $smtp_password = ''; // agregar cuando tengas
    $smtp_host = 'smtp.tu-servidor.com'; // cambiar
    $smtp_puerto = 587;

    try {
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_usuario;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = 'tls';
        $mail->Port = $smtp_puerto;

        $mail->setFrom($smtp_usuario, 'Fight Academy');
        $mail->addAddress($destinatario);

        $mail->isHTML(true);
        $mail->Subject = 'Tu acceso al sistema de Fight Academy';
        $mail->Body = '
        <h2>¡Bienvenido!</h2>
        <p>Tu usuario ha sido creado en el sistema.</p>
        <p><strong>Usuario:</strong> ' . $usuario . '</p>
        <p><strong>Contraseña temporal:</strong> ' . $claveTemporal . '</p>
        <p>Ingresá al sistema desde <a href="https://tusistema.com/login.php">este enlace</a>.</p>
        <p>Por seguridad, cambiá tu contraseña desde: <a href="https://tusistema.com/cambiar_contrasena.php">cambiar contraseña</a></p>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
