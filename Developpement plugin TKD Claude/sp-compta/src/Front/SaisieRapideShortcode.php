<?php

declare(strict_types=1);

namespace SpCompta\Front;

use SpCompta\Accounting\Categories;
use SpCompta\Admin\DepenseScreen;
use SpCompta\Capabilities;
use SpCompta\Repository\ExerciceRepository;
use SpCompta\Repository\FournisseurRepository;

/**
 * Shortcode [sp_compta_saisie_rapide] : formulaire front-end mobile-first
 * pour saisir une depense en quelques secondes (montant, categorie, photo
 * du justificatif), pense pour une utilisation au telephone en deplacement.
 * Injecte aussi un manifest PWA + l'enregistrement du service worker (voir
 * ServiceWorker.php) sur toute page qui utilise ce shortcode, pour
 * permettre l'ajout a l'ecran d'accueil (avec banniere automatique sur
 * Chrome/Android).
 */
final class SaisieRapideShortcode
{
    private const TAG = 'sp_compta_saisie_rapide';
    private const ACTION_SAVE = 'sp_compta_saisie_rapide_save';
    private const NONCE = 'sp_compta_saisie_rapide_nonce';

    public function __construct(
        private DepenseScreen $depenseScreen,
        private ExerciceRepository $exerciceRepository,
        private FournisseurRepository $fournisseurRepository
    ) {
    }

    public function registerHooks(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
        add_action('wp_head', [$this, 'maybeRenderHead']);
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'handleSave']);
    }

    /**
     * N'injecte le manifest/les meta tags PWA que sur les pages qui
     * utilisent reellement le shortcode - jamais sur le reste du site.
     */
    public function maybeRenderHead(): void
    {
        if (!is_singular()) {
            return;
        }

        $post = get_post();

        if ($post === null || !has_shortcode((string) $post->post_content, self::TAG)) {
            return;
        }

        $this->renderHeadTags();
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

    private function renderHeadTags(): void
    {
        $icon = $this->iconDataUri();
        $manifest = [
            'name' => 'Tresorerie - Saisie rapide',
            'short_name' => 'Tresorerie',
            'start_url' => '.',
            'scope' => '.',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#1e3a5f',
            'icons' => [
                ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/svg+xml'],
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/svg+xml'],
            ],
        ];

        echo '<link rel="manifest" href="data:application/manifest+json,'
            . rawurlencode((string) wp_json_encode($manifest)) . '">' . "\n";
        echo '<meta name="theme-color" content="#1e3a5f">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="Tresorerie">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_attr($icon) . '">' . "\n";
        echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){'
            . 'navigator.serviceWorker.register(' . wp_json_encode(ServiceWorker::url()) . ');});}</script>' . "\n";
    }

    private function iconDataUri(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">'
            . '<rect width="192" height="192" rx="32" fill="#1e3a5f"/>'
            . '<text x="96" y="128" font-family="system-ui,sans-serif" font-size="92" font-weight="700" '
            . 'fill="#ffffff" text-anchor="middle">SP</text></svg>';

        return 'data:image/svg+xml,' . rawurlencode($svg);
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
        echo '<h2>Nouvelle depense</h2>';

        if (isset($_GET['sp_compta_saved'])) {
            echo '<p class="confirmation">Depense enregistree.</p>';
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
        foreach (Categories::DEPENSE as $code => $definition) {
            echo '<optgroup label="' . esc_attr(Categories::libelleCategorie(Categories::DEPENSE, $code)) . '">';
            foreach ($definition['sous_categories'] as $key => $label) {
                echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select></div>';

        echo '<div class="champ"><label for="sp-compta-sr-mode">Mode de paiement</label>';
        echo '<select id="sp-compta-sr-mode" name="mode_paiement">';
        echo '<option value="">--</option>';
        foreach (DepenseScreen::modesPaiement() as $mode) {
            echo '<option value="' . esc_attr($mode) . '">' . esc_html($mode) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="champ"><label for="sp-compta-sr-justificatif">Photo du justificatif</label>';
        echo '<input type="file" id="sp-compta-sr-justificatif" name="justificatif" accept="image/*" capture="environment"></div>';

        echo '<details><summary>Plus de details (optionnel)</summary>';
        echo '<div class="champ"><label for="sp-compta-sr-date">Date</label>';
        echo '<input type="date" id="sp-compta-sr-date" name="date" value="' . esc_attr($today) . '"></div>';
        echo '<div class="champ"><label for="sp-compta-sr-fournisseur">Fournisseur</label>';
        echo '<select id="sp-compta-sr-fournisseur" name="fournisseur_id"><option value="">-- Aucun --</option>';
        foreach ($this->fournisseurRepository->all() as $fournisseur) {
            echo '<option value="' . esc_attr((string) $fournisseur->id()) . '">' . esc_html($fournisseur->nom()) . '</option>';
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

        $this->depenseScreen->saveFromRequest($_POST, $_FILES);

        $redirectTo = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        $redirectTo = wp_validate_redirect($redirectTo, home_url('/'));

        wp_safe_redirect(add_query_arg('sp_compta_saved', '1', $redirectTo));
        exit;
    }
}
