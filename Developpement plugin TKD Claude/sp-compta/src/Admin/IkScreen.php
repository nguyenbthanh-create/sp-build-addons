<?php

declare(strict_types=1);

namespace SpCompta\Admin;

use SpCompta\Billing\IkPaiementSync;
use SpCompta\Capabilities;
use SpCompta\Integration\SpBuildReader;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\IkPaiementRepository;

/**
 * Ecran "Indemnites Km" : vue tresorerie des indemnites kilometriques des entraineurs (donnees
 * calculees a la volee depuis sp_build via SpBuildReader, jamais figees en base ici) avec, pour
 * chaque entraineur/mois, une case a cocher "paye" qui bascule automatiquement le montant en
 * Depense (voir IkPaiementSync). Une ligne n'apparait que si l'entraineur a eu au moins une
 * intervention ou un km exceptionnel ce mois-la (meme exclusion que l'email recapitulatif
 * mensuel de sp_build, pour eviter d'afficher des lignes a 0).
 */
final class IkScreen implements AdminScreen
{
    private const SLUG = 'sp-compta-ik';
    private const ACTION_TOGGLE = 'sp_compta_toggle_ik_paye';
    private const NONCE = 'sp_compta_ik_nonce';

    private const MOIS_LABELS = [
        1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Aout', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
    ];

    public function __construct(
        private SpBuildReader $spBuildReader,
        private IkPaiementRepository $ikRepository,
        private IkPaiementSync $ikSync,
        private ExerciceRepository $exerciceRepository
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_TOGGLE, [$this, 'handleTogglePaye']);
    }

    public function slug(): string
    {
        return self::SLUG;
    }

    public function title(): string
    {
        return 'Indemnites Km';
    }

    public function menuLabel(): string
    {
        return 'Indemnites Km';
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        echo '<div class="wrap"><h1>Indemnites kilometriques des entraineurs</h1>';

        if (!$this->spBuildReader->disponible()) {
            echo '<p>Le plugin sp_build (calendrier/entraineurs) n\'est pas actif ou n\'a pas encore ses tables — ';
            echo 'ce module a besoin de lui pour recuperer les entraineurs, leur km et le nombre d\'interventions.</p></div>';

            return;
        }

        $exerciceActif = $this->exerciceRepository->active();
        if ($exerciceActif === null) {
            echo '<p><em>Aucun exercice actif — les montants sont affiches mais impossibles a marquer "paye" ';
            echo '(la depense generee a besoin d\'un exercice). Activez-en un dans Parametres.</em></p>';
        }

        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) gmdate('Y');
        $tri = ($_GET['tri'] ?? 'mois') === 'nom' ? 'nom' : 'mois';
        $masquerSansKm = isset($_GET['masquer_sans_km']);

        $this->renderControles($annee, $tri, $masquerSansKm);
        $this->renderTableau($annee, $tri, $masquerSansKm, $exerciceActif !== null);

        echo '</div>';
    }

    private function renderControles(int $anneeCourante, string $triCourant, bool $masquerSansKm): void
    {
        echo '<form method="get" style="margin:1em 0;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';

        echo '<label>Annee : <select name="annee" onchange="this.form.submit()">';
        $maintenant = (int) gmdate('Y');
        for ($annee = $maintenant; $annee >= $maintenant - 4; $annee--) {
            $selected = $annee === $anneeCourante ? ' selected' : '';
            echo '<option value="' . $annee . '"' . $selected . '>' . $annee . '</option>';
        }
        echo '</select></label>';

        echo '<label>Trier par : <select name="tri" onchange="this.form.submit()">';
        echo '<option value="mois"' . ($triCourant === 'mois' ? ' selected' : '') . '>Mois</option>';
        echo '<option value="nom"' . ($triCourant === 'nom' ? ' selected' : '') . '>Nom de l\'entraineur</option>';
        echo '</select></label>';

        echo '<label><input type="checkbox" name="masquer_sans_km" value="1"' . ($masquerSansKm ? ' checked' : '') . ' onchange="this.form.submit()"> ';
        echo 'N\'afficher que les entraineurs avec un km A/R renseigne</label>';

        echo ' <noscript><button type="submit" class="button">Afficher</button></noscript>';
        echo '</form>';
    }

    private function renderTableau(int $annee, string $tri, bool $masquerSansKm, bool $peutMarquerPaye): void
    {
        $trainers = $this->spBuildReader->trainersActifs();
        if ($masquerSansKm) {
            $trainers = array_values(array_filter($trainers, static fn (array $t): bool => $t['km'] > 0));
        }
        if ($trainers === []) {
            echo '<p>Aucun entraineur ne correspond aux filtres actuels.</p>';

            return;
        }

        $tarif = $this->spBuildReader->tarifKm();
        $ikParPeriode = $this->ikRepository->forAnnee($annee);

        // Construire toutes les lignes d'abord (au lieu d'echo direct dans la boucle) pour
        // pouvoir les trier par nom sans dupliquer la double boucle mois x entraineur.
        $lignes = [];
        for ($mois = 1; $mois <= 12; $mois++) {
            if ($annee === (int) gmdate('Y') && $mois > (int) gmdate('n')) {
                break; // pas de mois futurs pour l'annee en cours
            }

            $interventions = $this->spBuildReader->interventionsParTrainer($annee, $mois);
            $kmExceptionnels = $this->spBuildReader->kmExceptionnelsParTrainer($annee, $mois);

            foreach ($trainers as $trainer) {
                $nb = $interventions[$trainer['id']] ?? 0;
                $kmExcep = $kmExceptionnels[$trainer['id']] ?? 0.0;
                if ($nb === 0 && $kmExcep <= 0.0) {
                    continue;
                }

                $montantHabituel = ($tarif > 0 && $trainer['km'] > 0 && $nb > 0) ? round($tarif * $trainer['km'] * $nb, 2) : 0.0;
                $montantExceptionnel = ($tarif > 0 && $kmExcep > 0) ? round($tarif * $kmExcep, 2) : 0.0;
                $montant = $montantHabituel + $montantExceptionnel;

                $ik = $ikParPeriode[$trainer['id'] . '_' . $mois] ?? null;

                $lignes[] = [
                    'mois' => $mois,
                    'trainer' => $trainer,
                    'nb' => $nb,
                    'kmExcep' => $kmExcep,
                    'montant' => $montant,
                    'paye' => $ik !== null && $ik->paye(),
                ];
            }
        }

        if ($tri === 'nom') {
            usort($lignes, static fn (array $a, array $b): int =>
                [$a['trainer']['nom'], $a['mois']] <=> [$b['trainer']['nom'], $b['mois']]);
        } else {
            usort($lignes, static fn (array $a, array $b): int =>
                [$a['mois'], $a['trainer']['nom']] <=> [$b['mois'], $b['trainer']['nom']]);
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Mois</th><th>Entraineur</th><th>Nb AR</th><th>Km A/R</th><th>Km exceptionnels</th>';
        echo '<th>Montant</th><th>Paye</th>';
        echo '</tr></thead><tbody>';

        $totalAnnee = 0.0;
        foreach ($lignes as $ligne) {
            $trainer = $ligne['trainer'];
            $totalAnnee += $ligne['montant'];

            echo '<tr>';
            echo '<td>' . esc_html(self::MOIS_LABELS[$ligne['mois']]) . '</td>';
            echo '<td>' . esc_html($trainer['nom']) . '</td>';
            echo '<td>' . $ligne['nb'] . '</td>';
            echo '<td>' . ($trainer['km'] > 0 ? esc_html(number_format($trainer['km'], 1)) . ' km' : '—') . '</td>';
            echo '<td>' . ($ligne['kmExcep'] > 0 ? esc_html(number_format($ligne['kmExcep'], 1)) . ' km' : '—') . '</td>';
            echo '<td>' . esc_html(number_format($ligne['montant'], 2)) . ' €</td>';
            echo '<td>' . $this->renderCaseAPayer($trainer['id'], $trainer['nom'], $annee, $ligne['mois'], $ligne['montant'], $ligne['paye'], $peutMarquerPaye) . '</td>';
            echo '</tr>';
        }

        if ($lignes === []) {
            echo '<tr><td colspan="7">Aucune indemnite kilometrique pour cette annee.</td></tr>';
        } else {
            echo '<tr style="font-weight:bold;"><td colspan="5">Total ' . $annee . '</td>';
            echo '<td>' . esc_html(number_format($totalAnnee, 2)) . ' €</td><td></td></tr>';
        }

        echo '</tbody></table>';
    }

    private function renderCaseAPayer(
        int $trainerId,
        string $trainerNom,
        int $annee,
        int $mois,
        float $montant,
        bool $paye,
        bool $peutMarquerPaye
    ): string {
        if (!$peutMarquerPaye && !$paye) {
            return '—';
        }

        $html = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
        $html .= wp_nonce_field(self::NONCE, '_wpnonce', true, false);
        $html .= '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_TOGGLE) . '">';
        $html .= '<input type="hidden" name="trainer_id" value="' . $trainerId . '">';
        $html .= '<input type="hidden" name="trainer_nom" value="' . esc_attr($trainerNom) . '">';
        $html .= '<input type="hidden" name="annee" value="' . $annee . '">';
        $html .= '<input type="hidden" name="mois" value="' . $mois . '">';
        $html .= '<input type="hidden" name="montant" value="' . $montant . '">';
        $html .= '<input type="hidden" name="paye" value="' . ($paye ? '0' : '1') . '">';
        $disabled = $peutMarquerPaye ? '' : ' disabled';
        $html .= '<input type="checkbox"' . ($paye ? ' checked' : '') . $disabled . ' onchange="this.form.submit()">';
        $html .= '</form>';

        return $html;
    }

    public function handleTogglePaye(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $trainerId = (int) ($_POST['trainer_id'] ?? 0);
        $trainerNom = sanitize_text_field(wp_unslash($_POST['trainer_nom'] ?? ''));
        $annee = (int) ($_POST['annee'] ?? 0);
        $mois = (int) ($_POST['mois'] ?? 0);
        $montant = (float) ($_POST['montant'] ?? 0);
        $paye = ($_POST['paye'] ?? '0') === '1';

        if ($trainerId > 0 && $annee > 0 && $mois >= 1 && $mois <= 12) {
            if ($paye) {
                $this->ikSync->marquerPaye($trainerId, $annee, $mois, $montant, $trainerNom);
            } else {
                $this->ikSync->demarquerPaye($trainerId, $annee, $mois);
            }
        }

        $retour = wp_get_referer() ?: add_query_arg(['page' => self::SLUG, 'annee' => $annee], admin_url('admin.php'));
        wp_safe_redirect($retour);
        exit;
    }
}
