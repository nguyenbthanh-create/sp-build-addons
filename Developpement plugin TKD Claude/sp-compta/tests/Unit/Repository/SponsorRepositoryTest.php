<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Sponsor;
use SpCompta\Repository\SponsorRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/SponsorRepository.md
 */
final class SponsorRepositoryTest extends WP_UnitTestCase
{
    private SponsorRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new SponsorRepository($database->tableSponsor());
    }

    /** @test */
    public function it_assigns_an_id_when_a_new_sponsor_is_saved(): void
    {
        // Given a sponsor that does not exist yet
        $sponsor = new Sponsor(null, 1, 'Boulangerie du village', 200.0, '2026-09-07');

        // When it is saved
        $saved = $this->repository->save($sponsor);

        // Then it now has a positive id
        $this->assertGreaterThan(0, $saved->id());
    }

    /** @test */
    public function it_finds_a_previously_saved_sponsor_by_id(): void
    {
        // Given a sponsor saved in the database
        $saved = $this->repository->save(
            new Sponsor(null, 1, 'Boulangerie du village', 200.0, '2026-09-07', 'numeraire', true)
        );

        // When it is looked up by its id
        $found = $this->repository->find((int) $saved->id());

        // Then the same data comes back, including the boolean flag
        $this->assertNotNull($found);
        $this->assertSame('Boulangerie du village', $found->nom());
        $this->assertTrue($found->contratSigne());
    }

    /** @test */
    public function it_returns_null_when_the_sponsor_does_not_exist(): void
    {
        // Given no sponsor with id 999999
        // When it is looked up
        $found = $this->repository->find(999999);

        // Then nothing is returned
        $this->assertNull($found);
    }

    /** @test */
    public function it_updates_an_existing_sponsor_instead_of_duplicating_it(): void
    {
        // Given a saved sponsor
        $saved = $this->repository->save(new Sponsor(null, 1, 'Ancien nom', 100.0, '2026-09-07'));

        // When it is saved again with the same id but a new montant
        $updated = new Sponsor((int) $saved->id(), 1, 'Ancien nom', 300.0, '2026-09-07');
        $this->repository->save($updated);

        // Then only the montant has changed, no duplicate row was created
        $found = $this->repository->find((int) $saved->id());
        $this->assertSame(300.0, $found->montant());
        $this->assertCount(1, $this->repository->forExercice(1));
    }

    /** @test */
    public function it_removes_a_sponsor_on_delete(): void
    {
        // Given a saved sponsor
        $saved = $this->repository->save(new Sponsor(null, 1, 'A supprimer', 100.0, '2026-09-07'));

        // When it is deleted
        $result = $this->repository->delete((int) $saved->id());

        // Then it can no longer be found
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $saved->id()));
    }

    /** @test */
    public function it_only_lists_sponsors_of_the_requested_exercice(): void
    {
        // Given sponsors spread across two exercices
        $this->repository->save(new Sponsor(null, 1, 'Sponsor A', 10.0, '2026-09-01'));
        $this->repository->save(new Sponsor(null, 1, 'Sponsor B', 20.0, '2026-09-05'));
        $this->repository->save(new Sponsor(null, 2, 'Sponsor C', 30.0, '2026-09-03'));

        // When sponsors of exercice 1 are requested
        $sponsors = $this->repository->forExercice(1);

        // Then only its two sponsors come back
        $this->assertCount(2, $sponsors);
    }
}
