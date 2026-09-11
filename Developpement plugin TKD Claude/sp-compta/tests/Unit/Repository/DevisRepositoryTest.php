<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Devis;
use SpCompta\Entity\LigneDocument;
use SpCompta\Numbering\DocumentNumeroGenerator;
use SpCompta\Numbering\SequenceGenerator;
use SpCompta\Repository\DevisRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/DevisRepository.md
 */
final class DevisRepositoryTest extends WP_UnitTestCase
{
    private DevisRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new DevisRepository(
            $database->tableDevis(),
            $database->tableDevisLigne(),
            new DocumentNumeroGenerator(new SequenceGenerator($database->tableSequence()), 'D')
        );
    }

    /** @test */
    public function it_assigns_a_numero_and_persists_lignes_on_first_save(): void
    {
        // Given a devis with two lignes and no numero
        $devis = new Devis(null, 1, 1, '2026-09-07', '2026-10-07', [
            new LigneDocument(null, 'Cours decouverte x10', 10, 5.0),
            new LigneDocument(null, 'Kimono', 1, 35.0),
        ]);

        // When it is saved for the first time
        $saved = $this->repository->save($devis);

        // Then it gets a D-prefixed numero, its lignes are persisted, and its total is the sum of lignes
        $this->assertSame('D2026-0001', $saved->numero());
        $this->assertCount(2, $saved->lignes());
        $this->assertSame(85.0, $saved->total());
    }

    /** @test */
    public function it_gives_distinct_numeros_to_two_devis_the_same_year(): void
    {
        // Given a first devis already saved in 2026
        $this->repository->save(new Devis(null, 1, 1, '2026-09-07', '2026-10-07'));

        // When a second devis is saved the same year
        $second = $this->repository->save(new Devis(null, 1, 1, '2026-09-08', '2026-10-08'));

        // Then its sequence is incremented compared to the first
        $this->assertSame('D2026-0002', $second->numero());
    }

    /** @test */
    public function it_finds_a_devis_with_its_lignes_in_order(): void
    {
        // Given a devis saved with its lignes
        $saved = $this->repository->save(new Devis(null, 1, 1, '2026-09-07', '2026-10-07', [
            new LigneDocument(null, 'Ligne A', 1, 10.0),
            new LigneDocument(null, 'Ligne B', 2, 20.0),
        ]));

        // When it is looked up by its id
        $found = $this->repository->find((int) $saved->id());

        // Then the same lignes come back, in their original order
        $this->assertNotNull($found);
        $this->assertSame('Ligne A', $found->lignes()[0]->designation());
        $this->assertSame('Ligne B', $found->lignes()[1]->designation());
    }

    /** @test */
    public function it_replaces_lignes_instead_of_accumulating_them_on_update(): void
    {
        // Given a devis saved with one ligne
        $saved = $this->repository->save(
            new Devis(null, 1, 1, '2026-09-07', '2026-10-07', [new LigneDocument(null, 'Ancienne ligne', 1, 10.0)])
        );

        // When it is saved again with a different ligne
        $updated = new Devis(
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

    /** @test */
    public function it_deletes_the_devis_and_all_its_lignes(): void
    {
        // Given a devis saved with lignes
        $saved = $this->repository->save(new Devis(null, 1, 1, '2026-09-07', '2026-10-07', [
            new LigneDocument(null, 'Ligne A', 1, 10.0),
        ]));

        // When it is deleted
        $result = $this->repository->delete((int) $saved->id());

        // Then the devis and its lignes are gone
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $saved->id()));
    }
}
