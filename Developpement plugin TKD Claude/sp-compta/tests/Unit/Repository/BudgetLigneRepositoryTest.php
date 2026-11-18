<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Repository;

use SpCompta\Database;
use SpCompta\Entity\BudgetLigne;
use SpCompta\Repository\BudgetLigneRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Repository/BudgetLigneRepository.md
 */
final class BudgetLigneRepositoryTest extends WP_UnitTestCase
{
    private BudgetLigneRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->repository = new BudgetLigneRepository($database->tableBudgetLigne());
    }

    /** @test */
    public function it_reads_back_lines_in_the_same_order(): void
    {
        // Given two lines for projet 7
        $lignes = [
            new BudgetLigne(null, 7, 'depense', '61', 'Salle', 300.0),
            new BudgetLigne(null, 7, 'recette', '70', 'Buvette', 120.5),
        ];

        // When they are saved then read
        $this->repository->remplacer(7, $lignes);
        $lues = $this->repository->forProjet(7);

        // Then they come back unchanged, in order
        $this->assertCount(2, $lues);
        $this->assertSame('Salle', $lues[0]->libelle());
        $this->assertSame(300.0, $lues[0]->montant());
        $this->assertSame('recette', $lues[1]->sens());
        $this->assertSame(120.5, $lues[1]->montant());
    }

    /** @test */
    public function it_replaces_previous_lines(): void
    {
        // Given projet 7 already has a line
        $this->repository->remplacer(7, [new BudgetLigne(null, 7, 'depense', '60', 'Ancienne', 10.0)]);

        // When its lines are replaced
        $this->repository->remplacer(7, [new BudgetLigne(null, 7, 'depense', '60', 'Nouvelle', 20.0)]);

        // Then only the new line remains
        $lues = $this->repository->forProjet(7);
        $this->assertCount(1, $lues);
        $this->assertSame('Nouvelle', $lues[0]->libelle());
    }

    /** @test */
    public function it_leaves_other_projets_untouched(): void
    {
        // Given lines for projets 7 and 8
        $this->repository->remplacer(8, [new BudgetLigne(null, 8, 'depense', '60', 'Autre projet', 50.0)]);
        $this->repository->remplacer(7, [new BudgetLigne(null, 7, 'depense', '60', 'Projet 7', 10.0)]);

        // When projet 7's lines are deleted
        $this->repository->deleteForProjet(7);

        // Then projet 8 keeps its line
        $this->assertSame([], $this->repository->forProjet(7));
        $this->assertCount(1, $this->repository->forProjet(8));
    }
}
