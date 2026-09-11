<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Devis;
use SpCompta\Entity\LigneDocument;
use SpCompta\Numbering\DocumentNumeroGenerator;

final class DevisRepository
{
    public function __construct(
        private string $table,
        private string $ligneTable,
        private DocumentNumeroGenerator $numeroGenerator
    ) {
    }

    public function save(Devis $devis): Devis
    {
        if ($devis->id() !== null) {
            return $this->update($devis);
        }

        return $this->insert($devis);
    }

    public function find(int $id): ?Devis
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
        $this->wpdb()->delete($this->ligneTable, ['devis_id' => $id], ['%d']);

        return (bool) $this->wpdb()->delete($this->table, ['id' => $id], ['%d']);
    }

    /**
     * @return Devis[]
     */
    public function forExercice(int $exerciceId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->table} WHERE exercice_id = %d ORDER BY date_creation DESC",
                $exerciceId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    private function insert(Devis $devis): Devis
    {
        $numero = $this->numeroGenerator->next(substr($devis->dateCreation(), 0, 4));

        $this->wpdb()->insert($this->table, $this->columns($devis, $numero), $this->formats());
        $id = (int) $this->wpdb()->insert_id;

        $lignes = $this->insertLignes($id, $devis->lignes());

        return new Devis(
            $id,
            $devis->exerciceId(),
            $devis->clientId(),
            $devis->dateCreation(),
            $devis->dateValidite(),
            $lignes,
            $devis->statut(),
            $numero,
            $devis->notes()
        );
    }

    private function update(Devis $devis): Devis
    {
        $id = (int) $devis->id();

        $this->wpdb()->update(
            $this->table,
            $this->columns($devis, $devis->numero()),
            ['id' => $id],
            $this->formats(),
            ['%d']
        );

        $this->wpdb()->delete($this->ligneTable, ['devis_id' => $id], ['%d']);
        $lignes = $this->insertLignes($id, $devis->lignes());

        return new Devis(
            $id,
            $devis->exerciceId(),
            $devis->clientId(),
            $devis->dateCreation(),
            $devis->dateValidite(),
            $lignes,
            $devis->statut(),
            $devis->numero(),
            $devis->notes()
        );
    }

    /**
     * @param LigneDocument[] $lignes
     * @return LigneDocument[]
     */
    private function insertLignes(int $devisId, array $lignes): array
    {
        $result = [];

        foreach ($lignes as $ligne) {
            $this->wpdb()->insert(
                $this->ligneTable,
                [
                    'devis_id' => $devisId,
                    'designation' => $ligne->designation(),
                    'quantite' => $ligne->quantite(),
                    'prix_unitaire' => $ligne->prixUnitaire(),
                ],
                ['%d', '%s', '%f', '%f']
            );

            $result[] = $ligne->withId((int) $this->wpdb()->insert_id);
        }

        return $result;
    }

    /**
     * @return LigneDocument[]
     */
    private function findLignes(int $devisId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->ligneTable} WHERE devis_id = %d ORDER BY id ASC",
                $devisId
            ),
            ARRAY_A
        );

        return array_map(
            static fn (array $row): LigneDocument => new LigneDocument(
                (int) $row['id'],
                $row['designation'],
                (float) $row['quantite'],
                (float) $row['prix_unitaire']
            ),
            $rows
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Devis $devis, string $numero): array
    {
        return [
            'exercice_id' => $devis->exerciceId(),
            'numero' => $numero,
            'client_id' => $devis->clientId(),
            'date_creation' => $devis->dateCreation(),
            'date_validite' => $devis->dateValidite(),
            'statut' => $devis->statut(),
            'notes' => $devis->notes(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%d', '%s', '%s', '%s', '%s'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Devis
    {
        return new Devis(
            (int) $row['id'],
            (int) $row['exercice_id'],
            (int) $row['client_id'],
            $row['date_creation'],
            $row['date_validite'],
            $this->findLignes((int) $row['id']),
            $row['statut'],
            $row['numero'],
            (string) $row['notes']
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
