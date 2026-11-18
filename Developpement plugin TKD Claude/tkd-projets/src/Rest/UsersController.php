<?php

namespace TkdProjets\Rest;

use TkdProjets\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Personnes proposées pour les équipes, les tâches et les zones (jamais les adresses e-mail).
 * Les rôles entiers ne sont envoyés qu'aux gestionnaires (eux seuls peuvent écrire à tout un rôle).
 */
final class UsersController {

	public static function index(): array {
		$users  = array();
		$counts = array();
		foreach ( Access::proposedUsers() as $u ) {
			$roles   = array_values( (array) $u->roles );
			$users[] = array(
				'id'    => (int) $u->ID,
				'name'  => $u->display_name,
				'roles' => $roles,
			);
			foreach ( $roles as $role ) {
				$counts[ $role ] = ( $counts[ $role ] ?? 0 ) + 1;
			}
		}

		$roles = array();
		foreach ( wp_roles()->role_names as $slug => $name ) {
			if ( ! empty( $counts[ $slug ] ) ) {
				$roles[] = array(
					'slug'       => $slug,
					'name'       => translate_user_role( $name ),
					'count'      => $counts[ $slug ],
					'assignable' => in_array( $slug, Access::proposedRoles(), true ),
				);
			}
		}

		return array(
			'users'       => $users,
			'roles'       => $roles,
			'canMailRole' => Access::isGestionnaire(),
		);
	}
}
