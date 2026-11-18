<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Accounting;

use SpCompta\Accounting\ProjetBilan;
use SpCompta\Entity\BudgetLigne;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Projet;
use SpCompta\Entity\Recette;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Accounting/ProjetBilan.md (bilan par categorie, 18/11/2026)
 */
final class ProjetBilanParCategorieTest extends WP_UnitTestCase
{
    private function depense(float $montant, string $categorie, ?int $projetId = 7): Depense
    {
        return new Depense(null, 1, '2026-12-05', $montant, null, $categorie, null, '', '', '', $projetId);
    }

    private function recette(float $montant, string $categorie, ?int $projetId = 7): Recette
    {
        return new Recette(null, 1, '2026-12-05', $montant, '', null, $categorie, null, '', '', '', $projetId);
    }

    private function ligne(string $sens, string $categorie, string $libelle, float $montant, int $projetId = 7): BudgetLigne
    {
        return new BudgetLigne(null, $projetId, $sens, $categorie, $libelle, $montant);
    }

    /** @test */
    public function it_compares_each_budget_category_with_the_movements_of_that_category(): void
    {
        // Given a projet planning 300 EUR of purchases (60) and 200 EUR of rentals (61)
        $projet = new Projet(7, 1, 'Noel');
        $lignes = [
            $this->ligne('depense', '60', 'Cadeaux', 250.0),
            $this->ligne('depense', '60', 'Gouter', 50.0),
            $this->ligne('depense', '61', 'Salle', 200.0),
        ];

        // When 280 EUR of purchases were spent, plus an unrelated movement of another projet
        $bilan = ProjetBilan::parCategorie($projet, $lignes, [$this->depense(200.0, '60'), $this->depense(80.0, '60'), $this->depense(999.0, '60', 8)], []);

        // Then category 60 shows 300 planned / 280 spent, and 61 shows 200 planned / 0 spent
        $this->assertCount(2, $bilan);
        $this->assertSame('60', $bilan[0]['categorie']);
        $this->assertSame(['Cadeaux', 'Gouter'], $bilan[0]['libelles']);
        $this->assertSame(300.0, $bilan[0]['prevu']);
        $this->assertSame(280.0, $bilan[0]['realise']);
        $this->assertSame(-20.0, $bilan[0]['ecart']);
        $this->assertFalse($bilan[0]['hors_budget']);
        $this->assertSame(0.0, $bilan[1]['realise']);
    }

    /** @test */
    public function it_shows_spending_outside_the_budget(): void
    {
        // Given a projet with only a purchases line
        $projet = new Projet(7, 1, 'Noel');
        $lignes = [$this->ligne('depense', '60', 'Cadeaux', 100.0)];

        // When the projet also has a travel expense (62) and an uncategorised one
        $bilan = ProjetBilan::parCategorie($projet, $lignes, [$this->depense(40.0, '62'), $this->depense(5.0, '')], []);

        // Then both appear "hors budget", with nothing planned
        $horsBudget = array_values(array_filter($bilan, static fn (array $c): bool => $c['hors_budget']));
        $this->assertCount(2, $horsBudget);
        $this->assertSame('Non catégorisé', $horsBudget[0]['libelle_categorie']);
        $this->assertSame(0.0, $horsBudget[1]['prevu']);
        $this->assertSame(40.0, $horsBudget[1]['realise']);
    }

    /** @test */
    public function it_lists_depenses_before_recettes(): void
    {
        // Given lines and movements on both sides
        $projet = new Projet(7, 1, 'Gala');
        $lignes = [$this->ligne('recette', '70', 'Buvette', 500.0), $this->ligne('depense', '61', 'Salle', 300.0)];

        // When the bilan is computed
        $bilan = ProjetBilan::parCategorie($projet, $lignes, [], [$this->recette(320.0, '70')]);

        // Then depenses come first, and the recette gap is realise - prevu
        $this->assertSame(['depense', 'recette'], array_column($bilan, 'sens'));
        $this->assertSame(-180.0, $bilan[1]['ecart']);
    }

    /** @test */
    public function it_sums_the_planned_totals_of_the_lines(): void
    {
        // Given lines on both sides
        $lignes = [$this->ligne('depense', '60', 'a', 100.5), $this->ligne('depense', '61', 'b', 99.5), $this->ligne('recette', '70', 'c', 40.0)];

        // When the totals are computed
        $totaux = ProjetBilan::totauxLignes($lignes);

        // Then they feed budget_depenses / budget_recettes
        $this->assertSame(['depenses' => 200.0, 'recettes' => 40.0], $totaux);
    }
}
