# Baracca — Configurateur de modules pour Archicad

Configurateur web (React + Vite) qui génère un fichier JSON d'échange,
consommé par les scripts Python `baracca_import.py` (création d'éléments)
ou `baracca_import_hotlink.py` (modules .mod) côté Archicad.

## Démarrer

```bash
npm install
npm run dev        # développement : http://localhost:5173
npm run build      # production : génère le dossier dist/
```

## Déployer sur votre site PHP

1. `npm run build`
2. Copiez le contenu de `dist/` dans un dossier de votre serveur,
   par exemple `public/configurateur/`.
   → Le configurateur est accessible sur `https://votre-site.fr/configurateur/`
3. Si le dossier est servi sous un autre chemin, ajustez `base`
   dans `vite.config.js` avant de builder.
4. Intégrez-le à une page PHP (voir `php/configurateur.php`).

## Traçage des configurations (optionnel)

Dans `src/App.jsx`, renseignez :

```js
const TRACE_URL = "/api/trace_module.php";
```

À chaque téléchargement du JSON, une copie sera envoyée en POST à cette
URL. Le fichier `php/trace_module.php` fourni sauvegarde chaque
configuration en JSON daté (et peut envoyer un e-mail à l'architecte).

## Personnalisation du catalogue

Tous les produits (murs, toitures, planchers, façades) sont dans
`src/App.jsx`, en tête de fichier (constantes `MURS`, `TOITURES`,
`PLANCHERS`, `FACADES`). Modifiez-les pour refléter le catalogue
Baracca réel.

## Schéma d'export JSON

Le schéma est versionné (`schemaVersion: "1.0"`). Toute évolution doit
rester rétro-compatible, car les scripts Archicad s'appuient sur la
clé `module` et ses sous-clés `dimensions`, `plancher`, `toiture`,
`murs`.

## Sécurité iframe (recommandé)

Sur le serveur qui héberge `dist/`, autorisez l'affichage en iframe
depuis votre seul domaine, via nginx :

```
add_header Content-Security-Policy "frame-ancestors https://votre-site.fr";
```

ou Apache (.htaccess) :

```
Header set Content-Security-Policy "frame-ancestors https://votre-site.fr"
```
