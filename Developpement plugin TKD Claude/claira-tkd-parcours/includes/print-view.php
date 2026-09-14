<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * En-têtes affichés au-dessus du tableau, par tranche d'âge. Reproduit les
 * deux "cahiers de révision" HTML d'origine (technique_enfant.html /
 * technique_adoadulte.html), désormais générés en direct depuis la base au
 * lieu d'être des fichiers figés à maintenir à la main.
 */
function claira_tkd_get_print_view_headers() {
    return array(
        'Enfant' => array(
            'title'    => 'Programme de progression technique',
            'subtitle' => 'Grades & Ceintures • Taekwondo Claira • tkdclaira.fr',
        ),
        'Adolescent' => array(
            'title'    => 'Progression technique : Ado & Adulte',
            'subtitle' => 'Grades & Ceintures • À partir de 11 ans révolus • Taekwondo Claira',
        ),
        'Adulte' => array(
            'title'    => 'Progression technique : Ado & Adulte',
            'subtitle' => 'Grades & Ceintures • À partir de 11 ans révolus • Taekwondo Claira',
        ),
    );
}

/**
 * Couleur (unie ou dégradée) associée à un titre de grade pour la pastille de
 * ceinture de la vue tableau. Réutilise la même détection que l'affichage
 * public (claira_tkd_get_belt_color_class / claira_tkd_get_bicolor_class)
 * pour ne pas dupliquer la logique de reconnaissance des noms de ceinture —
 * seule la représentation (couleur exacte de cette vue) est propre à ce fichier.
 */
function claira_tkd_get_pill_colors( $title ) {
    $belt_class    = claira_tkd_get_belt_color_class( $title );
    $bicolor_class = claira_tkd_get_bicolor_class( $title );

    $solid = array(
        'claira-tkd-belt-white'   => array( '#ffffff', '#000000' ),
        'claira-tkd-belt-yellow'  => array( '#FFD700', '#000000' ),
        'claira-tkd-belt-orange'  => array( '#FF8C00', '#000000' ),
        'claira-tkd-belt-green'   => array( '#27ae60', '#ffffff' ),
        'claira-tkd-belt-purple'  => array( '#8e44ad', '#ffffff' ),
        'claira-tkd-belt-blue'    => array( '#2980b9', '#ffffff' ),
        'claira-tkd-belt-red'     => array( '#c0102a', '#ffffff' ),
        'claira-tkd-belt-black'   => array( '#111111', '#FFD700' ),
        'claira-tkd-belt-default' => array( '#64748b', '#ffffff' ),
    );

    $gradients = array(
        'claira-tkd-bicolor-white-yellow'  => 'linear-gradient(90deg, #ffffff 50%, #FFD700 50%)',
        'claira-tkd-bicolor-yellow-orange' => 'linear-gradient(90deg, #FFD700 50%, #FF8C00 50%)',
        'claira-tkd-bicolor-orange-green'  => 'linear-gradient(90deg, #FF8C00 50%, #27ae60 50%)',
        'claira-tkd-bicolor-red-black'     => 'linear-gradient(90deg, #c0102a 50%, #111111 50%)',
        'claira-tkd-bicolor-green-blue'    => 'linear-gradient(90deg, #27ae60 50%, #2980b9 50%)',
        'claira-tkd-bicolor-blue-red'      => 'linear-gradient(90deg, #2980b9 50%, #c0102a 50%)',
    );

    list( $background, $color ) = isset( $solid[ $belt_class ] ) ? $solid[ $belt_class ] : $solid['claira-tkd-belt-default'];

    if ( $bicolor_class && isset( $gradients[ $bicolor_class ] ) ) {
        $background = $gradients[ $bicolor_class ];
    }

    return array( 'background' => $background, 'color' => $color );
}

/**
 * Trie les grades selon l'ordre officiel de progression (référentiel
 * claira_tkd_get_keup_options_by_age()) plutôt que par titre ou par date de
 * création. Un rang keup inconnu du référentiel (ex. tranche Baby, ou grade
 * ajouté à la main avec un libellé libre) est placé à la fin, dans l'ordre où
 * il a été trouvé.
 */
function claira_tkd_sort_grades_by_keup( $grades, $age_name ) {
    $options = claira_tkd_get_keup_options_by_age();
    $order   = isset( $options[ $age_name ] ) ? array_flip( array_keys( $options[ $age_name ] ) ) : array();

    usort( $grades, function( $a, $b ) use ( $order ) {
        $keup_a = get_post_meta( $a->ID, '_claira_tkd_keup_rank', true );
        $keup_b = get_post_meta( $b->ID, '_claira_tkd_keup_rank', true );

        $index_a = isset( $order[ $keup_a ] ) ? $order[ $keup_a ] : PHP_INT_MAX;
        $index_b = isset( $order[ $keup_b ] ) ? $order[ $keup_b ] : PHP_INT_MAX;

        if ( $index_a === $index_b ) {
            return 0;
        }

        return $index_a < $index_b ? -1 : 1;
    } );

    return $grades;
}

/**
 * Convertit le texte libre d'un champ technique ("Coréen - Français", une
 * paire par ligne) en spans bilingues, à l'identique du rendu des anciens
 * fichiers HTML statiques. Une ligne qui ne suit pas cette convention (saisie
 * manuelle libre) est simplement affichée telle quelle.
 */
function claira_tkd_render_technique_lines( $text ) {
    $text = trim( (string) $text );
    if ( '' === $text ) {
        return '';
    }

    $lines  = preg_split( '/\r\n|\r|\n/', $text );
    $output = '';

    foreach ( $lines as $i => $line ) {
        $line = trim( $line );
        if ( '' === $line ) {
            continue;
        }

        if ( $i > 0 ) {
            $output .= '<div class="claira-tkd-print-spacer"></div>';
        }

        if ( false !== strpos( $line, ' - ' ) ) {
            list( $kor, $fr ) = explode( ' - ', $line, 2 );
            $output .= '<span class="claira-tkd-print-kor">' . esc_html( trim( $kor ) ) . '</span>';
            $output .= '<span class="claira-tkd-print-fr">' . esc_html( trim( $fr ) ) . '</span>';
        } else {
            $output .= '<span class="claira-tkd-print-fr">' . esc_html( $line ) . '</span>';
        }
    }

    return $output;
}

/**
 * [claira_tkd_parcours_tableau age="Enfant"] — "cahier de révision" imprimable :
 * un tableau complet (une ligne par grade) généré à la volée depuis la base,
 * à la place des fichiers technique_enfant.html / technique_adoadulte.html
 * maintenus à la main jusqu'ici. Toujours à jour, imprimable via le
 * navigateur (Ctrl+P → PDF), sans fichier à régénérer ni à synchroniser.
 */
function claira_tkd_progression_table_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'age' => 'Enfant',
    ), $atts, 'claira_tkd_parcours_tableau' );

    $age_name = trim( $atts['age'] );
    $term     = get_term_by( 'name', $age_name, 'tkd_age_group' );
    if ( ! $term || is_wp_error( $term ) ) {
        return '<p>' . esc_html__( 'Tranche d’âge inconnue pour le tableau de progression.', 'claira-tkd-parcours' ) . '</p>';
    }

    $query = new WP_Query( array(
        'post_type'      => 'tkd_grade',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'tkd_age_group',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ),
        ),
    ) );

    if ( ! $query->have_posts() ) {
        return '<p>' . esc_html__( 'Aucun grade disponible pour ce tableau.', 'claira-tkd-parcours' ) . '</p>';
    }

    $grades  = claira_tkd_sort_grades_by_keup( $query->posts, $age_name );
    $headers = claira_tkd_get_print_view_headers();
    $header  = isset( $headers[ $age_name ] ) ? $headers[ $age_name ] : array(
        'title'    => 'Programme de progression technique',
        'subtitle' => 'Grades & Ceintures • Taekwondo Claira',
    );

    ob_start();
    ?>
    <div class="claira-tkd-print-wrapper">
        <div class="claira-tkd-print-header">
            <h1><?php echo esc_html( $header['title'] ); ?></h1>
            <p><?php echo esc_html( $header['subtitle'] ); ?></p>
            <hr class="claira-tkd-print-divider">
        </div>

        <div class="claira-tkd-print-responsive">
            <table class="claira-tkd-print-table">
                <thead>
                    <tr>
                        <th class="col-center" style="width: 50px;"><?php esc_html_e( 'Grd', 'claira-tkd-parcours' ); ?></th>
                        <th class="col-center" style="width: 110px;"><?php esc_html_e( 'Ceinture', 'claira-tkd-parcours' ); ?></th>
                        <th class="col-left"><?php esc_html_e( 'Techniques bras', 'claira-tkd-parcours' ); ?></th>
                        <th class="col-left"><?php esc_html_e( 'Techniques jambes', 'claira-tkd-parcours' ); ?></th>
                        <th class="col-left"><?php esc_html_e( 'Poomsae', 'claira-tkd-parcours' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $grades as $grade ) :
                        $keup_rank   = get_post_meta( $grade->ID, '_claira_tkd_keup_rank', true );
                        $tech_bras   = get_post_meta( $grade->ID, '_claira_tkd_tech_bras', true );
                        $tech_jambes = get_post_meta( $grade->ID, '_claira_tkd_tech_jambes', true );
                        $poomsae     = get_post_meta( $grade->ID, '_claira_tkd_poomsae', true );
                        $title       = claira_tkd_get_grade_display_label( get_the_title( $grade ) );
                        $pill        = claira_tkd_get_pill_colors( get_the_title( $grade ) );
                        // Un tech_jambes vide alors que tech_bras est rempli signale une description
                        // fusionnée (ex. grades de révision globale / Poom) : on affiche alors une
                        // seule cellule sur les deux colonnes, comme dans les fichiers d'origine.
                        $merged = ( '' === trim( (string) $tech_jambes ) && '' !== trim( (string) $tech_bras ) );
                        ?>
                        <tr>
                            <td class="col-grd"><?php echo esc_html( $keup_rank ); ?></td>
                            <td class="col-belt-cell">
                                <span class="claira-tkd-print-pill" style="background:<?php echo esc_attr( $pill['background'] ); ?>; color:<?php echo esc_attr( $pill['color'] ); ?>;">
                                    <?php echo esc_html( strtoupper( $title ) ); ?>
                                </span>
                            </td>
                            <?php if ( $merged ) : ?>
                                <td colspan="2"><?php echo wp_kses_post( claira_tkd_render_technique_lines( $tech_bras ) ); ?></td>
                            <?php else : ?>
                                <td><?php echo wp_kses_post( claira_tkd_render_technique_lines( $tech_bras ) ); ?></td>
                                <td><?php echo wp_kses_post( claira_tkd_render_technique_lines( $tech_jambes ) ); ?></td>
                            <?php endif; ?>
                            <td><?php echo esc_html( $poomsae ? $poomsae : '-' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
