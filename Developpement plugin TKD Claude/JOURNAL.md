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
| 6. Tests automatiques, espaces de noms | ⏳ plus tard | |

**Tout est en production** (dernière version déployée : `710f384`, vérifiée le 07/10/2026 à 15h55 — pages d'administration, application en mode PIN, hors connexion). Restent à essayer en conditions réelles : le **scan d'une carte QR** et le **lien personnel d'un entraîneur** dans l'application.

**Leçons de déploiement (07/10/2026)** :
- Une panne (« erreur critique ») a été causée par un `class-admin.php` arrivé incomplet sur le serveur → toujours utiliser `outils/preparer-deploiement.ps1` (dossier + tailles exactes), FTP en mode **binaire**, comparer les tailles après l'envoi ; garder un dossier `RESTAURATION_…` le temps de vérifier.
- Copier le **contenu** du dossier de déploiement à sa place, jamais le dossier lui-même sur le serveur.
- La prod et le site de test partagent le même hébergement / FTP : **vérifier le dossier distant** (un envoi est parti sur le site de test au lieu de la prod).
- Les fichiers de restauration se créent avec Git Bash (`git show … > fichier`), pas avec PowerShell 5.1 (`>` ajoute un BOM UTF-8 qui casse le PHP).

**Hébergement** : passé de STARTUP à **PERFORMANCE** (section 7) — l'éditeur Elementor fonctionne de nouveau.

**Site de test (`dev.tkdclaira.fr`)** : protégé par une authentification HTTP (« Staging - Accès restreint ») que le navigateur intégré de l'application ne sait pas ouvrir → vérifications par **Claude in Chrome** (extension installée et reliée le 07/10/2026). Il a reçu le plugin complet `710f384`. Sa base date de la saison précédente (pas de cours en octobre 2026) et sa table `mod237_sp_adhesions_pending` n'a pas les colonnes récentes (ajout impossible, signalé par `class-schema.php`) → à régler lors du rafraîchissement de sa base (copie de la prod avec adresses neutralisées). Emails du site de test : désactivés (« ENVOI DÉSACTIVÉ » de WP Mail SMTP).

**Extension « SP Pointage QR » désactivée en prod le 07/10/2026** (pointage pas encore utilisé en ce début de saison, donc directement en prod). Avant : code de prod relu (292 lignes, mêmes 4 routes et même format de réponse que sp_build ; elle contenait un PIN de secours écrit en dur et le préfixe `mod237_` en dur) et aucune autre extension (sp-compta, tkd-cotisations, claira-tkd-parcours) ne l'utilise. Après : routes `/pointage/*` testées (cours du jour, mauvais PIN refusé, scan / rattrapage / lot avec carte inconnue → messages accentués de sp_build), page `/app/` sans erreur, aucune présence enregistrée. **Reste** : confirmer au premier vrai scan d'une carte, puis supprimer l'extension (réactivation en un clic d'ici là si besoin).

**Extension « SP Member Cards (Add-on) » désactivée en prod le 07/10/2026** : premier essai jamais terminé (5 fichiers, ~230 lignes, auteur « Ton Nom »). Sa page `/carte-interactive/?token=` ne montrait jamais de carte (sp_build redirige tout lien `?token=` valide vers la fiche membre) et sa génération de carte PDF + QR ne pouvait pas marcher (bibliothèques `vendor/` jamais installées, aucun lien dans l'administration). sp_build imprime déjà les cartes avec QR (« 🖨️ Cartes membres »). Page « carte-interactive » retirée par l'utilisateur, puis extension désactivée. Vérifié : accueil sans erreur, `/carte-interactive/` en 404, fiche membre complète, anciens liens `/?token=` et `/carte-interactive/?token=` redirigés vers `/fiche-membre/`. **Reste** : supprimer les deux extensions ensemble après un premier vrai scan de carte.

**Ménage des extensions en prod (07–08/10/2026)**, pour éviter toute confusion. Avant chaque suppression : programme de désinstallation lu (aucun ne touche aux tables du club ; seuls Brevo et WPForms effacent leurs propres réglages), dépendances cherchées dans nos 4 extensions, contenus des pages contrôlés.
- **Supprimées par l'utilisateur** : `sp_build_backup` (copie de sp_build du 01/09/2026 = commit `943a50f`, même nom « SportPress Calendar PRO » → risque d'erreur critique si activée par erreur à côté de sp_build ; sans désinstallation), SP Member Cards, Taekwondo Progression Master (« Gemini Dev », remplacée par Claira TKD Parcours), Brevo (les mails passent par WP Mail SMTP, réglé sur Brevo), Login or Logout Menu Item (doublon), SportsPress gratuit (inclus dans SportsPress Pro), Essential Addons, Essential Blocks, Templately.
- **Pages retirées** : `/carte-interactive/` et `/progression/` (affichait le shortcode brut `[tkd_progression]`).
- **À garder** : SportsPress Pro (accueil, 65 joueurs, 20 sponsors ; sp_build lit `sp_player` / `sp_staff`), sp_build, SP Compta, TKD Cotisations, Claira TKD Parcours, Elementor + Pro, WP Mail SMTP, Login Logout Menu, Disable XML-RPC-API.
- **Encore présentes, désactivées, supprimables sans risque** : Insert PHP Code Snippet, Pricing Table by Supsystic, Widget Importer & Exporter, WPForms Lite. **SP Pointage QR** : à supprimer après le premier vrai scan.
- Vérifié le 08/10 : pages publiques, `/app/`, éditeur Elementor (1,4 s), 9 pages d'administration sp_build, fiche membre, pointage (cours du soir listé), WP Mail SMTP → Brevo : tout fonctionne.

**En attente** :
- Rafraîchir la base du site de test.
- Étape 6 du plan.

---

*Créé le 11/09/2026. Sections 6 et 7 ajoutées le 06/10/2026, audit de sécurité et section 8 le 07/10/2026.*
