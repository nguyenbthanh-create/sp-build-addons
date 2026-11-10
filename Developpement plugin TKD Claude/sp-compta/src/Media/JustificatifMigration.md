# Media/JustificatifMigration.php

**Rôle** (10/11/2026) : ranger une fois pour toutes, dans `uploads/sp-compta/`, les justificatifs envoyés avant [JustificatifStorage](JustificatifStorage.md).

## Fonctionnement

- **Quand ça se lance** : au premier `admin_init` d'un compte `sp_compta_manager` après le déploiement. Un verrou de 5 minutes empêche deux passages en même temps.
- **`idsReferences()`** : relève les ids cités par `depense.justificatif`, `recette.justificatif` et `sponsor.fichier_contrat`, en ne gardant que les valeurs numériques. Les anciennes références en texte libre sont ignorées.
- **Déplacement** : chaque id passe par `JustificatifStorage::deplacer()`.
- **Résultat** : le bilan (`deplace`, `deja`, `absent`, `echec`) et les ids en échec vont dans l'option `sp_compta_justificatifs_journal`.
- **Fin** : l'option `sp_compta_justificatifs_proteges = '1'` n'est posée que s'il n'y a eu **aucun échec**. Sinon, le passage est rejoué au prochain chargement d'une page admin, et les ids en échec sont écrits dans le journal d'erreurs PHP.

**Hors périmètre** : un fichier de la médiathèque qui n'est cité par aucune ligne SP Compta n'est pas déplacé. Un PDF envoyé à la main dans Médias, par exemple, reste où il est.

## En cas de bug

- **Rien n'a bougé** : vérifier que la page admin a été chargée par un compte trésorerie, et lire l'option `sp_compta_justificatifs_journal`.
- **Échec répété sur un id** : un fichier du même nom existe déjà dans `uploads/sp-compta/AAAA/MM/` (jamais écrasé), ou bien les droits d'écriture manquent.
