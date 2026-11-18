# Integration/ProjetsApi

## Rôle

**Porte d'entrée documentée** de SP Compta pour l'extension **TKD Projets** (« Gestion de projets », 18/11/2026). TKD Projets ne lit ni n'écrit jamais les tables de SP Compta directement. Elle passe par cette classe, ce qui garde un seul endroit à maintenir si le schéma change.

Seconde exception documentée à l'indépendance de SP Compta, après [SpBuildReader](SpBuildReader.md). Celle-ci va dans l'autre sens : c'est SP Compta qui s'expose.

Décisions de l'utilisateur (18/11/2026) :
- l'argent reste dans SP Compta ;
- le budget est **visible du bureau seulement** ;
- seul le président propose et valide une dépense de projet, qui entre alors directement ici. Ce dernier point sera ajouté à cette classe à l'étape 4.

## Utilisation depuis une autre extension

```php
if (class_exists(\SpCompta\Integration\ProjetsApi::class)) {
    $api = \SpCompta\Integration\ProjetsApi::depuisPlugin();
    if ($api->peutConsulter()) {
        $bilan = $api->bilan($comptaProjetId);
    }
}
```

`depuisPlugin()` lance aussi `Database::maybeUpgrade()`, pour que la table des lignes existe même si personne n'a encore ouvert wp-admin depuis le déploiement.

## API

Toutes les méthodes, sauf `peutConsulter()` et `categories()`, lèvent une `\RuntimeException` (code 403) si la personne n'a pas `sp_compta_manager`. Les données invalides lèvent une `\InvalidArgumentException`, dont le message en français peut être affiché tel quel.

| Méthode | Rôle |
|---|---|
| `peutConsulter(): bool` | la personne est du bureau |
| `saisons()` | `id`, `libelle` (« 2026-2027 »), `debut`, `fin`, `actif` |
| `saisonPourDate(?string)` | saison qui contient la date, sinon la saison active, sinon null |
| `categories()` | `['depense' => ['60' => '60 - Achat', …], 'recette' => […]]` |
| `projet(int)` | projet compta en tableau, ou null |
| `enregistrerProjet(?int $id, array $data): int` | crée ou met à jour (nom, nature, `exercice_id` à la création seulement, responsable, dates, description, statut). Ne touche pas aux totaux prévus. |
| `lignesBudget(int)` | lignes du budget |
| `enregistrerLignesBudget(int, array)` | remplace les lignes (transaction), puis met `budget_depenses` / `budget_recettes` du projet à la **somme des lignes** |
| `lignesValidees(int, array)` (statique) | validation seule, sans base de données |
| `bilan(int)` | `projet`, `totaux` (de `ProjetBilan::pour()`), `categories` (de `ProjetBilan::parCategorie()`) |
| `mouvements(int)` | dépenses et recettes du projet (montant négatif pour une dépense), sans justificatif |

### Validation des lignes

- **Ligne vide** (montant nul et pas de libellé) : ignorée.
- **Sens** : `depense` ou `recette`.
- **Catégorie** : un code du référentiel du même sens.
- **Montant** : au format français accepté (`ProjetBilan::montantSaisi()`).
- **Taille** : 100 lignes au plus.

## Scénarios (tests : `tests/Unit/Integration/ProjetsApiTest.php`)

```gherkin
Scénario: hors bureau, toute lecture est refusée
Scénario: un projet créé par l'API est dans la saison demandée, sans budget
Scénario: un projet existant ne change pas de saison
Scénario: enregistrer des lignes met les totaux du projet à jour
Scénario: une ligne vide est ignorée, une catégorie inconnue refusée
Scénario: le bilan par catégorie compte les mouvements du projet
```

## En cas de bug

- **« Réservé au bureau »** : cocher la personne dans *Trésorerie → Paramètres → Accès bureau*.
- **« Saison inconnue »** à la création : aucune saison ne contient la date, et aucune n'est active.
- **Le rapport AG ne montre pas le budget détaillé** : c'est voulu. Il lit les totaux, tenus à jour par `enregistrerLignesBudget()`.
- **Table `…sp_compta_budget_ligne` absente** : `DB_VERSION` 1.5.0 n'a pas été appliquée. `depuisPlugin()` le fait ; sinon, recharger wp-admin.
