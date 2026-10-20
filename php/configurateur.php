<?php
/* Exemple d'intégration du configurateur dans une page de votre site PHP.
   Adaptez header.php / footer.php à votre structure habituelle. */
include 'header.php';
?>
<section class="configurateur">
    <iframe
        src="/configurateur/"
        width="100%" height="950"
        style="border:0; border-radius:12px;"
        title="Configurateur de modules Baracca">
    </iframe>
</section>
<?php include 'footer.php'; ?>
