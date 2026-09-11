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
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s'];
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
            (string) ($row['sous_categorie'] ?? '')
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
