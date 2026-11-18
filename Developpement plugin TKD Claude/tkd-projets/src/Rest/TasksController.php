<?php

namespace TkdProjets\Rest;

use TkdProjets\Access;
use TkdProjets\Attachments;
use TkdProjets\Mail\Mailer;
use TkdProjets\Policy;
use TkdProjets\Repository\Members;
use TkdProjets\Repository\Projects;
use TkdProjets\Repository\Tasks;
use TkdProjets\Repository\Zones;
use TkdProjets\Validation;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Tâches d'un projet. */
final class TasksController {

	public static function index( WP_REST_Request $r ) {
		$pid = (int) $r['id'];
		if ( ! Projects::find( $pid ) ) {
			return Format::notFound( 'Projet' );
		}
		$role   = Access::roleIn( $pid );
		$counts = Zones::countsForProject( $pid );
		$rows   = Tasks::forProject( $pid );
		cache_users( array_filter( array_map( static fn( $t ) => (int) $t->assignee, $rows ) ) );
		return array_map( static fn( $t ) => Format::task( $t, $counts[ (int) $t->id ] ?? 0, $role ), $rows );
	}

	public static function create( WP_REST_Request $r ) {
		$pid = (int) $r['id'];
		if ( ! Projects::find( $pid ) ) {
			return Format::notFound( 'Projet' );
		}
		$f = self::fields( $r, null, $pid, 'full' );
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		$task = Tasks::find( Tasks::insert( $pid, $f ) );
		self::addAssigneeToTeam( $task );
		$out = Format::task( $task, 0, Access::roleIn( $pid ) );
		if ( $r->get_param( 'notify' ) && ( $task->assignee || $task->assignee_role ) ) {
			$out['notified'] = Mailer::taskMail( $task, 'assigned' );
		}
		return $out;
	}

	public static function update( WP_REST_Request $r ) {
		$task = Tasks::find( (int) $r['id'] );
		if ( ! $task ) {
			return Format::notFound( 'Tâche' );
		}
		$pid    = (int) $task->project_id;
		$role   = Access::roleIn( $pid );
		$access = Policy::taskAccess( $role, Access::ownsTask( $task ) );
		if ( 'none' === $access ) {
			return Format::forbidden( 'Seules les personnes à qui la tâche est attribuée et les responsables du projet peuvent la modifier.' );
		}
		$f = self::fields( $r, $task, $pid, $access );
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		Tasks::update( (int) $task->id, $f );

		$new = Tasks::find( (int) $task->id );
		self::addAssigneeToTeam( $new );
		$out = Format::task( $new, Zones::countForTask( (int) $new->id ), $role );

		// E-mail : nouvelle attribution, ou date limite modifiée pour une tâche déjà attribuée.
		if ( 'full' === $access && $r->get_param( 'notify' ) && ( $new->assignee || $new->assignee_role ) ) {
			$reassigned = (int) $new->assignee !== (int) $task->assignee || (string) $new->assignee_role !== (string) $task->assignee_role;
			if ( $reassigned ) {
				$out['notified'] = Mailer::taskMail( $new, 'assigned' );
			} elseif ( $new->end_date && $new->end_date !== $task->end_date ) {
				$out['notified'] = Mailer::taskMail( $new, 'deadline' );
			}
		}
		return $out;
	}

	public static function delete( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! Tasks::find( $id ) ) {
			return Format::notFound( 'Tâche' );
		}
		Tasks::delete( $id );
		return array( 'deleted' => true );
	}

	public static function reorder( WP_REST_Request $r ) {
		Tasks::reorder( (int) $r['id'], (array) $r->get_param( 'order' ) );
		return array( 'ok' => true );
	}

	/** Une personne à qui l'on attribue une tâche rejoint l'équipe (pour voir le projet). */
	private static function addAssigneeToTeam( object $task ): void {
		if ( $task->assignee ) {
			Members::add( (int) $task->project_id, (int) $task->assignee, Policy::MEMBRE );
		}
	}

	/**
	 * Champs de la tâche à enregistrer.
	 *
	 * @param object|null $existing Tâche existante (modification) ou null (création).
	 * @param string      $access   « full » ou « own » (voir Policy::taskAccess()).
	 * @return array|WP_Error
	 */
	private static function fields( WP_REST_Request $r, ?object $existing, int $pid, string $access ) {
		$partial = null !== $existing;
		$allowed = 'full' === $access ? null : Policy::OWN_TASK_FIELDS;
		$has     = static function ( string $key ) use ( $r, $partial, $allowed ): bool {
			if ( null !== $allowed && ! in_array( $key, $allowed, true ) ) {
				return false;
			}
			return ! $partial || $r->has_param( $key );
		};
		$f = array();

		if ( $has( 'title' ) ) {
			$f['title'] = Validation::cut( sanitize_text_field( (string) $r->get_param( 'title' ) ), 255 );
			if ( '' === $f['title'] ) {
				return Format::badRequest( 'Le titre de la tâche est obligatoire.' );
			}
		}
		if ( $has( 'description' ) ) {
			$f['description'] = sanitize_textarea_field( (string) $r->get_param( 'description' ) );
		}
		if ( $has( 'status' ) ) {
			$f['status'] = Validation::oneOf( $r->get_param( 'status' ), Validation::STATUSES, 'todo' );
		}
		if ( $has( 'priority' ) ) {
			$f['priority'] = Validation::oneOf( $r->get_param( 'priority' ), Validation::PRIORITIES, 'normal' );
		}

		// Attribution : une personne (de la liste proposée) ou un rôle entier (gestionnaires seulement).
		if ( $has( 'assignee' ) ) {
			$uid = absint( $r->get_param( 'assignee' ) );
			if ( $uid && ! Access::isProposedUser( $uid ) ) {
				return Format::badRequest( 'Cette personne ne peut pas recevoir de tâche.' );
			}
			$f['assignee'] = $uid;
		}
		if ( $has( 'assignee_role' ) ) {
			$role = sanitize_key( (string) $r->get_param( 'assignee_role' ) );
			$old  = $existing ? (string) $existing->assignee_role : '';
			if ( '' !== $role && $role !== $old ) {
				if ( ! Access::can( 'mail_role', $pid ) ) {
					return Format::forbidden( 'Seuls les gestionnaires de projets peuvent attribuer une tâche à tout un rôle.' );
				}
				if ( ! in_array( $role, Access::proposedRoles(), true ) || '' === Format::roleName( $role ) ) {
					return Format::badRequest( 'Rôle inconnu.' );
				}
			}
			$f['assignee_role'] = $role;
		}
		if ( ! empty( $f['assignee_role'] ) ) {
			$f['assignee'] = 0;
		} elseif ( ! empty( $f['assignee'] ) ) {
			$f['assignee_role'] = '';
		}

		if ( $has( 'start_date' ) ) {
			$f['start_date'] = Validation::date( $r->get_param( 'start_date' ) );
		}
		if ( $has( 'end_date' ) ) {
			$f['end_date'] = Validation::date( $r->get_param( 'end_date' ) );
		}
		if ( $has( 'progress' ) && $r->has_param( 'progress' ) ) {
			$f['progress'] = Validation::percent( $r->get_param( 'progress' ) );
		} elseif ( isset( $f['status'] ) && 'done' === $f['status'] ) {
			$f['progress'] = 100;
		} elseif ( ! $partial ) {
			$f['progress'] = 0;
		}
		if ( $has( 'depends_on' ) ) {
			$dep             = absint( $r->get_param( 'depends_on' ) );
			$depTask         = $dep ? Tasks::find( $dep ) : null;
			$valid           = $depTask && (int) $depTask->project_id === $pid && ( ! $existing || $dep !== (int) $existing->id );
			$f['depends_on'] = $valid ? $dep : null;
		}
		if ( $has( 'attachments' ) ) {
			$f['attachments'] = self::attachments( $r->get_param( 'attachments' ), $existing );
		}

		// La date de fin ne peut pas précéder la date de début.
		$start = array_key_exists( 'start_date', $f ) ? $f['start_date'] : ( $existing->start_date ?? null );
		$end   = array_key_exists( 'end_date', $f ) ? $f['end_date'] : ( $existing->end_date ?? null );
		if ( $start && $end && $end < $start ) {
			return Format::badRequest( 'La date de fin doit être postérieure ou égale à la date de début.' );
		}
		return $f;
	}

	/**
	 * Pièces jointes : les nouveaux fichiers sont contrôlés (Attachments::isAllowed()) ; ceux qui
	 * étaient déjà sur la tâche sont gardés même si la personne qui enregistre ne peut pas les lire.
	 */
	private static function attachments( $value, ?object $existing ): ?string {
		$before = $existing ? Validation::ids( (string) $existing->attachments ) : array();
		$keep   = array();
		foreach ( Validation::ids( $value ) as $id ) {
			if ( in_array( $id, $before, true ) || Attachments::isAllowed( $id ) ) {
				$keep[] = $id;
			}
		}
		return $keep ? implode( ',', $keep ) : null;
	}
}
