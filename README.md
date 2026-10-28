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
configurateur.php              Page d'intégration de l'application (iframe) — réservée aux clients connectés
inscription.php                Création d'un compte client (accès au configurateur)
connexion.php                  Connexion client (email = identifiant)
deconnexion.php                Déconnexion client
compte.php                     Espace client : profil + configurations enregistrées
mentions-legales.php           Pages légales
cgu.php
politique-confidentialite.php
send_newsletter.php            Inscription newsletter (footer)
config.php                     Configuration générale (identifiants admin, chemins)
data/                          Stockage JSON (comptes, configurations, soumissions) — protégé par .htaccess
includes/head.html             En-tête + navigation (partagés)
includes/footer.php            Pied de page (newsletter, réseaux sociaux, légal)
includes/auth.php              Session client + limitation de débit des connexions
includes/users.php             Comptes clients (création, recherche, mot de passe)
includes/configurations.php    Configurations enregistrées par les clients
includes/soumissions.php       Configurations soumises via « Soumettre »
includes/admin-auth.php        Session administrateur + CSRF
includes/admin-head.php        En-tête du back-office
includes/admin-footer.php      Pied de page du back-office
admin/                         Back-office (tableau de bord, utilisateurs, soumissions)
assets/                        CSS, JS, images du site (dont admin.css pour le back-office)
configurateur/                 Application React compilée (générée par le build)
src/                           Source React de l'application (Vite, racine = src/)
php/api_session.php            API : état de la session client (pour l'application React)
php/api_configurations.php     API : enregistrer/lister/charger/supprimer une configuration
php/soumettre.php              API : bouton « Soumettre » (envoi de la configuration à Baracca)
php/trace_module.php           Endpoint optionnel de traçage des configurations
TEST/                          Exemple de JSON + script Python d'import Archicad
```

## Comptes clients, configurations et back-office

L'accès au configurateur nécessite un **compte client**, créé en ligne depuis
`inscription.php` : nom, prénom, adresse (numéro de rue, rue, code postal, ville),
référence cadastrale (facultative), email et mot de passe. L'email sert
d'identifiant de connexion (`connexion.php`).

Depuis l'onglet « Export JSON » du configurateur, un client connecté peut :

- **enregistrer** sa configuration pour la reprendre ultérieurement (bouton
  « Enregistrer cette configuration », liste « Mes configurations » avec
  Charger / Supprimer) — les configurations sont aussi listées sur `compte.php`,
  qui permet de les rouvrir directement dans le configurateur (`?config=<id>`) ;
- **soumettre** son dossier (bouton « Soumettre ») : la configuration est
  enregistrée dans `data/soumissions/` et l'architecte est averti par e-mail
  (`dev@baracca.fr`) ; il retrouve le fichier JSON correspondant dans le
  back-office (`admin/soumissions.php`).

Le **back-office** (`/admin/`) permet de gérer les comptes clients
(réinitialiser un mot de passe, supprimer un compte et ses configurations) et
de consulter les soumissions. Identifiants par défaut (à changer dans
`config.php` après la première connexion) : `admin` / `Baracca2025!`.

Stockage : fichiers JSON dans `data/` (même modèle que les sites invest et
blog), sans base de données. Le dossier est interdit au web par `data/.htaccess`
et son contenu n'est pas versionné (voir `.gitignore`).

Pour définir un nouveau mot de passe administrateur, générez son hash bcrypt :

```bash
php -r "echo password_hash('votre_mot_de_passe', PASSWORD_DEFAULT);"
```

puis remplacez `ARCHICAD_ADMIN_PASSWORD_HASH` dans `config.php`.

## Déployer

1. `npm install` (si besoin)
2. `npm run build` → génère le dossier `configurateur/`
   (l'application est servie sous `/configurateur/`, intégrée par iframe
   dans `configurateur.php`)
3. Copiez sur votre hébergement PHP **uniquement les fichiers d'exécution** :
   - pages `.php` (`index`, `configurateur`, `inscription`, `connexion`,
     `deconnexion`, `compte`, `mentions-legales`, `cgu`,
     `politique-confidentialite`, `send_newsletter`)
   - `config.php` (pensez à y changer le mot de passe administrateur)
   - `includes/` (`head.html`, `footer.php`, `auth.php`, `users.php`,
     `configurations.php`, `soumissions.php`, `admin-auth.php`,
     `admin-head.php`, `admin-footer.php`)
   - `admin/` (back-office)
   - `assets/` (CSS, JS, images)
   - `configurateur/` (l'application compilée — c'est elle qui est servie)
   - `data/.htaccess` (le dossier `data/` et son contenu sont créés
     automatiquement à la première utilisation)
   - `php/` (`api_session.php`, `api_configurations.php`, `soumettre.php`,
     et `trace_module.php` si vous activez le traçage)
   - `TEST/` (le JSON exemple est lié depuis le pied de page)

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
