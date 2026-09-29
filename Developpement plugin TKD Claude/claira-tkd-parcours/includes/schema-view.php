<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Colonnes du schéma des grades : une par tranche d'âge affichée. Ado et
 * Adulte partagent une colonne, car ils partagent les mêmes grades (cf.
 * claira_tkd_get_import_data()). `order_age` désigne la liste de rangs keup
 * utilisée pour trier la colonne.
 */
function claira_tkd_get_schema_columns( $atts ) {
    return array(
        'baby'   => array(
            'title'     => 'Baby',
            'subtitle'  => $atts['sous_titre_baby'],
            'terms'     => array( 'Baby' ),
            'order_age' => 'Baby',
        ),
        'enfant' => array(
            'title'     => 'Enfant',
            'subtitle'  => $atts['sous_titre_enfant'],
            'terms'     => array( 'Enfant' ),
            'order_age' => 'Enfant',
        ),
        'ado'    => array(
            'title'     => 'Ado & Adulte',
            'subtitle'  => $atts['sous_titre_ado'],
            'terms'     => array( 'Adolescent', 'Adulte' ),
            'order_age' => 'Adolescent',
        ),
    );
}

/**
 * Couleurs d'une pastille du schéma, déduites du titre du grade avec la même
 * détection que le reste du plugin (claira_tkd_get_belt_color_class /
 * claira_tkd_get_bicolor_class). La palette est celle de l'ancienne page
 * « Schéma des grades » du site.
 */
function claira_tkd_get_schema_badge_style( $title ) {
    $colors = array(
        'white'  => '#ffffff',
        'yellow' => '#FFD700',
        'orange' => '#e67e22',
        'green'  => '#27ae60',
        'purple' => '#8e44ad',
        'blue'   => '#2980b9',
        'red'    => '#c0392b',
        'black'  => '#111111',
    );

    // Classe CSS => array( couleur gauche, couleur droite, couleur du nom ).
    $bicolors = array(
        'claira-tkd-bicolor-white-yellow'  => array( 'white', 'yellow', '#111111' ),
        'claira-tkd-bicolor-yellow-orange' => array( 'yellow', 'orange', '#ffffff' ),
        'claira-tkd-bicolor-orange-green'  => array( 'orange', 'green', '#ffffff' ),
        'claira-tkd-bicolor-red-black'     => array( 'red', 'black', '#FFD700' ),
        'claira-tkd-bicolor-green-blue'    => array( 'green', 'blue', '#ffffff' ),
        'claira-tkd-bicolor-blue-red'      => array( 'blue', 'red', '#ffffff' ),
    );

    // Couleur du texte posé sur chaque couleur unie.
    $text = array(
        'white'  => '#111111',
        'yellow' => '#111111',
        'black'  => '#FFD700',
    );

    $bicolor_class = claira_tkd_get_bicolor_class( $title );
    if ( $bicolor_class && isset( $bicolors[ $bicolor_class ] ) ) {
        list( $left, $right, $name_color ) = $bicolors[ $bicolor_class ];
        $split = ( 'black' === $right ) ? array( 45, 55 ) : array( 35, 65 );
        return array(
            'key'        => $left . '-' . $right,
            'background' => sprintf( 'linear-gradient(90deg, %s %d%%, %s %d%%)', $colors[ $left ], $split[0], $colors[ $right ], $split[1] ),
            'circle'     => sprintf( 'linear-gradient(90deg, %1$s 50%%, %2$s 50%%)', $colors[ $left ], $colors[ $right ] ),
            'color'      => $name_color,
            // Le rang est posé sur la couleur de gauche.
            'grade_color' => isset( $text[ $left ] ) ? $text[ $left ] : '#ffffff',
        );
    }

    $solid = str_replace( 'claira-tkd-belt-', '', claira_tkd_get_belt_color_class( $title ) );
    if ( ! isset( $colors[ $solid ] ) ) {
        return array(
            'key'        => 'default',
            'background' => '#64748b',
            'circle'     => '#64748b',
            'color'      => '#ffffff',
            'grade_color' => '#ffffff',
        );
    }

    return array(
        'key'        => $solid,
        'background' => $colors[ $solid ],
        'circle'     => $colors[ $solid ],
        'color'      => isset( $text[ $solid ] ) ? $text[ $solid ] : '#ffffff',
        'grade_color' => isset( $text[ $solid ] ) ? $text[ $solid ] : '#ffffff',
    );
}

/**
 * Découpe un titre de grade en nom de ceinture et étoiles : « Rouge (**) »
 * donne array( 'Rouge', '★★' ).
 */
function claira_tkd_split_grade_stars( $title ) {
    $stars = '';
    if ( preg_match( '/\((\*+)\)/', $title, $m ) ) {
        $stars = str_repeat( '★', strlen( $m[1] ) );
    }
    $name = trim( preg_replace( '/\s*\(\*+\)/', '', claira_tkd_get_grade_display_label( $title ) ) );
    return array( $name, $stars );
}

/**
 * [claira_tkd_schema_grades] — schéma des grades par tranche d'âge (colonnes
 * Baby / Enfant / Ado & Adulte), généré à chaque affichage depuis les grades
 * enregistrés dans « TKD Parcours ». Remplace le bloc HTML écrit à la main de
 * la page « Schéma des grades » : ajouter, renommer ou modifier un grade (y
 * compris son âge minimum conseillé) dans l'admin suffit à mettre la page à jour.
 *
 * Chaque pastille est cliquable : elle ouvre la fiche du grade (programme
 * technique, poomsae, vidéo, téléchargements), la même que dans
 * [claira_tkd_parcours] (claira_tkd_render_grade_modal()).
 *
 * Attributs (tous facultatifs) : bandeau, aide, note, sous_titre_baby,
 * sous_titre_enfant, sous_titre_ado.
 */
function claira_tkd_schema_grades_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'bandeau'           => __( 'Toutes les catégories d’âge commencent le parcours par la ceinture blanche', 'claira-tkd-parcours' ),
        'aide'              => __( 'Cliquez sur un grade pour voir son programme technique.', 'claira-tkd-parcours' ),
        'note'              => __( 'Les âges minimums sont donnés à titre de conseil : après examen, les entraîneurs peuvent autoriser un passage de grade plus tôt. Le bureau et l’équipe pédagogique étudient chaque dossier au cas par cas.', 'claira-tkd-parcours' ),
        'sous_titre_baby'   => __( '3 – 6 ans', 'claira-tkd-parcours' ),
        'sous_titre_enfant' => __( '7 ans révolus', 'claira-tkd-parcours' ),
        'sous_titre_ado'    => __( '11 ans révolus', 'claira-tkd-parcours' ),
    ), $atts, 'claira_tkd_schema_grades' );

    $columns_html = '';
    $modals_html  = '';

    foreach ( claira_tkd_get_schema_columns( $atts ) as $slug => $column ) {
        $term_ids = array();
        foreach ( $column['terms'] as $term_name ) {
            $term = get_term_by( 'name', $term_name, 'tkd_age_group' );
            if ( $term && ! is_wp_error( $term ) ) {
                $term_ids[] = $term->term_id;
            }
        }
        if ( ! $term_ids ) {
            continue;
        }

        $query = new WP_Query( array(
            'post_type'      => 'tkd_grade',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'tkd_age_group',
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                ),
            ),
        ) );
        if ( ! $query->have_posts() ) {
            continue;
        }

        $grades = claira_tkd_sort_grades_by_keup( $query->posts, $column['order_age'] );

        ob_start();
        ?>
        <div class="claira-tkd-schema-col claira-tkd-schema-col-<?php echo esc_attr( $slug ); ?>">
            <div class="claira-tkd-schema-col-header">
                <div class="claira-tkd-schema-col-title"><?php echo esc_html( $column['title'] ); ?></div>
                <?php if ( '' !== $column['subtitle'] ) : ?>
                    <div class="claira-tkd-schema-col-sub"><?php echo esc_html( $column['subtitle'] ); ?></div>
                <?php endif; ?>
            </div>
            <div class="claira-tkd-schema-col-body">
                <?php
                $current_age = null;
                foreach ( $grades as $grade ) :
                    $min_age = trim( (string) get_post_meta( $grade->ID, '_claira_tkd_min_age', true ) );
                    $keup    = get_post_meta( $grade->ID, '_claira_tkd_keup_rank', true );
                    $title   = get_the_title( $grade );
                    $style   = claira_tkd_get_schema_badge_style( $title );
                    list( $name, $stars ) = claira_tkd_split_grade_stars( $title );

                    // Un intertitre d'âge à chaque changement ; un grade sans âge
                    // renseigné reste dans le groupe précédent.
                    if ( '' !== $min_age && $min_age !== $current_age ) :
                        $current_age = $min_age;
                        ?>
                        <div class="claira-tkd-schema-age">
                            <span class="claira-tkd-schema-age-accent"></span>
                            <span class="claira-tkd-schema-age-val"><?php echo esc_html( sprintf( __( '%s ans', 'claira-tkd-parcours' ), $min_age ) ); ?></span>
                            <span class="claira-tkd-schema-age-note"><?php esc_html_e( '— âge min. conseillé', 'claira-tkd-parcours' ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php
                    $modal_id = 'claira-tkd-schema-grade-' . $grade->ID;
                    $modals_html .= claira_tkd_render_grade_modal( $grade, $modal_id, array(
                        'title' => trim( $name . ' ' . $stars ),
                        'tags'  => array(
                            $keup,
                            $column['title'],
                            '' !== $min_age ? sprintf( __( 'âge min. conseillé : %s ans', 'claira-tkd-parcours' ), $min_age ) : '',
                        ),
                        'class' => 'claira-tkd-modal--light',
                    ) );
                    ?>
                    <button type="button" class="claira-tkd-schema-badge claira-tkd-schema-badge-<?php echo esc_attr( $style['key'] ); ?>" style="background:<?php echo esc_attr( $style['background'] ); ?>;" data-modal="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog">
                        <span class="claira-tkd-schema-circle" style="background:<?php echo esc_attr( $style['circle'] ); ?>;"></span>
                        <span class="claira-tkd-schema-grade<?php echo preg_match( '/^\d+e$/', $keup ) ? '' : ' is-long'; ?>" style="color:<?php echo esc_attr( $style['grade_color'] ); ?>;">
                            <?php echo esc_html( $keup ); ?><?php if ( $stars ) : ?><span class="claira-tkd-schema-star"> <?php echo esc_html( $stars ); ?></span><?php endif; ?>
                        </span>
                        <span class="claira-tkd-schema-name" style="color:<?php echo esc_attr( $style['color'] ); ?>;"><?php echo esc_html( $name ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        $columns_html .= ob_get_clean();
    }

    if ( '' === $columns_html ) {
        return '<p>' . esc_html__( 'Aucun grade disponible pour le schéma.', 'claira-tkd-parcours' ) . '</p>';
    }

    ob_start();
    ?>
    <div class="claira-tkd-schema">
        <?php if ( '' !== $atts['bandeau'] ) : ?>
            <div class="claira-tkd-schema-banner"><?php echo esc_html( $atts['bandeau'] ); ?></div>
        <?php endif; ?>
        <?php if ( '' !== $atts['aide'] ) : ?>
            <p class="claira-tkd-schema-help"><?php echo esc_html( $atts['aide'] ); ?></p>
        <?php endif; ?>
        <div class="claira-tkd-schema-cols"><?php echo $columns_html; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé ci-dessus. ?></div>
        <?php if ( '' !== $atts['note'] ) : ?>
            <div class="claira-tkd-schema-footer"><?php echo esc_html( $atts['note'] ); ?></div>
        <?php endif; ?>
    </div>
    <?php echo $modals_html; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans claira_tkd_render_grade_modal(). ?>
    <?php
    return ob_get_clean();
}
