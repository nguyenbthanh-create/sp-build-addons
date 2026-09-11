<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\FournisseurScreen;
use SpCompta\Database;
use SpCompta\Entity\Fournisseur;
use SpCompta\Repository\FournisseurRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/FournisseurScreen.md
 */
final class FournisseurScreenTest extends WP_UnitTestCase
{
    private FournisseurScreen $screen;

    private FournisseurRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new FournisseurRepository($database->tableFournisseur());
        $this->screen = new FournisseurScreen($this->repository);
    }

    /** @test */
    public function it_creates_a_fournisseur_from_a_form_submission_without_id(): void
    {
        // Given a form submission with no id, a name and an email
        $request = ['nom' => 'Sport Distribution', 'email' => 'contact@sport-distribution.fr'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then a new fournisseur is created in the database with these values
        $this->assertGreaterThan(0, $saved->id());
        $this->assertSame('Sport Distribution', $saved->nom());
        $this->assertSame('contact@sport-distribution.fr', $saved->email());
    }

    /** @test */
    public function it_updates_the_existing_fournisseur_when_an_id_is_submitted(): void
    {
        // Given an existing fournisseur and a submission with its id and a new name
        $existing = $this->repository->save(new Fournisseur(null, 'Ancien nom'));
        $request = ['id' => (string) $existing->id(), 'nom' => 'Nouveau nom'];

        // When saveFromRequest is called
        $this->screen->saveFromRequest($request);

        // Then the existing fournisseur is updated, no duplicate is created
        $found = $this->repository->find((int) $existing->id());
        $this->assertSame('Nouveau nom', $found->nom());
        $this->assertCount(1, $this->repository->all());
    }

    /** @test */
    public function it_deletes_the_fournisseur_matching_the_submitted_id(): void
    {
        // Given an existing fournisseur and a request with its id
        $existing = $this->repository->save(new Fournisseur(null, 'A supprimer'));

        // When deleteFromRequest is called
        $result = $this->screen->deleteFromRequest(['id' => (string) $existing->id()]);

        // Then it no longer exists
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $existing->id()));
    }

    /** @test */
    public function it_does_nothing_when_deleting_without_a_valid_id(): void
    {
        // Given a request without an id
        // When deleteFromRequest is called
        $result = $this->screen->deleteFromRequest([]);

        // Then nothing is deleted and false is returned
        $this->assertFalse($result);
    }
}
