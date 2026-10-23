<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function claira_tkd_enqueue_assets() {
    wp_register_style(
        'claira-tkd-style',
        CLAIRA_TKD_PLUGIN_URL . 'assets/css/style.css',
        array(),
        CLAIRA_TKD_PLUGIN_VERSION
    );
    wp_register_script(
        'claira-tkd-script',
        CLAIRA_TKD_PLUGIN_URL . 'assets/js/script.js',
        array(),
        CLAIRA_TKD_PLUGIN_VERSION,
        true
    );

    wp_enqueue_style( 'claira-tkd-style' );
    wp_enqueue_script( 'claira-tkd-script' );
}

function claira_tkd_admin_assets( $hook ) {
    // Pages « Grades » (…_page_claira-tkd-parcours) et « Vidéothèque » (…_page_claira-tkd-videotheque).
    if ( false === strpos( $hook, 'claira-tkd-parcours' ) && false === strpos( $hook, 'claira-tkd-videotheque' ) ) {
        return;
    }

    wp_enqueue_style( 'claira-tkd-admin-style', CLAIRA_TKD_PLUGIN_URL . 'assets/css/admin.css', array(), CLAIRA_TKD_PLUGIN_VERSION );
}
