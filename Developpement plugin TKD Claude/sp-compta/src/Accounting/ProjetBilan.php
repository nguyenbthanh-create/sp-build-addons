<?php

declare(strict_types=1);

namespace SpCompta\Accounting;

use SpCompta\Entity\Depense;
use SpCompta\Entity\Projet;
use SpCompta\Entity\Recette;

/**
 * Bilan "prevu / realise" des projets d'une saison, et separation
 * fonctionnement courant / projets pour l'AG. Classe pure (aucun acces base
 * de donnees ni WordPress) : seule source de ces calculs, utilisee par
 * l'ecran Projets et par le rapport AG - voir ProjetBilan.md.
 *
 * Conventions : montants toujours positifs en entree (comme en base) ; le
 * "net" vaut recettes - depenses (negatif = le projet a coute au club) ;
 * l'ecart vaut realise - prevu.
 */
final class ProjetBilan
{
    /**
     * Bilan d'un projet a partir de TOUTES les depenses/recettes de la saison
     * (seules celles rattachees au projet sont comptees).
     *
     * @param Depense[] $depenses
     * @param Recette[] $recettes
     * @return array{projet: Projet, recettes: float, depenses: float, net: float, nb_mouvements: int,
     *               a_un_budget: bool, budget_recettes: float, budget_depenses: float, budget_net: float,
     *               ecart_recettes: float, ecart_depenses: float, ecart_net: float}
     */
    public static function pour(Projet $projet, array $depenses, array $recettes): array
    {
        $projetId = (int) $projet->id();
        $totalDepenses = 0.0;
        $totalRecettes = 0.0;
        $nb = 0;

        foreach ($depenses as $depense) {
            if ($depense->projetId() === $projetId) {
                $totalDepenses += $depense->montant();
                $nb++;
            }
        }

        foreach ($recettes as $recette) {
            if ($recette->projetId() === $projetId) {
                $totalRecettes += $recette->montant();
                $nb++;
            }
        }

        $net = $totalRecettes - $totalDepenses;

        return [
            'projet' => $projet,
            'recettes' => round($totalRecettes, 2),
            'depenses' => round($totalDepenses, 2),
            'net' => round($net, 2),
            'nb_mouvements' => $nb,
            'a_un_budget' => $projet->aUnBudget(),
            'budget_recettes' => round($projet->budgetRecettes(), 2),
            'budget_depenses' => round($projet->budgetDepenses(), 2),
            'budget_net' => round($projet->budgetNet(), 2),
            'ecart_recettes' => round($totalRecettes - $projet->budgetRecettes(), 2),
            'ecart_depenses' => round($totalDepenses - $projet->budgetDepenses(), 2),
            'ecart_net' => round($net - $projet->budgetNet(), 2),
        ];
    }

    /**
     * Bilan de chaque projet, dans l'ordre recu.
     *
     * @param Projet[] $projets
     * @param Depense[] $depenses
     * @param Recette[] $recettes
     * @return array<int, array<string, mixed>>
     */
    public static function pourTous(array $projets, array $depenses, array $recettes): array
    {
        return array_map(
            static fn (Projet $projet): array => self::pour($projet, $depenses, $recettes),
            array_values($projets)
        );
    }

    /**
     * Separation pour l'AG : ce qui releve du fonctionnement courant (aucun
     * projet) et ce qui releve des projets. Les deux resultats additionnes
     * redonnent toujours le resultat net de la saison.
     *
     * @param Depense[] $depenses
     * @param Recette[] $recettes
     * @return array{fonctionnement_recettes: float, fonctionnement_depenses: float, fonctionnement_net: float,
     *               projets_recettes: float, projets_depenses: float, projets_net: float}
     */
    public static function repartition(array $depenses, array $recettes): array
    {
        $r = [
            'fonctionnement_recettes' => 0.0,
            'fonctionnement_depenses' => 0.0,
            'projets_recettes' => 0.0,
            'projets_depenses' => 0.0,
        ];

        foreach ($depenses as $depense) {
            $r[$depense->projetId() === null ? 'fonctionnement_depenses' : 'projets_depenses'] += $depense->montant();
        }

        foreach ($recettes as $recette) {
            $r[$recette->projetId() === null ? 'fonctionnement_recettes' : 'projets_recettes'] += $recette->montant();
        }

        $r = array_map(static fn (float $v): float => round($v, 2), $r);
        $r['fonctionnement_net'] = round($r['fonctionnement_recettes'] - $r['fonctionnement_depenses'], 2);
        $r['projets_net'] = round($r['projets_recettes'] - $r['projets_depenses'], 2);

        return $r;
    }

    /**
     * Montant saisi au format francais ou anglais ("1 250,50", "1250.5") ->
     * float positif ; vide ou illisible -> 0.
     */
    public static function montantSaisi(mixed $valeur): float
    {
        $texte = str_replace([' ', "\u{00A0}", "\u{202F}", '€'], '', trim((string) $valeur));
        $texte = str_replace(',', '.', $texte);

        if ($texte === '' || !is_numeric($texte)) {
            return 0.0;
        }

        return max(0.0, round((float) $texte, 2));
    }
}
