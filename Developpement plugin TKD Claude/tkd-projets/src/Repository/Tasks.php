<?php

namespace TkdProjets\Repository;

use TkdProjets\Db;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Table des tâches. */
final class Tasks {

	public static function find( int $id ): ?object {
		$wpdb = Db::wpdb();
		$t    = Db::table( 'tasks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
		return $row ?: null;
	}

	/** @return object[] */
	public static function forProject( int $projectId ): array {
		$wpdb = Db::wpdb();
		$t    = Db::table( 'tasks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE project_id = %d ORDER BY position ASC, id ASC", $projectId ) );
	}

	/** @return int[] */
	public static function idsForProject( int $projectId ): array {
		$wpdb = Db::wpdb();
		$t    = Db::table( 'tasks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE project_id = %d", $projectId ) ) );
	}

	public static function insert( int $projectId, array $fields ): int {
		$wpdb = Db::wpdb();
		$t    = Db::table( 'tasks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max                  = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(position) FROM $t WHERE project_id = %d", $projectId ) );
		$fields['project_id'] = $projectId;
		$fields['position']   = null === $max ? 0 : (int) $max + 1;
		$fields['created_at'] = current_time( 'mysql' );
		if ( false === $wpdb->insert( $t, $fields ) ) {
			throw new \RuntimeException( 'La tâche n\'a pas pu être enregistrée.' );
		}
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $fields ): void {
		if ( $fields ) {
			Db::wpdb()->update( Db::table( 'tasks' ), $fields, array( 'id' => $id ) );
		}
	}

	public static function delete( int $id ): void {
		Db::transaction(
			static function () use ( $id ) {
				$wpdb = Db::wpdb();
				$t    = Db::table( 'tasks' );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "UPDATE $t SET depends_on = NULL WHERE depends_on = %d", $id ) );
				Zones::deleteForTask( $id );
				Links::deleteForTask( $id );
				$wpdb->delete( $t, array( 'id' => $id ), array( '%d' ) );
			}
		);
	}

	/** Nouvel ordre des tâches d'un projet (en une requête). @param int[] $order */
	public static function reorder( int $projectId, array $order ): void {
		$order = array_values( array_filter( array_map( 'absint', $order ) ) );
		if ( ! $order ) {
			return;
		}
		$wpdb  = Db::wpdb();
		$t     = Db::table( 'tasks' );
		$cases = '';
		$args  = array();
		foreach ( $order as $i => $id ) {
			$cases .= ' WHEN %d THEN %d';
			$args[] = $id;
			$args[] = $i;
		}
		$in   = implode( ',', array_fill( 0, count( $order ), '%d' ) );
		$args = array_merge( $args, $order, array( $projectId ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET position = CASE id$cases ELSE position END WHERE id IN ($in) AND project_id = %d", $args ) );
	}

	/** Efface la personne de toutes les tâches (droit à l'effacement). */
	public static function unassignUser( int $userId ): int {
		return (int) Db::wpdb()->update( Db::table( 'tasks' ), array( 'assignee' => 0 ), array( 'assignee' => $userId ), array( '%d' ), array( '%d' ) );
	}

	/** @return object[] tâches attribuées à une personne */
	public static function assignedTo( int $userId ): array {
		$wpdb = Db::wpdb();
		$t    = Db::table( 'tasks' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE assignee = %d ORDER BY id", $userId ) );
	}
}
