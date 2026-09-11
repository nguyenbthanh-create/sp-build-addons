<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Front;

use SpCompta\Admin\DepenseScreen;
use SpCompta\Admin\RecetteScreen;
use SpCompta\Database;
use SpCompta\Entity\Exercice;
use SpCompta\Front\SaisieRapideCombineeShortcode;
use SpCompta\Front\SaisieRapideRecetteShortcode;
use SpCompta\Front\SaisieRapideShortcode;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\ClientRepository;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FournisseurRepository;
use SpCompta\Repository\RecetteRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Front/SaisieRapideCombineeShortcode.md
 */
final class SaisieRapideCombineeShortcodeTest extends WP_UnitTestCase
{
    private SaisieRapideCombineeShortcode $shortcode;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $exerciceRepository = new ExerciceRepository($database->tableExercice());
        $fournisseurRepository = new FournisseurRepository($database->tableFournisseur());
        $clientRepository = new ClientRepository($database->tableClient());
        $attachmentUploader = new AttachmentUploader();

        $depenseShortcode = new SaisieRapideShortcode(
            new DepenseScreen(new DepenseRepository($database->tableDepense()), $exerciceRepository, $fournisseurRepository, $attachmentUploader),
            $exerciceRepository,
            $fournisseurRepository
        );
        $recetteShortcode = new SaisieRapideRecetteShortcode(
            new RecetteScreen(new RecetteRepository($database->tableRecette()), $exerciceRepository, $clientRepository, $attachmentUploader),
            $exerciceRepository,
            $clientRepository
        );

        $this->shortcode = new SaisieRapideCombineeShortcode($depenseShortcode, $recetteShortcode);

        $userId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($userId);
        $exercice = $exerciceRepository->save(new Exercice(null, '2026-09-01', '2027-08-31'));
        $exerciceRepository->activate((int) $exercice->id());
    }

    /** @test */
    public function it_shows_both_panels_with_depense_active_by_default(): void
    {
        // Given no query parameter indicating a just-saved entry
        unset($_GET['sp_compta_saved']);

        // When the combined shortcode is rendered
        $html = $this->shortcode->render();

        // Then both forms are present, and the Depense panel is the visible one
        $this->assertStringContainsString('data-panel="depense">', $html);
        $this->assertStringContainsString('data-panel="recette" hidden>', $html);
        $this->assertStringContainsString('Nouvelle depense', $html);
        $this->assertStringContainsString('Nouvelle recette', $html);
    }

    /** @test */
    public function it_reopens_the_recette_tab_after_a_recette_was_just_saved(): void
    {
        // Given a redirect back from saving a recette
        $_GET['sp_compta_saved'] = 'recette';

        // When the combined shortcode is rendered
        $html = $this->shortcode->render();

        // Then the Recette panel is the visible one (its confirmation message would otherwise be hidden)
        $this->assertStringContainsString('data-panel="recette">', $html);
        $this->assertStringContainsString('data-panel="depense" hidden>', $html);
        $this->assertStringContainsString('Recette enregistree.', $html);

        unset($_GET['sp_compta_saved']);
    }
}
