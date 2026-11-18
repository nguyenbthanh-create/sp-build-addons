<?php

namespace TkdProjets\Repository;

use TkdProjets\Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Table des projets (avec les compteurs calculés à partir des tâches). */
final class Projects {

	private static function selectWithStats(): string {
		$p = Db::table( 'projects' );
		$t = Db::table( 'tasks' );
		return "SELECT p.*, COUNT(t.id) AS task_count,
			SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_count,
			LEAST(COALESCE(MIN(t.start_date), MIN(t.end_date)), COALESCE(MIN(t.end_date), MIN(t.start_date))) AS first_date,
			GREATEST(COALESCE(MAX(t.end_date), MAX(t.start_date)), COALESCE(MAX(t.start_date), MAX(t.end_date))) AS last_date
			FROM $p p LEFT JOIN $t t ON t.project_id = p.id";
	}

	public static function find( int $id ): ?object {
		$wpdb = Db::wpdb();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( self::selectWithStats() . ' WHERE p.id = %d GROUP BY p.id', $id ) );
		return $row ?: null;
	}

	/**
	 * Projets visibles : tous (null) ou ceux dont la personne fait partie de l'équipe.
	 *
	 * @return object[]
	 */
	public static function listFor( ?int $userId ): array {
		$wpdb = Db::wpdb();
		if ( null === $userId ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( self::selectWithStats() . ' GROUP BY p.id ORDER BY p.created_at DESC' );
		}
		$m = Db::table( 'members' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( self::selectWithStats() . " WHERE p.id IN (SELECT project_id FROM $m WHERE user_id = %d) GROUP BY p.id ORDER BY p.created_at DESC", $userId ) );
	}

	public static function insert( array $fields ): int {
		$wpdb = Db::wpdb();
		if ( false === $wpdb->insert( Db::table( 'projects' ), $fields ) ) {
			throw new \RuntimeException( 'Le projet n\'a pas pu être enregistré.' );
		}
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $fields ): void {
		$fields['updated_at'] = current_time( 'mysql' );
		Db::wpdb()->update( Db::table( 'projects' ), $fields, array( 'id' => $id ) );
	}

	/** Supprime le projet, ses tâches, zones, liens de carte et son équipe (tout ou rien). */
	public static function delete( int $id ): void {
		Db::transaction(
			static function () use ( $id ) {
				$wpdb = Db::wpdb();
				foreach ( Tasks::idsForProject( $id ) as $taskId ) {
					Zones::deleteForTask( $taskId );
					Links::deleteForTask( $taskId );
				}
				$wpdb->delete( Db::table( 'tasks' ), array( 'project_id' => $id ), array( '%d' ) );
				$wpdb->delete( Db::table( 'members' ), array( 'project_id' => $id ), array( '%d' ) );
				$wpdb->delete( Db::table( 'projects' ), array( 'id' => $id ), array( '%d' ) );
			}
		);
	}
}
