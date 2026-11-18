<?php

namespace TkdProjets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tables de l'extension et mises à jour du schéma.
 *
 * Les tables de la version 1.x (projects, tasks, zones) sont gardées : dbDelta() n'ajoute que
 * les nouvelles colonnes. Toute modification du schéma passe par un changement de TKDP_VERSION.
 */
final class Installer {

	public const OPTION_DB = 'tkdp_db_version';

	public static function maybeUpgrade(): void {
		if ( get_option( self::OPTION_DB ) !== TKDP_VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$previous = (string) get_option( self::OPTION_DB, '' );
		$charset  = $wpdb->get_charset_collate();
		$projects = Db::table( 'projects' );
		$tasks    = Db::table( 'tasks' );
		$zones    = Db::table( 'zones' );
		$members  = Db::table( 'members' );
		$links    = Db::table( 'links' );

		dbDelta(
			"CREATE TABLE $projects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'autre',
			status varchar(20) NOT NULL DEFAULT 'en_cours',
			description text NULL,
			color varchar(7) NOT NULL DEFAULT '#2271b1',
			event_date date NULL,
			compta_projet_id bigint(20) unsigned NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset;"
		);

		dbDelta(
			"CREATE TABLE $tasks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			description text NULL,
			status varchar(10) NOT NULL DEFAULT 'todo',
			priority varchar(10) NOT NULL DEFAULT 'normal',
			assignee bigint(20) unsigned NOT NULL DEFAULT 0,
			assignee_role varchar(60) NOT NULL DEFAULT '',
			start_date date NULL,
			end_date date NULL,
			progress tinyint(3) unsigned NOT NULL DEFAULT 0,
			depends_on bigint(20) unsigned NULL,
			attachments text NULL,
			position int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY assignee (assignee)
		) $charset;"
		);

		dbDelta(
			"CREATE TABLE $zones (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			task_id bigint(20) unsigned NOT NULL,
			name varchar(190) NOT NULL,
			color varchar(7) NOT NULL DEFAULT '#2271b1',
			assignee bigint(20) unsigned NOT NULL DEFAULT 0,
			coords longtext NOT NULL,
			notes text NULL,
			position int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY task_id (task_id),
			KEY assignee (assignee)
		) $charset;"
		);

		// Équipe d'un projet : responsable(s) et membres.
		dbDelta(
			"CREATE TABLE $members (
			project_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL DEFAULT 'membre',
			PRIMARY KEY  (project_id,user_id),
			KEY user_id (user_id)
		) $charset;"
		);

		// Liens privés « Ma carte » : seule l'empreinte de la clé est gardée.
		dbDelta(
			"CREATE TABLE $links (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			task_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			key_hash char(64) NOT NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			revoked_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY key_hash (key_hash),
			KEY task_user (task_id,user_id)
		) $charset;"
		);

		if ( '' !== $previous && version_compare( $previous, '2.0.0', '<' ) ) {
			self::upgradeFrom1x();
		}

		Access::registerCapability();
		update_option( self::OPTION_DB, TKDP_VERSION );
	}

	/**
	 * Passage de la 1.x à la 2.0 : l'ancienne clé en clair des liens de carte disparaît
	 * (les anciens liens cessent de marcher), et le créateur de chaque projet devient son responsable.
	 */
	private static function upgradeFrom1x(): void {
		global $wpdb;
		$zones    = Db::table( 'zones' );
		$projects = Db::table( 'projects' );
		$members  = Db::table( 'members' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $zones LIKE %s", 'view_key' ) ) ) {
			$wpdb->query( "ALTER TABLE $zones DROP INDEX view_key" );
			$wpdb->query( "ALTER TABLE $zones DROP COLUMN view_key" );
		}
		$wpdb->query(
			"INSERT IGNORE INTO $members (project_id, user_id, role)
			SELECT id, created_by, 'responsable' FROM $projects WHERE created_by > 0"
		);
		// phpcs:enable

		foreach ( array( 'tkdp_token_hash', 'tkdp_token_last_used', 'tkdp_token_created' ) as $key ) {
			delete_metadata( 'user', 0, $key, '', true );
		}
		delete_option( 'tkdp_mobile_page' );
	}
}
