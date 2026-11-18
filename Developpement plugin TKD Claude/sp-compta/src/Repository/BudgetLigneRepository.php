<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\BudgetLigne;

/**
 * Lignes de budget des projets (table sp_compta_budget_ligne) - voir
 * BudgetLigneRepository.md.
 */
final class BudgetLigneRepository
{
    public function __construct(private string $table)
    {
    }

    /**
     * @return BudgetLigne[] lignes du projet, dans l'ordre de saisie
     */
    public function forProjet(int $projetId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->table} WHERE projet_id = %d ORDER BY ordre ASC, id ASC",
                $projetId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Remplace toutes les lignes du projet par celles fournies (tout ou rien).
     *
     * @param BudgetLigne[] $lignes
     * @return BudgetLigne[] lignes enregistrees, avec leur id
     */
    public function remplacer(int $projetId, array $lignes): array
    {
        $wpdb = $this->wpdb();
        $wpdb->query('START TRANSACTION');

        try {
            $this->deleteForProjet($projetId);
            $enregistrees = [];
            foreach (array_values($lignes) as $i => $ligne) {
                $ok = $wpdb->insert(
                    $this->table,
                    [
                        'projet_id' => $projetId,
                        'sens' => $ligne->sens(),
                        'categorie' => $ligne->categorie(),
                        'libelle' => $ligne->libelle(),
                        'montant' => $ligne->montant(),
                        'ordre' => $i,
                    ],
                    ['%d', '%s', '%s', '%s', '%f', '%d']
                );
                if ($ok === false) {
                    throw new \RuntimeException('Une ligne de budget n\'a pas pu être enregistrée.');
                }
                $enregistrees[] = new BudgetLigne(
                    (int) $wpdb->insert_id,
                    $projetId,
                    $ligne->sens(),
                    $ligne->categorie(),
                    $ligne->libelle(),
                    $ligne->montant(),
                    $i
                );
            }
            $wpdb->query('COMMIT');

            return $enregistrees;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function deleteForProjet(int $projetId): int
    {
        return (int) $this->wpdb()->delete($this->table, ['projet_id' => $projetId], ['%d']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BudgetLigne
    {
        return new BudgetLigne(
            (int) $row['id'],
            (int) $row['projet_id'],
            (string) $row['sens'],
            (string) $row['categorie'],
            (string) $row['libelle'],
            (float) $row['montant'],
            (int) $row['ordre']
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
