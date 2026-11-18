<?php
/**
 * API REST interne du plugin (utilisée par la page d'administration).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'tkdp_register_routes' );

const TKDP_STATUSES   = array( 'todo', 'doing', 'done' );
const TKDP_PRIORITIES = array( 'low', 'normal', 'high' );

/* -------------------------------------------------------------------------
 * Droits
 * ---------------------------------------------------------------------- */

/** Peut consulter et modifier les projets et les tâches. */
function tkdp_can_edit() {
	return current_user_can( 'edit_posts' );
}

/** Peut supprimer un projet entier. */
function tkdp_can_manage() {
	return current_user_can( 'manage_options' );
}

/* -------------------------------------------------------------------------
 * Routes
 * ---------------------------------------------------------------------- */

function tkdp_register_routes() {
	$ns = 'tkdp/v1';

	register_rest_route(
		$ns,
		'/projects',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'tkdp_get_projects',
				'permission_callback' => 'tkdp_can_edit',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'tkdp_create_project',
				'permission_callback' => 'tkdp_can_edit',
			),
		)
	);

	register_rest_route(
		$ns,
		'/projects/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'tkdp_update_project',
				'permission_callback' => 'tkdp_can_edit',
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'tkdp_delete_project',
				'permission_callback' => 'tkdp_can_manage',
			),
		)
	);

	register_rest_route(
		$ns,
		'/projects/(?P<id>\d+)/tasks',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'tkdp_get_tasks',
				'permission_callback' => 'tkdp_can_edit',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'tkdp_create_task',
				'permission_callback' => 'tkdp_can_edit',
			),
		)
	);

	register_rest_route(
		$ns,
		'/projects/(?P<id>\d+)/reorder',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'tkdp_reorder_tasks',
			'permission_callback' => 'tkdp_can_edit',
		)
	);

	register_rest_route(
		$ns,
		'/tasks/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'tkdp_update_task',
				'permission_callback' => 'tkdp_can_edit',
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'tkdp_delete_task',
				'permission_callback' => 'tkdp_can_edit',
			),
		)
	);

	register_rest_route(
		$ns,
		'/users',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'tkdp_get_users',
			'permission_callback' => 'tkdp_can_edit',
		)
	);
}

/* -------------------------------------------------------------------------
 * Nettoyage des données
 * ---------------------------------------------------------------------- */

/** Date au format AAAA-MM-JJ valide, sinon null. */
function tkdp_clean_date( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return $value;
	}
	return null;
}

function tkdp_not_found( $what ) {
	return new WP_Error( 'tkdp_not_found', $what . ' introuvable.', array( 'status' => 404 ) );
}

function tkdp_bad_request( $message ) {
	return new WP_Error( 'tkdp_bad_request', $message, array( 'status' => 400 ) );
}

/* -------------------------------------------------------------------------
 * Projets
 * ---------------------------------------------------------------------- */

function tkdp_format_project( $row ) {
	return array(
		'id'          => (int) $row->id,
		'name'        => $row->name,
		'description' => (string) $row->description,
		'color'       => $row->color,
		'event_date'  => isset( $row->event_date ) ? $row->event_date : null,
		'task_count'  => isset( $row->task_count ) ? (int) $row->task_count : 0,
		'done_count'  => isset( $row->done_count ) ? (int) $row->done_count : 0,
		'first_date'  => isset( $row->first_date ) ? $row->first_date : null,
		'last_date'   => isset( $row->last_date ) ? $row->last_date : null,
	);
}

function tkdp_find_project( $id ) {
	global $wpdb;
	$p = tkdp_table( 'projects' );
	$t = tkdp_table( 'tasks' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT p.*, COUNT(t.id) AS task_count,
				SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_count,
				LEAST(COALESCE(MIN(t.start_date), MIN(t.end_date)), COALESCE(MIN(t.end_date), MIN(t.start_date))) AS first_date,
				GREATEST(COALESCE(MAX(t.end_date), MAX(t.start_date)), COALESCE(MAX(t.start_date), MAX(t.end_date))) AS last_date
			FROM $p p LEFT JOIN $t t ON t.project_id = p.id
			WHERE p.id = %d GROUP BY p.id",
			$id
		)
	);
}

function tkdp_get_projects() {
	global $wpdb;
	$p = tkdp_table( 'projects' );
	$t = tkdp_table( 'tasks' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		"SELECT p.*, COUNT(t.id) AS task_count,
			SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_count,
			LEAST(COALESCE(MIN(t.start_date), MIN(t.end_date)), COALESCE(MIN(t.end_date), MIN(t.start_date))) AS first_date,
			GREATEST(COALESCE(MAX(t.end_date), MAX(t.start_date)), COALESCE(MAX(t.start_date), MAX(t.end_date))) AS last_date
		FROM $p p LEFT JOIN $t t ON t.project_id = p.id
		GROUP BY p.id ORDER BY p.created_at DESC"
	);
	return array_map( 'tkdp_format_project', $rows );
}

function tkdp_project_fields( WP_REST_Request $r, $partial ) {
	$f = array();
	if ( ! $partial || $r->has_param( 'name' ) ) {
		$f['name'] = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 190 );
		if ( '' === $f['name'] ) {
			return tkdp_bad_request( 'Le nom du projet est obligatoire.' );
		}
	}
	if ( ! $partial || $r->has_param( 'description' ) ) {
		$f['description'] = sanitize_textarea_field( (string) $r->get_param( 'description' ) );
	}
	if ( ! $partial || $r->has_param( 'color' ) ) {
		$color      = sanitize_hex_color( (string) $r->get_param( 'color' ) );
		$f['color'] = $color ? $color : '#2271b1';
	}
	if ( ! $partial || $r->has_param( 'event_date' ) ) {
		$f['event_date'] = tkdp_clean_date( $r->get_param( 'event_date' ) ); // date de l'évènement (facultative)
	}
	return $f;
}

function tkdp_create_project( WP_REST_Request $r ) {
	global $wpdb;
	$f = tkdp_project_fields( $r, false );
	if ( is_wp_error( $f ) ) {
		return $f;
	}
	$f['created_by'] = get_current_user_id();
	$f['created_at'] = current_time( 'mysql' );
	$wpdb->insert( tkdp_table( 'projects' ), $f );
	return tkdp_format_project( tkdp_find_project( $wpdb->insert_id ) );
}

function tkdp_update_project( WP_REST_Request $r ) {
	global $wpdb;
	$id = (int) $r['id'];
	if ( ! tkdp_find_project( $id ) ) {
		return tkdp_not_found( 'Projet' );
	}
	$f = tkdp_project_fields( $r, true );
	if ( is_wp_error( $f ) ) {
		return $f;
	}
	if ( $f ) {
		$wpdb->update( tkdp_table( 'projects' ), $f, array( 'id' => $id ) );
	}
	return tkdp_format_project( tkdp_find_project( $id ) );
}

function tkdp_delete_project( WP_REST_Request $r ) {
	global $wpdb;
	$id = (int) $r['id'];
	if ( ! tkdp_find_project( $id ) ) {
		return tkdp_not_found( 'Projet' );
	}
	$t = tkdp_table( 'tasks' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE project_id = %d", $id ) ) as $task_id ) {
		tkdp_delete_task_zones( (int) $task_id );
	}
	$wpdb->delete( $t, array( 'project_id' => $id ), array( '%d' ) );
	$wpdb->delete( tkdp_table( 'projects' ), array( 'id' => $id ), array( '%d' ) );
	return array( 'deleted' => true );
}

/* -------------------------------------------------------------------------
 * Tâches
 * ---------------------------------------------------------------------- */

/** Nom affiché d'un rôle WordPress (traduit), ou '' s'il n'existe pas. */
function tkdp_role_name( $slug ) {
	$names = wp_roles()->role_names;
	return isset( $names[ $slug ] ) ? translate_user_role( $names[ $slug ] ) : '';
}

/**
 * Nettoie une liste de pièces jointes (identifiants de la médiathèque).
 *
 * @param mixed $value Tableau ou chaîne « 12,15 ».
 * @return string|null « 12,15 » ou null.
 */
function tkdp_clean_attachments( $value ) {
	$ids = is_array( $value ) ? $value : explode( ',', (string) $value );
	$ids = array_unique( array_filter( array_map( 'absint', $ids ) ) );
	$ids = array_filter(
		$ids,
		function ( $id ) {
			return 'attachment' === get_post_type( $id );
		}
	);
	$ids = array_slice( array_values( $ids ), 0, 20 );
	return $ids ? implode( ',', $ids ) : null;
}

/**
 * Détails des pièces jointes d'une tâche.
 *
 * @param object $row Ligne de la table des tâches.
 * @return array[]
 */
function tkdp_task_files( $row ) {
	$files = array();
	if ( empty( $row->attachments ) ) {
		return $files;
	}
	foreach ( explode( ',', $row->attachments ) as $id ) {
		$id   = (int) $id;
		$path = get_attached_file( $id );
		$url  = wp_get_attachment_url( $id );
		if ( ! $url ) {
			continue; // fichier supprimé de la médiathèque
		}
		$files[] = array(
			'id'       => $id,
			'title'    => get_the_title( $id ),
			'filename' => $path ? wp_basename( $path ) : wp_basename( $url ),
			'url'      => $url,
			'path'     => $path && file_exists( $path ) ? $path : '',
			'size'     => $path && file_exists( $path ) ? (int) filesize( $path ) : 0,
			'thumb'    => wp_attachment_is_image( $id ) ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '',
		);
	}
	return $files;
}

function tkdp_format_task( $row ) {
	$user = $row->assignee ? get_userdata( (int) $row->assignee ) : null;
	$role = isset( $row->assignee_role ) ? (string) $row->assignee_role : '';
	return array(
		'assignee_role' => $role,
		'role_name'     => $role ? tkdp_role_name( $role ) : '',
		'id'            => (int) $row->id,
		'project_id'    => (int) $row->project_id,
		'title'         => $row->title,
		'description'   => (string) $row->description,
		'status'        => $row->status,
		'priority'      => $row->priority,
		'assignee'      => (int) $row->assignee,
		'assignee_name' => $user ? $user->display_name : '',
		'start_date'    => $row->start_date,
		'end_date'      => $row->end_date,
		'progress'      => (int) $row->progress,
		'depends_on'    => $row->depends_on ? (int) $row->depends_on : null,
		'zone_count'    => tkdp_count_zones( (int) $row->id ),
		'attachments'   => array_map(
			function ( $f ) {
				unset( $f['path'] ); // le chemin sur le serveur n'est jamais envoyé au navigateur
				return $f;
			},
			tkdp_task_files( $row )
		),
		'position'      => (int) $row->position,
	);
}

function tkdp_find_task( $id ) {
	global $wpdb;
	$t = tkdp_table( 'tasks' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
}

function tkdp_get_tasks( WP_REST_Request $r ) {
	global $wpdb;
	$pid = (int) $r['id'];
	if ( ! tkdp_find_project( $pid ) ) {
		return tkdp_not_found( 'Projet' );
	}
	$t = tkdp_table( 'tasks' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE project_id = %d ORDER BY position ASC, id ASC", $pid ) );
	return array_map( 'tkdp_format_task', $rows );
}

/**
 * Prépare les champs d'une tâche à partir de la requête.
 *
 * @param WP_REST_Request $r        Requête.
 * @param object|null     $existing Tâche existante (mise à jour) ou null (création).
 * @param int             $pid      Projet de la tâche.
 */
function tkdp_task_fields( WP_REST_Request $r, $existing, $pid ) {
	$partial = null !== $existing;
	$has     = function ( $key ) use ( $r, $partial ) {
		return ! $partial || $r->has_param( $key );
	};
	$f = array();

	if ( $has( 'title' ) ) {
		$f['title'] = mb_substr( sanitize_text_field( (string) $r->get_param( 'title' ) ), 0, 255 );
		if ( '' === $f['title'] ) {
			return tkdp_bad_request( 'Le titre de la tâche est obligatoire.' );
		}
	}
	if ( $has( 'description' ) ) {
		$f['description'] = sanitize_textarea_field( (string) $r->get_param( 'description' ) );
	}
	if ( $has( 'status' ) ) {
		$status      = (string) $r->get_param( 'status' );
		$f['status'] = in_array( $status, TKDP_STATUSES, true ) ? $status : 'todo';
	}
	if ( $has( 'priority' ) ) {
		$priority      = (string) $r->get_param( 'priority' );
		$f['priority'] = in_array( $priority, TKDP_PRIORITIES, true ) ? $priority : 'normal';
	}
	// Attribution : soit une personne, soit un rôle entier (les deux s'excluent).
	if ( $has( 'assignee' ) ) {
		$uid           = absint( $r->get_param( 'assignee' ) );
		$f['assignee'] = ( $uid && get_userdata( $uid ) ) ? $uid : 0;
	}
	if ( $has( 'assignee_role' ) ) {
		$role               = sanitize_key( (string) $r->get_param( 'assignee_role' ) );
		$f['assignee_role'] = ( $role && tkdp_role_name( $role ) ) ? $role : '';
	}
	if ( ! empty( $f['assignee_role'] ) ) {
		$f['assignee'] = 0;
	} elseif ( ! empty( $f['assignee'] ) ) {
		$f['assignee_role'] = '';
	}
	if ( $has( 'start_date' ) ) {
		$f['start_date'] = tkdp_clean_date( $r->get_param( 'start_date' ) );
	}
	if ( $has( 'end_date' ) ) {
		$f['end_date'] = tkdp_clean_date( $r->get_param( 'end_date' ) );
	}
	if ( $r->has_param( 'progress' ) ) {
		$f['progress'] = max( 0, min( 100, (int) $r->get_param( 'progress' ) ) );
	} elseif ( isset( $f['status'] ) && 'done' === $f['status'] ) {
		$f['progress'] = 100;
	} elseif ( ! $partial ) {
		$f['progress'] = 0;
	}
	if ( $has( 'depends_on' ) ) {
		$dep             = absint( $r->get_param( 'depends_on' ) );
		$dep_task        = $dep ? tkdp_find_task( $dep ) : null;
		$valid           = $dep_task && (int) $dep_task->project_id === $pid && ( ! $existing || $dep !== (int) $existing->id );
		$f['depends_on'] = $valid ? $dep : null;
	}

	if ( $has( 'attachments' ) ) {
		$f['attachments'] = tkdp_clean_attachments( $r->get_param( 'attachments' ) );
	}

	// La date de fin ne peut pas précéder la date de début.
	$start = array_key_exists( 'start_date', $f ) ? $f['start_date'] : ( $existing ? $existing->start_date : null );
	$end   = array_key_exists( 'end_date', $f ) ? $f['end_date'] : ( $existing ? $existing->end_date : null );
	if ( $start && $end && $end < $start ) {
		return tkdp_bad_request( 'La date de fin doit être postérieure ou égale à la date de début.' );
	}

	return $f;
}

function tkdp_create_task( WP_REST_Request $r ) {
	global $wpdb;
	$pid = (int) $r['id'];
	if ( ! tkdp_find_project( $pid ) ) {
		return tkdp_not_found( 'Projet' );
	}
	$f = tkdp_task_fields( $r, null, $pid );
	if ( is_wp_error( $f ) ) {
		return $f;
	}
	$t = tkdp_table( 'tasks' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$max             = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(position) FROM $t WHERE project_id = %d", $pid ) );
	$f['project_id'] = $pid;
	$f['position']   = null === $max ? 0 : (int) $max + 1;
	$f['created_at'] = current_time( 'mysql' );
	$wpdb->insert( $t, $f );

	$task = tkdp_find_task( $wpdb->insert_id );
	$out  = tkdp_format_task( $task );
	if ( $r->get_param( 'notify' ) && ( $task->assignee || $task->assignee_role ) ) {
		$out['notified'] = tkdp_send_task_mail( $task, 'assigned' );
	}
	return $out;
}

function tkdp_update_task( WP_REST_Request $r ) {
	global $wpdb;
	$task = tkdp_find_task( (int) $r['id'] );
	if ( ! $task ) {
		return tkdp_not_found( 'Tâche' );
	}
	$f = tkdp_task_fields( $r, $task, (int) $task->project_id );
	if ( is_wp_error( $f ) ) {
		return $f;
	}
	if ( $f ) {
		$wpdb->update( tkdp_table( 'tasks' ), $f, array( 'id' => (int) $task->id ) );
	}

	$new = tkdp_find_task( (int) $task->id );
	$out = tkdp_format_task( $new );

	// E-mail : nouvelle attribution, ou date limite modifiée pour une tâche déjà attribuée.
	if ( $r->get_param( 'notify' ) && ( $new->assignee || $new->assignee_role ) ) {
		$reassigned = (int) $new->assignee !== (int) $task->assignee || (string) $new->assignee_role !== (string) $task->assignee_role;
		if ( $reassigned ) {
			$out['notified'] = tkdp_send_task_mail( $new, 'assigned' );
		} elseif ( $new->end_date && $new->end_date !== $task->end_date ) {
			$out['notified'] = tkdp_send_task_mail( $new, 'deadline' );
		}
	}
	return $out;
}

function tkdp_delete_task( WP_REST_Request $r ) {
	global $wpdb;
	$id = (int) $r['id'];
	if ( ! tkdp_find_task( $id ) ) {
		return tkdp_not_found( 'Tâche' );
	}
	$t = tkdp_table( 'tasks' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "UPDATE $t SET depends_on = NULL WHERE depends_on = %d", $id ) );
	tkdp_delete_task_zones( $id );
	$wpdb->delete( $t, array( 'id' => $id ), array( '%d' ) );
	return array( 'deleted' => true );
}

function tkdp_reorder_tasks( WP_REST_Request $r ) {
	global $wpdb;
	$pid   = (int) $r['id'];
	$order = $r->get_param( 'order' );
	if ( ! is_array( $order ) ) {
		return tkdp_bad_request( 'Ordre invalide.' );
	}
	foreach ( array_values( $order ) as $i => $tid ) {
		$wpdb->update(
			tkdp_table( 'tasks' ),
			array( 'position' => $i ),
			array(
				'id'         => absint( $tid ),
				'project_id' => $pid,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}
	return array( 'ok' => true );
}

/* -------------------------------------------------------------------------
 * Utilisateurs (responsables possibles)
 * ---------------------------------------------------------------------- */

/**
 * Tous les comptes du site avec leurs rôles, et la liste des rôles utilisés.
 * Les adresses e-mail ne sont jamais renvoyées au navigateur.
 */
function tkdp_get_users() {
	$users  = array();
	$counts = array();
	foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $u ) {
		$roles   = array_values( (array) $u->roles );
		$users[] = array(
			'id'    => (int) $u->ID,
			'name'  => $u->display_name,
			'roles' => $roles,
		);
		foreach ( $roles as $role ) {
			$counts[ $role ] = isset( $counts[ $role ] ) ? $counts[ $role ] + 1 : 1;
		}
	}

	$roles = array();
	foreach ( wp_roles()->role_names as $slug => $name ) {
		if ( ! empty( $counts[ $slug ] ) ) {
			$roles[] = array(
				'slug'  => $slug,
				'name'  => translate_user_role( $name ),
				'count' => $counts[ $slug ],
			);
		}
	}

	return array(
		'users' => $users,
		'roles' => $roles,
	);
}
