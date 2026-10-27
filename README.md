# Baracca — Configurateur de modules pour Archicad

Configurateur web (React + Vite) qui génère un fichier JSON d'échange,
consommé par les scripts Python `baracca_import.py` (création d'éléments)
ou `baracca_import_hotlink.py` (modules .mod) côté Archicad.

## Démarrer

```bash
npm install
npm run dev        # développement : http://localhost:5173
npm run build      # production : génère le dossier configurateur/
```

## Structure du site PHP

Ce dossier est désormais un site PHP complet (même mise en page que `dev.baracca.fr`
et `invest.baracca.fr`), dans lequel l'application React est intégrée :

```
index.php                      Accueil (présentation du configurateur)
configurateur.php              Page d'intégration de l'application (iframe)
mentions-legales.php           Pages légales
cgu.php
politique-confidentialite.php
send_newsletter.php            Inscription newsletter (footer)
includes/head.html             En-tête + navigation (partagés)
includes/footer.php            Pied de page (newsletter, réseaux sociaux, légal)
assets/                        CSS, JS, images du site
configurateur/                 Application React compilée (générée par le build)
src/                           Source React de l'application (Vite, racine = src/)
php/trace_module.php           Endpoint optionnel de traçage des configurations
TEST/                          Exemple de JSON + script Python d'import Archicad
```

## Déployer

1. `npm install` (si besoin)
2. `npm run build` → génère le dossier `configurateur/`
   (l'application est servie sous `/configurateur/`, intégrée par iframe
   dans `configurateur.php`)
3. Copiez sur votre hébergement PHP **uniquement les fichiers d'exécution** :
   - pages `.php` (`index`, `configurateur`, `mentions-legales`, `cgu`,
     `politique-confidentialite`, `send_newsletter`)
   - `includes/` (`head.html`, `footer.php`)
   - `assets/` (CSS, JS, images)
   - `configurateur/` (l'application compilée — c'est elle qui est servie)
   - `TEST/` (le JSON exemple est lié depuis le pied de page)
   - `php/trace_module.php` (si vous activez le traçage)

   **Inutile sur le serveur** (build local uniquement) : `node_modules/`
   (centaines de Mo, dépendances de build), `src/` (source React),
   `package.json` et `vite.config.js` (utilisés seulement par Vite
   au moment du build). Le dossier `configurateur/` contient déjà
   l'application compilée, autonome (HTML + CSS + JS).
4. Si le site est servi sous un sous-dossier, ajustez `base`
   dans `vite.config.js` avant de builder.
5. Renommez `htaccess.txt` en `.htaccess` dans `configurateur/`
   (politique iframe) — voir section « Sécurité iframe » ci-dessous.

## Traçage des configurations (optionnel)

Dans `src/App.jsx`, renseignez :

```js
const TRACE_URL = "/php/trace_module.php";
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

Sur le serveur qui héberge `configurateur/`, autorisez l'affichage en iframe
depuis votre seul domaine, via nginx :

```
add_header Content-Security-Policy "frame-ancestors https://votre-site.fr";
```

ou Apache (.htaccess) :

```
Header set Content-Security-Policy "frame-ancestors https://votre-site.fr"
```

**Important** : `frame-ancestors` compare l'**origine** (schéma + hôte) de la page
qui intègre l'iframe. La liste doit contenir exactement l'adresse du site déployé —
s'y déployez sur un sous-domaine, celui-ci doit figurer dans la liste, sinon le
navigateur bloque l'iframe. Ici le site est servi sur `https://archicad.baracca.fr`,
d'où le `htaccess.txt` :

```
Header always set Content-Security-Policy "frame-ancestors https://archicad.baracca.fr https://baracca.fr https://www.baracca.fr"
```
