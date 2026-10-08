<?php
/**
 * Amorce des tests automatiques (restructuration, étape 6 — 08/10/2026).
 *
 * Il n'y a pas de WordPress ici : on charge les fichiers des plugins tels quels, après avoir
 * défini les quelques fonctions WordPress qu'ils appellent au chargement (add_action…) et
 * celles qu'utilisent les calculs testés. Les tests appellent ensuite les calculs directement,
 * avec des données inventées : rien ne touche le site ni sa base.
 *
 * Seuls les calculs « purs » ou dont les accès à la base passent par un objet remplaçable
 * sont testés (cf. FauxWpdb ci-dessous).
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TESTS_RACINE', dirname( __DIR__, 2 ) . '/' );

// WordPress fixe toujours le fuseau du PHP sur UTC (wp-settings.php) : les calculs de dates
// des plugins s'exécutent donc en UTC sur le site. Même chose ici.
date_default_timezone_set( 'UTC' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

// ── Fonctions WordPress appelées au chargement des fichiers : sans effet ici ──
$GLOBALS['tests_options'] = [];
function add_action( ...$a ) { return true; }
function add_filter( ...$a ) { return true; }
function register_activation_hook( ...$a ) {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://exemple.test/' . basename( dirname( $f ) ) . '/'; }
function wp_next_scheduled( ...$a ) { return true; }
function wp_schedule_event( ...$a ) { return true; }
function get_option( $nom, $defaut = false ) { return $GLOBALS['tests_options'][ $nom ] ?? $defaut; }
function update_option( $nom, $valeur, ...$a ) { $GLOBALS['tests_options'][ $nom ] = $valeur; return true; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode( $v, ...$a ) { return json_encode( $v ); }
/** Date « du site » : réglable par les tests ($GLOBALS['tests_maintenant'], ex. '2027-03-15'), sinon aujourd'hui. */
function current_time( $format, ...$a ) {
	$ts = isset( $GLOBALS['tests_maintenant'] ) ? strtotime( $GLOBALS['tests_maintenant'] . ' 12:00:00' ) : time();
	return in_array( $format, [ 'mysql', 'timestamp' ], true ) ? ( $format === 'mysql' ? gmdate( 'Y-m-d H:i:s', $ts ) : $ts ) : gmdate( $format, $ts );
}

/**
 * Remplaçant de $wpdb : renvoie des résultats préparés à l'avance, dans l'ordre des appels,
 * et note les écritures (update / insert) pour que les tests puissent vérifier qu'il n'y en a pas.
 */
class FauxWpdb {
	public string $prefix = 'wp_';
	/** @var array[] résultats successifs de get_results() */
	public array $resultats = [];
	public array $ecritures = [];
	public array $requetes  = [];

	public function prepare( $sql, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) $args = $args[0];
		return preg_replace_callback( '/%%|%[dsf]/', static function ( $m ) use ( &$args ) {
			if ( $m[0] === '%%' ) return '%';
			$v = array_shift( $args );
			return $m[0] === '%s' ? "'" . addslashes( (string) $v ) . "'" : (string) $v;
		}, $sql );
	}
	public function get_results( $sql, $mode = null ) { $this->requetes[] = $sql; return array_shift( $this->resultats ) ?? []; }
	public function update( ...$a ) { $this->ecritures[] = [ 'update', $a ]; return 1; }
	public function insert( ...$a ) { $this->ecritures[] = [ 'insert', $a ]; return 1; }
}
$GLOBALS['wpdb'] = new FauxWpdb();

/** Appelle une méthode privée (les calculs testés ne sont pas publics : on ne change pas le plugin pour les tests). */
function tests_appeler( $objet_ou_classe, string $methode, ...$args ) {
	$m = new ReflectionMethod( $objet_ou_classe, $methode );
	return $m->invoke( is_object( $objet_ou_classe ) ? $objet_ou_classe : null, ...$args );
}

/** Instance d'une classe « singleton » sans passer par son constructeur (qui enregistre des hooks). */
function tests_instance( string $classe, array $proprietes = [] ) {
	$r = new ReflectionClass( $classe );
	$o = $r->newInstanceWithoutConstructor();
	foreach ( $proprietes as $nom => $valeur ) {
		$p = $r->getProperty( $nom );
		$p->setValue( $o, $valeur );
	}
	return $o;
}

// ── Fichiers des plugins testés (chargés tels quels, sans modification) ──
require TESTS_RACINE . 'sp_build/includes/class-passages.php';
require TESTS_RACINE . 'sp_build/includes/class-ik-cloture.php';
require TESTS_RACINE . 'sp_build/includes/trait-db-membres.php';
require TESTS_RACINE . 'tkd-cotisations/cotisations.php';
