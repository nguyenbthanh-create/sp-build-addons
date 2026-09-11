<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Client;
use SpCompta\Repository\ClientRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/ClientRepository.md
 */
final class ClientRepositoryTest extends WP_UnitTestCase
{
    private ClientRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new ClientRepository($database->tableClient());
    }

    /** @test */
    public function it_assigns_an_id_when_a_new_client_is_saved(): void
    {
        // Given a client that does not exist yet
        $client = new Client(null, 'Centre de loisirs Claira');

        // When it is saved
        $saved = $this->repository->save($client);

        // Then it now has a positive id
        $this->assertGreaterThan(0, $saved->id());
    }

    /** @test */
    public function it_finds_a_previously_saved_client_by_id(): void
    {
        // Given a client saved in the database
        $saved = $this->repository->save(
            new Client(null, 'Centre de loisirs Claira', 'collectivite', '', '', '', 'contact@cl-claira.fr')
        );

        // When it is looked up by its id
        $found = $this->repository->find((int) $saved->id());

        // Then the same data comes back
        $this->assertNotNull($found);
        $this->assertSame('Centre de loisirs Claira', $found->nom());
        $this->assertSame('collectivite', $found->type());
    }

    /** @test */
    public function it_returns_null_when_the_client_does_not_exist(): void
    {
        // Given no client with id 999999
        // When it is looked up
        $found = $this->repository->find(999999);

        // Then nothing is returned
        $this->assertNull($found);
    }

    /** @test */
    public function it_updates_an_existing_client_instead_of_duplicating_it(): void
    {
        // Given a saved client
        $saved = $this->repository->save(new Client(null, 'Ancien nom'));

        // When it is saved again with the same id but a new name
        $updated = new Client((int) $saved->id(), 'Nouveau nom');
        $this->repository->save($updated);

        // Then only the name has changed, no duplicate row was created
        $found = $this->repository->find((int) $saved->id());
        $this->assertSame('Nouveau nom', $found->nom());
        $this->assertCount(1, $this->repository->all());
    }

    /** @test */
    public function it_removes_a_client_on_delete(): void
    {
        // Given a saved client
        $saved = $this->repository->save(new Client(null, 'A supprimer'));

        // When it is deleted
        $result = $this->repository->delete((int) $saved->id());

        // Then it can no longer be found
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $saved->id()));
    }
}
