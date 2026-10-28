<?php
/**
 * Baracca - Site archicad.baracca.fr
 * Espace client : profil et configurations enregistrées
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/configurations.php';

// Page réservée aux comptes connectés
$user = archicad_current_user();
if ($user === null) {
    // Session active mais compte supprimé entre-temps : on nettoie la session
    archicad_logout();
    header('Location: connexion.php');
    exit;
}

$configs = archicad_user_configurations((string) $user['id']);

$pageTitle = 'Mon compte - Baracca';
$pageDescription = 'Votre espace client Baracca : profil et configurations de modules enregistrées.';
include 'includes/head.html';
?>

  <section class="section">
    <div class="container">
      <div class="auth-card auth-card-wide">
        <span class="section-tag">Espace client</span>
        <h1>Mon compte</h1>
        <p>Bienvenue<?= $user['prenom'] !== '' ? ', ' . htmlspecialchars($user['prenom']) : '' ?>. Retrouvez ici votre profil et vos configurations enregistrées dans le configurateur.</p>

        <h2>Mon profil</h2>
        <table class="profile-table">
          <tbody>
            <tr><th>Nom</th><td><?= htmlspecialchars($user['nom']) ?></td></tr>
            <tr><th>Prénom</th><td><?= htmlspecialchars($user['prenom']) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
            <?php if (($user['telephone'] ?? '') !== ''): ?>
              <tr><th>Téléphone</th><td><?= htmlspecialchars($user['telephone']) ?></td></tr>
            <?php endif; ?>
            <tr><th>Adresse</th><td><?= htmlspecialchars(trim($user['adresse_numero'] . ' ' . $user['adresse_rue'])) ?></td></tr>
            <tr><th>Code postal</th><td><?= htmlspecialchars($user['code_postal']) ?></td></tr>
            <tr><th>Ville</th><td><?= htmlspecialchars($user['ville']) ?></td></tr>
            <?php if ($user['reference_cadastrale'] !== ''): ?>
              <tr><th>Référence cadastrale</th><td><?= htmlspecialchars($user['reference_cadastrale']) ?></td></tr>
            <?php endif; ?>
            <tr><th>Membre depuis</th><td><?= htmlspecialchars($user['created']) ?></td></tr>
          </tbody>
        </table>

        <h2>Mes configurations enregistrées (<?= count($configs) ?>)</h2>
        <?php if ($configs === []): ?>
          <p class="auth-links">Aucune configuration enregistrée pour le moment. Lancez le <a href="configurateur.php">configurateur</a> et utilisez « Enregistrer cette configuration » dans l'onglet Export JSON.</p>
        <?php else: ?>
          <table class="profile-table configs-table">
            <thead>
              <tr><th>Nom</th><th>Dernière modification</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($configs as $config): ?>
                <tr>
                  <td><strong><?= htmlspecialchars((string) $config['nom']) ?></strong></td>
                  <td><?= htmlspecialchars((string) $config['updated']) ?></td>
                  <td><a href="configurateur.php?config=<?= urlencode((string) $config['id']) ?>" class="btn btn-secondary btn-small">Ouvrir dans le configurateur</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <div class="auth-actions">
          <a href="configurateur.php" class="btn btn-cta">Lancer le configurateur</a>
          <a href="deconnexion.php" class="btn btn-secondary">Se déconnecter</a>
        </div>
      </div>
    </div>
  </section>

<?php include 'includes/footer.php'; ?>
