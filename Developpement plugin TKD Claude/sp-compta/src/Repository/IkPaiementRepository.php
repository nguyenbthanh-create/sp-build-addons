<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\IkPaiement;

final class IkPaiementRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(IkPaiement $ik): IkPaiement
    {
        if ($ik->id() !== null) {
            return $this->update($ik);
        }

        return $this->insert($ik);
    }

    public function find(int $id): ?IkPaiement
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

    public function findByTrainerPeriode(int $trainerId, int $annee, int $mois): ?IkPaiement
    {
        $row = $this->wpdb()->get_row(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->table} WHERE trainer_id = %d AND annee = %d AND mois = %d",
                $trainerId,
                $annee,
                $mois
            ),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array<string, IkPaiement>  Clé "trainerId_mois" pour un lookup facile depuis l'écran.
     */
    public function forAnnee(int $annee): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare("SELECT * FROM {$this->table} WHERE annee = %d", $annee),
            ARRAY_A
        );

        $result = [];
        foreach ($rows as $row) {
            $ik = $this->hydrate($row);
            $result[$ik->trainerId() . '_' . $ik->mois()] = $ik;
        }

        return $result;
    }

    private function insert(IkPaiement $ik): IkPaiement
    {
        $this->wpdb()->insert($this->table, $this->columns($ik), $this->formats());

        return $ik->withId((int) $this->wpdb()->insert_id);
    }

    private function update(IkPaiement $ik): IkPaiement
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($ik),
            ['id' => $ik->id()],
            $this->formats(),
            ['%d']
        );

        return $ik;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(IkPaiement $ik): array
    {
        return [
            'trainer_id' => $ik->trainerId(),
            'annee' => $ik->annee(),
            'mois' => $ik->mois(),
            'paye' => $ik->paye() ? 1 : 0,
            'montant_verse' => $ik->montantVerse(),
            'date_paiement' => $ik->datePaiement(),
            'depense_id' => $ik->depenseId(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%d', '%d', '%d', '%f', '%s', '%d'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): IkPaiement
    {
        return new IkPaiement(
            (int) $row['id'],
            (int) $row['trainer_id'],
            (int) $row['annee'],
            (int) $row['mois'],
            (bool) $row['paye'],
            $row['montant_verse'] !== null ? (float) $row['montant_verse'] : null,
            $row['date_paiement'] !== null && $row['date_paiement'] !== '' ? $row['date_paiement'] : null,
            $row['depense_id'] !== null ? (int) $row['depense_id'] : null
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
