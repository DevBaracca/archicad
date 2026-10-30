<?php
/**
 * Baracca - Site archicad.baracca.fr
 * Catalogues du configurateur (data/catalogue/, un fichier JSON par rubrique :
 * menuiseries.json, modules.json, murs.json, planchers.json, surfaces.json,
 * toitures.json) : menuiseries (baies vitrées, fenêtres, portes — avec visuel),
 * modèles de modules (géométrie prédéfinie), murs, planchers, surfaces et
 * toitures. Chaque élément porte un tarif, additionné par le configurateur
 * pour afficher le tarif global du projet.
 *
 * Gérés depuis le back-office (admin/configurateur.php), lus par
 * l'application React via php/api_catalogue.php. Au premier chargement,
 * chaque catalogue est amorcé avec les valeurs par défaut ci-dessous.
 */

require_once __DIR__ . '/../config.php';

// Chaque kind a son propre fichier JSON dans data/catalogue/ :
// menuiseries.json, modules.json, murs.json, planchers.json,
// surfaces.json, toitures.json (structure {"items": [...]}).
function archicad_catalogue_dir(): string
{
    return ARCHICAD_DATA_DIR . '/catalogue';
}

function archicad_catalogue_file(string $kind): string
{
    return archicad_catalogue_dir() . '/' . $kind . '.json';
}

/**
 * Migration one-shot : l'ancien fichier unique enveloppe.json est éclaté en
 * fichiers séparés (murs/planchers/surfaces/toitures), puis supprimé.
 */
function archicad_catalogue_migrate_enveloppe_file(): void
{
    $old = archicad_catalogue_dir() . '/enveloppe.json';
    if (!is_file($old)) {
        return;
    }
    $data = json_decode((string) file_get_contents($old), true);
    if (is_array($data)) {
        // Ancienne clé "facades" -> "surfaces"
        if (isset($data['facades']) && !isset($data['surfaces'])) {
            $data['surfaces'] = $data['facades'];
            unset($data['facades']);
        }
        foreach (['murs', 'planchers', 'surfaces', 'toitures'] as $kind) {
            $file = archicad_catalogue_dir() . '/' . $kind . '.json';
            if (!is_file($file) && isset($data[$kind]) && is_array($data[$kind])) {
                file_put_contents(
                    $file,
                    json_encode(['items' => array_values($data[$kind])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    LOCK_EX
                );
            }
        }
    }
    unlink($old);
}

// Dossier public des visuels du catalogue (servi par le serveur web)
function archicad_catalogue_images_dir(): string
{
    return __DIR__ . '/../assets/images/catalogue';
}

// Convertit un chemin web stocké dans le catalogue (/assets/images/catalogue/...)
// en chemin filesystem, ou null si le chemin sort du dossier du catalogue.
function archicad_catalogue_image_fs_path(string $webPath): ?string
{
    $prefix = '/assets/images/catalogue/';
    if (!str_starts_with($webPath, $prefix)) {
        return null;
    }
    $rel = substr($webPath, strlen($prefix));
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    return archicad_catalogue_images_dir() . '/' . $rel;
}

/**
 * Valeurs d'amorçage des catalogues (reprend le catalogue historique du
 * configurateur, tarif à 0 — à renseigner depuis le back-office).
 */
function archicad_catalogue_defaults(string $kind): array
{
    $today = date('Y-m-d');
    switch ($kind) {
        case 'menuiseries':
            return [
                ['id' => 'baie-120x215', 'nom' => 'Baie vitrée 1,20 × 2,15 m', 'type' => 'baie', 'largeur' => 1.20, 'hauteur' => 2.15, 'favori' => '', 'tarif' => 0, 'image' => '', 'created' => $today],
                ['id' => 'fenetre-90x120', 'nom' => 'Fenêtre 0,90 × 1,20 m', 'type' => 'fenetre', 'largeur' => 0.90, 'hauteur' => 1.20, 'favori' => '', 'tarif' => 0, 'image' => '', 'created' => $today],
                ['id' => 'porte-90x205', 'nom' => 'Porte 0,90 × 2,05 m', 'type' => 'porte', 'largeur' => 0.90, 'hauteur' => 2.05, 'favori' => '', 'tarif' => 0, 'image' => '', 'created' => $today],
                ['id' => 'porte-fenetre-180x215', 'nom' => 'Porte-fenêtre 1,80 × 2,15 m', 'type' => 'baie', 'largeur' => 1.80, 'hauteur' => 2.15, 'favori' => '', 'tarif' => 0, 'image' => '', 'created' => $today],
            ];
        case 'modules':
            // Un modèle de module embarque les éléments liés
            // (mur, plancher, facade : id des catalogues murs/planchers/surfaces)
            return [
                ['id' => 'module-6x3', 'nom' => 'Module 6,00 × 3,00 m — h 2,80 m', 'longueur' => 6.0, 'largeur' => 3.0, 'hauteur' => 2.8, 'toiture' => 'monopente', 'image' => '', 'pente' => 15, 'pignon' => true, 'mur' => 'clt140', 'plancher' => 'clt_dalle', 'facade' => 'bardage_bois', 'tarif' => 0, 'created' => $today],
                ['id' => 'module-9x36', 'nom' => 'Module 9,00 × 3,60 m — h 2,80 m', 'longueur' => 9.0, 'largeur' => 3.6, 'hauteur' => 2.8, 'toiture' => 'deuxpentes', 'image' => '', 'pente' => 30, 'pignon' => true, 'mur' => 'clt140', 'plancher' => 'clt_dalle', 'facade' => 'bardage_bois', 'tarif' => 0, 'created' => $today],
            ];
        case 'murs':
            // favori = nom du favori Archicad à utiliser ('' = aucun, à
            // renseigner depuis le back-office) — repris dans l'export JSON.
            // surface = id d'une surface (sous-catalogue "surfaces") dont le
            // visuel est utilisé dans les élévations.
            return [
                ['id' => 'clt90', 'nom' => 'Mur CLT 90 mm', 'epaisseur' => 0.09, 'image' => '', 'surface' => 'bardage_bois', 'rgb' => [140, 107, 89], 'favori' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'clt140', 'nom' => 'Mur CLT 140 mm', 'epaisseur' => 0.14, 'image' => '', 'surface' => 'bardage_bois', 'rgb' => [140, 107, 89], 'favori' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'ossature160', 'nom' => 'Ossature bois 160 mm', 'epaisseur' => 0.16, 'image' => '', 'surface' => 'enduit', 'rgb' => [194, 169, 133], 'favori' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'bloc200', 'nom' => 'Bloc béton 200 mm', 'epaisseur' => 0.20, 'image' => '', 'surface' => 'brique', 'rgb' => [158, 158, 158], 'favori' => '', 'tarif' => 0, 'created' => $today],
            ];
        case 'planchers':
            // épaisseur (m) du plancher, affichée en élévation et exportée
            return [
                ['id' => 'clt_dalle', 'nom' => 'Dalle CLT + chape', 'epaisseur' => 0.30, 'image' => '', 'favori' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'plancher_bois', 'nom' => 'Plancher bois sur lambourdes', 'epaisseur' => 0.24, 'image' => '', 'favori' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'dalle_beton', 'nom' => 'Dalle béton', 'epaisseur' => 0.20, 'image' => '', 'favori' => '', 'tarif' => 0, 'created' => $today],
            ];
        case 'surfaces':
            // Surfaces (habillage extérieur) : visuel téléversé depuis le
            // back-office (image), utilisé dans les élévations. Pas de favori.
            return [
                ['id' => 'bardage_bois', 'nom' => 'Bardage bois', 'image' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'enduit', 'nom' => 'Enduit minéral', 'image' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'tole', 'nom' => 'Tôle nervurée', 'image' => '', 'tarif' => 0, 'created' => $today],
                ['id' => 'brique', 'nom' => 'Brique de parement', 'image' => '', 'tarif' => 0, 'created' => $today],
            ];
        case 'toitures':
            // Rubrique toiture (à l'identique des murs) : type = géométrie
            // (monopente/deux pentes/terrasse), épaisseur (m) dessinée au-dessus
            // de la ligne directrice en élévation, surface, couleur, favori.
            return [
                ['id' => 'toit-monopente', 'nom' => 'Toiture monopente', 'type' => 'monopente', 'epaisseur' => 0.30, 'image' => '', 'surface' => '', 'rgb' => [120, 113, 108], 'favori' => 'Baracca — Toiture monopente', 'tarif' => 0, 'created' => $today],
                ['id' => 'toit-deuxpentes', 'nom' => 'Toiture deux pentes', 'type' => 'deuxpentes', 'epaisseur' => 0.30, 'image' => '', 'surface' => '', 'rgb' => [120, 113, 108], 'favori' => 'Baracca — Toiture deux pentes', 'tarif' => 0, 'created' => $today],
                ['id' => 'toit-terrasse', 'nom' => 'Terrasse plate', 'type' => 'terrasse', 'epaisseur' => 0.25, 'image' => '', 'surface' => '', 'rgb' => [120, 113, 108], 'favori' => 'Baracca — Toiture terrasse', 'tarif' => 0, 'created' => $today],
            ];
    }
    return [];
}

/**
 * Rubrique « Toitures » (toitures.json — éléments complets : géométrie,
 * épaisseur, surface, couleur, favori Archicad, tarif). Un modèle de module
 * référence l'id d'un élément de cette rubrique.
 */
function archicad_catalogue_toitures(): array
{
    return archicad_load_catalogue('toitures');
}

/**
 * Géométries de toiture possibles (vocabulaire fixe) — champ « type » des
 * éléments de la rubrique toitures.
 */
function archicad_catalogue_toiture_types(): array
{
    return [
        ['id' => 'monopente', 'nom' => 'Monopente'],
        ['id' => 'deuxpentes', 'nom' => 'Deux pentes'],
        ['id' => 'terrasse', 'nom' => 'Terrasse plate'],
    ];
}

function archicad_save_catalogue(string $kind, array $items): bool
{
    if (!is_dir(archicad_catalogue_dir())) {
        mkdir(archicad_catalogue_dir(), 0775, true);
    }
    archicad_catalogue_migrate_enveloppe_file();
    $file = archicad_catalogue_file($kind);
    $data = ['items' => array_values($items)];
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function archicad_load_catalogue(string $kind): array
{
    archicad_catalogue_migrate_enveloppe_file();
    if (!is_file(archicad_catalogue_file($kind))) {
        // Premier chargement : amorce le catalogue avec les valeurs par défaut
        archicad_save_catalogue($kind, archicad_catalogue_defaults($kind));
    }
    $data = json_decode((string) file_get_contents(archicad_catalogue_file($kind)), true);
    if (!is_array($data)) {
        return [];
    }
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    // Rétro-compatibilité : favori Archicad ('' = aucun) et visuel ('')
    foreach ($items as $i => $item) {
        if (!array_key_exists('favori', $item)) {
            $items[$i]['favori'] = '';
        }
        if (!array_key_exists('image', $item)) {
            $items[$i]['image'] = '';
        }
    }
    if ($kind === 'murs' || $kind === 'toitures') {
        // Surface par défaut = première surface du catalogue surfaces.json
        $firstSurface = (string) (archicad_load_catalogue('surfaces')[0]['id'] ?? '');
        foreach ($items as $i => $item) {
            if (empty($item['surface'])) {
                $items[$i]['surface'] = $firstSurface;
            }
        }
    }
    if ($kind === 'murs') {
        foreach ($items as $i => $item) {
            if (empty($item['epaisseur'])) {
                $items[$i]['epaisseur'] = 0.14;
            }
            if (empty($item['rgb'])) {
                $items[$i]['rgb'] = [158, 158, 158];
            }
        }
    }
    if ($kind === 'planchers') {
        // Épaisseur en m (défaut 20 cm)
        foreach ($items as $i => $item) {
            if (empty($item['epaisseur'])) {
                $items[$i]['epaisseur'] = 0.20;
            }
        }
    }
    if ($kind === 'toitures') {
        foreach ($items as $i => $item) {
            if (empty($item['type'])) {
                $items[$i]['type'] = 'monopente';
            }
            if (empty($item['epaisseur'])) {
                $items[$i]['epaisseur'] = 0.30;
            }
            if (empty($item['rgb'])) {
                $items[$i]['rgb'] = [120, 113, 108];
            }
        }
    }
    if ($kind === 'modules') {
        // Rétro-compatibilité : un modèle embarque les éléments d'enveloppe
        // (mur, plancher, facade) — à défaut, le premier de chaque catalogue
        $refs = [
            'mur' => archicad_load_catalogue('murs'),
            'plancher' => archicad_load_catalogue('planchers'),
            'facade' => archicad_load_catalogue('surfaces'),
        ];
        foreach ($items as $i => $item) {
            foreach ($refs as $key => $list) {
                if (empty($item[$key]) && isset($list[0]['id'])) {
                    $items[$i][$key] = (string) $list[0]['id'];
                }
            }
            // La toiture du modèle référence désormais la rubrique toitures :
            // un ancien id de vocabulaire géométrique (monopente, ...) est
            // traduit vers le premier élément de toiture de ce type
            $toitures = archicad_load_catalogue('toitures');
            $toitureOk = false;
            foreach ($toitures as $t) {
                if (($t['id'] ?? '') === ($item['toiture'] ?? '')) {
                    $toitureOk = true;
                    break;
                }
            }
            if (!$toitureOk) {
                $found = null;
                foreach ($toitures as $t) {
                    if (($t['type'] ?? '') === ($item['toiture'] ?? '')) {
                        $found = $t;
                        break;
                    }
                }
                $items[$i]['toiture'] = (string) (($found ?? $toitures[0] ?? [])['id'] ?? 'monopente');
            }
        }
    }
    return $items;
}

function archicad_catalogue_find(string $kind, string $id): ?array
{
    foreach (archicad_load_catalogue($kind) as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }
    return null;
}

/**
 * Crée ou met à jour un élément du catalogue. Si $id est fourni et existe,
 * les champs sont fusionnés sur l'élément existant ; sinon un nouvel élément
 * est créé. Retourne l'élément enregistré.
 */
function archicad_catalogue_upsert(string $kind, array $fields, ?string $id = null): array
{
    $items = archicad_load_catalogue($kind);
    $now = date('Y-m-d H:i:s');
    if ($id !== null && $id !== '') {
        foreach ($items as $i => $item) {
            if (($item['id'] ?? '') === $id) {
                $items[$i] = array_merge($item, $fields, ['id' => $id, 'updated' => $now]);
                archicad_save_catalogue($kind, $items);
                return $items[$i];
            }
        }
    }
    $item = array_merge($fields, ['id' => bin2hex(random_bytes(8)), 'created' => $now, 'updated' => $now]);
    $items[] = $item;
    archicad_save_catalogue($kind, $items);
    return $item;
}

function archicad_catalogue_delete(string $kind, string $id): bool
{
    $items = archicad_load_catalogue($kind);
    foreach ($items as $i => $item) {
        if (($item['id'] ?? '') === $id) {
            // Supprime le visuel associé (menuiseries)
            $image = (string) ($item['image'] ?? '');
            if ($image !== '') {
                $fs = archicad_catalogue_image_fs_path($image);
                if ($fs !== null && is_file($fs)) {
                    unlink($fs);
                }
            }
            unset($items[$i]);
            return archicad_save_catalogue($kind, $items);
        }
    }
    return false;
}

function archicad_catalogue_total_items(): int
{
    return count(archicad_load_catalogue('menuiseries'))
        + count(archicad_load_catalogue('modules'))
        + count(archicad_load_catalogue('murs'))
        + count(archicad_load_catalogue('planchers'))
        + count(archicad_load_catalogue('surfaces'))
        + count(archicad_load_catalogue('toitures'));
}

/**
 * Enregistre un visuel (menuiserie, surface...) envoyé depuis le back-office.
 * Retourne ['ok' => bool, 'webPath' => ?string, 'error' => ?string].
 */
function archicad_catalogue_store_image(array $file, string $id, string $subdir = 'menuiseries'): array
{
    $fail = fn (string $error) => ['ok' => false, 'webPath' => null, 'error' => $error];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $fail('Aucun fichier envoyé.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return $fail('Image trop lourde (5 Mo maximum).');
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return $fail('Format non accepté (jpg, png, webp, gif).');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp) || @getimagesize($tmp) === false) {
        return $fail('Fichier image invalide.');
    }
    $dir = archicad_catalogue_images_dir() . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = $id . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        return $fail('Échec de l\'enregistrement de l\'image.');
    }
    return ['ok' => true, 'webPath' => '/assets/images/catalogue/' . $subdir . '/' . $name, 'error' => null];
}
