<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\SponsorScreen;
use SpCompta\Billing\SponsorPaiementSync;
use SpCompta\Database;
use SpCompta\Entity\Sponsor;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/SponsorScreen.md
 */
final class SponsorScreenTest extends WP_UnitTestCase
{
    private SponsorScreen $screen;

    private SponsorRepository $repository;

    private RecetteRepository $recetteRepository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new SponsorRepository($database->tableSponsor());
        $this->recetteRepository = new RecetteRepository($database->tableRecette());
        $this->screen = new SponsorScreen(
            $this->repository,
            new ExerciceRepository($database->tableExercice()),
            new SponsorPaiementSync($this->repository, $this->recetteRepository),
            new AttachmentUploader()
        );
    }

    /** @test */
    public function it_creates_a_sponsor_from_a_form_submission(): void
    {
        // Given a form submission with valid values
        $request = [
            'exercice_id' => '1',
            'nom' => 'Boulangerie du village',
            'montant' => '200',
            'date' => '2026-09-07',
            'type_paiement' => 'numeraire',
        ];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then a new sponsor is created with these values
        $this->assertGreaterThan(0, $saved->id());
        $this->assertSame('Boulangerie du village', $saved->nom());
        $this->assertSame('numeraire', $saved->typePaiement());
    }

    /** @test */
    public function it_falls_back_to_numeraire_when_type_paiement_is_invalid(): void
    {
        // Given a submission with a type_paiement outside the allowed list
        $request = ['exercice_id' => '1', 'nom' => 'Test', 'montant' => '10', 'date' => '2026-09-07', 'type_paiement' => 'cheque-cadeau'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then the sponsor is saved with the default type "numeraire"
        $this->assertSame('numeraire', $saved->typePaiement());
    }

    /** @test */
    public function it_treats_a_missing_contrat_signe_key_as_unchecked(): void
    {
        // Given a submission without the contrat_signe key (checkbox left unchecked)
        $request = ['exercice_id' => '1', 'nom' => 'Test', 'montant' => '10', 'date' => '2026-09-07'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then contratSigne() is false
        $this->assertFalse($saved->contratSigne());
    }

    /** @test */
    public function it_switches_a_paid_sponsor_into_a_linked_recette(): void
    {
        // Given a submission with contrat_paye checked, no recette_id yet
        $request = [
            'exercice_id' => '1',
            'nom' => 'Boulangerie du village',
            'montant' => '200',
            'date' => '2026-09-07',
            'contrat_paye' => '1',
        ];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then the sponsor is created AND a linked recette is created
        $this->assertNotNull($saved->recetteId());
        $this->assertCount(1, $this->recetteRepository->forExercice(1));
    }

    /** @test */
    public function it_keeps_the_existing_fichier_contrat_when_editing_without_choosing_a_new_file(): void
    {
        // Given an existing sponsor with a fichier_contrat already saved
        $created = $this->screen->saveFromRequest(['exercice_id' => '1', 'nom' => 'Test', 'montant' => '10', 'date' => '2026-09-07']);
        $withFichier = $this->repository->save(new Sponsor(
            id: (int) $created->id(),
            exerciceId: 1,
            nom: 'Test',
            montant: 10.0,
            date: '2026-09-07',
            fichierContrat: '42'
        ));

        // When the sponsor is saved again through the screen without submitting a new file
        $resaved = $this->screen->saveFromRequest([
            'id' => (string) $withFichier->id(),
            'exercice_id' => '1',
            'nom' => 'Test',
            'montant' => '20',
            'date' => '2026-09-07',
        ]);

        // Then the previously saved fichier_contrat is preserved, not erased
        $this->assertSame('42', $resaved->fichierContrat());
    }

    /** @test */
    public function it_deletes_the_sponsor_matching_the_submitted_id(): void
    {
        // Given an existing sponsor
        $existing = $this->repository->save(new Sponsor(null, 1, 'A supprimer', 10.0, '2026-09-07'));

        // When deleteFromRequest is called
        $result = $this->screen->deleteFromRequest(['id' => (string) $existing->id()]);

        // Then it no longer exists
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $existing->id()));
    }
}
