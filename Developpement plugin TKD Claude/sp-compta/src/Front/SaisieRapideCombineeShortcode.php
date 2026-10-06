<?php

declare(strict_types=1);

namespace SpCompta\Front;

use SpCompta\Capabilities;

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

    /**
     * Appel AJAX fait juste avant chaque envoi : renvoie des jetons de
     * formulaire neufs si la session est toujours ouverte, sinon signale qu'il
     * faut se reconnecter (la saisie reste gardee sur le telephone).
     */
    public const AJAX_JETON = 'sp_compta_saisie_jeton';

    public function __construct(
        private SaisieRapideShortcode $depenseShortcode,
        private SaisieRapideRecetteShortcode $recetteShortcode
    ) {
    }

    public function registerHooks(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
        add_action('wp_head', [$this, 'maybeRenderHead']);
        add_action('wp_ajax_' . self::AJAX_JETON, [$this, 'ajaxJeton']);
        add_action('wp_ajax_nopriv_' . self::AJAX_JETON, [$this, 'ajaxJeton']);
    }

    /**
     * Jetons neufs pour les deux formulaires, ou null si l'utilisateur n'est
     * pas (ou plus) autorise - logique testable de ajaxJeton().
     *
     * @return array{depense: string, recette: string}|null
     */
    public function jetons(): ?array
    {
        if (!current_user_can(Capabilities::MANAGE_COMPTA)) {
            return null;
        }

        return [
            'depense' => wp_create_nonce(SaisieRapideShortcode::NONCE),
            'recette' => wp_create_nonce(SaisieRapideRecetteShortcode::NONCE),
        ];
    }

    public function ajaxJeton(): void
    {
        nocache_headers();
        $jetons = $this->jetons();

        if ($jetons === null) {
            wp_send_json_error(is_user_logged_in() ? 'acces' : 'connexion');
        }

        wp_send_json_success($jetons);
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

        // Session expiree ou absente : un seul ecran, avec un bouton qui
        // ramene directement ici apres la connexion (la saisie en cours est
        // gardee sur le telephone, voir le script de brouillon ci-dessous).
        if (!is_user_logged_in()) {
            ob_start();
            $this->renderConnexion();
            $this->renderTabsStyleEtScript();

            return (string) ob_get_clean();
        }

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

    private function renderConnexion(): void
    {
        echo '<div class="sp-compta-tabs"><div class="sp-compta-connexion">';
        echo '<p><strong>Connexion nécessaire</strong></p>';
        echo '<p>Connectez-vous avec votre compte du site pour saisir une dépense ou une recette. '
            . 'Une saisie commencée avant est conservée sur ce téléphone et sera remise en place.</p>';
        echo '<a class="sp-compta-btn-connexion" href="' . esc_url(wp_login_url(self::urlRetour())) . '">Se connecter</a>';
        echo '</div></div>';
    }

    /**
     * Adresse de la page de saisie, sans les parametres de confirmation.
     */
    private static function urlRetour(): string
    {
        $url = get_permalink();

        return $url !== false ? $url : home_url('/');
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
.sp-compta-connexion{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px;text-align:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f172a;}
.sp-compta-connexion p{margin:0 0 12px;}
.sp-compta-btn-connexion{display:inline-block;padding:14px 24px;background:#1e3a5f;color:#fff !important;border-radius:8px;font-weight:700;text-decoration:none;}
.sp-compta-note{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a5f;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:14px;}
.sp-compta-note.erreur{background:#fef2f2;border-color:#fecaca;color:#991b1b;}
.sp-compta-note a,.sp-compta-note button{color:inherit;font-weight:700;text-decoration:underline;background:none;border:0;padding:0;font-size:14px;cursor:pointer;}
</style>';

        $config = [
            'ajax' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_JETON,
            'login' => wp_login_url(self::urlRetour()),
            'types' => [
                SaisieRapideShortcode::ACTION_SAVE => 'depense',
                SaisieRapideRecetteShortcode::ACTION_SAVE => 'recette',
            ],
        ];

        echo '<script>(function(){
var onglets = document.querySelectorAll(".sp-compta-tab");
var panneaux = document.querySelectorAll(".sp-compta-tab-panel");
function ouvrir(cible){
    onglets.forEach(function(o){ o.classList.toggle("active", o.getAttribute("data-tab") === cible); });
    panneaux.forEach(function(p){ p.hidden = (p.getAttribute("data-panel") !== cible); });
}
onglets.forEach(function(onglet){
    onglet.addEventListener("click", function(){ ouvrir(onglet.getAttribute("data-tab")); });
});

/* Saisie gardee sur le telephone (06/10/2026) : chaque champ est memorise pendant la
   saisie et remis en place si la page est rechargee (reconnexion, application fermee
   par le systeme). Brouillon efface des que l enregistrement est confirme. La photo
   ne peut pas etre conservee par un navigateur. */
var CFG = ' . wp_json_encode($config) . ';
var CLE = "sp_compta_brouillon_";
var IGNORES = ["action", "_wpnonce", "_wp_http_referer", "exercice_id", "redirect_to"];
function lire(t){ try { return JSON.parse(localStorage.getItem(CLE + t) || "null"); } catch (e) { return null; } }
function ecrire(t, v){ try { localStorage.setItem(CLE + t, JSON.stringify(v)); } catch (e) {} }
function effacer(t){ try { localStorage.removeItem(CLE + t); } catch (e) {} }
function note(form, html, erreur){
    var p = form.querySelector(".sp-compta-note");
    if (!p) { p = document.createElement("p"); form.insertBefore(p, form.firstChild); }
    p.className = "sp-compta-note" + (erreur ? " erreur" : "");
    p.innerHTML = html;
    return p;
}
var confirme = new URLSearchParams(window.location.search).get("sp_compta_saved");
if (confirme) { effacer(confirme); }

document.querySelectorAll(".sp-compta-saisie-rapide form").forEach(function(form){
    var a = form.querySelector("input[name=action]");
    var type = a ? CFG.types[a.value] : null;
    if (!type) { return; }
    function champs(){
        return Array.prototype.filter.call(form.elements, function(el){
            return el.name && IGNORES.indexOf(el.name) === -1 && ["file", "submit", "button"].indexOf(el.type) === -1;
        });
    }
    function sauver(){
        var v = {}, vide = true;
        champs().forEach(function(el){ v[el.name] = el.value; if (el.value !== "" && el.name !== "date") { vide = false; } });
        if (vide) { effacer(type); } else { ecrire(type, v); }
    }

    var brouillon = lire(type);
    if (brouillon) {
        var restaure = false;
        champs().forEach(function(el){
            if (Object.prototype.hasOwnProperty.call(brouillon, el.name) && brouillon[el.name] !== "") { el.value = brouillon[el.name]; restaure = true; }
        });
        if (restaure) {
            var p = note(form, "Saisie en cours remise en place (reprenez la photo si besoin). <button type=\"button\">Effacer</button>", false);
            p.querySelector("button").addEventListener("click", function(){ effacer(type); window.location.reload(); });
            if (type === "recette") { ouvrir("recette"); }
        }
    }
    form.addEventListener("input", sauver);
    form.addEventListener("change", sauver);

    /* Juste avant l envoi : jeton de formulaire neuf (la page a pu rester ouverte des
       heures) et verification que la session est toujours ouverte. */
    var envoiConfirme = false;
    form.addEventListener("submit", function(e){
        if (envoiConfirme) { return; }
        e.preventDefault();
        sauver();
        var bouton = form.querySelector("button[type=submit]");
        var libelle = bouton ? bouton.textContent : "";
        if (bouton) { bouton.disabled = true; bouton.textContent = "Envoi…"; }
        var corps = new URLSearchParams();
        corps.append("action", CFG.action);
        fetch(CFG.ajax, { method: "POST", credentials: "same-origin", body: corps })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r && r.success && r.data && r.data[type]) {
                    var jeton = form.querySelector("input[name=_wpnonce]");
                    if (jeton) { jeton.value = r.data[type]; }
                    envoiConfirme = true;
                    form.submit();
                    return;
                }
                if (bouton) { bouton.disabled = false; bouton.textContent = libelle; }
                if (r && r.data === "acces") {
                    note(form, "Votre compte n’a pas accès à la trésorerie.", true);
                } else {
                    note(form, "Session expirée : votre saisie est gardée sur ce téléphone. <a href=\"" + CFG.login + "\">Se reconnecter</a>", true);
                }
                form.scrollIntoView({ behavior: "smooth", block: "start" });
            })
            .catch(function(){
                /* Reponse illisible ou reseau incertain : envoi classique, le serveur tranchera. */
                envoiConfirme = true;
                form.submit();
            });
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
