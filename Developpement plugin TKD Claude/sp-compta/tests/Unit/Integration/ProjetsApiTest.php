<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Integration;

use SpCompta\Admin\DepenseScreen;
use SpCompta\Capabilities;
use SpCompta\Database;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Exercice;
use SpCompta\Integration\ProjetsApi;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Repository\BudgetLigneRepository;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FournisseurRepository;
use SpCompta\Repository\ProjetRepository;
use SpCompta\Repository\RecetteRepository;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Integration/ProjetsApi.md
 */
final class ProjetsApiTest extends WP_UnitTestCase
{
    private ProjetsApi $api;

    private ProjetRepository $projets;

    private DepenseRepository $depenses;

    private int $saison;

    public function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $database->createTables();

        $exercices = new ExerciceRepository($database->tableExercice());
        $this->saison = (int) $exercices->save(new Exercice(null, '2026-09-01', '2027-08-31', 0.0, true))->id();
        $this->projets = new ProjetRepository($database->tableProjet());
        $this->depenses = new DepenseRepository($database->tableDepense());
        $this->api = new ProjetsApi(
            $this->projets,
            new BudgetLigneRepository($database->tableBudgetLigne()),
            $exercices,
            $this->depenses,
            new RecetteRepository($database->tableRecette()),
            new DepenseScreen($this->depenses, $exercices, new FournisseurRepository($database->tableFournisseur()), new AttachmentUploader(), $this->projets)
        );

        $bureau = self::factory()->user->create(['role' => 'editor']);
        get_userdata($bureau)->add_cap(Capabilities::MANAGE_COMPTA);
        wp_set_current_user($bureau);
    }

    /** @test */
    public function it_refuses_everything_outside_the_bureau(): void
    {
        // Given a connected account without sp_compta_manager
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        // Then reading a bilan is refused
        $this->assertFalse($this->api->peutConsulter());
        $this->expectException(\RuntimeException::class);

        // When it is asked for
        $this->api->bilan(1);
    }

    /** @test */
    public function it_creates_a_projet_in_the_requested_season_without_budget(): void
    {
        // Given the season containing the event date
        $saison = $this->api->saisonPourDate('2026-12-19');

        // When a projet is created through the API
        $id = $this->api->enregistrerProjet(null, ['nom' => 'Fete de Noel', 'nature' => 'evenement', 'exercice_id' => $saison]);

        // Then it belongs to that season and has no budget yet
        $projet = $this->projets->find($id);
        $this->assertSame($this->saison, $projet->exerciceId());
        $this->assertFalse($projet->aUnBudget());
    }

    /** @test */
    public function it_never_moves_an_existing_projet_to_another_season(): void
    {
        // Given an existing projet
        $id = $this->api->enregistrerProjet(null, ['nom' => 'Gala', 'exercice_id' => $this->saison]);

        // When it is updated with another season
        $this->api->enregistrerProjet($id, ['nom' => 'Gala 2027', 'exercice_id' => 999]);

        // Then its name changes but not its season
        $this->assertSame('Gala 2027', $this->projets->find($id)->nom());
        $this->assertSame($this->saison, $this->projets->find($id)->exerciceId());
    }

    /** @test */
    public function it_updates_the_projet_totals_from_its_lines(): void
    {
        // Given a projet
        $id = $this->api->enregistrerProjet(null, ['nom' => 'Gala', 'exercice_id' => $this->saison]);

        // When budget lines are saved
        $this->api->enregistrerLignesBudget($id, [
            ['sens' => 'depense', 'categorie' => '61', 'libelle' => 'Salle', 'montant' => '300'],
            ['sens' => 'depense', 'categorie' => '60', 'libelle' => 'Trophees', 'montant' => '150,50'],
            ['sens' => 'recette', 'categorie' => '70', 'libelle' => 'Repas', 'montant' => '400'],
        ]);

        // Then the projet totals are the sums of the lines
        $projet = $this->projets->find($id);
        $this->assertSame(450.5, $projet->budgetDepenses());
        $this->assertSame(400.0, $projet->budgetRecettes());
        $this->assertCount(3, $this->api->lignesBudget($id));
    }

    /** @test */
    public function it_computes_the_bilan_by_category(): void
    {
        // Given a projet with a rental line and a matching expense
        $id = $this->api->enregistrerProjet(null, ['nom' => 'Gala', 'exercice_id' => $this->saison]);
        $this->api->enregistrerLignesBudget($id, [['sens' => 'depense', 'categorie' => '61', 'libelle' => 'Salle', 'montant' => '300']]);
        $this->depenses->save(new Depense(null, $this->saison, '2027-01-10', 280.0, null, '61', 'Acompte salle', '', '', '', $id));

        // When the bilan is read
        $bilan = $this->api->bilan($id);

        // Then totals and the category line agree
        $this->assertSame(280.0, $bilan['totaux']['depenses']);
        $this->assertSame(-20.0, $bilan['totaux']['ecart_depenses']);
        $this->assertSame(300.0, $bilan['categories'][0]['prevu']);
        $this->assertSame(280.0, $bilan['categories'][0]['realise']);
        $this->assertSame(-280.0, $this->api->mouvements($id)[0]['montant']);
    }
    /** @test */
    public function it_records_a_depense_validated_by_the_president(): void
    {
        // Given a projet of the season
        $id = $this->api->enregistrerProjet(null, ['nom' => 'Gala', 'exercice_id' => $this->saison]);

        // When the president validates a 45,90 EUR purchase of trophies
        $depense = $this->api->enregistrerDepense(
            $id,
            ['date' => '2027-01-15', 'montant' => '45,90', 'sous_categorie' => 'petit_equipement', 'detail' => 'Trophees', 'mode_paiement' => 'CB'],
            [],
            'Noe'
        );

        // Then it is in the treasury, attached to the projet, in category 60, with who validated it
        $enregistree = $this->depenses->find($depense['id']);
        $this->assertSame($id, $enregistree->projetId());
        $this->assertSame(45.9, $enregistree->montant());
        $this->assertSame('60', $enregistree->categorie());
        $this->assertSame($this->saison, $enregistree->exerciceId());
        $this->assertStringContainsString('validée par Noe', (string) $enregistree->detail());
    }

    /** @test */
    public function it_refuses_a_depense_outside_the_season_of_the_projet(): void
    {
        // Given a projet of the 2026-2027 season
        $id = $this->api->enregistrerProjet(null, ['nom' => 'Gala', 'exercice_id' => $this->saison]);

        // Then a depense dated in the next season is refused
        $this->expectException(\InvalidArgumentException::class);

        // When it is recorded
        $this->api->enregistrerDepense($id, ['date' => '2027-09-15', 'montant' => '10', 'sous_categorie' => 'petit_equipement']);
    }

    /** @test */
    public function it_refuses_a_depense_on_a_closed_projet(): void
    {
        // Given a closed projet
        $id = $this->api->enregistrerProjet(null, ['nom' => 'Gala', 'exercice_id' => $this->saison, 'statut' => 'cloture']);

        // Then a new depense is refused
        $this->expectException(\InvalidArgumentException::class);

        // When it is recorded
        $this->api->enregistrerDepense($id, ['date' => '2027-01-15', 'montant' => '10', 'sous_categorie' => 'petit_equipement']);
    }
}
