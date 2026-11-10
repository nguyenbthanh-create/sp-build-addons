<?php
/**
 * Pistes audio des démonstrations.
 *
 * Rangées hors de la médiathèque, dans wp-content/uploads/tkd-gala/<édition>/, sous un nom
 * aléatoire, avec un .htaccess qui interdit tout accès direct. On les écoute uniquement par
 * ?tkd_gala_audio=<id de démonstration>, avec le jeton du club ou en administrateur
 * connecté. Les requêtes « Range » sont gérées : sans elles, Safari (iPhone) ne lit pas
 * l'audio dans la balise <audio>.
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Audio {

	private static ?self $instance = null;

	const EXTENSIONS = [ 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav' ];

	public static function get_instance(): self {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'maybe_servir' ] );
	}

	// ─── Dossier ────────────────────────────────────────────────────────────────

	public static function dossier( string $edition ): string {
		$up = wp_upload_dir( null, false );
		return trailingslashit( $up['basedir'] ) . 'tkd-gala/' . sanitize_file_name( $edition ) . '/';
	}

	private static function preparer_dossier( string $dir ): bool {
		if ( ! wp_mkdir_p( $dir ) ) return false;
		$racine = dirname( $dir ) . '/';
		foreach ( [ $racine, $dir ] as $d ) {
			if ( ! file_exists( $d . '.htaccess' ) ) {
				file_put_contents( $d . '.htaccess', "# TKD Gala : pistes audio servies uniquement par l'extension\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
			}
			if ( ! file_exists( $d . 'index.php' ) ) file_put_contents( $d . 'index.php', "<?php\n// Silence.\n" );
		}
		return true;
	}

	public static function taille_max(): int {
		$mo = (float) Tkd_Gala_DB::reglages()['audio_max_mo'];
		return (int) min( $mo * MB_IN_BYTES, wp_max_upload_size() );
	}

	// ─── Envoi ──────────────────────────────────────────────────────────────────

	/** Contrôle d'un fichier envoyé ; '' = accepté, sinon le message d'erreur. */
	public static function verifier( array $f ): string {
		$code = intval( $f['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( in_array( $code, [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) ) return 'fichier trop lourd (' . size_format( self::taille_max() ) . ' au maximum).';
		if ( $code === UPLOAD_ERR_PARTIAL ) return 'l\'envoi du fichier a été interrompu, réessayez.';
		if ( $code !== UPLOAD_ERR_OK || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) return 'le fichier n\'a pas pu être reçu.';
		if ( intval( $f['size'] ) > self::taille_max() ) return 'fichier trop lourd (' . size_format( self::taille_max() ) . ' au maximum).';

		$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		if ( ! isset( self::EXTENSIONS[ $ext ] ) ) return 'format non accepté (MP3, M4A, AAC ou WAV).';
		// Contenu réellement audio (un M4A est souvent reconnu comme « video/mp4 », même conteneur).
		if ( function_exists( 'finfo_open' ) ) {
			$fi   = finfo_open( FILEINFO_MIME_TYPE );
			$mime = $fi ? (string) finfo_file( $fi, $f['tmp_name'] ) : '';
			if ( $fi ) finfo_close( $fi );
			$ok = $mime === '' || strpos( $mime, 'audio/' ) === 0 || in_array( $mime, [ 'video/mp4', 'application/octet-stream' ], true );
			if ( ! $ok ) return 'ce fichier n\'est pas un fichier audio.';
		}
		return '';
	}

	/** Range le fichier (déjà vérifié) et le rattache à la démonstration ; remplace l'ancien. */
	public static function enregistrer_envoi( array $f, int $demo_id ): bool {
		$demo = Tkd_Gala_DB::demo( $demo_id );
		$ins  = $demo ? Tkd_Gala_DB::inscription( intval( $demo->inscription_id ) ) : null;
		if ( ! $demo || ! $ins ) return false;

		$dir = self::dossier( $ins->edition );
		if ( ! self::preparer_dossier( $dir ) ) { error_log( '[TKD Gala] dossier audio impossible à créer : ' . $dir ); return false; }

		$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		$nom = 'club' . intval( $ins->id ) . '-demo' . $demo_id . '-' . bin2hex( random_bytes( 6 ) ) . '.' . $ext;
		if ( ! move_uploaded_file( $f['tmp_name'], $dir . $nom ) ) { error_log( '[TKD Gala] move_uploaded_file a échoué vers ' . $dir . $nom ); return false; }

		self::supprimer_fichier( $demo );
		Tkd_Gala_DB::attacher_audio( $demo_id, $nom, mb_substr( sanitize_file_name( (string) $f['name'] ), 0, 255 ), intval( $f['size'] ) );
		return true;
	}

	/**
	 * Chemin du fichier d'une démonstration de club (édition lue sur son inscription) ou d'une
	 * candidature retenue (objet qui porte lui-même son « edition »).
	 */
	public static function chemin( object $demo ): string {
		if ( (string) $demo->audio_fichier === '' ) return '';
		if ( isset( $demo->edition ) ) return self::dossier( (string) $demo->edition ) . basename( (string) $demo->audio_fichier );
		$ins = Tkd_Gala_DB::inscription( intval( $demo->inscription_id ) );
		return $ins ? self::dossier( $ins->edition ) . basename( (string) $demo->audio_fichier ) : '';
	}

	/** Piste d'une candidature retenue, déposée par l'organisation ; remplace l'ancienne. */
	public static function enregistrer_envoi_externe( array $f, int $externe_id ): bool {
		$x = Tkd_Gala_Candidatures::externe( $externe_id );
		if ( ! $x ) return false;
		$dir = self::dossier( $x->edition );
		if ( ! self::preparer_dossier( $dir ) ) { error_log( '[TKD Gala] dossier audio impossible à créer : ' . $dir ); return false; }
		$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		$nom = 'candidature' . intval( $x->submission_id ) . '-' . bin2hex( random_bytes( 6 ) ) . '.' . $ext;
		if ( ! move_uploaded_file( $f['tmp_name'], $dir . $nom ) ) { error_log( '[TKD Gala] move_uploaded_file a échoué vers ' . $dir . $nom ); return false; }
		self::supprimer_fichier( $x );
		Tkd_Gala_Candidatures::attacher_audio( $externe_id, $nom, mb_substr( sanitize_file_name( (string) $f['name'] ), 0, 255 ), intval( $f['size'] ) );
		return true;
	}

	public static function supprimer_fichier( object $demo ): void {
		$p = self::chemin( $demo );
		if ( $p !== '' && is_file( $p ) ) @unlink( $p ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- fichier déjà absent : sans importance
	}

	public static function supprimer_dossier( string $edition ): void {
		$dir = self::dossier( $edition );
		if ( ! is_dir( $dir ) ) return;
		foreach ( (array) scandir( $dir ) as $f ) if ( is_file( $dir . $f ) ) @unlink( $dir . $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	// ─── Écoute / téléchargement ────────────────────────────────────────────────

	public static function url_ecoute( int $demo_id, string $token = '' ): string {
		$args = [ 'tkd_gala_audio' => $demo_id ];
		if ( $token !== '' ) $args['gala'] = $token;
		return add_query_arg( $args, home_url( '/' ) );
	}

	/** Écoute d'une piste de candidature : administrateurs seulement. */
	public static function url_ecoute_externe( int $externe_id ): string {
		return add_query_arg( 'tkd_gala_audio_ext', $externe_id, home_url( '/' ) );
	}

	public function maybe_servir(): void {
		if ( ! empty( $_GET['tkd_gala_audio_ext'] ) ) {
			$x    = current_user_can( 'manage_options' ) ? Tkd_Gala_Candidatures::externe( intval( $_GET['tkd_gala_audio_ext'] ) ) : null;
			$path = $x ? self::chemin( $x ) : '';
			if ( $path === '' || ! is_file( $path ) ) { status_header( 404 ); nocache_headers(); exit( 'Piste introuvable.' ); }
			self::envoyer_fichier( $path, (string) ( $x->audio_nom ?: basename( $path ) ), ! empty( $_GET['telecharger'] ) );
		}
		if ( empty( $_GET['tkd_gala_audio'] ) ) return;
		$demo = Tkd_Gala_DB::demo( intval( $_GET['tkd_gala_audio'] ) );
		$ins  = $demo ? Tkd_Gala_DB::inscription( intval( $demo->inscription_id ) ) : null;
		$tok  = sanitize_text_field( wp_unslash( $_GET['gala'] ?? '' ) );
		$ok   = $ins && ( current_user_can( 'manage_options' ) || ( $tok !== '' && hash_equals( (string) $ins->token, $tok ) ) );
		$path = $ok ? self::chemin( $demo ) : '';
		if ( $path === '' || ! is_file( $path ) ) { status_header( 404 ); nocache_headers(); exit( 'Piste introuvable.' ); }

		$telecharger = ! empty( $_GET['telecharger'] );
		self::envoyer_fichier( $path, (string) ( $demo->audio_nom ?: basename( $path ) ), $telecharger );
	}

	/** Envoie un fichier audio en gérant « Range » (lecture et avance rapide sur iPhone). */
	private static function envoyer_fichier( string $path, string $nom, bool $telecharger ): void {
		$taille = filesize( $path );
		$ext    = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$debut  = 0;
		$fin    = $taille - 1;

		while ( ob_get_level() ) ob_end_clean();
		header( 'Content-Type: ' . ( self::EXTENSIONS[ $ext ] ?? 'application/octet-stream' ) );
		header( 'Accept-Ranges: bytes' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, max-age=3600' );
		header( 'Content-Disposition: ' . ( $telecharger ? 'attachment' : 'inline' ) . '; filename="' . str_replace( '"', '', $nom ) . '"' );

		$range = sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ?? '' ) );
		if ( $range !== '' && preg_match( '/bytes=(\d*)-(\d*)/', $range, $m ) ) {
			if ( $m[1] === '' && $m[2] !== '' ) { $debut = max( 0, $taille - intval( $m[2] ) ); }
			else { $debut = intval( $m[1] ); if ( $m[2] !== '' ) $fin = min( intval( $m[2] ), $taille - 1 ); }
			if ( $debut > $fin || $debut >= $taille ) {
				status_header( 416 );
				header( 'Content-Range: bytes */' . $taille );
				exit;
			}
			status_header( 206 );
			header( 'Content-Range: bytes ' . $debut . '-' . $fin . '/' . $taille );
		}
		header( 'Content-Length: ' . ( $fin - $debut + 1 ) );

		$fh = fopen( $path, 'rb' );
		if ( $fh ) {
			fseek( $fh, $debut );
			$reste = $fin - $debut + 1;
			while ( $reste > 0 && ! feof( $fh ) ) {
				$bloc = fread( $fh, (int) min( 65536, $reste ) );
				if ( $bloc === false ) break;
				echo $bloc; // phpcs:ignore WordPress.Security.EscapeOutput -- contenu binaire du fichier audio
				$reste -= strlen( $bloc );
				flush();
			}
			fclose( $fh );
		}
		exit;
	}
}
