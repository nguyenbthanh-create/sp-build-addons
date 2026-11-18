<?php
/**
 * Carte des zones (OpenStreetMap) : zones dessinées sur une tâche, affectées à des personnes,
 * consultables via un lien privé en lecture seule envoyé par e-mail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TKDP_ZONE_PARAM = 'tkdp-zone';

add_action( 'rest_api_init', 'tkdp_zone_routes' );
add_action( 'template_redirect', 'tkdp_maybe_render_zone_page', 0 );

/** Bibliothèque de carte Leaflet (chargée depuis unpkg, avec contrôle d'intégrité). */
function tkdp_leaflet_assets() {
	return array(
		'js'     => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
		'jsSri'  => 'sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==',
		'css'    => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
		'cssSri' => 'sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==',
		'tiles'  => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
	);
}

/** Centre de la carte par défaut : Claira. Modifiable avec le filtre « tkdp_map_center ». */
function tkdp_map_center() {
	return apply_filters( 'tkdp_map_center', array( 42.7613, 2.9567, 15 ) );
}

/* -------------------------------------------------------------------------
 * Données
 * ---------------------------------------------------------------------- */

/**
 * Nettoie les coins d'une zone : [[lat, lng], …], 3 à 500 points.
 *
 * @return array|null
 */
function tkdp_clean_coords( $value ) {
	if ( is_string( $value ) ) {
		$value = json_decode( $value, true );
	}
	if ( ! is_array( $value ) ) {
		return null;
	}
	$out = array();
	foreach ( $value as $pt ) {
		if ( ! is_array( $pt ) || count( $pt ) < 2 || ! is_numeric( $pt[0] ) || ! is_numeric( $pt[1] ) ) {
			continue;
		}
		$lat = (float) $pt[0];
		$lng = (float) $pt[1];
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			continue;
		}
		$out[] = array( round( $lat, 6 ), round( $lng, 6 ) );
		if ( count( $out ) >= 500 ) {
			break;
		}
	}
	return count( $out ) >= 3 ? $out : null;
}

function tkdp_format_zone( $row ) {
	$user   = $row->assignee ? get_userdata( (int) $row->assignee ) : null;
	$coords = json_decode( $row->coords, true );
	return array(
		'id'            => (int) $row->id,
		'task_id'       => (int) $row->task_id,
		'name'          => $row->name,
		'color'         => $row->color,
		'assignee'      => (int) $row->assignee,
		'assignee_name' => $user ? $user->display_name : '',
		'coords'        => is_array( $coords ) ? $coords : array(),
		'notes'         => (string) $row->notes,
	);
}

function tkdp_get_zone_rows( $task_id, $assignee = null ) {
	global $wpdb;
	$z = tkdp_table( 'zones' );
	if ( null === $assignee ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $z WHERE task_id = %d ORDER BY position ASC, id ASC", $task_id ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $z WHERE task_id = %d AND assignee = %d ORDER BY position ASC, id ASC", $task_id, $assignee ) );
}

function tkdp_count_zones( $task_id ) {
	global $wpdb;
	$z = tkdp_table( 'zones' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $z WHERE task_id = %d", $task_id ) );
}

function tkdp_delete_task_zones( $task_id ) {
	global $wpdb;
	$wpdb->delete( tkdp_table( 'zones' ), array( 'task_id' => (int) $task_id ), array( '%d' ) );
}

/** Lien privé (lecture seule) vers les zones d'une personne pour une tâche. */
function tkdp_zone_link( $task_id, $user_id ) {
	$rows = tkdp_get_zone_rows( $task_id, $user_id );
	if ( ! $rows ) {
		return '';
	}
	return add_query_arg( TKDP_ZONE_PARAM, $rows[0]->view_key, home_url( '/' ) );
}

/* -------------------------------------------------------------------------
 * API REST
 * ---------------------------------------------------------------------- */

function tkdp_zone_routes() {
	register_rest_route(
		'tkdp/v1',
		'/tasks/(?P<id>\d+)/zones',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'tkdp_get_zones',
				'permission_callback' => 'tkdp_can_edit',
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'tkdp_save_zones',
				'permission_callback' => 'tkdp_can_edit',
			),
		)
	);
}

function tkdp_get_zones( WP_REST_Request $r ) {
	$task = tkdp_find_task( (int) $r['id'] );
	if ( ! $task ) {
		return tkdp_not_found( 'Tâche' );
	}
	return array_map( 'tkdp_format_zone', tkdp_get_zone_rows( (int) $task->id ) );
}

/**
 * Enregistre toutes les zones d'une tâche (remplace la liste).
 * Les personnes nouvellement affectées reçoivent un e-mail avec leur lien privé.
 */
function tkdp_save_zones( WP_REST_Request $r ) {
	global $wpdb;
	$z    = tkdp_table( 'zones' );
	$task = tkdp_find_task( (int) $r['id'] );
	if ( ! $task ) {
		return tkdp_not_found( 'Tâche' );
	}
	$input = $r->get_param( 'zones' );
	if ( ! is_array( $input ) || count( $input ) > 100 ) {
		return tkdp_bad_request( 'Liste de zones invalide (100 zones maximum).' );
	}

	// Validation complète avant toute écriture.
	$clean = array();
	foreach ( array_values( $input ) as $i => $zone ) {
		if ( ! is_array( $zone ) ) {
			continue;
		}
		$name   = mb_substr( sanitize_text_field( (string) ( $zone['name'] ?? '' ) ), 0, 190 );
		$name   = '' === $name ? 'Zone ' . ( $i + 1 ) : $name;
		$coords = tkdp_clean_coords( $zone['coords'] ?? null );
		if ( ! $coords ) {
			return tkdp_bad_request( 'La zone « ' . $name . ' » doit avoir au moins 3 coins.' );
		}
		$uid   = absint( $zone['assignee'] ?? 0 );
		$color = sanitize_hex_color( (string) ( $zone['color'] ?? '' ) );
		$clean[] = array(
			'id'   => absint( $zone['id'] ?? 0 ),
			'data' => array(
				'name'     => $name,
				'color'    => $color ? $color : '#2271b1',
				'assignee' => ( $uid && get_userdata( $uid ) ) ? $uid : 0,
				'coords'   => wp_json_encode( $coords ),
				'notes'    => sanitize_textarea_field( (string) ( $zone['notes'] ?? '' ) ),
				'position' => $i,
			),
		);
	}

	$existing = array();
	foreach ( tkdp_get_zone_rows( (int) $task->id ) as $row ) {
		$existing[ (int) $row->id ] = $row;
	}

	$keep   = array();
	$gained = array(); // personnes qui reçoivent au moins une nouvelle zone
	foreach ( $clean as $item ) {
		$data = $item['data'];
		$id   = $item['id'];
		if ( $id && isset( $existing[ $id ] ) ) {
			if ( (int) $existing[ $id ]->assignee !== $data['assignee'] ) {
				// Changement de personne : nouveau lien, l'ancien cesse de fonctionner.
				$data['view_key'] = wp_generate_password( 32, false );
				if ( $data['assignee'] ) {
					$gained[ $data['assignee'] ] = true;
				}
			}
			$wpdb->update( $z, $data, array( 'id' => $id ) );
			$keep[] = $id;
		} else {
			$data['task_id']    = (int) $task->id;
			$data['view_key']   = wp_generate_password( 32, false );
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $z, $data );
			$keep[] = (int) $wpdb->insert_id;
			if ( $data['assignee'] ) {
				$gained[ $data['assignee'] ] = true;
			}
		}
	}
	foreach ( array_keys( $existing ) as $id ) {
		if ( ! in_array( $id, $keep, true ) ) {
			$wpdb->delete( $z, array( 'id' => $id ), array( '%d' ) );
		}
	}

	$out = array( 'zones' => array_map( 'tkdp_format_zone', tkdp_get_zone_rows( (int) $task->id ) ) );
	if ( $r->get_param( 'notify' ) && $gained ) {
		$sent = 0;
		foreach ( array_keys( $gained ) as $uid ) {
			$sent += tkdp_send_zone_mail( $task, $uid );
		}
		$out['notified'] = $sent;
		$out['expected'] = count( $gained );
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * E-mail
 * ---------------------------------------------------------------------- */

/**
 * Prévient une personne de ses zones pour une tâche. Renvoie 1 si l'e-mail est parti, sinon 0.
 */
function tkdp_send_zone_mail( $task, $user_id ) {
	$user  = get_userdata( (int) $user_id );
	$zones = tkdp_get_zone_rows( (int) $task->id, (int) $user_id );
	if ( ! $user || ! $zones || ! is_email( $user->user_email ) ) {
		return 0;
	}

	$project   = tkdp_find_project( (int) $task->project_id );
	$color     = $project && $project->color ? $project->color : '#2271b1';
	$site      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$count     = count( $zones );
	$subject   = sprintf( '[%s] %s : %s', $site, 1 === $count ? 'Votre zone' : 'Vos ' . $count . ' zones', $task->title );
	$assigner  = wp_get_current_user();
	$countdown = tkdp_mail_countdown( $task->end_date );
	list( $files, $paths ) = tkdp_mail_files( $task );

	$list = '';
	foreach ( $zones as $zone ) {
		$list .= '<div style="margin:0 0 6px"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;vertical-align:middle;background:' . esc_attr( $zone->color ) . '"></span> '
			. '<strong>' . esc_html( $zone->name ) . '</strong>'
			. ( '' !== trim( (string) $zone->notes ) ? '<br><span style="color:#50575e">' . nl2br( esc_html( $zone->notes ) ) . '</span>' : '' ) . '</div>';
	}

	$rows = array(
		'Tâche'                              => '<strong>' . esc_html( $task->title ) . '</strong>',
		'Projet'                             => esc_html( $project ? $project->name : '' ),
		1 === $count ? 'Votre zone' : 'Vos zones' => $list,
		'Date limite'                        => '<strong style="color:#b32d2e">' . esc_html( tkdp_mail_date( $task->end_date ) ) . '</strong>'
			. ( $countdown ? ' <span style="color:#646970">(' . esc_html( $countdown ) . ')</span>' : '' ),
	);
	if ( '' !== trim( (string) $task->description ) ) {
		$rows['Consignes'] = nl2br( esc_html( $task->description ) );
	}
	if ( $files ) {
		$rows['Pièces jointes'] = tkdp_mail_files_html( $files );
	}

	$url     = tkdp_zone_link( (int) $task->id, (int) $user_id );
	$content = '<p>Bonjour ' . esc_html( $user->display_name ) . ',</p>'
		. '<p>' . esc_html( $assigner->display_name ) . ' vous a affecté' . ( 1 === $count ? ' une zone' : ' ' . $count . ' zones' ) . ' sur la carte.</p>'
		. tkdp_mail_table( $rows )
		. '<p style="margin:24px 0 8px"><a href="' . esc_url( $url ) . '" style="background:' . esc_attr( $color ) . ';color:#fff;text-decoration:none;padding:12px 20px;border-radius:6px;display:inline-block;font-weight:bold">🗺️ Voir ma carte</a></p>'
		. '<p style="color:#646970;font-size:12px">Ce lien est personnel : il ouvre votre carte sans mot de passe, ne le transférez pas.</p>';

	$from_name = function () use ( $site ) {
		return $site;
	};
	add_filter( 'wp_mail_from_name', $from_name );
	$ok = wp_mail( $user->user_email, $subject, tkdp_mail_layout( $color, $content ), array( 'Content-Type: text/html; charset=UTF-8' ), $paths );
	remove_filter( 'wp_mail_from_name', $from_name );

	return $ok ? 1 : 0;
}

/* -------------------------------------------------------------------------
 * Page privée « Ma carte » : https://votre-site/?tkdp-zone=CLÉ
 * ---------------------------------------------------------------------- */

function tkdp_zone_error_page( $title, $message ) {
	status_header( 404 );
	echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
		. '<title>' . esc_html( $title ) . '</title></head><body style="font-family:sans-serif;padding:40px 20px;text-align:center;color:#1d2327">'
		. '<h1 style="font-size:20px">' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></body></html>';
	exit;
}

function tkdp_maybe_render_zone_page() {
	global $wpdb;
	if ( ! isset( $_GET[ TKDP_ZONE_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Referrer-Policy: no-referrer' );

	$key = (string) wp_unslash( $_GET[ TKDP_ZONE_PARAM ] ); // phpcs:ignore WordPress.Security
	if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', $key ) ) {
		tkdp_zone_error_page( 'Lien invalide', 'Ce lien de carte n\'est pas valide.' );
	}
	$z = tkdp_table( 'zones' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $z WHERE view_key = %s LIMIT 1", $key ) );
	$task = $row ? tkdp_find_task( (int) $row->task_id ) : null;
	if ( ! $row || ! $row->assignee || ! $task ) {
		tkdp_zone_error_page( 'Lien expiré', 'Cette zone a été modifiée ou réattribuée. Demandez un nouveau lien au responsable.' );
	}

	$user    = get_userdata( (int) $row->assignee );
	$project = tkdp_find_project( (int) $task->project_id );
	$color   = $project && $project->color ? $project->color : '#2271b1';
	$site    = get_bloginfo( 'name' );
	$zones   = array();
	foreach ( tkdp_get_zone_rows( (int) $task->id, (int) $row->assignee ) as $zrow ) {
		$f       = tkdp_format_zone( $zrow );
		$zones[] = array(
			'name'   => $f['name'],
			'color'  => $f['color'],
			'coords' => $f['coords'],
			'notes'  => $f['notes'],
		);
	}
	$files = array();
	foreach ( tkdp_task_files( $task ) as $file ) {
		$files[] = array(
			'url'      => $file['url'],
			'filename' => $file['filename'],
		);
	}
	$data = array(
		'zones'   => $zones,
		'center'  => tkdp_map_center(),
		'leaflet' => tkdp_leaflet_assets(),
	);
	$leaflet   = tkdp_leaflet_assets();
	$countdown = tkdp_mail_countdown( $task->end_date );
	?><!doctype html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<meta name="referrer" content="no-referrer">
	<title><?php echo esc_html( $task->title . ' — ' . $site ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $leaflet['css'] ); ?>" integrity="<?php echo esc_attr( $leaflet['cssSri'] ); ?>" crossorigin="">
	<style>
		* { box-sizing: border-box; }
		html, body { margin: 0; height: 100%; }
		body { display: flex; flex-direction: column; font: 14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1d2327; background: #f0f0f1; }
		header { padding: 12px 16px; padding-top: max(12px, env(safe-area-inset-top)); background: #fff; border-top: 5px solid <?php echo esc_attr( $color ); ?>; box-shadow: 0 1px 3px rgba(0,0,0,.08); z-index: 2; }
		header h1 { font-size: 17px; margin: 0 0 2px; }
		header .meta { color: #50575e; font-size: 13px; }
		header .deadline { color: #b32d2e; font-weight: 600; }
		#map { flex: 1; min-height: 300px; }
		.panel { background: #fff; padding: 12px 16px; padding-bottom: max(12px, env(safe-area-inset-bottom)); max-height: 40vh; overflow-y: auto; box-shadow: 0 -1px 3px rgba(0,0,0,.08); z-index: 2; }
		.zone { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; border-bottom: 1px solid #f0f0f1; }
		.zone:last-child { border-bottom: 0; }
		.swatch { width: 14px; height: 14px; border-radius: 3px; flex: none; margin-top: 3px; }
		.zone .body { flex: 1; }
		.zone .notes { color: #50575e; white-space: pre-line; }
		.btn { display: inline-block; border: 1px solid #2271b1; color: #2271b1; background: #fff; border-radius: 5px; padding: 6px 10px; font: inherit; font-size: 13px; text-decoration: none; cursor: pointer; white-space: nowrap; }
		.btn-primary { background: #2271b1; color: #fff; }
		.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
		.desc { white-space: pre-line; margin: 8px 0 0; }
		.files a { display: block; color: #2271b1; margin-top: 4px; }
		.err { padding: 16px; color: #b32d2e; }
		.tkdp-zone-label { background: rgba(255,255,255,.85); border: 0; box-shadow: none; font-weight: 600; color: #1d2327; }
		.tkdp-zone-label::before { display: none; }
	</style>
</head>
<body>
	<header>
		<h1><?php echo esc_html( $task->title ); ?></h1>
		<div class="meta">
			<?php echo esc_html( $user ? $user->display_name : '' ); ?>
			<?php if ( $task->end_date ) : ?>
				· à faire avant le <span class="deadline"><?php echo esc_html( tkdp_mail_date( $task->end_date ) ); ?></span>
				<?php echo $countdown ? '(' . esc_html( $countdown ) . ')' : ''; ?>
			<?php endif; ?>
		</div>
	</header>
	<div id="map"></div>
	<div class="panel">
		<div id="zones"></div>
		<div class="actions">
			<button type="button" class="btn btn-primary" id="locate">📍 Me localiser</button>
		</div>
		<?php if ( '' !== trim( (string) $task->description ) ) : ?>
			<p class="desc"><strong>Consignes :</strong> <?php echo esc_html( $task->description ); ?></p>
		<?php endif; ?>
		<?php if ( $files ) : ?>
			<div class="files"><strong>Documents :</strong>
				<?php foreach ( $files as $file ) : ?>
					<a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank" rel="noopener">📎 <?php echo esc_html( $file['filename'] ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<script>window.TKDP_ZONE = <?php echo wp_json_encode( $data ); ?>;</script>
	<script src="<?php echo esc_url( $leaflet['js'] ); ?>" integrity="<?php echo esc_attr( $leaflet['jsSri'] ); ?>" crossorigin=""></script>
	<script src="<?php echo esc_url( TKDP_URL . 'assets/zone-view.js?ver=' . rawurlencode( TKDP_VERSION ) ); ?>"></script>
</body>
</html>
	<?php
	exit;
}
