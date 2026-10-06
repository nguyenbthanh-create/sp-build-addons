<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Auth;

use SpCompta\Auth\SessionLongue;
use SpCompta\Capabilities;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Auth/SessionLongue.md
 */
final class SessionLongueTest extends WP_UnitTestCase
{
    /** @test */
    public function it_gives_a_one_year_session_to_a_treasury_account(): void
    {
        // Given a user granted the treasury capability (bureau member)
        $userId = self::factory()->user->create(['role' => 'subscriber']);
        get_user_by('id', $userId)->add_cap(Capabilities::MANAGE_COMPTA);

        // When WordPress computes the length of its login cookie (14 days with "remember me")
        $duree = (new SessionLongue())->filtrerDuree(14 * 24 * 3600, $userId, true);

        // Then the session lasts one year
        $this->assertSame(SessionLongue::DUREE, $duree);
    }

    /** @test */
    public function it_leaves_other_accounts_unchanged(): void
    {
        // Given a regular site user without treasury access
        $userId = self::factory()->user->create(['role' => 'subscriber']);

        // When WordPress computes the length of its login cookie
        $duree = (new SessionLongue())->filtrerDuree(14 * 24 * 3600, $userId, true);

        // Then the usual duration is kept
        $this->assertSame(14 * 24 * 3600, $duree);
    }

    /** @test */
    public function it_ignores_an_unknown_user(): void
    {
        // Given no user (id 0, e.g. a failed login)
        // When the duration is filtered
        $duree = (new SessionLongue())->filtrerDuree(172800, 0, false);

        // Then nothing changes
        $this->assertSame(172800, $duree);
    }
}
