<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Accounting\Categories;
use SpCompta\Capabilities;
use SpCompta\Entity\Exercice;
use SpCompta\Repository\ClientRepository;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\DevisRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FactureRepository;
use SpCompta\Repository\FournisseurRepository;
use SpCompta\Repository\ParametresRepository;
use SpCompta\Repository\RecetteRepository;
use SpCompta\Repository\SponsorRepository;
use SpCompta\Reporting\ExerciceExportateur;
use SpCompta\Reporting\MouvementsCsvExportateur;
use SpCompta\Reporting\RapportAgGenerator;

/**
 * Ecran "Rapport / Export" : pour chaque exercice, deux exports distincts -
 * une sauvegarde brute (JSON complet, voir ExerciceExportateur) et un
 * rapport presentable pour l'Assemblee Generale (HTML imprimable avec
 * comparaison a l'exercice precedent, voir RapportAgGenerator). Ecran
 * demande le 11/09/2026 suite a un etat des lieux utilisateur constatant
 * l'absence de toute fonctionnalite d'export.
 */
final class RapportExerciceScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-rapport';
    private const ACTION_EXPORT_JSON = 'sp_compta_export_json';
    private const ACTION_EXPORT_CSV = 'sp_compta_export_csv';
    private const ACTION_RAPPORT_AG = 'sp_compta_rapport_ag';
    private const NONCE = 'sp_compta_rapport_nonce';

    public function __construct(
        private ExerciceRepository $exerciceRepository,
        private DepenseRepository $depenseRepository,
        private RecetteRepository $recetteRepository,
        private SponsorRepository $sponsorRepository,
        private DevisRepository $devisRepository,
        private FactureRepository $factureRepository,
        private ClientRepository $clientRepository,
        private FournisseurRepository $fournisseurRepository,
        private ParametresRepository $parametresRepository
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_EXPORT_JSON, [$this, 'handleExportJson']);
        add_action('admin_post_' . self::ACTION_EXPORT_CSV, [$this, 'handleExportCsv']);
        add_action('admin_post_' . self::ACTION_RAPPORT_AG, [$this, 'handleRapportAg']);
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function title(): string
    {
        return 'Rapport et export';
    }

    public function menuLabel(): string
    {
        return 'Rapport / Export';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        echo '<div class="wrap"><h1>Rapport et export</h1>';
        echo '<p>Pour chaque exercice : une <strong>sauvegarde brute</strong> (fichier JSON complet, a garder de cote), '
            . 'un <strong>export CSV des mouvements</strong> (a ouvrir dans Excel, pour l\'archivage) '
            . 'et un <strong>rapport financier presentable</strong> (page imprimable / PDF, avec comparaison a l\'exercice precedent), '
            . 'pense pour etre communique en Assemblee Generale.</p>';

        $exercices = $this->exerciceRepository->all();

        if ($exercices === []) {
            echo '<p>Aucun exercice enregistre.</p></div>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Periode</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';

        foreach ($exercices as $exercice) {
            $exportUrl = wp_nonce_url(
                add_query_arg(
                    ['action' => self::ACTION_EXPORT_JSON, 'exercice_id' => $exercice->id()],
                    admin_url('admin-post.php')
                ),
                self::NONCE
            );
            $csvUrl = wp_nonce_url(
                add_query_arg(
                    ['action' => self::ACTION_EXPORT_CSV, 'exercice_id' => $exercice->id()],
                    admin_url('admin-post.php')
                ),
                self::NONCE
            );
            $rapportUrl = wp_nonce_url(
                add_query_arg(
                    ['action' => self::ACTION_RAPPORT_AG, 'exercice_id' => $exercice->id()],
                    admin_url('admin-post.php')
                ),
                self::NONCE
            );

            echo '<tr>';
            echo '<td>' . esc_html($exercice->dateDebut()) . ' au ' . esc_html($exercice->dateFin()) . '</td>';
            echo '<td>' . ($exercice->actif() ? '<strong>Actif</strong>' : 'Cloture') . '</td>';
            echo '<td>';
            echo '<a class="button" href="' . esc_url($exportUrl) . '">Sauvegarde brute (JSON)</a> ';
            echo '<a class="button" href="' . esc_url($csvUrl) . '">Export CSV (mouvements)</a> ';
            echo '<a class="button button-primary" href="' . esc_url($rapportUrl) . '" target="_blank">Rapport AG</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    public function handleExportJson(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $exercice = $this->exerciceRepository->find((int) ($_GET['exercice_id'] ?? 0));

        if ($exercice === null) {
            wp_die(esc_html__('Exercice introuvable.', 'sp-compta'));
        }

        $exerciceId = (int) $exercice->id();

        $donnees = ExerciceExportateur::toArray(
            $exercice,
            $this->depenseRepository->forExercice($exerciceId),
            $this->recetteRepository->forExercice($exerciceId),
            $this->sponsorRepository->forExercice($exerciceId),
            $this->devisRepository->forExercice($exerciceId),
            $this->factureRepository->forExercice($exerciceId),
            $this->clientRepository->all(),
            $this->fournisseurRepository->all()
        );

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sp-compta-exercice-' . $exerciceId . '.json"');
        echo (string) wp_json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function handleExportCsv(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $exercice = $this->exerciceRepository->find((int) ($_GET['exercice_id'] ?? 0));

        if ($exercice === null) {
            wp_die(esc_html__('Exercice introuvable.', 'sp-compta'));
        }

        $exerciceId = (int) $exercice->id();

        $csv = MouvementsCsvExportateur::toCsv(
            $this->depenseRepository->forExercice($exerciceId),
            $this->recetteRepository->forExercice($exerciceId),
            $this->fournisseurRepository->all(),
            $this->clientRepository->all()
        );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sp-compta-mouvements-' . $exerciceId . '.csv"');
        echo $csv;
        exit;
    }

    public function handleRapportAg(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $exercice = $this->exerciceRepository->find((int) ($_GET['exercice_id'] ?? 0));

        if ($exercice === null) {
            wp_die(esc_html__('Exercice introuvable.', 'sp-compta'));
        }

        $exerciceId = (int) $exercice->id();
        $depenses = $this->depenseRepository->forExercice($exerciceId);
        $recettes = $this->recetteRepository->forExercice($exerciceId);

        $totalDepenses = SoldeScreen::totalMontant($depenses);
        $totalRecettes = SoldeScreen::totalMontant($recettes);
        $solde = SoldeScreen::solde($exercice->soldeInitial(), $totalRecettes, $totalDepenses);

        $exercicePrecedent = $this->exercicePrecedent($exerciceId, $this->exerciceRepository->all());
        $labelPrecedent = null;
        $totalRecettesPrecedent = null;
        $totalDepensesPrecedent = null;

        if ($exercicePrecedent !== null) {
            $idPrecedent = (int) $exercicePrecedent->id();
            $depensesPrecedent = $this->depenseRepository->forExercice($idPrecedent);
            $recettesPrecedent = $this->recetteRepository->forExercice($idPrecedent);
            $labelPrecedent = $exercicePrecedent->dateDebut() . ' - ' . $exercicePrecedent->dateFin();
            $totalDepensesPrecedent = SoldeScreen::totalMontant($depensesPrecedent);
            $totalRecettesPrecedent = SoldeScreen::totalMontant($recettesPrecedent);
        }

        $html = RapportAgGenerator::render(
            $exercice,
            $totalRecettes,
            $totalDepenses,
            $solde,
            SoldeScreen::repartitionHierarchique($depenses, Categories::DEPENSE),
            SoldeScreen::repartitionHierarchique($recettes, Categories::RECETTE),
            $this->sponsorRepository->forExercice($exerciceId),
            $labelPrecedent,
            $totalRecettesPrecedent,
            $totalDepensesPrecedent,
            $this->parametresRepository->get()
        );

        echo $html;
        exit;
    }

    /**
     * @param Exercice[] $tousLesExercices Trie par date_debut decroissante (voir ExerciceRepository::all())
     */
    private function exercicePrecedent(int $exerciceId, array $tousLesExercices): ?Exercice
    {
        foreach ($tousLesExercices as $index => $candidat) {
            if ($candidat->id() === $exerciceId) {
                return $tousLesExercices[$index + 1] ?? null;
            }
        }

        return null;
    }
}
