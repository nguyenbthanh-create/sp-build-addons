<?php
/**
 * Page d'administration « Projets ».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'tkdp_admin_menu' );
add_action( 'admin_enqueue_scripts', 'tkdp_admin_assets' );

/**
 * Ajoute le menu « Projets ».
 */
function tkdp_admin_menu() {
	add_menu_page(
		'Projets',
		'Projets',
		'edit_posts',
		'tkd-projets',
		'tkdp_render_page',
		'dashicons-chart-bar',
		26
	);
}

/**
 * Charge le JavaScript et le CSS uniquement sur la page du plugin.
 *
 * @param string $hook Identifiant de la page.
 */
function tkdp_admin_assets( $hook ) {
	if ( 'toplevel_page_tkd-projets' !== $hook ) {
		return;
	}

	wp_enqueue_media(); // médiathèque WordPress pour les pièces jointes
	wp_enqueue_style( 'tkdp-app', TKDP_URL . 'assets/app.css', array(), TKDP_VERSION );
	wp_enqueue_script( 'tkdp-zones-export', TKDP_URL . 'assets/zones-export.js', array(), TKDP_VERSION, true );
	wp_enqueue_script( 'tkdp-zones', TKDP_URL . 'assets/zones.js', array( 'tkdp-zones-export' ), TKDP_VERSION, true );
	wp_enqueue_script( 'tkdp-app', TKDP_URL . 'assets/app.js', array( 'wp-api-fetch', 'tkdp-zones' ), TKDP_VERSION, true );

	wp_localize_script(
		'tkdp-app',
		'TKDP',
		array(
			'today'     => wp_date( 'Y-m-d' ),
			'canManage' => current_user_can( 'manage_options' ),
			'userId'    => get_current_user_id(),
			'canUpload' => current_user_can( 'upload_files' ),
			'mapCenter' => tkdp_map_center(),
			'leaflet'   => tkdp_leaflet_assets(),
		)
	);
}

/**
 * Affiche le conteneur de l'application.
 */
function tkdp_render_page() {
	echo '<div class="wrap"><div id="tkdp-app" class="tkdp"><p>Chargement…</p></div>';
	echo '<noscript><p>JavaScript doit être activé pour utiliser cette page.</p></noscript></div>';
}
