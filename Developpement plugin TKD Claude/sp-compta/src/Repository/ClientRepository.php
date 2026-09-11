<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Client;

final class ClientRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(Client $client): Client
    {
        if ($client->id() !== null) {
            return $this->update($client);
        }

        return $this->insert($client);
    }

    public function find(int $id): ?Client
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
     * @return Client[]
     */
    public function all(): array
    {
        $rows = $this->wpdb()->get_results("SELECT * FROM {$this->table} ORDER BY nom ASC", ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    private function insert(Client $client): Client
    {
        $this->wpdb()->insert($this->table, $this->columns($client), $this->formats());

        return $client->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Client $client): Client
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($client),
            ['id' => $client->id()],
            $this->formats(),
            ['%d']
        );

        return $client;
    }

    /**
     * @return array<string, string|null>
     */
    private function columns(Client $client): array
    {
        return [
            'type' => $client->type(),
            'nom' => $client->nom(),
            'adresse' => $client->adresse(),
            'code_postal' => $client->codePostal(),
            'ville' => $client->ville(),
            'email' => $client->email(),
            'telephone' => $client->telephone(),
            'notes' => $client->notes(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Client
    {
        return new Client(
            (int) $row['id'],
            $row['nom'],
            $row['type'],
            $row['adresse'],
            $row['code_postal'],
            $row['ville'],
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
