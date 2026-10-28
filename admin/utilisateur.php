<?php
/**
 * Baracca - Back-office (archicad.baracca.fr)
 * Fiche détaillée d'un compte client : profil complet, réinitialisation
 * du mot de passe et suppression du compte (et de ses configurations)
 */

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/users.php';
require_once __DIR__ . '/../includes/configurations.php';

archicad_admin_require_auth();

$id = (string) ($_GET['id'] ?? '');
$user = archicad_find_user_by_id($id);
if ($user === null) {
    header('Location: utilisateurs.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!archicad_admin_csrf_verify($_POST['csrf'] ?? null)) {
        $message = ['error', 'Jeton de sécurité invalide.'];
    } elseif (isset($_POST['supprimer'])) {
        // Suppression du compte et de ses configurations enregistrées
        archicad_delete_user_configurations($id);
        archicad_delete_user($id);
        header('Location: utilisateurs.php?success=deleted');
        exit;
    } elseif (isset($_POST['reinit'])) {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            $message = ['error', 'Le mot de passe doit contenir au moins 8 caractères.'];
        } else {
            $message = archicad_set_user_password($id, $password)
                ? ['success', 'Mot de passe réinitialisé.']
                : ['error', 'Utilisateur introuvable.'];
        }
    }
}

$nbConfigs = archicad_count_configurations($id);
$telephone = (string) ($user['telephone'] ?? '');

$pageTitle = 'Fiche client';
$adminPage = 'utilisateurs';
include __DIR__ . '/../includes/admin-head.php';
?>
    <div class="admin-card">
      <h1><?= htmlspecialchars(trim((string) ($user['prenom'] ?? '') . ' ' . (string) ($user['nom'] ?? ''))) ?></h1>
      <p><a href="utilisateurs.php">← Retour à la liste des utilisateurs</a></p>
      <?php if ($message !== ''): ?>
        <p class="admin-message admin-message-<?= $message[0] ?>"><?= htmlspecialchars($message[1]) ?></p>
      <?php endif; ?>
      <table class="admin-table">
        <tbody>
          <tr><th>Nom</th><td><?= htmlspecialchars((string) ($user['nom'] ?? '')) ?></td></tr>
          <tr><th>Prénom</th><td><?= htmlspecialchars((string) ($user['prenom'] ?? '')) ?></td></tr>
          <tr><th>Email (identifiant)</th><td><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></td></tr>
          <tr><th>Téléphone</th><td><?= $telephone !== '' ? htmlspecialchars($telephone) : '—' ?></td></tr>
          <tr><th>Adresse</th><td><?= htmlspecialchars(trim((string) ($user['adresse_numero'] ?? '') . ' ' . (string) ($user['adresse_rue'] ?? ''))) ?></td></tr>
          <tr><th>Code postal</th><td><?= htmlspecialchars((string) ($user['code_postal'] ?? '')) ?></td></tr>
          <tr><th>Ville</th><td><?= htmlspecialchars((string) ($user['ville'] ?? '')) ?></td></tr>
          <?php if (($user['reference_cadastrale'] ?? '') !== ''): ?>
            <tr><th>Référence cadastrale</th><td><?= htmlspecialchars((string) $user['reference_cadastrale']) ?></td></tr>
          <?php endif; ?>
          <tr><th>Configurations enregistrées</th><td><?= $nbConfigs ?></td></tr>
          <tr><th>Inscrit le</th><td><?= htmlspecialchars((string) ($user['created'] ?? '')) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="admin-card">
      <h2>Mot de passe</h2>
      <form method="post" action="utilisateur.php?id=<?= urlencode($id) ?>" class="admin-form-inline">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(archicad_admin_csrf_token()) ?>">
        <div>
          <label for="password">Nouveau mot de passe</label>
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="8 caractères minimum">
        </div>
        <button type="submit" name="reinit" value="1" class="btn btn-secondary">Réinitialiser le mot de passe</button>
      </form>
    </div>
    <div class="admin-card">
      <h2>Supprimer le compte</h2>
      <p class="admin-hint">La suppression est définitive : le compte et ses <?= $nbConfigs ?> configuration(s) enregistrée(s) seront supprimés.</p>
      <form method="post" action="utilisateur.php?id=<?= urlencode($id) ?>" onsubmit="return confirm('Supprimer définitivement ce compte client et ses configurations enregistrées ?');">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(archicad_admin_csrf_token()) ?>">
        <button type="submit" name="supprimer" value="1" class="btn-danger">Supprimer le compte</button>
      </form>
    </div>
<?php
include __DIR__ . '/../includes/admin-footer.php';
