<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Accounting;

use SpCompta\Accounting\ProjetBilan;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Projet;
use SpCompta\Entity\Recette;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Accounting/ProjetBilan.md
 */
final class ProjetBilanTest extends WP_UnitTestCase
{
    private function depense(float $montant, ?int $projetId): Depense
    {
        return new Depense(null, 1, '2026-12-05', $montant, null, '', null, '', '', '', $projetId);
    }

    private function recette(float $montant, ?int $projetId): Recette
    {
        return new Recette(null, 1, '2026-12-05', $montant, '', null, '', null, '', '', '', $projetId);
    }

    /** @test */
    public function it_sums_only_the_movements_attached_to_the_projet(): void
    {
        // Given a projet (id 7) and movements of the season, some attached to it
        $projet = new Projet(7, 1, 'Noel');
        $depenses = [$this->depense(200.0, 7), $this->depense(50.0, 7), $this->depense(999.0, null), $this->depense(30.0, 8)];
        $recettes = [$this->recette(80.0, 7), $this->recette(500.0, null)];

        // When its bilan is computed
        $bilan = ProjetBilan::pour($projet, $depenses, $recettes);

        // Then only its own movements count, and the net is recettes - depenses
        $this->assertSame(250.0, $bilan['depenses']);
        $this->assertSame(80.0, $bilan['recettes']);
        $this->assertSame(-170.0, $bilan['net']);
        $this->assertSame(3, $bilan['nb_mouvements']);
    }

    /** @test */
    public function it_compares_realised_with_planned_amounts(): void
    {
        // Given a projet planned at 300 EUR of depenses and 100 EUR of recettes (net -200)
        $projet = new Projet(7, 1, 'Noel', 'evenement', 300.0, 100.0);

        // When 250 EUR were spent and 80 EUR received
        $bilan = ProjetBilan::pour($projet, [$this->depense(250.0, 7)], [$this->recette(80.0, 7)]);

        // Then the gaps are realised - planned, and the net gap is +30 (cheaper than planned)
        $this->assertTrue($bilan['a_un_budget']);
        $this->assertSame(-200.0, $bilan['budget_net']);
        $this->assertSame(-50.0, $bilan['ecart_depenses']);
        $this->assertSame(-20.0, $bilan['ecart_recettes']);
        $this->assertSame(30.0, $bilan['ecart_net']);
    }

    /** @test */
    public function it_reports_no_budget_when_none_was_entered(): void
    {
        // Given a projet without any planned amount
        $projet = new Projet(7, 1, 'Noel');

        // When its bilan is computed
        $bilan = ProjetBilan::pour($projet, [], []);

        // Then the bilan says there is no planned budget to compare with
        $this->assertFalse($bilan['a_un_budget']);
    }

    /** @test */
    public function it_splits_the_season_between_regular_operations_and_projets(): void
    {
        // Given movements with and without projet
        $depenses = [$this->depense(1000.0, null), $this->depense(300.0, 7)];
        $recettes = [$this->recette(1500.0, null), $this->recette(100.0, 7)];

        // When the season is split
        $r = ProjetBilan::repartition($depenses, $recettes);

        // Then each part has its own result, and both add up to the season result
        $this->assertSame(500.0, $r['fonctionnement_net']);
        $this->assertSame(-200.0, $r['projets_net']);
        $this->assertSame(300.0, $r['fonctionnement_net'] + $r['projets_net']);
    }

    /** @test */
    public function it_reads_french_and_english_amounts(): void
    {
        // Given amounts typed in several formats
        // When they are read
        // Then they all become positive floats, and garbage becomes 0
        $this->assertSame(1250.5, ProjetBilan::montantSaisi('1 250,50'));
        $this->assertSame(1250.5, ProjetBilan::montantSaisi('1250.5'));
        $this->assertSame(0.0, ProjetBilan::montantSaisi(''));
        $this->assertSame(0.0, ProjetBilan::montantSaisi('abc'));
        $this->assertSame(0.0, ProjetBilan::montantSaisi('-40'));
    }
}
