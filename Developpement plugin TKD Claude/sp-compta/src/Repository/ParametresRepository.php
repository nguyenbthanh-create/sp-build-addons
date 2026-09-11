<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Parametres;

final class ParametresRepository
{
    public function __construct(private string $table)
    {
    }

    public function get(): ?Parametres
    {
        $row = $this->wpdb()->get_row("SELECT * FROM {$this->table} LIMIT 1", ARRAY_A);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Cree la ligne unique de parametres si elle n'existe pas encore,
     * sinon met a jour la ligne existante quel que soit l'id fourni
     * (garantit qu'il n'y a jamais qu'une seule ligne).
     */
    public function save(Parametres $parametres): Parametres
    {
        $existing = $this->get();

        if ($existing === null) {
            return $this->insert($parametres);
        }

        $toUpdate = $parametres->withId((int) $existing->id());
        $this->update($toUpdate);

        return $toUpdate;
    }

    private function insert(Parametres $parametres): Parametres
    {
        $this->wpdb()->insert($this->table, $this->columns($parametres), $this->formats());

        return $parametres->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Parametres $parametres): void
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($parametres),
            ['id' => $parametres->id()],
            $this->formats(),
            ['%d']
        );
    }

    /**
     * @return array<string, string>
     */
    private function columns(Parametres $parametres): array
    {
        return [
            'siret' => $parametres->siret(),
            'siege_social' => $parametres->siegeSocial(),
            'nom_association' => $parametres->nomAssociation(),
            'logo_url' => $parametres->logoUrl(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%s', '%s', '%s', '%s'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Parametres
    {
        return new Parametres(
            (int) $row['id'],
            $row['siret'],
            $row['siege_social'],
            $row['nom_association'],
            $row['logo_url']
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
