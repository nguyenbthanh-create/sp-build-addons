<?php

namespace TkdProjets\Rest;

use TkdProjets\Compta;
use TkdProjets\Gala;
use TkdProjets\Repository\Projects;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Onglet Gala d'un projet de type « Gala » : chiffres de l'édition reliée (totaux seulement). */
final class GalaController {

	public static function show( WP_REST_Request $r ) {
		$project = Projects::find( (int) $r['id'] );
		if ( ! $project ) {
			return Format::notFound( 'Projet' );
		}
		if ( 'gala' !== $project->type ) {
			return Format::badRequest( 'Ce projet n\'est pas de type Gala.' );
		}
		$edition = (string) $project->gala_edition !== '' ? (string) $project->gala_edition : Gala::currentEdition();
		$summary = Gala::summary( $edition );
		if ( null === $summary ) {
			return Format::badRequest( 'L\'extension TKD Gala n\'est pas active.' );
		}
		$summary['repas_suggestion'] = Compta::canViewBudget() ? Gala::repasSuggestion( $summary['repas'], $summary['prix_repas'] ) : null;
		return $summary;
	}
}
