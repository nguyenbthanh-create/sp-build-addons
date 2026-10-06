# Auth/SessionLongue

## Rôle

Session WordPress de **1 an** pour les comptes ayant accès à la trésorerie (capacité `sp_compta_manager` : administrateurs et comptes cochés dans « Accès bureau »). Problème réglé le 06/10/2026 : la saisie rapide installée sur l'écran d'accueil (iPhone et Android) **redemandait la connexion**. Sans « Se souvenir de moi », WordPress pose un cookie temporaire, effacé dès que le système ferme l'application ; avec la case, le cookie durait 14 jours.

Les autres comptes du site ne sont pas concernés.

## Fonctionnement

- Filtre `auth_cookie_expiration` → `filtrerDuree()` : 1 an (`DUREE`) pour un compte trésorerie, durée inchangée sinon.
- Action `wp_login` → `prolongerALaConnexion()` : si la case « Se souvenir de moi » n'était pas cochée, repose le cookie en mode persistant (`wp_set_auth_cookie(…, true)`), donc lui aussi pour 1 an.
- Branché dans `Plugin::boot()` (toutes les requêtes : la connexion passe par `wp-login.php`, hors `bootAdmin()`).

**Effet seulement à la prochaine connexion** : chaque membre du bureau doit se reconnecter une fois dans l'application installée.

## Sécurité

Un téléphone perdu et déverrouillé donne accès à la saisie pendant la durée de la session. Parade : verrouillage du téléphone ; un administrateur peut fermer toutes les sessions d'un compte (Profil WordPress → « Se déconnecter partout »).

## Scénarios (tests : `tests/Unit/Auth/SessionLongueTest.php`)

```gherkin
Scénario: un compte trésorerie obtient une session d'un an
Scénario: un compte ordinaire garde la durée habituelle
Scénario: un utilisateur inconnu (id 0) ne change rien
```

## En cas de bug

- Toujours déconnecté : le membre ne s'est pas reconnecté depuis le déploiement, ou son compte n'a pas l'accès trésorerie (Paramètres → Accès bureau), ou une autre extension filtre `auth_cookie_expiration` après nous (priorité 10).
- Sur iPhone : l'application installée garde ses propres cookies, séparés de Safari — la connexion doit être faite **dans l'application installée**.
