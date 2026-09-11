<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Media;

use SpCompta\Media\AttachmentUploader;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Media/AttachmentUploader.md
 */
final class AttachmentUploaderTest extends WP_UnitTestCase
{
    /** @test */
    public function it_keeps_the_existing_value_when_the_field_is_absent_from_files(): void
    {
        // Given $files does not contain the field's key at all
        $files = [];

        // When handle is called with an existing value
        $result = (new AttachmentUploader())->handle($files, 'justificatif', '42');

        // Then the existing value is returned unchanged
        $this->assertSame('42', $result);
    }

    /** @test */
    public function it_keeps_the_existing_value_when_no_file_was_chosen(): void
    {
        // Given the field is present but marked as "no file" (browsers always send this key)
        $files = ['justificatif' => ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'name' => '']];

        // When handle is called with an existing value
        $result = (new AttachmentUploader())->handle($files, 'justificatif', '42');

        // Then the existing value is returned unchanged
        $this->assertSame('42', $result);
    }

    /** @test */
    public function it_keeps_the_existing_value_when_the_field_is_absent_and_none_existed_before(): void
    {
        // Given no file submitted and no prior value
        $files = [];

        // When handle is called
        $result = (new AttachmentUploader())->handle($files, 'fichier_contrat', '');

        // Then it stays empty
        $this->assertSame('', $result);
    }
}
