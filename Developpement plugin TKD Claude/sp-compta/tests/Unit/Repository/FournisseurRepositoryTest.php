<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Fournisseur;
use SpCompta\Repository\FournisseurRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/FournisseurRepository.md
 */
final class FournisseurRepositoryTest extends WP_UnitTestCase
{
    private FournisseurRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new FournisseurRepository($database->tableFournisseur());
    }

    /** @test */
    public function it_assigns_an_id_when_a_new_fournisseur_is_saved(): void
    {
        // Given a fournisseur that does not exist yet
        $fournisseur = new Fournisseur(null, 'Sport Distribution');

        // When it is saved
        $saved = $this->repository->save($fournisseur);

        // Then it now has a positive id
        $this->assertGreaterThan(0, $saved->id());
    }

    /** @test */
    public function it_finds_a_previously_saved_fournisseur_by_id(): void
    {
        // Given a fournisseur saved in the database
        $saved = $this->repository->save(
            new Fournisseur(null, 'Sport Distribution', '', '', 'contact@sport-distribution.fr')
        );

        // When it is looked up by its id
        $found = $this->repository->find((int) $saved->id());

        // Then the same data comes back
        $this->assertNotNull($found);
        $this->assertSame('Sport Distribution', $found->nom());
        $this->assertSame('contact@sport-distribution.fr', $found->email());
    }

    /** @test */
    public function it_returns_null_when_the_fournisseur_does_not_exist(): void
    {
        // Given no fournisseur with id 999999
        // When it is looked up
        $found = $this->repository->find(999999);

        // Then nothing is returned
        $this->assertNull($found);
    }

    /** @test */
    public function it_updates_an_existing_fournisseur_instead_of_duplicating_it(): void
    {
        // Given a saved fournisseur
        $saved = $this->repository->save(new Fournisseur(null, 'Ancien nom'));

        // When it is saved again with the same id but a new name
        $updated = new Fournisseur((int) $saved->id(), 'Nouveau nom');
        $this->repository->save($updated);

        // Then only the name has changed, no duplicate row was created
        $found = $this->repository->find((int) $saved->id());
        $this->assertSame('Nouveau nom', $found->nom());
        $this->assertCount(1, $this->repository->all());
    }

    /** @test */
    public function it_removes_a_fournisseur_on_delete(): void
    {
        // Given a saved fournisseur
        $saved = $this->repository->save(new Fournisseur(null, 'A supprimer'));

        // When it is deleted
        $result = $this->repository->delete((int) $saved->id());

        // Then it can no longer be found
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $saved->id()));
    }
}
