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
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];
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
            (string) ($row['sous_categorie'] ?? '')
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
