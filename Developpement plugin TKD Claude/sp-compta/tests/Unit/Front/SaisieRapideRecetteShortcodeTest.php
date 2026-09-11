<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Front;

use SpCompta\Admin\RecetteScreen;
use SpCompta\Database;
use SpCompta\Entity\Exercice;
use SpCompta\Front\SaisieRapideRecetteShortcode;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\ClientRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\RecetteRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Front/SaisieRapideRecetteShortcode.md
 */
final class SaisieRapideRecetteShortcodeTest extends WP_UnitTestCase
{
    private SaisieRapideRecetteShortcode $shortcode;

    private ExerciceRepository $exerciceRepository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $clientRepository = new ClientRepository($database->tableClient());
        $this->exerciceRepository = new ExerciceRepository($database->tableExercice());
        $recetteScreen = new RecetteScreen(
            new RecetteRepository($database->tableRecette()),
            $this->exerciceRepository,
            $clientRepository,
            new AttachmentUploader()
        );

        $this->shortcode = new SaisieRapideRecetteShortcode($recetteScreen, $this->exerciceRepository, $clientRepository);
    }

    /** @test */
    public function it_shows_an_access_message_when_the_user_lacks_the_capability(): void
    {
        // Given a visitor without the sp_compta_manager capability
        wp_set_current_user(0);

        // When the shortcode is rendered
        $html = $this->shortcode->render();

        // Then an access-denied message is shown, no form
        $this->assertStringContainsString('Acces reserve', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    /** @test */
    public function it_shows_a_no_exercice_message_when_none_is_active(): void
    {
        // Given an authorized user but no active exercice
        $userId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($userId);

        // When the shortcode is rendered
        $html = $this->shortcode->render();

        // Then a "no exercice" message is shown, no form
        $this->assertStringContainsString('Aucun exercice actif', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    /** @test */
    public function it_renders_the_quick_entry_form_when_authorized_with_an_active_exercice(): void
    {
        // Given an authorized user and an active exercice
        $userId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($userId);
        $exercice = $this->exerciceRepository->save(new Exercice(null, '2026-09-01', '2027-08-31'));
        $this->exerciceRepository->activate((int) $exercice->id());

        // When the shortcode is rendered
        $html = $this->shortcode->render();

        // Then the quick-entry form is shown, with the montant and mode_paiement fields
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="montant"', $html);
        $this->assertStringContainsString('name="mode_paiement"', $html);
    }
}
