<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Billing;

use RuntimeException;
use SpCompta\Billing\DevisToFactureConverter;
use SpCompta\Database;
use SpCompta\Entity\Devis;
use SpCompta\Entity\LigneDocument;
use SpCompta\Numbering\DocumentNumeroGenerator;
use SpCompta\Numbering\SequenceGenerator;
use SpCompta\Repository\DevisRepository;
use SpCompta\Repository\FactureRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Billing/DevisToFactureConverter.md
 */
final class DevisToFactureConverterTest extends WP_UnitTestCase
{
    private DevisToFactureConverter $converter;

    private DevisRepository $devisRepository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $sequences = new SequenceGenerator($database->tableSequence());

        $this->devisRepository = new DevisRepository(
            $database->tableDevis(),
            $database->tableDevisLigne(),
            new DocumentNumeroGenerator($sequences, 'D')
        );

        $factureRepository = new FactureRepository(
            $database->tableFacture(),
            $database->tableFactureLigne(),
            new DocumentNumeroGenerator($sequences, 'F')
        );

        $this->converter = new DevisToFactureConverter($this->devisRepository, $factureRepository);
    }

    /** @test */
    public function it_creates_a_facture_from_an_accepted_devis(): void
    {
        // Given an accepted devis with lignes
        $devis = $this->devisRepository->save(new Devis(
            null,
            1,
            1,
            '2026-09-07',
            '2026-10-07',
            [new LigneDocument(null, 'Cours decouverte x10', 10, 5.0)],
            Devis::STATUT_ACCEPTE
        ));

        // When it is converted to a facture
        $facture = $this->converter->convert($devis, '2026-09-10', '2026-10-10');

        // Then the facture has the same lignes and a link back to the originating devis
        $this->assertCount(1, $facture->lignes());
        $this->assertSame($devis->id(), $facture->devisId());

        // And the devis is now marked as converted
        $updatedDevis = $this->devisRepository->find((int) $devis->id());
        $this->assertSame(Devis::STATUT_FACTURE, $updatedDevis->statut());
    }

    /** @test */
    public function it_refuses_to_convert_a_devis_that_is_not_accepted(): void
    {
        // Given a devis still in brouillon
        $devis = $this->devisRepository->save(new Devis(null, 1, 1, '2026-09-07', '2026-10-07'));

        // When converting it is attempted
        // Then an exception is thrown
        $this->expectException(RuntimeException::class);
        $this->converter->convert($devis, '2026-09-10', '2026-10-10');
    }
}
