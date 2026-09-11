<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Facture;
use SpCompta\Entity\LigneDocument;
use SpCompta\Numbering\DocumentNumeroGenerator;
use SpCompta\Numbering\SequenceGenerator;
use SpCompta\Repository\FactureRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/FactureRepository.md
 */
final class FactureRepositoryTest extends WP_UnitTestCase
{
    private FactureRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new FactureRepository(
            $database->tableFacture(),
            $database->tableFactureLigne(),
            new DocumentNumeroGenerator(new SequenceGenerator($database->tableSequence()), 'F')
        );
    }

    /** @test */
    public function it_assigns_a_numero_and_persists_lignes_on_first_save(): void
    {
        // Given a facture with two lignes and no numero
        $facture = new Facture(null, 1, 1, '2026-09-07', '2026-10-07', [
            new LigneDocument(null, 'Cours decouverte x10', 10, 5.0),
            new LigneDocument(null, 'Kimono', 1, 35.0),
        ]);

        // When it is saved for the first time
        $saved = $this->repository->save($facture);

        // Then it gets an F-prefixed numero, its lignes are persisted, and its total is the sum of lignes
        $this->assertSame('F2026-0001', $saved->numero());
        $this->assertCount(2, $saved->lignes());
        $this->assertSame(85.0, $saved->total());
    }

    /** @test */
    public function it_gives_distinct_numeros_to_two_factures_the_same_year(): void
    {
        // Given a first facture already saved in 2026
        $this->repository->save(new Facture(null, 1, 1, '2026-09-07', '2026-10-07'));

        // When a second facture is saved the same year
        $second = $this->repository->save(new Facture(null, 1, 1, '2026-09-08', '2026-10-08'));

        // Then its sequence is incremented compared to the first
        $this->assertSame('F2026-0002', $second->numero());
    }

    /** @test */
    public function it_keeps_track_of_the_originating_devis(): void
    {
        // Given a facture created from an accepted devis
        $facture = new Facture(
            null,
            1,
            1,
            '2026-09-07',
            '2026-10-07',
            [],
            Facture::STATUT_EMISE,
            '',
            42
        );

        // When it is saved
        $saved = $this->repository->save($facture);

        // Then the devis link is persisted
        $found = $this->repository->find((int) $saved->id());
        $this->assertSame(42, $found->devisId());
    }

    /** @test */
    public function it_replaces_lignes_instead_of_accumulating_them_on_update(): void
    {
        // Given a facture saved with one ligne
        $saved = $this->repository->save(
            new Facture(null, 1, 1, '2026-09-07', '2026-10-07', [new LigneDocument(null, 'Ancienne ligne', 1, 10.0)])
        );

        // When it is saved again with a different ligne
        $updated = new Facture(
            (int) $saved->id(),
            1,
            1,
            '2026-09-07',
            '2026-10-07',
            [new LigneDocument(null, 'Nouvelle ligne', 1, 20.0)],
            $saved->statut(),
            $saved->numero()
        );
        $this->repository->save($updated);

        // Then the old ligne is gone and only the new one remains
        $found = $this->repository->find((int) $saved->id());
        $this->assertCount(1, $found->lignes());
        $this->assertSame('Nouvelle ligne', $found->lignes()[0]->designation());
    }
}
