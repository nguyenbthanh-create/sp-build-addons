<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit;

use SpCompta\Capabilities;
use WP_UnitTestCase;

final class CapabilitiesTest extends WP_UnitTestCase
{
    /** @test */
    public function it_grants_the_capability_to_the_administrator_role(): void
    {
        // Given the capability has not been registered yet
        $administrator = get_role('administrator');
        $administrator->remove_cap(Capabilities::MANAGE_COMPTA);

        // When capabilities are registered
        (new Capabilities())->register();

        // Then the administrator role has the capability
        $this->assertTrue(get_role('administrator')->has_cap(Capabilities::MANAGE_COMPTA));
    }

    /** @test */
    public function it_reports_that_the_current_user_cannot_manage_when_not_logged_in(): void
    {
        // Given no logged-in user
        wp_set_current_user(0);

        // When checking manage permission
        $canManage = (new Capabilities())->currentUserCanManage();

        // Then it is false
        $this->assertFalse($canManage);
    }

    /** @test */
    public function it_grants_the_capability_directly_to_users_synced_as_bureau(): void
    {
        // Given a non-administrator user
        $userId = self::factory()->user->create(['role' => 'subscriber']);

        // When they are synced as a bureau user
        (new Capabilities())->syncBureauUsers([$userId]);

        // Then they have the capability directly, without being an administrator
        $user = get_user_by('id', $userId);
        $this->assertTrue($user->has_cap(Capabilities::MANAGE_COMPTA));
    }

    /** @test */
    public function it_revokes_the_capability_from_a_user_removed_from_the_bureau_list(): void
    {
        // Given a subscriber previously synced as bureau
        $userId = self::factory()->user->create(['role' => 'subscriber']);
        $capabilities = new Capabilities();
        $capabilities->syncBureauUsers([$userId]);

        // When they are synced again with an empty list
        $capabilities->syncBureauUsers([]);

        // Then they no longer have the capability
        $user = get_user_by('id', $userId);
        $this->assertFalse($user->has_cap(Capabilities::MANAGE_COMPTA));
    }

    /** @test */
    public function it_remembers_the_current_bureau_users(): void
    {
        // Given two users synced as bureau
        $firstId = self::factory()->user->create(['role' => 'subscriber']);
        $secondId = self::factory()->user->create(['role' => 'subscriber']);
        $capabilities = new Capabilities();
        $capabilities->syncBureauUsers([$firstId, $secondId]);

        // When the bureau list is read back
        $bureauUsers = $capabilities->bureauUsers();

        // Then it contains both ids
        $this->assertSame([$firstId, $secondId], $bureauUsers);
    }
}
