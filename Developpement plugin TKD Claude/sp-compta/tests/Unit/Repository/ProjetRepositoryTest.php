<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\Projet;
use SpCompta\Repository\ProjetRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/ProjetRepository.md
 */
final class ProjetRepositoryTest extends WP_UnitTestCase
{
    private ProjetRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new ProjetRepository($database->tableProjet());
    }

    /** @test */
    public function it_saves_and_finds_a_projet_with_its_budget(): void
    {
        // Given a new projet with a planned budget
        $projet = new Projet(null, 1, 'Fete de Noel 2026', 'evenement', 450.0, 120.0, 'Thanh', '2026-12-01', '2026-12-20', 'Gouter et cadeaux');

        // When it is saved then looked up
        $saved = $this->repository->save($projet);
        $found = $this->repository->find((int) $saved->id());

        // Then every field comes back unchanged
        $this->assertNotNull($found);
        $this->assertSame('Fete de Noel 2026', $found->nom());
        $this->assertSame(450.0, $found->budgetDepenses());
        $this->assertSame(120.0, $found->budgetRecettes());
        $this->assertSame('2026-12-01', $found->dateDebut());
        $this->assertSame(Projet::STATUT_EN_COURS, $found->statut());
    }

    /** @test */
    public function it_keeps_empty_dates_as_null(): void
    {
        // Given a projet without dates
        $saved = $this->repository->save(new Projet(null, 1, 'Achat tatamis', 'investissement'));

        // When it is read back
        $found = $this->repository->find((int) $saved->id());

        // Then its dates are null, not an empty string or 0000-00-00
        $this->assertNull($found->dateDebut());
        $this->assertNull($found->dateFin());
    }

    /** @test */
    public function it_lists_only_the_projets_of_the_requested_exercice(): void
    {
        // Given projets in two seasons
        $this->repository->save(new Projet(null, 1, 'Noel'));
        $this->repository->save(new Projet(null, 1, 'Stage'));
        $this->repository->save(new Projet(null, 2, 'Noel suivant'));

        // When the projets of season 1 are requested
        $projets = $this->repository->forExercice(1);

        // Then only its two projets come back, sorted by name
        $this->assertCount(2, $projets);
        $this->assertSame('Noel', $projets[0]->nom());
    }

    /** @test */
    public function it_only_offers_open_projets_for_new_entries(): void
    {
        // Given an open and a closed projet in the same season
        $this->repository->save(new Projet(null, 1, 'En cours'));
        $this->repository->save(new Projet(null, 1, 'Termine', 'evenement', 0.0, 0.0, '', null, null, null, Projet::STATUT_CLOTURE));

        // When the projets offered for entry are requested
        $projets = $this->repository->enCoursPourExercice(1);

        // Then the closed projet is not offered
        $this->assertCount(1, $projets);
        $this->assertSame('En cours', $projets[0]->nom());
    }
}
