<?php
/**
 * Baracca - Site archicad.baracca.fr
 * API (même origine) : catalogues du configurateur (menuiseries, modèles de
 * modules, enveloppe), consommée par l'application React. Lecture seule :
 * la gestion des catalogues se fait depuis le back-office
 * (admin/configurateur.php).
 */
require_once __DIR__ . '/../includes/catalogue.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => true,
    'catalogue' => [
        'menuiseries' => archicad_load_catalogue('menuiseries'),
        'modules' => archicad_load_catalogue('modules'),
        'enveloppe' => [
            'murs' => archicad_load_catalogue('murs'),
            'planchers' => archicad_load_catalogue('planchers'),
            'facades' => archicad_load_catalogue('facades'),
        ],
        'toitures' => archicad_catalogue_toitures(),
    ],
], JSON_UNESCAPED_UNICODE);
