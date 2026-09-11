<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\Search;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/Search.md
 */
final class SearchTest extends WP_UnitTestCase
{
    /** @test */
    public function it_matches_when_the_term_is_a_substring_of_one_field(): void
    {
        // Given a term that appears in one of the fields
        // When matches is called
        $result = Search::matches('spor', ['Sport Distribution', 'contact@example.fr']);

        // Then it returns true
        $this->assertTrue($result);
    }

    /** @test */
    public function it_is_case_insensitive(): void
    {
        // Given a term in a different case than the field
        // When matches is called
        $result = Search::matches('SPORT', ['Sport Distribution']);

        // Then it still matches
        $this->assertTrue($result);
    }

    /** @test */
    public function it_matches_numeric_values_converted_to_string(): void
    {
        // Given a numeric field
        // When matches is called with a term found in that number
        $result = Search::matches('42', [42.5]);

        // Then it matches
        $this->assertTrue($result);
    }

    /** @test */
    public function it_does_not_match_when_the_term_is_nowhere_to_be_found(): void
    {
        // Given a term absent from every field
        // When matches is called
        $result = Search::matches('introuvable', ['Sport Distribution', 42.5]);

        // Then it returns false
        $this->assertFalse($result);
    }

    /** @test */
    public function it_matches_everything_when_the_term_is_empty(): void
    {
        // Given an empty term
        // When matches is called
        $result = Search::matches('', ['Sport Distribution']);

        // Then it always matches (no filter applied)
        $this->assertTrue($result);
    }

    /** @test */
    public function it_ignores_null_fields_without_erroring(): void
    {
        // Given a haystack containing a null value
        // When matches is called with a term found in another field
        $result = Search::matches('sport', [null, 'Sport Distribution']);

        // Then it still matches, null is skipped safely
        $this->assertTrue($result);
    }
}
