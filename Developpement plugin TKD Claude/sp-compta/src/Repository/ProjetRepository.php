<?php

declare(strict_types=1);

namespace SpCompta\Repository;

use SpCompta\Entity\Projet;

final class ProjetRepository
{
    public function __construct(private string $table)
    {
    }

    public function save(Projet $projet): Projet
    {
        if ($projet->id() !== null) {
            return $this->update($projet);
        }

        return $this->insert($projet);
    }

    public function find(int $id): ?Projet
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
     * Tous les projets d'un exercice, tries par nom.
     *
     * @return Projet[]
     */
    public function forExercice(int $exerciceId): array
    {
        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT * FROM {$this->table} WHERE exercice_id = %d ORDER BY nom ASC",
                $exerciceId
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Projets proposes a la saisie d'une depense/recette : ceux de l'exercice
     * encore "en cours" (un projet cloture reste dans les rapports, mais n'est
     * plus propose).
     *
     * @return Projet[]
     */
    public function enCoursPourExercice(int $exerciceId): array
    {
        return array_values(array_filter(
            $this->forExercice($exerciceId),
            static fn (Projet $projet): bool => $projet->estEnCours()
        ));
    }

    private function insert(Projet $projet): Projet
    {
        $this->wpdb()->insert($this->table, $this->columns($projet), $this->formats());

        return $projet->withId((int) $this->wpdb()->insert_id);
    }

    private function update(Projet $projet): Projet
    {
        $this->wpdb()->update(
            $this->table,
            $this->columns($projet),
            ['id' => $projet->id()],
            $this->formats(),
            ['%d']
        );

        return $projet;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Projet $projet): array
    {
        return [
            'exercice_id' => $projet->exerciceId(),
            'nom' => $projet->nom(),
            'nature' => $projet->nature(),
            'budget_depenses' => $projet->budgetDepenses(),
            'budget_recettes' => $projet->budgetRecettes(),
            'responsable' => $projet->responsable(),
            'date_debut' => $projet->dateDebut(),
            'date_fin' => $projet->dateFin(),
            'description' => $projet->description(),
            'statut' => $projet->statut(),
        ];
    }

    /**
     * @return string[]
     */
    private function formats(): array
    {
        return ['%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Projet
    {
        return new Projet(
            (int) $row['id'],
            (int) $row['exercice_id'],
            (string) $row['nom'],
            (string) $row['nature'],
            (float) $row['budget_depenses'],
            (float) $row['budget_recettes'],
            (string) $row['responsable'],
            $row['date_debut'] !== null && $row['date_debut'] !== '' ? (string) $row['date_debut'] : null,
            $row['date_fin'] !== null && $row['date_fin'] !== '' ? (string) $row['date_fin'] : null,
            $row['description'],
            (string) $row['statut']
        );
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
