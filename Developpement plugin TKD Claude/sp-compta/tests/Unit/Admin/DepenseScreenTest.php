<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\DepenseScreen;
use SpCompta\Database;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Projet;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FournisseurRepository;
use SpCompta\Repository\ProjetRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/DepenseScreen.md
 */
final class DepenseScreenTest extends WP_UnitTestCase
{
    private DepenseScreen $screen;

    private DepenseRepository $repository;

    private ProjetRepository $projets;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new DepenseRepository($database->tableDepense());
        $this->projets = new ProjetRepository($database->tableProjet());
        $this->screen = new DepenseScreen(
            $this->repository,
            new ExerciceRepository($database->tableExercice()),
            new FournisseurRepository($database->tableFournisseur()),
            new AttachmentUploader(),
            $this->projets
        );
    }

    /** @test */
    public function it_creates_a_depense_from_a_form_submission(): void
    {
        // Given a form submission for exercice 1 with a valid mode_paiement
        $request = ['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '42.5', 'mode_paiement' => 'CB'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then a new depense is created with these values
        $this->assertGreaterThan(0, $saved->id());
        $this->assertSame(42.5, $saved->montant());
        $this->assertSame('CB', $saved->modePaiement());
    }

    /** @test */
    public function it_falls_back_to_empty_mode_paiement_when_invalid(): void
    {
        // Given a submission with a mode_paiement outside the allowed list
        $request = ['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '10', 'mode_paiement' => 'bitcoin'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then the depense is saved with an empty mode_paiement instead of the invalid value
        $this->assertSame('', $saved->modePaiement());
    }

    /** @test */
    public function it_derives_the_categorie_code_from_a_known_sous_categorie(): void
    {
        // Given a submission with a sous_categorie key present under code "60" in Categories::DEPENSE
        $request = ['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '10', 'sous_categorie' => 'fourniture_bureau'];

        // When saveFromRequest is called
        $saved = $this->screen->saveFromRequest($request);

        // Then the depense keeps this sous-categorie and its parent code categorie is derived
        $this->assertSame('fourniture_bureau', $saved->sousCategorie());
        $this->assertSame('60', $saved->categorie());
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
        // Given an existing depense with a justificatif already saved
        $created = $this->screen->saveFromRequest(['exercice_id' => '1', 'date' => '2026-09-07', 'montant' => '10']);
        $withJustificatif = $this->repository->save(
            new Depense(
                (int) $created->id(),
                1,
                '2026-09-07',
                10.0,
                null,
                '',
                null,
                '',
                '42'
            )
        );

        // When the depense is saved again through the screen without submitting a new file
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
    public function it_deletes_the_depense_matching_the_submitted_id(): void
    {
        // Given an existing depense
        $existing = $this->repository->save(new Depense(null, 1, '2026-09-07', 10.0));

        // When deleteFromRequest is called
        $result = $this->screen->deleteFromRequest(['id' => (string) $existing->id()]);

        // Then it no longer exists
        $this->assertTrue($result);
        $this->assertNull($this->repository->find((int) $existing->id()));
    }

    /** @test */
    public function it_attaches_the_depense_to_a_projet_of_the_same_season(): void
    {
        // Given a projet of season 1
        $projet = $this->projets->save(new Projet(null, 1, 'Fete de Noel'));

        // When a depense of season 1 is submitted with this projet
        $saved = $this->screen->saveFromRequest([
            'exercice_id' => '1', 'date' => '2026-12-05', 'montant' => '35', 'projet_id' => (string) $projet->id(),
        ]);

        // Then it is attached to the projet
        $this->assertSame($projet->id(), $saved->projetId());
    }

    /** @test */
    public function it_ignores_a_projet_from_another_season(): void
    {
        // Given a projet of season 2
        $projet = $this->projets->save(new Projet(null, 2, 'Projet de l\'autre saison'));

        // When a depense of season 1 is submitted with this projet
        $saved = $this->screen->saveFromRequest([
            'exercice_id' => '1', 'date' => '2026-12-05', 'montant' => '35', 'projet_id' => (string) $projet->id(),
        ]);

        // Then the depense stays in regular operations
        $this->assertNull($saved->projetId());
    }

    /** @test */
    public function it_keeps_the_projet_when_the_field_is_not_submitted(): void
    {
        // Given a depense already attached to a projet
        $projet = $this->projets->save(new Projet(null, 1, 'Fete de Noel'));
        $created = $this->screen->saveFromRequest([
            'exercice_id' => '1', 'date' => '2026-12-05', 'montant' => '35', 'projet_id' => (string) $projet->id(),
        ]);

        // When it is saved again by a form that has no "projet" field
        $resaved = $this->screen->saveFromRequest([
            'id' => (string) $created->id(), 'exercice_id' => '1', 'date' => '2026-12-05', 'montant' => '40',
        ]);

        // Then the projet is preserved, not erased
        $this->assertSame($projet->id(), $resaved->projetId());
    }

    /** @test */
    public function it_detaches_the_depense_when_the_field_is_emptied(): void
    {
        // Given a depense attached to a projet
        $projet = $this->projets->save(new Projet(null, 1, 'Fete de Noel'));
        $created = $this->screen->saveFromRequest([
            'exercice_id' => '1', 'date' => '2026-12-05', 'montant' => '35', 'projet_id' => (string) $projet->id(),
        ]);

        // When it is saved with "fonctionnement courant" chosen
        $resaved = $this->screen->saveFromRequest([
            'id' => (string) $created->id(), 'exercice_id' => '1', 'date' => '2026-12-05', 'montant' => '35', 'projet_id' => '',
        ]);

        // Then it is back in regular operations
        $this->assertNull($resaved->projetId());
    }
}
