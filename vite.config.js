import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  // Pour un hébergement dans un sous-dossier, ex. https://site.fr/configurateur/
  // décommentez et ajustez :
  // base: "/configurateur/",
});
