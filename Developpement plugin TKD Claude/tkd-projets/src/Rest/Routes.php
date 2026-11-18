<?php

namespace TkdProjets\Rest;

use TkdProjets\Access;
use TkdProjets\Compta;
use TkdProjets\Repository\Tasks;
use TkdProjets\Types;
use TkdProjets\Validation;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes de l'API interne (/wp-json/tkdp/v1/…), utilisées par la page Projets.
 *
 * Le jeton de sécurité (nonce « wp_rest ») est envoyé par wp.apiFetch ; chaque route vérifie
 * en plus le rôle de la personne dans le projet concerné (voir Policy).
 */
final class Routes {

	public const NS = 'tkdp/v1';

	public static function register(): void {
		$id = array(
			'id' => array(
				'type'     => 'integer',
				'minimum'  => 1,
				'required' => true,
			),
		);

		register_rest_route(
			self::NS,
			'/projects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( ProjectsController::class, 'index' ),
					'permission_callback' => array( Access::class, 'canUseApp' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( ProjectsController::class, 'create' ),
					'permission_callback' => static fn(): bool => Access::isGestionnaire(),
					'args'                => self::projectArgs( true ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( ProjectsController::class, 'update' ),
					'permission_callback' => self::onProject( 'edit_project' ),
					'args'                => $id + self::projectArgs( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( ProjectsController::class, 'delete' ),
					'permission_callback' => self::onProject( 'delete_project' ),
					'args'                => $id,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( TasksController::class, 'index' ),
					'permission_callback' => self::onProject( 'view' ),
					'args'                => $id,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( TasksController::class, 'create' ),
					'permission_callback' => self::onProject( 'create_task' ),
					'args'                => $id + self::taskArgs(),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( TasksController::class, 'reorder' ),
				'permission_callback' => self::onProject( 'reorder' ),
				'args'                => $id + array(
					'order' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
						'maxItems' => 1000,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					// Le détail des droits (tout / ses champs / rien) est vérifié dans le contrôleur.
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( TasksController::class, 'update' ),
					'permission_callback' => self::onTask( 'view' ),
					'args'                => $id + self::taskArgs(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( TasksController::class, 'delete' ),
					'permission_callback' => self::onTask( 'delete_task' ),
					'args'                => $id,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/tasks/(?P<id>\d+)/zones',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( ZonesController::class, 'index' ),
					'permission_callback' => self::onTask( 'zones' ),
					'args'                => $id,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( ZonesController::class, 'save' ),
					'permission_callback' => self::onTask( 'zones' ),
					'args'                => $id + array(
						'zones'  => array(
							'type'     => 'array',
							'required' => true,
							'maxItems' => 100,
						),
						'notify' => array( 'type' => 'boolean' ),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/tasks/(?P<id>\d+)/links',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( ZonesController::class, 'links' ),
				'permission_callback' => self::onTask( 'zones' ),
				'args'                => $id + array(
					'action'  => array(
						'type'     => 'string',
						'enum'     => array( 'send', 'revoke' ),
						'required' => true,
					),
					'user_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)/budget',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( BudgetController::class, 'show' ),
					'permission_callback' => self::onBudget( false ),
					'args'                => $id,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( BudgetController::class, 'saveLines' ),
					'permission_callback' => self::onBudget( false ),
					'args'                => $id + array(
						'lignes' => array(
							'type'     => 'array',
							'required' => true,
							'maxItems' => 100,
							'items'    => array( 'type' => 'object' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)/budget/link',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( BudgetController::class, 'link' ),
				'permission_callback' => self::onBudget( false ),
				'args'                => $id + array(
					'action'    => array(
						'type'     => 'string',
						'enum'     => array( 'create', 'existing' ),
						'required' => true,
					),
					'compta_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)/depenses',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( BudgetController::class, 'addDepense' ),
				'permission_callback' => self::onBudget( true ),
				'args'                => $id + array(
					'date'           => array(
						'type'     => 'string',
						'required' => true,
					),
					'montant'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'sous_categorie' => array(
						'type'     => 'string',
						'required' => true,
					),
					'detail'         => array( 'type' => 'string' ),
					'mode_paiement'  => array( 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/users',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( UsersController::class, 'index' ),
				'permission_callback' => array( Access::class, 'canListUsers' ),
			)
		);
	}

	/** Contrôle d'accès sur une route /projects/{id}. */
	private static function onProject( string $action ): callable {
		return static fn( WP_REST_Request $r ): bool => Access::can( $action, (int) $r['id'] );
	}

	/**
	 * Budget : bureau seulement (vérifié par SP Compta), et accès au projet.
	 * $depense : en plus, seul le président valide une dépense.
	 */
	private static function onBudget( bool $depense ): callable {
		return static function ( WP_REST_Request $r ) use ( $depense ): bool {
			if ( ! Access::can( 'view', (int) $r['id'] ) ) {
				return false;
			}
			return $depense ? Compta::canValidateDepense() : Compta::canViewBudget();
		};
	}

	/** Contrôle d'accès sur une route /tasks/{id} : rôle dans le projet de la tâche. */
	private static function onTask( string $action ): callable {
		return static function ( WP_REST_Request $r ) use ( $action ): bool {
			$task = Tasks::find( (int) $r['id'] );
			if ( ! $task ) {
				return Access::canUseApp(); // le contrôleur répondra « introuvable »
			}
			return Access::can( $action, (int) $task->project_id );
		};
	}

	private static function projectArgs( bool $create ): array {
		return array(
			'name'           => array(
				'type'      => 'string',
				'maxLength' => 190,
				'required'  => $create,
			),
			'description'    => array( 'type' => 'string' ),
			'color'          => array( 'type' => 'string' ),
			'event_date'     => array( 'type' => array( 'string', 'null' ) ),
			'type'           => array(
				'type' => 'string',
				'enum' => array_keys( Types::all() ),
			),
			'status'         => array(
				'type' => 'string',
				'enum' => Validation::PROJECT_STATUSES,
			),
			'team'           => array(
				'type'     => 'array',
				'maxItems' => 200,
				'items'    => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array( 'type' => 'integer' ),
						'role'    => array( 'type' => 'string' ),
					),
				),
			),
			'apply_template' => array( 'type' => 'boolean' ),
		);
	}

	private static function taskArgs(): array {
		return array(
			'title'         => array(
				'type'      => 'string',
				'maxLength' => 255,
			),
			'description'   => array( 'type' => 'string' ),
			'status'        => array(
				'type' => 'string',
				'enum' => Validation::STATUSES,
			),
			'priority'      => array(
				'type' => 'string',
				'enum' => Validation::PRIORITIES,
			),
			'assignee'      => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'assignee_role' => array( 'type' => 'string' ),
			'start_date'    => array( 'type' => array( 'string', 'null' ) ),
			'end_date'      => array( 'type' => array( 'string', 'null' ) ),
			'progress'      => array(
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 100,
			),
			'depends_on'    => array( 'type' => array( 'integer', 'null' ) ),
			'attachments'   => array(
				'type'     => 'array',
				'items'    => array( 'type' => 'integer' ),
				'maxItems' => 20,
			),
			'notify'        => array( 'type' => 'boolean' ),
		);
	}
}
