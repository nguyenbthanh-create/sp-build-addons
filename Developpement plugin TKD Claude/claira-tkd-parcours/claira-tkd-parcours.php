<?php
/**
 * Plugin Name: Claira TKD Parcours
 * Plugin URI:  https://example.com/
 * Description: Interface ludique de suivi des grades TKD avec contenu texte, ressources téléchargeables et vidéos URL.
 * Version:     1.14.8
 * Author:      Claira
 * Text Domain: claira-tkd-parcours
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CLAIRA_TKD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLAIRA_TKD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLAIRA_TKD_PLUGIN_VERSION', '1.14.8' );

require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/post-types.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/shortcodes.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/import.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/print-view.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/schema-view.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/admin-page.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/front-edit.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/videotheque.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/qcm-dan.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/enqueue.php';

add_action( 'init', 'claira_tkd_register_post_types' );
add_action( 'init', 'claira_tkd_register_taxonomies' );
add_action( 'init', 'claira_tkd_vt_register_post_type' );
add_action( 'init', 'claira_tkd_migrate_poom_labels', 20 );
add_action( 'wp_enqueue_scripts', 'claira_tkd_enqueue_assets' );
add_action( 'admin_enqueue_scripts', 'claira_tkd_admin_assets' );
add_action( 'admin_menu', 'claira_tkd_register_admin_menu' );
// Sous-menu « Vidéothèque », après le menu principal.
add_action( 'admin_menu', 'claira_tkd_vt_register_admin_menu', 11 );
// Sous-menu « QCM Dan » (révision du Module B de l'examen Dan), après la vidéothèque.
add_action( 'admin_menu', 'claira_tkd_qcm_register_admin_menu', 12 );
// Modification d'un grade depuis sa fiche sur le site (comptes connectés uniquement).
add_action( 'wp_ajax_claira_tkd_front_save_grade', 'claira_tkd_ajax_front_save_grade' );

add_shortcode( 'claira_tkd_parcours', 'claira_tkd_parcours_shortcode' );
add_shortcode( 'claira_tkd_parcours_tableau', 'claira_tkd_progression_table_shortcode' );
add_shortcode( 'claira_tkd_schema_grades', 'claira_tkd_schema_grades_shortcode' );
add_shortcode( 'claira_tkd_videotheque', 'claira_tkd_videotheque_shortcode' );
add_shortcode( 'claira_qcm_dan', 'claira_tkd_qcm_shortcode' );
// QCM Dan réservé aux adhérents : entrée de menu cachée par défaut, contrôle d'accès en tête de page.
add_filter( 'wp_nav_menu_objects', 'claira_tkd_qcm_menu_classes' );
add_action( 'wp_head', 'claira_tkd_qcm_acces_head', 1 );
// Recherches sans résultat de la page du QCM, gardées pour l'encadrement (18/11/2026).
add_action( 'rest_api_init', 'claira_tkd_qcm_rest_routes' );

add_action( 'plugins_loaded', 'claira_tkd_load_textdomain' );

function claira_tkd_load_textdomain() {
    load_plugin_textdomain( 'claira-tkd-parcours', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
