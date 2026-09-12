<?php
/**
 * Plugin Name: Claira TKD Parcours
 * Plugin URI:  https://example.com/
 * Description: Interface ludique de suivi des grades TKD avec contenu texte, ressources téléchargeables et vidéos URL.
 * Version:     1.2.1
 * Author:      Claira
 * Text Domain: claira-tkd-parcours
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CLAIRA_TKD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLAIRA_TKD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLAIRA_TKD_PLUGIN_VERSION', '1.2.1' );

require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/post-types.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/shortcodes.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/import.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/admin-page.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/enqueue.php';

add_action( 'init', 'claira_tkd_register_post_types' );
add_action( 'init', 'claira_tkd_register_taxonomies' );
add_action( 'wp_enqueue_scripts', 'claira_tkd_enqueue_assets' );
add_action( 'admin_enqueue_scripts', 'claira_tkd_admin_assets' );
add_action( 'admin_menu', 'claira_tkd_register_admin_menu' );

add_shortcode( 'claira_tkd_parcours', 'claira_tkd_parcours_shortcode' );

add_action( 'plugins_loaded', 'claira_tkd_load_textdomain' );

function claira_tkd_load_textdomain() {
    load_plugin_textdomain( 'claira-tkd-parcours', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
