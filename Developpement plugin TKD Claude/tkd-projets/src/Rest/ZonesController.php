<?php

namespace TkdProjets\Rest;

use TkdProjets\Access;
use TkdProjets\Db;
use TkdProjets\Mail\Mailer;
use TkdProjets\Repository\Links;
use TkdProjets\Repository\Projects;
use TkdProjets\Repository\Tasks;
use TkdProjets\Repository\Zones;
use TkdProjets\Validation;
use TkdProjets\ZoneLinks;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carte des zones d'une tâche, et liens privés « Ma carte ». */
final class ZonesController {

	public static function index( WP_REST_Request $r ) {
		$task = Tasks::find( (int) $r['id'] );
		if ( ! $task ) {
			return Format::notFound( 'Tâche' );
		}
		return array_map( array( Format::class, 'zone' ), Zones::forTask( (int) $task->id ) );
	}

	/**
	 * Enregistre toutes les zones d'une tâche (remplace la liste), tout ou rien.
	 * Les personnes nouvellement affectées reçoivent un nouveau lien si « notify » est coché ;
	 * celles qui n'ont plus aucune zone perdent leur lien.
	 */
	public static function save( WP_REST_Request $r ) {
		$task = Tasks::find( (int) $r['id'] );
		if ( ! $task ) {
			return Format::notFound( 'Tâche' );
		}
		$taskId = (int) $task->id;

		// Validation complète avant toute écriture.
		$clean = array();
		foreach ( array_values( (array) $r->get_param( 'zones' ) ) as $i => $zone ) {
			if ( ! is_array( $zone ) ) {
				continue;
			}
			$name   = Validation::cut( sanitize_text_field( (string) ( $zone['name'] ?? '' ) ), 190 );
			$name   = '' === $name ? 'Zone ' . ( $i + 1 ) : $name;
			$coords = Validation::coords( $zone['coords'] ?? null );
			if ( ! $coords ) {
				return Format::badRequest( 'La zone « ' . $name . ' » doit avoir au moins 3 coins.' );
			}
			$uid = absint( $zone['assignee'] ?? 0 );
			if ( $uid && ! Access::isProposedUser( $uid ) ) {
				return Format::badRequest( 'La zone « ' . $name . ' » est affectée à une personne qui ne peut pas la recevoir.' );
			}
			$clean[] = array(
				'id'   => absint( $zone['id'] ?? 0 ),
				'data' => array(
					'name'     => $name,
					'color'    => Validation::color( $zone['color'] ?? '' ),
					'assignee' => $uid,
					'coords'   => wp_json_encode( $coords ),
					'notes'    => sanitize_textarea_field( (string) ( $zone['notes'] ?? '' ) ),
					'position' => $i,
				),
			);
		}

		$before = Zones::assigneesOf( $taskId );
		$gained = Db::transaction(
			static function () use ( $clean, $taskId ) {
				$existing = array();
				foreach ( Zones::forTask( $taskId ) as $row ) {
					$existing[ (int) $row->id ] = $row;
				}
				$keep   = array();
				$gained = array(); // personnes qui reçoivent au moins une nouvelle zone
				foreach ( $clean as $item ) {
					$data = $item['data'];
					$id   = $item['id'];
					if ( $id && isset( $existing[ $id ] ) ) {
						if ( (int) $existing[ $id ]->assignee !== $data['assignee'] && $data['assignee'] ) {
							$gained[ $data['assignee'] ] = true;
						}
						Zones::update( $id, $data );
						$keep[] = $id;
					} else {
						$data['task_id']    = $taskId;
						$data['created_at'] = current_time( 'mysql' );
						$keep[]             = Zones::insert( $data );
						if ( $data['assignee'] ) {
							$gained[ $data['assignee'] ] = true;
						}
					}
				}
				foreach ( array_keys( $existing ) as $id ) {
					if ( ! in_array( $id, $keep, true ) ) {
						Zones::delete( $id );
					}
				}
				return array_keys( $gained );
			}
		);

		// Personnes qui n'ont plus de zone : leur lien est coupé.
		foreach ( array_diff( $before, Zones::assigneesOf( $taskId ) ) as $uid ) {
			Links::revoke( $taskId, (int) $uid );
		}

		$out = array( 'zones' => array_map( array( Format::class, 'zone' ), Zones::forTask( $taskId ) ) );
		if ( $r->get_param( 'notify' ) && $gained ) {
			$out['notified'] = self::sendLinks( $task, $gained );
			$out['expected'] = count( $gained );
		}
		return $out;
	}

	/** « Renvoyer les liens » (un nouveau lien remplace l'ancien) ou « Couper les liens ». */
	public static function links( WP_REST_Request $r ) {
		$task = Tasks::find( (int) $r['id'] );
		if ( ! $task ) {
			return Format::notFound( 'Tâche' );
		}
		if ( 'revoke' === $r->get_param( 'action' ) ) {
			return array( 'revoked' => Links::revoke( (int) $task->id ) );
		}
		$people = Zones::assigneesOf( (int) $task->id );
		$uid    = absint( $r->get_param( 'user_id' ) );
		if ( $uid ) {
			$people = array_values( array_intersect( $people, array( $uid ) ) );
		}
		if ( ! $people ) {
			return Format::badRequest( 'Aucune zone n\'est affectée à une personne.' );
		}
		return array(
			'notified' => self::sendLinks( $task, $people ),
			'expected' => count( $people ),
		);
	}

	/**
	 * Crée un nouveau lien pour chaque personne (l'ancien cesse de marcher) et l'envoie par e-mail.
	 *
	 * @param int[] $userIds
	 * @return int Nombre d'e-mails partis.
	 */
	private static function sendLinks( object $task, array $userIds ): int {
		$project = Projects::find( (int) $task->project_id );
		$expires = ZoneLinks::expiry( $task->end_date, $project->event_date ?? null, wp_date( 'Y-m-d' ) );
		$sent    = 0;
		foreach ( $userIds as $uid ) {
			$uid = (int) $uid;
			$key = ZoneLinks::newKey();
			Links::revoke( (int) $task->id, $uid );
			Links::create( (int) $task->id, $uid, ZoneLinks::hash( $key, wp_salt( 'auth' ) ), $expires );
			$sent += Mailer::zoneMail( $task, $uid, $key, $expires );
		}
		return $sent;
	}
}
