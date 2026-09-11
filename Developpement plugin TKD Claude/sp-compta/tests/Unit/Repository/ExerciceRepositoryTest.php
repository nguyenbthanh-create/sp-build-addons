<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Exercice;
use SpCompta\Repository\ExerciceRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/ExerciceRepository.md
 */
final class ExerciceRepositoryTest extends WP_UnitTestCase
{
    private ExerciceRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new ExerciceRepository($database->tableExercice());
    }

    /** @test */
    public function it_assigns_an_id_when_a_new_exercice_is_saved(): void
    {
        // Given an exercice that does not exist yet
        $exercice = new Exercice(null, '2026-09-01', '2027-08-31');

        // When it is saved
        $saved = $this->repository->save($exercice);

        // Then it now has a positive id
        $this->assertGreaterThan(0, $saved->id());
    }

    /** @test */
    public function it_returns_null_when_no_exercice_is_active(): void
    {
        // Given no exercice has been created
        // When the active exercice is requested
        $active = $this->repository->active();

        // Then nothing is returned
        $this->assertNull($active);
    }

    /** @test */
    public function it_activates_an_exercice_among_several_inactive_ones(): void
    {
        // Given two saved exercices, none active
        $first = $this->repository->save(new Exercice(null, '2025-09-01', '2026-08-31'));
        $this->repository->save(new Exercice(null, '2026-09-01', '2027-08-31'));

        // When the first one is activated
        $this->repository->activate((int) $first->id());

        // Then it is the one reported as active
        $active = $this->repository->active();
        $this->assertNotNull($active);
        $this->assertSame($first->id(), $active->id());
    }

    /** @test */
    public function it_switches_the_active_exercice(): void
    {
        // Given a first exercice already active
        $first = $this->repository->save(new Exercice(null, '2025-09-01', '2026-08-31'));
        $second = $this->repository->save(new Exercice(null, '2026-09-01', '2027-08-31'));
        $this->repository->activate((int) $first->id());

        // When the second one is activated
        $this->repository->activate((int) $second->id());

        // Then the second becomes active and the first is no longer active
        $active = $this->repository->active();
        $this->assertSame($second->id(), $active->id());
    }

    /** @test */
    public function it_deletes_an_exercice(): void
    {
        // Given a saved exercice
        $saved = $this->repository->save(new Exercice(null, '2026-09-01', '2027-08-31'));

        // When it is deleted
        $result = $this->repository->delete((int) $saved->id());

        // Then it can no longer be found
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $saved->id()));
    }
}
