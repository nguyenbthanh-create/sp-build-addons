<?php

namespace TkdProjets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Noms des tables et transactions. */
final class Db {

	public const TABLES = array( 'projects', 'tasks', 'zones', 'members', 'links' );

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'tkdp_' . $name;
	}

	public static function wpdb(): \wpdb {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Exécute $fn dans une transaction : tout ou rien (tables InnoDB).
	 *
	 * @template T
	 * @param callable():T $fn
	 * @return T
	 */
	public static function transaction( callable $fn ) {
		$wpdb = self::wpdb();
		$wpdb->query( 'START TRANSACTION' );
		try {
			$result = $fn();
			$wpdb->query( 'COMMIT' );
			return $result;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}
}
