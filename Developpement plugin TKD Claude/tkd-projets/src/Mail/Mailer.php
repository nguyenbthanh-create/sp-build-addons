<?php

namespace TkdProjets\Mail;

use TkdProjets\Access;
use TkdProjets\Attachments;
use TkdProjets\Repository\Projects;
use TkdProjets\Repository\Zones;
use TkdProjets\Rest\Format;
use TkdProjets\ZoneLinks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** E-mails de l'extension : un envoi par personne, les adresses ne sont jamais partagées. */
final class Mailer {

	private const PRIORITY = array(
		'low'    => 'Basse',
		'normal' => 'Normale',
		'high'   => 'Haute',
	);

	private const STATUS = array(
		'todo'  => 'À faire',
		'doing' => 'En cours',
		'done'  => 'Terminé',
	);

	/**
	 * Attribution d'une tâche, ou date limite modifiée.
	 *
	 * @param string $reason assigned|deadline.
	 * @return int Nombre d'e-mails partis.
	 */
	public static function taskMail( object $task, string $reason ): int {
		$recipients = self::recipients( $task );
		if ( ! $recipients ) {
			return 0;
		}
		$project = Projects::find( (int) $task->project_id );
		$site    = self::siteName();
		$subject = 'deadline' === $reason
			? sprintf( '[%s] Date limite modifiée : %s', $site, $task->title )
			: sprintf( '[%s] Nouvelle tâche : %s', $site, $task->title );
		list( $files, $paths ) = self::files( $task );

		$sent = 0;
		foreach ( $recipients as $user ) {
			if ( self::send( $user, $subject, self::taskBody( $task, $project, $user, $reason, $files ), $paths ) ) {
				++$sent;
			}
		}
		return $sent;
	}

	/**
	 * Lien privé vers les zones d'une personne. Renvoie 1 si l'e-mail est parti, sinon 0.
	 *
	 * @param string $key     Clé en clair (n'existe que dans cet e-mail).
	 * @param string $expires Date d'expiration du lien (AAAA-MM-JJ HH:MM:SS).
	 */
	public static function zoneMail( object $task, int $userId, string $key, string $expires ): int {
		$user  = get_userdata( $userId );
		$zones = Zones::forTask( (int) $task->id, $userId );
		if ( ! $user || ! $zones ) {
			return 0;
		}
		$project   = Projects::find( (int) $task->project_id );
		$color     = $project ? $project->color : '#2271b1';
		$count     = count( $zones );
		$subject   = sprintf( '[%s] %s : %s', self::siteName(), 1 === $count ? 'Votre zone' : 'Vos ' . $count . ' zones', $task->title );
		$assigner  = wp_get_current_user();
		$countdown = self::countdown( $task->end_date );
		list( $files, $paths ) = self::files( $task );

		$list = '';
		foreach ( $zones as $zone ) {
			$list .= '<div style="margin:0 0 6px"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;vertical-align:middle;background:' . esc_attr( $zone->color ) . '"></span> '
				. '<strong>' . esc_html( $zone->name ) . '</strong>'
				. ( '' !== trim( (string) $zone->notes ) ? '<br><span style="color:#50575e">' . nl2br( esc_html( $zone->notes ) ) . '</span>' : '' ) . '</div>';
		}

		$rows = array(
			'Tâche'                                   => '<strong>' . esc_html( $task->title ) . '</strong>',
			'Projet'                                  => esc_html( $project ? $project->name : '' ),
			1 === $count ? 'Votre zone' : 'Vos zones' => $list,
			'Date limite'                             => '<strong style="color:#b32d2e">' . esc_html( self::date( $task->end_date ) ) . '</strong>'
				. ( $countdown ? ' <span style="color:#646970">(' . esc_html( $countdown ) . ')</span>' : '' ),
		);
		if ( '' !== trim( (string) $task->description ) ) {
			$rows['Consignes'] = nl2br( esc_html( $task->description ) );
		}
		if ( $files ) {
			$rows['Pièces jointes'] = self::filesHtml( $files );
		}

		$url     = add_query_arg( ZoneLinks::PARAM, $key, home_url( '/' ) );
		$content = '<p>Bonjour ' . esc_html( $user->display_name ) . ',</p>'
			. '<p>' . esc_html( $assigner->display_name ) . ' vous a affecté' . ( 1 === $count ? ' une zone' : ' ' . $count . ' zones' ) . ' sur la carte.</p>'
			. self::table( $rows )
			. '<p style="margin:24px 0 8px"><a href="' . esc_url( $url ) . '" style="background:' . esc_attr( $color ) . ';color:#fff;text-decoration:none;padding:12px 20px;border-radius:6px;display:inline-block;font-weight:bold">🗺️ Voir ma carte</a></p>'
			. '<p style="color:#646970;font-size:12px">Ce lien est personnel : il ouvre votre carte sans mot de passe, ne le transférez pas. '
			. 'Il reste valable jusqu\'au ' . esc_html( self::date( substr( $expires, 0, 10 ) ) ) . ', tant que la tâche n\'est pas terminée.</p>';

		return self::send( $user, $subject, self::layout( $color, $content ), $paths ) ? 1 : 0;
	}

	/**
	 * Personnes à prévenir : la personne attribuée, ou tous les comptes du rôle attribué.
	 *
	 * @return \WP_User[]
	 */
	private static function recipients( object $task ): array {
		if ( $task->assignee ) {
			$user = get_userdata( (int) $task->assignee );
			return $user ? array( $user ) : array();
		}
		if ( $task->assignee_role && in_array( $task->assignee_role, Access::proposedRoles(), true ) ) {
			return get_users( array( 'role' => $task->assignee_role ) );
		}
		return array();
	}

	private static function send( \WP_User $user, string $subject, string $body, array $paths ): bool {
		if ( ! is_email( $user->user_email ) ) {
			return false;
		}
		$site      = self::siteName();
		$from_name = static fn() => $site;
		add_filter( 'wp_mail_from_name', $from_name );
		$ok = wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), $paths );
		remove_filter( 'wp_mail_from_name', $from_name );
		return (bool) $ok;
	}

	private static function siteName(): string {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	private static function taskBody( object $task, ?object $project, \WP_User $user, string $reason, array $files ): string {
		$color     = $project ? $project->color : '#2271b1';
		$assigner  = wp_get_current_user();
		$countdown = self::countdown( $task->end_date );

		if ( 'deadline' === $reason ) {
			$intro = sprintf( 'La date limite de la tâche <strong>« %s »</strong> a été modifiée par %s.', esc_html( $task->title ), esc_html( $assigner->display_name ) );
		} elseif ( $task->assignee_role ) {
			$intro = sprintf(
				'%s a attribué une tâche à tous les membres du rôle <strong>%s</strong>, dont vous faites partie.',
				esc_html( $assigner->display_name ),
				esc_html( Format::roleName( $task->assignee_role ) )
			);
		} else {
			$intro = sprintf( '%s vous a attribué une nouvelle tâche.', esc_html( $assigner->display_name ) );
		}

		$rows = array(
			'Tâche'       => '<strong>' . esc_html( $task->title ) . '</strong>',
			'Projet'      => esc_html( $project ? $project->name : '' ),
			'Priorité'    => esc_html( self::PRIORITY[ $task->priority ] ?? '' ) . ( 'high' === $task->priority ? ' ⚠️' : '' ),
			'Statut'      => esc_html( self::STATUS[ $task->status ] ?? '' ),
			'Début'       => esc_html( self::date( $task->start_date ) ),
			'Date limite' => '<strong style="color:#b32d2e">' . esc_html( self::date( $task->end_date ) ) . '</strong>'
				. ( $countdown ? ' <span style="color:#646970">(' . esc_html( $countdown ) . ')</span>' : '' ),
		);
		if ( '' !== trim( (string) $task->description ) ) {
			$rows['Description'] = nl2br( esc_html( $task->description ) );
		}
		if ( $files ) {
			$rows['Pièces jointes'] = self::filesHtml( $files );
		}

		$button = '';
		if ( null !== Access::roleIn( (int) $task->project_id, (int) $user->ID ) ) {
			$url    = admin_url( 'admin.php?page=tkd-projets#projet-' . (int) $task->project_id );
			$button = '<p style="margin:24px 0 8px"><a href="' . esc_url( $url ) . '" style="background:' . esc_attr( $color ) . ';color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block">Voir le projet</a></p>';
		}

		return self::layout( $color, '<p>Bonjour ' . esc_html( $user->display_name ) . ',</p><p>' . $intro . '</p>' . self::table( $rows ) . $button );
	}

	/**
	 * Pièces jointes : jointes jusqu'à 10 Mo au total (filtre « tkdp_mail_max_attachment_size »), sinon en lien.
	 *
	 * @return array{0:array,1:string[]} [ fichiers (avec « attached »), chemins à joindre ]
	 */
	private static function files( object $task ): array {
		$files = Attachments::files( $task->attachments );
		$paths = array();
		$total = 0;
		$max   = (int) apply_filters( 'tkdp_mail_max_attachment_size', 10 * MB_IN_BYTES );
		foreach ( $files as $i => $file ) {
			$files[ $i ]['attached'] = false;
			if ( $file['path'] && $total + $file['size'] <= $max ) {
				$paths[]                 = $file['path'];
				$total                  += $file['size'];
				$files[ $i ]['attached'] = true;
			}
		}
		return array( $files, $paths );
	}

	private static function filesHtml( array $files ): string {
		$items = array();
		foreach ( $files as $file ) {
			$items[] = '📎 <a href="' . esc_url( $file['url'] ) . '" style="color:#2271b1">' . esc_html( $file['filename'] ) . '</a>'
				. ' <span style="color:#646970">(' . ( $file['size'] ? esc_html( size_format( $file['size'], 1 ) ) . ', ' : '' )
				. ( ! empty( $file['attached'] ) ? 'jointe à cet e-mail' : 'à télécharger' ) . ')</span>';
		}
		return implode( '<br>', $items );
	}

	public static function layout( string $color, string $content ): string {
		return '<!doctype html><html lang="fr"><body style="margin:0;background:#f0f0f1;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1d2327">'
			. '<div style="max-width:600px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden;border-top:5px solid ' . esc_attr( $color ) . '">'
			. '<div style="padding:24px">' . $content . '</div>'
			. '<div style="padding:12px 24px;background:#f6f7f7;color:#646970;font-size:12px">E-mail envoyé automatiquement par ' . esc_html( self::siteName() ) . '.</div>'
			. '</div></body></html>';
	}

	private static function table( array $rows ): string {
		$table = '';
		foreach ( $rows as $label => $value ) {
			$table .= '<tr><td style="padding:8px 12px;color:#646970;white-space:nowrap;vertical-align:top;border-bottom:1px solid #eee">' . esc_html( $label ) . '</td>'
				. '<td style="padding:8px 12px;border-bottom:1px solid #eee">' . $value . '</td></tr>';
		}
		return '<table style="width:100%;border-collapse:collapse;margin-top:16px">' . $table . '</table>';
	}

	/** AAAA-MM-JJ → « lundi 17 novembre 2026 » (langue du site). */
	public static function date( ?string $date ): string {
		if ( ! $date ) {
			return '—';
		}
		$utc = new \DateTimeZone( 'UTC' );
		return wp_date( 'l j F Y', ( new \DateTime( $date . ' 12:00', $utc ) )->getTimestamp(), $utc );
	}

	/** « dans 5 jours », « aujourd'hui », « dépassée de 2 jours »… */
	public static function countdown( ?string $date ): string {
		if ( ! $date ) {
			return '';
		}
		$utc   = new \DateTimeZone( 'UTC' );
		$today = new \DateTime( wp_date( 'Y-m-d' ), $utc );
		$days  = (int) $today->diff( new \DateTime( $date, $utc ) )->format( '%r%a' );
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
}
