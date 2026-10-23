<?php
/**
 * Vidéothèque (v1.11.0) : toutes les vidéos du club sur une seule page.
 *
 * Deux sources, fusionnées par vidéo (même lien YouTube/Vimeo = une seule vignette) :
 * - les liens vidéo des grades (méta `_claira_tkd_video_url`), repris
 *   automatiquement, thème « Poomsae » par défaut ;
 * - les fiches « vidéo » ajoutées dans l'administration (type `tkd_video`) :
 *   vidéos hors grade (techniques, combat, self-défense…) ou fiche qui
 *   personnalise une vidéo de grade (titre, thème, grades en plus, ordre, masquer).
 *
 * Front : [claira_tkd_videotheque] — page publique, vignettes groupées par
 * thème, filtres thème / grade / recherche. Le lecteur YouTube (version
 * youtube-nocookie) n'est chargé qu'au clic, dans une fenêtre.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Option : titres récupérés auprès de YouTube / Vimeo (clé vidéo => array titre, auteur, vignette, le). */
define( 'CLAIRA_TKD_VT_TITRES_OPTION', 'claira_tkd_vt_titres' );

function claira_tkd_vt_themes() {
    return array(
        'poomsae'    => __( 'Poomsae', 'claira-tkd-parcours' ),
        'techniques' => __( 'Techniques', 'claira-tkd-parcours' ),
        'kyorugi'    => __( 'Combat (Kyorugi)', 'claira-tkd-parcours' ),
        'hosinsul'   => __( 'Self-défense (Hosinsul)', 'claira-tkd-parcours' ),
        'casse'      => __( 'Casse', 'claira-tkd-parcours' ),
        'autres'     => __( 'Autres', 'claira-tkd-parcours' ),
    );
}

function claira_tkd_vt_register_post_type() {
    register_post_type( 'tkd_video', array(
        'labels'       => array(
            'name'          => __( 'Vidéos TKD', 'claira-tkd-parcours' ),
            'singular_name' => __( 'Vidéo TKD', 'claira-tkd-parcours' ),
        ),
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'supports'     => array( 'title' ),
        'rewrite'      => false,
    ) );
}

/**
 * Reconnaît un lien YouTube (watch, youtu.be, embed, shorts, live, nocookie) ou Vimeo.
 * Renvoie array( provider, id, key ) ou null.
 */
function claira_tkd_vt_parse_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return null;
    }
    if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $url, $m )
        || preg_match( '~youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})~', $url, $m ) ) {
        return array( 'provider' => 'youtube', 'id' => $m[1], 'key' => 'youtube:' . $m[1] );
    }
    if ( preg_match( '~(?:player\.)?vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
        return array( 'provider' => 'vimeo', 'id' => $m[1], 'key' => 'vimeo:' . $m[1] );
    }
    return null;
}

function claira_tkd_vt_watch_url( $video ) {
    return 'youtube' === $video['provider']
        ? 'https://www.youtube.com/watch?v=' . $video['id']
        : 'https://vimeo.com/' . $video['id'];
}

/** Lecteur chargé au clic : youtube-nocookie (pas de cookie YouTube avant lecture). */
function claira_tkd_vt_embed_url( $video ) {
    return 'youtube' === $video['provider']
        ? 'https://www.youtube-nocookie.com/embed/' . $video['id'] . '?autoplay=1&rel=0'
        : 'https://player.vimeo.com/video/' . $video['id'] . '?autoplay=1&dnt=1';
}

/**
 * « TAEGEUK 1JANG » (titre Kukkiwon) => « Taegeuk 1 Jang ». Un titre qui n'est
 * pas tout en majuscules est gardé tel quel.
 */
function claira_tkd_vt_embellir_titre( $titre ) {
    $titre = trim( (string) $titre );
    $titre = preg_replace( '/(\d)(jang)\b/i', '$1 $2', $titre );
    if ( function_exists( 'mb_strtoupper' ) && $titre === mb_strtoupper( $titre, 'UTF-8' ) ) {
        $titre = mb_convert_case( mb_strtolower( $titre, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
    }
    return $titre;
}

/**
 * Titre, auteur et vignette d'une vidéo, demandés une fois à YouTube / Vimeo
 * (oEmbed, sans clé d'API) puis gardés dans l'option CLAIRA_TKD_VT_TITRES_OPTION.
 * Un échec est retenté au plus une fois par jour.
 *
 * $autoriser_appel : false = cache seulement (aucune requête réseau).
 */
function claira_tkd_vt_infos( $video, $autoriser_appel = true ) {
    $cache = get_option( CLAIRA_TKD_VT_TITRES_OPTION, array() );
    if ( ! is_array( $cache ) ) {
        $cache = array();
    }
    $infos = isset( $cache[ $video['key'] ] ) && is_array( $cache[ $video['key'] ] ) ? $cache[ $video['key'] ] : null;

    $a_retenter = $infos && '' === $infos['titre'] && ( time() - (int) $infos['le'] ) > DAY_IN_SECONDS;
    if ( ( ! $infos || $a_retenter ) && $autoriser_appel ) {
        $endpoint = 'youtube' === $video['provider']
            ? 'https://www.youtube.com/oembed?format=json&url='
            : 'https://vimeo.com/api/oembed.json?url=';
        claira_tkd_vt_nb_appels( 1 );
        $reponse = wp_remote_get( $endpoint . rawurlencode( claira_tkd_vt_watch_url( $video ) ), array( 'timeout' => 4 ) );
        $data    = ( ! is_wp_error( $reponse ) && 200 === (int) wp_remote_retrieve_response_code( $reponse ) )
            ? json_decode( wp_remote_retrieve_body( $reponse ), true )
            : null;

        $infos = array(
            'titre'    => is_array( $data ) && ! empty( $data['title'] ) ? claira_tkd_vt_embellir_titre( sanitize_text_field( $data['title'] ) ) : '',
            'auteur'   => is_array( $data ) && ! empty( $data['author_name'] ) ? sanitize_text_field( $data['author_name'] ) : '',
            'vignette' => is_array( $data ) && ! empty( $data['thumbnail_url'] ) ? esc_url_raw( $data['thumbnail_url'] ) : '',
            'le'       => time(),
        );
        $cache[ $video['key'] ] = $infos;
        update_option( CLAIRA_TKD_VT_TITRES_OPTION, $cache, false );
    }

    return $infos ? $infos : array( 'titre' => '', 'auteur' => '', 'vignette' => '', 'le' => 0 );
}

/** Compteur des requêtes envoyées à YouTube / Vimeo pendant cette page. */
function claira_tkd_vt_nb_appels( $ajout = 0 ) {
    static $nb = 0;
    $nb += $ajout;
    return $nb;
}

function claira_tkd_vt_vignette( $video, $infos ) {
    if ( 'youtube' === $video['provider'] ) {
        return 'https://i.ytimg.com/vi/' . $video['id'] . '/hqdefault.jpg';
    }
    return ! empty( $infos['vignette'] ) ? $infos['vignette'] : '';
}

/** « Kukkiwon World Taekwondo Headquarters » => « Kukkiwon » ; autres auteurs inchangés. */
function claira_tkd_vt_auteur_court( $auteur ) {
    return false !== stripos( (string) $auteur, 'kukkiwon' ) ? 'Kukkiwon' : (string) $auteur;
}

/**
 * Place d'un grade dans la progression : 19e keup => 81 … 1er keup => 99,
 * Il/Yi/Sam Poom => 101/102/103, Ne Dan => 100 + N. Sert à ranger les vidéos
 * dans l'ordre où on les apprend.
 */
function claira_tkd_vt_rang_progression( $keup_rank ) {
    $keup_rank = strtolower( trim( (string) $keup_rank ) );
    $poom      = array( 'il' => 1, 'yi' => 2, 'sam' => 3, 'sa' => 4 );
    if ( preg_match( '/^(il|yi|sam|sa)\s+poom/', $keup_rank, $m ) ) {
        return 100 + $poom[ $m[1] ];
    }
    if ( preg_match( '/(\d+)\s*(?:er|e|ème)?\s*dan/', $keup_rank, $m ) ) {
        return 100 + (int) $m[1];
    }
    if ( preg_match( '/(\d+)/', $keup_rank, $m ) ) {
        return 100 - (int) $m[1];
    }
    return 999;
}

/**
 * Grades publiés, rangés par progression puis tranche d'âge, avec ce qu'il faut
 * pour les afficher : array( id => array( id, label, age, rang, classes ) ).
 */
function claira_tkd_vt_grades() {
    static $grades = null;
    if ( null !== $grades ) {
        return $grades;
    }
    $ordre_age = array( 'Baby' => 1, 'Enfant' => 2, 'Adolescent' => 3, 'Adulte' => 4 );
    $grades    = array();
    $posts     = get_posts( array(
        'post_type'      => 'tkd_grade',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ) );
    foreach ( $posts as $post ) {
        $titre  = get_the_title( $post );
        $terms  = get_the_terms( $post->ID, 'tkd_age_group' );
        $age    = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
        list( $nom, $etoiles ) = claira_tkd_split_grade_stars( $titre );
        $grades[ $post->ID ] = array(
            'id'      => $post->ID,
            'label'   => trim( $nom . ' ' . $etoiles ),
            'age'     => $age,
            'rang'    => claira_tkd_vt_rang_progression( get_post_meta( $post->ID, '_claira_tkd_keup_rank', true ) ),
            'ordre_age' => isset( $ordre_age[ $age ] ) ? $ordre_age[ $age ] : 9,
            'classes' => trim( claira_tkd_get_belt_color_class( $titre ) . ' ' . claira_tkd_get_bicolor_class( $titre ) ),
        );
    }
    uasort( $grades, function ( $a, $b ) {
        if ( $a['ordre_age'] !== $b['ordre_age'] ) {
            return $a['ordre_age'] - $b['ordre_age'];
        }
        if ( $a['rang'] !== $b['rang'] ) {
            return $a['rang'] - $b['rang'];
        }
        return strcmp( $a['label'], $b['label'] );
    } );
    return $grades;
}

/**
 * Toutes les vidéos de la vidéothèque, fusionnées par lien.
 *
 * Chaque élément : key, provider, id, titre, auteur, vignette, theme,
 * description, grades (ids, ordre de progression), grades_auto (ids venant des
 * fiches de grade), post_id (fiche de la vidéothèque, 0 si aucune), ordre,
 * masquee, rang.
 *
 * $appels_max : nombre maximum de titres à demander à YouTube / Vimeo pendant
 * cet appel (les autres gardent un titre de secours jusqu'au prochain affichage).
 */
function claira_tkd_vt_collecter( $appels_max = 3 ) {
    $grades = claira_tkd_vt_grades();
    $videos = array();

    // 1. Liens vidéo des fiches de grade.
    foreach ( $grades as $grade_id => $grade ) {
        $video = claira_tkd_vt_parse_url( get_post_meta( $grade_id, '_claira_tkd_video_url', true ) );
        if ( ! $video ) {
            continue;
        }
        if ( ! isset( $videos[ $video['key'] ] ) ) {
            $videos[ $video['key'] ] = $video + array(
                'titre'       => '',
                'titre_saisi' => '',
                'secours'     => trim( (string) get_post_meta( $grade_id, '_claira_tkd_poomsae', true ) ),
                'theme'       => 'poomsae',
                'description' => '',
                'grades'      => array(),
                'grades_auto' => array(),
                'post_id'     => 0,
                'ordre'       => 0,
                'masquee'     => false,
            );
        }
        $videos[ $video['key'] ]['grades_auto'][] = $grade_id;
    }

    // 2. Fiches ajoutées dans la vidéothèque (vidéos hors grade ou personnalisation).
    $fiches = get_posts( array(
        'post_type'      => 'tkd_video',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ) );
    $themes = claira_tkd_vt_themes();
    foreach ( $fiches as $fiche ) {
        $video = claira_tkd_vt_parse_url( get_post_meta( $fiche->ID, '_claira_tkd_vt_url', true ) );
        if ( ! $video ) {
            continue;
        }
        if ( ! isset( $videos[ $video['key'] ] ) ) {
            $videos[ $video['key'] ] = $video + array(
                'titre'       => '',
                'secours'     => '',
                'grades_auto' => array(),
            );
        }
        $theme   = (string) get_post_meta( $fiche->ID, '_claira_tkd_vt_theme', true );
        $choisis = array_map( 'absint', (array) get_post_meta( $fiche->ID, '_claira_tkd_vt_grades', true ) );
        $videos[ $video['key'] ] = array_merge( $videos[ $video['key'] ], array(
            'titre_saisi' => $fiche->post_title,
            'theme'       => isset( $themes[ $theme ] ) ? $theme : 'autres',
            'description' => (string) get_post_meta( $fiche->ID, '_claira_tkd_vt_description', true ),
            'grades'      => array_filter( $choisis ),
            'post_id'     => $fiche->ID,
            'ordre'       => (int) $fiche->menu_order,
            'masquee'     => '1' === get_post_meta( $fiche->ID, '_claira_tkd_vt_masquee', true ),
        ) );
    }

    // 3. Titres, grades fusionnés et rang dans la progression.
    $appels = 0;
    foreach ( $videos as $key => $v ) {
        $tous = array_unique( array_merge( isset( $v['grades'] ) ? $v['grades'] : array(), $v['grades_auto'] ) );
        $tous = array_values( array_filter( array_keys( $grades ), function ( $id ) use ( $tous ) {
            return in_array( $id, $tous, true );
        } ) );

        $avant = claira_tkd_vt_nb_appels();
        $infos = claira_tkd_vt_infos( $v, $appels < $appels_max );
        $appels += claira_tkd_vt_nb_appels() - $avant;

        $titre = trim( (string) ( isset( $v['titre_saisi'] ) ? $v['titre_saisi'] : '' ) );
        if ( '' === $titre ) {
            $titre = $infos['titre'];
        }
        if ( '' === $titre ) {
            $titre = '' !== $v['secours'] ? $v['secours'] : __( 'Vidéo', 'claira-tkd-parcours' );
        }

        $rang = 999;
        foreach ( $tous as $id ) {
            $rang = min( $rang, $grades[ $id ]['rang'] );
        }

        $videos[ $key ] = array_merge( $v, array(
            'titre'    => $titre,
            'auteur'   => claira_tkd_vt_auteur_court( $infos['auteur'] ),
            'vignette' => claira_tkd_vt_vignette( $v, $infos ),
            'grades'   => $tous,
            'rang'     => $rang,
        ) );
    }

    // Ordre : ordre saisi (1, 2, 3…) d'abord, puis progression, puis titre.
    uasort( $videos, function ( $a, $b ) {
        $oa = $a['ordre'] > 0 ? $a['ordre'] : PHP_INT_MAX;
        $ob = $b['ordre'] > 0 ? $b['ordre'] : PHP_INT_MAX;
        if ( $oa !== $ob ) {
            return $oa < $ob ? -1 : 1;
        }
        if ( $a['rang'] !== $b['rang'] ) {
            return $a['rang'] - $b['rang'];
        }
        return strnatcasecmp( $a['titre'], $b['titre'] );
    } );

    return $videos;
}

/** « Jaune ★ · Enfant » pour un grade. */
function claira_tkd_vt_grade_label( $grade, $avec_age = true ) {
    $age = 'Adolescent' === $grade['age'] ? __( 'Ado', 'claira-tkd-parcours' ) : $grade['age'];
    return $avec_age && '' !== $age ? $grade['label'] . ' · ' . $age : $grade['label'];
}

/* ==========================================================================
   Front : [claira_tkd_videotheque]
   ========================================================================== */

/**
 * Attributs (facultatifs) :
 * - titre      : titre de la page (défaut « Vidéothèque ») ;
 * - sous_titre : ligne sous le titre ;
 * - theme      : n'afficher qu'un thème (poomsae, techniques, kyorugi, hosinsul, casse, autres).
 */
function claira_tkd_videotheque_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'titre'      => __( 'Vidéothèque', 'claira-tkd-parcours' ),
        'sous_titre' => __( 'Poomsae, techniques, combat : toutes les vidéos du club', 'claira-tkd-parcours' ),
        'theme'      => '',
    ), $atts, 'claira_tkd_videotheque' );

    $themes = claira_tkd_vt_themes();
    $grades = claira_tkd_vt_grades();
    $seul   = sanitize_key( $atts['theme'] );

    $par_theme = array();
    foreach ( claira_tkd_vt_collecter() as $v ) {
        if ( $v['masquee'] || ( $seul && $seul !== $v['theme'] ) ) {
            continue;
        }
        $par_theme[ $v['theme'] ][] = $v;
    }
    // Ordre des thèmes = ordre de la liste.
    $par_theme = array_filter( array_replace( array_fill_keys( array_keys( $themes ), array() ), $par_theme ) );

    // Grades proposés dans le filtre : ceux qui ont au moins une vidéo.
    $grades_filtre = array();
    foreach ( $par_theme as $liste ) {
        foreach ( $liste as $v ) {
            foreach ( $v['grades'] as $id ) {
                $grades_filtre[ $id ] = true;
            }
        }
    }

    ob_start();
    ?>
    <div class="claira-tkd-vt" data-vt>
        <header class="claira-tkd-vt-head">
            <h2><?php echo esc_html( $atts['titre'] ); ?></h2>
            <?php if ( '' !== $atts['sous_titre'] ) : ?>
                <p><?php echo esc_html( $atts['sous_titre'] ); ?></p>
            <?php endif; ?>
        </header>

        <?php if ( ! $par_theme ) : ?>
            <p class="claira-tkd-vt-vide"><?php esc_html_e( 'La vidéothèque sera bientôt disponible.', 'claira-tkd-parcours' ); ?></p>
        <?php else : ?>
            <div class="claira-tkd-vt-filtres">
                <?php if ( count( $par_theme ) > 1 ) : ?>
                    <div class="claira-tkd-vt-themes" role="group" aria-label="<?php esc_attr_e( 'Thèmes', 'claira-tkd-parcours' ); ?>">
                        <button type="button" class="claira-tkd-vt-chip" data-vt-theme="" aria-pressed="true"><?php esc_html_e( 'Tout', 'claira-tkd-parcours' ); ?></button>
                        <?php foreach ( $par_theme as $theme => $liste ) : ?>
                            <button type="button" class="claira-tkd-vt-chip" data-vt-theme="<?php echo esc_attr( $theme ); ?>" aria-pressed="false"><?php echo esc_html( $themes[ $theme ] ); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="claira-tkd-vt-champs">
                    <?php if ( $grades_filtre ) : ?>
                        <label class="claira-tkd-vt-sr" for="claira-tkd-vt-grade"><?php esc_html_e( 'Grade', 'claira-tkd-parcours' ); ?></label>
                        <select id="claira-tkd-vt-grade" data-vt-grade>
                            <option value=""><?php esc_html_e( 'Tous les grades', 'claira-tkd-parcours' ); ?></option>
                            <?php
                            $age_courant = null;
                            foreach ( $grades as $id => $grade ) :
                                if ( empty( $grades_filtre[ $id ] ) ) {
                                    continue;
                                }
                                if ( $grade['age'] !== $age_courant ) {
                                    if ( null !== $age_courant ) {
                                        echo '</optgroup>';
                                    }
                                    $age_courant = $grade['age'];
                                    echo '<optgroup label="' . esc_attr( '' !== $age_courant ? $age_courant : __( 'Autres', 'claira-tkd-parcours' ) ) . '">';
                                }
                                ?>
                                <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $grade['label'] ); ?></option>
                            <?php endforeach; ?>
                            <?php if ( null !== $age_courant ) { echo '</optgroup>'; } ?>
                        </select>
                    <?php endif; ?>
                    <label class="claira-tkd-vt-sr" for="claira-tkd-vt-recherche"><?php esc_html_e( 'Rechercher', 'claira-tkd-parcours' ); ?></label>
                    <input type="search" id="claira-tkd-vt-recherche" data-vt-search placeholder="<?php esc_attr_e( 'Rechercher une vidéo…', 'claira-tkd-parcours' ); ?>" />
                </div>
            </div>

            <?php foreach ( $par_theme as $theme => $liste ) : ?>
                <section class="claira-tkd-vt-section" data-vt-section="<?php echo esc_attr( $theme ); ?>">
                    <h3><?php echo esc_html( $themes[ $theme ] ); ?></h3>
                    <div class="claira-tkd-vt-grid">
                        <?php foreach ( $liste as $v ) :
                            $labels = array();
                            foreach ( $v['grades'] as $id ) {
                                $labels[] = claira_tkd_vt_grade_label( $grades[ $id ] );
                            }
                            $recherche = implode( ' ', array( $v['titre'], $v['description'], $v['auteur'], $themes[ $theme ], implode( ' ', $labels ) ) );
                            $recherche = function_exists( 'mb_strtolower' ) ? mb_strtolower( $recherche, 'UTF-8' ) : strtolower( $recherche );
                            ?>
                            <article class="claira-tkd-vt-card"
                                     data-vt-card
                                     data-theme="<?php echo esc_attr( $theme ); ?>"
                                     data-grades="<?php echo esc_attr( implode( ' ', $v['grades'] ) ); ?>"
                                     data-search="<?php echo esc_attr( remove_accents( $recherche ) ); ?>">
                                <button type="button" class="claira-tkd-vt-play"
                                        data-vt-embed="<?php echo esc_url( claira_tkd_vt_embed_url( $v ) ); ?>"
                                        data-vt-titre="<?php echo esc_attr( $v['titre'] ); ?>"
                                        data-vt-tags="<?php echo esc_attr( implode( ' · ', array_filter( array( $themes[ $theme ], $v['auteur'] ) ) ) ); ?>"
                                        data-vt-desc="<?php echo esc_attr( $v['description'] ); ?>"
                                        data-vt-lien="<?php echo esc_url( claira_tkd_vt_watch_url( $v ) ); ?>">
                                    <span class="claira-tkd-vt-thumb">
                                        <?php if ( $v['vignette'] ) : ?>
                                            <img src="<?php echo esc_url( $v['vignette'] ); ?>" alt="" loading="lazy" width="480" height="360" />
                                        <?php endif; ?>
                                        <span class="claira-tkd-vt-icon" aria-hidden="true"></span>
                                    </span>
                                    <span class="claira-tkd-vt-titre"><?php echo esc_html( $v['titre'] ); ?></span>
                                    <?php if ( $v['auteur'] ) : ?>
                                        <span class="claira-tkd-vt-auteur"><?php echo esc_html( $v['auteur'] ); ?></span>
                                    <?php endif; ?>
                                </button>
                                <?php if ( $v['grades'] ) : ?>
                                    <ul class="claira-tkd-vt-grades" aria-label="<?php esc_attr_e( 'Au programme des grades', 'claira-tkd-parcours' ); ?>">
                                        <?php
                                        // Grades groupés par tranche d'âge : « Enfant  ● Jaune ● Orange ».
                                        $age_courant = null;
                                        foreach ( $v['grades'] as $id ) :
                                            if ( $grades[ $id ]['age'] !== $age_courant ) :
                                                $age_courant = $grades[ $id ]['age'];
                                                if ( '' !== $age_courant ) : ?>
                                                    <li class="claira-tkd-vt-age"><?php echo esc_html( 'Adolescent' === $age_courant ? __( 'Ado', 'claira-tkd-parcours' ) : $age_courant ); ?></li>
                                                <?php endif;
                                            endif; ?>
                                            <li class="claira-tkd-vt-grade <?php echo esc_attr( $grades[ $id ]['classes'] ); ?>"><?php echo esc_html( $grades[ $id ]['label'] ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <p class="claira-tkd-vt-vide" data-vt-aucune hidden><?php esc_html_e( 'Aucune vidéo ne correspond à votre recherche.', 'claira-tkd-parcours' ); ?></p>

            <div class="claira-tkd-modal claira-tkd-modal--light claira-tkd-vt-modal" id="claira-tkd-vt-lecteur" aria-hidden="true" data-vt-lecteur>
                <div class="claira-tkd-modal-panel" role="dialog" aria-modal="true" aria-labelledby="claira-tkd-vt-lecteur-titre">
                    <button class="claira-tkd-modal-close" type="button" aria-label="<?php esc_attr_e( 'Fermer', 'claira-tkd-parcours' ); ?>">×</button>
                    <header class="claira-tkd-modal-header">
                        <h2 id="claira-tkd-vt-lecteur-titre"></h2>
                        <p class="claira-tkd-modal-tags" data-vt-lecteur-tags></p>
                    </header>
                    <div class="claira-tkd-vt-lecteur-corps">
                        <div class="claira-tkd-video-container" data-vt-lecteur-video></div>
                        <p class="claira-tkd-vt-lecteur-desc" data-vt-lecteur-desc hidden></p>
                        <p class="claira-tkd-vt-lecteur-lien"><a href="#" target="_blank" rel="noopener noreferrer" data-vt-lecteur-lien><?php esc_html_e( 'Ouvrir sur YouTube', 'claira-tkd-parcours' ); ?></a></p>
                    </div>
                </div>
                <div class="claira-tkd-modal-backdrop"></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* ==========================================================================
   Administration : TKD Parcours → Vidéothèque
   ========================================================================== */

function claira_tkd_vt_register_admin_menu() {
    $hook = add_submenu_page(
        'claira-tkd-parcours',
        __( 'Vidéothèque', 'claira-tkd-parcours' ),
        __( 'Vidéothèque', 'claira-tkd-parcours' ),
        'edit_posts',
        'claira-tkd-videotheque',
        'claira_tkd_vt_render_admin_page'
    );
    // Traitement avant l'envoi de l'en-tête admin (redirection possible).
    add_action( "load-{$hook}", 'claira_tkd_vt_process_admin_form' );
}

function claira_tkd_vt_admin_message( $set = null ) {
    static $message = '';
    if ( null !== $set ) {
        $message = $set;
    }
    return $message;
}

function claira_tkd_vt_admin_url( $args = array() ) {
    return add_query_arg( $args, admin_url( 'admin.php?page=claira-tkd-videotheque' ) );
}

function claira_tkd_vt_process_admin_form() {
    if ( ! isset( $_POST['claira_tkd_vt_action'] ) ) {
        return;
    }
    $erreur = claira_tkd_vt_handle_admin_form();
    if ( $erreur ) {
        claira_tkd_vt_admin_message( '<div class="notice notice-error"><p>' . esc_html( $erreur ) . '</p></div>' );
    }
}

/** Traite le formulaire ; redirige en cas de succès, renvoie le message d'erreur sinon. */
function claira_tkd_vt_handle_admin_form() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return __( "Vous n'avez pas les droits nécessaires.", 'claira-tkd-parcours' );
    }
    if ( ! isset( $_POST['claira_tkd_vt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claira_tkd_vt_nonce'] ) ), 'claira_tkd_vt_action' ) ) {
        return __( 'La validation de sécurité a échoué. Rechargez la page et recommencez.', 'claira-tkd-parcours' );
    }

    $action  = sanitize_key( wp_unslash( $_POST['claira_tkd_vt_action'] ) );
    $post_id = isset( $_POST['claira_tkd_vt_id'] ) ? absint( wp_unslash( $_POST['claira_tkd_vt_id'] ) ) : 0;
    if ( $post_id && 'tkd_video' !== get_post_type( $post_id ) ) {
        return __( 'Vidéo introuvable.', 'claira-tkd-parcours' );
    }

    if ( 'delete' === $action ) {
        if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
            return __( 'Impossible de supprimer cette vidéo.', 'claira-tkd-parcours' );
        }
        wp_delete_post( $post_id, true );
        wp_safe_redirect( claira_tkd_vt_admin_url( array( 'vt_msg' => 'deleted' ) ) );
        exit;
    }

    if ( 'save' !== $action ) {
        return __( 'Action inconnue.', 'claira-tkd-parcours' );
    }
    if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
        return __( 'Vous ne pouvez pas modifier cette vidéo.', 'claira-tkd-parcours' );
    }

    $url   = isset( $_POST['claira_tkd_vt_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['claira_tkd_vt_url'] ) ) ) : '';
    $video = claira_tkd_vt_parse_url( $url );
    if ( ! $video ) {
        return __( "Le lien n'est pas reconnu : collez le lien d'une vidéo YouTube (youtube.com/watch?v=…, youtu.be/…, shorts…) ou Vimeo.", 'claira-tkd-parcours' );
    }

    // Une seule fiche par vidéo.
    foreach ( get_posts( array( 'post_type' => 'tkd_video', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $autre ) {
        $autre_video = claira_tkd_vt_parse_url( get_post_meta( $autre, '_claira_tkd_vt_url', true ) );
        if ( $autre !== $post_id && $autre_video && $autre_video['key'] === $video['key'] ) {
            return __( 'Cette vidéo est déjà dans la vidéothèque : modifiez la fiche existante (✏️ dans la liste).', 'claira-tkd-parcours' );
        }
    }

    $themes = claira_tkd_vt_themes();
    $theme  = isset( $_POST['claira_tkd_vt_theme'] ) ? sanitize_key( wp_unslash( $_POST['claira_tkd_vt_theme'] ) ) : 'autres';
    if ( ! isset( $themes[ $theme ] ) ) {
        $theme = 'autres';
    }
    $titre = isset( $_POST['claira_tkd_vt_titre'] ) ? sanitize_text_field( wp_unslash( $_POST['claira_tkd_vt_titre'] ) ) : '';
    if ( '' === $titre ) {
        // Titre repris de YouTube / Vimeo ; à défaut, un titre de secours.
        $infos = claira_tkd_vt_infos( $video );
        $titre = '' !== $infos['titre'] ? $infos['titre'] : __( 'Vidéo', 'claira-tkd-parcours' ) . ' ' . $video['id'];
    }
    $grades_valides = array_keys( claira_tkd_vt_grades() );
    $grades = isset( $_POST['claira_tkd_vt_grades'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['claira_tkd_vt_grades'] ) ) : array();
    $grades = array_values( array_intersect( $grades, $grades_valides ) );
    $description = isset( $_POST['claira_tkd_vt_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_vt_description'] ) ) : '';
    $ordre = isset( $_POST['claira_tkd_vt_ordre'] ) ? max( 0, (int) wp_unslash( $_POST['claira_tkd_vt_ordre'] ) ) : 0;

    $data = array(
        'post_type'   => 'tkd_video',
        'post_status' => 'publish',
        'post_title'  => $titre,
        'menu_order'  => $ordre,
    );
    if ( $post_id ) {
        $data['ID'] = $post_id;
    }
    $saved = wp_insert_post( $data, true );
    if ( is_wp_error( $saved ) ) {
        return __( "Une erreur est survenue lors de l'enregistrement.", 'claira-tkd-parcours' );
    }

    update_post_meta( $saved, '_claira_tkd_vt_url', claira_tkd_vt_watch_url( $video ) );
    update_post_meta( $saved, '_claira_tkd_vt_theme', $theme );
    update_post_meta( $saved, '_claira_tkd_vt_grades', $grades );
    update_post_meta( $saved, '_claira_tkd_vt_description', $description );
    update_post_meta( $saved, '_claira_tkd_vt_masquee', ! empty( $_POST['claira_tkd_vt_masquee'] ) ? '1' : '' );

    wp_safe_redirect( claira_tkd_vt_admin_url( array( 'vt_msg' => $post_id ? 'updated' : 'created' ) ) );
    exit;
}

function claira_tkd_vt_render_admin_page() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( "Vous n'avez pas les droits nécessaires pour accéder à cette page.", 'claira-tkd-parcours' ) );
    }

    $themes = claira_tkd_vt_themes();
    $grades = claira_tkd_vt_grades();
    // En administration, on complète tous les titres manquants (quelques secondes au plus la première fois).
    $videos = claira_tkd_vt_collecter( 50 );

    $messages = array(
        'created' => __( 'Vidéo ajoutée à la vidéothèque.', 'claira-tkd-parcours' ),
        'updated' => __( 'Vidéo mise à jour.', 'claira-tkd-parcours' ),
        'deleted' => __( 'Fiche supprimée.', 'claira-tkd-parcours' ),
    );
    $message = claira_tkd_vt_admin_message();
    $code    = isset( $_GET['vt_msg'] ) ? sanitize_key( wp_unslash( $_GET['vt_msg'] ) ) : '';
    if ( ! $message && isset( $messages[ $code ] ) ) {
        $message = '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
    }

    // Formulaire : modification d'une fiche, personnalisation d'une vidéo de grade, ou ajout.
    $edit_id = isset( $_GET['edit_video'] ) ? absint( wp_unslash( $_GET['edit_video'] ) ) : 0;
    $edit    = $edit_id ? get_post( $edit_id ) : null;
    if ( $edit && 'tkd_video' !== $edit->post_type ) {
        $edit = null;
    }
    $prefill_url = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';

    $form = array(
        'url'         => $edit ? get_post_meta( $edit->ID, '_claira_tkd_vt_url', true ) : $prefill_url,
        'titre'       => $edit ? $edit->post_title : '',
        'theme'       => $edit ? get_post_meta( $edit->ID, '_claira_tkd_vt_theme', true ) : ( $prefill_url ? 'poomsae' : '' ),
        'grades'      => $edit ? array_map( 'absint', (array) get_post_meta( $edit->ID, '_claira_tkd_vt_grades', true ) ) : array(),
        'description' => $edit ? get_post_meta( $edit->ID, '_claira_tkd_vt_description', true ) : '',
        'ordre'       => $edit ? (int) $edit->menu_order : 0,
        'masquee'     => $edit ? '1' === get_post_meta( $edit->ID, '_claira_tkd_vt_masquee', true ) : false,
    );
    // Si l'enregistrement a échoué, on réaffiche ce qui avait été saisi.
    if ( claira_tkd_vt_admin_message() && isset( $_POST['claira_tkd_vt_url'] ) ) {
        $form = array(
            'url'         => esc_url_raw( wp_unslash( $_POST['claira_tkd_vt_url'] ) ),
            'titre'       => isset( $_POST['claira_tkd_vt_titre'] ) ? sanitize_text_field( wp_unslash( $_POST['claira_tkd_vt_titre'] ) ) : '',
            'theme'       => isset( $_POST['claira_tkd_vt_theme'] ) ? sanitize_key( wp_unslash( $_POST['claira_tkd_vt_theme'] ) ) : '',
            'grades'      => isset( $_POST['claira_tkd_vt_grades'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['claira_tkd_vt_grades'] ) ) : array(),
            'description' => isset( $_POST['claira_tkd_vt_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_vt_description'] ) ) : '',
            'ordre'       => isset( $_POST['claira_tkd_vt_ordre'] ) ? max( 0, (int) wp_unslash( $_POST['claira_tkd_vt_ordre'] ) ) : 0,
            'masquee'     => ! empty( $_POST['claira_tkd_vt_masquee'] ),
        );
    }

    // Grades qui ont déjà ce lien dans leur fiche (rappel sous la liste des grades).
    $form_video  = claira_tkd_vt_parse_url( $form['url'] );
    $grades_auto = ( $form_video && isset( $videos[ $form_video['key'] ] ) ) ? $videos[ $form_video['key'] ]['grades_auto'] : array();

    $est_edition = $edit || ( $form_video && $prefill_url );
    ?>
    <div class="wrap sp-cal-wrap">
        <h1>🎬 <?php esc_html_e( 'Vidéothèque', 'claira-tkd-parcours' ); ?></h1>
        <?php echo wp_kses_post( $message ); ?>

        <p class="description" style="max-width:760px;">
            <?php esc_html_e( 'La vidéothèque reprend automatiquement le lien vidéo de chaque grade (thème Poomsae). Ajoutez ici les vidéos hors grade, ou cliquez sur « Personnaliser » pour changer le titre, le thème ou l’ordre d’une vidéo de grade, ou la masquer. Pour l’afficher sur le site, placez le code [claira_tkd_videotheque] dans une page.', 'claira-tkd-parcours' ); ?>
        </p>

        <div class="sp-box claira-tkd-admin-form-box">
            <h2>
                <span><?php echo esc_html( $edit ? __( 'Modifier une vidéo', 'claira-tkd-parcours' ) : ( $est_edition ? __( 'Personnaliser une vidéo de grade', 'claira-tkd-parcours' ) : __( 'Ajouter une vidéo', 'claira-tkd-parcours' ) ) ); ?></span>
                <?php if ( $est_edition ) : ?>
                    <a class="button button-small" href="<?php echo esc_url( claira_tkd_vt_admin_url() ); ?>">+ <?php esc_html_e( 'Ajouter une autre vidéo', 'claira-tkd-parcours' ); ?></a>
                <?php endif; ?>
            </h2>
            <form method="post" action="<?php echo esc_url( claira_tkd_vt_admin_url() ); ?>">
                <?php wp_nonce_field( 'claira_tkd_vt_action', 'claira_tkd_vt_nonce' ); ?>
                <input type="hidden" name="claira_tkd_vt_action" value="save" />
                <?php if ( $edit ) : ?>
                    <input type="hidden" name="claira_tkd_vt_id" value="<?php echo esc_attr( $edit->ID ); ?>" />
                <?php endif; ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="claira_tkd_vt_url"><?php esc_html_e( 'Lien YouTube ou Vimeo', 'claira-tkd-parcours' ); ?> *</label></th>
                        <td>
                            <input type="url" id="claira_tkd_vt_url" name="claira_tkd_vt_url" class="regular-text" value="<?php echo esc_attr( $form['url'] ); ?>" placeholder="https://www.youtube.com/watch?v=..." required />
                            <p class="description"><?php esc_html_e( 'Le lien de la page de la vidéo, ou son lien de partage (youtu.be/…).', 'claira-tkd-parcours' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="claira_tkd_vt_titre"><?php esc_html_e( 'Titre', 'claira-tkd-parcours' ); ?></label></th>
                        <td>
                            <input type="text" id="claira_tkd_vt_titre" name="claira_tkd_vt_titre" class="regular-text" value="<?php echo esc_attr( $form['titre'] ); ?>" />
                            <p class="description"><?php esc_html_e( 'Facultatif : laissé vide, le titre de la vidéo sur YouTube est repris.', 'claira-tkd-parcours' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="claira_tkd_vt_theme"><?php esc_html_e( 'Thème', 'claira-tkd-parcours' ); ?></label></th>
                        <td>
                            <select id="claira_tkd_vt_theme" name="claira_tkd_vt_theme">
                                <?php foreach ( $themes as $slug => $libelle ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $form['theme'] ? $form['theme'] : 'techniques', $slug ); ?>><?php echo esc_html( $libelle ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Grades concernés', 'claira-tkd-parcours' ); ?></th>
                        <td>
                            <div class="claira-tkd-vt-admin-grades">
                                <?php
                                $age_courant = null;
                                foreach ( $grades as $id => $grade ) :
                                    if ( $grade['age'] !== $age_courant ) {
                                        $age_courant = $grade['age'];
                                        echo '<strong>' . esc_html( '' !== $age_courant ? $age_courant : __( 'Sans tranche d’âge', 'claira-tkd-parcours' ) ) . '</strong>';
                                    }
                                    $auto = in_array( $id, $grades_auto, true );
                                    ?>
                                    <label>
                                        <input type="checkbox" name="claira_tkd_vt_grades[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $auto || in_array( $id, $form['grades'], true ) ); ?> <?php disabled( $auto ); ?> />
                                        <span class="claira-tkd-admin-pill <?php echo esc_attr( $grade['classes'] ); ?>"></span>
                                        <?php echo esc_html( $grade['label'] ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">
                                <?php esc_html_e( 'Facultatif. Les grades cochés et grisés ont déjà ce lien dans leur fiche (page Grades) : ils restent liés tant que leur fiche le garde.', 'claira-tkd-parcours' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="claira_tkd_vt_description"><?php esc_html_e( 'Description courte', 'claira-tkd-parcours' ); ?></label></th>
                        <td><textarea id="claira_tkd_vt_description" name="claira_tkd_vt_description" rows="2" class="large-text"><?php echo esc_textarea( $form['description'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="claira_tkd_vt_ordre"><?php esc_html_e( 'Ordre d’affichage', 'claira-tkd-parcours' ); ?></label></th>
                        <td>
                            <input type="number" min="0" id="claira_tkd_vt_ordre" name="claira_tkd_vt_ordre" value="<?php echo esc_attr( $form['ordre'] ); ?>" style="width:80px;" />
                            <p class="description"><?php esc_html_e( '0 = automatique (ordre de la progression des grades, puis titre). 1, 2, 3… = en tête de son thème, dans cet ordre.', 'claira-tkd-parcours' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Visibilité', 'claira-tkd-parcours' ); ?></th>
                        <td><label><input type="checkbox" name="claira_tkd_vt_masquee" value="1" <?php checked( $form['masquee'] ); ?> /> <?php esc_html_e( 'Masquer cette vidéo de la vidéothèque', 'claira-tkd-parcours' ); ?></label></td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary"><?php echo esc_html( $edit ? __( 'Mettre à jour la vidéo', 'claira-tkd-parcours' ) : __( 'Enregistrer la vidéo', 'claira-tkd-parcours' ) ); ?></button>
                    <?php if ( $est_edition ) : ?>
                        <a class="button" style="margin-left:8px;" href="<?php echo esc_url( claira_tkd_vt_admin_url() ); ?>"><?php esc_html_e( 'Annuler', 'claira-tkd-parcours' ); ?></a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <div class="sp-box">
            <h2><?php
                /* translators: %d: nombre de vidéos */
                echo esc_html( sprintf( __( 'Vidéos de la vidéothèque (%d)', 'claira-tkd-parcours' ), count( $videos ) ) );
            ?></h2>
            <table class="wp-list-table widefat striped claira-tkd-admin-table claira-tkd-vt-admin-table">
                <thead>
                    <tr>
                        <th style="width:96px;"></th>
                        <th><?php esc_html_e( 'Titre', 'claira-tkd-parcours' ); ?></th>
                        <th><?php esc_html_e( 'Thème', 'claira-tkd-parcours' ); ?></th>
                        <th><?php esc_html_e( 'Grades', 'claira-tkd-parcours' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'claira-tkd-parcours' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! $videos ) : ?>
                        <tr><td colspan="5" class="sp-muted"><?php esc_html_e( 'Aucune vidéo pour le moment : ajoutez un lien vidéo à un grade, ou une vidéo avec le formulaire ci-dessus.', 'claira-tkd-parcours' ); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ( $videos as $v ) :
                        $labels = array();
                        foreach ( $v['grades'] as $id ) {
                            $labels[] = claira_tkd_vt_grade_label( $grades[ $id ] );
                        }
                        ?>
                        <tr<?php echo $v['masquee'] ? ' style="opacity:.55;"' : ''; ?>>
                            <td>
                                <?php if ( $v['vignette'] ) : ?>
                                    <img src="<?php echo esc_url( $v['vignette'] ); ?>" alt="" width="88" height="66" style="object-fit:cover;border-radius:4px;display:block;" loading="lazy" />
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $v['titre'] ); ?></strong>
                                <?php if ( $v['masquee'] ) : ?> — <em><?php esc_html_e( 'masquée', 'claira-tkd-parcours' ); ?></em><?php endif; ?>
                                <br /><span class="sp-muted"><?php
                                    echo esc_html( $v['grades_auto']
                                        ? ( $v['post_id'] ? __( 'Lien d’un grade, personnalisé', 'claira-tkd-parcours' ) : __( 'Lien d’un grade (automatique)', 'claira-tkd-parcours' ) )
                                        : __( 'Ajoutée dans la vidéothèque', 'claira-tkd-parcours' ) );
                                    if ( $v['auteur'] ) {
                                        echo ' · ' . esc_html( $v['auteur'] );
                                    }
                                ?></span>
                            </td>
                            <td><?php echo esc_html( $themes[ $v['theme'] ] ); ?></td>
                            <td><?php echo esc_html( $labels ? implode( ', ', $labels ) : '—' ); ?></td>
                            <td>
                                <div class="claira-tkd-admin-actions">
                                    <a class="button button-small" href="<?php echo esc_url( claira_tkd_vt_watch_url( $v ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Voir la vidéo', 'claira-tkd-parcours' ); ?>">▶</a>
                                    <?php if ( $v['post_id'] ) : ?>
                                        <a class="button button-small" href="<?php echo esc_url( claira_tkd_vt_admin_url( array( 'edit_video' => $v['post_id'] ) ) ); ?>" title="<?php esc_attr_e( 'Modifier', 'claira-tkd-parcours' ); ?>">✏️</a>
                                        <form method="post" class="claira-tkd-admin-delete-form" action="<?php echo esc_url( claira_tkd_vt_admin_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( $v['grades_auto'] ? __( 'Supprimer la personnalisation ? La vidéo reste dans la vidéothèque grâce au lien du grade, avec son titre et son thème automatiques.', 'claira-tkd-parcours' ) : __( 'Retirer cette vidéo de la vidéothèque ?', 'claira-tkd-parcours' ) ); ?>');">
                                            <?php wp_nonce_field( 'claira_tkd_vt_action', 'claira_tkd_vt_nonce' ); ?>
                                            <input type="hidden" name="claira_tkd_vt_action" value="delete" />
                                            <input type="hidden" name="claira_tkd_vt_id" value="<?php echo esc_attr( $v['post_id'] ); ?>" />
                                            <button type="submit" class="button button-small sp-btn-del" title="<?php esc_attr_e( 'Supprimer', 'claira-tkd-parcours' ); ?>">🗑️</button>
                                        </form>
                                    <?php else : ?>
                                        <a class="button button-small" href="<?php echo esc_url( claira_tkd_vt_admin_url( array( 'url' => rawurlencode( claira_tkd_vt_watch_url( $v ) ) ) ) ); ?>"><?php esc_html_e( 'Personnaliser', 'claira-tkd-parcours' ); ?></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
