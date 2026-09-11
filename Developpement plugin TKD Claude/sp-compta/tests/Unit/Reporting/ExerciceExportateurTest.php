<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Reporting;

use SpCompta\Entity\Client;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Exercice;
use SpCompta\Entity\Fournisseur;
use SpCompta\Entity\Recette;
use SpCompta\Reporting\ExerciceExportateur;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Reporting/ExerciceExportateur.md
 */
final class ExerciceExportateurTest extends WP_UnitTestCase
{
    /** @test */
    public function it_resolves_the_fournisseur_name_on_each_depense(): void
    {
        // Given a depense linked to a known fournisseur
        $exercice = new Exercice(1, '2026-09-01', '2027-08-31', 100.0, true);
        $fournisseur = new Fournisseur(7, 'Decathlon');
        $depense = new Depense(1, 1, '2026-09-05', 42.0, 7, '60', 'Ballons');

        // When the exercice is exported
        $donnees = ExerciceExportateur::toArray($exercice, [$depense], [], [], [], [], [], [$fournisseur]);

        // Then the fournisseur name is resolved alongside its id
        $this->assertSame(7, $donnees['depenses'][0]['fournisseur_id']);
        $this->assertSame('Decathlon', $donnees['depenses'][0]['fournisseur_nom']);
    }

    /** @test */
    public function it_leaves_the_fournisseur_name_empty_when_the_depense_has_none(): void
    {
        // Given a depense without a fournisseur
        $exercice = new Exercice(1, '2026-09-01', '2027-08-31');
        $depense = new Depense(1, 1, '2026-09-05', 42.0);

        // When the exercice is exported
        $donnees = ExerciceExportateur::toArray($exercice, [$depense], [], [], [], [], [], []);

        // Then no name is guessed
        $this->assertSame('', $donnees['depenses'][0]['fournisseur_nom']);
    }

    /** @test */
    public function it_resolves_the_client_name_on_each_recette(): void
    {
        // Given a recette linked to a known client
        $exercice = new Exercice(1, '2026-09-01', '2027-08-31');
        $client = new Client(3, 'Mairie de Claira');
        $recette = new Recette(1, 1, '2026-09-05', 500.0, 'Subvention', 3);

        // When the exercice is exported
        $donnees = ExerciceExportateur::toArray($exercice, [], [$recette], [], [], [], [$client], []);

        // Then the client name is resolved alongside its id
        $this->assertSame(3, $donnees['recettes'][0]['client_id']);
        $this->assertSame('Mairie de Claira', $donnees['recettes'][0]['client_nom']);
    }

    /** @test */
    public function it_includes_the_exercice_metadata(): void
    {
        // Given an exercice
        $exercice = new Exercice(5, '2026-09-01', '2027-08-31', 250.5, true);

        // When it is exported
        $donnees = ExerciceExportateur::toArray($exercice, [], [], [], [], [], [], []);

        // Then its own fields are present as-is
        $this->assertSame(5, $donnees['exercice']['id']);
        $this->assertSame('2026-09-01', $donnees['exercice']['date_debut']);
        $this->assertSame(250.5, $donnees['exercice']['solde_initial']);
        $this->assertTrue($donnees['exercice']['actif']);
    }
}
