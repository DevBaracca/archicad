import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  // Racine Vite = src/ : le point d'entrée index.html vit dans src/ pour ne pas
  // entrer en conflit avec index.php (page d'accueil du site PHP) à la racine.
  root: "src",
  // L'application est servie depuis le sous-dossier /configurateur/ du site PHP
  // (intégrée par iframe dans configurateur.php)
  base: "/configurateur/",
  build: {
    outDir: "../configurateur",
    emptyOutDir: true,
  },
});
