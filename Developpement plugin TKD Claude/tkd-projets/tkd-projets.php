<?php
/**
 * Plugin Name:       TKD Projets
 * Description:       Gestion de projets et de tâches en français : liste, tableau Kanban et diagramme de Gantt.
 * Version:           1.6.0
 * Author:            Noé PETIT DE MIRBECK
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       tkd-projets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TKDP_VERSION', '1.6.0' );
define( 'TKDP_DIR', plugin_dir_path( __FILE__ ) );
define( 'TKDP_URL', plugin_dir_url( __FILE__ ) );

require_once TKDP_DIR . 'includes/install.php';
require_once TKDP_DIR . 'includes/rest.php';
require_once TKDP_DIR . 'includes/mail.php';
require_once TKDP_DIR . 'includes/zones.php';
require_once TKDP_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, 'tkdp_install' );
add_action( 'plugins_loaded', 'tkdp_maybe_upgrade' );
