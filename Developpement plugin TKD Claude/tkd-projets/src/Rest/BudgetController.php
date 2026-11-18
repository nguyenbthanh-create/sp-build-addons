<?php

namespace TkdProjets\Rest;

use TkdProjets\Compta;
use TkdProjets\Repository\Projects;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onglet Budget d'un projet (bureau seulement) et dépense validée par le président.
 * Les montants et les calculs viennent tous de SP Compta (ProjetsApi).
 */
final class BudgetController {

	public static function show( WP_REST_Request $r ) {
		return self::guard( static fn() => self::state( (int) $r['id'] ) );
	}

	/** Relie le projet à la trésorerie : nouveau projet compta, ou projet déjà créé par la trésorière. */
	public static function link( WP_REST_Request $r ) {
		return self::guard(
			static function () use ( $r ) {
				$project = self::project( (int) $r['id'] );
				if ( 'existing' === $r->get_param( 'action' ) ) {
					Compta::linkExisting( $project, (int) $r->get_param( 'compta_id' ) );
				} else {
					Compta::createAndLink( $project );
				}
				return self::state( (int) $project->id );
			}
		);
	}

	/** Remplace les lignes du budget prévu (le projet est relié à la trésorerie s'il ne l'est pas encore). */
	public static function saveLines( WP_REST_Request $r ) {
		return self::guard(
			static function () use ( $r ) {
				$project = self::project( (int) $r['id'] );
				$id      = Compta::linkedId( $project );
				if ( ! $id ) {
					$id = Compta::createAndLink( $project );
				}
				Compta::api()->enregistrerLignesBudget( $id, (array) $r->get_param( 'lignes' ) );
				return self::state( (int) $project->id );
			}
		);
	}

	/** Dépense validée par le président : elle entre directement dans SP Compta, rattachée au projet. */
	public static function addDepense( WP_REST_Request $r ) {
		return self::guard(
			static function () use ( $r ) {
				$project = self::project( (int) $r['id'] );
				$id      = Compta::linkedId( $project );
				if ( ! $id ) {
					throw new \InvalidArgumentException( 'Reliez d\'abord le projet à la trésorerie.' );
				}
				$data = array();
				foreach ( array( 'date', 'montant', 'sous_categorie', 'detail', 'mode_paiement' ) as $key ) {
					$data[ $key ] = (string) $r->get_param( $key );
				}
				$depense = Compta::api()->enregistrerDepense( $id, $data, $r->get_file_params(), wp_get_current_user()->display_name );
				$out     = self::state( (int) $project->id );
				$out['depense'] = $depense;
				return $out;
			}
		);
	}

	/** État complet de l'onglet Budget. */
	private static function state( int $projectId ): array {
		$project = self::project( $projectId );
		$api     = Compta::api();
		$id      = Compta::linkedId( $project );
		$saison  = $api->saisonPourDate( $project->event_date ?: wp_date( 'Y-m-d' ) );
		$out     = array(
			'linked'         => $id > 0,
			'canValidate'    => Compta::canValidateDepense(),
			'categories'     => $api->categories(),
			'sousCategories' => $api->sousCategoriesDepense(),
			'modesPaiement'  => $api->modesPaiement(),
			'saison'         => null,
		);
		foreach ( $api->saisons() as $s ) {
			if ( $s['id'] === ( $id ? $api->projet( $id )['exercice_id'] : $saison ) ) {
				$out['saison'] = $s;
			}
		}
		if ( $id ) {
			$out['bilan']      = $api->bilan( $id );
			$out['lignes']     = $api->lignesBudget( $id );
			$out['mouvements'] = $api->mouvements( $id );
		} else {
			$out['candidats'] = $saison ? $api->projetsDeSaison( $saison ) : array();
		}
		return $out;
	}

	private static function project( int $id ): object {
		$project = Projects::find( $id );
		if ( ! $project ) {
			throw new \OutOfBoundsException( 'Projet introuvable.' );
		}
		return $project;
	}

	/** Exécute $fn et traduit les erreurs de SP Compta en réponses de l'API. */
	private static function guard( callable $fn ) {
		try {
			return $fn();
		} catch ( \OutOfBoundsException $e ) {
			return Format::notFound( 'Projet' );
		} catch ( \InvalidArgumentException $e ) {
			return Format::badRequest( $e->getMessage() );
		} catch ( \RuntimeException $e ) {
			if ( 403 === $e->getCode() ) {
				return Format::forbidden( $e->getMessage() );
			}
			error_log( '[TKD Projets] budget : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error( 'tkdp_compta', $e->getMessage(), array( 'status' => 500 ) );
		} catch ( \Throwable $e ) {
			error_log( '[TKD Projets] budget : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error( 'tkdp_compta', 'La trésorerie n\'a pas pu répondre.', array( 'status' => 500 ) );
		}
	}
}
