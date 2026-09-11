<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Accounting\Categories;
use SpCompta\Admin\SoldeScreen;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Recette;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/SoldeScreen.md
 */
final class SoldeScreenTest extends WP_UnitTestCase
{
    /** @test */
    public function it_computes_a_positive_solde(): void
    {
        // Given an initial balance of 100, 50 of recettes, 30 of depenses
        // When solde is called
        $result = SoldeScreen::solde(100.0, 50.0, 30.0);

        // Then the result is 120
        $this->assertSame(120.0, $result);
    }

    /** @test */
    public function it_totals_an_empty_list_as_zero(): void
    {
        // Given no lignes at all
        // When totalMontant is called
        $result = SoldeScreen::totalMontant([]);

        // Then the result is 0.0
        $this->assertSame(0.0, $result);
    }

    /** @test */
    public function it_groups_amounts_by_categorie_then_by_sous_categorie(): void
    {
        // Given three depenses: two under the same code categorie but different sous-categories
        $lignes = [
            new Depense(id: 1, exerciceId: 1, date: '2026-09-01', montant: 10.0, categorie: '60', sousCategorie: 'fourniture_bureau'),
            new Depense(id: 2, exerciceId: 1, date: '2026-09-02', montant: 20.0, categorie: '60', sousCategorie: 'petit_equipement'),
            new Depense(id: 3, exerciceId: 1, date: '2026-09-03', montant: 5.0, categorie: '61', sousCategorie: 'locations'),
        ];

        // When repartitionHierarchique is called
        $repartition = SoldeScreen::repartitionHierarchique($lignes, Categories::DEPENSE);

        // Then the code's total sums all three lines, and each sous-categorie has its own distinct total
        $this->assertSame(30.0, $repartition['60']['total']);
        $this->assertSame(10.0, $repartition['60']['sous']['fourniture_bureau']['total']);
        $this->assertSame(20.0, $repartition['60']['sous']['petit_equipement']['total']);
        $this->assertSame(5.0, $repartition['61']['total']);
    }

    /** @test */
    public function it_merges_depenses_and_recettes_sorted_by_date_descending(): void
    {
        // Given one depense and one more recent recette
        $depenses = [new Depense(1, 1, '2026-09-01', 20.0, null, 'deplacements')];
        $recettes = [new Recette(1, 1, '2026-09-05', 100.0, 'Cotisation')];

        // When mouvements is called
        $mouvements = SoldeScreen::mouvements($depenses, $recettes);

        // Then the most recent movement comes first, and depense montant is negative
        $this->assertCount(2, $mouvements);
        $this->assertSame('2026-09-05', $mouvements[0]['date']);
        $this->assertSame('Recette', $mouvements[0]['type']);
        $this->assertSame(100.0, $mouvements[0]['montant']);
        $this->assertSame('Depense', $mouvements[1]['type']);
        $this->assertSame(-20.0, $mouvements[1]['montant']);
    }

    /** @test */
    public function it_keeps_only_movements_of_the_requested_type(): void
    {
        // Given a mix of depenses and recettes
        $depenses = [new Depense(1, 1, '2026-09-01', 20.0)];
        $recettes = [new Recette(2, 1, '2026-09-02', 100.0, 'Cotisation')];
        $mouvements = SoldeScreen::mouvements($depenses, $recettes);

        // When filtrerParType is called with "Recette"
        $filtres = SoldeScreen::filtrerParType($mouvements, 'Recette');

        // Then only the recette remains
        $this->assertCount(1, $filtres);
        $this->assertSame('Recette', $filtres[0]['type']);
    }

    /** @test */
    public function it_keeps_every_movement_when_no_type_filter_is_given(): void
    {
        // Given a mix of depenses and recettes
        $depenses = [new Depense(1, 1, '2026-09-01', 20.0)];
        $recettes = [new Recette(2, 1, '2026-09-02', 100.0, 'Cotisation')];
        $mouvements = SoldeScreen::mouvements($depenses, $recettes);

        // When filtrerParType is called with an empty type
        $filtres = SoldeScreen::filtrerParType($mouvements, '');

        // Then both movements remain
        $this->assertCount(2, $filtres);
    }
}
