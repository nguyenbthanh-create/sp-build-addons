<?php

namespace TkdProjets;

use TkdProjets\Repository\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Droits WordPress de l'extension (les règles elles-mêmes sont dans Policy).
 *
 * - Droit « tkd_projets_gerer » : toujours donné au rôle administrateur (accès de secours),
 *   et en plus aux comptes cochés dans Projets → Réglages (président, bureau).
 * - Les autres personnes n'ont accès qu'aux projets dont elles font partie de l'équipe.
 * - Budget (étape suivante) : réservé au bureau, c'est-à-dire au droit « sp_compta_manager » de SP Compta.
 */
final class Access {

	public const CAP                 = 'tkd_projets_gerer';
	public const OPTION_GESTIONNAIRES = 'tkdp_gestionnaires';
	public const SP_COMPTA_CAP       = 'sp_compta_manager';

	/** Rôles des personnes proposées dans les équipes et les attributions (filtre « tkdp_roles_proposes »). */
	public const PROPOSED_ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'sp_secretaire', 'sp_tresoriere', 'entraineur' );

	public static function registerCapability(): void {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAP ) ) {
			$role->add_cap( self::CAP );
		}
	}

	public static function isGestionnaire( ?int $userId = null ): bool {
		$userId = $userId ?? get_current_user_id();
		return $userId > 0 && user_can( $userId, self::CAP );
	}

	/** Rôle effectif de la personne dans un projet (null : aucun accès). */
	public static function roleIn( int $projectId, ?int $userId = null ): ?string {
		$userId = $userId ?? get_current_user_id();
		if ( $userId <= 0 ) {
			return null;
		}
		return Policy::projectRole( self::isGestionnaire( $userId ), Members::roleOf( $projectId, $userId ) );
	}

	public static function can( string $action, int $projectId ): bool {
		return Policy::can( $action, self::roleIn( $projectId ) );
	}

	/** Peut ouvrir la page Projets : gestionnaire, ou membre d'au moins une équipe. */
	public static function canUseApp(): bool {
		$uid = get_current_user_id();
		return $uid > 0 && ( self::isGestionnaire( $uid ) || Members::hasAnyProject( $uid ) );
	}

	/** Peut voir la liste des personnes (pour composer une équipe ou attribuer une tâche). */
	public static function canListUsers(): bool {
		$uid = get_current_user_id();
		return $uid > 0 && ( self::isGestionnaire( $uid ) || Members::isResponsableSomewhere( $uid ) );
	}

	/** Budget des projets : bureau seulement (décision du 18/11/2026). */
	public static function canViewBudget(): bool {
		return current_user_can( self::SP_COMPTA_CAP ) || current_user_can( 'manage_options' );
	}

	/** La tâche est-elle attribuée à la personne (directement ou par son rôle) ? */
	public static function ownsTask( object $task, ?int $userId = null ): bool {
		$userId = $userId ?? get_current_user_id();
		if ( $userId <= 0 ) {
			return false;
		}
		if ( (int) $task->assignee === $userId ) {
			return true;
		}
		if ( '' !== (string) $task->assignee_role ) {
			$user = get_userdata( $userId );
			return $user && in_array( $task->assignee_role, (array) $user->roles, true );
		}
		return false;
	}

	public static function proposedRoles(): array {
		return (array) apply_filters( 'tkdp_roles_proposes', self::PROPOSED_ROLES );
	}

	/**
	 * Personnes proposées : comptes des rôles proposés, gestionnaires, et équipiers déjà présents.
	 *
	 * @return \WP_User[]
	 */
	public static function proposedUsers(): array {
		$users = get_users(
			array(
				'role__in' => self::proposedRoles(),
				'orderby'  => 'display_name',
			)
		);
		$ids   = array_map( static fn( $u ) => (int) $u->ID, $users );
		$extra = array_diff( array_merge( self::gestionnaires(), self::teamUserIds() ), $ids );
		if ( $extra ) {
			$users = array_merge( $users, get_users( array( 'include' => array_values( $extra ) ) ) );
			usort( $users, static fn( $a, $b ) => strcasecmp( $a->display_name, $b->display_name ) );
		}
		return $users;
	}

	public static function isProposedUser( int $userId ): bool {
		$user = get_userdata( $userId );
		if ( ! $user ) {
			return false;
		}
		return (bool) array_intersect( (array) $user->roles, self::proposedRoles() )
			|| self::isGestionnaire( $userId )
			|| Members::hasAnyProject( $userId );
	}

	/** @return int[] */
	private static function teamUserIds(): array {
		$wpdb = Db::wpdb();
		$m    = Db::table( 'members' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT user_id FROM $m" ) );
	}

	/** @return int[] comptes cochés dans les réglages */
	public static function gestionnaires(): array {
		return array_values( array_filter( array_map( 'intval', (array) get_option( self::OPTION_GESTIONNAIRES, array() ) ) ) );
	}

	/**
	 * Remplace la liste des gestionnaires : donne le droit aux nouveaux, le retire aux anciens.
	 * Ne touche jamais au droit donné par le rôle administrateur.
	 *
	 * @param int[] $userIds
	 */
	public static function syncGestionnaires( array $userIds ): void {
		$new = array_values( array_unique( array_filter( array_map( 'intval', $userIds ), static fn( $id ) => $id > 0 && get_userdata( $id ) ) ) );
		foreach ( array_diff( self::gestionnaires(), $new ) as $old ) {
			$user = get_userdata( $old );
			if ( $user ) {
				$user->remove_cap( self::CAP );
			}
		}
		foreach ( $new as $id ) {
			get_userdata( $id )->add_cap( self::CAP );
		}
		update_option( self::OPTION_GESTIONNAIRES, $new, false );
	}
}
