<?php

namespace TkdProjets\Rest;

use TkdProjets\Access;
use TkdProjets\Attachments;
use TkdProjets\Policy;
use TkdProjets\Repository\Members;
use TkdProjets\Types;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Mise en forme des réponses de l'API (jamais d'adresse e-mail ni de chemin de fichier). */
final class Format {

	public static function notFound( string $what ): WP_Error {
		return new WP_Error( 'tkdp_not_found', $what . ' introuvable.', array( 'status' => 404 ) );
	}

	public static function badRequest( string $message ): WP_Error {
		return new WP_Error( 'tkdp_bad_request', $message, array( 'status' => 400 ) );
	}

	public static function forbidden( string $message ): WP_Error {
		return new WP_Error( 'tkdp_forbidden', $message, array( 'status' => 403 ) );
	}

	public static function project( object $row ): array {
		$role  = Access::roleIn( (int) $row->id );
		$types = Types::all();
		$type  = isset( $types[ $row->type ] ) ? $row->type : Types::DEFAULT_TYPE;
		$team  = array();
		foreach ( Members::forProject( (int) $row->id ) as $uid => $teamRole ) {
			$user   = get_userdata( $uid );
			$team[] = array(
				'user_id' => $uid,
				'name'    => $user ? $user->display_name : 'Compte supprimé',
				'role'    => $teamRole,
			);
		}
		return array(
			'id'          => (int) $row->id,
			'name'        => (string) $row->name,
			'type'        => $type,
			'gala_edition' => (string) ( $row->gala_edition ?? '' ),
			'status'      => (string) $row->status,
			'description' => (string) $row->description,
			'color'       => (string) $row->color,
			'event_date'  => $row->event_date ?: null,
			'task_count'  => (int) ( $row->task_count ?? 0 ),
			'done_count'  => (int) ( $row->done_count ?? 0 ),
			'first_date'  => $row->first_date ?? null,
			'last_date'   => $row->last_date ?? null,
			'team'        => $team,
			'my_role'     => $role,
			'can'         => Policy::abilities( $role ),
		);
	}

	/**
	 * @param object      $row        Ligne de la table des tâches.
	 * @param int         $zoneCount  Nombre de zones de la tâche.
	 * @param string|null $role       Rôle de la personne connectée dans le projet.
	 */
	public static function task( object $row, int $zoneCount, ?string $role ): array {
		$user = $row->assignee ? get_userdata( (int) $row->assignee ) : null;
		$slug = (string) $row->assignee_role;
		return array(
			'id'            => (int) $row->id,
			'project_id'    => (int) $row->project_id,
			'title'         => (string) $row->title,
			'description'   => (string) $row->description,
			'status'        => (string) $row->status,
			'priority'      => (string) $row->priority,
			'assignee'      => (int) $row->assignee,
			'assignee_name' => $user ? $user->display_name : '',
			'assignee_role' => $slug,
			'role_name'     => $slug ? self::roleName( $slug ) : '',
			'start_date'    => $row->start_date ?: null,
			'end_date'      => $row->end_date ?: null,
			'progress'      => (int) $row->progress,
			'depends_on'    => $row->depends_on ? (int) $row->depends_on : null,
			'zone_count'    => $zoneCount,
			'attachments'   => Attachments::forBrowser( $row->attachments ),
			'position'      => (int) $row->position,
			'access'        => Policy::taskAccess( $role, Access::ownsTask( $row ) ),
		);
	}

	public static function zone( object $row ): array {
		$user   = $row->assignee ? get_userdata( (int) $row->assignee ) : null;
		$coords = json_decode( (string) $row->coords, true );
		return array(
			'id'            => (int) $row->id,
			'task_id'       => (int) $row->task_id,
			'name'          => (string) $row->name,
			'color'         => (string) $row->color,
			'assignee'      => (int) $row->assignee,
			'assignee_name' => $user ? $user->display_name : '',
			'coords'        => is_array( $coords ) ? $coords : array(),
			'notes'         => (string) $row->notes,
		);
	}

	/** Nom affiché d'un rôle WordPress, ou '' s'il n'existe pas. */
	public static function roleName( string $slug ): string {
		$names = wp_roles()->role_names;
		return isset( $names[ $slug ] ) ? translate_user_role( $names[ $slug ] ) : '';
	}
}
