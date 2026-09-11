<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Numbering;

use SpCompta\Database;
use SpCompta\Numbering\SequenceGenerator;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Numbering/SequenceGenerator.md
 */
final class SequenceGeneratorTest extends WP_UnitTestCase
{
    private SequenceGenerator $generator;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->generator = new SequenceGenerator($database->tableSequence());
    }

    /** @test */
    public function it_returns_one_for_a_brand_new_key(): void
    {
        // Given a key that has never been used
        // When the next value is requested
        $value = $this->generator->next('facture_2026');

        // Then it starts at 1
        $this->assertSame(1, $value);
    }

    /** @test */
    public function it_increments_on_each_call_for_the_same_key(): void
    {
        // Given a key already used once
        $this->generator->next('facture_2026');

        // When the next value is requested again
        $value = $this->generator->next('facture_2026');

        // Then it is 2
        $this->assertSame(2, $value);
    }

    /** @test */
    public function it_keeps_independent_counters_per_key(): void
    {
        // Given two distinct keys
        $this->generator->next('facture_2026');
        $this->generator->next('facture_2026');

        // When the next value of a different key is requested
        $value = $this->generator->next('devis_2026');

        // Then it starts at 1, unaffected by the other key's counter
        $this->assertSame(1, $value);
    }
}
