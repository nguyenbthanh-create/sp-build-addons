<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Champs texte d'un grade modifiables à la fois depuis l'administration et
 * depuis la fiche du site ([claira_tkd_parcours]). Titre, tranche d'âge, rang,
 * fichiers vidéo et téléchargements restent réservés à l'administration.
 */
function claira_tkd_get_grade_text_fields() {
    return array( 'tech_bras', 'tech_jambes', 'poomsae', 'min_age', 'video_url' );
}

/**
 * Nettoie et enregistre les champs texte d'un grade. Source unique pour le
 * formulaire d'administration et pour l'enregistrement depuis le site, afin
 * que les deux chemins produisent exactement les mêmes données.
 *
 * $raw : valeurs brutes (déjà passées par wp_unslash), indexées par nom de
 * champ sans préfixe (cf. claira_tkd_get_grade_text_fields()). Un champ
 * absent n'est pas modifié.
 */
function claira_tkd_save_grade_text_fields( $grade_id, array $raw ) {
    foreach ( claira_tkd_get_grade_text_fields() as $field ) {
        if ( ! array_key_exists( $field, $raw ) ) {
            continue;
        }
        $value = (string) $raw[ $field ];

        switch ( $field ) {
            case 'video_url':
                $value = esc_url_raw( trim( $value ) );
                break;
            case 'min_age':
                // « 7 ans » ou « 7 » => « 7 » ; « 14+ » conservé.
                $value = trim( preg_replace( '/\s*ans?\s*$/i', '', sanitize_text_field( $value ) ) );
                break;
            default:
                $value = sanitize_textarea_field( $value );
        }

        update_post_meta( $grade_id, '_claira_tkd_' . $field, $value );
    }
}

/**
 * Enregistrement depuis la fiche d'un grade sur le site (admin-ajax, comptes
 * connectés uniquement : pas de variante nopriv). Mêmes droits que la page
 * d'administration « TKD Parcours », plus le droit sur ce grade précis.
 */
function claira_tkd_ajax_front_save_grade() {
    check_ajax_referer( 'claira_tkd_front_edit', 'nonce' );

    $grade_id = isset( $_POST['grade_id'] ) ? absint( wp_unslash( $_POST['grade_id'] ) ) : 0;
    $grade    = $grade_id ? get_post( $grade_id ) : null;

    if ( ! $grade || 'tkd_grade' !== $grade->post_type ) {
        wp_send_json_error( array( 'message' => __( 'Grade introuvable.', 'claira-tkd-parcours' ) ), 404 );
    }
    if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $grade_id ) ) {
        wp_send_json_error( array( 'message' => __( "Vous n'avez pas le droit de modifier ce grade.", 'claira-tkd-parcours' ) ), 403 );
    }

    $raw = array();
    foreach ( claira_tkd_get_grade_text_fields() as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            $raw[ $field ] = wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyé dans claira_tkd_save_grade_text_fields().
        }
    }
    claira_tkd_save_grade_text_fields( $grade_id, $raw );

    wp_send_json_success( array( 'grade_id' => $grade_id ) );
}
