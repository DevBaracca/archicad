<?php
/**
 * Baracca - Back-office (archicad.baracca.fr)
 * Gestion des catalogues du configurateur : menuiseries (baies vitrées,
 * fenêtres, portes — avec visuel et favori Archicad), modèles de modules
 * (géométrie prédéfinie + toiture et éléments d'enveloppe liés) et enveloppe
 * (murs, planchers, surfaces, toitures). Chaque élément porte un tarif ;
 * le configurateur en calcule le tarif global du projet.
 *
 * L'ajout / la modification d'un élément se fait dans une boîte (modale) :
 * bouton « + » à côté du titre de la rubrique, crayon pour modifier,
 * croix rouge pour supprimer ; à la validation la boîte se referme.
 */

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/catalogue.php';

archicad_admin_require_auth();

$section = (string) ($_GET['section'] ?? 'modules');
// Onglets : Modules, Murs, Planchers, Toitures, Ouvrants, Surfaces
// (l'ancien onglet « enveloppe » est éclaté en sous-onglets)
if ($section === 'enveloppe') {
    $sous0 = (string) ($_GET['sous'] ?? '');
    $section = in_array($sous0, ['murs', 'planchers', 'toitures'], true) ? $sous0 : ($sous0 === 'facades' ? 'surfaces' : 'murs');
}
if (!in_array($section, ['modules', 'murs', 'planchers', 'toitures', 'menuiseries', 'surfaces'], true)) {
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
        $pente = max(0, min(45, (int) round(cat_num('pente'))));
        $tarif = round(cat_num('tarif'), 2);
        if ($nom === '' || $longueur <= 0 || $largeur <= 0 || $hauteur <= 0) {
            cat_redirect('modules', 'invalid');
        }
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
        // extérieure est celle du mur choisi)
        archicad_catalogue_upsert('modules', [
            'nom' => $nom,
            'longueur' => $longueur,
            'largeur' => $largeur,
            'hauteur' => $hauteur,
            'toiture' => $toiture,
            'pente' => $pente,
            'pignon' => isset($_POST['pignon']),
            'mur' => $murId,
            'plancher' => $plancherId,
            'tarif' => $tarif,
        ], $id !== '' ? $id : null);
        cat_redirect('modules', 'saved');
    }
    if ($action === 'delete_module') {
        archicad_catalogue_delete('modules', trim((string) ($_POST['id'] ?? '')));
        cat_redirect('modules', 'deleted');
    }

    // ----- Enveloppe (murs, planchers, surfaces, toitures) -----
    if ($action === 'save_enveloppe') {
        $sous = (string) ($_POST['sous'] ?? '');
        if (!in_array($sous, ['murs', 'planchers', 'facades', 'toitures'], true)) {
            cat_redirect('enveloppe', 'invalid');
        }
        $id = trim((string) ($_POST['id'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $tarif = round(cat_num('tarif'), 2);
        if ($nom === '') {
            cat_redirect('enveloppe', 'invalid');
        }
        $fields = ['nom' => $nom, 'tarif' => $tarif];
        // Référence de surface (sous-catalogue facades = « Surfaces »)
        $facadeList = archicad_load_catalogue('facades');
        $surfaceRef = function (string $posted) use ($facadeList): string {
            $sid = (string) $posted;
            if ($sid === '' || archicad_catalogue_find('facades', $sid) === null) {
                $sid = (string) ($facadeList[0]['id'] ?? '');
            }
            return $sid;
        };
        if ($sous === 'murs' || $sous === 'toitures') {
            // favori Archicad ('' = aucun) — les surfaces n'en ont pas
            $fields['favori'] = trim((string) ($_POST['favori'] ?? ''));
            $fields['surface'] = $surfaceRef((string) ($_POST['surface'] ?? ''));
            // Saisie en cm (précision mm) — stockage en m
            $epaisseur = round(cat_num('epaisseur') / 100, 3);
            if ($epaisseur <= 0) {
                cat_redirect('enveloppe', 'invalid');
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
                cat_redirect('enveloppe', 'invalid');
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
        $destSection = $sous === 'facades' ? 'surfaces' : $sous;
        // Surfaces : visuel téléversé (suppression demandée ou nouvel envoi)
        if ($sous === 'facades') {
            if (isset($_POST['remove_image']) && (string) ($item['image'] ?? '') !== '') {
                $fs = archicad_catalogue_image_fs_path((string) $item['image']);
                if ($fs !== null && is_file($fs)) {
                    unlink($fs);
                }
                archicad_catalogue_upsert('facades', ['image' => ''], (string) $item['id']);
                $item = archicad_catalogue_find('facades', (string) $item['id']) ?? $item;
            }
            if (isset($_FILES['image']) && is_array($_FILES['image'])) {
                $stored = archicad_catalogue_store_image($_FILES['image'], (string) $item['id'], 'surfaces');
                if (!$stored['ok']) {
                    cat_redirect('enveloppe', 'image', (string) $stored['error']);
                }
                $old = (string) ($item['image'] ?? '');
                if ($old !== '' && $old !== $stored['webPath']) {
                    $fs = archicad_catalogue_image_fs_path($old);
                    if ($fs !== null && is_file($fs)) {
                        unlink($fs);
                    }
                }
                archicad_catalogue_upsert('facades', ['image' => (string) $stored['webPath']], (string) $item['id']);
            }
        }
        cat_redirect($destSection, 'saved');
    }
    if ($action === 'delete_enveloppe') {
        $sous = (string) ($_POST['sous'] ?? '');
        if (!in_array($sous, ['murs', 'planchers', 'facades', 'toitures'], true)) {
            cat_redirect('enveloppe', 'invalid');
        }
        archicad_catalogue_delete($sous, trim((string) ($_POST['id'] ?? '')));
        cat_redirect($sous === 'facades' ? 'surfaces' : $sous, 'deleted');
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
$murs = archicad_load_catalogue('murs');
$planchers = archicad_load_catalogue('planchers');
$facades = archicad_load_catalogue('facades');
$toitures = archicad_catalogue_toitures();
$toitureTypes = archicad_catalogue_toiture_types();

$typeLabels = ['baie' => 'Baie vitrée', 'fenetre' => 'Fenêtre', 'porte' => 'Porte'];
$toitureLabels = array_column($toitures, 'nom', 'id');
$toitureTypeOf = array_column($toitures, 'type', 'id');
$toitureTypeLabels = array_column($toitureTypes, 'nom', 'id');
$murNames = array_column($murs, 'nom', 'id');
$plancherNames = array_column($planchers, 'nom', 'id');
$facadeNames = array_column($facades, 'nom', 'id');

$new = isset($_GET['new']);
$editMenuiserie = null;
$editModule = null;
$editEnv = ['sous' => '', 'item' => null];
if (($editId = trim((string) ($_GET['edit'] ?? ''))) !== '') {
    if ($section === 'menuiseries') {
        $editMenuiserie = archicad_catalogue_find('menuiseries', $editId);
    } elseif ($section === 'modules') {
        $editModule = archicad_catalogue_find('modules', $editId);
    } elseif (in_array($section, ['murs', 'planchers', 'toitures', 'surfaces'], true)) {
        $sous = $section === 'surfaces' ? 'facades' : $section;
        $editEnv = ['sous' => $sous, 'item' => archicad_catalogue_find($sous, $editId)];
    }
}
// Rubrique active (édition ou ajout) — ouvre la boîte correspondante
$envSous = $editEnv['sous'];
if ($envSous === '' && $new && in_array($section, ['murs', 'planchers', 'toitures', 'surfaces'], true)) {
    $envSous = $section === 'surfaces' ? 'facades' : $section;
}

include __DIR__ . '/../includes/admin-head.php';
?>

    <?php if ($flashMessage): ?>
      <p class="admin-flash <?= $flashMessage[0] ?>"><?= htmlspecialchars($flashMessage[1]) ?></p>
    <?php endif; ?>

      <nav class="admin-tabs">
        <a href="configurateur.php?section=modules"<?= $section === 'modules' ? ' class="active"' : '' ?>>Modules</a>
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
              <th>Favori Archicad</th>
              <th>Tarif</th>
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
                <td><?= cat_eur((float) ($m['tarif'] ?? 0)) ?> €</td>
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
        <div class="admin-form-grid">
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
          <div class="admin-form-row">
            <label for="m-largeur">Largeur (cm)</label>
            <input type="number" id="m-largeur" name="largeur" step="0.1" min="10" required value="<?= htmlspecialchars($editMenuiserie ? cat_cm_input((float) $editMenuiserie['largeur']) : '120') ?>">
          </div>
          <div class="admin-form-row">
            <label for="m-hauteur">Hauteur (cm)</label>
            <input type="number" id="m-hauteur" name="hauteur" step="0.1" min="10" required value="<?= htmlspecialchars($editMenuiserie ? cat_cm_input((float) $editMenuiserie['hauteur']) : '215') ?>">
          </div>
          <div class="admin-form-row">
            <label for="m-favori">Favori Archicad</label>
            <input type="text" id="m-favori" name="favori" value="<?= htmlspecialchars((string) ($editMenuiserie['favori'] ?? '')) ?>" placeholder="Nom du favori Archicad (ex. Baracca — Porte), vide = aucun">
          </div>
          <div class="admin-form-row">
            <label for="m-tarif">Tarif (€)</label>
            <input type="number" id="m-tarif" name="tarif" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editMenuiserie['tarif'] ?? '0')) ?>">
          </div>
          <div class="admin-form-row">
            <label for="m-image">Visuel (image)</label>
            <input type="file" id="m-image" name="image" accept="image/*">
            <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo maximum.</span>
          </div>
        </div>
        <?php if ($editMenuiserie && ($editMenuiserie['image'] ?? '') !== ''): ?>
          <p class="admin-hint">
            Visuel actuel :
            <img src="../<?= ltrim(htmlspecialchars((string) $editMenuiserie['image']), '/') ?>" alt="" class="admin-catalogue-thumb">
            <label><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel actuel</label>
          </p>
        <?php endif; ?>
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
      <p class="admin-hint">Le client choisit un modèle dans ce catalogue (onglet « Module » du configurateur) : longueur, largeur, hauteur sous plafond, pente, pignon <strong>et éléments d'enveloppe liés (mur, plancher, toiture)</strong> sont définis par le modèle — le client ne peut pas les modifier, ils lui sont présentés en lecture seule. La surface extérieure est celle du mur choisi.</p>
      <?php if ($modules === []): ?>
        <p class="admin-hint">Aucun modèle de module dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Géométrie</th>
              <th>Toiture</th>
              <th>Tarif</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($modules as $mod): $ttype = $toitureTypeOf[$mod['toiture'] ?? ''] ?? ''; ?>
              <tr>
                <td><strong><?= htmlspecialchars((string) $mod['nom']) ?></strong></td>
                <td><?= cat_cm((float) $mod['longueur']) ?> × <?= cat_cm((float) $mod['largeur']) ?> — h <?= cat_cm((float) $mod['hauteur']) ?></td>
                <td><?= htmlspecialchars($toitureLabels[$mod['toiture'] ?? ''] ?? (string) ($mod['toiture'] ?? '')) ?><?= $ttype !== '' && $ttype !== 'terrasse' ? ' — pente ' . (int) ($mod['pente'] ?? 0) . '°' : '' ?><?= $ttype === 'deuxpentes' ? ((!empty($mod['pignon'])) ? ', pignon' : ', croupe') : '' ?></td>
                <td><?= cat_eur((float) ($mod['tarif'] ?? 0)) ?> €</td>
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
      <form method="post" action="configurateur.php?section=modules">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_module">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editModule['id'] ?? '')) ?>">
        <div class="admin-form-grid">
          <div class="admin-form-row">
            <label for="mod-nom">Nom</label>
            <input type="text" id="mod-nom" name="nom" required value="<?= htmlspecialchars((string) ($editModule['nom'] ?? '')) ?>" placeholder="Module 6,00 × 3,00 m — h 2,80 m">
          </div>
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
          <div class="admin-form-row">
            <label for="mod-pente">Pente (°)</label>
            <input type="number" id="mod-pente" name="pente" step="1" min="0" max="45" value="<?= htmlspecialchars((string) ($editModule['pente'] ?? '15')) ?>">
          </div>
          <div class="admin-form-row">
            <label for="mod-tarif">Tarif (€)</label>
            <input type="number" id="mod-tarif" name="tarif" step="0.01" min="0" value="<?= htmlspecialchars((string) ($editModule['tarif'] ?? '0')) ?>">
          </div>
          <div class="admin-form-row admin-form-check">
            <label><input type="checkbox" name="pignon" value="1"<?= !isset($editModule) || !empty($editModule['pignon']) ? ' checked' : '' ?>> Pignon sur les petits côtés (toiture deux pentes, sinon croupe)</label>
          </div>
        </div>
        <hr class="admin-sep">
        <p class="admin-sep-label">Enveloppe liée au modèle</p>
        <div class="admin-form-grid">
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
                <option value="<?= htmlspecialchars((string) $t['id']) ?>"<?= (($editModule['toiture'] ?? ($toitures[0]['id'] ?? '')) === $t['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $t['nom']) ?><?= !empty($t['epaisseur']) ? ' — ép. ' . cat_cm((float) $t['epaisseur']) : '' ?><?= ($t['favori'] ?? '') !== '' ? ' — favori « ' . htmlspecialchars((string) $t['favori']) . ' »' : '' ?></option>
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

    <?php elseif ($section === 'surfaces'): ?>
    <div class="admin-card">
      <h2>Surfaces (<?= count($facades) ?>) <?= cat_btn_add('configurateur.php?section=surfaces&new=1', 'Ajouter une surface') ?></h2>
      <p class="admin-hint">Surfaces d'habillage extérieur choisies pour les murs et les toitures ; le visuel téléversé est utilisé dans les élévations du configurateur. Pas de favori Archicad pour cette rubrique.</p>
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
                  <?= cat_action_delete('configurateur.php?section=surfaces', $csrf, 'delete_enveloppe', (string) $f['id'], 'Supprimer cette surface du catalogue ?', '<input type="hidden" name="sous" value="facades">') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php if ($envSous === 'facades' && ($editEnv['item'] || $new)): modal_open('configurateur.php?section=surfaces'); ?>
    <div class="admin-card">
      <h3><?= $editEnv['item'] ? 'Modifier : ' . htmlspecialchars((string) $editEnv['item']['nom']) : 'Ajouter une surface' ?></h3>
      <form method="post" action="configurateur.php?section=surfaces" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="facades">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="admin-form-grid">
          <div class="admin-form-row">
            <label for="fa-nom">Nom</label>
            <input type="text" id="fa-nom" name="nom" required value="<?= cat_env_field($editEnv, 'facades', 'nom') ?>" placeholder="Bardage bois">
          </div>
          <div class="admin-form-row">
            <label for="fa-image">Visuel (image)</label>
            <input type="file" id="fa-image" name="image" accept="image/*">
            <span class="admin-hint">JPG, PNG, WebP ou GIF — 5 Mo maximum. Le visuel est utilisé dans les élévations du configurateur.</span>
          </div>
        </div>
        <?php if ($editEnv['item'] && ($editEnv['item']['image'] ?? '') !== ''): ?>
          <p class="admin-hint">
            Visuel actuel :
            <img src="../<?= ltrim(htmlspecialchars((string) $editEnv['item']['image']), '/') ?>" alt="" class="admin-catalogue-thumb">
            <label><input type="checkbox" name="remove_image" value="1"> Supprimer le visuel actuel</label>
          </p>
        <?php endif; ?>
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
      <p class="admin-hint">Types de mur porteur : épaisseur, surface (visuel utilisé en élévation), couleur, favori Archicad, tarif — liés aux modules.</p>
      <?php if ($murs === []): ?>
        <p class="admin-hint">Aucun type de mur dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Nom</th><th>Épaisseur</th><th>Surface</th><th>Couleur</th><th>Favori Archicad</th><th>Tarif</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($murs as $mur): $rgb = $mur['rgb'] ?? [158, 158, 158]; ?>
              <tr>
                <td><strong><?= htmlspecialchars((string) $mur['nom']) ?></strong></td>
                <td><?= cat_cm((float) ($mur['epaisseur'] ?? 0.20)) ?></td>
                <td><?= htmlspecialchars($facadeNames[$mur['surface'] ?? ''] ?? '—') ?></td>
                <td><span class="admin-color-swatch" style="background-color: <?= cat_hex($rgb) ?>;"></span> <?= cat_hex($rgb) ?></td>
                <td><?= ($mur['favori'] ?? '') !== '' ? htmlspecialchars((string) $mur['favori']) : '—' ?></td>
                <td><?= cat_eur((float) ($mur['tarif'] ?? 0)) ?> €</td>
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
      <p class="admin-hint">Types de plancher : épaisseur (dessinée en élévation, +21,7 cm au-delà de la ligne directrice des murs), favori Archicad, tarif — liés aux modules.</p>
      <?php if ($planchers === []): ?>
        <p class="admin-hint">Aucun plancher dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Nom</th><th>Épaisseur</th><th>Favori Archicad</th><th>Tarif</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($planchers as $p): ?>
              <tr>
                <td><strong><?= htmlspecialchars((string) $p['nom']) ?></strong></td>
                <td><?= cat_cm((float) ($p['epaisseur'] ?? 0.20)) ?></td>
                <td><?= ($p['favori'] ?? '') !== '' ? htmlspecialchars((string) $p['favori']) : '—' ?></td>
                <td><?= cat_eur((float) ($p['tarif'] ?? 0)) ?> €</td>
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
      <p class="admin-hint">Types de toiture : géométrie, épaisseur (bande au-dessus de la ligne directrice en élévation), surface, couleur, favori Archicad, tarif — liés aux modules.</p>
      <?php if ($toitures === []): ?>
        <p class="admin-hint">Aucun type de toiture dans le catalogue pour le moment.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr><th>Nom</th><th>Géométrie</th><th>Épaisseur</th><th>Surface</th><th>Couleur</th><th>Favori Archicad</th><th>Tarif</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($toitures as $t): $rgbT = $t['rgb'] ?? [120, 113, 108]; ?>
              <tr>
                <td><strong><?= htmlspecialchars((string) $t['nom']) ?></strong></td>
                <td><?= htmlspecialchars($toitureTypeLabels[$t['type'] ?? ''] ?? (string) ($t['type'] ?? '')) ?></td>
                <td><?= cat_cm((float) ($t['epaisseur'] ?? 0.30)) ?></td>
                <td><?= htmlspecialchars($facadeNames[$t['surface'] ?? ''] ?? '—') ?></td>
                <td><span class="admin-color-swatch" style="background-color: <?= cat_hex($rgbT) ?>;"></span> <?= cat_hex($rgbT) ?></td>
                <td><?= ($t['favori'] ?? '') !== '' ? htmlspecialchars((string) $t['favori']) : '—' ?></td>
                <td><?= cat_eur((float) ($t['tarif'] ?? 0)) ?> €</td>
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
      <form method="post" action="configurateur.php?section=<?= $section ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="murs">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="admin-form-grid">
          <div class="admin-form-row">
            <label for="mur-nom">Nom</label>
            <input type="text" id="mur-nom" name="nom" required value="<?= cat_env_field($editEnv, 'murs', 'nom') ?>" placeholder="Mur CLT 140 mm">
          </div>
          <div class="admin-form-row">
            <label for="mur-epaisseur">Épaisseur (cm)</label>
            <input type="number" id="mur-epaisseur" name="epaisseur" step="0.1" min="1" required value="<?= htmlspecialchars($editEnv['item'] ? cat_cm_input((float) ($editEnv['item']['epaisseur'] ?? 0.14)) : '14') ?>">
          </div>
          <div class="admin-form-row">
            <label for="mur-surface">Surface</label>
            <select id="mur-surface" name="surface">
              <?php foreach ($facades as $f): ?>
                <option value="<?= htmlspecialchars((string) $f['id']) ?>"<?= (($editEnv['item']['surface'] ?? ($facades[0]['id'] ?? '')) === $f['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $f['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
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
          <div class="admin-form-row">
            <label for="mur-favori">Favori Archicad</label>
            <input type="text" id="mur-favori" name="favori" value="<?= cat_env_field($editEnv, 'murs', 'favori') ?>" placeholder="Nom du favori Archicad (ex. Baracca — Mur CLT), vide = aucun">
          </div>
          <div class="admin-form-row">
            <label for="mur-tarif">Tarif (€)</label>
            <input type="number" id="mur-tarif" name="tarif" step="0.01" min="0" value="<?= cat_env_field($editEnv, 'murs', 'tarif', '0') ?>">
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
      <form method="post" action="configurateur.php?section=<?= $section ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="planchers">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="admin-form-grid">
          <div class="admin-form-row">
            <label for="pl-nom">Nom</label>
            <input type="text" id="pl-nom" name="nom" required value="<?= cat_env_field($editEnv, 'planchers', 'nom') ?>" placeholder="Dalle CLT + chape">
          </div>
          <div class="admin-form-row">
            <label for="pl-epaisseur">Épaisseur (cm)</label>
            <input type="number" id="pl-epaisseur" name="epaisseur" step="0.1" min="1" required value="<?= htmlspecialchars($editEnv['item'] ? cat_cm_input((float) ($editEnv['item']['epaisseur'] ?? 0.20)) : '20') ?>">
          </div>
          <div class="admin-form-row">
            <label for="pl-favori">Favori Archicad</label>
            <input type="text" id="pl-favori" name="favori" value="<?= cat_env_field($editEnv, 'planchers', 'favori') ?>" placeholder="Nom du favori Archicad (ex. Baracca — Dalle CLT), vide = aucun">
          </div>
          <div class="admin-form-row">
            <label for="pl-tarif">Tarif (€)</label>
            <input type="number" id="pl-tarif" name="tarif" step="0.01" min="0" value="<?= cat_env_field($editEnv, 'planchers', 'tarif', '0') ?>">
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
      <form method="post" action="configurateur.php?section=<?= $section ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_enveloppe">
        <input type="hidden" name="sous" value="toitures">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEnv['item']['id'] ?? '')) ?>">
        <div class="admin-form-grid">
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
          <div class="admin-form-row">
            <label for="to-epaisseur">Épaisseur (cm)</label>
            <input type="number" id="to-epaisseur" name="epaisseur" step="0.1" min="1" required value="<?= htmlspecialchars($editEnv['item'] ? cat_cm_input((float) ($editEnv['item']['epaisseur'] ?? 0.30)) : '30') ?>">
          </div>
          <div class="admin-form-row">
            <label for="to-surface">Surface</label>
            <select id="to-surface" name="surface">
              <?php foreach ($facades as $f): ?>
                <option value="<?= htmlspecialchars((string) $f['id']) ?>"<?= (($editEnv['item']['surface'] ?? ($facades[0]['id'] ?? '')) === $f['id']) ? ' selected' : '' ?>><?= htmlspecialchars((string) $f['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
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
          <div class="admin-form-row">
            <label for="to-favori">Favori Archicad</label>
            <input type="text" id="to-favori" name="favori" value="<?= cat_env_field($editEnv, 'toitures', 'favori') ?>" placeholder="Nom du favori Archicad (ex. Baracca — Toiture deux pentes), vide = aucun">
          </div>
          <div class="admin-form-row">
            <label for="to-tarif">Tarif (€)</label>
            <input type="number" id="to-tarif" name="tarif" step="0.01" min="0" value="<?= cat_env_field($editEnv, 'toitures', 'tarif', '0') ?>">
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
