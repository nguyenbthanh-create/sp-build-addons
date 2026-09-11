<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Billing;

use SpCompta\Billing\ExerciceDeletionGuard;
use SpCompta\Database;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Exercice;
use SpCompta\Entity\Facture;
use SpCompta\Numbering\DocumentNumeroGenerator;
use SpCompta\Numbering\SequenceGenerator;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\DevisRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FactureRepository;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Billing/ExerciceDeletionGuard.md
 */
final class ExerciceDeletionGuardTest extends WP_UnitTestCase
{
    private ExerciceDeletionGuard $guard;

    private ExerciceRepository $exerciceRepository;

    private DepenseRepository $depenseRepository;

    private FactureRepository $factureRepository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->exerciceRepository = new ExerciceRepository($database->tableExercice());
        $this->depenseRepository = new DepenseRepository($database->tableDepense());
        $recetteRepository = new RecetteRepository($database->tableRecette());
        $sponsorRepository = new SponsorRepository($database->tableSponsor());
        $sequences = new SequenceGenerator($database->tableSequence());
        $devisRepository = new DevisRepository(
            $database->tableDevis(),
            $database->tableDevisLigne(),
            new DocumentNumeroGenerator($sequences, 'D')
        );
        $this->factureRepository = new FactureRepository(
            $database->tableFacture(),
            $database->tableFactureLigne(),
            new DocumentNumeroGenerator($sequences, 'F')
        );

        $this->guard = new ExerciceDeletionGuard(
            $this->exerciceRepository,
            $this->depenseRepository,
            $recetteRepository,
            $sponsorRepository,
            $devisRepository,
            $this->factureRepository
        );
    }

    /** @test */
    public function it_deletes_an_exercice_with_no_data(): void
    {
        // Given an exercice with no data attached
        $exercice = $this->exerciceRepository->save(new Exercice(null, '2026-09-01', '2027-08-31'));

        // When delete is called
        $result = $this->guard->delete((int) $exercice->id());

        // Then the exercice is deleted and true is returned
        $this->assertTrue($result);
        $this->assertNull($this->exerciceRepository->find((int) $exercice->id()));
    }

    /** @test */
    public function it_refuses_to_delete_an_exercice_with_a_depense(): void
    {
        // Given an exercice with at least one depense
        $exercice = $this->exerciceRepository->save(new Exercice(null, '2026-09-01', '2027-08-31'));
        $this->depenseRepository->save(new Depense(null, (int) $exercice->id(), '2026-09-07', 10.0));

        // When delete is called
        $result = $this->guard->delete((int) $exercice->id());

        // Then nothing is deleted and false is returned
        $this->assertFalse($result);
        $this->assertNotNull($this->exerciceRepository->find((int) $exercice->id()));
    }

    /** @test */
    public function it_refuses_to_delete_an_exercice_with_a_facture(): void
    {
        // Given an exercice with at least one facture
        $exercice = $this->exerciceRepository->save(new Exercice(null, '2026-09-01', '2027-08-31'));
        $this->factureRepository->save(new Facture(null, (int) $exercice->id(), 1, '2026-09-07', '2026-10-07'));

        // When delete is called
        $result = $this->guard->delete((int) $exercice->id());

        // Then nothing is deleted and false is returned
        $this->assertFalse($result);
        $this->assertNotNull($this->exerciceRepository->find((int) $exercice->id()));
    }
}
