<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Integration;

use SpCompta\Integration\ProjetsApi;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Integration/ProjetsApi.md (validation des lignes, sans base)
 */
final class ProjetsApiValidationTest extends WP_UnitTestCase
{
    /** @test */
    public function it_accepts_french_amounts_and_skips_empty_lines(): void
    {
        // Given two filled lines and an empty one
        $recues = [
            ['sens' => 'depense', 'categorie' => '61', 'libelle' => '  Location   de la salle ', 'montant' => '1 250,50'],
            ['sens' => 'depense', 'categorie' => '', 'libelle' => '', 'montant' => ''],
            ['sens' => 'recette', 'categorie' => '70', 'libelle' => '<b>Buvette</b>', 'montant' => '300'],
        ];

        // When they are validated
        $lignes = ProjetsApi::lignesValidees(7, $recues);

        // Then the empty line is dropped, text is cleaned and amounts are read
        $this->assertCount(2, $lignes);
        $this->assertSame('Location de la salle', $lignes[0]->libelle());
        $this->assertSame(1250.5, $lignes[0]->montant());
        $this->assertSame('Buvette', $lignes[1]->libelle());
        $this->assertSame(7, $lignes[1]->projetId());
    }

    /** @test */
    public function it_refuses_a_category_of_the_wrong_side(): void
    {
        // Given a recette line classed in a depense category (60)
        $recues = [['sens' => 'recette', 'categorie' => '60', 'libelle' => 'x', 'montant' => '10']];

        // Then validation fails with a message naming the line
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ligne 1');

        // When the lines are validated
        ProjetsApi::lignesValidees(7, $recues);
    }

    /** @test */
    public function it_refuses_an_unknown_side(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProjetsApi::lignesValidees(7, [['sens' => 'virement', 'categorie' => '60', 'libelle' => 'x', 'montant' => '10']]);
    }

    /** @test */
    public function it_refuses_too_many_lines(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProjetsApi::lignesValidees(7, array_fill(0, ProjetsApi::MAX_LIGNES + 1, ['sens' => 'depense', 'categorie' => '60', 'montant' => '1']));
    }
}
