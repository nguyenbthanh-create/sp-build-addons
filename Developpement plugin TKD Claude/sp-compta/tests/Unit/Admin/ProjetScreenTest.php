<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Admin;

use SpCompta\Admin\ProjetScreen;
use SpCompta\Database;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Projet;
use SpCompta\Entity\Recette;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\ProjetRepository;
use SpCompta\Repository\RecetteRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Admin/ProjetScreen.md
 */
final class ProjetScreenTest extends WP_UnitTestCase
{
    private ProjetScreen $screen;

    private ProjetRepository $projets;

    private DepenseRepository $depenses;

    private RecetteRepository $recettes;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $this->projets = new ProjetRepository($database->tableProjet());
        $this->depenses = new DepenseRepository($database->tableDepense());
        $this->recettes = new RecetteRepository($database->tableRecette());
        $this->screen = new ProjetScreen(
            $this->projets,
            new ExerciceRepository($database->tableExercice()),
            $this->depenses,
            $this->recettes
        );
    }

    /** @test */
    public function it_creates_a_projet_from_the_form_with_a_french_budget(): void
    {
        // Given a form submission with a budget typed the French way
        $request = [
            'exercice_id' => '1', 'nom' => 'Fete de Noel 2026', 'nature' => 'evenement',
            'budget_depenses' => '450,00', 'budget_recettes' => '1 200', 'statut' => 'en_cours',
        ];

        // When the projet is saved
        $projet = $this->screen->saveFromRequest($request);

        // Then it is created with its budget
        $this->assertNotNull($projet);
        $this->assertSame(450.0, $projet->budgetDepenses());
        $this->assertSame(1200.0, $projet->budgetRecettes());
    }

    /** @test */
    public function it_refuses_a_projet_without_a_name(): void
    {
        // Given a submission with an empty name
        // When it is saved
        $projet = $this->screen->saveFromRequest(['exercice_id' => '1', 'nom' => '  ']);

        // Then nothing is created
        $this->assertNull($projet);
        $this->assertSame([], $this->projets->forExercice(1));
    }

    /** @test */
    public function it_falls_back_to_safe_values_for_unknown_nature_and_statut(): void
    {
        // Given a submission with values outside the allowed lists
        $projet = $this->screen->saveFromRequest(['exercice_id' => '1', 'nom' => 'X', 'nature' => 'pirate', 'statut' => 'supprime']);

        // When it is saved
        // Then the nature becomes "autre" and the projet stays "en cours"
        $this->assertSame('autre', $projet->nature());
        $this->assertSame(Projet::STATUT_EN_COURS, $projet->statut());
    }

    /** @test */
    public function it_never_moves_an_existing_projet_to_another_season(): void
    {
        // Given a projet of season 1
        $projet = $this->projets->save(new Projet(null, 1, 'Noel'));

        // When an edit is submitted claiming season 2
        $modifie = $this->screen->saveFromRequest(['id' => (string) $projet->id(), 'exercice_id' => '2', 'nom' => 'Noel modifie']);

        // Then the projet keeps its season
        $this->assertSame(1, $modifie->exerciceId());
    }

    /** @test */
    public function it_attaches_and_detaches_movements_of_the_same_season_only(): void
    {
        // Given a projet of season 1, a depense of season 1 and a recette of season 2
        $projet = $this->projets->save(new Projet(null, 1, 'Noel'));
        $depense = $this->depenses->save(new Depense(null, 1, '2026-12-05', 40.0));
        $autreSaison = $this->recettes->save(new Recette(null, 2, '2027-12-05', 10.0));

        // When both are ticked to be attached
        $this->screen->rattacherFromRequest([
            'projet_id' => (string) $projet->id(),
            'rattacher_depenses' => [(string) $depense->id()],
            'rattacher_recettes' => [(string) $autreSaison->id()],
        ]);

        // Then only the movement of the projet's season is attached
        $this->assertSame($projet->id(), $this->depenses->find((int) $depense->id())->projetId());
        $this->assertNull($this->recettes->find((int) $autreSaison->id())->projetId());

        // And when it is ticked to be detached
        $this->screen->rattacherFromRequest(['projet_id' => (string) $projet->id(), 'detacher_depenses' => [(string) $depense->id()]]);

        // Then it is back in regular operations
        $this->assertNull($this->depenses->find((int) $depense->id())->projetId());
    }

    /** @test */
    public function it_keeps_the_money_when_a_projet_is_deleted(): void
    {
        // Given a projet with an attached depense
        $projet = $this->projets->save(new Projet(null, 1, 'Noel'));
        $depense = $this->depenses->save(new Depense(null, 1, '2026-12-05', 40.0, null, '', null, '', '', '', $projet->id()));

        // When the projet is deleted
        $this->screen->deleteFromRequest(['id' => (string) $projet->id()]);

        // Then the projet is gone but the depense still exists, back in regular operations
        $this->assertNull($this->projets->find((int) $projet->id()));
        $restante = $this->depenses->find((int) $depense->id());
        $this->assertNotNull($restante);
        $this->assertNull($restante->projetId());
        $this->assertSame(40.0, $restante->montant());
    }
}
