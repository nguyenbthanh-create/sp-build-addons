<?php
/**
 * Création des tables de la base de données.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nom complet d'une table du plugin.
 *
 * @param string $name projects|tasks.
 * @return string
 */
function tkdp_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'tkdp_' . $name;
}

/**
 * Crée ou met à jour les tables.
 */
function tkdp_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset  = $wpdb->get_charset_collate();
	$projects = tkdp_table( 'projects' );
	$tasks    = tkdp_table( 'tasks' );

	dbDelta(
		"CREATE TABLE $projects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			description text NULL,
			color varchar(7) NOT NULL DEFAULT '#2271b1',
			event_date date NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
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
			KEY project_id (project_id)
		) $charset;"
	);

	$zones = tkdp_table( 'zones' );
	dbDelta(
		"CREATE TABLE $zones (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			task_id bigint(20) unsigned NOT NULL,
			name varchar(190) NOT NULL,
			color varchar(7) NOT NULL DEFAULT '#2271b1',
			assignee bigint(20) unsigned NOT NULL DEFAULT 0,
			coords longtext NOT NULL,
			notes text NULL,
			view_key varchar(64) NOT NULL DEFAULT '',
			position int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY task_id (task_id),
			KEY view_key (view_key)
		) $charset;"
	);

	update_option( 'tkdp_db_version', TKDP_VERSION );
}

/**
 * Met à jour les tables après une mise à jour du plugin.
 */
function tkdp_maybe_upgrade() {
	if ( get_option( 'tkdp_db_version' ) !== TKDP_VERSION ) {
		tkdp_install();
		tkdp_cleanup_token_feature();
	}
}

/**
 * Supprime les données de l'ancien accès par token (versions 1.2.x), retiré depuis.
 */
function tkdp_cleanup_token_feature() {
	foreach ( array( 'tkdp_token_hash', 'tkdp_token_last_used', 'tkdp_token_created' ) as $key ) {
		delete_metadata( 'user', 0, $key, '', true );
	}
	delete_option( 'tkdp_mobile_page' );
}
