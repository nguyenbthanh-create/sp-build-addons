<?php

namespace TkdProjets\Admin;

use TkdProjets\Access;
use TkdProjets\Compta;
use TkdProjets\Policy;
use TkdProjets\Types;
use TkdProjets\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Menu « Projets » : l'application (JavaScript) et la page Réglages (gestionnaires). */
final class Page {

	public const SLUG          = 'tkd-projets';
	public const SETTINGS_SLUG = 'tkd-projets-reglages';

	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_post_tkdp_save_settings', array( self::class, 'saveSettings' ) );
	}

	public static function menu(): void {
		if ( Access::canUseApp() ) {
			add_menu_page( 'Projets', 'Projets', 'read', self::SLUG, array( self::class, 'render' ), 'dashicons-chart-bar', 26 );
		}
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page( Access::canUseApp() ? self::SLUG : 'options-general.php', 'Réglages des projets', 'Réglages', 'manage_options', self::SETTINGS_SLUG, array( self::class, 'renderSettings' ) );
		}
	}

	/** Bibliothèque de carte Leaflet (chargée depuis unpkg, avec contrôle d'intégrité). */
	public static function leafletAssets(): array {
		return array(
			'js'     => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			'jsSri'  => 'sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==',
			'css'    => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			'cssSri' => 'sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==',
			'tiles'  => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
		);
	}

	/** Centre de la carte par défaut : Claira. Modifiable avec le filtre « tkdp_map_center ». */
	public static function mapCenter(): array {
		return (array) apply_filters( 'tkdp_map_center', array( 42.7613, 2.9567, 15 ) );
	}

	public static function assets( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media(); // médiathèque WordPress pour les pièces jointes
		wp_enqueue_style( 'tkdp-app', TKDP_URL . 'assets/app.css', array(), TKDP_VERSION );
		wp_enqueue_script( 'tkdp-zones-export', TKDP_URL . 'assets/zones-export.js', array(), TKDP_VERSION, true );
		wp_enqueue_script( 'tkdp-zones', TKDP_URL . 'assets/zones.js', array( 'tkdp-zones-export' ), TKDP_VERSION, true );
		wp_enqueue_script( 'tkdp-budget', TKDP_URL . 'assets/budget.js', array(), TKDP_VERSION, true );
		wp_enqueue_script( 'tkdp-app', TKDP_URL . 'assets/app.js', array( 'wp-api-fetch', 'tkdp-zones', 'tkdp-budget' ), TKDP_VERSION, true );
		wp_localize_script(
			'tkdp-app',
			'TKDP',
			array(
				'today'          => wp_date( 'Y-m-d' ),
				'userId'         => get_current_user_id(),
				'isGestionnaire' => Access::isGestionnaire(),
				'canListUsers'   => Access::canListUsers(),
				'canViewBudget'  => Compta::canViewBudget(),
				'canUpload'      => current_user_can( 'upload_files' ),
				'types'          => Types::all(),
				'teamRoles'      => array(
					Policy::RESPONSABLE => 'Responsable',
					Policy::MEMBRE      => 'Membre',
				),
				'mapCenter'      => self::mapCenter(),
				'leaflet'        => self::leafletAssets(),
			)
		);
	}

	public static function render(): void {
		if ( ! Access::canUseApp() ) {
			wp_die( esc_html__( 'Vous n\'avez pas accès aux projets.', 'tkd-projets' ), 403 );
		}
		echo '<div class="wrap"><div id="tkdp-app" class="tkdp"><p>Chargement…</p></div>';
		echo '<noscript><p>JavaScript doit être activé pour utiliser cette page.</p></noscript></div>';
	}

	public static function renderSettings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', 403 );
		}
		$chosen = Access::gestionnaires();
		echo '<div class="wrap"><h1>Réglages des projets</h1>';
		if ( isset( $_GET['tkdp-ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>';
		}
		echo '<h2>Gestionnaires de projets</h2>';
		echo '<p>Les gestionnaires créent les projets, voient tous les projets et peuvent écrire à tout un rôle. '
			. 'Les administrateurs le sont toujours. Les autres personnes ne voient que les projets dont elles font partie de l\'équipe.</p>';
		echo '<p class="description">L\'onglet <strong>Budget</strong> des projets est visible du seul bureau : les comptes cochés dans <em>Trésorerie → Paramètres → Accès bureau</em>.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="tkdp_save_settings">';
		wp_nonce_field( 'tkdp_save_settings' );
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th style="width:40px"></th><th>Personne</th><th>Rôle sur le site</th></tr></thead><tbody>';
		foreach ( Access::proposedUsers() as $user ) {
			$isAdmin = in_array( 'administrator', (array) $user->roles, true );
			$checked = $isAdmin || in_array( (int) $user->ID, $chosen, true );
			$roles   = array_map(
				static fn( $slug ) => translate_user_role( wp_roles()->role_names[ $slug ] ?? $slug ),
				(array) $user->roles
			);
			echo '<tr><td><input type="checkbox" name="gestionnaires[]" value="' . (int) $user->ID . '"' . checked( $checked, true, false ) . disabled( $isAdmin, true, false )
				. ' id="tkdp-g-' . (int) $user->ID . '"></td>'
				. '<td><label for="tkdp-g-' . (int) $user->ID . '">' . esc_html( $user->display_name ) . '</label></td>'
				. '<td>' . esc_html( implode( ', ', $roles ) ) . ( $isAdmin ? ' <span class="description">(toujours gestionnaire)</span>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Président</h2>';
		echo '<p>Seul le président propose et valide une dépense de projet (onglet Budget) ; elle entre alors directement dans la trésorerie, rattachée au projet.</p>';
		$current = Compta::presidentId();
		echo '<p><label for="tkdp-president">Président : </label><select id="tkdp-president" name="president"><option value="0">— Personne —</option>';
		foreach ( Access::proposedUsers() as $user ) {
			echo '<option value="' . (int) $user->ID . '"' . selected( (int) $user->ID, $current, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select></p>';
		if ( $current && Compta::available() && ! user_can( $current, Access::SP_COMPTA_CAP ) ) {
			echo '<div class="notice notice-warning inline"><p>Le président n\'a pas encore accès à la trésorerie : cochez-le dans <em>Trésorerie → Paramètres → Accès bureau</em>, sinon il ne verra pas l\'onglet Budget.</p></div>';
		}
		if ( ! Compta::available() ) {
			echo '<div class="notice notice-warning inline"><p>SP Compta (version 0.3.0 ou plus) n\'est pas active : l\'onglet Budget n\'apparaît pas.</p></div>';
		}
		submit_button( 'Enregistrer' );
		echo '</form></div>';
	}

	public static function saveSettings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.', 403 );
		}
		check_admin_referer( 'tkdp_save_settings' );
		$ids = Validation::ids( wp_unslash( $_POST['gestionnaires'] ?? array() ), 500 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- entiers via Validation::ids()
		Access::syncGestionnaires( $ids );
		$president = absint( $_POST['president'] ?? 0 );
		update_option( Compta::OPTION_PRESIDENT, ( $president && Access::isProposedUser( $president ) ) ? $president : 0, false );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SETTINGS_SLUG, 'tkdp-ok' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
