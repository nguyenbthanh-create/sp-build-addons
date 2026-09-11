<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Billing;

use SpCompta\Billing\SponsorPaiementSync;
use SpCompta\Database;
use SpCompta\Entity\Sponsor;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Billing/SponsorPaiementSync.md
 */
final class SponsorPaiementSyncTest extends WP_UnitTestCase
{
    private SponsorPaiementSync $sync;

    private SponsorRepository $sponsorRepository;

    private RecetteRepository $recetteRepository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->sponsorRepository = new SponsorRepository($database->tableSponsor());
        $this->recetteRepository = new RecetteRepository($database->tableRecette());
        $this->sync = new SponsorPaiementSync($this->sponsorRepository, $this->recetteRepository);
    }

    /** @test */
    public function it_creates_a_recette_for_a_paid_sponsor_without_a_linked_recette(): void
    {
        // Given a sponsor marked as paid, with no linked recette
        $sponsor = $this->sponsorRepository->save(
            new Sponsor(null, 1, 'Boulangerie du village', 200.0, '2026-09-07', 'numeraire', false, '', true)
        );

        // When sync is called
        $updated = $this->sync->sync($sponsor);

        // Then a recette is created with the sponsor's montant and date
        $this->assertNotNull($updated->recetteId());
        $recette = $this->recetteRepository->find((int) $updated->recetteId());
        $this->assertNotNull($recette);
        $this->assertSame(200.0, $recette->montant());
        $this->assertSame('2026-09-07', $recette->date());
        $this->assertSame('74', $recette->categorie());
        $this->assertSame('sponsors_prives', $recette->sousCategorie());

        // And the sponsor is updated with this recette's id
        $found = $this->sponsorRepository->find((int) $sponsor->id());
        $this->assertSame($updated->recetteId(), $found->recetteId());
    }

    /** @test */
    public function it_does_not_create_a_duplicate_on_a_second_call(): void
    {
        // Given a sponsor already synced (recetteId already set)
        $sponsor = $this->sponsorRepository->save(
            new Sponsor(null, 1, 'Boulangerie du village', 200.0, '2026-09-07', 'numeraire', false, '', true)
        );
        $synced = $this->sync->sync($sponsor);

        // When sync is called again
        $this->sync->sync($synced);

        // Then no new recette was created
        $this->assertCount(1, $this->recetteRepository->forExercice(1));
    }

    /** @test */
    public function it_does_nothing_for_an_unpaid_sponsor(): void
    {
        // Given a sponsor with contrat_paye false
        $sponsor = $this->sponsorRepository->save(
            new Sponsor(null, 1, 'Boulangerie du village', 200.0, '2026-09-07')
        );

        // When sync is called
        $result = $this->sync->sync($sponsor);

        // Then no recette is created
        $this->assertNull($result->recetteId());
        $this->assertCount(0, $this->recetteRepository->forExercice(1));
    }
}
