<?php

namespace TkdProjets\Repository;

use TkdProjets\Db;
use TkdProjets\Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Équipe des projets : une ligne par personne et par projet (responsable ou membre). */
final class Members {

	/** @var array<string,?string> rôles déjà lus pendant cette requête */
	private static array $cache = array();

	public static function roleOf( int $projectId, int $userId ): ?string {
		if ( $userId <= 0 ) {
			return null;
		}
		$key = $projectId . ':' . $userId;
		if ( ! array_key_exists( $key, self::$cache ) ) {
			$wpdb = Db::wpdb();
			$m    = Db::table( 'members' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$role               = $wpdb->get_var( $wpdb->prepare( "SELECT role FROM $m WHERE project_id = %d AND user_id = %d", $projectId, $userId ) );
			self::$cache[ $key ] = $role ? (string) $role : null;
		}
		return self::$cache[ $key ];
	}

	/** @return array<int,string> user_id => rôle */
	public static function forProject( int $projectId ): array {
		$wpdb = Db::wpdb();
		$m    = Db::table( 'members' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, role FROM $m WHERE project_id = %d", $projectId ) );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->user_id ] = (string) $r->role;
		}
		return $out;
	}

	/** Remplace toute l'équipe d'un projet. @param array<int,string> $team */
	public static function replace( int $projectId, array $team ): void {
		$wpdb = Db::wpdb();
		$wpdb->delete( Db::table( 'members' ), array( 'project_id' => $projectId ), array( '%d' ) );
		foreach ( $team as $uid => $role ) {
			self::add( $projectId, (int) $uid, $role );
		}
		self::$cache = array();
	}

	/** Ajoute une personne si elle n'est pas déjà dans l'équipe (son rôle actuel est gardé). */
	public static function add( int $projectId, int $userId, string $role = Policy::MEMBRE ): void {
		$wpdb = Db::wpdb();
		$m    = Db::table( 'members' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $m (project_id, user_id, role) VALUES (%d, %d, %s)", $projectId, $userId, $role ) );
		unset( self::$cache[ $projectId . ':' . $userId ] );
	}

	public static function hasAnyProject( int $userId ): bool {
		$wpdb = Db::wpdb();
		$m    = Db::table( 'members' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $m WHERE user_id = %d LIMIT 1", $userId ) );
	}

	public static function isResponsableSomewhere( int $userId ): bool {
		$wpdb = Db::wpdb();
		$m    = Db::table( 'members' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $m WHERE user_id = %d AND role = %s LIMIT 1", $userId, Policy::RESPONSABLE ) );
	}

	/** @return int[] projets dont la personne fait partie */
	public static function projectsOf( int $userId ): array {
		$wpdb = Db::wpdb();
		$m    = Db::table( 'members' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT project_id FROM $m WHERE user_id = %d", $userId ) ) );
	}

	public static function removeUser( int $userId ): int {
		self::$cache = array();
		return (int) Db::wpdb()->delete( Db::table( 'members' ), array( 'user_id' => $userId ), array( '%d' ) );
	}
}
