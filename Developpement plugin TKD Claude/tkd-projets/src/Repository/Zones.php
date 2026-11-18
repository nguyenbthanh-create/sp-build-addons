<?php

namespace TkdProjets\Repository;

use TkdProjets\Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Zones dessinées sur la carte d'une tâche. */
final class Zones {

	/** @return object[] */
	public static function forTask( int $taskId, ?int $assignee = null ): array {
		$wpdb = Db::wpdb();
		$z    = Db::table( 'zones' );
		if ( null === $assignee ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $z WHERE task_id = %d ORDER BY position ASC, id ASC", $taskId ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $z WHERE task_id = %d AND assignee = %d ORDER BY position ASC, id ASC", $taskId, $assignee ) );
	}

	/** Nombre de zones par tâche pour tout un projet (une seule requête). @return array<int,int> */
	public static function countsForProject( int $projectId ): array {
		$wpdb = Db::wpdb();
		$z    = Db::table( 'zones' );
		$t    = Db::table( 'tasks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT z.task_id, COUNT(*) AS n FROM $z z JOIN $t t ON t.id = z.task_id WHERE t.project_id = %d GROUP BY z.task_id", $projectId ) );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->task_id ] = (int) $r->n;
		}
		return $out;
	}

	public static function countForTask( int $taskId ): int {
		$wpdb = Db::wpdb();
		$z    = Db::table( 'zones' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $z WHERE task_id = %d", $taskId ) );
	}

	/** @return int[] personnes ayant au moins une zone sur la tâche */
	public static function assigneesOf( int $taskId ): array {
		$wpdb = Db::wpdb();
		$z    = Db::table( 'zones' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT assignee FROM $z WHERE task_id = %d AND assignee > 0", $taskId ) ) );
	}

	public static function insert( array $fields ): int {
		$wpdb = Db::wpdb();
		if ( false === $wpdb->insert( Db::table( 'zones' ), $fields ) ) {
			throw new \RuntimeException( 'Une zone n\'a pas pu être enregistrée.' );
		}
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $fields ): void {
		Db::wpdb()->update( Db::table( 'zones' ), $fields, array( 'id' => $id ) );
	}

	public static function delete( int $id ): void {
		Db::wpdb()->delete( Db::table( 'zones' ), array( 'id' => $id ), array( '%d' ) );
	}

	public static function deleteForTask( int $taskId ): void {
		Db::wpdb()->delete( Db::table( 'zones' ), array( 'task_id' => $taskId ), array( '%d' ) );
	}

	public static function unassignUser( int $userId ): int {
		return (int) Db::wpdb()->update( Db::table( 'zones' ), array( 'assignee' => 0 ), array( 'assignee' => $userId ), array( '%d' ), array( '%d' ) );
	}

	/** @return object[] */
	public static function assignedTo( int $userId ): array {
		$wpdb = Db::wpdb();
		$z    = Db::table( 'zones' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $z WHERE assignee = %d ORDER BY id", $userId ) );
	}
}
