<?php

declare(strict_types=1);

namespace SpCompta\Front;

use SpCompta\Accounting\Categories;
use SpCompta\Admin\RecetteScreen;
use SpCompta\Capabilities;
use SpCompta\Repository\ClientRepository;
use SpCompta\Repository\ExerciceRepository;

/**
 * Pendant de SaisieRapideShortcode.php pour les recettes (meme demande
 * utilisateur, confirmee le 11/09/2026 - jusque-la seule la Depense etait
 * couverte). Meme patron a l'identique : reutilisation totale de
 * RecetteScreen::saveFromRequest(), aucune logique de sauvegarde propre a
 * cette classe. Depuis le meme jour, plus un shortcode a part entiere - voir
 * SaisieRapideShortcode.md pour pourquoi (assemblee avec la version Depense
 * par SaisieRapideCombineeShortcode.php).
 */
final class SaisieRapideRecetteShortcode
{
    private const ACTION_SAVE = 'sp_compta_saisie_rapide_recette_save';
    private const NONCE = 'sp_compta_saisie_rapide_recette_nonce';

    public function __construct(
        private RecetteScreen $recetteScreen,
        private ExerciceRepository $exerciceRepository,
        private ClientRepository $clientRepository
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'handleSave']);
    }

    public function render(): string
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            return '<p>' . esc_html__('Acces reserve aux membres du bureau. Connectez-vous avec votre compte WordPress.', 'sp-compta') . '</p>';
        }

        $exerciceActif = $this->exerciceRepository->active();

        if ($exerciceActif === null) {
            return '<p>' . esc_html__('Aucun exercice actif. Contactez le bureau.', 'sp-compta') . '</p>';
        }

        ob_start();
        $this->renderStyles();
        $this->renderForm((int) $exerciceActif->id());

        return (string) ob_get_clean();
    }

    private function renderStyles(): void
    {
        echo '<style>
.sp-compta-saisie-rapide{max-width:480px;margin:0 auto;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;}
.sp-compta-saisie-rapide h2{color:#0f172a;font-size:20px;margin:0 0 16px;}
.sp-compta-saisie-rapide .champ{display:block;margin-bottom:16px;}
.sp-compta-saisie-rapide label{display:block;font-weight:600;font-size:14px;color:#374151;margin-bottom:6px;}
.sp-compta-saisie-rapide input,.sp-compta-saisie-rapide select,.sp-compta-saisie-rapide textarea{width:100%;box-sizing:border-box;padding:14px;font-size:16px;border:1px solid #e2e8f0;border-radius:8px;}
.sp-compta-saisie-rapide input[type="number"]{font-size:24px;font-weight:600;}
.sp-compta-saisie-rapide summary{cursor:pointer;color:#1e3a5f;font-weight:600;font-size:14px;margin:4px 0 16px;}
.sp-compta-saisie-rapide button{width:100%;padding:16px;font-size:17px;font-weight:700;color:#ffffff;background:#1e3a5f;border:none;border-radius:8px;cursor:pointer;}
.sp-compta-saisie-rapide .confirmation{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-weight:600;}
</style>';
    }

    private function renderForm(int $exerciceId): void
    {
        $today = gmdate('Y-m-d');
        $redirectTo = get_permalink() ?: home_url('/');

        echo '<div class="sp-compta-saisie-rapide">';
        echo '<h2>Nouvelle recette</h2>';

        if (($_GET['sp_compta_saved'] ?? '') === 'recette') {
            echo '<p class="confirmation">Recette enregistree.</p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE) . '">';
        echo '<input type="hidden" name="exercice_id" value="' . esc_attr((string) $exerciceId) . '">';
        echo '<input type="hidden" name="redirect_to" value="' . esc_url($redirectTo) . '">';

        echo '<div class="champ"><label for="sp-compta-sr-montant">Montant (&euro;)</label>';
        echo '<input type="number" step="0.01" inputmode="decimal" id="sp-compta-sr-montant" name="montant" autofocus required></div>';

        echo '<div class="champ"><label for="sp-compta-sr-categorie">Categorie</label>';
        echo '<select id="sp-compta-sr-categorie" name="sous_categorie" required>';
        echo '<option value="">-- Choisir --</option>';
        foreach (Categories::RECETTE as $code => $definition) {
            echo '<optgroup label="' . esc_attr(Categories::libelleCategorie(Categories::RECETTE, $code)) . '">';
            foreach ($definition['sous_categories'] as $key => $label) {
                echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select></div>';

        echo '<div class="champ"><label for="sp-compta-sr-mode">Mode de paiement</label>';
        echo '<select id="sp-compta-sr-mode" name="mode_paiement">';
        echo '<option value="">--</option>';
        foreach (RecetteScreen::modesPaiement() as $mode) {
            echo '<option value="' . esc_attr($mode) . '">' . esc_html($mode) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="champ"><label for="sp-compta-sr-justificatif">Photo du justificatif</label>';
        echo '<input type="file" id="sp-compta-sr-justificatif" name="justificatif" accept="image/*" capture="environment"></div>';

        echo '<details><summary>Plus de details (optionnel)</summary>';
        echo '<div class="champ"><label for="sp-compta-sr-date">Date</label>';
        echo '<input type="date" id="sp-compta-sr-date" name="date" value="' . esc_attr($today) . '"></div>';
        echo '<div class="champ"><label for="sp-compta-sr-provenance">Provenance</label>';
        echo '<input type="text" id="sp-compta-sr-provenance" name="provenance" placeholder="Cotisation, buvette..."></div>';
        echo '<div class="champ"><label for="sp-compta-sr-client">Client lie</label>';
        echo '<select id="sp-compta-sr-client" name="client_id"><option value="">-- Aucun --</option>';
        foreach ($this->clientRepository->all() as $client) {
            echo '<option value="' . esc_attr((string) $client->id()) . '">' . esc_html($client->nom()) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="champ"><label for="sp-compta-sr-detail">Detail</label>';
        echo '<textarea id="sp-compta-sr-detail" name="detail" rows="2"></textarea></div>';
        echo '</details>';

        echo '<button type="submit">Enregistrer</button>';
        echo '</form>';
        echo '</div>';
    }

    public function handleSave(): void
    {
        check_admin_referer(self::NONCE);

        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            wp_die(esc_html__('Acces non autorise.', 'sp-compta'));
        }

        $this->recetteScreen->saveFromRequest($_POST, $_FILES);

        $redirectTo = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        $redirectTo = wp_validate_redirect($redirectTo, home_url('/'));

        wp_safe_redirect(add_query_arg('sp_compta_saved', 'recette', $redirectTo));
        exit;
    }
}
