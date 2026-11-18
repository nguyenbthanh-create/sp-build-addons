# TKD Projets

Gestion des projets du club : **gala**, **fêtes** (Noël, fin d'année) et **compétitions**. Pour chaque projet :
- les tâches, en liste, en tableau Kanban ou en diagramme de Gantt ;
- l'équipe ;
- des e-mails d'attribution ;
- une carte des zones (OpenStreetMap) avec un lien privé « Ma carte » par personne.

L'extension a été écrite par Noé PETIT DE MIRBECK (président) jusqu'à la 1.6.0. La 2.0.0 est une réécriture du 18/11/2026, avec les mêmes écrans et les mêmes tables. L'analyse qui l'a motivée est dans `RefontePlugin\tkd-projets-ANALYSE.md`.

## Droits

| Qui | Peut |
|---|---|
| **Gestionnaire** : administrateurs, et comptes cochés dans *Projets → Réglages* | créer des projets, voir et modifier tous les projets, supprimer un projet, attribuer une tâche à tout un rôle |
| **Responsable** d'un projet | tout sur son projet (tâches, équipe, carte), sauf le supprimer |
| **Membre** d'un projet | voir le projet ; sur **ses** tâches : statut, avancement, description, pièces jointes |
| Personne affectée à une zone | sa carte seulement, par le lien reçu par e-mail |

- Le menu **Projets** n'apparaît qu'aux gestionnaires et aux personnes qui sont dans au moins une équipe.
- Une personne à qui l'on attribue une tâche rejoint l'équipe comme membre.
- Les personnes proposées pour les équipes, les tâches et les zones sont les comptes des rôles suivants : administrateur, éditeur, auteur, contributeur, secrétaire, trésorière et entraîneur (filtre `tkdp_roles_proposes`). Les abonnés n'y sont jamais.
- **Budget** (onglet « 💶 Budget », 2.1.0) : visible du **bureau seulement**, c'est-à-dire du droit `sp_compta_manager` de SP Compta (*Trésorerie → Paramètres → Accès bureau*), et seulement sur les projets que la personne voit déjà.
- **Dépense de projet** : seul le **président** (*Projets → Réglages*) la valide. Il doit aussi être dans « Accès bureau ».

## Types de projets (`src/Types.php`)

Chaque type a une icône, une couleur, une nature SP Compta et une liste de **tâches modèles**. À la création, ces tâches sont datées à partir de la date de l'évènement (case cochée par défaut).

- **Gala** (12 tâches) : les inscriptions, le conducteur et la projection restent dans l'extension TKD Gala.
- **Fêtes** (7 tâches).
- **Compétition** (7 tâches).
- **Autre** : n'est pas proposé à la création. Il sert aux projets créés avant la 2.0.

Pour changer une tâche modèle, modifier le tableau `tasks` du type, au format : titre, jours avant (−) ou après (+) l'évènement pour le début, idem pour la fin, priorité, consigne. Les tests sont dans `outils/tests/TkdProjetsTypesTest.php`.

## Budget et trésorerie (`src/Compta.php`, `src/Rest/BudgetController.php`, `assets/budget.js`)

Tout passe par `SpCompta\Integration\ProjetsApi` (SP Compta 0.4.0 ou plus). TKD Projets ne lit jamais les tables de la trésorerie. Sans SP Compta, l'onglet n'apparaît pas.

- **Relier** : un projet TKD est relié à un projet de la trésorerie (colonne `compta_projet_id`). On peut le créer dans la saison qui contient la date de l'évènement (sinon la saison active), ou choisir un projet déjà créé par la trésorière. Enregistrer le budget prévu relie automatiquement.
- **Budget prévu** : des lignes (sens, catégorie de la trésorerie, libellé, montant). Les totaux du projet compta en deviennent la somme.
- **Bilan** : prévu, réalisé et écart, au total et par catégorie. Les dépenses classées hors des catégories prévues sont marquées « hors budget ». Les mouvements rattachés sont listés, sans justificatif.
- **Dépense validée par le président** : date, montant, catégorie, mode de paiement, détail et justificatif. Elle entre directement dans la trésorerie, rattachée au projet. Le justificatif est rangé dans le dossier protégé de SP Compta.
- **Synchronisation** : modifier le projet TKD (nom, dates, état, responsables) met à jour le projet de la trésorerie quand c'est un membre du bureau qui modifie. « Terminé » devient « Clôturé ».
- **Supprimer un projet TKD** ne supprime rien dans la trésorerie : l'argent reste, et le projet compta aussi.

## Données

| Table | Contenu |
|---|---|
| `…tkdp_projects` | projets : type, état (en cours / terminé), date de l'évènement, `compta_projet_id` (lien SP Compta, à venir) |
| `…tkdp_tasks` | tâches ; les pièces jointes sont des numéros de la médiathèque (« 12,15 ») |
| `…tkdp_zones` | zones de la carte (coins en JSON) |
| `…tkdp_members` | équipes : projet, personne, rôle |
| `…tkdp_links` | liens « Ma carte » : **empreinte** de la clé seulement, expiration, coupure |

Options :
- `tkdp_db_version` : version du schéma ;
- `tkdp_gestionnaires` : comptes cochés dans les réglages ;
- `tkdp_president` : le président (seul à valider une dépense de projet).

Droit WordPress : `tkd_projets_gerer`.

À la **suppression** de l'extension (pas à la simple désactivation), `uninstall.php` efface les tables, les options et le droit. Les outils RGPD de WordPress (*Outils → Exporter / Effacer les données personnelles*) couvrent les équipes, les tâches et les zones.

## Sécurité

- **API REST** `tkdp/v1` :
  - le jeton de sécurité est envoyé par `wp.apiFetch` ;
  - chaque route vérifie le rôle dans le projet concerné (`src/Policy.php`, règles testées) ;
  - les champs reçus sont déclarés et contrôlés.
- **Pièces jointes** (`src/Attachments.php`) : seuls les fichiers de la partie publique de la médiathèque, lisibles par la personne, sont acceptés. Les justificatifs de SP Compta (`uploads/sp-compta`, marque `_sp_compta_protege`) et les pistes du gala (`uploads/tkd-gala`) sont **refusés**. En 1.x, ils pouvaient être envoyés par e-mail.
- **Liens « Ma carte »** (`src/ZoneLinks.php`) :
  - clé aléatoire de 128 bits, dont seule l'empreinte HMAC est gardée en base ;
  - le lien expire 30 jours après la date limite de la tâche ou la date de l'évènement (au moins 7 jours, 90 jours sans date) ;
  - il cesse de marcher quand la tâche ou le projet est terminé ;
  - dans la carte : « Renvoyer à tous » (nouveau lien) et « Couper les liens ».
- Ni adresse e-mail ni chemin de fichier n'est envoyé au navigateur.
- Les e-mails à tout un rôle sont réservés aux gestionnaires.
- Suppression d'un projet ou d'une tâche, et enregistrement d'une carte : en transaction (tout ou rien).

## Dépendances externes

- **Leaflet 1.9.4** depuis `unpkg.com`, avec contrôle d'intégrité (SRI).
- Fonds de carte de `tile.openstreetmap.org`.
- Recherche d'adresse par `nominatim.openstreetmap.org` (appelée par le navigateur).

Ces services reçoivent l'adresse IP des visiteurs de la carte : à mentionner dans la politique de confidentialité.

## Tests

- Depuis `outils` : `vendor\bin\phpunit --filter TkdProjets` (PHP 8.3 ou plus).
- Avec PHP 8.2, utiliser le petit lanceur décrit dans `JOURNAL.md` (18/11/2026).
- `verifier.ps1` couvre aussi tkd-projets (syntaxe, PHPStan, PHP_CodeSniffer).

## En cas de bug

- **Le menu Projets n'apparaît pas** : la personne n'est ni gestionnaire ni dans une équipe. L'ajouter à l'équipe d'un projet, ou la cocher dans *Projets → Réglages*.
- **« Désolé, vous n'avez pas l'autorisation » (403) sur une action** : comparer le rôle de la personne dans le projet (`my_role` dans la réponse de `/wp-json/tkdp/v1/projects`) avec le tableau des droits ci-dessus.
- **Une pièce jointe disparaît à l'enregistrement** : le fichier est protégé (SP Compta, Gala), privé, ou hors de la médiathèque. C'est voulu.
- **« Lien expiré » sur la carte** : la tâche est terminée, le projet est terminé, le lien a été remplacé par un plus récent, ou sa date est passée. Utiliser « Renvoyer à tous » dans la carte. Les liens envoyés avant la 2.0 ne marchent plus.
- **Tables ou colonnes absentes** : recharger une page d'administration. `Installer::maybeUpgrade()` compare `tkdp_db_version` à `TKDP_VERSION`.
- **Pas d'onglet Budget** : la personne n'est pas dans « Accès bureau » de SP Compta, ou SP Compta est absente ou plus ancienne que la 0.3.0.
- **Pas de formulaire « Dépense validée par le président »** : la personne n'est pas le président choisi dans *Projets → Réglages*, ou elle n'est pas dans « Accès bureau ».
- **« Aucune saison dans la trésorerie »** : créer et activer une saison dans *Trésorerie → Paramètres*.
- **La carte ne se charge pas** : `unpkg.com` ou OpenStreetMap n'est pas joignable depuis le navigateur.
