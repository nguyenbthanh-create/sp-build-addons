<?php

namespace TkdProjets;

/**
 * Pièces jointes des tâches (fichiers de la médiathèque).
 *
 * Correctif de sécurité de la 2.0 : en 1.x, n'importe quel numéro de fichier était accepté, et le
 * fichier était lu sur le disque pour être joint au mail. Un contributeur pouvait ainsi se faire
 * envoyer un justificatif protégé de SP Compta. Désormais un fichier n'est accepté, affiché ou joint
 * que s'il est dans la partie publique de la médiathèque et lisible par la personne connectée.
 */
final class Attachments {

	/** Dossiers de « uploads » jamais acceptés (fichiers protégés d'autres extensions). */
	public const PRIVATE_DIRS = array( 'sp-compta', 'tkd-gala' );

	/** Marque posée par SP Compta sur ses justificatifs. */
	public const SP_COMPTA_META = '_sp_compta_protege';

	/**
	 * Le chemin réel est-il dans « uploads », hors des dossiers protégés ? (sans WordPress)
	 */
	public static function isPublicPath( string $path, string $uploadsDir ): bool {
		$norm = static fn( string $p ) => rtrim( str_replace( '\\', '/', $p ), '/' );
		$real = realpath( $path );
		$base = realpath( $uploadsDir );
		if ( false === $real || false === $base ) {
			return false;
		}
		$real = $norm( $real );
		$base = $norm( $base );
		if ( 0 !== strpos( $real, $base . '/' ) ) {
			return false;
		}
		$first = explode( '/', substr( $real, strlen( $base ) + 1 ) )[0];
		return ! in_array( $first, self::PRIVATE_DIRS, true );
	}

	/**
	 * Un fichier de la médiathèque peut-il être joint à une tâche ?
	 *
	 * @param bool $checkReader Vérifier aussi que la personne connectée peut le lire. Faux seulement pour
	 *                          la page « Ma carte » (visiteur sans compte), sur des fichiers déjà contrôlés à l'enregistrement.
	 */
	public static function isAllowed( int $id, bool $checkReader = true ): bool {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
			return false;
		}
		if ( get_post_meta( $id, self::SP_COMPTA_META, true ) ) {
			return false;
		}
		if ( $checkReader && ! current_user_can( 'read_post', $id ) ) {
			return false;
		}
		$path = get_attached_file( $id );
		$dir  = wp_upload_dir();
		return $path && empty( $dir['error'] ) && self::isPublicPath( $path, $dir['basedir'] );
	}

	/**
	 * Nettoie une liste de pièces jointes reçue.
	 *
	 * @param mixed $value Tableau ou « 12,15 ».
	 * @return string|null « 12,15 » ou null.
	 */
	public static function clean( $value ): ?string {
		$ids = array_values( array_filter( Validation::ids( $value, 20 ), array( self::class, 'isAllowed' ) ) );
		return $ids ? implode( ',', $ids ) : null;
	}

	/**
	 * Détails des pièces jointes d'une tâche (seulement celles encore autorisées).
	 *
	 * @param string|null $stored      « 12,15 ».
	 * @param bool        $checkReader Voir isAllowed().
	 * @return array<int,array{id:int,title:string,filename:string,url:string,path:string,size:int,thumb:string}>
	 */
	public static function files( ?string $stored, bool $checkReader = true ): array {
		$files = array();
		foreach ( Validation::ids( (string) $stored, 20 ) as $id ) {
			if ( ! self::isAllowed( $id, $checkReader ) ) {
				continue;
			}
			$path = (string) get_attached_file( $id );
			$url  = wp_get_attachment_url( $id );
			if ( ! $url ) {
				continue;
			}
			$exists  = '' !== $path && file_exists( $path );
			$files[] = array(
				'id'       => $id,
				'title'    => get_the_title( $id ),
				'filename' => wp_basename( $exists ? $path : $url ),
				'url'      => $url,
				'path'     => $exists ? $path : '',
				'size'     => $exists ? (int) filesize( $path ) : 0,
				'thumb'    => wp_attachment_is_image( $id ) ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '',
			);
		}
		return $files;
	}

	/** Version envoyée au navigateur : jamais le chemin sur le serveur. */
	public static function forBrowser( ?string $stored ): array {
		return array_map(
			static function ( array $f ): array {
				unset( $f['path'] );
				return $f;
			},
			self::files( $stored )
		);
	}
}
