<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Recette;

final class RecetteRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(Recette $recette): Recette
    {
        if ($recette->id() !== null) {
            return $this->update($recette);
        }

        return $this->insert($recette);
    }

    public function find(int $id): ?Recette
    {
        $row = $this->wpdb()->get_row(
            $this->wpdb()->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->wpdb()->delete($this->table, ['id' => $id], ['%d']);
    }

    /**
     * @return Recette[]
     */
    public function forExercice(int $exerciceId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->table} WHERE exercice_id = %d ORDER BY date DESC",
                $exerciceId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Recettes rattachees a un projet (voir Entity/Projet.md).
     *
     * @return Recette[]
     */
    public function forProjet(int $projetId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->table} WHERE projet_id = %d ORDER BY date DESC",
                $projetId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Rattache d'un coup plusieurs recettes deja saisies a un projet (null =
     * les remettre en fonctionnement courant). Limite aux recettes de
     * l'exercice donne, pour ne jamais deplacer une ligne d'une autre saison.
     *
     * @param int[] $ids
     * @return int nombre de lignes modifiees
     */
    public function rattacherAuProjet(array $ids, ?int $projetId, int $exerciceId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $valeur = $projetId === null ? 'NULL' : '%d';
        $args = $projetId === null ? [...$ids, $exerciceId] : [$projetId, ...$ids, $exerciceId];

        return (int) $this->wpdb()->query(
            $this->wpdb()->prepare(
                "UPDATE {$this->table} SET projet_id = {$valeur} WHERE id IN ({$placeholders}) AND exercice_id = %d",
                ...$args
            )
        );
    }

    /**
     * Remet en fonctionnement courant toutes les recettes d'un projet (a la
     * suppression du projet : les montants ne disparaissent jamais).
     */
    public function detacherDuProjet(int $projetId): int
    {
        return (int) $this->wpdb()->query(
            $this->wpdb()->prepare("UPDATE {$this->table} SET projet_id = NULL WHERE projet_id = %d", $projetId)
        );
    }

    private function insert(Recette $recette): Recette
    {
        $this->wpdb()->insert($this->table, $this->columns($recette), $this->formats());

        return $recette->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Recette $recette): Recette
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($recette),
            ['id' => $recette->id()],
            $this->formats(),
            ['%d']
        );

        return $recette;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Recette $recette): array
    {
        return [
            'exercice_id' => $recette->exerciceId(),
            'date' => $recette->date(),
            'montant' => $recette->montant(),
            'provenance' => $recette->provenance(),
            'client_id' => $recette->clientId(),
            'categorie' => $recette->categorie(),
            'sous_categorie' => $recette->sousCategorie(),
            'detail' => $recette->detail(),
            'mode_paiement' => $recette->modePaiement(),
            'justificatif' => $recette->justificatif(),
            'projet_id' => $recette->projetId(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Recette
    {
        return new Recette(
            (int) $row['id'],
            (int) $row['exercice_id'],
            $row['date'],
            (float) $row['montant'],
            $row['provenance'],
            $row['client_id'] !== null ? (int) $row['client_id'] : null,
            $row['categorie'],
            $row['detail'],
            $row['mode_paiement'],
            $row['justificatif'],
            (string) ($row['sous_categorie'] ?? ''),
            isset($row['projet_id']) && $row['projet_id'] !== null ? (int) $row['projet_id'] : null
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
