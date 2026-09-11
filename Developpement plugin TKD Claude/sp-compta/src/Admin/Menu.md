# Admin/Menu.php

**Rôle** : construit le menu wp-admin "Trésorerie" à partir de la liste des écrans ([AdminScreen.php](AdminScreen.php)) câblés dans [Plugin.php](../Plugin.php)`::bootAdmin()`. Le premier écran de la liste devient la page d'accueil du menu (clic sur "Trésorerie" lui-même) ET le premier sous-menu — comportement standard `add_menu_page()`/`add_submenu_page()` de WordPress, pas un choix arbitraire de cette classe.

## Accès

Chaque page est enregistrée avec la capacité `Capabilities::MANAGE_COMPTA` — WordPress masque automatiquement le menu entier aux utilisateurs qui ne l'ont pas (pas besoin de vérification supplémentaire ici pour l'affichage du menu ; chaque `render()` d'écran revérifie quand même la capacité, voir [FournisseurScreen.md](FournisseurScreen.md), car un accès direct par URL contourne le menu).

## En cas de bug

- Menu absent → vérifier que `Menu::register()` est bien appelé (câblage dans [Plugin.php](../Plugin.php)`::bootAdmin()`, uniquement si `is_admin()`), et que l'utilisateur courant a la capacité `sp_compta_manager`.
- Sous-menu manquant → vérifier que l'écran correspondant est bien dans le tableau `$screens` passé au constructeur.

## Tests

Pas de test dédié (pure orchestration d'API WordPress `add_menu_page`/`add_submenu_page`, non observable sans un WordPress complet en cours de requête admin — testé manuellement sur le site de test).
