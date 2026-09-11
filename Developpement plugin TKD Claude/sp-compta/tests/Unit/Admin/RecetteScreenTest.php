<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\RecetteScreen;
use SpCompta\Database;
use SpCompta\Entity\Recette;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\ClientRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\RecetteRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/RecetteScreen.md
 */
final class RecetteScreenTest extends WP_UnitTestCase
{
    private RecetteScreen $screen;

    private RecetteRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new RecetteRepository($database->tableRecette());
        $this->screen = new RecetteScreen(
            $this->repository,
            new ExerciceRepository($database->tableExercice()),
            new ClientRepository($database->tableClient()),
            new AttachmentUploader()
        );
    }

    /** @test */
    public function it_creates_a_recette_from_a_form_submission(): void
    {
        // Given a form submission for exercice 1 with a valid mode_paiement
        $request = [
            'exercice_id' => '1',
            'date' => '2026-09-07',
            'montant' => '100',
            'provenance' => 'Cotisation',
            'mode_paiement' => 'especes',
        ];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then a new recette is created with these values
        $this->assertGreaterThan(0, $saved->id());
        $this->assertSame('Cotisation', $saved->provenance());
        $this->assertSame('especes', $saved->modePaiement());
    }

    /** @test */
    public function it_falls_back_to_empty_mode_paiement_when_invalid(): void
    {
        // Given a submission with a mode_paiement outside the allowed list
        $request = ['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '10', 'mode_paiement' => 'bitcoin'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then the recette is saved with an empty mode_paiement instead of the invalid value
        $this->assertSame('', $saved->modePaiement());
    }

    /** @test */
    public function it_derives_the_categorie_code_from_a_known_sous_categorie(): void
    {
        // Given a submission with a sous_categorie key present under code "74" in Categories::RECETTE
        $request = ['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '10', 'sous_categorie' => 'commune'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then the recette keeps this sous-categorie and its parent code categorie is derived
        $this->assertSame('commune', $saved->sousCategorie());
        $this->assertSame('74', $saved->categorie());
    }

    /** @test */
    public function it_falls_back_to_empty_categorie_and_sous_categorie_when_invalid(): void
    {
        // Given a submission with a sous_categorie outside the allowed list
        $request = ['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '10', 'sous_categorie' => 'invente'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then both categorie and sous_categorie are saved empty instead of the invalid value
        $this->assertSame('', $saved->categorie());
        $this->assertSame('', $saved->sousCategorie());
    }

    /** @test */
    public function it_keeps_the_existing_justificatif_when_editing_without_choosing_a_new_file(): void
    {
        // Given an existing recette with a justificatif already saved
        $created = $this->screen->saveFromRequest(['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '10']);
        $withJustificatif = $this->repository->save(
            new Recette(
                (int) $created->id(),
                1,
                '2026-09-07',
                10.0,
                '',
                null,
                '',
                null,
                '',
                '42'
            )
        );

        // When the recette is saved again through the screen without submitting a new file
        $resaved = $this->screen->saveFromRequest([
            'id' => (string) $withJustificatif->id(),
            'exercice_id' => '1',
            'date' => '2026-09-07',
            'montant' => '15',
        ]);

        // Then the previously saved justificatif is preserved, not erased
        $this->assertSame('42', $resaved->justificatif());
    }

    /** @test */
    public function it_deletes_the_recette_matching_the_submitted_id(): void
    {
        // Given an existing recette
        $existing = $this->repository->save(new Recette(null, 1, '2026-09-07', 10.0));

        // When deleteFromRequest is called
        $result = $this->screen->deleteFromRequest(['id' => (string) $existing->id()]);

        // Then it no longer exists
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $existing->id()));
    }
}
