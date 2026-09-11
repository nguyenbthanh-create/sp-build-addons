<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Sponsor;

final class SponsorRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(Sponsor $sponsor): Sponsor
    {
        if ($sponsor->id() !== null) {
            return $this->update($sponsor);
        }

        return $this->insert($sponsor);
    }

    public function find(int $id): ?Sponsor
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
     * @return Sponsor[]
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

    private function insert(Sponsor $sponsor): Sponsor
    {
        $this->wpdb()->insert($this->table, $this->columns($sponsor), $this->formats());

        return $sponsor->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Sponsor $sponsor): Sponsor
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($sponsor),
            ['id' => $sponsor->id()],
            $this->formats(),
            ['%d']
        );

        return $sponsor;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Sponsor $sponsor): array
    {
        return [
            'exercice_id' => $sponsor->exerciceId(),
            'nom' => $sponsor->nom(),
            'montant' => $sponsor->montant(),
            'type_paiement' => $sponsor->typePaiement(),
            'date' => $sponsor->date(),
            'contrat_signe' => $sponsor->contratSigne() ? 1 : 0,
            'fichier_contrat' => $sponsor->fichierContrat(),
            'contrat_paye' => $sponsor->contratPaye() ? 1 : 0,
            'recette_id' => $sponsor->recetteId(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%f', '%s', '%s', '%d', '%s', '%d', '%d'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Sponsor
    {
        return new Sponsor(
            (int) $row['id'],
            (int) $row['exercice_id'],
            $row['nom'],
            (float) $row['montant'],
            $row['date'],
            $row['type_paiement'],
            (bool) $row['contrat_signe'],
            $row['fichier_contrat'],
            (bool) $row['contrat_paye'],
            $row['recette_id'] !== null ? (int) $row['recette_id'] : null
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
