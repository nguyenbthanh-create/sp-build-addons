<?php

namespace TkdProjets\Repository;

use TkdProjets\Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Liens privés « Ma carte » (empreintes des clés). */
final class Links {

	public static function create( int $taskId, int $userId, string $keyHash, string $expiresAt ): void {
		$wpdb = Db::wpdb();
		$ok   = $wpdb->insert(
			Db::table( 'links' ),
			array(
				'task_id'    => $taskId,
				'user_id'    => $userId,
				'key_hash'   => $keyHash,
				'created_at' => current_time( 'mysql' ),
				'expires_at' => $expiresAt,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'Le lien de la carte n\'a pas pu être créé.' );
		}
	}

	public static function findByHash( string $keyHash ): ?object {
		$wpdb = Db::wpdb();
		$l    = Db::table( 'links' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $l WHERE key_hash = %s LIMIT 1", $keyHash ) );
		return $row ?: null;
	}

	/** Coupe les liens d'une tâche (de toutes les personnes, ou d'une seule). Renvoie le nombre de liens coupés. */
	public static function revoke( int $taskId, ?int $userId = null ): int {
		$wpdb = Db::wpdb();
		$l    = Db::table( 'links' );
		$now  = current_time( 'mysql' );
		if ( null === $userId ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->query( $wpdb->prepare( "UPDATE $l SET revoked_at = %s WHERE task_id = %d AND revoked_at IS NULL", $now, $taskId ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE $l SET revoked_at = %s WHERE task_id = %d AND user_id = %d AND revoked_at IS NULL", $now, $taskId, $userId ) );
	}

	public static function deleteForTask( int $taskId ): void {
		Db::wpdb()->delete( Db::table( 'links' ), array( 'task_id' => $taskId ), array( '%d' ) );
	}

	public static function deleteForUser( int $userId ): int {
		return (int) Db::wpdb()->delete( Db::table( 'links' ), array( 'user_id' => $userId ), array( '%d' ) );
	}
}
