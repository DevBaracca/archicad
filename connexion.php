<?php
/**
 * Baracca - Site archicad.baracca.fr
 * Connexion à l'espace client (accès au configurateur)
 */
require_once __DIR__ . '/includes/auth.php';

// Déjà connecté : direction le configurateur
if (archicad_is_logged_in()) {
    header('Location: configurateur.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    if (archicad_rate_limit_is_blocked()) {
        $minutes = (int) ceil(archicad_rate_limit_blocked_seconds() / 60);
        $loginError = 'Trop de tentatives de connexion. Réessayez dans ' . $minutes . ' min.';
    } elseif (archicad_login((string) $_POST['email'], (string) $_POST['password'])) {
        archicad_rate_limit_success();
        header('Location: configurateur.php');
        exit;
    } else {
        archicad_rate_limit_failure();
        $loginError = 'Email ou mot de passe incorrect.';
    }
}

$pageTitle = 'Connexion - Baracca';
$pageDescription = 'Connectez-vous à votre espace client Baracca pour accéder au configurateur de modules.';
include 'includes/head.html';
?>

  <section class="section">
    <div class="container">
      <div class="auth-card auth-card-narrow">
        <span class="section-tag">Espace client</span>
        <h1>Connexion</h1>
        <p>Connectez-vous avec votre email et votre mot de passe pour accéder au configurateur.</p>

        <?php if ($loginError !== ''): ?>
          <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="post" action="connexion.php" class="auth-form">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username">
          </div>
          <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
          </div>
          <div class="auth-actions">
            <button type="submit" class="btn btn-cta">Se connecter</button>
          </div>
          <p class="auth-links">Pas encore de compte ? <a href="inscription.php">Créer un compte</a></p>
        </form>
      </div>
    </div>
  </section>

<?php include 'includes/footer.php'; ?>
