<?php

declare(strict_types=1);

namespace SpCompta;

use SpCompta\Admin\ClientScreen;
use SpCompta\Admin\DepenseScreen;
use SpCompta\Admin\FournisseurScreen;
use SpCompta\Admin\Menu;
use SpCompta\Admin\ParametresScreen;
use SpCompta\Admin\RecetteScreen;
use SpCompta\Admin\SoldeScreen;
use SpCompta\Admin\SponsorScreen;
use SpCompta\Billing\ExerciceDeletionGuard;
use SpCompta\Billing\SponsorPaiementSync;
use SpCompta\Front\SaisieRapideShortcode;
use SpCompta\Front\ServiceWorker;
use SpCompta\Media\AttachmentUploader;
use SpCompta\Numbering\DocumentNumeroGenerator;
use SpCompta\Numbering\SequenceGenerator;
use SpCompta\Repository\ClientRepository;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\DevisRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FactureRepository;
use SpCompta\Repository\FournisseurRepository;
use SpCompta\Repository\ParametresRepository;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;

final class Plugin
{
    private static ?Plugin $instance = null;

    private Database $database;

    private Capabilities $capabilities;

    private function __construct()
    {
        $this->database = new Database();
        $this->capabilities = new Capabilities();
    }

    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        self::getInstance()->database->createTables();
    }

    public function boot(): void
    {
        add_action('init', [$this->capabilities, 'register']);

        $this->bootFront();

        if (is_admin()) {
            add_action('admin_init', [$this->database, 'maybeUpgrade']);
            $this->bootAdmin();
        }
    }

    /**
     * Cable ce qui doit reagir sur le front-end public (shortcodes, hooks
     * wp_head...), donc appele sur toute requete, pas seulement en admin -
     * contrairement a bootAdmin(), qui ne construit que ce qui sert aux
     * ecrans wp-admin.
     */
    private function bootFront(): void
    {
        $depenseRepository = new DepenseRepository($this->database->tableDepense());
        $exerciceRepository = new ExerciceRepository($this->database->tableExercice());
        $fournisseurRepository = new FournisseurRepository($this->database->tableFournisseur());

        $depenseScreen = new DepenseScreen(
            $depenseRepository,
            $exerciceRepository,
            $fournisseurRepository,
            new AttachmentUploader()
        );

        $saisieRapide = new SaisieRapideShortcode($depenseScreen, $exerciceRepository, $fournisseurRepository);
        $saisieRapide->registerHooks();

        (new ServiceWorker())->registerHooks();
    }

    /**
     * Cable les ecrans wp-admin. Un ecran de plus = une ligne de plus ici
     * (instanciation du repository + de l'ecran + registerHooks()) et une
     * entree de plus dans le tableau passe a Menu.
     */
    private function bootAdmin(): void
    {
        $fournisseurRepository = new FournisseurRepository($this->database->tableFournisseur());
        $clientRepository = new ClientRepository($this->database->tableClient());
        $exerciceRepository = new ExerciceRepository($this->database->tableExercice());
        $depenseRepository = new DepenseRepository($this->database->tableDepense());
        $recetteRepository = new RecetteRepository($this->database->tableRecette());
        $sponsorRepository = new SponsorRepository($this->database->tableSponsor());

        $sequences = new SequenceGenerator($this->database->tableSequence());
        $devisRepository = new DevisRepository(
            $this->database->tableDevis(),
            $this->database->tableDevisLigne(),
            new DocumentNumeroGenerator($sequences, 'D')
        );
        $factureRepository = new FactureRepository(
            $this->database->tableFacture(),
            $this->database->tableFactureLigne(),
            new DocumentNumeroGenerator($sequences, 'F')
        );

        $exerciceDeletionGuard = new ExerciceDeletionGuard(
            $exerciceRepository,
            $depenseRepository,
            $recetteRepository,
            $sponsorRepository,
            $devisRepository,
            $factureRepository
        );

        $attachmentUploader = new AttachmentUploader();

        $screens = [
            new DepenseScreen($depenseRepository, $exerciceRepository, $fournisseurRepository, $attachmentUploader),
            new RecetteScreen($recetteRepository, $exerciceRepository, $clientRepository, $attachmentUploader),
            new SponsorScreen(
                $sponsorRepository,
                $exerciceRepository,
                new SponsorPaiementSync($sponsorRepository, $recetteRepository),
                $attachmentUploader
            ),
            new SoldeScreen($depenseRepository, $recetteRepository, $exerciceRepository),
            new ClientScreen($clientRepository),
            new FournisseurScreen($fournisseurRepository),
            new ParametresScreen(
                new ParametresRepository($this->database->tableParametres()),
                $exerciceRepository,
                $this->capabilities,
                $exerciceDeletionGuard
            ),
        ];

        foreach ($screens as $screen) {
            $screen->registerHooks();
        }

        $menu = new Menu($screens);
        $menu->register();
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function capabilities(): Capabilities
    {
        return $this->capabilities;
    }
}
