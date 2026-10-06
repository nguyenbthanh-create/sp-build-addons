# Repository/ProjetRepository

## Rôle

Accès SQL à la table `sp_compta_projet` (patron : [FournisseurRepository](FournisseurRepository.php)). Entité : [Entity/Projet.md](../Entity/Projet.md).

## API

- `save(Projet)` : insère (id null) ou met à jour.
- `find(int)` : projet ou null.
- `delete(int)` : supprime la ligne **seule** — pour supprimer un projet sans perdre d'argent, passer par `ProjetScreen::deleteFromRequest()`, qui remet d'abord ses mouvements en fonctionnement courant.
- `forExercice(int)` : projets d'une saison, triés par nom.
- `enCoursPourExercice(int)` : projets proposés à la saisie (statut « en cours »).

Les mouvements d'un projet se lisent dans `DepenseRepository::forProjet()` / `RecetteRepository::forProjet()` ; le rattachement en lot passe par leur `rattacherAuProjet(ids, projetId|null, exerciceId)` (toujours limité à la saison) et `detacherDuProjet(projetId)`.

## Scénarios (tests : `tests/Unit/Repository/ProjetRepositoryTest.php`)

```gherkin
Scénario: un projet et son budget sont relus à l'identique
Scénario: des dates vides restent nulles (pas de 0000-00-00)
Scénario: seuls les projets de la saison demandée sont listés, triés par nom
Scénario: un projet clôturé n'est pas proposé à la saisie
```

## En cas de bug

- Table absente : `DB_VERSION` pas relu → recharger une page wp-admin (`Database::maybeUpgrade()` sur `admin_init`).
- Dates à `0000-00-00` : la colonne doit être `DATE NULL` et la valeur `null` (pas `''`), voir `hydrate()`.
