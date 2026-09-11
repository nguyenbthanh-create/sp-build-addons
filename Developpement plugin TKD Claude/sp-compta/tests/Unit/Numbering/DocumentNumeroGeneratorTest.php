<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Numbering;

use SpCompta\Database;
use SpCompta\Numbering\DocumentNumeroGenerator;
use SpCompta\Numbering\SequenceGenerator;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Numbering/DocumentNumeroGenerator.md
 */
final class DocumentNumeroGeneratorTest extends WP_UnitTestCase
{
    private DocumentNumeroGenerator $factureNumeros;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->factureNumeros = new DocumentNumeroGenerator(
            new SequenceGenerator($database->tableSequence()),
            'F'
        );
    }

    /** @test */
    public function it_formats_the_first_number_of_a_year(): void
    {
        // Given no facture has been issued yet in 2026
        // When the first number of 2026 is requested
        $numero = $this->factureNumeros->next('2026');

        // Then it is F2026-0001
        $this->assertSame('F2026-0001', $numero);
    }

    /** @test */
    public function it_increments_the_sequence_within_the_same_year(): void
    {
        // Given a first facture already issued in 2026
        $this->factureNumeros->next('2026');

        // When a second number of 2026 is requested
        $numero = $this->factureNumeros->next('2026');

        // Then it is F2026-0002
        $this->assertSame('F2026-0002', $numero);
    }

    /** @test */
    public function it_restarts_the_sequence_on_a_new_year(): void
    {
        // Given a facture already issued in 2026
        $this->factureNumeros->next('2026');

        // When the first number of 2027 is requested
        $numero = $this->factureNumeros->next('2027');

        // Then the sequence restarts at 1
        $this->assertSame('F2027-0001', $numero);
    }
}
