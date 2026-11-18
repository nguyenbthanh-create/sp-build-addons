<?php
/**
 * Plugin Name:       TKD Projets
 * Description:       Gestion des projets du club (gala, fêtes, compétitions) : tâches, équipe, liste, Kanban, Gantt et carte des zones.
 * Version:           2.1.0
 * Author:            Noé PETIT DE MIRBECK (version d'origine), TKD Claira
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * License:           GPL-2.0-or-later
 * Text Domain:       tkd-projets
 *
 * Contenu (voir README.md) :
 *  src/Plugin.php        branchement des hooks
 *  src/Installer.php     tables et mises à jour du schéma
 *  src/Access.php        droits (gestionnaires, rôle dans un projet) ; src/Policy.php règles pures
 *  src/Types.php         types de projets et listes de tâches modèles
 *  src/Rest/             API interne utilisée par la page d'administration
 *  src/Mail/Mailer.php   e-mails d'attribution et de zones
 *  src/Front/ZonePage.php  page privée « Ma carte »
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TKDP_VERSION', '2.1.0' );
define( 'TKDP_FILE', __FILE__ );
define( 'TKDP_DIR', plugin_dir_path( __FILE__ ) );
define( 'TKDP_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'TkdProjets\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$path = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

register_activation_hook( __FILE__, array( \TkdProjets\Installer::class, 'install' ) );
\TkdProjets\Plugin::boot();
