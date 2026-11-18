<?php

namespace TkdProjets;

/**
 * Règles d'accès, sans WordPress (testées dans outils/tests/TkdProjetsPolicyTest.php).
 *
 * Rôles dans un projet :
 *  - gestionnaire : droit « tkd_projets_gerer » (président, bureau, administrateurs) : tout, sur tous les projets ;
 *  - responsable  : tout sur son projet, sauf le supprimer et écrire à tout un rôle ;
 *  - membre       : voit le projet, met à jour ses propres tâches (statut, avancement, description, pièces jointes).
 */
final class Policy {

	public const GESTIONNAIRE = 'gestionnaire';
	public const RESPONSABLE  = 'responsable';
	public const MEMBRE       = 'membre';

	/** Rôles qu'on peut donner dans l'équipe d'un projet. */
	public const TEAM_ROLES = array( self::RESPONSABLE, self::MEMBRE );

	/** Champs qu'un membre peut modifier sur une tâche qui lui est attribuée. */
	public const OWN_TASK_FIELDS = array( 'status', 'progress', 'description', 'attachments', 'notify' );

	private const RULES = array(
		'view'           => array( self::GESTIONNAIRE, self::RESPONSABLE, self::MEMBRE ),
		'edit_project'   => array( self::GESTIONNAIRE, self::RESPONSABLE ),
		'manage_members' => array( self::GESTIONNAIRE, self::RESPONSABLE ),
		'delete_project' => array( self::GESTIONNAIRE ),
		'create_task'    => array( self::GESTIONNAIRE, self::RESPONSABLE ),
		'edit_task'      => array( self::GESTIONNAIRE, self::RESPONSABLE ),
		'delete_task'    => array( self::GESTIONNAIRE, self::RESPONSABLE ),
		'reorder'        => array( self::GESTIONNAIRE, self::RESPONSABLE ),
		'zones'          => array( self::GESTIONNAIRE, self::RESPONSABLE ),
		'mail_role'      => array( self::GESTIONNAIRE ),
	);

	/** Rôle effectif dans un projet : gestionnaire d'abord, sinon le rôle dans l'équipe, sinon aucun. */
	public static function projectRole( bool $isGestionnaire, ?string $teamRole ): ?string {
		if ( $isGestionnaire ) {
			return self::GESTIONNAIRE;
		}
		return in_array( $teamRole, self::TEAM_ROLES, true ) ? $teamRole : null;
	}

	public static function can( string $action, ?string $role ): bool {
		return null !== $role && in_array( $role, self::RULES[ $action ] ?? array(), true );
	}

	/** Droits sur une tâche : « full » (tout), « own » (ses champs à soi), « none » (lecture). */
	public static function taskAccess( ?string $role, bool $ownTask ): string {
		if ( self::can( 'edit_task', $role ) ) {
			return 'full';
		}
		return ( self::MEMBRE === $role && $ownTask ) ? 'own' : 'none';
	}

	/**
	 * Garde seulement les champs qu'un accès autorise à modifier.
	 *
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>
	 */
	public static function filterTaskParams( array $params, string $access ): array {
		if ( 'full' === $access ) {
			return $params;
		}
		if ( 'own' === $access ) {
			return array_intersect_key( $params, array_flip( self::OWN_TASK_FIELDS ) );
		}
		return array();
	}

	/** Liste des actions permises (envoyée à l'interface pour afficher les bons boutons). */
	public static function abilities( ?string $role ): array {
		$out = array();
		foreach ( array_keys( self::RULES ) as $action ) {
			$out[ $action ] = self::can( $action, $role );
		}
		return $out;
	}
}
