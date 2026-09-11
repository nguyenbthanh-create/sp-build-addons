<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Recette;
use SpCompta\Repository\RecetteRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/RecetteRepository.md
 */
final class RecetteRepositoryTest extends WP_UnitTestCase
{
    private RecetteRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new RecetteRepository($database->tableRecette());
    }

    /** @test */
    public function it_assigns_an_id_when_a_new_recette_is_saved(): void
    {
        // Given a recette that does not exist yet
        $recette = new Recette(null, 1, '2026-09-07', 100.0, 'Cotisation');

        // When it is saved
        $saved = $this->repository->save($recette);

        // Then it now has a positive id
        $this->assertGreaterThan(0, $saved->id());
    }

    /** @test */
    public function it_finds_a_previously_saved_recette_by_id(): void
    {
        // Given a recette saved in the database
        $saved = $this->repository->save(new Recette(null, 1, '2026-09-07', 100.0, 'Cotisation'));

        // When it is looked up by its id
        $found = $this->repository->find((int) $saved->id());

        // Then the same data comes back
        $this->assertNotNull($found);
        $this->assertSame(100.0, $found->montant());
        $this->assertSame('Cotisation', $found->provenance());
    }

    /** @test */
    public function it_returns_null_when_the_recette_does_not_exist(): void
    {
        // Given no recette with id 999999
        // When it is looked up
        $found = $this->repository->find(999999);

        // Then nothing is returned
        $this->assertNull($found);
    }

    /** @test */
    public function it_updates_an_existing_recette_instead_of_duplicating_it(): void
    {
        // Given a saved recette
        $saved = $this->repository->save(new Recette(null, 1, '2026-09-07', 50.0));

        // When it is saved again with the same id but a new montant
        $updated = new Recette((int) $saved->id(), 1, '2026-09-07', 75.0);
        $this->repository->save($updated);

        // Then only the montant has changed, no duplicate row was created
        $found = $this->repository->find((int) $saved->id());
        $this->assertSame(75.0, $found->montant());
        $this->assertCount(1, $this->repository->forExercice(1));
    }

    /** @test */
    public function it_removes_a_recette_on_delete(): void
    {
        // Given a saved recette
        $saved = $this->repository->save(new Recette(null, 1, '2026-09-07', 50.0));

        // When it is deleted
        $result = $this->repository->delete((int) $saved->id());

        // Then it can no longer be found
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $saved->id()));
    }

    /** @test */
    public function it_only_lists_recettes_of_the_requested_exercice(): void
    {
        // Given recettes spread across two exercices
        $this->repository->save(new Recette(null, 1, '2026-09-01', 10.0));
        $this->repository->save(new Recette(null, 1, '2026-09-05', 20.0));
        $this->repository->save(new Recette(null, 2, '2026-09-03', 30.0));

        // When recettes of exercice 1 are requested
        $recettes = $this->repository->forExercice(1);

        // Then only its two recettes come back
        $this->assertCount(2, $recettes);
    }
}
