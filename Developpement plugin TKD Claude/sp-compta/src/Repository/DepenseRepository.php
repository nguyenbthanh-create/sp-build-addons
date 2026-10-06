<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Depense;

final class DepenseRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(Depense $depense): Depense
    {
        if ($depense->id() !== null) {
            return $this->update($depense);
        }

        return $this->insert($depense);
    }

    public function find(int $id): ?Depense
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
     * @return Depense[]
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
     * Depenses rattachees a un projet (voir Entity/Projet.md).
     *
     * @return Depense[]
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
     * Rattache d'un coup plusieurs depenses deja saisies a un projet (null =
     * les remettre en fonctionnement courant). Limite aux depenses de
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
     * Remet en fonctionnement courant toutes les depenses d'un projet (a la
     * suppression du projet : les montants ne disparaissent jamais).
     */
    public function detacherDuProjet(int $projetId): int
    {
        return (int) $this->wpdb()->query(
            $this->wpdb()->prepare("UPDATE {$this->table} SET projet_id = NULL WHERE projet_id = %d", $projetId)
        );
    }

    private function insert(Depense $depense): Depense
    {
        $this->wpdb()->insert($this->table, $this->columns($depense), $this->formats());

        return $depense->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Depense $depense): Depense
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($depense),
            ['id' => $depense->id()],
            $this->formats(),
            ['%d']
        );

        return $depense;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Depense $depense): array
    {
        return [
            'exercice_id' => $depense->exerciceId(),
            'date' => $depense->date(),
            'montant' => $depense->montant(),
            'fournisseur_id' => $depense->fournisseurId(),
            'categorie' => $depense->categorie(),
            'sous_categorie' => $depense->sousCategorie(),
            'detail' => $depense->detail(),
            'mode_paiement' => $depense->modePaiement(),
            'justificatif' => $depense->justificatif(),
            'projet_id' => $depense->projetId(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%d'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Depense
    {
        return new Depense(
            (int) $row['id'],
            (int) $row['exercice_id'],
            $row['date'],
            (float) $row['montant'],
            $row['fournisseur_id'] !== null ? (int) $row['fournisseur_id'] : null,
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
