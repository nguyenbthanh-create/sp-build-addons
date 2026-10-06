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

    /** @test */
    public function it_shows_a_login_button_instead_of_the_forms_when_logged_out(): void
    {
        // Given nobody is logged in (session expired on the phone)
        wp_set_current_user(0);

        // When the combined shortcode is rendered
        $html = $this->shortcode->render();

        // Then a single login screen is shown, with a button leading to the login page, and no form
        $this->assertStringContainsString('Connexion nécessaire', $html);
        $this->assertStringContainsString('wp-login.php', $html);
        $this->assertStringNotContainsString('Nouvelle depense', $html);
    }

    /** @test */
    public function it_hands_out_fresh_form_tokens_to_a_treasury_account(): void
    {
        // Given the logged-in administrator of setUp (treasury access)
        // When fresh tokens are requested just before sending a form
        $jetons = $this->shortcode->jetons();

        // Then both forms get a valid token
        $this->assertNotNull($jetons);
        $this->assertSame(1, wp_verify_nonce($jetons['depense'], SaisieRapideShortcode::NONCE));
        $this->assertSame(1, wp_verify_nonce($jetons['recette'], SaisieRapideRecetteShortcode::NONCE));
    }

    /** @test */
    public function it_refuses_tokens_to_an_account_without_treasury_access(): void
    {
        // Given a logged-in user without treasury access
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        // When fresh tokens are requested
        // Then none are given
        $this->assertNull($this->shortcode->jetons());
    }
}
