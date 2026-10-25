<?php
// Configuration de l'email de destination
$to = "invest@baracca.fr";
$subject = "Nouvelle inscription newsletter Baracca";

// Récupération de l'email depuis le formulaire
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

// Vérification que l'email est valide
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Redirection avec erreur si l'email est invalide
    header("Location: index.php?newsletter=error");
    exit();
}

// Préparation du message
$message = "Nouvelle inscription à la newsletter Baracca:\n\n";
$message .= "Adresse email: " . $email . "\n";

// En-têtes de l'email
$headers = "From: no-reply@baracca.fr\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Envoi de l'email
$mailSent = mail($to, $subject, $message, $headers);

// Redirection vers le bas de page (footer)
if ($mailSent) {
    header("Location: index.php?newsletter=success#footer");
} else {
    header("Location: index.php?newsletter=error#footer");
}
exit();
?>
