<?php
/**
 * Plugin Name: TKD Vie privée
 * Description: Aucun service tiers chargé à l'ouverture des pages : polices du thème servies par le site (plus de Google Fonts) et carte Google Maps affichée seulement au clic. Le site ne dépose ainsi que des cookies indispensables, sans bandeau de consentement à afficher (recommandations CNIL).
 * Version:     1.0.0
 * Author:      TKD Claira
 *
 * Extension « toujours active » (wp-content/mu-plugins/), créée le 17/11/2026. Elle va avec
 * le dossier mu-plugins/tkd-polices/ (Lato et Droid Sans, licence SIL OFL). Pour la retirer :
 * supprimer ce fichier et ce dossier par FTP.
 *
 * Autres réglages faits le même jour pour la même raison (à ne pas réactiver sans bandeau
 * de consentement) :
 *   - Elementor → Réglages → « Charger les polices Google localement » : activé ;
 *   - SportsPress → Réglages → Modules → Facebook : désactivé (chargeait le SDK Facebook) ;
 *   - WooCommerce → Réglages → Avancé → Fonctionnalités → Attribution des commandes :
 *     désactivé (cookies sbjs_*) ;
 *   - TKD Parcours 1.12 : vidéos des grades lancées au clic (youtube-nocookie).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------------------------
 * 1. Polices du thème (Premier / Rookie) servies par le site
 * ------------------------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', static function () {
	$remplacees = false;
	foreach ( [ 'rookie-lato', 'premier-droid-sans' ] as $poignee ) {
		if ( wp_style_is( $poignee, 'registered' ) ) {
			wp_dequeue_style( $poignee );
			wp_deregister_style( $poignee );
			$remplacees = true;
		}
	}
	if ( $remplacees ) {
		$fichier = __DIR__ . '/tkd-polices/polices.css';
		wp_enqueue_style( 'tkd-polices', plugins_url( 'tkd-polices/polices.css', __FILE__ ), [], file_exists( $fichier ) ? (string) filemtime( $fichier ) : '1' );
	}
}, 100 );

// Plus de « dns-prefetch » vers fonts.googleapis.com.
add_filter( 'wp_resource_hints', static function ( $urls, $type ) {
	if ( $type !== 'dns-prefetch' && $type !== 'preconnect' ) return $urls;
	return array_values( array_filter( $urls, static function ( $u ) {
		$href = is_array( $u ) ? ( $u['href'] ?? '' ) : (string) $u;
		return ! str_contains( $href, 'fonts.googleapis.com' ) && ! str_contains( $href, 'fonts.gstatic.com' );
	} ) );
}, 10, 2 );

/* ---------------------------------------------------------------------------------------
 * 2. Carte Google Maps (widget Elementor) affichée au clic
 * ------------------------------------------------------------------------------------- */

add_filter( 'elementor/widget/render_content', static function ( $contenu, $widget ) {
	if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || $widget->get_name() !== 'google_maps' ) return $contenu;
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return $contenu; // éditeur Elementor : carte normale
	if ( ! preg_match( '~<iframe[^>]*\ssrc="([^"]+)"~i', (string) $contenu, $m ) ) return $contenu;

	$src  = html_entity_decode( $m[1] );
	parse_str( (string) wp_parse_url( $src, PHP_URL_QUERY ), $q );
	$lien = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( (string) ( $q['q'] ?? '' ) );

	$contenu = preg_replace( '~(<iframe[^>]*\s)src="[^"]+"~i', '$1data-tkd-src="' . esc_attr( $src ) . '"', (string) $contenu, 1 );
	$GLOBALS['tkd_vie_privee_carte'] = true;

	return '<div class="tkd-carte">' . $contenu
		. '<div class="tkd-carte-voile"><button type="button" class="tkd-carte-bouton">📍 Afficher la carte</button>'
		. '<span class="tkd-carte-note">Carte fournie par Google Maps, chargée seulement si vous cliquez. '
		. '<a href="' . esc_url( $lien ) . '" target="_blank" rel="noopener noreferrer">Ouvrir dans Google Maps</a></span></div></div>';
}, 10, 2 );

add_action( 'wp_footer', static function () {
	if ( empty( $GLOBALS['tkd_vie_privee_carte'] ) ) return;
	?>
	<style>
	.tkd-carte{position:relative}
	.tkd-carte-voile{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:16px;background:#eef1f4;text-align:center}
	.tkd-carte-bouton{padding:10px 18px;border:0;border-radius:6px;background:#b91c1c;color:#fff;font:inherit;font-weight:600;cursor:pointer}
	.tkd-carte-bouton:hover,.tkd-carte-bouton:focus-visible{background:#7f1d1d}
	.tkd-carte-note{font-size:13px;color:#4b5563;max-width:420px}
	.tkd-carte-note a{color:inherit;text-decoration:underline}
	</style>
	<script>
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.tkd-carte-bouton') : null;
		if (!b) return;
		var zone = b.closest('.tkd-carte');
		var f = zone.querySelector('iframe[data-tkd-src]');
		if (f) { f.src = f.getAttribute('data-tkd-src'); f.removeAttribute('data-tkd-src'); }
		zone.querySelector('.tkd-carte-voile').remove();
	});
	</script>
	<?php
} );
