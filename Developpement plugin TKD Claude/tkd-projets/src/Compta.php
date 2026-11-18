<?php

namespace TkdProjets;

use TkdProjets\Repository\Members;
use TkdProjets\Repository\Projects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pont vers SP Compta (étape 4, 18/11/2026). Tout passe par SpCompta\Integration\ProjetsApi :
 * TKD Projets ne lit ni n'écrit jamais les tables de la trésorerie.
 *
 * Décisions de l'utilisateur :
 *  - budget visible du bureau seulement (droit « sp_compta_manager », vérifié par ProjetsApi) ;
 *  - seul le président (Projets → Réglages) valide une dépense de projet, qui entre directement en trésorerie.
 */
final class Compta {

	public const OPTION_PRESIDENT = 'tkdp_president';

	/** SP Compta est-elle active, en version 0.3.0 ou plus (porte d'entrée présente) ? */
	public static function available(): bool {
		return class_exists( '\SpCompta\Integration\ProjetsApi' );
	}

	public static function api(): \SpCompta\Integration\ProjetsApi {
		return \SpCompta\Integration\ProjetsApi::depuisPlugin();
	}

	public static function canViewBudget(): bool {
		return self::available() && is_user_logged_in() && self::api()->peutConsulter();
	}

	public static function presidentId(): int {
		return (int) get_option( self::OPTION_PRESIDENT, 0 );
	}

	/** Le président peut valider une dépense de projet s'il est aussi du bureau. */
	public static function canValidateDepense(): bool {
		$president = self::presidentId();
		return $president > 0 && get_current_user_id() === $president && self::canViewBudget();
	}

	/**
	 * Données du projet compta correspondant à un projet TKD.
	 *
	 * @return array<string,mixed>
	 */
	public static function projectData( object $project, ?int $exerciceId = null ): array {
		$responsables = array();
		foreach ( Members::forProject( (int) $project->id ) as $uid => $role ) {
			$user = Policy::RESPONSABLE === $role ? get_userdata( $uid ) : false;
			if ( $user ) {
				$responsables[] = $user->display_name;
			}
		}
		$data = array(
			'nom'         => (string) $project->name,
			'nature'      => Types::nature( (string) $project->type ),
			'responsable' => implode( ', ', $responsables ),
			'date_debut'  => $project->first_date ?? null,
			'date_fin'    => $project->event_date ?: ( $project->last_date ?? null ),
			'description' => (string) $project->description,
			'statut'      => 'termine' === $project->status ? 'cloture' : 'en_cours',
		);
		if ( null !== $exerciceId ) {
			$data['exercice_id'] = $exerciceId;
		}
		return $data;
	}

	/** Le projet compta lié existe-t-il encore ? Renvoie son id, ou 0. */
	public static function linkedId( object $project ): int {
		$id = (int) ( $project->compta_projet_id ?? 0 );
		return ( $id > 0 && null !== self::api()->projet( $id ) ) ? $id : 0;
	}

	/**
	 * Crée le projet compta (dans la saison de la date de l'évènement, sinon la saison active) et le relie.
	 *
	 * @throws \InvalidArgumentException Aucune saison.
	 */
	public static function createAndLink( object $project ): int {
		$api    = self::api();
		$saison = $api->saisonPourDate( $project->event_date ?: wp_date( 'Y-m-d' ) );
		if ( null === $saison ) {
			throw new \InvalidArgumentException( 'Aucune saison dans la trésorerie : créez et activez-en une dans Trésorerie → Paramètres.' );
		}
		$id = $api->enregistrerProjet( null, self::projectData( $project, $saison ) );
		Projects::update( (int) $project->id, array( 'compta_projet_id' => $id ) );
		return $id;
	}

	/** Relie à un projet compta déjà créé par la trésorière. */
	public static function linkExisting( object $project, int $comptaId ): int {
		if ( null === self::api()->projet( $comptaId ) ) {
			throw new \InvalidArgumentException( 'Projet de la trésorerie introuvable.' );
		}
		Projects::update( (int) $project->id, array( 'compta_projet_id' => $comptaId ) );
		return $comptaId;
	}

	/** Après une modification du projet TKD : nom, dates, état et responsables repris dans la trésorerie. */
	public static function syncAfterUpdate( int $projectId ): void {
		if ( ! self::canViewBudget() ) {
			return;
		}
		$project = Projects::find( $projectId );
		if ( ! $project ) {
			return;
		}
		try {
			$id = self::linkedId( $project );
			if ( $id ) {
				self::api()->enregistrerProjet( $id, self::projectData( $project ) );
			}
		} catch ( \Throwable $e ) {
			// La trésorerie reste juste ; le nom se resynchronisera à la prochaine modification.
			error_log( '[TKD Projets] synchronisation SP Compta : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
