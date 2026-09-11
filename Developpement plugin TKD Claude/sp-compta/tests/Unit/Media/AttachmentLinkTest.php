<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Media;

use SpCompta\Media\AttachmentLink;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Media/AttachmentLink.md
 */
final class AttachmentLinkTest extends WP_UnitTestCase
{
    /** @test */
    public function it_renders_nothing_for_an_empty_value(): void
    {
        // Given no value at all
        // When render is called
        $html = AttachmentLink::render('');

        // Then nothing is rendered
        $this->assertSame('', $html);
    }

    /** @test */
    public function it_renders_a_clickable_link_for_a_real_attachment_id(): void
    {
        // Given a real WordPress attachment
        $attachmentId = self::factory()->attachment->create();

        // When render is called with its id
        $html = AttachmentLink::render((string) $attachmentId);

        // Then a clickable link to that file is rendered
        $this->assertStringContainsString('<a href=', $html);
    }

    /** @test */
    public function it_renders_plain_text_for_a_legacy_free_text_reference(): void
    {
        // Given a legacy free-text reference entered before real uploads existed
        $value = 'ticket-2026-05';

        // When render is called
        $html = AttachmentLink::render($value);

        // Then the text is shown as-is, with no link
        $this->assertSame('ticket-2026-05', $html);
        $this->assertStringNotContainsString('<a href=', $html);
    }

    /** @test */
    public function it_renders_plain_text_for_a_number_matching_no_attachment(): void
    {
        // Given a number that does not correspond to any existing attachment
        $value = '999999';

        // When render is called
        $html = AttachmentLink::render($value);

        // Then the value is shown as-is, with no link
        $this->assertSame('999999', $html);
        $this->assertStringNotContainsString('<a href=', $html);
    }
}
