<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Facture;
use SpCompta\Entity\LigneDocument;
use SpCompta\Numbering\DocumentNumeroGenerator;

final class FactureRepository
{
    public function __construct(
        private string $table,
        private string $ligneTable,
        private DocumentNumeroGenerator $numeroGenerator
    ) {
    }

    public function save(Facture $facture): Facture
    {
        if ($facture->id() !== null) {
            return $this->update($facture);
        }

        return $this->insert($facture);
    }

    public function find(int $id): ?Facture
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

    /**
     * @return Facture[]
     */
    public function forExercice(int $exerciceId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->table} WHERE exercice_id = %d ORDER BY date_emission DESC",
                $exerciceId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    private function insert(Facture $facture): Facture
    {
        $numero = $this->numeroGenerator->next(substr($facture->dateEmission(), 0, 4));

        $this->wpdb()->insert($this->table, $this->columns($facture, $numero), $this->formats());
        $id = (int) $this->wpdb()->insert_id;

        $lignes = $this->insertLignes($id, $facture->lignes());

        return new Facture(
            $id,
            $facture->exerciceId(),
            $facture->clientId(),
            $facture->dateEmission(),
            $facture->dateEcheance(),
            $lignes,
            $facture->statut(),
            $numero,
            $facture->devisId(),
            $facture->modeReglement(),
            $facture->notes()
        );
    }

    private function update(Facture $facture): Facture
    {
        $id = (int) $facture->id();

        $this->wpdb()->update(
            $this->table,
            $this->columns($facture, $facture->numero()),
            ['id' => $id],
            $this->formats(),
            ['%d']
        );

        $this->wpdb()->delete($this->ligneTable, ['facture_id' => $id], ['%d']);
        $lignes = $this->insertLignes($id, $facture->lignes());

        return new Facture(
            $id,
            $facture->exerciceId(),
            $facture->clientId(),
            $facture->dateEmission(),
            $facture->dateEcheance(),
            $lignes,
            $facture->statut(),
            $facture->numero(),
            $facture->devisId(),
            $facture->modeReglement(),
            $facture->notes()
        );
    }

    /**
     * @param LigneDocument[] $lignes
     * @return LigneDocument[]
     */
    private function insertLignes(int $factureId, array $lignes): array
    {
        $result = [];

        foreach ($lignes as $ligne) {
            $this->wpdb()->insert(
                $this->ligneTable,
                [
                    'facture_id' => $factureId,
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
    private function findLignes(int $factureId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->ligneTable} WHERE facture_id = %d ORDER BY id ASC",
                $factureId
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
    private function columns(Facture $facture, string $numero): array
    {
        return [
            'exercice_id' => $facture->exerciceId(),
            'numero' => $numero,
            'devis_id' => $facture->devisId(),
            'client_id' => $facture->clientId(),
            'date_emission' => $facture->dateEmission(),
            'date_echeance' => $facture->dateEcheance(),
            'statut' => $facture->statut(),
            'mode_reglement' => $facture->modeReglement(),
            'notes' => $facture->notes(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Facture
    {
        return new Facture(
            (int) $row['id'],
            (int) $row['exercice_id'],
            (int) $row['client_id'],
            $row['date_emission'],
            $row['date_echeance'],
            $this->findLignes((int) $row['id']),
            $row['statut'],
            $row['numero'],
            $row['devis_id'] !== null ? (int) $row['devis_id'] : null,
            $row['mode_reglement'],
            (string) $row['notes']
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
