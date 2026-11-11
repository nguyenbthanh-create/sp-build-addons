<?php
/**
 * Logos des clubs et des structures candidates (11/11/2026), rangés dans la médiathèque.
 *
 *  - Formulaire des clubs : champ « Logo du club » (facultatif) ; l'image devient un média
 *    WordPress dont l'id est gardé dans tkd_gala_inscriptions.logo_id.
 *  - Formulaire de candidature Elementor : champ « Logo » (type Envoi de fichier, id struct_logo).
 *    Elementor garde le fichier dans uploads/elementor/forms/ ; l'extension en fait une copie
 *    dans la médiathèque à la première lecture de la candidature (onglet Candidatures,
 *    Conducteur, Projection) et garde l'id dans tkd_gala_externes.logo_id.
 *
 * Les logos servent au Conducteur et à l'écran de projection. Ils sont publics (médiathèque).
 * Chaque média créé ici porte la marque _tkd_gala_logo (« club » ou « candidature »).
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Logos {

	const MAX_OCTETS = 5 * MB_IN_BYTES;
	const TYPES      = [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ];
	const META       = '_tkd_gala_logo';

	/** Contrôle d'un logo envoyé ; '' = accepté, sinon le message d'erreur. */
	public static function verifier( array $f ): string {
		$code = intval( $f['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( in_array( $code, [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) ) return 'logo trop lourd (' . size_format( self::MAX_OCTETS ) . ' au maximum).';
		if ( $code !== UPLOAD_ERR_OK || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) return 'le logo n\'a pas pu être reçu.';
		if ( intval( $f['size'] ) > self::MAX_OCTETS ) return 'logo trop lourd (' . size_format( self::MAX_OCTETS ) . ' au maximum).';
		$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		if ( ! isset( self::TYPES[ $ext ] ) ) return 'format de logo non accepté (PNG, JPG ou WebP).';
		$info = @getimagesize( $f['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- fichier non image : false
		if ( ! $info || ! in_array( $info['mime'] ?? '', self::TYPES, true ) ) return 'ce fichier n\'est pas une image PNG, JPG ou WebP.';
		return '';
	}

	/**
	 * Range un fichier (déjà contrôlé) dans la médiathèque ; renvoie l'id du média, 0 en cas
	 * d'échec. $chemin : fichier temporaire de l'envoi ; il est déplacé par WordPress.
	 */
	public static function vers_mediatheque( string $chemin, string $nom, string $titre, string $origine ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload( [ 'name' => sanitize_file_name( $nom ), 'tmp_name' => $chemin ], 0, $titre, [ 'post_title' => $titre ] );
		if ( is_wp_error( $id ) ) {
			error_log( '[TKD Gala] logo non enregistré dans la médiathèque : ' . $id->get_error_message() );
			return 0;
		}
		update_post_meta( (int) $id, self::META, $origine );
		return (int) $id;
	}

	/** Logo envoyé par le formulaire des clubs : média créé et rattaché à l'inscription. */
	public static function enregistrer_club( array $f, int $inscription_id, string $club ): bool {
		$id = self::vers_mediatheque( (string) $f['tmp_name'], (string) $f['name'], 'Logo — ' . $club, 'club' );
		if ( ! $id ) return false;
		global $wpdb;
		$wpdb->update( Tkd_Gala_DB::t_inscriptions(), [ 'logo_id' => $id ], [ 'id' => $inscription_id ] );
		return true;
	}

	public static function url( int $id, string $taille = 'medium' ): string {
		if ( ! $id || ! wp_attachment_is_image( $id ) ) return '';
		$src = wp_get_attachment_image_url( $id, $taille );
		return $src ? (string) $src : '';
	}

	/**
	 * Logo d'une candidature Elementor (valeur du champ struct_logo = adresse du fichier dans
	 * uploads/) : copié une fois dans la médiathèque, id gardé sur la ligne tkd_gala_externes.
	 * Renvoie l'id du média, 0 s'il n'y a pas de logo exploitable.
	 */
	public static function candidature( object $c, string $edition ): int {
		$x = Tkd_Gala_Candidatures::externes( $edition )[ intval( $c->id ) ] ?? null;
		if ( $x && intval( $x->logo_id ?? 0 ) && get_post_type( intval( $x->logo_id ) ) === 'attachment' ) return intval( $x->logo_id );

		$url = trim( explode( ',', (string) ( $c->v['struct_logo'] ?? '' ) )[0] );
		if ( $url === '' ) return 0;
		$up   = wp_upload_dir( null, false );
		$base = trailingslashit( (string) $up['baseurl'] );
		// Seuls les fichiers du site (uploads/) sont repris, jamais une adresse extérieure.
		$url_sans_proto = preg_replace( '#^https?:#', '', $url );
		$base_sans      = preg_replace( '#^https?:#', '', $base );
		if ( ! str_starts_with( (string) $url_sans_proto, (string) $base_sans ) ) return 0;
		$rel    = rawurldecode( substr( (string) $url_sans_proto, strlen( (string) $base_sans ) ) );
		$source = wp_normalize_path( trailingslashit( (string) $up['basedir'] ) . $rel );
		$reel   = wp_normalize_path( (string) realpath( $source ) );
		if ( $reel === '' || ! str_starts_with( $reel, wp_normalize_path( trailingslashit( (string) $up['basedir'] ) ) ) || ! is_file( $reel ) ) return 0;
		$ext = strtolower( pathinfo( $reel, PATHINFO_EXTENSION ) );
		$info = @getimagesize( $reel ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! isset( self::TYPES[ $ext ] ) || ! $info || ! in_array( $info['mime'] ?? '', self::TYPES, true ) || filesize( $reel ) > self::MAX_OCTETS ) return 0;

		// Copie temporaire : le fichier d'Elementor reste en place (pièce de l'envoi).
		$tmp = wp_tempnam( basename( $reel ) );
		if ( ! $tmp || ! copy( $reel, $tmp ) ) return 0;
		$nom = sanitize_title( (string) ( $c->v['struct_nom'] ?? 'structure' ) ) . '-logo.' . $ext;
		$id  = self::vers_mediatheque( $tmp, $nom, 'Logo — ' . (string) ( $c->v['struct_nom'] ?? 'structure' ), 'candidature' );
		if ( is_file( $tmp ) ) @unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $id ) return 0;

		$ligne = Tkd_Gala_Candidatures::assurer_externe( intval( $c->id ), $edition );
		global $wpdb;
		$wpdb->update( Tkd_Gala_Candidatures::t_externes(), [ 'logo_id' => $id ], [ 'id' => intval( $ligne->id ) ] );
		return $id;
	}
}
