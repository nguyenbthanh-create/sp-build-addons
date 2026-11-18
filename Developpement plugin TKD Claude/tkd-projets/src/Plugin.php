<?php

namespace TkdProjets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Branche les hooks WordPress de l'extension. */
final class Plugin {

	public static function boot(): void {
		add_action( 'plugins_loaded', array( Installer::class, 'maybeUpgrade' ) );
		add_action( 'rest_api_init', array( Rest\Routes::class, 'register' ) );
		add_action( 'template_redirect', array( Front\ZonePage::class, 'maybeRender' ), 0 );
		add_filter( 'wp_privacy_personal_data_exporters', array( Privacy::class, 'registerExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( Privacy::class, 'registerEraser' ) );
		if ( is_admin() ) {
			Admin\Page::boot();
		}
	}
}
