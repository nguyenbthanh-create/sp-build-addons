<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Accounting\Categories;
use SpCompta\Capabilities;
use SpCompta\Entity\Depense;
use SpCompta\Entity\Recette;
use SpCompta\Repository\DepenseRepository;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\RecetteRepository;

final class SoldeScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-solde';
    private const TYPES_MOUVEMENT = ['Depense', 'Recette'];

    public function __construct(
        private DepenseRepository $depenseRepository,
        private RecetteRepository $recetteRepository,
        private ExerciceRepository $exerciceRepository
    ) {
    }

    /**
     * Ecran en lecture seule, aucune action admin_post a enregistrer -
     * la methode existe uniquement parce que Plugin::bootAdmin() l'appelle
     * generiquement sur chaque ecran.
     */
    public function registerHooks(): void
    {
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function title(): string
    {
        return 'Solde';
    }

    public function menuLabel(): string
    {
        return 'Solde';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        echo '<div class="wrap"><h1>Solde</h1>';

        $exerciceActif = $this->exerciceRepository->active();

        if ($exerciceActif === null) {
            echo '<p>Aucun exercice actif. Creez et activez un exercice dans la page Parametres.</p></div>';

            return;
        }

        $depenses = $this->depenseRepository->forExercice((int) $exerciceActif->id());
        $recettes = $this->recetteRepository->forExercice((int) $exerciceActif->id());

        $totalDepenses = self::totalMontant($depenses);
        $totalRecettes = self::totalMontant($recettes);
        $solde = self::solde($exerciceActif->soldeInitial(), $totalRecettes, $totalDepenses);

        echo '<table class="widefat"><tbody>';
        echo '<tr><th>Solde initial de l\'exercice</th><td>' . esc_html(number_format($exerciceActif->soldeInitial(), 2)) . '</td></tr>';
        echo '<tr><th>Total recettes</th><td>' . esc_html(number_format($totalRecettes, 2)) . '</td></tr>';
        echo '<tr><th>Total depenses</th><td>' . esc_html(number_format($totalDepenses, 2)) . '</td></tr>';
        echo '<tr><th>Solde actuel</th><td><strong>' . esc_html(number_format($solde, 2)) . '</strong></td></tr>';
        echo '</tbody></table>';

        $this->renderRepartition('Depenses par categorie', self::repartitionHierarchique($depenses, Categories::DEPENSE));
        $this->renderRepartition('Recettes par categorie', self::repartitionHierarchique($recettes, Categories::RECETTE));

        $searchTerm = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $typeFilter = isset($_GET['type']) && in_array($_GET['type'], self::TYPES_MOUVEMENT, true)
            ? $_GET['type']
            : '';
        $this->renderMouvements($depenses, $recettes, $searchTerm, $typeFilter);

        echo '</div>';
    }

    private function renderSearchBox(string $term, string $typeFilter): void
    {
        echo '<form method="get" style="margin:1em 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($term) . '" placeholder="Rechercher un mouvement...">';
        echo ' <select name="type">';
        echo '<option value="">Tous les types</option>';
        foreach (self::TYPES_MOUVEMENT as $type) {
            $selected = $type === $typeFilter ? ' selected' : '';
            echo '<option value="' . esc_attr($type) . '"' . $selected . '>' . esc_html($type) . '</option>';
        }
        echo '</select>';
        echo ' <button type="submit" class="button">Rechercher</button>';
        if ($term !== '' || $typeFilter !== '') {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Reinitialiser</a>';
        }
        echo '</form>';
    }

    /**
     * @param Depense[] $depenses
     * @param Recette[] $recettes
     */
    private function renderMouvements(array $depenses, array $recettes, string $searchTerm, string $typeFilter): void
    {
        echo '<h2>Mouvements de l\'exercice</h2>';
        $this->renderSearchBox($searchTerm, $typeFilter);

        $mouvements = self::filtrerParType(self::mouvements($depenses, $recettes), $typeFilter);

        if ($mouvements === []) {
            echo $typeFilter !== ''
                ? '<p>Aucun mouvement de type "' . esc_html($typeFilter) . '" pour cet exercice.</p>'
                : '<p>Aucun mouvement pour cet exercice.</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Date</th><th>Type</th><th>Categorie</th><th>Montant</th><th>Detail</th>';
        echo '</tr></thead><tbody>';

        foreach ($mouvements as $mouvement) {
            $groupe = $mouvement['type'] === 'Depense' ? Categories::DEPENSE : Categories::RECETTE;
            $categorieLabel = $mouvement['categorie'] !== ''
                ? Categories::libelleCategorie($groupe, $mouvement['categorie'])
                    . ' · ' . Categories::libelleSousCategorie($groupe, $mouvement['categorie'], $mouvement['sous_categorie'])
                : 'Non categorise';

            if (!Search::matches($searchTerm, [
                $mouvement['date'],
                $mouvement['type'],
                $categorieLabel,
                $mouvement['montant'],
                $mouvement['detail'],
            ])) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html($mouvement['date']) . '</td>';
            echo '<td>' . esc_html($mouvement['type']) . '</td>';
            echo '<td>' . esc_html($categorieLabel) . '</td>';
            echo '<td>' . esc_html(number_format($mouvement['montant'], 2)) . '</td>';
            echo '<td>' . esc_html((string) $mouvement['detail']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @param array<string, array{label: string, total: float, sous: array<string, array{label: string, total: float}>}> $repartition
     */
    private function renderRepartition(string $titre, array $repartition): void
    {
        echo '<h2>' . esc_html($titre) . '</h2>';

        if ($repartition === []) {
            echo '<p>Aucune ligne pour cet exercice.</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Categorie</th><th>Total</th></tr></thead><tbody>';

        foreach ($repartition as $categorie) {
            echo '<tr style="background:var(--wp-admin-theme-color-lighter, #f0f0f1);">';
            echo '<th style="text-align:left;">' . esc_html($categorie['label']) . '</th>';
            echo '<td><strong>' . esc_html(number_format($categorie['total'], 2)) . '</strong></td>';
            echo '</tr>';

            foreach ($categorie['sous'] as $sousCategorie) {
                echo '<tr>';
                echo '<td style="padding-left:28px;">' . esc_html($sousCategorie['label']) . '</td>';
                echo '<td>' . esc_html(number_format($sousCategorie['total'], 2)) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /**
     * @param array<int, object{montant: float}> $lignes
     */
    public static function totalMontant(array $lignes): float
    {
        $total = 0.0;

        foreach ($lignes as $ligne) {
            $total += $ligne->montant();
        }

        return $total;
    }

    public static function solde(float $soldeInitial, float $totalRecettes, float $totalDepenses): float
    {
        return $soldeInitial + $totalRecettes - $totalDepenses;
    }

    /**
     * Regroupe les lignes par code categorie CERFA (voir Categories.php),
     * puis par sous-categorie a l'interieur de chaque code, avec un total
     * a chaque niveau - "74 - Subvention d'exploitation" (total du code)
     * puis, en dessous, "Sponsors prives" / "Commune(s)" etc. (total de
     * chaque sous-categorie).
     *
     * @param array<int, object{categorie: string, sousCategorie: string, montant: float}> $lignes
     * @param array<string, array{label: string, sous_categories: array<string, string>}> $groupe
     * @return array<string, array{label: string, total: float, sous: array<string, array{label: string, total: float}>}>
     */
    public static function repartitionHierarchique(array $lignes, array $groupe): array
    {
        $repartition = [];

        foreach ($lignes as $ligne) {
            $code = $ligne->categorie();
            $sousCode = $ligne->sousCategorie();

            if (!isset($repartition[$code])) {
                $repartition[$code] = [
                    'label' => $code !== '' ? Categories::libelleCategorie($groupe, $code) : 'Non categorise',
                    'total' => 0.0,
                    'sous' => [],
                ];
            }

            $repartition[$code]['total'] += $ligne->montant();

            if (!isset($repartition[$code]['sous'][$sousCode])) {
                $repartition[$code]['sous'][$sousCode] = [
                    'label' => $sousCode !== '' ? Categories::libelleSousCategorie($groupe, $code, $sousCode) : 'Non categorise',
                    'total' => 0.0,
                ];
            }

            $repartition[$code]['sous'][$sousCode]['total'] += $ligne->montant();
        }

        return $repartition;
    }

    /**
     * Fusionne depenses et recettes en une liste unique triee par date
     * decroissante, chaque ligne portant son propre montant (positif pour
     * une recette, negatif pour une depense) pour lecture directe en liste.
     *
     * @param Depense[] $depenses
     * @param Recette[] $recettes
     * @return array<int, array{date: string, type: string, categorie: string, sous_categorie: string, montant: float, detail: string}>
     */
    public static function mouvements(array $depenses, array $recettes): array
    {
        $mouvements = [];

        foreach ($depenses as $depense) {
            $mouvements[] = [
                'date' => $depense->date(),
                'type' => 'Depense',
                'categorie' => $depense->categorie(),
                'sous_categorie' => $depense->sousCategorie(),
                'montant' => -$depense->montant(),
                'detail' => (string) $depense->detail(),
            ];
        }

        foreach ($recettes as $recette) {
            $mouvements[] = [
                'date' => $recette->date(),
                'type' => 'Recette',
                'categorie' => $recette->categorie(),
                'sous_categorie' => $recette->sousCategorie(),
                'montant' => $recette->montant(),
                'detail' => (string) $recette->detail(),
            ];
        }

        usort($mouvements, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $mouvements;
    }

    /**
     * Ne garde que les mouvements du type demande ("Depense" ou "Recette").
     * $type vide = aucun filtre, tous les mouvements sont gardes.
     *
     * @param array<int, array{date: string, type: string, categorie: string, sous_categorie: string, montant: float, detail: string}> $mouvements
     * @return array<int, array{date: string, type: string, categorie: string, sous_categorie: string, montant: float, detail: string}>
     */
    public static function filtrerParType(array $mouvements, string $type): array
    {
        if ($type === '') {
            return $mouvements;
        }

        return array_values(array_filter(
            $mouvements,
            static fn (array $mouvement): bool => $mouvement['type'] === $type
        ));
    }
}
