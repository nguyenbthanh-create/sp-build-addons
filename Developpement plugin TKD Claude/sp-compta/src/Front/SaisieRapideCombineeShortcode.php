<?php

declare(strict_types=1);

namespace SpCompta\Front;

/**
 * Le vrai shortcode public [sp_compta_saisie_rapide] depuis le 11/09/2026 :
 * assemble SaisieRapideShortcode.php (Depense) et SaisieRapideRecetteShortcode.php
 * (Recette) sur une seule page, avec deux boutons "Depense"/"Recette" pour
 * basculer instantanement de l'un a l'autre (JS pur, pas de rechargement de
 * page). Avant ce changement, Depense et Recette etaient deux shortcodes
 * distincts sur deux pages differentes - demande utilisateur : un moyen
 * "intuitif" de passer de l'un a l'autre sans avoir a se souvenir de deux
 * URLs. Porte aussi le manifest PWA unique (un seul "ajout a l'ecran
 * d'accueil" pour les deux formulaires, plutot que deux qui se
 * chevauchaient si les deux anciens shortcodes se retrouvaient sur la meme
 * page).
 */
final class SaisieRapideCombineeShortcode
{
    private const TAG = 'sp_compta_saisie_rapide';

    public function __construct(
        private SaisieRapideShortcode $depenseShortcode,
        private SaisieRapideRecetteShortcode $recetteShortcode
    ) {
    }

    public function registerHooks(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
        add_action('wp_head', [$this, 'maybeRenderHead']);
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
        // Rouvre l'onglet Recette apres enregistrement d'une recette (sinon
        // le message de confirmation resterait cache derriere l'onglet
        // Depense, actif par defaut) - voir handleSave() des deux
        // formulaires, qui distinguent desormais 'depense'/'recette' dans
        // sp_compta_saved plutot qu'un simple '1' generique.
        $ongletActif = ($_GET['sp_compta_saved'] ?? '') === 'recette' ? 'recette' : 'depense';

        ob_start();

        echo '<div class="sp-compta-tabs">';
        echo '<div class="sp-compta-tabs-nav">';
        echo '<button type="button" class="sp-compta-tab' . ($ongletActif === 'depense' ? ' active' : '') . '" data-tab="depense">💸 Dépense</button>';
        echo '<button type="button" class="sp-compta-tab' . ($ongletActif === 'recette' ? ' active' : '') . '" data-tab="recette">💰 Recette</button>';
        echo '</div>';

        echo '<div class="sp-compta-tab-panel" data-panel="depense"' . ($ongletActif === 'depense' ? '' : ' hidden') . '>' . $this->depenseShortcode->render() . '</div>';
        echo '<div class="sp-compta-tab-panel" data-panel="recette"' . ($ongletActif === 'recette' ? '' : ' hidden') . '>' . $this->recetteShortcode->render() . '</div>';

        echo '</div>';

        $this->renderTabsStyleEtScript();

        return (string) ob_get_clean();
    }

    /**
     * CSS/JS du bascule uniquement - le CSS de chaque formulaire lui-meme
     * (classe .sp-compta-saisie-rapide) reste porte par chaque shortcode
     * d'origine, deja inclus dans le HTML renvoye par leurs render().
     */
    private function renderTabsStyleEtScript(): void
    {
        echo '<style>
.sp-compta-tabs{max-width:480px;margin:0 auto;}
.sp-compta-tabs-nav{display:flex;gap:8px;margin-bottom:20px;}
.sp-compta-tab{flex:1;padding:14px;font-size:15px;font-weight:700;border:2px solid #1e3a5f;border-radius:8px;background:#ffffff;color:#1e3a5f;cursor:pointer;}
.sp-compta-tab.active{background:#1e3a5f;color:#ffffff;}
</style>';

        echo '<script>(function(){
var onglets = document.querySelectorAll(".sp-compta-tab");
var panneaux = document.querySelectorAll(".sp-compta-tab-panel");
onglets.forEach(function(onglet){
    onglet.addEventListener("click", function(){
        var cible = onglet.getAttribute("data-tab");
        onglets.forEach(function(o){ o.classList.toggle("active", o === onglet); });
        panneaux.forEach(function(p){ p.hidden = (p.getAttribute("data-panel") !== cible); });
    });
});
})();</script>';
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
}
