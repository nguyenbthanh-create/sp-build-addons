<?php
/**
 * Plugin Name: TKD Gala
 * Plugin URI:  https://tkdclaira.fr
 * Description: Inscriptions des clubs invités au gala des arts martiaux (sans compte WordPress : lien personnel par club), participants, repas, démonstrations et pistes audio.
 * Version:     1.2.0
 * Author:      TKD Claira
 * Text Domain: tkd-gala
 * Requires PHP: 8.0
 *
 * Indépendant de sp_build (aucune table ni classe partagée). Shortcode public : [tkd_gala_inscription].
 *
 * Contenu :
 *  includes/class-gala-db.php     Tables, réglages, lecture / écriture des inscriptions
 *  includes/class-gala-candidatures.php  Candidatures hors taekwondo (envois Elementor) et conducteur commun
 *  includes/class-gala-audio.php  Pistes audio : envoi, stockage protégé, écoute
 *  includes/class-gala-mails.php  Mails au représentant du club et à l'organisation
 *  includes/class-gala-front.php  Formulaire public, lien personnel, « lien perdu »
 *  includes/class-gala-admin.php  Écran « Gala » : inscriptions, conducteur, exports, réglages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TKD_GALA_VERSION', '1.2.0' );
define( 'TKD_GALA_DIR', plugin_dir_path( __FILE__ ) );
define( 'TKD_GALA_URL', plugin_dir_url( __FILE__ ) );

require_once TKD_GALA_DIR . 'includes/class-gala-db.php';
require_once TKD_GALA_DIR . 'includes/class-gala-candidatures.php';
require_once TKD_GALA_DIR . 'includes/class-gala-audio.php';
require_once TKD_GALA_DIR . 'includes/class-gala-mails.php';
require_once TKD_GALA_DIR . 'includes/class-gala-front.php';
require_once TKD_GALA_DIR . 'includes/class-gala-admin.php';

register_activation_hook( __FILE__, [ 'Tkd_Gala_DB', 'installer' ] );

// Site déjà actif quand une nouvelle version est déposée : tables mises à jour au premier
// chargement, sans réactivation (comparaison de version, donc une seule requête ensuite).
add_action( 'plugins_loaded', [ 'Tkd_Gala_DB', 'maybe_upgrade' ] );

Tkd_Gala_Audio::get_instance();
Tkd_Gala_Front::get_instance();
if ( is_admin() ) Tkd_Gala_Admin::get_instance();
