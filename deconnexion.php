<?php
/**
 * Baracca - Site archicad.baracca.fr
 * Espace client : déconnexion
 */
require_once __DIR__ . '/includes/auth.php';
archicad_logout();
header('Location: index.php');
exit;
