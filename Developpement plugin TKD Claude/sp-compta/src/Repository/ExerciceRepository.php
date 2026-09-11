<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Exercice;

final class ExerciceRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(Exercice $exercice): Exercice
    {
        if ($exercice->id() !== null) {
            return $this->update($exercice);
        }

        return $this->insert($exercice);
    }

    public function find(int $id): ?Exercice
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

    public function active(): ?Exercice
    {
        $row = $this->wpdb()->get_row(
            "SELECT * FROM {$this->table} WHERE actif = 1 LIMIT 1",
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Rend l'exercice donne actif et desactive tous les autres.
     */
    public function activate(int $id): void
    {
        $this->wpdb()->update($this->table, ['actif' => 0], ['actif' => 1], ['%d'], ['%d']);
        $this->wpdb()->update($this->table, ['actif' => 1], ['id' => $id], ['%d'], ['%d']);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->wpdb()->delete($this->table, ['id' => $id], ['%d']);
    }

    /**
     * @return Exercice[]
     */
    public function all(): array
    {
        $rows = $this->wpdb()->get_results("SELECT * FROM {$this->table} ORDER BY date_debut DESC", ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    private function insert(Exercice $exercice): Exercice
    {
        $this->wpdb()->insert($this->table, $this->columns($exercice), $this->formats());

        return $exercice->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Exercice $exercice): Exercice
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($exercice),
            ['id' => $exercice->id()],
            $this->formats(),
            ['%d']
        );

        return $exercice;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Exercice $exercice): array
    {
        return [
            'date_debut' => $exercice->dateDebut(),
            'date_fin' => $exercice->dateFin(),
            'solde_initial' => $exercice->soldeInitial(),
            'actif' => $exercice->actif() ? 1 : 0,
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%s', '%s', '%f', '%d'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Exercice
    {
        return new Exercice(
            (int) $row['id'],
            $row['date_debut'],
            $row['date_fin'],
            (float) $row['solde_initial'],
            (bool) $row['actif']
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
