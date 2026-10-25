  <!-- Footer -->
  <footer id="footer" class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-main">
          <div class="footer-menus">
            <div class="footer-section">
              <h4>Navigation</h4>
              <ul>
                <li><a href="index.php">Accueil</a></li>
                <li><a href="configurateur.php">Configurateur</a></li>
              </ul>
            </div>
            <div class="footer-section">
              <h4>Ressources</h4>
              <ul>
                <li><a href="configurateur.php">Lancer le configurateur</a></li>
                <li><a href="TEST/Baracca_Module_01.json">Exemple de fichier JSON</a></li>
                <li><a href="mailto:contact@baracca.fr">Nous contacter</a></li>
              </ul>
            </div>
            <div class="footer-section">
              <h4>Légal</h4>
              <ul>
                <li><a href="mentions-legales.php">Mentions légales</a></li>
                <li><a href="cgu.php">CGU</a></li>
                <li><a href="politique-confidentialite.php">Politique de confidentialité</a></li>
                <li><a href="#" id="cookie-preferences-link">Gérer les cookies</a></li>
              </ul>
            </div>

            <div class="footer-section footer-newsletter">
              <form id="footer-newsletter-form" class="footer-newsletter-form" action="send_newsletter.php" method="post">
                <p class="newsletter-intro">Pour suivre le projet</p>
                <div class="newsletter-input-wrapper">
                  <input type="email" id="footer-email" name="email" required placeholder="Adresse email">
                  <button type="submit" class="newsletter-submit">S'inscrire</button>
                </div>
                <p class="newsletter-disclaimer">En m'inscrivant, j'accepte la <a href="politique-confidentialite.php">politique de protection des données</a> de Baracca</p>

                <!-- Message de confirmation -->
                <?php if (isset($_GET['newsletter'])): ?>
                  <div id="newsletter-message" class="newsletter-message newsletter-message-<?php echo htmlspecialchars($_GET['newsletter']); ?>">
                    <?php
                      if ($_GET['newsletter'] == 'success') echo 'Merci pour votre inscription !';
                      else echo 'Erreur. Veuillez réessayer.';
                    ?>
                  </div>
                <?php endif; ?>

                <div class="social-links">
                  <a href="#" aria-label="LinkedIn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                      <rect x="2" y="9" width="4" height="12"></rect>
                      <circle cx="4" cy="4" r="2"></circle>
                    </svg>
                  </a>
                  <a href="#" aria-label="Twitter">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path>
                    </svg>
                  </a>
                  <a href="#" aria-label="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                      <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                      <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                    </svg>
                  </a>
                </div>
              </form>
            </div>
          </div>
          <div class="footer-brand">
            <a href="index.php" class="footer-logo">
              <img src="assets/images/logo-baracca-bandeau.png" alt="Baracca" class="footer-logo-img">
            </a>
            <p class="footer-tagline">L'habitat mobile, biosourcé et réversible.</p>
          </div>
        </div>
        <div class="footer-bottom">
          <p>© <span id="current-year"></span> Baracca. Tous droits réservés.</p>
          <p>Développé avec passion en France</p>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bandeau de consentement aux cookies -->
  <div id="cookie-banner" class="cookie-banner hidden">
    <div class="cookie-banner-content">
      <div class="cookie-banner-text">
        <p>Nous utilisons des cookies pour améliorer votre expérience sur notre site. En continuant à naviguer, vous acceptez notre utilisation des cookies conformément à notre <a href="politique-confidentialite.php">politique de confidentialité</a>.</p>
      </div>
      <div class="cookie-banner-actions">
        <button id="cookie-accept" class="btn btn-primary cookie-btn">Accepter</button>
        <button id="cookie-refuse" class="btn btn-secondary cookie-btn">Refuser</button>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/script.js"></script>
</body>
</html>
