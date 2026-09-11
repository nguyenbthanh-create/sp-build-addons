<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\ClientScreen;
use SpCompta\Database;
use SpCompta\Entity\Client;
use SpCompta\Repository\ClientRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/ClientScreen.md
 */
final class ClientScreenTest extends WP_UnitTestCase
{
    private ClientScreen $screen;

    private ClientRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new ClientRepository($database->tableClient());
        $this->screen = new ClientScreen($this->repository);
    }

    /** @test */
    public function it_creates_a_client_from_a_form_submission_without_id(): void
    {
        // Given a form submission with no id, a name and a type
        $request = ['nom' => 'Centre de loisirs Claira', 'type' => 'collectivite'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then a new client is created with these values
        $this->assertGreaterThan(0, $saved->id());
        $this->assertSame('collectivite', $saved->type());
    }

    /** @test */
    public function it_falls_back_to_particulier_when_the_submitted_type_is_invalid(): void
    {
        // Given a form submission with a type outside the allowed list
        $request = ['nom' => 'Test', 'type' => 'admin'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then the client is saved with the default type "particulier"
        $this->assertSame('particulier', $saved->type());
    }

    /** @test */
    public function it_updates_the_existing_client_when_an_id_is_submitted(): void
    {
        // Given an existing client and a submission with its id and a new name
        $existing = $this->repository->save(new Client(null, 'Ancien nom'));
        $request = ['id' => (string) $existing->id(), 'nom' => 'Nouveau nom'];

        // When saveFromRequest is called
        $this->screen->saveFromRequest($request);

        // Then the existing client is updated, no duplicate is created
        $found = $this->repository->find((int) $existing->id());
        $this->assertSame('Nouveau nom', $found->nom());
        $this->assertCount(1, $this->repository->all());
    }

    /** @test */
    public function it_deletes_the_client_matching_the_submitted_id(): void
    {
        // Given an existing client
        $existing = $this->repository->save(new Client(null, 'A supprimer'));

        // When deleteFromRequest is called
        $result = $this->screen->deleteFromRequest(['id' => (string) $existing->id()]);

        // Then it no longer exists
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $existing->id()));
    }
}
