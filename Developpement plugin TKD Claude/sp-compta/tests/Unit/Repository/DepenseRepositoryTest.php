<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Depense;
use SpCompta\Repository\DepenseRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/DepenseRepository.md
 */
final class DepenseRepositoryTest extends WP_UnitTestCase
{
    private DepenseRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new DepenseRepository($database->tableDepense());
    }

    /** @test */
    public function it_assigns_an_id_when_a_new_depense_is_saved(): void
    {
        // Given a depense that does not exist yet
        $depense = new Depense(null, 1, '2026-09-07', 42.50);

        // When it is saved
        $saved = $this->repository->save($depense);

        // Then it now has a positive id
        $this->assertGreaterThan(0, $saved->id());
    }

    /** @test */
    public function it_finds_a_previously_saved_depense_by_id(): void
    {
        // Given a depense saved in the database
        $saved = $this->repository->save(new Depense(null, 1, '2026-09-07', 42.50, null, 'materiel'));

        // When it is looked up by its id
        $found = $this->repository->find((int) $saved->id());

        // Then the same data comes back
        $this->assertNotNull($found);
        $this->assertSame(42.50, $found->montant());
        $this->assertSame('materiel', $found->categorie());
    }

    /** @test */
    public function it_returns_null_when_the_depense_does_not_exist(): void
    {
        // Given no depense with id 999999
        // When it is looked up
        $found = $this->repository->find(999999);

        // Then nothing is returned
        $this->assertNull($found);
    }

    /** @test */
    public function it_updates_an_existing_depense_instead_of_duplicating_it(): void
    {
        // Given a saved depense
        $saved = $this->repository->save(new Depense(null, 1, '2026-09-07', 10.0));

        // When it is saved again with the same id but a new montant
        $updated = new Depense((int) $saved->id(), 1, '2026-09-07', 25.0);
        $this->repository->save($updated);

        // Then only the montant has changed, no duplicate row was created
        $found = $this->repository->find((int) $saved->id());
        $this->assertSame(25.0, $found->montant());
        $this->assertCount(1, $this->repository->forExercice(1));
    }

    /** @test */
    public function it_removes_a_depense_on_delete(): void
    {
        // Given a saved depense
        $saved = $this->repository->save(new Depense(null, 1, '2026-09-07', 10.0));

        // When it is deleted
        $result = $this->repository->delete((int) $saved->id());

        // Then it can no longer be found
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $saved->id()));
    }

    /** @test */
    public function it_only_lists_depenses_of_the_requested_exercice(): void
    {
        // Given depenses spread across two exercices
        $this->repository->save(new Depense(null, 1, '2026-09-01', 10.0));
        $this->repository->save(new Depense(null, 1, '2026-09-05', 20.0));
        $this->repository->save(new Depense(null, 2, '2026-09-03', 30.0));

        // When depenses of exercice 1 are requested
        $depenses = $this->repository->forExercice(1);

        // Then only its two depenses come back
        $this->assertCount(2, $depenses);
    }
}
