<?php

namespace TkdProjets\Front;

use TkdProjets\Admin\Page;
use TkdProjets\Attachments;
use TkdProjets\Mail\Mailer;
use TkdProjets\Repository\Links;
use TkdProjets\Repository\Projects;
use TkdProjets\Repository\Tasks;
use TkdProjets\Repository\Zones;
use TkdProjets\Rest\Format;
use TkdProjets\ZoneLinks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Page privée « Ma carte » : https://site/?tkdp-zone=CLÉ (lecture seule, sans compte). */
final class ZonePage {

	public static function maybeRender(): void {
		if ( ! isset( $_GET[ ZoneLinks::PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		// strict-origin (18/11/2026) : seul « https://site/ » est transmis, jamais la clé du lien ;
		// no-referrer faisait refuser les tuiles par OpenStreetMap (403 « Access blocked »).
		header( 'Referrer-Policy: strict-origin' );

		$key = sanitize_text_field( wp_unslash( (string) $_GET[ ZoneLinks::PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! ZoneLinks::isKeyFormat( $key ) ) {
			self::error( 'Lien invalide', 'Ce lien de carte n\'est pas valide.' );
		}
		$link    = Links::findByHash( ZoneLinks::hash( $key, wp_salt( 'auth' ) ) );
		$task    = $link ? Tasks::find( (int) $link->task_id ) : null;
		$project = $task ? Projects::find( (int) $task->project_id ) : null;
		if ( ! $link || ! $task || ! $project ) {
			self::error( 'Lien expiré', 'Ce lien n\'est plus valable. Demandez un nouveau lien au responsable.' );
		}
		$refusal = ZoneLinks::refusal( $link, (string) $task->status, (string) $project->status, current_time( 'mysql' ) );
		if ( '' !== $refusal ) {
			$messages = array(
				'revoked'  => 'Ce lien a été remplacé ou coupé. Utilisez le lien du dernier e-mail reçu, ou demandez-en un nouveau au responsable.',
				'expired'  => 'Ce lien a expiré. Demandez un nouveau lien au responsable.',
				'finished' => 'Cette tâche est terminée : la carte n\'est plus accessible.',
			);
			self::error( 'Lien expiré', $messages[ $refusal ] );
		}

		$zones = array();
		foreach ( Zones::forTask( (int) $task->id, (int) $link->user_id ) as $row ) {
			$f       = Format::zone( $row );
			$zones[] = array(
				'name'   => $f['name'],
				'color'  => $f['color'],
				'coords' => $f['coords'],
				'notes'  => $f['notes'],
			);
		}
		$files = array();
		foreach ( Attachments::files( $task->attachments, false ) as $file ) {
			$files[] = array(
				'url'      => $file['url'],
				'filename' => $file['filename'],
			);
		}
		self::render( $task, $project, get_userdata( (int) $link->user_id ), $zones, $files );
	}

	private static function error( string $title, string $message ): void {
		status_header( 404 );
		echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
			. '<title>' . esc_html( $title ) . '</title></head><body style="font-family:sans-serif;padding:40px 20px;text-align:center;color:#1d2327">'
			. '<h1 style="font-size:20px">' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></body></html>';
		exit;
	}

	/**
	 * @param \WP_User|false $user
	 */
	private static function render( object $task, object $project, $user, array $zones, array $files ): void {
		$color     = $project->color ? $project->color : '#2271b1';
		$leaflet   = Page::leafletAssets();
		$countdown = Mailer::countdown( $task->end_date );
		$data      = array(
			'zones'   => $zones,
			'center'  => Page::mapCenter(),
			'leaflet' => $leaflet,
		);
		?><!doctype html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<meta name="referrer" content="strict-origin">
	<title><?php echo esc_html( $task->title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
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
				· à faire avant le <span class="deadline"><?php echo esc_html( Mailer::date( $task->end_date ) ); ?></span>
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
}
