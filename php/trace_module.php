<?php
/* Endpoint de traçage des configurations (optionnel).
   Renseignez TRACE_URL dans src/App.jsx avec ce chemin.
   Chaque téléchargement du JSON envoie une copie ici : elle est
   sauvegardée en fichier daté, et peut être envoyée par e-mail. */

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['module'])) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalide']);
    exit;
}

// 1. Sauvegarde en fichier daté (créez le dossier et interdisez son listage)
$dir = __DIR__ . '/modules-configures';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }
$nom = preg_replace('/[^A-Za-z0-9_-]/', '_', $data['module']['nom'] ?? 'module');
file_put_contents(
    $dir . '/' . $nom . '_' . date('Ymd_His') . '.json',
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// 2. (Optionnel) notification e-mail à l'architecte — décommentez :
/*
$to      = 'archi@baracca.fr';
$sujet   = 'Nouveau module configuré : ' . $data['module']['nom'];
$message = "Un module a été configuré sur le site.\n\n"
         . "Surface : " . $data['metriques']['surfacePlancher'] . " m2\n"
         . "Toiture : " . $data['module']['toiture']['type'] . "\n";
mail($to, $sujet, $message);
*/

echo json_encode(['ok' => true]);
