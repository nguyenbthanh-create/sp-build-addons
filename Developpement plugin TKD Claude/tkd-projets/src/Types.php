<?php

namespace TkdProjets;

/**
 * Types de projets et listes de tâches modèles, sans WordPress
 * (testés dans outils/tests/TkdProjetsTypesTest.php).
 *
 * Décision du 18/11/2026 : trois types proposés à la création (Gala, Fêtes, Compétition).
 * « Autre » n'est pas proposé : il sert aux projets créés avant la 2.0.
 *
 * Une tâche modèle : [ titre, jours avant (-) ou après (+) l'évènement pour le début,
 * idem pour la fin, priorité, consigne ]. Sans date d'évènement, les tâches sont créées sans dates.
 * « nature » : nature du projet correspondant dans SP Compta (Entity\Projet::NATURES).
 */
final class Types {

	public const DEFAULT_TYPE = 'autre';

	private const TYPES = array(
		'gala'        => array(
			'label'    => 'Gala',
			'icon'     => '🎭',
			'color'    => '#8e44ad',
			'nature'   => 'evenement',
			'proposed' => true,
			'hint'     => 'Exceptionnel. Les inscriptions des clubs, le conducteur et la projection restent dans le menu « Gala ».',
			'tasks'    => array(
				array( 'Réserver la salle et le matériel', -150, -120, 'high', '' ),
				array( 'Ouvrir les inscriptions des clubs (menu Gala → Réglages)', -100, -90, 'high', '' ),
				array( 'Chercher les annonceurs et les sponsors', -100, -45, 'normal', 'Les contrats signés s\'enregistrent dans SP Compta (Sponsors).' ),
				array( 'Commander trophées, médailles et cadeaux', -60, -40, 'normal', '' ),
				array( 'Communication : affiches, réseaux sociaux, presse', -60, -7, 'normal', '' ),
				array( 'Clôturer les inscriptions et relancer les clubs incomplets', -35, -30, 'high', '' ),
				array( 'Commander les repas au traiteur', -25, -15, 'normal', 'Liste des repas : menu Gala → Inscriptions → export CSV.' ),
				array( 'Construire le conducteur (ordre de passage)', -21, -10, 'high', '' ),
				array( 'Préparer la sono, la projection et les musiques', -10, -2, 'normal', '' ),
				array( 'Installer la salle', -1, 0, 'high', '' ),
				array( 'Bilan, remerciements aux clubs et aux bénévoles', 1, 14, 'normal', '' ),
				array( 'Supprimer les données de l\'édition (RGPD)', 25, 30, 'normal', 'Menu Gala → Réglages → « Supprimer les données de l\'édition ».' ),
			),
		),
		'fetes'       => array(
			'label'    => 'Fêtes',
			'icon'     => '🎄',
			'color'    => '#c0392b',
			'nature'   => 'evenement',
			'proposed' => true,
			'hint'     => 'Fête de Noël, fête de fin d\'année.',
			'tasks'    => array(
				array( 'Fixer la date et le lieu', -60, -50, 'high', '' ),
				array( 'Réserver la salle et le matériel', -50, -40, 'normal', '' ),
				array( 'Prévenir les adhérents et les familles', -30, -21, 'normal', '' ),
				array( 'Commander les cadeaux, le goûter, les boissons', -21, -10, 'normal', '' ),
				array( 'Organiser les bénévoles', -14, -5, 'normal', '' ),
				array( 'Installer la salle', -1, 0, 'high', '' ),
				array( 'Ranger et faire le bilan', 0, 7, 'normal', '' ),
			),
		),
		'competition' => array(
			'label'    => 'Compétition',
			'icon'     => '🥋',
			'color'    => '#2271b1',
			'nature'   => 'competition',
			'proposed' => true,
			'hint'     => 'Annuel : un projet par compétition.',
			'tasks'    => array(
				array( 'Vérifier licences, passeports sportifs et certificats', -60, -45, 'high', '' ),
				array( 'Choisir les combattants avec les entraîneurs', -45, -35, 'high', '' ),
				array( 'Inscrire les combattants sur le site fédéral', -35, -25, 'high', '' ),
				array( 'Payer les engagements', -25, -20, 'normal', '' ),
				array( 'Réserver le transport (et l\'hébergement si besoin)', -30, -14, 'normal', '' ),
				array( 'Vérifier les protections et les tenues', -10, -3, 'normal', '' ),
				array( 'Publier les résultats', 1, 3, 'low', '' ),
			),
		),
		'autre'       => array(
			'label'    => 'Autre',
			'icon'     => '📁',
			'color'    => '#2271b1',
			'nature'   => 'autre',
			'proposed' => false,
			'hint'     => '',
			'tasks'    => array(),
		),
	);

	public static function exists( string $type ): bool {
		return isset( self::TYPES[ $type ] );
	}

	/** Type valide pour un projet : un type proposé, ou le type qu'il a déjà. */
	public static function isAllowed( string $type, ?string $current = null ): bool {
		return self::exists( $type ) && ( self::TYPES[ $type ]['proposed'] || $type === $current );
	}

	/** @return array<string,array{label:string,icon:string,color:string,nature:string,proposed:bool,hint:string,count:int}> */
	public static function all(): array {
		$out = array();
		foreach ( self::TYPES as $slug => $t ) {
			$out[ $slug ] = array(
				'label'    => $t['label'],
				'icon'     => $t['icon'],
				'color'    => $t['color'],
				'nature'   => $t['nature'],
				'proposed' => $t['proposed'],
				'hint'     => $t['hint'],
				'count'    => count( $t['tasks'] ),
			);
		}
		return $out;
	}

	public static function nature( string $type ): string {
		return self::TYPES[ $type ]['nature'] ?? 'autre';
	}

	/**
	 * Tâches modèles d'un type, datées à partir de la date de l'évènement.
	 *
	 * @param string      $type      Type du projet.
	 * @param string|null $eventDate AAAA-MM-JJ ou null.
	 * @return array<int,array{title:string,description:string,priority:string,start_date:?string,end_date:?string}>
	 */
	public static function templateTasks( string $type, ?string $eventDate ): array {
		$tasks = self::TYPES[ $type ]['tasks'] ?? array();
		$event = null;
		if ( null !== $eventDate && null !== Validation::date( $eventDate ) ) {
			$event = new \DateTimeImmutable( $eventDate . ' 00:00:00', new \DateTimeZone( 'UTC' ) );
		}
		$out = array();
		foreach ( $tasks as $t ) {
			list( $title, $from, $to, $priority, $description ) = $t;
			$out[] = array(
				'title'       => $title,
				'description' => $description,
				'priority'    => $priority,
				'start_date'  => $event ? self::shift( $event, (int) $from ) : null,
				'end_date'    => $event ? self::shift( $event, (int) $to ) : null,
			);
		}
		return $out;
	}

	private static function shift( \DateTimeImmutable $day, int $days ): string {
		return $day->modify( ( $days >= 0 ? '+' : '' ) . $days . ' days' )->format( 'Y-m-d' );
	}
}
