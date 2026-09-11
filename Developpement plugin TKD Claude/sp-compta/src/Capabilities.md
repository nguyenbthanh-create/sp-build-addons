# Capabilities.php

**Rôle** : porte d'accès unique du plugin. Définit la capacité WordPress `sp_compta_manager` (décision du 07/09/2026 : capacité dédiée, aucune dépendance au système de rôles de sp_build) et le point de contrôle `currentUserCanManage()` à appeler avant tout écran/action admin ou front du plugin.

## État actuel (incrément 6, 08/09/2026)

`register()` accorde la capacité au rôle `administrator` (safety net anti-blocage — un site reste toujours administrable après activation, quoi qu'il arrive côté configuration bureau). **En plus** (pas à la place) : `syncBureauUsers(int[] $userIds)` accorde la capacité directement à des comptes utilisateurs précis, indépendamment de leur rôle — c'est le mécanisme utilisé par [Admin/ParametresScreen.php](Admin/ParametresScreen.md) (section "Accès bureau") pour donner l'accès aux 3 membres du bureau même s'ils ne sont pas administrateurs WordPress (ce qui est le cas réaliste — on ne va pas faire de 3 bénévoles des administrateurs complets du site juste pour saisir des dépenses).

## API publique

- `Capabilities::MANAGE_COMPTA` — constante du nom de la capacité, à utiliser partout (jamais la chaîne `'sp_compta_manager'` en dur ailleurs).
- `register(): void` — à appeler sur le hook `init` (déjà câblé dans [Plugin.php](Plugin.php)).
- `currentUserCanManage(): bool` — garde à appeler en tête de chaque handler admin/AJAX/shortcode du plugin.
- `bureauUsers(): int[]` — ids des utilisateurs actuellement configurés comme "bureau" (lit l'option `sp_compta_bureau_users`, source de vérité pour savoir qui a été assigné explicitement — pas une requête sur les capacités WordPress elles-mêmes).
- `syncBureauUsers(int[] $userIds): void` — remplace la liste bureau par `$userIds` : accorde la capacité à chaque nouvel id, la retire à tout id qui était dans l'ancienne liste mais plus dans la nouvelle. **N'affecte jamais l'octroi via le rôle administrateur** — retirer un administrateur de la liste bureau ne lui enlève pas l'accès si `register()` la lui a déjà donnée via son rôle.

## Pourquoi garder le rôle administrateur en plus de la liste bureau

Risque évité : si `syncBureauUsers()` remplaçait entièrement l'accès (et non `register()`), une erreur de configuration (liste bureau vidée par erreur) bloquerait tout le monde, y compris l'administrateur technique du site, sans porte de secours. Conserver l'octroi au rôle `administrator` garantit qu'il reste toujours au moins un accès de secours.

## En cas de bug

- Un membre du bureau assigné ne peut rien voir/faire → vérifier `user->has_cap(Capabilities::MANAGE_COMPTA)` sur son compte, et que son id figure bien dans `bureauUsers()` — pas seulement qu'il est connecté.
- Un ancien membre du bureau retiré de la liste garde l'accès → normal s'il est administrateur WordPress (voir section ci-dessus) ; sinon vérifier que `syncBureauUsers()` a bien été appelé avec la nouvelle liste (pas juste `bureauUsers()` relu sans resauvegarde).
- Tout le monde peut accéder → un appel à `currentUserCanManage()` manque quelque part dans le code appelant, pas ici.

## Tests

[CapabilitiesTest.php](../tests/Unit/CapabilitiesTest.php).
