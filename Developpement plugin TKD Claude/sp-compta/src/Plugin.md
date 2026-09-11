# Plugin.php

**Rôle** : point d'orchestration unique. Instancie `Database` et `Capabilities`, câble les hooks WordPress, expose un singleton `getInstance()`.

## API publique

- `Plugin::getInstance(): Plugin` — accès au singleton.
- `Plugin::activate(): void` — appelé par `register_activation_hook()` dans [sp-compta.php](../sp-compta.php), crée les tables via `Database::createTables()`.
- `boot(): void` — accroche `Capabilities::register()` sur le hook `init`, appelle toujours `bootFront()` ; si `is_admin()`, accroche aussi `Database::maybeUpgrade()` sur `admin_init` (voir [Database.md](Database.md#mise-à-jour-du-schéma-sur-un-site-déjà-actif--maybeupgrade)) puis appelle `bootAdmin()`.
- `bootFront(): void` (depuis le 10/09/2026) — câble ce qui doit réagir sur le **front-end public** (shortcodes, `wp_head`...), donc appelée sur **toute** requête, contrairement à `bootAdmin()`. Construit un `DepenseScreen` (réutilisé pour sa logique de sauvegarde, voir [Front/SaisieRapideShortcode.md](Front/SaisieRapideShortcode.md)) et câble `SaisieRapideShortcode`. Duplique volontairement quelques instanciations de repositories avec `bootAdmin()` (appelée séparément si `is_admin()`) — des objets sans état partagé, sans coût réel à les recréer, plus simple que de restructurer tout `boot()` pour les partager.
- `bootAdmin(): void` — câble tous les écrans wp-admin (instancie chaque repository + chaque `AdminScreen`, appelle `registerHooks()`, construit le [Menu](Admin/Menu.md)). **Un écran de plus = une entrée de plus dans `$screens`** — voir [Admin/FournisseurScreen.md](Admin/FournisseurScreen.md) pour le patron à reproduire. Le premier élément du tableau devient la page d'accueil du menu "Trésorerie" (`Depenses` actuellement) — réordonner reste sans risque, c'est juste l'ordre d'affichage.
- `database()` / `capabilities()` — accesseurs pour les autres classes qui ont besoin de ces dépendances (pas d'injection de conteneur, projet trop petit pour ça).

## Dépendances

[Database.php](Database.php), [Capabilities.php](Capabilities.php), [Admin/Menu.php](Admin/Menu.php), [Front/SaisieRapideShortcode.php](Front/SaisieRapideShortcode.php), et un repository + un `AdminScreen` par écran câblé dans `bootAdmin()`.

## En cas de bug

- **"Erreur fatale" à l'activation** → deux causes possibles, à distinguer via le log d'erreurs PHP (ou `WP_DEBUG` activé) :
  1. Classe introuvable / erreur d'autoload → vérifier que l'autoloader natif dans [sp-compta.php](../sp-compta.php) (`spl_autoload_register`, plus de dépendance à `vendor/autoload.php` depuis le correctif du 07/09/2026) résout bien `SpCompta\...` vers `src/...`.
  2. Erreur de syntaxe PHP → **ce plugin requiert PHP >= 8.0** (property promotion utilisée dans toutes les entités/repositories, voir [composer.json](../composer.json)) ; sur un hébergement encore en PHP 7.x, le fichier ne se parse même pas. Vérifier la version PHP du site (Outils → Santé du site, ou panneau d'hébergement) avant toute autre piste.
- Table absente après activation → le bug est dans `Database::createTables()`, pas ici (voir [Database.md](Database.md)).
- Capacité `sp_compta_manager` absente → le bug est dans `Capabilities::register()`, pas ici (voir [Capabilities.md](Capabilities.md)).

## Tests

Pas de test dédié (classe de câblage pure, sans logique propre à vérifier). Couvert indirectement par les tests de `Database` et `Capabilities`.
