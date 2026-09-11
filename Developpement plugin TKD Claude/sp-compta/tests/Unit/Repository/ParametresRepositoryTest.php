<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Parametres;
use SpCompta\Repository\ParametresRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/ParametresRepository.md
 */
final class ParametresRepositoryTest extends WP_UnitTestCase
{
    private ParametresRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new ParametresRepository($database->tableParametres());
    }

    /** @test */
    public function it_returns_null_when_nothing_has_been_configured_yet(): void
    {
        // Given no parametres row has ever been saved
        // When it is requested
        $parametres = $this->repository->get();

        // Then nothing is returned
        $this->assertNull($parametres);
    }

    /** @test */
    public function it_creates_the_row_on_first_save(): void
    {
        // Given no parametres row exists
        // When parametres are saved for the first time
        $this->repository->save(new Parametres(null, '12345678900012', '1 rue du Stade, 66530 Claira'));

        // Then get() now returns them
        $found = $this->repository->get();
        $this->assertNotNull($found);
        $this->assertSame('12345678900012', $found->siret());
    }

    /** @test */
    public function it_updates_the_same_row_instead_of_creating_a_second_one(): void
    {
        // Given parametres already saved
        $this->repository->save(new Parametres(null, 'ancien-siret'));

        // When they are saved again with different values, even with no id set
        $this->repository->save(new Parametres(null, 'nouveau-siret'));

        // Then get() reflects the new value and only one row exists
        $found = $this->repository->get();
        $this->assertSame('nouveau-siret', $found->siret());

        global $wpdb;
        $table = (new Database())->tableParametres();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $this->assertSame(1, $count);
    }
}
