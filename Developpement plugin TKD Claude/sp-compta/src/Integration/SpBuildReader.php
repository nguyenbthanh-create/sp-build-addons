<?php

declare(strict_types=1);

namespace SpCompta\Integration;

/**
 * Seul point de lecture des données sp_build (entraîneurs, km, tarif) dans tout sp-compta.
 * Exception documentée et volontaire à la règle "sp-compta n'a aucune dépendance à sp_build"
 * (cf. CLAUDE.md), acceptée pour le module IK afin d'éviter une double saisie des mêmes
 * données déjà gérées côté sp_build (fiche entraîneur, km exceptionnels, tarif €/km).
 *
 * Tout est lu directement en SQL (tables simples), sauf le nombre d'AR/mois : ce chiffre est
 * calculé par un algorithme non trivial côté sp_build (expansion des créneaux récurrents +
 * disponibilités) qu'on ne duplique pas ici — on passe par le filtre WordPress documenté
 * `sp_cal_interventions_par_trainer`, exposé par SpCalPro_Notifications::filter_interventions_par_trainer().
 *
 * Se dégrade silencieusement (tableaux/valeurs vides) si sp_build n'est pas actif ou que ses
 * tables n'existent pas encore — le module IK de sp-compta ne doit jamais fataliser pour autant.
 */
final class SpBuildReader
{
    /**
     * @return bool  true si les tables sp_build nécessaires existent sur ce site.
     */
    public function disponible(): bool
    {
        return $this->wpdb()->get_var(
            $this->wpdb()->prepare('SHOW TABLES LIKE %s', $this->tableTrainers())
        ) !== null;
    }

    /**
     * @return list<array{id: int, nom: string, km: float}>  Entraîneurs actifs (rôle "entraineur").
     */
    public function trainersActifs(): array
    {
        if (!$this->disponible()) {
            return [];
        }

        $rows = $this->wpdb()->get_results(
            "SELECT id, nom, roles, km_aller_retour FROM {$this->tableTrainers()} WHERE actif = 1 ORDER BY ordre ASC, nom ASC",
            ARRAY_A
        );

        $trainers = [];
        foreach ($rows as $row) {
            if (strpos((string) $row['roles'], 'entraineur') === false) {
                continue;
            }
            $trainers[] = [
                'id' => (int) $row['id'],
                'nom' => trim((string) $row['nom']),
                'km' => (float) $row['km_aller_retour'],
            ];
        }

        return $trainers;
    }

    /**
     * Nombre d'interventions (AR) par entraîneur pour le mois donné — via le filtre WordPress
     * exposé par sp_build, jamais recalculé ici (cf. commentaire de classe).
     *
     * @return array<int, int>  [ trainer_id => nombre d'AR ]
     */
    public function interventionsParTrainer(int $annee, int $mois): array
    {
        $resultat = apply_filters('sp_cal_interventions_par_trainer', [], $annee, $mois);

        return is_array($resultat) ? $resultat : [];
    }

    /**
     * Somme des km exceptionnels saisis par entraîneur pour le mois donné (table
     * sp_cal_km_exceptionnels — simple valeur, lue directement).
     *
     * @return array<int, float>  [ trainer_id => total km exceptionnels ]
     */
    public function kmExceptionnelsParTrainer(int $annee, int $mois): array
    {
        if (!$this->disponible()) {
            return [];
        }

        $debut = sprintf('%04d-%02d-01', $annee, $mois);
        $fin = gmdate('Y-m-t', strtotime($debut));

        $rows = $this->wpdb()->get_results(
            $this->wpdb()->prepare(
                "SELECT trainer_id, SUM(km) AS total_km FROM {$this->tableKmExceptionnels()}
                 WHERE date BETWEEN %s AND %s GROUP BY trainer_id",
                $debut,
                $fin
            ),
            ARRAY_A
        );

        $totaux = [];
        foreach ($rows as $row) {
            $totaux[(int) $row['trainer_id']] = (float) $row['total_km'];
        }

        return $totaux;
    }

    /**
     * Tarif €/km configuré dans sp_build (Paramètres → Récapitulatif mensuel).
     */
    public function tarifKm(): float
    {
        return (float) get_option('sp_cal_tarif_km', 0);
    }

    private function tableTrainers(): string
    {
        return $this->wpdb()->prefix . 'sp_cal_trainers';
    }

    private function tableKmExceptionnels(): string
    {
        return $this->wpdb()->prefix . 'sp_cal_km_exceptionnels';
    }

    private function wpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
