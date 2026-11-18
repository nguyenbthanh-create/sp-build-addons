<?php
/**
 * E-mails d'attribution de tâche.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Personnes à prévenir : le responsable, ou tous les comptes du rôle attribué.
 *
 * @param object $task Ligne de la table des tâches.
 * @return WP_User[]
 */
function tkdp_task_recipients( $task ) {
	if ( $task->assignee ) {
		$user = get_userdata( (int) $task->assignee );
		return $user ? array( $user ) : array();
	}
	if ( $task->assignee_role ) {
		return get_users( array( 'role' => $task->assignee_role ) );
	}
	return array();
}

/** Date AAAA-MM-JJ -> « lundi 17 novembre 2026 » (langue du site). */
function tkdp_mail_date( $date ) {
	if ( ! $date ) {
		return '—';
	}
	$utc = new DateTimeZone( 'UTC' );
	return wp_date( 'l j F Y', ( new DateTime( $date . ' 12:00', $utc ) )->getTimestamp(), $utc );
}

/** « dans 5 jours », « aujourd'hui », « dépassée de 2 jours »… */
function tkdp_mail_countdown( $date ) {
	if ( ! $date ) {
		return '';
	}
	$utc   = new DateTimeZone( 'UTC' );
	$today = new DateTime( wp_date( 'Y-m-d' ), $utc );
	$days  = (int) $today->diff( new DateTime( $date, $utc ) )->format( '%r%a' );
	if ( 0 === $days ) {
		return 'aujourd\'hui';
	}
	if ( 1 === $days ) {
		return 'demain';
	}
	if ( $days > 1 ) {
		return 'dans ' . $days . ' jours';
	}
	return 'dépassée de ' . abs( $days ) . ' jour' . ( -1 === $days ? '' : 's' );
}

/**
 * Pièces jointes de la tâche pour un e-mail : jointes jusqu'à 10 Mo au total, sinon envoyées sous forme de lien.
 *
 * @param object $task Ligne de la table des tâches.
 * @return array [ fichiers (avec la clé « attached »), chemins à joindre ]
 */
function tkdp_mail_files( $task ) {
	$files    = tkdp_task_files( $task );
	$paths    = array();
	$total    = 0;
	$max_size = (int) apply_filters( 'tkdp_mail_max_attachment_size', 10 * MB_IN_BYTES );
	foreach ( $files as $i => $file ) {
		$files[ $i ]['attached'] = false;
		if ( $file['path'] && $total + $file['size'] <= $max_size ) {
			$paths[]                 = $file['path'];
			$total                  += $file['size'];
			$files[ $i ]['attached'] = true;
		}
	}
	return array( $files, $paths );
}

/** Liste HTML des pièces jointes pour un e-mail. */
function tkdp_mail_files_html( $files ) {
	$items = array();
	foreach ( $files as $file ) {
		$items[] = '📎 <a href="' . esc_url( $file['url'] ) . '" style="color:#2271b1">' . esc_html( $file['filename'] ) . '</a>'
			. ' <span style="color:#646970">(' . ( $file['size'] ? esc_html( size_format( $file['size'], 1 ) ) . ', ' : '' )
			. ( ! empty( $file['attached'] ) ? 'jointe à cet e-mail' : 'à télécharger' ) . ')</span>';
	}
	return implode( '<br>', $items );
}

/** Habillage commun des e-mails du plugin. */
function tkdp_mail_layout( $color, $content ) {
	return '<!doctype html><html lang="fr"><body style="margin:0;background:#f0f0f1;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1d2327">'
		. '<div style="max-width:600px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden;border-top:5px solid ' . esc_attr( $color ) . '">'
		. '<div style="padding:24px">' . $content . '</div>'
		. '<div style="padding:12px 24px;background:#f6f7f7;color:#646970;font-size:12px">E-mail envoyé automatiquement par ' . esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '.</div>'
		. '</div></body></html>';
}

/** Tableau « libellé / valeur » pour un e-mail. */
function tkdp_mail_table( $rows ) {
	$table = '';
	foreach ( $rows as $label => $value ) {
		$table .= '<tr><td style="padding:8px 12px;color:#646970;white-space:nowrap;vertical-align:top;border-bottom:1px solid #eee">' . esc_html( $label ) . '</td>'
			. '<td style="padding:8px 12px;border-bottom:1px solid #eee">' . $value . '</td></tr>';
	}
	return '<table style="width:100%;border-collapse:collapse;margin-top:16px">' . $table . '</table>';
}

/**
 * Envoie l'e-mail à chaque destinataire (un envoi par personne, les adresses ne sont jamais partagées).
 *
 * @param object $task   Ligne de la table des tâches.
 * @param string $reason assigned|deadline.
 * @return int Nombre d'e-mails envoyés.
 */
function tkdp_send_task_mail( $task, $reason ) {
	$recipients = tkdp_task_recipients( $task );
	if ( ! $recipients ) {
		return 0;
	}

	$project = tkdp_find_project( (int) $task->project_id );
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = 'deadline' === $reason
		? sprintf( '[%s] Date limite modifiée : %s', $site, $task->title )
		: sprintf( '[%s] Nouvelle tâche : %s', $site, $task->title );

	list( $files, $paths ) = tkdp_mail_files( $task );

	$from_name = function () use ( $site ) {
		return $site;
	};
	add_filter( 'wp_mail_from_name', $from_name );

	$sent = 0;
	foreach ( $recipients as $user ) {
		if ( ! is_email( $user->user_email ) ) {
			continue;
		}
		$body = tkdp_mail_body( $task, $project, $user, $reason, $files );
		if ( wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), $paths ) ) {
			++$sent;
		}
	}

	remove_filter( 'wp_mail_from_name', $from_name );
	return $sent;
}

/**
 * Contenu HTML de l'e-mail.
 */
function tkdp_mail_body( $task, $project, WP_User $user, $reason, $files = array() ) {
	$color     = $project && $project->color ? $project->color : '#2271b1';
	$pname     = $project ? $project->name : '';
	$assigner  = wp_get_current_user();
	$priority  = array(
		'low'    => 'Basse',
		'normal' => 'Normale',
		'high'   => 'Haute',
	);
	$status    = array(
		'todo'  => 'À faire',
		'doing' => 'En cours',
		'done'  => 'Terminé',
	);
	$countdown = tkdp_mail_countdown( $task->end_date );

	if ( 'deadline' === $reason ) {
		$intro = sprintf(
			'La date limite de la tâche <strong>« %s »</strong> a été modifiée par %s.',
			esc_html( $task->title ),
			esc_html( $assigner->display_name )
		);
	} elseif ( $task->assignee_role ) {
		$intro = sprintf(
			'%s a attribué une tâche à tous les membres du rôle <strong>%s</strong>, dont vous faites partie.',
			esc_html( $assigner->display_name ),
			esc_html( tkdp_role_name( $task->assignee_role ) )
		);
	} else {
		$intro = sprintf( '%s vous a attribué une nouvelle tâche.', esc_html( $assigner->display_name ) );
	}

	$rows = array(
		'Tâche'        => '<strong>' . esc_html( $task->title ) . '</strong>',
		'Projet'       => esc_html( $pname ),
		'Priorité'     => esc_html( $priority[ $task->priority ] ?? '' ) . ( 'high' === $task->priority ? ' ⚠️' : '' ),
		'Statut'       => esc_html( $status[ $task->status ] ?? '' ),
		'Début'        => esc_html( tkdp_mail_date( $task->start_date ) ),
		'Date limite'  => '<strong style="color:#b32d2e">' . esc_html( tkdp_mail_date( $task->end_date ) ) . '</strong>'
			. ( $countdown ? ' <span style="color:#646970">(' . esc_html( $countdown ) . ')</span>' : '' ),
	);
	if ( '' !== trim( (string) $task->description ) ) {
		$rows['Description'] = nl2br( esc_html( $task->description ) );
	}

	if ( $files ) {
		$rows['Pièces jointes'] = tkdp_mail_files_html( $files );
	}

	$button = '';
	if ( user_can( $user, 'edit_posts' ) ) {
		$url    = admin_url( 'admin.php?page=tkd-projets#projet-' . (int) $task->project_id );
		$button = '<p style="margin:24px 0 8px"><a href="' . esc_url( $url ) . '" style="background:' . esc_attr( $color ) . ';color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block">Voir le projet</a></p>';
	}

	return tkdp_mail_layout(
		$color,
		'<p>Bonjour ' . esc_html( $user->display_name ) . ',</p><p>' . $intro . '</p>' . tkdp_mail_table( $rows ) . $button
	);
}
