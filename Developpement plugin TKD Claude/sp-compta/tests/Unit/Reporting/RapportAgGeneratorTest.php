<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Reporting;

use SpCompta\Entity\Exercice;
use SpCompta\Reporting\RapportAgGenerator;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Reporting/RapportAgGenerator.md
 */
final class RapportAgGeneratorTest extends WP_UnitTestCase
{
    /** @test */
    public function svg_comparaison_barres_includes_all_four_amounts(): void
    {
        // Given amounts for the previous and the current exercice
        // When the comparison chart is built
        $svg = RapportAgGenerator::svgComparaisonBarres('2025/2026', 1000.0, 800.0, '2026/2027', 1200.0, 900.0);

        // Then it is a valid-looking SVG containing all four formatted amounts
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('1 000 €', $svg);
        $this->assertStringContainsString('800 €', $svg);
        $this->assertStringContainsString('1 200 €', $svg);
        $this->assertStringContainsString('900 €', $svg);
    }

    /** @test */
    public function svg_comparaison_barres_does_not_divide_by_zero_when_every_amount_is_zero(): void
    {
        // Given an exercice with no movement at all yet
        // When the comparison chart is built
        $svg = RapportAgGenerator::svgComparaisonBarres('2025/2026', 0.0, 0.0, '2026/2027', 0.0, 0.0);

        // Then it still renders without error (no NAN/INF in the output)
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('NAN', $svg);
        $this->assertStringNotContainsString('INF', $svg);
    }

    /** @test */
    public function render_shows_the_summary_and_the_comparison_when_a_previous_exercice_is_known(): void
    {
        // Given an exercice with a known previous exercice
        $exercice = new Exercice(2, '2026-09-01', '2027-08-31', 100.0, true);

        // When the report is rendered
        $html = RapportAgGenerator::render(
            $exercice,
            1500.0,
            1200.0,
            400.0,
            [],
            [],
            [],
            '2025-09-01 - 2026-08-31',
            1000.0,
            900.0,
            null
        );

        // Then the summary totals and the comparison chart both appear
        $this->assertStringContainsString('Rapport financier', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('Aucun exercice antérieur', $html);
    }

    /** @test */
    public function render_shows_a_note_instead_of_a_chart_when_there_is_no_previous_exercice(): void
    {
        // Given the very first exercice ever tracked (no predecessor)
        $exercice = new Exercice(1, '2026-09-01', '2027-08-31');

        // When the report is rendered without previous-exercice figures
        $html = RapportAgGenerator::render($exercice, 1000.0, 800.0, 200.0, [], [], [], null, null, null, null);

        // Then a note replaces the comparison chart, no SVG is emitted
        $this->assertStringContainsString('Aucun exercice antérieur', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }
}
