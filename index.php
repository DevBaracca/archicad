<?php
$pageTitle = 'Baracca | Configurateur de modules pour Archicad';
$pageDescription = 'Baracca - Configurateur de modules d\'habitation en ossature bois : concevez votre module en ligne et exportez les données vers Archicad.';
include 'includes/head.html';
?>

  <!-- Hero Section -->
  <section class="hero">
    <div class="container">
      <div class="hero-content fade-in">
        <h1>Configurez votre module Baracca en ligne</h1>
        <p class="hero-subtitle">Un configurateur interactif pour concevoir votre module en ossature bois et exporter les données directement vers Archicad.</p>
        <div class="hero-actions">
          <a href="configurateur.php" class="btn btn-cta">Lancer le configurateur</a>
          <a href="#fonctionnement" class="btn btn-secondary">Comment ça marche</a>
        </div>
      </div>
      <div class="hero-image" style="background-image: url('assets/images/systemeconstructif.png');"></div>
    </div>
  </section>

  <!-- Présentation Section -->
  <section class="section">
    <div class="container">
      <h2 class="section-title fade-in">Pourquoi utiliser le configurateur ?</h2>
      <p class="section-subtitle fade-in">Tous les outils pour passer de l'idée au module constructible, sans logiciel de CAO.</p>

      <div class="feature-grid fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="3" y1="9" x2="21" y2="9"></line>
              <line x1="9" y1="21" x2="9" y2="9"></line>
            </svg>
          </div>
          <h3>Plan interactif</h3>
          <p>Visualisez votre module en plan et en élévation. Cliquez sur un mur pour l'éditer, ajouter des ouvertures ou le désactiver.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7,10 12,15 17,10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
          </div>
          <h3>Export JSON Archicad</h3>
          <p>Générez un fichier JSON structuré (schéma v1.0) lu par le script Python côté Archicad via l'API JSON officielle et l'add-on Tapir.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
          </div>
          <h3>Métriques en temps réel</h3>
          <p>Surfaces de plancher et de toiture, volume brut, menuiseries : les indicateurs de votre module se mettent à jour à chaque modification.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Comment ça marche Section -->
  <section class="section section-light" id="fonctionnement">
    <div class="container">
      <h2 class="section-title fade-in">Comment ça marche ?</h2>
      <p class="section-subtitle fade-in">Trois étapes entre votre idée et le fichier prêt pour Archicad.</p>

      <div class="feature-grid fade-in">
        <div class="feature-card">
          <h3>1. Configurez</h3>
          <p>Dimensions, type de toiture, pente, murs porteurs, plancher et façade : réglez chaque paramètre du module.</p>
        </div>

        <div class="feature-card">
          <h3>2. Visualisez</h3>
          <p>Le plan et les élévations se mettent à jour en direct. Repérez immédiatement toute ouverture mal placée.</p>
        </div>

        <div class="feature-card">
          <h3>3. Exportez</h3>
          <p>Téléchargez le fichier JSON du module. Le script Python côté Archicad génère murs, toiture, plancher et ouvertures.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Final -->
  <section class="section">
    <div class="container">
      <div class="fade-in" style="text-align: center; max-width: 700px; margin: 0 auto;">
        <h2>Prêt à concevoir votre module ?</h2>
        <p style="margin-bottom: var(--spacing-xl);">Le configurateur est gratuit et ne nécessite aucune installation : il fonctionne directement dans votre navigateur.</p>
        <a href="configurateur.php" class="btn btn-cta">Lancer le configurateur</a>
      </div>
    </div>
  </section>

<?php include 'includes/footer.php'; ?>
