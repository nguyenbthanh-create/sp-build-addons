<?php

namespace TkdProjets;

use TkdProjets\Repository\Links;
use TkdProjets\Repository\Members;
use TkdProjets\Repository\Projects;
use TkdProjets\Repository\Tasks;
use TkdProjets\Repository\Zones;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outils RGPD de WordPress (Outils → Exporter / Effacer les données personnelles) :
 * tâches et zones attribuées, équipes. L'effacement retire la personne sans supprimer les tâches.
 */
final class Privacy {

	public static function registerExporter( array $exporters ): array {
		$exporters['tkd-projets'] = array(
			'exporter_friendly_name' => 'TKD Projets',
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	public static function registerEraser( array $erasers ): array {
		$erasers['tkd-projets'] = array(
			'eraser_friendly_name' => 'TKD Projets',
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	public static function export( string $email ): array {
		$user  = get_user_by( 'email', $email );
		$items = array();
		if ( $user ) {
			foreach ( Members::projectsOf( (int) $user->ID ) as $pid ) {
				$project = Projects::find( $pid );
				if ( $project ) {
					$items[] = array(
						'group_id'    => 'tkdp-projets',
						'group_label' => 'Projets (équipe)',
						'item_id'     => 'tkdp-projet-' . $pid,
						'data'        => array(
							array(
								'name'  => 'Projet',
								'value' => $project->name,
							),
							array(
								'name'  => 'Rôle',
								'value' => (string) Members::roleOf( $pid, (int) $user->ID ),
							),
						),
					);
				}
			}
			foreach ( Tasks::assignedTo( (int) $user->ID ) as $task ) {
				$items[] = array(
					'group_id'    => 'tkdp-taches',
					'group_label' => 'Tâches attribuées',
					'item_id'     => 'tkdp-tache-' . $task->id,
					'data'        => array(
						array(
							'name'  => 'Tâche',
							'value' => $task->title,
						),
						array(
							'name'  => 'Date limite',
							'value' => (string) $task->end_date,
						),
					),
				);
			}
			foreach ( Zones::assignedTo( (int) $user->ID ) as $zone ) {
				$items[] = array(
					'group_id'    => 'tkdp-zones',
					'group_label' => 'Zones attribuées sur une carte',
					'item_id'     => 'tkdp-zone-' . $zone->id,
					'data'        => array(
						array(
							'name'  => 'Zone',
							'value' => $zone->name,
						),
					),
				);
			}
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	public static function erase( string $email ): array {
		$user    = get_user_by( 'email', $email );
		$removed = 0;
		if ( $user ) {
			$uid      = (int) $user->ID;
			$removed += Tasks::unassignUser( $uid );
			$removed += Zones::unassignUser( $uid );
			$removed += Members::removeUser( $uid );
			$removed += Links::deleteForUser( $uid );
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
