# Journal général — Développement plugins TKD Claude

Ce fichier est le journal de **contexte et d'environnement** du projet, à la différence des fichiers `REALISATION.md`/`EVOLUTION.md` de chaque plugin (qui parlent du code). Il répond à la question « où en est-on, sur quelle machine, avec quel Git, et que faut-il faire ensuite ? ». À tenir à jour à chaque changement de machine ou d'environnement.

## 1. Le projet en une phrase

Trois plugins WordPress développés pour le club **TKD Claira** (taekwondo), avec l'aide de Claude Code, hébergés sur OVH (un site de test + un site de production) :

| Plugin | Rôle | Suivi documentaire |
|---|---|---|
| [sp_build](sp_build/) | Gestion complète du club : adhésions, pointage/QR, planning, jury/examens, compétitions, PWA membres, API publique | `CLAUDE.md` + Git local (36 commits) + [REALISATION.md](sp_build/REALISATION.md)/[EVOLUTION.md](sp_build/EVOLUTION.md) créés le 11/09/2026 |
| [sp-compta](sp-compta/) | Trésorerie associative (dépenses, recettes, devis, factures, sponsors) | `CLAUDE.md` très détaillé + `correctif.md` + [REALISATION.md](sp-compta/REALISATION.md)/[EVOLUTION.md](sp-compta/EVOLUTION.md) créés le 11/09/2026 |
| [tkd-cotisations](tkd-cotisations/) | Cotisations, paiements et reçus PDF | Aucun suivi historique retrouvé — [REALISATION.md](tkd-cotisations/REALISATION.md)/[EVOLUTION.md](tkd-cotisations/EVOLUTION.md) créés le 11/09/2026, à alimenter à partir de maintenant |

## 2. Environnement technique

- **Aucun environnement WordPress/PHP local** : d'après les `CLAUDE.md` de `sp_build` et `sp-compta`, il n'y a ni binaire PHP ni WordPress installé dans l'environnement de développement (sandbox Claude Code). Tout se vérifie par relecture attentive du code, puis déploiement réel sur le **site de test OVH**.
- **Déploiement = copie de fichiers (FTP), pas de build** : les trois plugins fonctionnent « sans outillage » — pas de `npm install`, pas de compilation. `sp-compta` a un `composer.json`, mais uniquement pour les tests PHPUnit en développement ; `vendor/` n'est jamais copié sur le site.
- **Deux environnements OVH** : un site de test (utilisé pour valider avant tout déploiement en production) et le site de production du club — confirmé dans `sp_build/CLAUDE.md`.
- **Machine actuelle** (celle où ce journal est écrit, 11/09/2026) : Windows 11, dossier `Developpement plugin TKD Claude`. Aucune configuration Git globale n'existe sur cette machine (`git config --global user.name/user.email` vides) et l'outil `gh` (GitHub CLI) n'est pas installé.

## 3. État Git au 11/09/2026 — le cœur du problème

Voici exactement ce qui a été constaté en explorant ce dossier aujourd'hui :

- **`sp_build/`** a **son propre dépôt Git local**, avec un historique réel et propre : 36 commits, du 01/09/2026 au 10/09/2026, branche `master`, arbre de travail propre (rien en attente). **Mais aucun `remote` n'est configuré** — c'est-à-dire qu'il n'a jamais existé de destination (GitHub, GitLab...) vers laquelle pousser ce dépôt. Ce n'est donc pas un push qui a échoué techniquement : c'est qu'il n'y a jamais eu de dépôt distant créé.
- **`sp-compta/`** et **`tkd-cotisations/`** : **aucun dépôt Git**, ce sont de simples dossiers de fichiers.
- **Le dossier racine** (`Developpement plugin TKD Claude/`) a un dépôt Git tout juste initialisé (aucun commit pour l'instant), qui pour l'instant ne contient rien.
- **Fichiers de documentation manquants** : `sp_build/CLAUDE.md` et `sp-compta/CLAUDE.md` font référence à un dossier `../md/` (juste à côté des plugins, donc à la racine de ce projet) contenant des fichiers importants : `doleances.md`, `01-fonctionnalites.md`, `02-etat-des-lieux.md`, `03-proposition-refonte.md`, `04-journal-modifications.md`, `05-cartographie-css.md`, `06-renouvellement-saison.md`, `07-refonte-init.md`, et `spec-fonctionnelle-module-compta.md`. **Ce dossier `md/` n'existe pas ici.** Il a très probablement été créé et utilisé sur l'autre ordinateur, et n'a jamais été copié/transféré sur celui-ci.

**Conclusion** : le point bloquant n'est pas un push qui a raté, c'est qu'**aucun dépôt distant (GitHub ou autre) n'a jamais été créé pour ce projet**, et qu'**un dossier de documentation (`md/`) existe peut-être uniquement sur l'autre ordinateur**. Tant que ce dossier `md/` n'est pas retrouvé et copié ici, il est possible qu'il y ait des demandes en attente (`doleances.md`) ou des décisions d'architecture (`03-proposition-refonte.md`) invisibles depuis cette machine.

## 4. Plan d'action pour lundi (retour au bureau)

### Étape 0 — avant de toucher à Git : sécuriser les fichiers manquants

Sur l'**autre ordinateur** (celui où le projet a démarré), avant toute manipulation Git, faire une copie de sécurité complète du dossier `Developpement plugin TKD Claude` (ou au moins du dossier `md/` s'il n'existe qu'à côté) sur une clé USB, un espace cloud (OneDrive/Google Drive) ou par email. **Ne rien supprimer sur l'autre ordinateur tant que tout n'est pas confirmé retrouvé ici.**

### Étape 1 — créer un compte GitHub (si pas déjà fait)

GitHub est gratuit, y compris pour des dépôts privés. Créer un compte sur [github.com](https://github.com) si ce n'est pas déjà fait. *(Si un compte existe déjà, passer à l'étape 2.)*

Pour un débutant complet en Git, une alternative plus visuelle à la ligne de commande : **[GitHub Desktop](https://desktop.github.com/)** — une application avec des boutons (« Publish », « Commit », « Push ») plutôt que des commandes à taper. Tout ce qui suit peut aussi se faire avec cette application ; les commandes ci-dessous sont l'équivalent « ligne de commande ».

### Étape 2 — configurer l'identité Git (une seule fois, sur chaque ordinateur)

```bash
git config --global user.name "Votre Nom"
git config --global user.email "votre.email@example.com"
```

### Étape 3 — le piège à éviter : ne pas imbriquer les dépôts Git

`sp_build` a déjà son propre `.git`. Si l'on fait un `git add` depuis le dossier racine sans faire attention, Git essaiera de traiter `sp_build` comme un sous-module cassé au lieu de suivre ses fichiers normalement — source de confusion classique pour un débutant. **La solution la plus simple : un dépôt GitHub séparé par plugin**, plutôt qu'un seul grand dépôt pour tout.

Structure recommandée : **3 dépôts GitHub indépendants**, un par plugin (`sp_build`, `sp-compta`, `tkd-cotisations`). Le dossier racine et ce `JOURNAL.md` peuvent rester simplement en local (pas besoin de Git pour un seul fichier de notes), ou être ajoutés à l'un des trois dépôts si préféré.

### Étape 4 — pousser `sp_build` (le plus simple : il a déjà son historique)

1. Sur github.com, créer un nouveau dépôt vide nommé par exemple `sp_build` (**ne pas** cocher « Add a README », le dépôt local en a déjà un historique).
2. Dans un terminal, se placer dans le dossier `sp_build` puis :

```bash
git remote add origin https://github.com/VOTRE-COMPTE/sp_build.git
```

```bash
git branch -M main
```

```bash
git push -u origin main
```

Les 36 commits et tout l'historique partiront d'un coup — rien à recréer.

### Étape 5 — créer un dépôt et pousser `sp-compta`

1. Sur github.com, créer un nouveau dépôt vide `sp-compta`.
2. Dans le dossier `sp-compta` :

```bash
git init
```

```bash
git add .
```

```bash
git commit -m "Premier commit sp-compta"
```

```bash
git branch -M main
```

```bash
git remote add origin https://github.com/VOTRE-COMPTE/sp-compta.git
```

```bash
git push -u origin main
```

### Étape 6 — même chose pour `tkd-cotisations`

Répéter exactement les commandes de l'étape 5 en remplaçant `sp-compta` par `tkd-cotisations` partout (nom du dépôt GitHub et dossier local).

### Étape 7 — retrouver le dossier `md/` manquant

Une fois le dossier `md/` de l'autre ordinateur récupéré (étape 0), le copier à la racine de `Developpement plugin TKD Claude/` (à côté de `sp_build`, `sp-compta`, `tkd-cotisations`). Vérifier ensuite s'il contient des demandes non traitées (`doleances.md`) à reporter dans les fichiers `EVOLUTION.md` correspondants.

### Ensuite, pour chaque nouvelle session de travail

```bash
git add .
```

```bash
git commit -m "Description courte de ce qui a changé"
```

```bash
git push
```

## 5. Repères pour la suite

- Mettre à jour ce journal à chaque fois que l'environnement change (nouvelle machine, nouveau compte GitHub, dossier retrouvé ou déplacé).
- Mettre à jour `REALISATION.md` de chaque plugin à la fin de chaque session (une ligne datée suffit) — ne pas attendre d'avoir tout oublié.
- Mettre à jour `EVOLUTION.md` dès qu'une idée ou une demande apparaît, même non urgente.

## 6. Refonte ou restructuration progressive ? (avis du 06/10/2026)

Question de l'utilisateur : faut-il réécrire et fusionner tous les plugins en un seul, selon les bonnes pratiques PHP (structure des fichiers, taille, tables…), ou restructurer peu à peu pour sauver le travail fait ?

**Mesures au 06/10/2026** : sp_build ~35 000 lignes (PHP + JS), sp-compta ~10 800, tkd-cotisations ~2 600, claira-tkd-parcours ~2 500 ; une vingtaine de tables. Plus gros fichiers de sp_build : `class-admin.php` 4 900 lignes (menu, réglages, application mobile complète, API), `class-admin-members.php` 4 670, `class-db.php` 3 330. Défauts constatés : fonctionnalités à moitié construites (sondage — rétabli le 06/10 —, notifications push sans moteur d'envoi), doublons (pointage QR, routes d'API), sécurité inégale selon les fichiers.

**Avis : pas de réécriture d'un coup — restructuration progressive, module par module, chaque étape déployable.**
- Contre la réécriture totale : site en production pour un vrai club (des mois de double maintenance, bascule d'un coup, migration de ~20 tables) ; le code contient beaucoup de règles métier apprises sur le terrain (cf. `REALISATION.md`) qu'une réécriture perdrait en partie ; aucun environnement de test local ; une réécriture sans tests ni règles finit comme l'ancien code.
- Fusionner les plugins n'est pas nécessaire en soi : des plugins séparés reliés par des points d'accroche (filtres / actions) sont une bonne pratique WordPress. Le problème est l'organisation **à l'intérieur** de sp_build. Tout au plus, intégrer un jour tkd-cotisations à sp_build comme module.

**Étapes proposées :**
1. **Environnement de test local** (LocalWP, ou au minimum PHP pour vérifier la syntaxe avant déploiement) — levier n°1.
2. **Règles pour tout nouveau code** (déjà suivies depuis septembre) : un module = un fichier qui s'enregistre lui-même, ses tables avec numéro de version, contrôle des droits + nonces partout (modèle : `class-mail-queue.php`).
3. **Découper les gros fichiers quand on y touche** : d'abord sortir l'application mobile de `class-admin.php` (JS/CSS dans de vrais fichiers), puis découper `class-db.php` par domaine.
4. **Ménage** : supprimer les doublons ; terminer ou retirer les fonctionnalités à moitié construites (inventaire à faire).
5. **Gestion unique des évolutions de la base** (au lieu de créations de tables dispersées).
6. Plus tard si utile : espaces de noms + chargement automatique, tests automatiques sur les calculs sensibles (verdict des passages, IK, cotisations).

**Une refonte deviendrait justifiée** si l'architecture empêchait une fonctionnalité importante (ex. plusieurs clubs), si une faille ne pouvait pas se corriger proprement, ou si une version de WordPress/PHP rendait le code incompatible — pas le cas aujourd'hui.

**À faire avant de trancher** : retrouver le dossier `md/` (resté sur l'autre ordinateur, cf. étape 7) — il contient une proposition de refonte déjà rédigée (`03-proposition-refonte.md`, `07-refonte-init.md`) à confronter à cet avis.

## 7. Outils de vérification installés (06/10/2026) — étape 1 du plan

Sur cette machine (Windows 11), installés via `winget` et la méthode officielle de Composer :
- **PHP 8.4.25** (même version que la prod OVH : PHP 8.4.22, MySQL 8.0.46), avec un `php.ini` qui active openssl, mbstring, curl, zip, intl, sodium, fileinfo (dans le dossier du paquet winget `PHP.PHP.8.4`). Sert à vérifier la syntaxe (`php -l`) et à lancer les outils.
- **Composer 2.10.3** (`composer.phar` + `composer.bat` dans le dossier de PHP, donc déjà dans le PATH).
- **Node.js 24 LTS** : vérifier la syntaxe des fichiers JavaScript (`node --check`).
- ⚠️ **Le réseau filaire de cet ordinateur bloque `downloads.php.net` / `windows.php.net`** (redirection vers la page de blocage DNS4EU) : l'installation de PHP s'est faite via le partage de connexion du téléphone. Même chose à prévoir pour une mise à jour de PHP.
- Pas encore installé : **Local** (WordPress local, ~600 Mo) — seulement si une étape le justifie (migrations de base, gros découpages). Décision du 06/10/2026 : **on travaille avec le site de test OVH (staging)** + les outils ci-dessous.

**Hébergement OVH — passage de STARTUP à PERFORMANCE le 07/10/2026** (`tkdclad.cluster021.hosting.ovh.net`, même hébergement pour la prod, le site de test `dev.tkdclaira.fr` et 3 autres sites) :
- Cause : l'éditeur Elementor ne s'ouvrait plus (« Impossible de modifier ? »). Mesuré : l'offre STARTUP ne traitait qu'environ 2 requêtes PHP à la fois (~0,7 s chacune) ; l'éditeur en envoie une trentaine d'un coup → file d'attente, erreurs 500 (Apache), aperçu de la page en échec. Configuration déjà optimale (PHP 8.4, moteur FPM, mode production) : seule l'offre limitait.
- Après passage en PERFORMANCE (2 vCores, 4 Go garantis) : 20 requêtes simultanées en 5,5 s sans erreur (avant : 14 s dont 4 erreurs 500) ; éditeur Elementor de l'accueil ouvert en ~20 s. IP inchangée (188.165.53.185, zone DNS à jour). Option CDN incluse dans l'offre mais non activée (« Option CDN : Non »).

**Site de test OVH (staging) — état au 06/10/2026 :**
- C'est le « site de test » de `sp_build/CLAUDE.md` (pas un troisième environnement).
- Sa base **n'est pas une copie à jour de la prod** : données anciennes, à rafraîchir avant les gros chantiers (export de la base de prod → import sur le test, en ajustant l'adresse du site — `siteurl` / `home` et liens internes).
- **Envoi d'emails non vérifié** : avant tout test, et obligatoirement après une copie de la prod (vraies adresses des familles), couper les envois réels sur le test (WP Mail SMTP → Réglages → Divers → « Ne pas envoyer », ou redirection vers une adresse de l'utilisateur) — sinon vœux d'anniversaire, rappels entraîneurs et file d'envoi écriraient à de vrais adhérents. À prévoir dans le script de copie : remplacer les adresses des adhérents par des adresses factices.
- Accès : l'utilisateur se connectera (navigateur intégré, console OVH ou phpMyAdmin) quand une étape le demandera — ne jamais lui demander ses mots de passe.

Dossier **[outils/](outils/)** à la racine (jamais copié sur le site, `vendor/` non versionné — refaire `composer install` dans `outils/` sur un autre ordinateur) :
- `composer.json` : PHP_CodeSniffer + règles WordPress (WPCS) + PHPCompatibilityWP, PHPStan + extension WordPress.
- `phpcs.xml.dist` : règles de **sécurité** WordPress + **compatibilité PHP 8.4** (le style complet viendra plus tard, module par module).
- `phpstan.neon.dist` (niveau 1 : erreurs franches) + `phpstan-constantes.php` (constantes des plugins).
- **`verifier.ps1`** : à lancer avant chaque déploiement (`.\verifier.ps1`, ou `.\verifier.ps1 -Rapide` pour la seule syntaxe).

**Premier état des lieux (06/10/2026) :**
- Syntaxe : **133 fichiers PHP et 4 fichiers JavaScript sans erreur** (y compris tout le code écrit le 06/10, jusque-là jamais vérifié).
- PHPStan : 17 remarques, dont **un vrai reste de code mort** — `sp_build/includes/class-pdf.php` : l'impression `?sp_cal_print=liste_groupe` appelle `render_liste_groupe()` qui n'existe pas (aucun bouton ne l'utilise ; à supprimer lors du ménage). Le reste : faux positifs (variables des gabarits `templates/`, `$msg` de `class-passages.php`) ou détails (`DOING_AJAX` → `wp_doing_ajax()`, variables inutilisées dans `tkd-cotisations/TkdPDF.php`).
- PHP_CodeSniffer : **aucune incompatibilité PHP 8.4** ; **1 937 remarques de sécurité** à trier, pas autant de failles : 538 noms de table insérés dans les requêtes (faux positifs le plus souvent), 435 affichages non échappés, 503 traitements de formulaire sans vérification de nonce signalée (souvent vérifiée ailleurs), 274 entrées non nettoyées / non « unslash », 97 requêtes non préparées, 57 `wp_redirect` (préférer `wp_safe_redirect`). Ce sera la matière des étapes de ménage, en commençant par les requêtes non préparées et les formulaires sans nonce.

**Audit de sécurité du 07/10/2026 (tri des remarques ci-dessus) :**
- **SQL : aucune faille d'injection.** Les 97 requêtes « non préparées » et les 538 variables insérées dans les requêtes ont été vérifiées : noms de tables, listes d'identifiants forcées en entiers, colonnes écrites en dur, ou requêtes construites par morceaux puis passées dans `prepare()`. Ce sont des faux positifs de l'outil.
- **Actions AJAX et formulaires** : nouveau script `outils/audit-actions.php` (`php audit-actions.php ..`), qui vérifie pour chacune des **114 actions** déclarées si elle contrôle un jeton (nonce) et les droits. Toutes les actions réservées aux utilisateurs connectés sont protégées. Les actions publiques (pointage QR, application entraîneur, réponse aux inscriptions) sont protégées par le PIN du club ou le jeton personnel de l'adhérent (64 caractères aléatoires).
- **API REST (`/wp-json/…`)** : testée en anonyme sur le site réel → toutes les routes refusent sans jeton, PIN ou clé (401 / 403).
- **Un vrai problème trouvé et corrigé** : le calendrier (`sp_cal_get_events`, ouvert aux visiteurs) renvoyait à n'importe qui les anniversaires des adhérents avec nom complet et âge, mineurs compris (cf. `sp_build/REALISATION.md`, 07/10/2026).
- **Un point faible à traiter** : le PIN du pointage (4 chiffres, 5 040 combinaisons, aucune limite d'essais) — cf. `sp_build/EVOLUTION.md`, 07/10/2026.
- Reste à trier : les 435 affichages non échappés (risque de script injecté, surtout là où s'affichent des textes saisis par les familles) — prochaine étape.

---

## 8. Avancement de la restructuration au 07/10/2026 (sp_build)

| Étape du plan (section 6) | État | Détail (voir `sp_build/REALISATION.md`, 07/10/2026) |
|---|---|---|
| 1. Outils de vérification | ✅ | PHP 8.4, Composer, Node, PHPStan, PHPCS ; `outils/verifier.ps1`, `audit-actions.php`, `preparer-deploiement.ps1`, `deplacer-methodes.php`, `comparer-fonctions.php` |
| 2. Règles pour le nouveau code | ✅ | modules autonomes, nonces + droits, tables via `class-schema.php` (règle dans `sp_build/CLAUDE.md`) |
| 3. Découper les gros fichiers | ✅ | phases A (pages admin), C (adhérents), D (requêtes par domaine), B (application de pointage + `assets/pwa/`) — `class-admin.php` 4 934 → 307 lignes, `class-admin-members.php` 4 665 → 396, `class-db.php` 3 334 → 918 ; déplacement pur en *traits*, 0 fonction modifiée |
| 4. Ménage et sécurité | ✅ | fuite des anniversaires corrigée, PIN bloqué après 10 essais (y compris routes de l'extension « SP Pointage QR »), code mort, doublons, calendrier public retiré, 457 affichages triés (aucune faille, 3 oublis corrigés) |
| 5. Gestion unique de la base | ✅ | `class-schema.php` : vérification des tables une fois après chaque déploiement au lieu de ~95 requêtes par page |
| 6. Tests automatiques, espaces de noms | ✅ tests (08/10) | PHPUnit, 32 tests (passages, IK, catégories d'âge) ; espaces de noms non retenus — détail plus bas |

**Tout est en production** (dernière version déployée : `710f384`, vérifiée le 07/10/2026 à 15h55 — pages d'administration, application en mode PIN, hors connexion). Restent à essayer en conditions réelles : le **scan d'une carte QR** et le **lien personnel d'un entraîneur** dans l'application.

**Leçons de déploiement (07/10/2026)** :
- Une panne (« erreur critique ») a été causée par un `class-admin.php` arrivé incomplet sur le serveur → toujours utiliser `outils/preparer-deploiement.ps1` (dossier + tailles exactes), FTP en mode **binaire**, comparer les tailles après l'envoi ; garder un dossier `RESTAURATION_…` le temps de vérifier.
- Copier le **contenu** du dossier de déploiement à sa place, jamais le dossier lui-même sur le serveur.
- La prod et le site de test partagent le même hébergement / FTP : **vérifier le dossier distant** (un envoi est parti sur le site de test au lieu de la prod).
- Les fichiers de restauration se créent avec Git Bash (`git show … > fichier`), pas avec PowerShell 5.1 (`>` ajoute un BOM UTF-8 qui casse le PHP).

**Hébergement** : passé de STARTUP à **PERFORMANCE** (section 7) — l'éditeur Elementor fonctionne de nouveau.

**Site de test (`dev.tkdclaira.fr`)** : protégé par une authentification HTTP (« Staging - Accès restreint ») que le navigateur intégré de l'application ne sait pas ouvrir → vérifications par **Claude in Chrome** (extension installée et reliée le 07/10/2026). Il a reçu le plugin complet `710f384`. Sa base date de la saison précédente (pas de cours en octobre 2026) et sa table `mod237_sp_adhesions_pending` n'a pas les colonnes récentes (ajout impossible, signalé par `class-schema.php`) → à régler lors du rafraîchissement de sa base (copie de la prod avec adresses neutralisées). Emails du site de test : désactivés (« ENVOI DÉSACTIVÉ » de WP Mail SMTP). **→ Base rafraîchie le 08/10/2026** (copie de la prod, voir plus bas).

**Extension « SP Pointage QR » désactivée en prod le 07/10/2026** (pointage pas encore utilisé en ce début de saison, donc directement en prod). Avant : code de prod relu (292 lignes, mêmes 4 routes et même format de réponse que sp_build ; elle contenait un PIN de secours écrit en dur et le préfixe `mod237_` en dur) et aucune autre extension (sp-compta, tkd-cotisations, claira-tkd-parcours) ne l'utilise. Après : routes `/pointage/*` testées (cours du jour, mauvais PIN refusé, scan / rattrapage / lot avec carte inconnue → messages accentués de sp_build), page `/app/` sans erreur, aucune présence enregistrée. **Reste** : confirmer au premier vrai scan d'une carte, puis supprimer l'extension (réactivation en un clic d'ici là si besoin).

**Extension « SP Member Cards (Add-on) » désactivée en prod le 07/10/2026** : premier essai jamais terminé (5 fichiers, ~230 lignes, auteur « Ton Nom »). Sa page `/carte-interactive/?token=` ne montrait jamais de carte (sp_build redirige tout lien `?token=` valide vers la fiche membre) et sa génération de carte PDF + QR ne pouvait pas marcher (bibliothèques `vendor/` jamais installées, aucun lien dans l'administration). sp_build imprime déjà les cartes avec QR (« 🖨️ Cartes membres »). Page « carte-interactive » retirée par l'utilisateur, puis extension désactivée. Vérifié : accueil sans erreur, `/carte-interactive/` en 404, fiche membre complète, anciens liens `/?token=` et `/carte-interactive/?token=` redirigés vers `/fiche-membre/`. **Reste** : supprimer les deux extensions ensemble après un premier vrai scan de carte.

**Ménage des extensions en prod (07–08/10/2026)**, pour éviter toute confusion. Avant chaque suppression : programme de désinstallation lu (aucun ne touche aux tables du club ; seuls Brevo et WPForms effacent leurs propres réglages), dépendances cherchées dans nos 4 extensions, contenus des pages contrôlés.
- **Supprimées par l'utilisateur** : `sp_build_backup` (copie de sp_build du 01/09/2026 = commit `943a50f`, même nom « SportPress Calendar PRO » → risque d'erreur critique si activée par erreur à côté de sp_build ; sans désinstallation), SP Member Cards, Taekwondo Progression Master (« Gemini Dev », remplacée par Claira TKD Parcours), Brevo (les mails passent par WP Mail SMTP, réglé sur Brevo), Login or Logout Menu Item (doublon), SportsPress gratuit (inclus dans SportsPress Pro), Essential Addons, Essential Blocks, Templately.
- **Pages retirées** : `/carte-interactive/` et `/progression/` (affichait le shortcode brut `[tkd_progression]`).
- **À garder** : SportsPress Pro (accueil, 65 joueurs, 20 sponsors ; sp_build lit `sp_player` / `sp_staff`), sp_build, SP Compta, TKD Cotisations, Claira TKD Parcours, Elementor + Pro, WP Mail SMTP, Login Logout Menu, Disable XML-RPC-API.
- **Supprimées ensuite (08/10)** : Insert PHP Code Snippet, Pricing Table by Supsystic, Widget Importer & Exporter, WPForms Lite. Il ne reste que les 10 extensions actives + **SP Pointage QR** (désactivée, à supprimer après le premier vrai scan). Revérifié après : pages publiques, `/app/`, éditeur Elementor, pages d'administration sp_build, fiche membre, pointage — tout fonctionne.
- Vérifié le 08/10 : pages publiques, `/app/`, éditeur Elementor (1,4 s), 9 pages d'administration sp_build, fiche membre, pointage (cours du soir listé), WP Mail SMTP → Brevo : tout fonctionne.

**Étape 6 — tests automatiques (08/10/2026)** :
- **PHPUnit 12** ajouté à `outils/` (`composer.json`, `phpunit.xml.dist`, dossier `outils/tests/`, jamais déployé), lancé par `verifier.ps1` ou `vendor\bin\phpunit`. Les tests chargent les vrais fichiers des plugins **sans WordPress** : `tests/bootstrap.php` remplace les quelques fonctions WordPress utilisées et `$wpdb` (`FauxWpdb`, résultats préparés d'avance, écritures notées) ; méthodes privées appelées par réflexion — **aucune ligne des plugins modifiée pour les tests**.
- **32 tests, 0,1 s** : verdict des passages de grade (18 — keup : majorité, égalité, arbitrage, seuils de notes et mesures ; Poom : moyenne, seuil, plancher, mesure ramenée sur 10), indemnités kilométriques (8 — tarif × km × allers-retours, km exceptionnels, bureau exclu, arrondis), catégories d'âge (6).
- **Défaut trouvé par les tests** : l'âge était calculé en divisant des jours par 365,25 → un enfant né un 1er septembre était classé un an trop jeune certaines années (ex. né le 01/09/2020 : Baby au lieu d'Enfant en 2026), dans sp_build **et** dans TKD Cotisations.
- **Nouvelle règle des catégories (décision de l'utilisateur, 08/10/2026)** : la **classe scolaire à la rentrée**, d'après la seule année de naissance — **Baby = maternelle, Enfant = primaire (CP → CM2), Ado/adulte = collège et plus** ; « Tout âge » pour le Renfo inchangé ; la tranche **« Adulte » (15 ans et plus) n'est plus attribuée** (aucun tarif ne la visait, le reste du site la traitait déjà comme Ado/adulte). Appliquée aux trois endroits : formulaire d'adhésion / renouvellement, bascule de rentrée (sp_build `SpCalPro_DB::categorie_scolaire()` + `annee_saison_categories()` : à partir de juin, la rentrée de septembre suivante), bouton « Recalculer » de TKD Cotisations. Vérifiée par les tests sur 30 rentrées.
- **Déployé en prod le 08/10/2026** : sp_build `75666d1` (3 fichiers) + TKD Cotisations **v1.2.0** (qui contenait aussi, jamais déployé jusque-là, l'historique des rappels de dépôt de chèques du 02/10 — la prod était restée en v1.1.0). Tailles vérifiées, formulaires, page Adhérents, Cotisations → Paramètres, fiche membre : OK.
- **Aucune fiche modifiée par le déploiement** : 16 adhérents de Taekwondo changeront de catégorie quand l'utilisateur lancera la bascule (avec aperçu) ou le recalcul — 8 Baby → Enfant (nés en 2019-2020), 6 Enfant → Ado/adulte (nés en 2014-2015), les 2 « Adulte » → Ado/adulte.
- **Espaces de noms + chargement automatique : non retenus** — renommer toutes les classes des 4 plugins, beaucoup de risque et aucun gain pour le club ; à réserver à du code entièrement nouveau.
- **Leçon** : avant de déployer un fichier d'une autre extension, comparer avec la version **en ligne** (éditeur d'extensions, en lecture) et pas seulement avec git — TKD Cotisations en prod était plus ancienne que le dépôt.

- **Bascule lancée par l'utilisateur le 08/10/2026, vérifiée** : tous les adhérents **actifs** sont dans la catégorie de leur classe — Taekwondo : 18 Baby, 51 Enfant, 31 Ado/adulte ; Renfo : 16 « Tout âge » (non touchés) ; plus aucun « Adulte ». 6 fiches **inactives** (saison 2025/2026, non renouvelées) gardent l'ancienne catégorie, comme prévu (la bascule ne traite que les actifs) ; en cas de renouvellement, le formulaire recalcule la catégorie.

**Rafraîchissement du site de test (08/10/2026)** — la base du test (`tkdcladst`, MySQL 8.4, même préfixe `mod237_`) est désormais une **copie de la prod** (`tkdclad766`) du 08/10 à 9h23 :
1. `wp-config.php` **du test** : `WPMS_ON` + `WPMS_DO_NOT_SEND` (WP Mail SMTP bloque tout envoi, quelle que soit la base — vérifié : case verrouillée par la constante) et `DISABLE_WP_CRON`.
2. OVH : sauvegarde de la base du test (copie locale `tkdcladst.sql`), sauvegarde + téléchargement de la prod, « Importer un fichier » sur le test avec « Vider la base ».
3. **1er import refusé par OVH** (« Required user is not in the instance: tkdclad766 ») : la sauvegarde contient 4 vues (`mod237_uap_*_view`, restes d'Uncanny Automator) avec `DEFINER=\`tkdclad766\`@\`%\``. Corrigé en retirant ces 4 mentions : `gzip -dc sauvegarde.gz | sed 's#DEFINER=\`tkdclad766\`@\`%\` ##g' | gzip -9 > corrige.sql.gz` (Git Bash). 2e import : 263 objets, complet.
4. **`outils/rafraichir-site-test.sql`** exécuté dans phpMyAdmin (base du test ; garde-fou `DATABASE() <> 'tkdclad766'` dans chaque requête) : `siteurl` / `home` → `https://dev.tkdclaira.fr` ; « .invalid » ajouté aux 12 comptes et à l'adresse admin ; abonnements aux notifications (5) supprimés ; **toutes les adresses email des 66 tables `mod237_sp*`** (324 colonnes texte/JSON : adhérents, demandes, entraîneurs, trésorerie…) → `…@domaine.fr.invalid` ; contrôle final : 0 adresse restante. Tables WordPress (options, métadonnées) non touchées (valeurs sérialisées).
5. Plugins du test alignés : sp_build `75666d1`, TKD Cotisations v1.2.0 ; **Elementor, Elementor Pro, Claira TKD Parcours et Login Logout Menu manquaient sur le serveur du test** → dossiers copiés depuis la prod puis **réactivés à la main** (WordPress les avait désactivés en les trouvant absents). 10 extensions actives, comme en prod.
6. Elementor → Outils → « Remplacer l'URL » (`https://tkdclaira.fr` → `https://dev.tkdclaira.fr`).
- Vérifié (Claude in Chrome) : pages publiques avec en-tête Elementor, éditeur Elementor, 12 pages d'administration, fiche membre, pointage (cours du jour), « ENVOI DÉSACTIVÉ ». Restent quelques images (logo de l'application, affiche des événements) pointant vers la prod — sans conséquence. Le test est en WordPress 7.1 (7.1.3 proposé). **Connexion au test : identifiants de la prod** (comptes copiés). Anciennes extensions inactives encore présentes sur le test (sp_build-backup, SP Member Cards, SP Pointage QR, TKD Progression, TKD Competition Manager…) — à supprimer si l'on veut un test identique.
- **Pour le prochain rafraîchissement** : mêmes étapes ; vérifier d'abord que les dossiers de toutes les extensions actives de la prod existent sur le test.

**Nettoyage de la base (08/10/2026)** — script `outils/nettoyer-base.sql`, passé d'abord sur le site de test puis sur la prod (après sauvegarde OVH ; exécuté par l'utilisateur dans phpMyAdmin). La suppression des extensions (07–08/10) n'avait retiré que leurs fichiers : leurs données restaient en base.
- **Lot 1** : révisions des pages — 1 558 supprimées, les 10 plus récentes de chaque page gardées (485). À compléter dans le `wp-config.php` de prod par `define( 'WP_POST_REVISIONS', 10 );`.
- **Lot 2** : 4 vues + 160 tables d'extensions disparues (WP ERP, WooCommerce / WooPayments, Uncanny Automator — dont les vues qui avaient bloqué le 1er import du test —, All in One SEO, WPForms, MonsterInsights, Jetpack, Ultimate Member, Supsystic, Essential Blocks).
- **Lot 3** : 20 anciennes tables de sp_build (`sp_cal_v6_*`, `sp_cal_v7_*`, `sp_cal_test_*`, `sp_cal_people`, `sp_cal_presence`, `sp_cal_categories`, `calendrier_notes`, `sp_cal_grade_correspondance`, `sp_exam_grade_progression`) — lues par aucun des 4 plugins.
- **Lot 4** : 452 réglages et 112 données temporaires d'extensions disparues (dont une clé d'API « iwc » avec droits de création / suppression d'utilisateurs), 11 contenus orphelins, 513 tâches planifiées (+ 1 549 lignes de journal) de WooCommerce / AIOSEO / WPForms, 135 réglages d'utilisateurs, 14 037 métadonnées orphelines ; puis `OPTIMIZE TABLE`.
- **Résultat prod** : 263 → **79 tables**, 68,1 → **15,3 Mo** ; données du club intactes (116 fiches, 174 présences, 27 dépenses, 61 pages). Vérifié : 8 pages publiques, éditeur Elementor, 26 pages d'administration, fiche membre, pointage, mails toujours envoyés par Brevo.
- **Gardés volontairement** : tables d'Elementor, WP Mail SMTP, Action Scheduler, SportsPress ; les anciennes tables du jury (sp_build les recrée encore — retirer d'abord `create_jury_tables()`) ; les 12 tableaux TablePress (la page publiée « Horaires d'entraînement » affiche `[table id=7 /]` en brut, extension absente — à mettre en brouillon ou réinstaller) ; la corbeille WordPress.

- `define( 'WP_POST_REVISIONS', 10 );` ajouté au `wp-config.php` de prod le 08/10 (site vérifié après).

**Journal des erreurs public — corrigé le 08/10/2026** : la prod a `WP_DEBUG` + `WP_DEBUG_LOG` activés (affichage désactivé) ; le journal `wp-content/debug.log` était **téléchargeable par n'importe qui** (`https://tkdclaira.fr/wp-content/debug.log` → 200) : **78 Mo**, 279 000 lignes depuis le 15/03/2026, **118 adresses email** et les chemins du serveur. Corrigé par l'utilisateur : fichier supprimé (un nouveau repart vide) et règle ajoutée **en haut du `.htaccess` à la racine** du site (une 1re tentative dans `wp-content/.htaccess` n'avait pas pris effet) :
```apache
<Files "debug.log">
    Require all denied
</Files>
```
Vérifié : `debug.log` → **403** sur la prod et sur le site de test (même règle), sites fonctionnels (pages publiques, administration, application, API, éditeur Elementor). Le journal reste lisible par FTP. **Piste** : voir ce qui le remplissait (78 Mo en 7 mois) en surveillant le nouveau fichier quelques jours.

**Documents d'adhésion protégés (08/10/2026)** — constat : les pièces déposées par le formulaire d'adhésion (`uploads/sp-adhesions-docs/` : 67 certificats médicaux, attestations RC, décharges, bons CAF, photos — 119 fichiers) étaient **téléchargeables sans connexion** par leur adresse (seule la liste du dossier était bloquée ; noms souvent devinables). Correction sp_build `249c00a` (nouveau `class-docs-adhesion.php`) : règle « accès refusé » écrite par le plugin dans les 4 dossiers sensibles, boutons « 📄 Voir le document » (demande d'adhésion, fiche élève) servis par `admin-post.php?action=sp_adh_doc` (connexion + droit « gestion des adhésions » + jeton) ; fichiers et adresses en base inchangés ; photos non concernées. 4 tests automatiques (chemins piégés refusés). Testé sur le site de test puis **déployé en prod** : accès direct → 403 sur de vrais documents, ouverture par le bureau connecté OK (PDF et photos), 88 liens sur 88 protégés. **Reste** : 9 documents ajoutés à la main depuis les fiches élèves (médiathèque générale, `uploads/2026/09|10/`) — en cours ; photos des adhérents (31) — à décider.
- **Suite (08/10/2026, sp_build `89d3bdc`)** : les documents choisis dans la médiathèque depuis la fiche élève sont, à l'enregistrement, recopiés dans le dossier protégé et retirés de la médiathèque (sauf s'ils servent ailleurs) ; migration unique automatique des fiches existantes. Testé sur le site de test (fichiers absents → fiches inchangées, essai refait chaque heure) puis **déployé en prod** : les 9 documents déplacés, **92 liens sur 92** des fiches protégés, 92 ouvertures réussies pour le bureau, plus aucun document d'adhérent dans la médiathèque (248 fichiers passés en revue par leur nom : seul `cafPassLoisir.png`, un logo). 3 tests de plus (39 au total).
- **Photos des adhérents** : 77 fiches en ont une — 24 envoyées avec le formulaire (`sp-adhesions-docs/photos/`), **53 ajoutées par le bureau dans la médiathèque générale** (mêlées aux photos du site). Elles servent à la carte de membre, à la fiche personnelle et aux vœux d'anniversaire ; toujours publiques par leur adresse. **Décision de l'utilisateur (08/10)** : ranger tout ce qui concerne un adhérent dans un **dossier par adhérent** fermé au web (`uploads/sp-adherents/<id>-<code>/`), séparé de la médiathèque publique — chantier lancé. **Fin de journée** : codé (`a61bb54` + `8aaa5ef`) et **validé sur le site de test** (après copie des `uploads/` de la prod par SSH) ; **déploiement en prod à faire** — détail et procédure dans `sp_build/EVOLUTION.md` (« Dossier par adhérent »). SSH OVH : `ssh tkdclad@ssh.cluster021.hosting.ovh.net` (mot de passe FTP), seulement par le partage de connexion ; dossiers `~/www` (prod) et `~/dev.tkdclaira.fr` (test). **09/10/2026** : déployé en prod (après `tar czf ~/sauvegarde-uploads-2026-10-08.tgz -C ~/www/wp-content uploads` — à taper **une fois connecté en SSH**, pas dans PowerShell) ; puis `dba541a` : envoi direct des photos / documents depuis la fiche élève (plus par la médiathèque) et page « 🗂️ Photos à trier » pour les photos d'identité orphelines restées dans la médiathèque (48 proposées) — tri à faire par le bureau. Détail dans `sp_build/EVOLUTION.md`.

**Erreur critique du 10/10/2026 (prod), cause trouvée** : mail WordPress « Votre site connaît un problème technique » à l'enregistrement d'une fiche adhérent (`Call to undefined method SP_Cal_Docs_Adhesion::securiser_url()`, `class-admin-members.php:141`). Ce n'est pas une tâche planifiée : `class-admin-members.php` est resté en prod dans sa version `89d3bdc` (24 958 octets) alors que `class-docs-adhesion.php` a été mis à jour — le fichier figurait dans l'envoi du 08/10 15:44 mais n'est pas arrivé. **Résolu le 12/10/2026** (fichier renvoyé, enregistrement de la fiche n° 136 vérifié par l'utilisateur) — il fallait renvoyer `DEPLOIEMENT_2026-10-08_1544_sp_build_8aaa5ef/includes/class-admin-members.php` (24 976 octets), puis refaire la modification de la fiche n° 136. **Leçon** : après chaque envoi, comparer la taille de TOUS les fichiers de `LISTE.txt`.

**Menu « SP Calendar » rangé par thème + page « Shortcodes » (12/10/2026)** : codé (`4a840c2`, `class-menu.php`), vérifié localement (syntaxe, PHPStan, tests, simulation du menu par profil) ; validé sur le site de test puis **déployé en prod le 12/10/2026 (`7d5ced3`), vérifié par l'utilisateur**. Le site de test est désormais à la même version que la prod (il lui manquait `dba541a`, d'où une erreur critique au premier envoi — toujours préparer le dossier du test depuis la version réellement présente sur le test). Détail dans `sp_build/EVOLUTION.md`. Restent : « Paramètres » en onglets ; menu à regarder avec un compte secrétaire.

**Suite du 12/10/2026, tout en prod (`30677c8`) et vérifié** : « Paramètres » en 5 onglets (récapitulatif entraîneurs → Indemnités km, notifications → Envois d'emails, veille réglementaire → Fin de saison ; bogue des formulaires qui s'écrasaient entre eux corrigé) ; vacances remplies depuis le calendrier officiel par zone + lien vers l'annulation par lot ; bouton « Planning public » automatique ; couleurs des cours par discipline et par cours spécial ; rappel d'anniversaires aux encadrants sur la page Anniversaires ; créneaux mensuels sur le même jour de la semaine (1er samedi) au lieu du même numéro de jour. Détail dans `sp_build/EVOLUTION.md`. **Restent à l'utilisateur** : rattacher les créneaux « TKD prépa Dan », « TKD Combat », « TKD Démo/Freestyle » et « Cours enfants confirmés » à leur cours spécial (liste du formulaire du créneau). **Reporté** : personnalisation des messages de vœux.

**Tenues du renforcement musculaire, étape 1 — en prod le 12/10/2026 (`bdbca65`), vérifié** : page « Doboks & tenues » (module `class-tenues.php`, tables propres), tailles XS → XXL en liste dans le formulaire d'adhésion et la fiche admin pour le renforcement. État en prod : 14 adhérents du renforcement, 12 fiches à compléter avant la commande (10 tailles de bas en chiffres, 4 sans sexe, 2 sans taille de haut). Détail dans `sp_build/EVOLUTION.md`. **Étape 2 en prod le 13/10/2026 (`686e918`), vérifiée** : bloc « Ma tenue » sur la fiche personnelle (demandes de changement de taille / remplacement / restitution, réservation ou liste d'attente, tailles XS → XXL modifiables par l'adhérent), onglet « Demandes » du bureau, échange en une saisie, rubrique « Grade » masquée pour le renforcement sans grade. Mails non vérifiés (le site de test n'envoie rien). **Restent** : inviter les adhérents du renforcement à compléter leurs tailles depuis leur lien personnel ; revoir le contenu de la fiche personnelle pour le renforcement (compétitions) ; corriger sur le test l'adresse de la fiche membre (fait à la main le 13/10, prévu dans `rafraichir-site-test.sql`).
**Nouvelle carte de membre dans l'application `/app/` — en prod le 13/10/2026 (`sp_build` `1feb94c`)** : accord explicite de l'utilisateur, essai sur téléphone fait par lui sur le site de test (carte et pointage par QR). L'API envoie le recto / verso de `class-carte.php`, `app.js` les affiche ; ancien dessin gardé en secours. Vérifié en prod : page et script servis ; l'onglet Carte lui-même n'est visible que sur téléphone. Version en ligne : `1feb94c` (test et prod).

**Tenues : case « Pratique aussi le renforcement musculaire » + saison en liste déroulante — en prod le 13/10/2026 (`sp_build` `8195845`), vérifié** : la case de la fiche admin fait entrer un adhérent du taekwondo dans le prêt des tenues (les deux blocs « Mes doboks » et « Ma tenue » sur sa fiche personnelle, s'il est de la saison en cours) ; la saison de la fiche admin se choisit dans une liste. Version en ligne : `8195845` (test et prod). Détail dans `sp_build/EVOLUTION.md`.

**Carte de membre, nouveau dessin — en prod le 13/10/2026 (`sp_build` `4f11415`), vérifié (fiche personnelle, fiche admin, liste des photos : 6 à recadrer, 29 sans photo sur 97)** : dessin unique `class-carte.php` (photo rectangulaire, contact d'urgence et naissance au verso, filigrane 태권도) pour la fiche personnelle et l'impression par lot ; outil de cadrage sur la fiche admin et liste « Photos des cartes » sur la page « Cartes membres ». **L'application `/app/` garde l'ancienne carte** (accord de l'utilisateur à demander). Version en ligne : `4f11415` (test et prod). Détail dans `sp_build/EVOLUTION.md`.

**Catégorie « Adulte » retirée des listes — en prod le 14/10/2026 (`sp_build` `b9f3f9d`, `tkd-cotisations` v1.2.1), vérifié sur le test et la prod** : Tarifs, Initialisation saison, Vue globale, fiche adhérent. Tarifs Renfo et Renfo NV passés en « Tout âge » (simple repère d'affichage). Détail dans `tkd-cotisations/REALISATION.md` et `sp_build/EVOLUTION.md`.

**Application `/app/` : QR code de la carte en plein écran — en prod le 15/10/2026 (`sp_build` `59da348`), vérifié** : accord explicite de l'utilisateur, essai fait par lui sur le site de test ; bouton « Afficher le QR en grand » sous la carte, ou appui sur le QR de la carte. Même QR, pointage et mode PIN inchangés. Version en ligne : `59da348` (test et prod). Détail dans `sp_build/EVOLUTION.md`.

**Annulation par lot : raccourci « Jours fériés de la saison » — en prod le 19/10/2026 (`sp_build` `7bd1e54`), vérifié** : essai complet sur le site de test (annuler, rétablir, mode vacances intact, un email par adhérent bloqué par le test), puis vérification en prod en lecture seule. **Les jours fériés de la saison 2026/2027 ont été annulés en prod par l'utilisateur lui-même, depuis un autre PC, le 19/10 à 15h31** (12 cours sur 5 jours : 11/11, 29/03, 01/05, 06/05, 17/05 ; un email par adhérent, parti à 15h35). Jours fériés calculés par `SpCalPro_DB::jours_feries()` (tests `outils/tests/JoursFeriesTest.php`). Version en ligne : `7bd1e54` (test et prod). Détail dans `sp_build/EVOLUTION.md`.

**Fiche produit de la boutique : mise en page et options lisibles — en prod le 20/10/2026, vérifié (ordinateur 1280 px et téléphone 375 px)** : changement fait dans *Apparence → Personnaliser → CSS additionnel* (thème `premier-child`), **hors des extensions et des dépôts Git**. Deux causes, toutes deux dans le thème Premier : (1) `framework.css` limite `.woocommerce div.product` à 62 % de la largeur (place d'une barre latérale), ce qui serrait à gauche le modèle Elementor « produit unique » (id 5740) ; (2) `style.css` met **tous** les tableaux sur fond `#111` avec texte gris, d'où le pavé noir illisible des options (Taille, Flocage). Ajouté : `.woocommerce div.product.elementor { width:100%; float:none; margin:0 }` et un style clair pour `form.cart table.variations` (fond `#f6f7fb`, étiquettes bleu marine `#1d2b53`, listes blanches, « Effacer » en rouge). Au passage : le bloc `@media (min-width: 1025px)` déjà présent n'était pas refermé (accolade ajoutée, sans effet sur l'existant) et la ligne vide `élément { }` a été supprimée. Les règles existantes `.site-content` / `.footer-area` / `.site-branding` (1280 px) ne trouvent plus aucun élément sur les pages Elementor : probablement inutiles. **Retour arrière** : supprimer dans le CSS additionnel tout ce qui suit la ligne `} /* fin du bloc @media … */`.

**Boutique : logo du club à la place de l'image grise des produits sans photo — en prod le 20/10/2026, vérifié** : réglage WooCommerce *Réglages → Produits → Image indicative* (`woocommerce_placeholder_image`) passé de **5702** (image grise par défaut de WooCommerce) à **3010** (`LogoSansLettre.png`, le logo de l'en-tête), **hors des extensions et des dépôts Git**. Vérifié : fiche « Short – RENFO » et page Boutique (le seul produit sans photo affiche le logo ; « Protection » et « T-Shirt Octobre Rose » gardent leur photo). Un produit qui reçoit une vraie photo l'affiche automatiquement à la place du logo. **Retour arrière** : remettre `5702` dans ce champ.

**Boutique : une image par catégorie de produits — en prod le 20/10/2026, vérifié** : les 6 catégories (qui n'avaient aucune image et montraient le logo de remplacement) ont chacune une vignette créée pour l'occasion, **hors des extensions et des dépôts Git** : carré 800 × 800, couleurs du club, pictogramme, nom de la catégorie en majuscules, petit logo en coin et en filigrane. Enfants → média 5924 (jaune, enfant) ; Femme → 5925 (rouge, femme) ; Homme → 5926 (bleu, homme) ; Protection → 5927 (marine, bouclier) ; Solidarité → 5928 (prune et rose, ruban rose d'Octobre Rose) ; Non classé → 5929 (gris clair, dobok ceinture noire). Pictogrammes **Noto Color Emoji** (Google, licence libre) plutôt que ceux de Windows (droits Microsoft flous) ; pas de photos d'adhérents (enfants reconnaissables). Dessinées dans le navigateur, ajoutées à la médiathèque (fichiers `categorie-<nom>.png`, titre et texte alternatif renseignés) puis attribuées par l'API WooCommerce. Vérifié dans *Produits → Catégories* (vignette de chaque catégorie). Écartées : la bannière Protection Tusah (n° 5800, 1500 × 500, mal recadrée en carré) ; le T-shirt Octobre Rose (n° 5844) reste une alternative possible pour Solidarité. **Changer une image** : *Produits → Catégories → Modifier → Miniature*.
- **Version 2, le même jour (retour de l'utilisateur)** : (1) la boutique affiche les vignettes de catégorie en **4:3** (300 × 225, recadrées) : le carré était coupé en haut et en bas et le filigrane du logo se voyait « en transparence » ; (2) « Protection » = **équipement de combat**, pas un bouclier. Refait au format **800 × 600, sans filigrane ni petit logo** ; Protection = **plastron (hogu) bleu et casque rouge dessinés à la main** (aucun pictogramme n'existe). Nouvelles images attribuées : Enfants 5930, Femme 5931, Homme 5932, Protection 5933, Solidarité 5934, Non classé 5935 (fichiers `categorie-<nom>-v2.png`). Vérifié sur la page Boutique (`/shop/`, widget « Catégories de produits » d'Elementor) : vignettes `-v2` en 300 × 225, plus de logo visible. **Les 6 images de la version 1 (n° 5924 à 5929) ne servent plus** : à supprimer par l'utilisateur dans *Médias* s'il le souhaite. **Relevé au passage sur `/shop/`** : le widget n'affiche que 5 catégories (« Enfants » absente, sans doute le nombre réglé dans le widget) ; le texte d'accueil est un faux texte latin (« Saepissime igitur mihi de amicitia… ») à remplacer.
- **Suite, le même jour** : « Enfants » absente du widget → corrigé par l'utilisateur (nombre de catégories affichées). **Nouvelle catégorie « Renforcement musculaire »** (n° 96, slug `renforcement-musculaire`), créée par l'API WooCommerce avec sa vignette (média 5939, `categorie-renforcement-musculaire-v2.png`, 800 × 600 : fond turquoise, t-shirt bleu marine à col et bandes rouges et short de sport noir à bandes rouges, **dessinés à la main** — le short Noto ressemblait à un maillot de bain). Le produit « Short – RENFO » (n° 5804) a été déplacé de « Non classé » vers cette catégorie par l'utilisateur ; vérifié : catégorie à 1 produit, image 5939.

**Page Boutique : vrai texte de présentation à la place du faux texte latin — en prod le 20/10/2026, vérifié** : page « Shop » (n° 5846, `/shop/`, Elementor), bloc Paragraphe `34b4ce6` sous « Bienvenue à notre boutique en ligne », **hors des extensions et des dépôts Git**. Ancien texte : faux texte latin (« Saepissime igitur mihi de amicitia… »). Nouveau : « Bienvenue dans la boutique du Taekwondo Claira ! Vous y trouverez les protections pour l'entraînement et la compétition, les tenues du renforcement musculaire et nos articles solidaires, comme le T-shirt Octobre Rose. Choisissez une catégorie ci-dessous pour découvrir les articles, et n'hésitez pas à demander conseil aux entraîneurs pour la taille ou l'équipement adapté. » Modifié et publié par l'éditeur Elementor (seul ce paragraphe touché) ; vérifié en ligne, plus aucun texte latin sur la page. **Volontairement absent** (informations inconnues) : retrait des commandes, paiement, délais — à ajouter si l'utilisateur les précise. **Retour arrière** : Elementor → *Historique → Révisions*.

**Pages de catégorie de la boutique : mise en page propre — en prod le 20/10/2026, vérifié** : les pages `/categorie-produit/<catégorie>/` n'ont pas de modèle Elementor : c'était la mise en page par défaut du thème Premier (deux `.content-area` imbriquées flottant à gauche, 835 px sur 1 265, sans marge ; vignettes de 184 px ; boutons gris ; badge « Promo ! » vert olive). Ajouté dans *CSS additionnel* (à la suite du bloc de la fiche produit, **hors des extensions et des dépôts Git**), un bloc limité à `body.tax-product_cat` : contenu centré (1 200 px max, marges), titre bleu marine souligné de rouge, fil d'Ariane discret, **grille de cartes produits** (fond blanc, coins arrondis, ombre au survol, image 4:3, nom bleu marine, bouton bleu `#467ff7` arrondi comme « Ajouter au panier », « Promo ! » en pastille rouge), 4 par rangée au plus sur ordinateur, 1 sur téléphone. Piège rencontré : la règle « petit écran » de WooCommerce (`ul.products[class*="columns-"] li.product { width: 48% }`) est plus prioritaire — sélecteur renforcé avec `[class*="columns-"]`. Vérifié : « Protection » et « Homme », ordinateur et téléphone (pas de défilement horizontal). **Retour arrière** : supprimer dans le CSS additionnel le bloc qui commence par `/* Pages de catégorie de la boutique … */`.
- **À savoir (constaté le même jour)** : WooCommerce est en mode **« Bientôt disponible », appliqué aux pages de boutique uniquement** (*WooCommerce → Réglages → Visibilité du site* ; badge « Boutique bientôt disponible » dans la barre d'administration). Les visiteurs **non connectés** voient donc « De grandes choses se profilent à l'horizon… » à la place des pages de la boutique (constaté par l'utilisateur sur Firefox), tandis qu'un administrateur connecté (Chrome) voit la vraie boutique. Rien de cassé : c'est ce réglage. Passer sur « En ligne » pour ouvrir la boutique à tous — décision laissée à l'utilisateur.

**Application des dispos : voir où sont les autres entraîneurs — en prod le 27/10/2026 (`sp_build` `c5ed293`), vérifié sur le test et la prod** : suite au rôle « Responsable des dispos » (`78b41e5`, codé sur l'autre PC le 25/10, testé en prod par l'utilisateur). **Pour tous** : un point bleu sous le numéro du jour quand un autre entraîneur est disponible (remplace le compteur « 2/5 » illisible) ; la case garde sa propre dispo. **Responsable** : boutons « 👤 Mes dispos » / « 👥 Équipe » ; en mode Équipe, vert = jour de cours couvert (entraîneur disponible ou remplaçant), orange = cours sans personne. Accord explicite de l'utilisateur pour cette modification de l'application. Vérifié en lecture seule sur le test (Chrome, jeton du test récupéré dans phpMyAdmin — différent de la prod car la base du test date du 08/10) puis en prod (navigateur intégré) : en prod, novembre 2026 montre 1 jour orange (samedi 07/11). **Retouche le même jour** (demande de l'utilisateur) : en mode Équipe, un jour sans cours ou dont les cours sont annulés (ex. 11/11) reste sans couleur, même avec des dispos — en prod le même jour (`6a1c65b`), vérifié (le 11/11 n'est plus coloré). Détail dans `sp_build/REALISATION.md`.


**Infos santé et export CSV des adhérents — en prod le 29/10/2026 (`sp_build` `c5dafb0`), vérifié sur le test et la prod** : champ facultatif « Informations santé utiles à l'encadrement » dans la demande d'adhésion (à part du « Message »), recopié sur la fiche à la validation, modifiable par les admins, et visible des entraîneurs dans l'appli (mode PIN, écran de pointage du cours, bloc repliable « 🩺 Infos santé (N) », accord explicite de l'utilisateur) ; jamais dans les e-mails. Export CSV : « Droit à l'image », « Repartir seul(e) », « Infos santé » (non cochée par défaut ; « Saison » existait déjà). Bug corrigé : « Repartir seul(e) » n'avait pas de colonne sur la fiche (case toujours décochée, jamais enregistrée) — colonne créée, 16 réponses reprises en prod depuis les demandes validées. Détail dans `sp_build/REALISATION.md`.

**Fiche « Détail de la demande » mise en forme, infos santé dans l'onglet « 👥 Adhérents » et au scan — en prod le 29/10/2026 (`sp_build` `fbfa43a` puis `9990497`), vérifié sur le test et la prod** : page admin des demandes d'adhésion en cartes sur deux colonnes (elle n'avait aucun style). Appli : onglet 🎂 renommé « 👥 Adhérents », avec en tête les infos santé de tout le club, groupées par catégorie et repliées ; repère 🩺 et texte dans le bandeau au scan d'un adhérent concerné, puis dans la liste des présences. Accord explicite de l'utilisateur, testé au téléphone sur le site de test. Détail dans `sp_build/REALISATION.md`.

**Déploiement autonome par FTP — `outils/deployer.ps1` (29/10/2026)** : à la demande de l'utilisateur, Claude envoie lui-même les dossiers `DEPLOIEMENT_…`. **SSH écarté** : le réseau habituel bloque le port 22 (déjà constaté le 08/10, seul le partage de connexion passe). Le script utilise la **session WinSCP enregistrée par l'utilisateur** (`tkdclad@ftp.cluster021.hosting.ovh.net`, mot de passe gardé par WinSCP, jamais vu par Claude). Étapes : état des fichiers sur le serveur ; **sauvegarde locale** des fichiers remplacés (`<dossier>/sauvegarde-<cible>-<date>/`) ; envoi **binaire** avec `-nopreservetime` (date du serveur = heure de l'envoi, pour que le numéro de version des CSS/JS change aussi lors d'un retour arrière) ; **contrôle des tailles** ; journal `outils/deploiements.log` (non versionné). Ne supprime jamais rien sur le serveur. Usage (dans `outils`) : `deployer.ps1 -Cible test` ; prod : `-Cible prod -Prod`, **uniquement sur feu vert explicite de l'utilisateur, à chaque fois** ; `-Verifier` (lecture seule) ; `-Restaurer <sauvegarde>` (retour arrière). ⚠️ La session WinSCP s'ouvre par défaut dans le dossier de la **prod** (`/www/…`) : le script utilise toujours des chemins complets (`/dev.tkdclaira.fr/wp-content/plugins/sp_build/` pour le test). Validé sur le test en renvoyant des fichiers identiques, puis premier envoi en prod (`9990497`). Commit `4a16a1a`.

**En attente** :
- **Avant le prochain chantier (décidé le 12/10/2026)** : rafraîchir la base du site de test (copie de la prod + `outils/rafraichir-site-test.sql`, procédure plus haut). Sa base date du 08/10 : il n'a ni les créneaux mensuels du samedi, ni les créneaux renommés et rattachés à un cours spécial, ni les couleurs, et la page « Horaires d'entraînement » y est encore en ligne.
- Page « Horaires d'entraînement » (TablePress absent) ; anciennes tables du jury ; origine du volume du journal d'erreurs.
- Tests à ajouter si utile : statut d'une cotisation (soldée / partielle / en attente), calculs de SP Compta.

---

*Créé le 11/09/2026. Sections 6 et 7 ajoutées le 06/10/2026, audit de sécurité et section 8 le 07/10/2026, étape 6 le 08/10/2026.*
