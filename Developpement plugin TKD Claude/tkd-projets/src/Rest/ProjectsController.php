<?php

namespace TkdProjets\Rest;

use TkdProjets\Access;
use TkdProjets\Compta;
use TkdProjets\Db;
use TkdProjets\Policy;
use TkdProjets\Repository\Members;
use TkdProjets\Repository\Projects;
use TkdProjets\Repository\Tasks;
use TkdProjets\Types;
use TkdProjets\Validation;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Projets : liste, création, modification, suppression. */
final class ProjectsController {

	public static function index(): array {
		$rows = Projects::listFor( Access::isGestionnaire() ? null : get_current_user_id() );
		return array_map( array( Format::class, 'project' ), $rows );
	}

	public static function create( WP_REST_Request $r ) {
		$fields = self::fields( $r, null );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$team = self::team( $r, null );
		if ( is_wp_error( $team ) ) {
			return $team;
		}
		$fields['created_by'] = get_current_user_id();
		$fields['created_at'] = current_time( 'mysql' );

		$id = Db::transaction(
			static function () use ( $fields, $team, $r ) {
				$id = Projects::insert( $fields );
				Members::replace( $id, $team );
				if ( $r->get_param( 'apply_template' ) ) {
					foreach ( Types::templateTasks( $fields['type'], $fields['event_date'] ) as $task ) {
						Tasks::insert( $id, $task );
					}
				}
				return $id;
			}
		);
		return Format::project( Projects::find( $id ) );
	}

	public static function update( WP_REST_Request $r ) {
		$id      = (int) $r['id'];
		$project = Projects::find( $id );
		if ( ! $project ) {
			return Format::notFound( 'Projet' );
		}
		$fields = self::fields( $r, $project );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$team = null;
		if ( $r->has_param( 'team' ) && Access::can( 'manage_members', $id ) ) {
			$team = self::team( $r, $id );
			if ( is_wp_error( $team ) ) {
				return $team;
			}
		}
		Db::transaction(
			static function () use ( $id, $fields, $team ) {
				if ( $fields ) {
					Projects::update( $id, $fields );
				}
				if ( null !== $team ) {
					Members::replace( $id, $team );
				}
			}
		);
		Compta::syncAfterUpdate( $id );
		return Format::project( Projects::find( $id ) );
	}

	public static function delete( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! Projects::find( $id ) ) {
			return Format::notFound( 'Projet' );
		}
		Projects::delete( $id );
		return array( 'deleted' => true );
	}

	/**
	 * Champs du projet à enregistrer.
	 *
	 * @param object|null $existing Projet existant (modification) ou null (création).
	 * @return array|WP_Error
	 */
	private static function fields( WP_REST_Request $r, ?object $existing ) {
		$create = null === $existing;
		$has    = static fn( string $key ): bool => $create || $r->has_param( $key );
		$f      = array();

		if ( $has( 'name' ) ) {
			$f['name'] = Validation::cut( sanitize_text_field( (string) $r->get_param( 'name' ) ), 190 );
			if ( '' === $f['name'] ) {
				return Format::badRequest( 'Le nom du projet est obligatoire.' );
			}
		}
		if ( $has( 'type' ) ) {
			$type = (string) $r->get_param( 'type' );
			if ( ! Types::isAllowed( $type, $existing->type ?? null ) ) {
				return Format::badRequest( 'Choisissez un type de projet.' );
			}
			$f['type'] = $type;
		}
		if ( $has( 'status' ) ) {
			$f['status'] = Validation::oneOf( $r->get_param( 'status' ), Validation::PROJECT_STATUSES, 'en_cours' );
		}
		if ( $has( 'description' ) ) {
			$f['description'] = sanitize_textarea_field( (string) $r->get_param( 'description' ) );
		}
		if ( $has( 'color' ) ) {
			$f['color'] = Validation::color( $r->get_param( 'color' ) );
		}
		if ( $has( 'event_date' ) ) {
			$f['event_date'] = Validation::date( $r->get_param( 'event_date' ) );
		}
		return $f;
	}

	/**
	 * Équipe reçue, contrôlée : seulement des personnes proposables.
	 * Un responsable qui modifie l'équipe ne peut pas s'en retirer lui-même.
	 *
	 * @return array<int,string>|WP_Error
	 */
	private static function team( WP_REST_Request $r, ?int $projectId ) {
		$team = Validation::team( $r->get_param( 'team' ) );
		foreach ( array_keys( $team ) as $uid ) {
			if ( ! Access::isProposedUser( $uid ) ) {
				return Format::badRequest( 'Une des personnes de l\'équipe ne peut pas être ajoutée.' );
			}
		}
		$me = get_current_user_id();
		if ( null !== $projectId && ! Access::isGestionnaire() ) {
			$team[ $me ] = Policy::RESPONSABLE;
		}
		return $team;
	}
}
