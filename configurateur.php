<?php
$pageTitle = 'Configurateur - Baracca';
$pageDescription = 'Configurateur de modules Baracca : définissez dimensions, toiture, murs et ouvertures, puis exportez le fichier JSON pour Archicad.';
include 'includes/head.html';
?>

  <!-- Hero Section -->
  <section class="hero" style="min-height: 50vh;">
    <div class="container">
      <div class="hero-content fade-in">
        <h1>Configurateur de modules</h1>
        <p class="hero-subtitle">Concevez votre module sur mesure et exportez le fichier JSON pour Archicad.</p>
      </div>
    </div>
  </section>

  <!-- Configurateur -->
  <section class="section">
    <div class="container">
      <div class="fade-in">
        <p>
          Le configurateur ci-dessous fonctionne directement dans votre navigateur :
          réglez les dimensions, la toiture, les murs et les ouvertures, visualisez le plan
          et les élévations, puis téléchargez le fichier JSON du module. Ce fichier est lu
          côté Archicad par le script Python Baracca (API JSON officielle + add-on Tapir),
          qui génère automatiquement murs, toiture, plancher et ouvertures.
        </p>
        <p>
          Besoin d'un point de départ ? Un <a href="TEST/Baracca_Module_01.json">exemple de fichier JSON</a>
          est disponible pour visualiser le format d'échange attendu.
        </p>
      </div>

      <div class="fade-in" style="margin-top: var(--spacing-xl);">
        <iframe
          src="/configurateur/"
          width="100%" height="950"
          style="border:0; border-radius:12px; box-shadow: var(--box-shadow-xl);"
          title="Configurateur de modules Baracca">
        </iframe>
      </div>

      <p class="fade-in" style="margin-top: var(--spacing-md); font-size: var(--font-size-small); color: var(--gray);">
        Le configurateur s'exécute entièrement dans votre navigateur : aucune donnée n'est transmise
        à Baracca, à l'exception de l'adresse e-mail si vous vous inscrivez à la newsletter en pied de page.
      </p>
    </div>
  </section>

<?php include 'includes/footer.php'; ?>
