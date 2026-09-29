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

    // Données de chaque ligne, calculées une fois pour le tableau (écran large
    // et impression) et pour les cartes (téléphone).
    $rows        = array();
    $modals_html = '';
    foreach ( $grades as $grade ) {
        $tech_bras   = get_post_meta( $grade->ID, '_claira_tkd_tech_bras', true );
        $tech_jambes = get_post_meta( $grade->ID, '_claira_tkd_tech_jambes', true );
        $downloads   = get_post_meta( $grade->ID, '_claira_tkd_download_urls', true );
        $has_video   = get_post_meta( $grade->ID, '_claira_tkd_video_url', true ) || get_post_meta( $grade->ID, '_claira_tkd_video_file_id', true );
        $has_files   = is_array( $downloads ) && array_filter( $downloads );
        list( $belt_name, $stars ) = claira_tkd_split_grade_stars( get_the_title( $grade ) );

        $row = array(
            'keup'        => get_post_meta( $grade->ID, '_claira_tkd_keup_rank', true ),
            'tech_bras'   => $tech_bras,
            'tech_jambes' => $tech_jambes,
            'poomsae'     => get_post_meta( $grade->ID, '_claira_tkd_poomsae', true ),
            'title'       => trim( $belt_name . ' ' . $stars ),
            'pill'        => claira_tkd_get_pill_colors( get_the_title( $grade ) ),
            // Un tech_jambes vide alors que tech_bras est rempli signale une description
            // fusionnée (ex. grades de révision globale / Poom) : on affiche alors une
            // seule cellule sur les deux colonnes, comme dans les fichiers d'origine.
            'merged'      => ( '' === trim( (string) $tech_jambes ) && '' !== trim( (string) $tech_bras ) ),
            'modal_id'    => '',
            'media_label' => '',
        );

        // Bouton vers la fiche du grade uniquement s'il y a une vidéo ou un
        // fichier à consulter : le texte, lui, est déjà dans le cahier.
        if ( $has_video || $has_files ) {
            $row['modal_id']    = 'claira-tkd-tableau-grade-' . $grade->ID;
            $row['media_label'] = $has_video ? __( '▶ Vidéo', 'claira-tkd-parcours' ) : __( 'Ressources', 'claira-tkd-parcours' );
            $modals_html       .= claira_tkd_render_grade_modal( $grade, $row['modal_id'], array(
                'title' => $row['title'],
                'tags'  => array( $row['keup'], $age_name ),
                'class' => 'claira-tkd-modal--light',
            ) );
        }

        $rows[] = $row;
    }

    ob_start();
    ?>
    <div class="claira-tkd-print-wrapper">
        <div class="claira-tkd-print-toolbar">
            <button type="button" class="claira-tkd-print-btn" onclick="window.clairaTkdPrint(this)">
                <?php esc_html_e( '🖨️ Imprimer / PDF (une feuille A3)', 'claira-tkd-parcours' ); ?>
            </button>
        </div>
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
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td class="col-grd"><?php echo esc_html( $row['keup'] ); ?></td>
                            <td class="col-belt-cell">
                                <span class="claira-tkd-print-pill" style="background:<?php echo esc_attr( $row['pill']['background'] ); ?>; color:<?php echo esc_attr( $row['pill']['color'] ); ?>;">
                                    <?php echo esc_html( strtoupper( $row['title'] ) ); ?>
                                </span>
                                <?php if ( $row['modal_id'] ) : ?>
                                    <button type="button" class="claira-tkd-print-media-btn" data-modal="<?php echo esc_attr( $row['modal_id'] ); ?>" aria-haspopup="dialog"><?php echo esc_html( $row['media_label'] ); ?></button>
                                <?php endif; ?>
                            </td>
                            <?php if ( $row['merged'] ) : ?>
                                <td colspan="2"><?php echo wp_kses_post( claira_tkd_render_technique_lines( $row['tech_bras'] ) ); ?></td>
                            <?php else : ?>
                                <td><?php echo wp_kses_post( claira_tkd_render_technique_lines( $row['tech_bras'] ) ); ?></td>
                                <td><?php echo wp_kses_post( claira_tkd_render_technique_lines( $row['tech_jambes'] ) ); ?></td>
                            <?php endif; ?>
                            <td><?php echo esc_html( $row['poomsae'] ? $row['poomsae'] : '-' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php // Version téléphone : une carte par grade (affichée à la place du tableau sous 900 px). ?>
        <div class="claira-tkd-print-cards">
            <?php foreach ( $rows as $row ) : ?>
                <div class="claira-tkd-print-card">
                    <div class="claira-tkd-print-card-head">
                        <span class="claira-tkd-print-card-grd"><?php echo esc_html( $row['keup'] ); ?></span>
                        <span class="claira-tkd-print-pill" style="background:<?php echo esc_attr( $row['pill']['background'] ); ?>; color:<?php echo esc_attr( $row['pill']['color'] ); ?>;">
                            <?php echo esc_html( strtoupper( $row['title'] ) ); ?>
                        </span>
                        <?php if ( $row['modal_id'] ) : ?>
                            <button type="button" class="claira-tkd-print-media-btn" data-modal="<?php echo esc_attr( $row['modal_id'] ); ?>" aria-haspopup="dialog"><?php echo esc_html( $row['media_label'] ); ?></button>
                        <?php endif; ?>
                    </div>
                    <?php if ( '' === trim( (string) $row['tech_bras'] ) && '' === trim( (string) $row['tech_jambes'] ) && ! $row['poomsae'] ) : ?>
                        <p class="claira-tkd-print-card-empty"><?php esc_html_e( 'Programme à venir.', 'claira-tkd-parcours' ); ?></p>
                    <?php else : ?>
                        <?php if ( $row['tech_bras'] ) : ?>
                            <div class="claira-tkd-print-card-section">
                                <div class="claira-tkd-print-card-label"><?php echo esc_html( $row['merged'] ? __( 'Programme technique', 'claira-tkd-parcours' ) : __( 'Techniques bras', 'claira-tkd-parcours' ) ); ?></div>
                                <?php echo wp_kses_post( claira_tkd_render_technique_lines( $row['tech_bras'] ) ); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ( $row['tech_jambes'] ) : ?>
                            <div class="claira-tkd-print-card-section">
                                <div class="claira-tkd-print-card-label"><?php esc_html_e( 'Techniques jambes', 'claira-tkd-parcours' ); ?></div>
                                <?php echo wp_kses_post( claira_tkd_render_technique_lines( $row['tech_jambes'] ) ); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ( $row['poomsae'] ) : ?>
                            <div class="claira-tkd-print-card-section">
                                <div class="claira-tkd-print-card-label"><?php esc_html_e( 'Poomsae', 'claira-tkd-parcours' ); ?></div>
                                <span class="claira-tkd-print-fr"><?php echo esc_html( $row['poomsae'] ); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php echo $modals_html; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans claira_tkd_render_grade_modal(). ?>
    <?php
    // Un seul script par page, même si plusieurs tableaux sont affichés.
    static $print_script_done = false;
    if ( ! $print_script_done ) {
        $print_script_done = true;
        ?>
        <script>
        (function () {
            // Plusieurs tableaux peuvent coexister sur la page (Enfant, Ado/Adulte) : le bouton
            // cliqué désigne celui à imprimer ; sans bouton (Ctrl+P), on prend le premier.
            var wrap = null;
            window.clairaTkdPrint = function (btn) {
                wrap = btn.closest ? btn.closest(".claira-tkd-print-wrapper") : null;
                window.print();
            };
            // A3 paysage, marges 8 mm, en pixels CSS (96 dpi)
            var PAGE_W = (420 - 16) / 25.4 * 96, PAGE_H = (297 - 16) / 25.4 * 96;
            var pageStyle = null, saved = [];

            function beforePrint() {
                if (!wrap) wrap = document.querySelector(".claira-tkd-print-wrapper");
                if (!wrap) return;
                pageStyle = document.createElement('style');
                pageStyle.textContent = '@page { size: A3 landscape; margin: 8mm; }';
                document.head.appendChild(pageStyle);
                wrap.classList.add('is-printing');

                // Isole le tableau : masque le reste de la page (en-tête/pied du thème...) et
                // neutralise marges, paddings et largeurs max des conteneurs du thème qui le
                // décaleraient ou le rétréciraient sur la feuille.
                for (var n = wrap; n && n !== document.body; n = n.parentNode) {
                    if (n !== wrap) {
                        saved.push([n, n.style.cssText]);
                        var reset = { 'margin': '0', 'padding': '0', 'max-width': 'none', 'min-width': '0', 'width': 'auto', 'float': 'none', 'left': '0', 'top': '0', 'transform': 'none' };
                        Object.keys(reset).forEach(function (p) { n.style.setProperty(p, reset[p], 'important'); });
                    }
                    Array.prototype.forEach.call(n.parentNode.children, function (s) {
                        if (s !== n && s.tagName !== 'SCRIPT' && s.tagName !== 'STYLE' && s.style.display !== 'none') {
                            saved.push([s, s.style.cssText]);
                            s.style.setProperty('display', 'none', 'important');
                        }
                    });
                }

                // Ajuste le facteur d'échelle (zoom) pour remplir la feuille sans la dépasser :
                // recherche par dichotomie, la hauteur du tableau dépendant de sa largeur
                // (largeur de mise en page = largeur de feuille / zoom).
                var lo = 0.4, hi = 2.5, target = PAGE_H * 0.97;
                for (var i = 0; i < 9; i++) {
                    var mid = (lo + hi) / 2;
                    wrap.style.zoom = '1';
                    wrap.style.width = (PAGE_W / mid) + 'px';
                    if (wrap.offsetHeight * mid <= target) lo = mid; else hi = mid;
                }
                wrap.style.width = (PAGE_W / lo) + 'px';
                wrap.style.zoom = String(lo);
            }

            function afterPrint() {
                if (!wrap) return;
                if (pageStyle && pageStyle.parentNode) pageStyle.parentNode.removeChild(pageStyle);
                pageStyle = null;
                for (var i = saved.length - 1; i >= 0; i--) saved[i][0].style.cssText = saved[i][1];
                saved = [];
                wrap.classList.remove('is-printing');
                wrap.style.zoom = '';
                wrap.style.width = '';
                wrap = null;
            }

            window.addEventListener('beforeprint', beforePrint);
            window.addEventListener('afterprint', afterPrint);
        })();
        </script>
        <?php
    }
    return ob_get_clean();
}
