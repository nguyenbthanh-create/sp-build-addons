<?php
/**
 * Suppression de l'extension (Extensions → Supprimer) : tables, réglages et droits.
 * N'est PAS exécuté à la simple désactivation : les données restent alors en place.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'links', 'zones', 'members', 'tasks', 'projects' ) as $name ) {
	$table = $wpdb->prefix . 'tkdp_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( (array) get_option( 'tkdp_gestionnaires', array() ) as $uid ) {
	$user = get_userdata( (int) $uid );
	if ( $user ) {
		$user->remove_cap( 'tkd_projets_gerer' );
	}
}
foreach ( wp_roles()->role_objects as $role ) {
	$role->remove_cap( 'tkd_projets_gerer' );
}

delete_option( 'tkdp_db_version' );
delete_option( 'tkdp_gestionnaires' );
