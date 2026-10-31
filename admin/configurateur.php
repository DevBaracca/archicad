<?php
/**
 * Baracca - Back-office (archicad.baracca.fr)
 * Gestion des catalogues du configurateur : menuiseries (baies vitrées,
 * fenêtres, portes — avec visuel et référence Archicad), modèles de modules
 * (géométrie prédéfinie + forme de toiture et éléments d'enveloppe liés),
 * formes de toiture (pente, pignon), charpentes et enveloppe (murs, planchers,
 * surfaces, toitures). Chaque élément porte un tarif ; le configurateur en
 * calcule le tarif global du projet.
 *
 * L'ajout / la modification d'un élément se fait dans une boîte (modale) :
 * bouton « + » à côté du titre de la rubrique, crayon pour modifier,
 * croix rouge pour supprimer ; à la validation la boîte se referme.
 */

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/catalogue.php';

archicad_admin_require_auth();

$section = (string) ($_GET['section'] ?? 'modules');
// Onglets : Modules, Formes, Charpentes, Murs, Planchers, Toitures, Ouvrants, Surfaces
// (l'ancien onglet « enveloppe » est éclaté en sous-onglets)
if ($section === 'enveloppe') {
    $sous0 = (string) ($_GET['sous'] ?? '');
    $section = in_array($sous0, ['murs', 'planchers', 'toitures', 'surfaces'], true) ? $sous0 : 'murs';
}
if (!in_array($section, ['modules', 'formes', 'charpentes', 'murs', 'planchers', 'toitures', 'menuiseries', 'surfaces'], true)) {
    $section = 'modules';
}

function cat_redirect(string $section, string $flash, string $err = ''): void
{
    $url = 'configurateur.php?section=' . urlencode($section) . '&flash=' . urlencode($flash);
    if ($err !== '') {
        $url .= '&err=' . urlencode($err);
    }
    header('Location: ' . $url);
    exit;
}

function cat_num(string $key): float
{
    $v = str_replace(',', '.', trim((string) ($_POST[$key] ?? '')));
    return is_numeric($v) ? (float) $v : 0.0;
}

function cat_eur(float $tarif): string
{
    return number_format($tarif, 2, ',', ' ');
}

// Dimension affichée en cm, précision au mm (0,1 cm) — stockage en m
function cat_cm(float $m): string
{
    return number_format($m * 100, 1, ',', '') . ' cm';
}

// Valeur de champ <input type="number"> saisie en cm (stockage en m)
function cat_cm_input(float $m): string
{
    return (string) round($m * 100, 1);
}

// Couleur d'affichage en hexadécimal (#rrggbb), à partir du rgb stocké
// (le choix des couleurs dans le formulaire reste en rgb : r/g/b)
function cat_hex(array $rgb): string
{
    $h = '#';
    foreach ([0, 1, 2] as $i) {
        $h .= str_pad(dechex(max(0, min(255, (int) ($rgb[$i] ?? 0)))), 2, '0', STR_PAD_LEFT);
    }
    return $h;
}

// Valeur échappée d'un champ de l'élément enveloppe en cours d'édition
function cat_env_field(array $editEnv, string $sous, string $key, string $default = ''): string
{
    return htmlspecialchars((string) (($editEnv['sous'] === $sous && $editEnv['item']) ? ($editEnv['item'][$key] ?? $default) : $default));
}

// Vignette du visuel d'un élément de catalogue ('—' si aucun)
function cat_thumb(array $item): string
{
    $img = (string) ($item['image'] ?? '');
    if ($img === '') {
        return '<span class="admin-no-image">—</span>';
    }
    return '<img src="../' . ltrim(htmlspecialchars($img), '/') . '" alt="" class="admin-catalogue-thumb">';
}

// ----- Icônes et modale -----
function cat_icon_edit(): string
{
    return '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><path d="M11.2 1.8a1.6 1.6 0 0 1 2.3 0l.7.7a1.6 1.6 0 0 1 0 2.3L5.6 13.4l-3.2.9.9-3.2 8.9-8.3z" fill="currentColor"/></svg>';
}

function cat_icon_delete(): string
{
    return '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><path d="M3.5 3.5l9 9m0-9l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';
}

// Bouton « + » à côté du titre d'une rubrique (ouvre la boîte d'ajout)
function cat_btn_add(string $href, string $title): string
{
    return '<a class="btn-add" href="' . htmlspecialchars($href) . '" title="' . htmlspecialchars($title) . '">+</a>';
}

// Action « modifier » (crayon) dans une ligne de liste
function cat_action_edit(string $href): string
{
    return '<a class="icon-btn" href="' . htmlspecialchars($href) . '" title="Modifier">' . cat_icon_edit() . '</a>';
}

// Action « supprimer » (croix rouge) dans une ligne de liste
function cat_action_delete(string $actionUrl, string $csrf, string $action, string $id, string $confirm, string $extraHidden = ''): string
{
    return '<form method="post" action="' . htmlspecialchars($actionUrl) . '" class="admin-inline-form" onsubmit="return confirm(\''
        . addslashes($confirm) . '\');">'
        . '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrf) . '">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">'
        . $extraHidden
        . '<input type="hidden" name="id" value="' . htmlspecialchars($id) . '">'
        . '<button type="submit" class="icon-btn danger" title="Supprimer">' . cat_icon_delete() . '</button>'
        . '</form>';
}

// Boîte (modale) d'ajout / modification : ouverte quand on édite ou ajoute
function modal_open(string $closeHref): void
{
    echo '<div class="modal-backdrop" onclick="if(event.target===this)location.href=\''
        . htmlspecialchars($closeHref, ENT_QUOTES) . '\'">'
        . '<div class="modal" role="dialog" aria-modal="true">'
        . '<a class="modal-close" href="' . htmlspecialchars($closeHref) . '" title="Fermer">' . cat_icon_delete() . '</a>';
}

function modal_close(): void
{
    echo '</div></div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!archicad_admin_csrf_verify($_POST['csrf'] ?? null)) {
        cat_redirect($section, 'csrf');
    }
    $action = (string) ($_POST['action'] ?? '');

    // ----- Menuiseries -----
    if ($action === 'save_menuiserie') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $type = (string) ($_POST['type'] ?? 'baie');
        if (!in_array($type, ['baie', 'fenetre', 'porte'], true)) {
            $type = 'baie';
        }
        // Saisie en cm (précision mm) — stockage en m
        $largeur = round(cat_num('largeur') / 100, 3);
        $hauteur = round(cat_num('hauteur') / 100, 3);
        $tarif = round(cat_num('tarif'), 2);
        if ($nom === '' || $largeur <= 0 || $hauteur <= 0) {
            cat_redirect('menuiseries', 'invalid');
        }
        $item = archicad_catalogue_upsert('menuiseries', [
            'nom' => $nom,
            'type' => $type,
            'largeur' => $largeur,
            'hauteur' => $hauteur,
            'favori' => trim((string) ($_POST['favori'] ?? '')),
            'tarif' => $tarif,
        ], $id !== '' ? $id : null);
        // Visuel : suppression demandée, ou nouvel envoi
        if (isset($_POST['remove_image']) && (string) ($item['image'] ?? '') !== '') {
            $fs = archicad_catalogue_image_fs_path((string) $item['image']);
            if ($fs !== null && is_file($fs)) {
                unlink($fs);
            }
            archicad_catalogue_upsert('menuiseries', ['image' => ''], (string) $item['id']);
        }
        if (isset($_FILES['image']) && is_array($_FILES['image'])) {
            $stored = archicad_catalogue_store_image($_FILES['image'], (string) $item['id']);
            if (!$stored['ok']) {
                cat_redirect('menuiseries', 'image', (string) $stored['error']);
            }
            $old = (string) ($item['image'] ?? '');
            if ($old !== '' && $old !== $stored['webPath']) {
                $fs = archicad_catalogue_image_fs_path($old);
                if ($fs !== null && is_file($fs)) {
                    unlink($fs);
                }
            }
            archicad_catalogue_upsert('menuiseries', ['image' => (string) $stored['webPath']], (string) $item['id']);
        }
        cat_redirect('menuiseries', 'saved');
    }
    if ($action === 'delete_menuiserie') {
        archicad_catalogue_delete('menuiseries', trim((string) ($_POST['id'] ?? '')));
        cat_redirect('menuiseries', 'deleted');
    }

    // ----- Modèles de modules -----
    if ($action === 'save_module') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        // Saisie en cm (précision mm) — stockage en m
        $longueur = round(cat_num('longueur') / 100, 3);
        $largeur = round(cat_num('largeur') / 100, 3);
        $hauteur = round(cat_num('hauteur') / 100, 3);
        // Toiture : élément de la rubrique « Toitures » du catalogue enveloppe
        $toiture = (string) ($_POST['toiture'] ?? '');
        $toitureList = archicad_load_catalogue('toitures');
        if (!in_array($toiture, array_column($toitureList, 'id'), true)) {
            $toiture = (string) ($toitureList[0]['id'] ?? '');
        }
        // Forme de toiture : élément de la rubrique « Formes » (pente + pignon)
        $forme = (string) ($_POST['forme'] ?? '');
        $formesList = archicad_load_catalogue('formes');
        if (!in_array($forme, array_column($formesList, 'id'), true)) {
            $forme = (string) ($formesList[0]['id'] ?? '');
        }
        $formeItem = archicad_catalogue_find('formes', $forme);
        if ($nom === '' || $longueur <= 0 || $largeur <= 0 || $hauteur <= 0) {
            cat_redirect('modules', 'invalid');
        }
        $formePente = (int) round((float) ($formeItem['pente1'] ?? $formeItem['pente'] ?? 15));
        $formePignon = ($formeItem['geometrie'] ?? '') === 'deuxpentes';
        // Éléments d'enveloppe liés au modèle (ids du catalogue enveloppe,
        // à défaut le premier élément de chaque sous-catalogue)
        $envRef = function (string $posted, string $kind, array $list): string {
            $id = (string) $posted;
            if ($id === '' || archicad_catalogue_find($kind, $id) === null) {
                $id = (string) ($list[0]['id'] ?? '');
            }
            return $id;
        };
        $murId = $envRef((string) ($_POST['mur'] ?? ''), 'murs', archicad_load_catalogue('murs'));
        $plancherId = $envRef((string) ($_POST['plancher'] ?? ''), 'planchers', archicad_load_catalogue('planchers'));
        // Enveloppe liée au modèle : mur, plancher et toiture (la surface
        // extérieure est celle du mur choisi). Le tarif n'est plus saisi dans
        // le formulaire mais reste stocké en base (conservé si non posté).
        $fields = [
            'nom' => $nom,
            'longueur' => $longueur,
            'largeur' => $largeur,
            'hauteur' => $hauteur,
            'forme' => $forme,
            // pente / pignon dénormalisés depuis la forme (rétro-compatibilité)
            'pente' => $formePente,
            'pignon' => $formePignon,
            'toiture' => $toiture,
            'mur' => $murId,
            'plancher' => $plancherId,
        ];
        if (isset($_POST['tarif'])) {
            $fields['tarif'] = round(cat_num('tarif'), 2);
        }
        $item = archicad_catalogue_upsert('modules', $fields, $id !== '' ? $id : null);
        // Visuel du module (suppression demandée ou nouvel envoi)
        if (isset($_POST['remove_image']) && (string) ($item['image'] ?? '') !== '') {
            $fs = archicad_catalogue_image_fs_path((string) $item['image']);
            if ($fs !== null && is_file($fs)) {
                unlink($fs);
            }
            archicad_catalogue_upsert('modules', ['image' => ''], (string) $item['id']);
        }
        if (isset($_FILES['image']) && is_array($_FILES['image'])) {
            $stored = archicad_catalogue_store_image($_FILES['image'], (string) $item['id'], 'modules');
            if (!$stored['ok']) {
                cat_redirect('modules', 'image', (string) $stored['error']);
            }
            $old = (string) ($item['image'] ?? '');
            if ($old !== '' && $old !== $stored['webPath']) {
                $fs = archicad_catalogue_image_fs_path($old);
                if ($fs !== null && is_file($fs)) {
                    unlink($fs);
                }
            }
            archicad_catalogue_upsert('modules', ['image' => (string) $stored['webPath']], (string) $item['id']);
        }
        cat_redirect('modules', 'saved');
    }
    if ($action === 'delete_module') {
        archicad_catalogue_delete('modules', trim((string) ($_POST['id'] ?? '')));
        cat_redirect('modules', 'deleted');
    }

    // ----- Formes de toiture (géométrie + pente(s)) -----
    if ($action === 'save_forme') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $geometrie = (string) ($_POST['geometrie'] ?? 'monopente');
        if (!in_array($geometrie, array_column(archicad_catalogue_forme_geometries(), 'id'), true)) {
            $geometrie = 'monopente';
        }
        $pente = max(0, min(90, (int) round(cat_num('pente'))));
        $pente1 = max(0, min(90, (int) round(cat_num('pente1'))));
        $pente2 = max(0, min(90, (int) round(cat_num('pente2'))));
        // deux pentes : une pente par versant ; monopente / quatre pentes : une pente
        if ($geometrie === 'deuxpentes' ? ($pente1 <= 0 || $pente2 <= 0) : $pente <= 0) {
            cat_redirect('formes', 'invalid');
        }
        if ($nom === '') {
            cat_redirect('formes', 'invalid');
        }
        if ($geometrie === 'deuxpentes') {
            $pente = $pente1; // « pente » dénormalisée = pente du versant 1
        }
        $item = archicad_catalogue_upsert('formes', [
            'nom' => $nom,
            'geometrie' => $geometrie,
            'pente' => $pente,
            'pente1' => $pente1,
            'pente2' => $pente2,
            'pignon' => $geometrie === 'deuxpentes', // dénormalisé (deux pentes = pignon)
        ], $id !== '' ? $id : null);
        // Visuel (suppression demandée ou nouvel envoi)
        if (isset($_POST['remove_image']) && (string) ($item['image'] ?? '') !== '') {
            $fs = archicad_catalogue_image_fs_path((string) $item['image']);
            if ($fs !== null && is_file($fs)) {
                unlink($fs);
            }
            archicad_catalogue_upsert('formes', ['image' => ''], (string) $item['id']);
        }
        if (isset($_FILES['image']) && is_array($_FILES['image'])) {
            $stored = archicad_catalogue_store_image($_FILES['image'], (string) $item['id'], 'formes');
            if (!$stored['ok']) {
                cat_redirect('formes', 'image', (string) $stored['error']);
            }
            $old = (string) ($item['image'] ?? '');
            if ($old !== '' && $old !== $stored['webPath']) {
                $fs = archicad_catalogue_image_fs_path($old);
                if ($fs !== null && is_file($fs)) {
                    unlink($fs);
                }
            }
            archicad_catalogue_upsert('formes', ['image' => (string) $stored['webPath']], (string) $item['id']);
        }
        cat_redirect('formes', 'saved');
    }
    if ($action === 'delete_forme') {
        archicad_catalogue_delete('formes', trim((string) ($_POST['id'] ?? '')));
        cat_redirect('formes', 'deleted');
    }

    // ----- Charpentes (visuel, nom, épaisseur, couleur, coût unitaire, référence Archicad) -----
    if ($action === 'save_charpente') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        // Saisie en cm (précision mm) — stockage en m
        $epaisseur = round(cat_num('epaisseur') / 100, 3);
        $tarif = round(cat_num('tarif'), 2);
        if ($nom === '' || $epaisseur <= 0) {
            cat_redirect('charpentes', 'invalid');
        }
        $item = archicad_catalogue_upsert('charpentes', [
            'nom' => $nom,
            'epaisseur' => $epaisseur,
            'rgb' => [
                max(0, min(255, (int) round(cat_num('r')))),
                max(0, min(255, (int) round(cat_num('g')))),
                max(0, min(255, (int) round(cat_num('b')))),
            ],
            'favori' => trim((string) ($_POST['favori'] ?? '')),
            'tarif' => $tarif,
        ], $id !== '' ? $id : null);
        // Visuel (suppression demandée ou nouvel envoi)
        if (isset($_POST['remove_image']) && (string) ($item['image'] ?? '') !== '') {
            $fs = archicad_catalogue_image_fs_path((string) $item['image']);
            if ($fs !== null && is_file($fs)) {
                unlink($fs);
            }
            archicad_catalogue_upsert('charpentes', ['image' => ''], (string) $item['id']);
        }
        if (isset($_FILES['image']) && is_array($_FILES['image'])) {
            $stored = archicad_catalogue_store_image($_FILES['image'], (string) $item['id'], 'charpentes');
            if (!$stored['ok']) {
                cat_redirect('charpentes', 'image', (string) $stored['error']);
            }
            $old = (string) ($item['image'] ?? '');
            if ($old !== '' && $old !== $stored['webPath']) {
                $fs = archicad_catalogue_image_fs_path($old);
                if ($fs !== null && is_file($fs)) {
                    unlink($fs);
                }
            }
            archicad_catalogue_upsert('charpentes', ['image' => (string) $stored['webPath']], (string) $item['id']);
        }
        cat_redirect('charpentes', 'saved');
    }
    if ($action === 'delete_charpente') {
        archicad_catalogue_delete('charpentes', trim((string) ($_POST['id'] ?? '')));
        cat_redirect('charpentes', 'deleted');
    }

    // ----- Enveloppe (murs, planchers, surfaces, toitures) -----
    if ($action === 'save_enveloppe') {
        $sous = (string) ($_POST['sous'] ?? '');
        if (!in_array($sous, ['murs', 'planchers', 'surfaces', 'toitures'], true)) {
            cat_redirect('modules', 'invalid');
        }
        $id = trim((string) ($_POST['id'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $tarif = round(cat_num('tarif'), 2);
        if ($nom === '') {
            cat_redirect($sous, 'invalid');
        }
        $fields = ['nom' => $nom, 'tarif' => $tarif];
        // Référence de surface (catalogue surfaces.json)
        $facadeList = archicad_load_catalogue('surfaces');
        $surfaceRef = function (string $posted) use ($facadeList): string {
            $sid = (string) $posted;
            if ($sid === '' || archicad_catalogue_find('surfaces', $sid) === null) {
                $sid = (string) ($facadeList[0]['id'] ?? '');
            }
            return $sid;
        };
        if ($sous === 'murs' || $sous === 'toitures') {
            // référence Archicad ('' = aucun) — les surfaces n'en ont pas
            $fields['favori'] = trim((string) ($_POST['favori'] ?? ''));
            $fields['surface'] = $surfaceRef((string) ($_POST['surface'] ?? ''));
            // Saisie en cm (précision mm) — stockage en m
            $epaisseur = round(cat_num('epaisseur') / 100, 3);
            if ($epaisseur <= 0) {
                cat_redirect($sous, 'invalid');
            }
            $fields['epaisseur'] = $epaisseur;
            $fields['rgb'] = [
                max(0, min(255, (int) round(cat_num('r')))),
                max(0, min(255, (int) round(cat_num('g')))),
                max(0, min(255, (int) round(cat_num('b')))),
            ];
        }
        if ($sous === 'planchers') {
            $fields['favori'] = trim((string) ($_POST['favori'] ?? ''));
            // Saisie en cm (précision mm) — stockage en m
            $epaisseur = round(cat_num('epaisseur') / 100, 3);
            if ($epaisseur <= 0) {
                cat_redirect($sous, 'invalid');
            }
            $fields['epaisseur'] = $epaisseur;
        }
        if ($sous === 'toitures') {
            $type = (string) ($_POST['type'] ?? 'monopente');
            if (!in_array($type, array_column(archicad_catalogue_toiture_types(), 'id'), true)) {
                $type = 'monopente';
            }
            $fields['type'] = $type;
        }
        $item = archicad_catalogue_upsert($sous, $fields, $id !== '' ? $id : null);
        // Chaque rubrique a son propre onglet : on y retourne après sauvegarde
        $destSection = $sous;
        // Visuel téléversé (suppression demandée ou nouvel envoi) — toutes les
        // rubriques du configurateur ont un visuel (sous-dossier = rubrique)
        if (in_array($sous, ['murs', 'planchers', 'surfaces', 'toitures'], true)) {
            if (isset($_POST['remove_image']) && (string) ($item['image'] ?? '') !== '') {
                $fs = archicad_catalogue_image_fs_path((string) $item['image']);
                if ($fs !== null && is_file($fs)) {
                    unlink($fs);
                }
                archicad_catalogue_upsert($sous, ['image' => ''], (string) $item['id']);
                $item = archicad_catalogue_find($sous, (string) $item['id']) ?? $item;
            }
            if (isset($_FILES['image']) && is_array($_FILES['image'])) {
                $stored = archicad_catalogue_store_image($_FILES['image'], (string) $item['id'], $sous);
                if (!$stored['ok']) {
                    cat_redirect($destSection, 'image', (string) $stored['error']);
                }
                $old = (string) ($item['image'] ?? '');
                if ($old !== '' && $old !== $stored['webPath']) {
                    $fs = archicad_catalogue_image_fs_path($old);
                    if ($fs !== null && is_file($fs)) {
                        unlink($fs);
                    }
                }
                archicad_catalogue_upsert($sous, ['image' => (string) $stored['webPath']], (string) $item['id']);
            }
        }
        cat_redirect($destSection, 'saved');
    }
    if ($action === 'delete_enveloppe') {
        $sous = (string) ($_POST['sous'] ?? '');
        if (!in_array($sous, ['murs', 'planchers', 'surfaces', 'toitures'], true)) {
            cat_redirect('modules', 'invalid');
        }
        archicad_catalogue_delete($sous, trim((string) ($_POST['id'] ?? '')));
        cat_redirect($sous, 'deleted');
    }

    cat_redirect($section, 'invalid');
}

// ----- Affichage -----

$flash = (string) ($_GET['flash'] ?? '');
$flashErr = (string) ($_GET['err'] ?? '');
$flashMessage = null;
if ($flash === 'saved') {
    $flashMessage = ['success', 'Élément enregistré dans le catalogue.'];
} elseif ($flash === 'deleted') {
    $flashMessage = ['success', 'Élément supprimé du catalogue.'];
} elseif ($flash === 'invalid') {
    $flashMessage = ['error', 'Vérifiez les champs obligatoires (nom renseigné, dimensions strictement positives).'];
} elseif ($flash === 'csrf') {
    $flashMessage = ['error', 'Jeton de sécurité invalide.'];
} elseif ($flash === 'image') {
    $flashMessage = ['error', $flashErr !== '' ? $flashErr : 'Image non enregistrée.'];
}

$csrf = archicad_admin_csrf_token();
$menuiseries = archicad_load_catalogue('menuiseries');
$modules = archicad_load_catalogue('modules');
$formes = archicad_load_catalogue('formes');
$charpentes = archicad_load_catalogue('charpentes');
$murs = archicad_load_catalogue('murs');
$planchers = archicad_load_catalogue('planchers');
$facades = archicad_load_catalogue('surfaces');
$toitures = archicad_catalogue_toitures();
$toitureTypes = archicad_catalogue_toiture_types();

$typeLabels = ['baie' => 'Baie vitrée', 'fenetre' => 'Fenêtre', 'porte' => 'Porte'];
$toitureLabels = array_column($toitures, 'nom', 'id');
$toitureTypeOf = array_column($toitures, 'type', 'id');
$toitureTypeLabels = array_column($toitureTypes, 'nom', 'id');
$formeNames = array_column($formes, 'nom', 'id');
$formeGeometries = archicad_catalogue_forme_geometries();
$formeGeoLabels = array_column($formeGeometries, 'nom', 'id');
$formeById = array_column($formes, null, 'id');
$murNames = array_column($murs, 'nom', 'id');
$plancherNames = array_column($planchers, 'nom', 'id');
$facadeNames = array_column($facades, 'nom', 'id');

$new = isset($_GET['new']);
$editMenuiserie = null;
$editModule = null;
$editForme = null;
$editCharpente = null;
$editEnv = ['sous' => '', 'item' => null];
if (($editId = trim((string) ($_GET['edit'] ?? ''))) !== '') {
    if ($section === 'menuiseries') {
        $editMenuiserie = archicad_catalogue_find('menuiseries', $editId);
    } elseif ($section === 'modules') {
        $editModule = archicad_catalogue_find('modules', $editId);
    } elseif ($section === 'formes') {
        $editForme = archicad_catalogue_find('formes', $editId);
    } elseif ($section === 'charpentes') {
        $editCharpente = archicad_catalogue_find('charpentes', $editId);
    } elseif (in_array($section, ['murs', 'planchers', 'toitures', 'surfaces'], true)) {
        $sous = $section;
        $editEnv = ['sous' => $sous, 'item' => archicad_catalogue_find($sous, $editId)];
    }
}
// Rubrique active (édition ou ajout) — ouvre la boîte correspondante
$envSous = $editEnv['sous'];
if ($envSous === '' && $new && in_array($section, ['murs', 'planchers', 'toitures', 'surfaces'], true)) {
    $envSous = $section;
}

include __DIR__ . '/../includes/admin-head.php';
?>

    <?php if ($flashMessage): ?>
      <p class="admin-flash <?= $flashMessage[0] ?>"><?= htmlspecialchars($flashMessage[1]) ?></p>
    <?php endif; ?>

      <nav class="admin-tabs">
        <a href="configurateur.php?section=modules"<?= $section === 'modules' ? ' class="active"' : '' ?>>Modules</a>
        <a href="configurateur.php?section=formes"<?= $section === 'formes' ? ' class="active"' : '' ?>>Formes</a>
        <a href="configurateur.php?section=charpentes"<?= $section === 'charpentes' ? ' class="active"' : '' ?>>Charpentes</a>
        <a href="configurateur.php?section=murs"<?= $section === 'murs' ? ' class="active"' : '' ?>>Murs</a>
        <a href="configurateur.php?section=planchers"<?= $section === 'planchers' ? ' class="active"' : '' ?>>Planchers</a>
        <a href="configurateur.php?section=toitures"<?= $section === 'toitures' ? ' class="active"' : '' ?>>Toitures</a>
        <a href="configurateur.php?section=menuiseries"<?= $section === 'menuiseries' ? ' class="active"' : '' ?>>Ouvrants</a>
        <a href="configurateur.php?section=surfaces"<?= $section === 'surfaces' ? ' class="active"' : '' ?>>Surfaces</a>
      </nav>

    <?php if ($section === 'menuiseries'): ?>
    <div class="admin-card">
      <h2>Ouvrants (<?= count($menuiseries) ?>) <?= cat_btn_add('configurateur.php?section=menuiseries&new=1', 'Ajouter un ouvrant') ?></h2>
      <p class="admin-hint">Le client choisit un ouvrant (baie vitrée, fenêtre, porte) dans ce catalogue pour l'intégrer à un mur (onglet « Murs &amp; ouvertures » du configurateur) ; seule la position sur le mur reste à définir. Le visuel est affiché dans le configurateur.</p>
      <?php if ($menuiseries === []): ?>
        <p class="admin-hint">Aucune menuiserie dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Visuel</th>
              <th>Nom</th>
              <th>Type</th>
              <th>Dimensions</th>
              <th>Référence Archicad</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($menuiseries as $m): ?>
              <tr>
                <td>
                  <?php if (($m['image'] ?? '') !== ''): ?>
                    <img src="../<?= ltrim(htmlspecialchars((string) $m['image']), '/') ?>" alt="" class="admin-catalogue-thumb">
                  <?php else: ?>
                    <span class="admin-no-image">—</span>
                  <?php endif; ?>
                </td>
                <td><strong><?= htmlspecialchars((string) $m['nom']) ?></strong></td>
                <td><?= htmlspecialchars($typeLabels[$m['type'] ?? ''] ?? (string) ($m['type'] ?? '')) ?></td>
                <td><?= cat_cm((float) $m['largeur']) ?> × <?= cat_cm((float) $m['hauteur']) ?></td>
                <td><?= ($m['favori'] ?? '') !== '' ? htmlspecialchars((string) $m['favori']) : '—' ?></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=menuiseries&edit=' . urlencode((string) $m['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=menuiseries', $csrf, 'delete_menuiserie', (string) $m['id'], 'Supprimer cette menuiserie du catalogue ?') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php if ($editMenuiserie || $new): modal_open('configurateur.php?section=menuiseries'); ?>
    <div class="admin-card">
      <h2><?= $editMenuiserie ? 'Modifier : ' . htmlspecialchars((string) $editMenuiserie['nom']) : 'Ajouter un ouvrant' ?></h2>
      <form method="post" action="configurateur.php?section=menuiseries" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_menuiserie">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editMenuiserie['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editMenuiserie && ($editMenuiserie['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editMenuiserie['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="m-image">Visuel (image)</label>
          <input type="file" id="m-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="m-nom">Nom</label>
            <input type="text" id="m-nom" name="nom" required value="<?= htmlspecialchars((string) ($editMenuiserie['nom'] ?? '')) ?>" placeholder="Baie vitrée 1,20 × 2,15 m">
          </div>
          <div class="admin-form-row">
            <label for="m-type">Type</label>
            <select id="m-type" name="type">
              <?php foreach ($typeLabels as $value => $label): ?>
                <option value="<?= $value ?>"<?= (($editMenuiserie['type'] ?? 'baie') === $value) ? ' selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="m-largeur">Largeur (cm)</label>
            <input type="number" id="m-largeur" name="largeur" step="0.1" min="10" required value="<?= htmlspecialchars($editMenuiserie ? cat_cm_input((float) $editMenuiserie['largeur']) : '120') ?>">
          </div>
          <div class="admin-form-row">
            <label for="m-hauteur">Hauteur (cm)</label>
            <input type="number" id="m-hauteur" name="hauteur" step="0.1" min="10" required value="<?= htmlspecialchars($editMenuiserie ? cat_cm_input((float) $editMenuiserie['hauteur']) : '215') ?>">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="m-tarif">Coût unitaire (€)</label>
            <input type="number" id="m-tarif" name="tarif" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editMenuiserie['tarif'] ?? '0')) ?>">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="m-favori">Référence Archicad</label>
            <input type="text" id="m-favori" name="favori" value="<?= htmlspecialchars((string) ($editMenuiserie['favori'] ?? '')) ?>" placeholder="Nom de la référence Archicad (ex. Baracca — Porte), vide = aucune">
          </div>
        </div>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=menuiseries" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php elseif ($section === 'modules'): ?>
    <div class="admin-card">
      <h2>Modules (<?= count($modules) ?>) <?= cat_btn_add('configurateur.php?section=modules&new=1', 'Ajouter un module') ?></h2>
      <p class="admin-hint">Le client choisit un modèle dans ce catalogue (onglet « Module » du configurateur) : longueur, largeur, hauteur sous plafond, forme de toiture (géométrie, pentes) <strong>et éléments d'enveloppe liés (mur, plancher, toiture)</strong> sont définis par le modèle — le client ne peut pas les modifier, ils lui sont présentés en lecture seule. La surface extérieure est celle du mur choisi.</p>
      <?php if ($modules === []): ?>
        <p class="admin-hint">Aucun modèle de module dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Visuel</th>
              <th>Nom</th>
              <th>Géométrie</th>
              <th>Forme</th>
              <th>Toiture</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($modules as $mod): $fo = $formeById[$mod['forme'] ?? ''] ?? null; ?>
              <tr>
                <td><?= cat_thumb($mod) ?></td>
                <td><strong><?= htmlspecialchars((string) $mod['nom']) ?></strong></td>
                <td><?= cat_cm((float) $mod['longueur']) ?> × <?= cat_cm((float) $mod['largeur']) ?> — h <?= cat_cm((float) $mod['hauteur']) ?></td>
                <td><?= htmlspecialchars($formeNames[$mod['forme'] ?? ''] ?? (string) ($mod['forme'] ?? '—')) ?><?= $fo ? ' — ' . htmlspecialchars($formeGeoLabels[$fo['geometrie'] ?? ''] ?? (string) ($fo['geometrie'] ?? '')) . ' — ' . (($fo['geometrie'] ?? '') === 'deuxpentes' ? (int) ($fo['pente1'] ?? 0) . '° / ' . (int) ($fo['pente2'] ?? 0) . '°' : (int) ($fo['pente'] ?? 0) . '°') : '' ?></td>
                <td><?= htmlspecialchars($toitureLabels[$mod['toiture'] ?? ''] ?? (string) ($mod['toiture'] ?? '')) ?></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=modules&edit=' . urlencode((string) $mod['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=modules', $csrf, 'delete_module', (string) $mod['id'], 'Supprimer ce modèle de module du catalogue ?') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php if ($editModule || $new): modal_open('configurateur.php?section=modules'); ?>
    <div class="admin-card">
      <h2><?= $editModule ? 'Modifier : ' . htmlspecialchars((string) $editModule['nom']) : 'Ajouter un module' ?></h2>
      <form method="post" action="configurateur.php?section=modules" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_module">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editModule['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editModule && ($editModule['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editModule['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="mod-image">Visuel (image)</label>
          <input type="file" id="mod-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mod-nom">Nom</label>
            <input type="text" id="mod-nom" name="nom" required value="<?= htmlspecialchars((string) ($editModule['nom'] ?? '')) ?>" placeholder="Module 6,00 × 3,00 m — h 2,80 m">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mod-longueur">Longueur (cm)</label>
            <input type="number" id="mod-longueur" name="longueur" step="0.1" min="50" required value="<?= htmlspecialchars($editModule ? cat_cm_input((float) $editModule['longueur']) : '600') ?>">
          </div>
          <div class="admin-form-row">
            <label for="mod-largeur">Largeur (cm)</label>
            <input type="number" id="mod-largeur" name="largeur" step="0.1" min="50" required value="<?= htmlspecialchars($editModule ? cat_cm_input((float) $editModule['largeur']) : '300') ?>">
          </div>
          <div class="admin-form-row">
            <label for="mod-hauteur">Hauteur sous plafond (cm)</label>
            <input type="number" id="mod-hauteur" name="hauteur" step="0.1" min="100" required value="<?= htmlspecialchars($editModule ? cat_cm_input((float) $editModule['hauteur']) : '280') ?>">
          </div>
        </div>
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mod-forme">Forme de toiture (géométrie, pentes — onglet « Formes »)</label>
            <select id="mod-forme" name="forme">
              <?php foreach ($formes as $fo): ?>
                <option value="<?= htmlspecialchars((string) $fo['id']) ?>"<?= (($editModule['forme'] ?? ($formes[0]['id'] ?? '')) === $fo['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $fo['nom']) ?> — <?= htmlspecialchars($formeGeoLabels[$fo['geometrie'] ?? ''] ?? (string) ($fo['geometrie'] ?? '')) ?> — <?= ($fo['geometrie'] ?? '') === 'deuxpentes' ? (int) ($fo['pente1'] ?? 0) . '° / ' . (int) ($fo['pente2'] ?? 0) . '°' : (int) ($fo['pente'] ?? 0) . '°' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <hr class="admin-sep">
        <p class="admin-sep-label">Enveloppe liée au modèle</p>
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mod-mur">Mur porteur</label>
            <select id="mod-mur" name="mur">
              <?php foreach ($murs as $mur): ?>
                <option value="<?= htmlspecialchars((string) $mur['id']) ?>"<?= (($editModule['mur'] ?? ($murs[0]['id'] ?? '')) === $mur['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $mur['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-form-row">
            <label for="mod-plancher">Plancher</label>
            <select id="mod-plancher" name="plancher">
              <?php foreach ($planchers as $p): ?>
                <option value="<?= htmlspecialchars((string) $p['id']) ?>"<?= (($editModule['plancher'] ?? ($planchers[0]['id'] ?? '')) === $p['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $p['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-form-row">
            <label for="mod-toiture">Toiture</label>
            <select id="mod-toiture" name="toiture">
              <?php foreach ($toitures as $t): ?>
                <option value="<?= htmlspecialchars((string) $t['id']) ?>"<?= (($editModule['toiture'] ?? ($toitures[0]['id'] ?? '')) === $t['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $t['nom']) ?><?= !empty($t['epaisseur']) ? ' — ép. ' . cat_cm((float) $t['epaisseur']) : '' ?><?= ($t['favori'] ?? '') !== '' ? ' — réf. « ' . htmlspecialchars((string) $t['favori']) . ' »' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=modules" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php elseif ($section === 'formes'): ?>
    <div class="admin-card">
      <h2>Formes de toiture (<?= count($formes) ?>) <?= cat_btn_add('configurateur.php?section=formes&new=1', 'Ajouter une forme') ?></h2>
      <p class="admin-hint">Formes de toiture proposées dans les modules : la géométrie (monopente, deux pentes = pignon, quatre pentes = croupe) et la pente de chaque versant sont définies ici, puis référencées par chaque module. Le visuel est affiché dans le configurateur.</p>
      <?php if ($formes === []): ?>
        <p class="admin-hint">Aucune forme de toiture dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Visuel</th><th>Nom</th><th>Géométrie</th><th>Pente(s)</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($formes as $fo): ?>
              <tr>
                <td><?= cat_thumb($fo) ?></td>
                <td><strong><?= htmlspecialchars((string) $fo['nom']) ?></strong></td>
                <td><?= htmlspecialchars($formeGeoLabels[$fo['geometrie'] ?? ''] ?? (string) ($fo['geometrie'] ?? '')) ?></td>
                <td><?= ($fo['geometrie'] ?? '') === 'deuxpentes' ? (int) ($fo['pente1'] ?? 0) . '° / ' . (int) ($fo['pente2'] ?? 0) . '° (par versant)' : (int) ($fo['pente'] ?? 0) . '°' ?></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=formes&edit=' . urlencode((string) $fo['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=formes', $csrf, 'delete_forme', (string) $fo['id'], 'Supprimer cette forme de toiture du catalogue ?') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php if ($editForme || $new): modal_open('configurateur.php?section=formes'); ?>
    <div class="admin-card">
      <h2><?= $editForme ? 'Modifier : ' . htmlspecialchars((string) $editForme['nom']) : 'Ajouter une forme de toiture' ?></h2>
      <form method="post" action="configurateur.php?section=formes" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_forme">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editForme['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editForme && ($editForme['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editForme['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="fo-image">Visuel (image)</label>
          <input type="file" id="fo-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="fo-nom">Nom</label>
            <input type="text" id="fo-nom" name="nom" required value="<?= htmlspecialchars((string) ($editForme['nom'] ?? '')) ?>" placeholder="Deux pentes 45° — pignon">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="fo-geometrie">Géométrie</label>
            <select id="fo-geometrie" name="geometrie">
              <?php foreach ($formeGeometries as $g): ?>
                <option value="<?= htmlspecialchars((string) $g['id']) ?>"<?= (($editForme['geometrie'] ?? 'monopente') === $g['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $g['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-row" id="fo-row-pente">
          <div class="admin-form-row">
            <label for="fo-pente">Pente (°) — monopente / quatre pentes</label>
            <input type="number" id="fo-pente" name="pente" step="1" min="0" max="90" required value="<?= htmlspecialchars((string) ($editForme['pente'] ?? '30')) ?>">
          </div>
        </div>
        <div class="modal-row" id="fo-row-pentes">
          <div class="admin-form-row">
            <label for="fo-pente1">Pente versant 1 (°)</label>
            <input type="number" id="fo-pente1" name="pente1" step="1" min="1" max="90" value="<?= htmlspecialchars((string) ($editForme['pente1'] ?? $editForme['pente'] ?? '30')) ?>">
          </div>
          <div class="admin-form-row">
            <label for="fo-pente2">Pente versant 2 (°)</label>
            <input type="number" id="fo-pente2" name="pente2" step="1" min="1" max="90" value="<?= htmlspecialchars((string) ($editForme['pente2'] ?? $editForme['pente'] ?? '30')) ?>">
          </div>
        </div>
        <script>
        (function () {
          var sel = document.getElementById('fo-geometrie');
          if (!sel) return;
          var toggle = function () {
            var deux = sel.value === 'deuxpentes';
            document.getElementById('fo-row-pente').style.display = deux ? 'none' : '';
            document.getElementById('fo-row-pentes').style.display = deux ? '' : 'none';
            document.getElementById('fo-pente').required = !deux;
          };
          sel.addEventListener('change', toggle);
          toggle();
        })();
        </script>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=formes" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php elseif ($section === 'charpentes'): ?>
    <div class="admin-card">
      <h2>Charpentes (<?= count($charpentes) ?>) <?= cat_btn_add('configurateur.php?section=charpentes&new=1', 'Ajouter une charpente') ?></h2>
      <p class="admin-hint">Charpentes (à l'identique des ouvrants) : visuel, nom, épaisseur, couleur, coût unitaire et référence Archicad.</p>
      <?php if ($charpentes === []): ?>
        <p class="admin-hint">Aucune charpente dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Visuel</th><th>Nom</th><th>Épaisseur</th><th>Couleur</th><th>Référence Archicad</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($charpentes as $ch): $rgbC = $ch['rgb'] ?? [140, 107, 89]; ?>
              <tr>
                <td><?= cat_thumb($ch) ?></td>
                <td><strong><?= htmlspecialchars((string) $ch['nom']) ?></strong></td>
                <td><?= cat_cm((float) ($ch['epaisseur'] ?? 0.20)) ?></td>
                <td><span class="admin-color-swatch" style="background-color: <?= cat_hex($rgbC) ?>;"></span> <?= cat_hex($rgbC) ?></td>
                <td><?= ($ch['favori'] ?? '') !== '' ? htmlspecialchars((string) $ch['favori']) : '—' ?></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=charpentes&edit=' . urlencode((string) $ch['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=charpentes', $csrf, 'delete_charpente', (string) $ch['id'], 'Supprimer cette charpente du catalogue ?') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php if ($editCharpente || $new): modal_open('configurateur.php?section=charpentes'); ?>
    <div class="admin-card">
      <h2><?= $editCharpente ? 'Modifier : ' . htmlspecialchars((string) $editCharpente['nom']) : 'Ajouter une charpente' ?></h2>
      <form method="post" action="configurateur.php?section=charpentes" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_charpente">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editCharpente['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editCharpente && ($editCharpente['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editCharpente['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="ch-image">Visuel (image)</label>
          <input type="file" id="ch-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="ch-nom">Nom</label>
            <input type="text" id="ch-nom" name="nom" required value="<?= htmlspecialchars((string) ($editCharpente['nom'] ?? '')) ?>" placeholder="Charpente traditionnelle">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="ch-epaisseur">Épaisseur (cm)</label>
            <input type="number" id="ch-epaisseur" name="epaisseur" step="0.1" min="1" required value="<?= htmlspecialchars($editCharpente ? cat_cm_input((float) ($editCharpente['epaisseur'] ?? 0.20)) : '20') ?>">
          </div>
        </div>
        <div class="modal-row">
          <div class="admin-form-row">
            <?php $rgbCh = $editCharpente['rgb'] ?? [140, 107, 89]; ?>
            <label>Couleur (aperçu, affichée en hex <?= cat_hex($rgbCh) ?>)</label>
            <div class="admin-color-inputs">
              <input type="number" name="r" min="0" max="255" value="<?= (int) ($rgbCh[0] ?? 0) ?>" aria-label="Rouge">
              <input type="number" name="g" min="0" max="255" value="<?= (int) ($rgbCh[1] ?? 0) ?>" aria-label="Vert">
              <input type="number" name="b" min="0" max="255" value="<?= (int) ($rgbCh[2] ?? 0) ?>" aria-label="Bleu">
              <span class="admin-color-swatch" style="background-color: <?= cat_hex($rgbCh) ?>;"></span>
            </div>
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="ch-tarif">Coût unitaire (€)</label>
            <input type="number" id="ch-tarif" name="tarif" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editCharpente['tarif'] ?? '0')) ?>">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="ch-favori">Référence Archicad</label>
            <input type="text" id="ch-favori" name="favori" value="<?= htmlspecialchars((string) ($editCharpente['favori'] ?? '')) ?>" placeholder="Nom de la référence Archicad (ex. Baracca — Charpente), vide = aucune">
          </div>
        </div>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=charpentes" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php elseif ($section === 'surfaces'): ?>
    <div class="admin-card">
      <h2>Surfaces (<?= count($facades) ?>) <?= cat_btn_add('configurateur.php?section=surfaces&new=1', 'Ajouter une surface') ?></h2>
      <p class="admin-hint">Surfaces d'habillage extérieur choisies pour les murs et les toitures ; le visuel téléversé est utilisé dans les élévations du configurateur. Pas de référence Archicad pour cette rubrique.</p>
      <?php if ($facades === []): ?>
        <p class="admin-hint">Aucune surface dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Visuel</th><th>Nom</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($facades as $f): ?>
              <tr>
                <td>
                  <?php if (($f['image'] ?? '') !== ''): ?>
                    <img src="../<?= ltrim(htmlspecialchars((string) $f['image']), '/') ?>" alt="" class="admin-catalogue-thumb">
                  <?php else: ?>
                    <span class="admin-no-image">—</span>
                  <?php endif; ?>
                </td>
                <td><strong><?= htmlspecialchars((string) $f['nom']) ?></strong></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=surfaces&edit=' . urlencode((string) $f['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=surfaces', $csrf, 'delete_enveloppe', (string) $f['id'], 'Supprimer cette surface du catalogue ?', '<input type="hidden" name="sous" value="surfaces">') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php if ($envSous === 'surfaces' && ($editEnv['item'] || $new)): modal_open('configurateur.php?section=surfaces'); ?>
    <div class="admin-card">
      <h3><?= $editEnv['item'] ? 'Modifier : ' . htmlspecialchars((string) $editEnv['item']['nom']) : 'Ajouter une surface' ?></h3>
      <form method="post" action="configurateur.php?section=surfaces" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="surfaces">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editEnv['item'] && ($editEnv['item']['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editEnv['item']['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="fa-image">Visuel (image)</label>
          <input type="file" id="fa-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max. Utilisé dans les élévations du configurateur.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="fa-nom">Nom</label>
            <input type="text" id="fa-nom" name="nom" required value="<?= cat_env_field($editEnv, 'surfaces', 'nom') ?>" placeholder="Bardage bois">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="fa-tarif">Coût au m² (€/m²)</label>
            <input type="number" id="fa-tarif" name="tarif" step="0.01" min="0" value="<?= cat_env_field($editEnv, 'surfaces', 'tarif', '0') ?>">
          </div>
        </div>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=surfaces" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php elseif (in_array($section, ['murs', 'planchers', 'toitures'], true)): ?>
    <div class="admin-card">
      <?php if ($section === 'murs'): ?>
      <h2>Murs porteurs (<?= count($murs) ?>) <?= cat_btn_add('configurateur.php?section=murs&new=1', 'Ajouter un type de mur') ?></h2>
      <p class="admin-hint">Types de mur porteur : épaisseur, surface (visuel utilisé en élévation), couleur, référence Archicad, coût au m² — liés aux modules.</p>
      <?php if ($murs === []): ?>
        <p class="admin-hint">Aucun type de mur dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Visuel</th><th>Nom</th><th>Épaisseur</th><th>Couleur</th><th>Référence Archicad</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($murs as $mur): $rgb = $mur['rgb'] ?? [158, 158, 158]; ?>
              <tr>
                <td><?= cat_thumb($mur) ?></td>
                <td><strong><?= htmlspecialchars((string) $mur['nom']) ?></strong></td>
                <td><?= cat_cm((float) ($mur['epaisseur'] ?? 0.20)) ?></td>
                <td><span class="admin-color-swatch" style="background-color: <?= cat_hex($rgb) ?>;"></span> <?= cat_hex($rgb) ?></td>
                <td><?= ($mur['favori'] ?? '') !== '' ? htmlspecialchars((string) $mur['favori']) : '—' ?></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=murs&edit=' . urlencode((string) $mur['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=murs', $csrf, 'delete_enveloppe', (string) $mur['id'], 'Supprimer ce type de mur du catalogue ?', '<input type="hidden" name="sous" value="murs">') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <?php elseif ($section === 'planchers'): ?>
      <h2>Planchers (<?= count($planchers) ?>) <?= cat_btn_add('configurateur.php?section=planchers&new=1', 'Ajouter un plancher') ?></h2>
      <p class="admin-hint">Types de plancher : épaisseur (dessinée en élévation, +21,7 cm au-delà de la ligne directrice des murs), référence Archicad, coût au m² — liés aux modules.</p>
      <?php if ($planchers === []): ?>
        <p class="admin-hint">Aucun plancher dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Visuel</th><th>Nom</th><th>Épaisseur</th><th>Référence Archicad</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($planchers as $p): ?>
              <tr>
                <td><?= cat_thumb($p) ?></td>
                <td><strong><?= htmlspecialchars((string) $p['nom']) ?></strong></td>
                <td><?= cat_cm((float) ($p['epaisseur'] ?? 0.20)) ?></td>
                <td><?= ($p['favori'] ?? '') !== '' ? htmlspecialchars((string) $p['favori']) : '—' ?></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=planchers&edit=' . urlencode((string) $p['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=planchers', $csrf, 'delete_enveloppe', (string) $p['id'], 'Supprimer ce plancher du catalogue ?', '<input type="hidden" name="sous" value="planchers">') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <?php else: ?>
      <h2>Toitures (<?= count($toitures) ?>) <?= cat_btn_add('configurateur.php?section=toitures&new=1', 'Ajouter un type de toiture') ?></h2>
      <p class="admin-hint">Types de toiture : géométrie, épaisseur (bande au-dessus de la ligne directrice en élévation), surface, couleur, référence Archicad, coût au m² — liés aux modules.</p>
      <?php if ($toitures === []): ?>
        <p class="admin-hint">Aucun type de toiture dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Visuel</th><th>Nom</th><th>Géométrie</th><th>Épaisseur</th><th>Couleur</th><th>Référence Archicad</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($toitures as $t): $rgbT = $t['rgb'] ?? [120, 113, 108]; ?>
              <tr>
                <td><?= cat_thumb($t) ?></td>
                <td><strong><?= htmlspecialchars((string) $t['nom']) ?></strong></td>
                <td><?= htmlspecialchars($toitureTypeLabels[$t['type'] ?? ''] ?? (string) ($t['type'] ?? '')) ?></td>
                <td><?= cat_cm((float) ($t['epaisseur'] ?? 0.30)) ?></td>
                <td><span class="admin-color-swatch" style="background-color: <?= cat_hex($rgbT) ?>;"></span> <?= cat_hex($rgbT) ?></td>
                <td><?= ($t['favori'] ?? '') !== '' ? htmlspecialchars((string) $t['favori']) : '—' ?></td>
                <td class="admin-actions">
                  <?= cat_action_edit('configurateur.php?section=toitures&edit=' . urlencode((string) $t['id'])) ?>
                  <?= cat_action_delete('configurateur.php?section=toitures', $csrf, 'delete_enveloppe', (string) $t['id'], 'Supprimer ce type de toiture du catalogue ?', '<input type="hidden" name="sous" value="toitures">') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($envSous === 'murs' && ($editEnv['item'] || $new)): modal_open('configurateur.php?section=' . $section); ?>
    <div class="admin-card">
      <h3><?= $editEnv['item'] ? 'Modifier : ' . htmlspecialchars((string) $editEnv['item']['nom']) : 'Ajouter un type de mur' ?></h3>
      <form method="post" action="configurateur.php?section=<?= $section ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="murs">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editEnv['item'] && ($editEnv['item']['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editEnv['item']['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="mur-image">Visuel (image)</label>
          <input type="file" id="mur-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mur-nom">Nom</label>
            <input type="text" id="mur-nom" name="nom" required value="<?= cat_env_field($editEnv, 'murs', 'nom') ?>" placeholder="Mur CLT 140 mm">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mur-epaisseur">Épaisseur (cm)</label>
            <input type="number" id="mur-epaisseur" name="epaisseur" step="0.1" min="1" required value="<?= htmlspecialchars($editEnv['item'] ? cat_cm_input((float) ($editEnv['item']['epaisseur'] ?? 0.14)) : '14') ?>">
          </div>
        </div>
        <div class="modal-row">
          <div class="admin-form-row">
            <?php $rgbEdit = $editEnv['item']['rgb'] ?? [158, 158, 158]; ?>
            <label>Couleur (aperçu plan, affichée en hex <?= cat_hex($rgbEdit) ?>)</label>
            <div class="admin-color-inputs">
              <input type="number" name="r" min="0" max="255" value="<?= (int) ($rgbEdit[0] ?? 0) ?>" aria-label="Rouge">
              <input type="number" name="g" min="0" max="255" value="<?= (int) ($rgbEdit[1] ?? 0) ?>" aria-label="Vert">
              <input type="number" name="b" min="0" max="255" value="<?= (int) ($rgbEdit[2] ?? 0) ?>" aria-label="Bleu">
              <span class="admin-color-swatch" style="background-color: <?= cat_hex($rgbEdit) ?>;"></span>
            </div>
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mur-tarif">Coût au m² (€/m²)</label>
            <input type="number" id="mur-tarif" name="tarif" step="0.01" min="0" value="<?= cat_env_field($editEnv, 'murs', 'tarif', '0') ?>">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="mur-favori">Référence Archicad</label>
            <input type="text" id="mur-favori" name="favori" value="<?= cat_env_field($editEnv, 'murs', 'favori') ?>" placeholder="Nom de la référence Archicad (ex. Baracca — Mur CLT), vide = aucune">
          </div>
        </div>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=<?= $section ?>" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php if ($envSous === 'planchers' && ($editEnv['item'] || $new)): modal_open('configurateur.php?section=' . $section); ?>
    <div class="admin-card">
      <h3><?= $editEnv['item'] ? 'Modifier : ' . htmlspecialchars((string) $editEnv['item']['nom']) : 'Ajouter un plancher' ?></h3>
      <form method="post" action="configurateur.php?section=<?= $section ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="planchers">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editEnv['item'] && ($editEnv['item']['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editEnv['item']['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="pl-image">Visuel (image)</label>
          <input type="file" id="pl-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="pl-nom">Nom</label>
            <input type="text" id="pl-nom" name="nom" required value="<?= cat_env_field($editEnv, 'planchers', 'nom') ?>" placeholder="Dalle CLT + chape">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="pl-epaisseur">Épaisseur (cm)</label>
            <input type="number" id="pl-epaisseur" name="epaisseur" step="0.1" min="1" required value="<?= htmlspecialchars($editEnv['item'] ? cat_cm_input((float) ($editEnv['item']['epaisseur'] ?? 0.20)) : '20') ?>">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="pl-tarif">Coût au m² (€/m²)</label>
            <input type="number" id="pl-tarif" name="tarif" step="0.01" min="0" value="<?= cat_env_field($editEnv, 'planchers', 'tarif', '0') ?>">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="pl-favori">Référence Archicad</label>
            <input type="text" id="pl-favori" name="favori" value="<?= cat_env_field($editEnv, 'planchers', 'favori') ?>" placeholder="Nom de la référence Archicad (ex. Baracca — Dalle CLT), vide = aucune">
          </div>
        </div>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=<?= $section ?>" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php if ($envSous === 'toitures' && ($editEnv['item'] || $new)): modal_open('configurateur.php?section=' . $section); ?>
    <div class="admin-card">
      <h3><?= $editEnv['item'] ? 'Modifier : ' . htmlspecialchars((string) $editEnv['item']['nom']) : 'Ajouter un type de toiture' ?></h3>
      <form method="post" action="configurateur.php?section=<?= $section ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="toitures">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="modal-visual-row">
          <?php if ($editEnv['item'] && ($editEnv['item']['image'] ?? '') !== ''): ?>
            <img src="../<?= ltrim(htmlspecialchars((string) $editEnv['item']['image']), '/') ?>" alt="" class="modal-visual-img">
            <label class="admin-hint"><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel</label>
          <?php endif; ?>
          <label for="to-image">Visuel (image)</label>
          <input type="file" id="to-image" name="image" accept="image/*">
          <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo max.</span>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="to-nom">Nom</label>
            <input type="text" id="to-nom" name="nom" required value="<?= cat_env_field($editEnv, 'toitures', 'nom') ?>" placeholder="Toiture deux pentes">
          </div>
          <div class="admin-form-row">
            <label for="to-type">Géométrie</label>
            <select id="to-type" name="type">
              <?php foreach ($toitureTypes as $tt): ?>
                <option value="<?= htmlspecialchars((string) $tt['id']) ?>"<?= (($editEnv['item']['type'] ?? 'monopente') === $tt['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $tt['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="to-epaisseur">Épaisseur (cm)</label>
            <input type="number" id="to-epaisseur" name="epaisseur" step="0.1" min="1" required value="<?= htmlspecialchars($editEnv['item'] ? cat_cm_input((float) ($editEnv['item']['epaisseur'] ?? 0.30)) : '30') ?>">
          </div>
        </div>
        <div class="modal-row">
          <div class="admin-form-row">
            <?php $rgbToit = $editEnv['item']['rgb'] ?? [120, 113, 108]; ?>
            <label>Couleur (aperçu élévation, affichée en hex <?= cat_hex($rgbToit) ?>)</label>
            <div class="admin-color-inputs">
              <input type="number" name="r" min="0" max="255" value="<?= (int) ($rgbToit[0] ?? 0) ?>" aria-label="Rouge">
              <input type="number" name="g" min="0" max="255" value="<?= (int) ($rgbToit[1] ?? 0) ?>" aria-label="Vert">
              <input type="number" name="b" min="0" max="255" value="<?= (int) ($rgbToit[2] ?? 0) ?>" aria-label="Bleu">
              <span class="admin-color-swatch" style="background-color: <?= cat_hex($rgbToit) ?>;"></span>
            </div>
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="to-tarif">Coût au m² (€/m²)</label>
            <input type="number" id="to-tarif" name="tarif" step="0.01" min="0" value="<?= cat_env_field($editEnv, 'toitures', 'tarif', '0') ?>">
          </div>
        </div>
        <hr class="admin-sep">
        <div class="modal-row">
          <div class="admin-form-row">
            <label for="to-favori">Référence Archicad</label>
            <input type="text" id="to-favori" name="favori" value="<?= cat_env_field($editEnv, 'toitures', 'favori') ?>" placeholder="Nom de la référence Archicad (ex. Baracca — Toiture deux pentes), vide = aucune">
          </div>
        </div>
        <div class="admin-actions">
          <button type="submit" class="btn btn-cta">Enregistrer</button>
          <a href="configurateur.php?section=<?= $section ?>" class="btn btn-secondary">Annuler</a>
        </div>
      </form>
    </div>
    <?php modal_close(); endif; ?>

    <?php endif; ?>
<?php
include __DIR__ . '/../includes/admin-footer.php';
