<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\ParametresScreen;
use SpCompta\Billing\ExerciceDeletionGuard;
use SpCompta\Capabilities;
use SpCompta\Database;
use SpCompta\Numbering\DocumentNumeroGenerator;
use SpCompta\Numbering\SequenceGenerator;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\DevisRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FactureRepository;
use SpCompta\Repository\ParametresRepository;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/ParametresScreen.md
 */
final class ParametresScreenTest extends WP_UnitTestCase
{
    private ParametresScreen $screen;

    private ExerciceRepository $exerciceRepository;

    private Capabilities $capabilities;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $parametresRepository = new ParametresRepository($database->tableParametres());
        $this->exerciceRepository = new ExerciceRepository($database->tableExercice());
        $this->capabilities = new Capabilities();

        $sequences = new SequenceGenerator($database->tableSequence());
        $exerciceDeletionGuard = new ExerciceDeletionGuard(
            $this->exerciceRepository,
            new DepenseRepository($database->tableDepense()),
            new RecetteRepository($database->tableRecette()),
            new SponsorRepository($database->tableSponsor()),
            new DevisRepository($database->tableDevis(), $database->tableDevisLigne(), new DocumentNumeroGenerator($sequences, 'D')),
            new FactureRepository($database->tableFacture(), $database->tableFactureLigne(), new DocumentNumeroGenerator($sequences, 'F'))
        );

        $this->screen = new ParametresScreen(
            $parametresRepository,
            $this->exerciceRepository,
            $this->capabilities,
            $exerciceDeletionGuard
        );
    }

    /** @test */
    public function it_saves_parametres_on_first_submission(): void
    {
        // Given no parametres saved yet
        $request = ['siret' => '12345678900012', 'nom_association' => 'Taekwondo Claira'];

        // When saveParametresFromRequest is called
        $saved = $this->screen->saveParametresFromRequest($request);

        // Then parametres are created with these values
        $this->assertSame('12345678900012', $saved->siret());
        $this->assertSame('Taekwondo Claira', $saved->nomAssociation());
    }

    /** @test */
    public function it_creates_an_inactive_exercice(): void
    {
        // Given a request with start/end dates and an initial balance
        $request = ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31', 'solde_initial' => '150.50'];

        // When saveExerciceFromRequest is called
        $saved = $this->screen->saveExerciceFromRequest($request);

        // Then a new exercice is created, inactive by default
        $this->assertGreaterThan(0, $saved->id());
        $this->assertFalse($saved->actif());
        $this->assertSame(150.50, $saved->soldeInitial());
    }

    /** @test */
    public function it_activates_the_exercice_matching_the_submitted_id(): void
    {
        // Given two created exercices, none active
        $first = $this->screen->saveExerciceFromRequest(['date_debut' => '2025-09-01', 'date_fin' => '2026-08-31']);
        $this->screen->saveExerciceFromRequest(['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31']);

        // When activateExerciceFromRequest is called with the first exercice's id
        $this->screen->activateExerciceFromRequest(['id' => (string) $first->id()]);

        // Then it becomes the active exercice
        $active = $this->exerciceRepository->active();
        $this->assertSame($first->id(), $active->id());
    }

    /** @test */
    public function it_grants_the_capability_to_users_submitted_as_bureau(): void
    {
        // Given two users and a submission checking both
        $firstId = self::factory()->user->create(['role' => 'subscriber']);
        $secondId = self::factory()->user->create(['role' => 'subscriber']);

        // When saveAccesBureauFromRequest is called
        $this->screen->saveAccesBureauFromRequest(['bureau_users' => [(string) $firstId, (string) $secondId]]);

        // Then both users now have the sp_compta_manager capability
        $this->assertTrue(get_user_by('id', $firstId)->has_cap(Capabilities::MANAGE_COMPTA));
        $this->assertTrue(get_user_by('id', $secondId)->has_cap(Capabilities::MANAGE_COMPTA));
    }

    /** @test */
    public function it_deletes_an_empty_exercice_via_the_screen(): void
    {
        // Given an exercice with no data attached
        $exercice = $this->screen->saveExerciceFromRequest(['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31']);

        // When deleteExerciceFromRequest is called
        $result = $this->screen->deleteExerciceFromRequest(['id' => (string) $exercice->id()]);

        // Then it is deleted
        $this->assertTrue($result);
        $this->assertNull($this->exerciceRepository->find((int) $exercice->id()));
    }

    /** @test */
    public function it_corrects_the_solde_initial_of_an_existing_exercice(): void
    {
        // Given an existing exercice with a wrong solde_initial
        $exercice = $this->screen->saveExerciceFromRequest([
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31',
            'solde_initial' => '4315.79',
        ]);

        // When saveExerciceFromRequest is called again with the same id and the corrected value
        $updated = $this->screen->saveExerciceFromRequest([
            'exercice_id' => (string) $exercice->id(),
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31',
            'solde_initial' => '5315.79',
        ]);

        // Then the same exercice is updated, not duplicated
        $this->assertSame($exercice->id(), $updated->id());
        $this->assertSame(5315.79, $updated->soldeInitial());
        $this->assertCount(1, $this->exerciceRepository->all());
    }

    /** @test */
    public function it_never_deactivates_the_active_exercice_when_correcting_its_solde(): void
    {
        // Given an active exercice
        $exercice = $this->screen->saveExerciceFromRequest(['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31']);
        $this->screen->activateExerciceFromRequest(['id' => (string) $exercice->id()]);

        // When its solde_initial is corrected via the edit form
        $this->screen->saveExerciceFromRequest([
            'exercice_id' => (string) $exercice->id(),
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31',
            'solde_initial' => '5315.79',
        ]);

        // Then it is still the active exercice
        $active = $this->exerciceRepository->active();
        $this->assertNotNull($active);
        $this->assertSame($exercice->id(), $active->id());
    }
}
