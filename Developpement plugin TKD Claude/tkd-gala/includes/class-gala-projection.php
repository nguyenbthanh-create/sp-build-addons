<?php
/**
 * Page « Projection » du gala (10/11/2026), réservée aux administrateurs :
 *   admin-post.php?action=tkd_gala_projection&vue=salle   écran du vidéoprojecteur (plein écran)
 *   admin-post.php?action=tkd_gala_projection&vue=regie   écran de la personne aux commandes
 *
 * Le déroulé est celui du Conducteur (Tkd_Gala_Candidatures::conducteur()) au moment de
 * l'ouverture de la page. Navigation au clavier ou à la télécommande de présentation
 * (assets/gala-projection.js) ; ouverts sur le même ordinateur, la régie pilote l'écran salle
 * (BroadcastChannel). Pages autonomes (sans l'habillage de wp-admin).
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Projection {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_tkd_gala_projection', [ $this, 'afficher' ] );
	}

	/** @return array<string, mixed> données passées au script : en-tête du gala et déroulé */
	public static function donnees(): array {
		$r      = Tkd_Gala_DB::reglages();
		$lignes = Tkd_Gala_Candidatures::conducteur( (string) $r['edition'] );
		$heures = Tkd_Gala_Admin::heures( $lignes, $r );
		$items  = [];
		foreach ( $lignes as $d ) {
			$ann     = $d->type === 'ann';
			$items[] = [
				'cle'        => (string) $d->cle,
				'type'       => (string) $d->type,
				'nom'        => (string) $d->club_nom,
				'discipline' => $ann ? '' : (string) $d->discipline,
				'titre'      => $ann ? '' : (string) $d->titre,
				'slogan'     => $ann ? (string) $d->slogan : '',
				'message'    => $ann ? (string) $d->message : '',
				'logo'       => (string) ( $d->logo_url ?? '' ), // annonceur, club ou structure candidate
				'heure'      => (string) ( $heures[ $d->cle ] ?? '' ),
				'duree'      => intval( $d->duree_sec ),
				'audio'      => $ann ? '' : (string) $d->audio_nom,
			];
		}
		return [
			'titre'   => (string) $r['titre'],
			'edition' => (string) $r['edition'],
			'date'    => (string) $r['date_gala'] !== '' ? Tkd_Gala_Front::date_fr( (string) $r['date_gala'] ) : '',
			'canal'   => 'tkd-gala-projection-' . sanitize_key( (string) $r['edition'] ),
			'items'   => $items,
		];
	}

	/** Adresse d'un écran ('salle' ou 'regie'), avec son jeton de sécurité. */
	public static function url( string $vue ): string {
		return add_query_arg( [ 'action' => 'tkd_gala_projection', 'vue' => $vue, '_wpnonce' => wp_create_nonce( 'tkd_gala_projection' ) ], admin_url( 'admin-post.php' ) );
	}

	public function afficher(): void {
		if ( ! current_user_can( Tkd_Gala_Admin::CAP ) ) wp_die( 'Accès refusé', 403 );
		check_admin_referer( 'tkd_gala_projection' );
		$vue  = sanitize_key( wp_unslash( $_GET['vue'] ?? 'regie' ) ) === 'salle' ? 'salle' : 'regie';
		$d    = self::donnees();
		$d['vue']        = $vue;
		$d['url_salle']  = self::url( 'salle' );
		$d['url_regie']  = self::url( 'regie' );
		$d['url_retour'] = admin_url( 'admin.php?page=tkd-gala&onglet=conducteur' );
		$ver  = TKD_GALA_VERSION . '-' . filemtime( TKD_GALA_DIR . 'assets/gala-projection.js' ) . '-' . filemtime( TKD_GALA_DIR . 'assets/gala-projection.css' );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( ( $vue === 'salle' ? 'Écran salle' : 'Régie' ) . ' — ' . $d['titre'] . ' ' . $d['edition'] ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( TKD_GALA_URL . 'assets/gala-projection.css?ver=' . rawurlencode( $ver ) ); ?>">
</head>
<body class="tkd-proj tkd-proj-<?php echo esc_attr( $vue ); ?>">
<div id="tkd-proj-app"></div>
<script type="application/json" id="tkd-proj-donnees"><?php echo wp_json_encode( $d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>
<script src="<?php echo esc_url( TKD_GALA_URL . 'assets/gala-projection.js?ver=' . rawurlencode( $ver ) ); ?>"></script>
</body>
</html>
		<?php
		exit;
	}
}
