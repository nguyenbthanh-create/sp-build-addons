<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Reporting;

use SpCompta\Entity\Client;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Fournisseur;
use SpCompta\Entity\Recette;
use SpCompta\Reporting\MouvementsCsvExportateur;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Reporting/MouvementsCsvExportateur.md
 */
final class MouvementsCsvExportateurTest extends WP_UnitTestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    private function lignes(string $csv): array
    {
        // Retire le BOM UTF-8 avant de reparser, sinon la premiere colonne
        // du premier enregistrement le porterait dans sa valeur.
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lignes = [];

        foreach (preg_split('/\r\n|\n/', trim($csv)) as $ligneBrute) {
            $lignes[] = str_getcsv($ligneBrute, ';');
        }

        return $lignes;
    }

    /** @test */
    public function it_starts_with_a_utf8_bom_and_the_header_row(): void
    {
        // Given no movements at all
        // When the CSV is built
        $csv = MouvementsCsvExportateur::toCsv([], [], [], []);

        // Then it starts with the UTF-8 BOM (Excel needs it for accents) and the header row
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(['Date', 'Type', 'Categorie', 'Tiers', 'Mode de paiement', 'Montant', 'Detail'], $this->lignes($csv)[0]);
    }

    /** @test */
    public function it_shows_a_depense_as_a_negative_amount_with_the_fournisseur_name_resolved(): void
    {
        // Given a depense linked to a known fournisseur
        $depense = new Depense(1, 1, '2026-09-05', 42.5, 7, '60', 'Ballons');
        $fournisseur = new Fournisseur(7, 'Decathlon');

        // When the CSV is built
        $csv = MouvementsCsvExportateur::toCsv([$depense], [], [$fournisseur], []);
        $ligne = $this->lignes($csv)[1];

        // Then the row shows the fournisseur name and a negative amount (French decimal comma)
        $this->assertSame('Depense', $ligne[1]);
        $this->assertSame('Decathlon', $ligne[3]);
        $this->assertSame('-42,50', $ligne[5]);
    }

    /** @test */
    public function it_shows_a_recette_as_a_positive_amount_with_the_client_name_resolved(): void
    {
        // Given a recette linked to a known client
        $recette = new Recette(1, 1, '2026-09-06', 500.0, 'Subvention', 3);
        $client = new Client(3, 'Mairie de Claira');

        // When the CSV is built
        $csv = MouvementsCsvExportateur::toCsv([], [$recette], [], [$client]);
        $ligne = $this->lignes($csv)[1];

        // Then the row shows the client name and a positive amount
        $this->assertSame('Recette', $ligne[1]);
        $this->assertSame('Mairie de Claira', $ligne[3]);
        $this->assertSame('500,00', $ligne[5]);
    }

    /** @test */
    public function it_falls_back_to_the_provenance_when_a_recette_has_no_linked_client(): void
    {
        // Given a recette with a provenance but no linked client
        $recette = new Recette(1, 1, '2026-09-06', 30.0, 'Buvette');

        // When the CSV is built
        $csv = MouvementsCsvExportateur::toCsv([], [$recette], [], []);
        $ligne = $this->lignes($csv)[1];

        // Then the "Tiers" column falls back to the free-text provenance
        $this->assertSame('Buvette', $ligne[3]);
    }

    /** @test */
    public function it_neutralizes_a_leading_formula_character_in_free_text_fields(): void
    {
        // Given a detail field crafted to look like a spreadsheet formula
        $depense = new Depense(1, 1, '2026-09-05', 10.0, null, '', '=CMD|/c calc');

        // When the CSV is built
        $csv = MouvementsCsvExportateur::toCsv([$depense], [], [], []);
        $ligne = $this->lignes($csv)[1];

        // Then the field is prefixed with an apostrophe, defusing the formula for Excel
        $this->assertSame("'=CMD|/c calc", $ligne[6]);
    }

    /** @test */
    public function it_sorts_all_movements_by_date_regardless_of_type(): void
    {
        // Given a recette and a depense in reverse chronological order
        $recette = new Recette(1, 1, '2026-09-10', 100.0);
        $depense = new Depense(1, 1, '2026-09-01', 50.0);

        // When the CSV is built
        $csv = MouvementsCsvExportateur::toCsv([$depense], [$recette], [], []);
        $lignes = $this->lignes($csv);

        // Then the earliest movement comes first
        $this->assertSame('01/09/2026', $lignes[1][0]);
        $this->assertSame('10/09/2026', $lignes[2][0]);
    }
}
