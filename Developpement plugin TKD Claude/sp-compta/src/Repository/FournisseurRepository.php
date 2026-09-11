<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Fournisseur;

final class FournisseurRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(Fournisseur $fournisseur): Fournisseur
    {
        if ($fournisseur->id() !== null) {
            return $this->update($fournisseur);
        }

        return $this->insert($fournisseur);
    }

    public function find(int $id): ?Fournisseur
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
     * @return Fournisseur[]
     */
    public function all(): array
    {
        $rows = $this->wpdb()->get_results("SELECT * FROM {$this->table} ORDER BY nom ASC", ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    private function insert(Fournisseur $fournisseur): Fournisseur
    {
        $this->wpdb()->insert($this->table, $this->columns($fournisseur), $this->formats());

        return $fournisseur->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Fournisseur $fournisseur): Fournisseur
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($fournisseur),
            ['id' => $fournisseur->id()],
            $this->formats(),
            ['%d']
        );

        return $fournisseur;
    }

    /**
     * @return array<string, string|null>
     */
    private function columns(Fournisseur $fournisseur): array
    {
        return [
            'nom' => $fournisseur->nom(),
            'adresse' => $fournisseur->adresse(),
            'contact' => $fournisseur->contact(),
            'email' => $fournisseur->email(),
            'telephone' => $fournisseur->telephone(),
            'notes' => $fournisseur->notes(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%s', '%s', '%s', '%s', '%s', '%s'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Fournisseur
    {
        return new Fournisseur(
            (int) $row['id'],
            $row['nom'],
            $row['adresse'],
            $row['contact'],
            $row['email'],
            $row['telephone'],
            $row['notes']
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
