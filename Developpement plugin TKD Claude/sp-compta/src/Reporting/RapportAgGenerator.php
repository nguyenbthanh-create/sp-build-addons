<?php

declare(strict_types=1);

namespace SpCompta\Reporting;

use SpCompta\Entity\Exercice;
use SpCompta\Entity\Parametres;
use SpCompta\Entity\Sponsor;

/**
 * Genere la page HTML imprimable du "rapport financier" presentable en
 * assemblee generale - meme principe que le recu PDF/HTML de tkd-cotisations
 * (une page autonome avec un bouton "Imprimer / PDF", pas de dependance a
 * une librairie externe). Classe volontairement pure (aucun acces base de
 * donnees ni WordPress au-dela des fonctions d'echappement) : tous les
 * chiffres sont deja calcules par l'appelant (voir RapportExerciceScreen),
 * ce qui la rend testable sans fixture de base de donnees.
 */
final class RapportAgGenerator
{
    /**
     * @param array<string, array{label: string, total: float, sous: array<string, array{label: string, total: float}>}> $repartitionDepenses
     * @param array<string, array{label: string, total: float, sous: array<string, array{label: string, total: float}>}> $repartitionRecettes
     * @param Sponsor[] $sponsors
     */
    public static function render(
        Exercice $exercice,
        float $totalRecettes,
        float $totalDepenses,
        float $solde,
        array $repartitionDepenses,
        array $repartitionRecettes,
        array $sponsors,
        ?string $labelExercicePrecedent,
        ?float $totalRecettesPrecedent,
        ?float $totalDepensesPrecedent,
        ?Parametres $parametres
    ): string {
        $nomAssociation = $parametres !== null && $parametres->nomAssociation() !== ''
            ? $parametres->nomAssociation()
            : get_bloginfo('name');
        $resultatNet = $totalRecettes - $totalDepenses;
        $labelActuel = self::formatPeriode($exercice);
        $genereLe = date_i18n('d/m/Y');

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
        $html .= '<title>Rapport financier — ' . esc_html($labelActuel) . '</title>';
        $html .= '<style>' . self::css() . '</style></head><body>';

        $html .= '<div class="no-print" style="text-align:center;padding:14px;background:#1e3a5f;">';
        $html .= '<button onclick="window.print()" style="background:#fff;color:#1e3a5f;border:none;border-radius:7px;padding:9px 22px;font-size:14px;font-weight:700;cursor:pointer;">🖨️ Imprimer / PDF</button>';
        $html .= '</div>';

        $html .= '<div class="wrap">';
        $html .= '<div class="header"><div class="club">' . esc_html(strtoupper($nomAssociation)) . '</div>';
        $html .= '<h1>Rapport financier</h1><p>Exercice ' . esc_html($labelActuel) . ' — présenté en Assemblée Générale</p></div>';

        $html .= '<div class="body">';

        $html .= '<div class="resume">';
        $html .= self::ligneResume('Solde initial', $exercice->soldeInitial());
        $html .= self::ligneResume('Total recettes', $totalRecettes);
        $html .= self::ligneResume('Total dépenses', $totalDepenses);
        $html .= self::ligneResume('Résultat net de l\'exercice', $resultatNet, true);
        $html .= self::ligneResume('Solde de clôture', $solde, true);
        $html .= '</div>';

        if ($totalRecettesPrecedent !== null && $totalDepensesPrecedent !== null && $labelExercicePrecedent !== null) {
            $html .= '<h2>Évolution par rapport à l\'exercice précédent</h2>';
            $html .= '<div class="chart">' . self::svgComparaisonBarres(
                $labelExercicePrecedent,
                $totalRecettesPrecedent,
                $totalDepensesPrecedent,
                $labelActuel,
                $totalRecettes,
                $totalDepenses
            ) . '</div>';
        } else {
            $html .= '<p class="note">Aucun exercice antérieur enregistré : pas de comparaison possible cette année.</p>';
        }

        $html .= self::repartitionTable('Recettes par catégorie', $repartitionRecettes);
        $html .= self::repartitionTable('Dépenses par catégorie', $repartitionDepenses);

        if ($sponsors !== []) {
            $html .= self::sponsorsTable($sponsors);
        }

        $html .= '<p class="fait">Document généré le ' . esc_html($genereLe) . ' — à usage de présentation, ne remplace pas les pièces comptables.</p>';
        $html .= '</div></div></body></html>';

        return $html;
    }

    private static function formatPeriode(Exercice $exercice): string
    {
        $debut = date_create($exercice->dateDebut());
        $fin = date_create($exercice->dateFin());

        if ($debut === false || $fin === false) {
            return $exercice->dateDebut() . ' au ' . $exercice->dateFin();
        }

        return $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y');
    }

    private static function ligneResume(string $label, float $montant, bool $accent = false): string
    {
        $classe = $accent ? ' class="accent"' : '';

        return '<div class="resume-ligne"' . $classe . '><span>' . esc_html($label) . '</span>'
            . '<strong>' . esc_html(number_format($montant, 2, ',', ' ')) . ' €</strong></div>';
    }

    /**
     * @param array<string, array{label: string, total: float, sous: array<string, array{label: string, total: float}>}> $repartition
     */
    private static function repartitionTable(string $titre, array $repartition): string
    {
        if ($repartition === []) {
            return '';
        }

        $html = '<h2>' . esc_html($titre) . '</h2><table class="tbl"><tbody>';

        foreach ($repartition as $categorie) {
            $html .= '<tr class="categorie"><td>' . esc_html($categorie['label']) . '</td>'
                . '<td>' . esc_html(number_format($categorie['total'], 2, ',', ' ')) . ' €</td></tr>';

            foreach ($categorie['sous'] as $sousCategorie) {
                $html .= '<tr><td class="sous">' . esc_html($sousCategorie['label']) . '</td>'
                    . '<td>' . esc_html(number_format($sousCategorie['total'], 2, ',', ' ')) . ' €</td></tr>';
            }
        }

        return $html . '</tbody></table>';
    }

    /**
     * @param Sponsor[] $sponsors
     */
    private static function sponsorsTable(array $sponsors): string
    {
        $html = '<h2>Sponsors</h2><table class="tbl"><tbody>';
        $total = 0.0;

        foreach ($sponsors as $sponsor) {
            $html .= '<tr><td>' . esc_html($sponsor->nom()) . '</td>'
                . '<td>' . esc_html(number_format($sponsor->montant(), 2, ',', ' ')) . ' €</td></tr>';
            $total += $sponsor->montant();
        }

        $html .= '<tr class="categorie"><td>Total sponsors</td><td>' . esc_html(number_format($total, 2, ',', ' ')) . ' €</td></tr>';

        return $html . '</tbody></table>';
    }

    /**
     * Diagramme en barres sans dependance externe (SVG genere en PHP) :
     * compare recettes/depenses de l'exercice precedent (si connu) a celles
     * de l'exercice en cours. Pure fonction de calcul, testable directement.
     */
    public static function svgComparaisonBarres(
        string $labelPrecedent,
        float $recettesPrecedent,
        float $depensesPrecedent,
        string $labelActuel,
        float $recettesActuel,
        float $depensesActuel
    ): string {
        $barres = [
            ['label' => 'Recettes ' . $labelPrecedent, 'valeur' => $recettesPrecedent, 'couleur' => '#94a3b8'],
            ['label' => 'Dépenses ' . $labelPrecedent, 'valeur' => $depensesPrecedent, 'couleur' => '#cbd5e1'],
            ['label' => 'Recettes ' . $labelActuel, 'valeur' => $recettesActuel, 'couleur' => '#1e3a5f'],
            ['label' => 'Dépenses ' . $labelActuel, 'valeur' => $depensesActuel, 'couleur' => '#b45309'],
        ];

        $max = max(array_column($barres, 'valeur'));
        $max = $max > 0.0 ? $max : 1.0;

        $largeurBarre = 80;
        $espace = 30;
        $hauteurZone = 150.0;
        $baseY = 185;
        $largeurTotale = count($barres) * ($largeurBarre + $espace) + $espace;

        $svg = '<svg viewBox="0 0 ' . $largeurTotale . ' 225" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Comparaison recettes et depenses">';

        $x = $espace;
        foreach ($barres as $barre) {
            $hauteur = ($barre['valeur'] / $max) * $hauteurZone;
            $y = $baseY - $hauteur;
            $svg .= '<rect x="' . $x . '" y="' . round($y, 1) . '" width="' . $largeurBarre . '" height="' . round($hauteur, 1)
                . '" fill="' . $barre['couleur'] . '" rx="4"></rect>';
            $svg .= '<text x="' . ($x + $largeurBarre / 2) . '" y="' . round($y - 6, 1)
                . '" font-size="12" font-weight="700" text-anchor="middle" fill="#111">'
                . esc_html(number_format($barre['valeur'], 0, ',', ' ')) . ' €</text>';
            $svg .= '<text x="' . ($x + $largeurBarre / 2) . '" y="' . ($baseY + 18)
                . '" font-size="11" text-anchor="middle" fill="#374151">' . esc_html($barre['label']) . '</text>';
            $x += $largeurBarre + $espace;
        }

        $svg .= '<line x1="' . ($espace / 2) . '" y1="' . $baseY . '" x2="' . ($largeurTotale - $espace / 2) . '" y2="' . $baseY
            . '" stroke="#e2e8f0" stroke-width="1"></line>';

        return $svg . '</svg>';
    }

    private static function css(): string
    {
        return '*{box-sizing:border-box;margin:0;padding:0}'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:#f1f5f9;}'
            . '.wrap{max-width:720px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);}'
            . '.header{background:#1e3a5f;padding:28px 40px;text-align:center;}'
            . '.header .club{color:rgba(255,255,255,.6);font-size:12px;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;}'
            . '.header h1{color:#fff;font-size:22px;font-weight:800;}'
            . '.header p{color:rgba(255,255,255,.75);font-size:13px;margin-top:6px;}'
            . '.body{padding:32px 40px;}'
            . '.resume{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:8px 20px;margin-bottom:24px;}'
            . '.resume-ligne{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#374151;}'
            . '.resume-ligne:last-child{border-bottom:none;}'
            . '.resume-ligne.accent{color:#1e3a5f;font-weight:700;}'
            . 'h2{font-size:15px;color:#1e3a5f;margin:24px 0 10px;}'
            . '.chart{text-align:center;overflow-x:auto;}'
            . '.note{font-size:13px;color:#6b7280;font-style:italic;}'
            . 'table.tbl{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:8px;}'
            . 'table.tbl td{padding:7px 10px;border-bottom:1px solid #e2e8f0;}'
            . 'table.tbl tr.categorie td{font-weight:700;background:#f8fafc;}'
            . 'table.tbl td.sous{padding-left:26px;color:#4b5563;}'
            . 'table.tbl td:last-child{text-align:right;white-space:nowrap;}'
            . '.fait{font-size:11px;color:#94a3b8;text-align:center;margin-top:28px;font-style:italic;}'
            . '@media print{ * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } '
            . 'body{background:#fff;} .wrap{box-shadow:none;margin:0;border-radius:0;max-width:100%;} .no-print{display:none!important;} }';
    }
}
