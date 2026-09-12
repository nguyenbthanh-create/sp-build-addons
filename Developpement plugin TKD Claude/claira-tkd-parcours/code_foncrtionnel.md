# Code Fonctionnel - Claira TKD Parcours

Ce document contient l'intégralité du code source validé et fonctionnel du plugin pour servir de sauvegarde et de référence.

---

## 1. `claira-tkd-parcours.php` (Fichier principal)
```php
<?php
/**
 * Plugin Name: Claira TKD Parcours
 * Plugin URI:  https://example.com/
 * Description: Interface ludique de suivi des grades TKD avec contenu texte, ressources téléchargeables et vidéos URL.
 * Version:     1.6.0
 * Author:      Claira
 * Text Domain: claira-tkd-parcours
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CLAIRA_TKD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLAIRA_TKD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLAIRA_TKD_PLUGIN_VERSION', '1.6.0' );

require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/post-types.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/meta-boxes.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/shortcodes.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/admin-frontend.php';
require_once CLAIRA_TKD_PLUGIN_DIR . 'includes/enqueue.php';

add_action( 'init', 'claira_tkd_register_post_types' );
add_action( 'init', 'claira_tkd_register_taxonomies' );
add_action( 'add_meta_boxes', 'claira_tkd_register_meta_boxes' );
add_action( 'save_post', 'claira_tkd_save_grade_meta', 10, 2 );
add_action( 'wp_enqueue_scripts', 'claira_tkd_enqueue_assets' );
add_action( 'admin_enqueue_scripts', 'claira_tkd_admin_assets' );

add_shortcode( 'claira_tkd_parcours', 'claira_tkd_parcours_shortcode' );
add_shortcode( 'claira_tkd_admin', 'claira_tkd_admin_frontend_shortcode' );

add_action( 'plugins_loaded', 'claira_tkd_load_textdomain' );

function claira_tkd_load_textdomain() {
    load_plugin_textdomain( 'claira-tkd-parcours', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
```

---

## 2. `includes/post-types.php`
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function claira_tkd_register_post_types() {
    $labels = array(
        'name'               => __( 'Grades TKD', 'claira-tkd-parcours' ),
        'singular_name'      => __( 'Grade TKD', 'claira-tkd-parcours' ),
        'menu_name'          => __( 'TKD Parcours', 'claira-tkd-parcours' ),
        'add_new_item'       => __( 'Ajouter un grade', 'claira-tkd-parcours' ),
        'edit_item'          => __( 'Modifier le grade', 'claira-tkd-parcours' ),
        'new_item'           => __( 'Nouveau grade', 'claira-tkd-parcours' ),
        'view_item'          => __( 'Voir le grade', 'claira-tkd-parcours' ),
        'search_items'       => __( 'Rechercher des grades', 'claira-tkd-parcours' ),
        'not_found'          => __( 'Aucun grade trouvé.', 'claira-tkd-parcours' ),
        'not_found_in_trash' => __( 'Aucun grade dans la corbeille.', 'claira-tkd-parcours' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => false,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-awards',
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'rewrite'            => array( 'slug' => 'grade-tkd' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'tkd_grade', $args );
}

function claira_tkd_register_taxonomies() {
    $age_labels = array(
        'name'              => __( 'Tranches d’âge', 'claira-tkd-parcours' ),
        'singular_name'     => __( 'Tranche d’âge', 'claira-tkd-parcours' ),
        'search_items'      => __( 'Rechercher des tranches d’âge', 'claira-tkd-parcours' ),
        'all_items'         => __( 'Toutes les tranches d’âge', 'claira-tkd-parcours' ),
        'edit_item'         => __( 'Modifier la tranche d’âge', 'claira-tkd-parcours' ),
        'add_new_item'      => __( 'Ajouter une tranche d’âge', 'claira-tkd-parcours' ),
    );

    register_taxonomy( 'tkd_age_group', 'tkd_grade', array(
        'labels'            => $age_labels,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'rewrite'           => array( 'slug' => 'age-group' ),
    ) );

    claira_tkd_register_default_terms();
}

function claira_tkd_register_default_terms() {
    $default_terms = array(
        'tkd_age_group' => array(
            'Baby',
            'Enfant',
            'Adolescent',
            'Adulte',
        ),
    );

    foreach ( $default_terms as $taxonomy => $terms ) {
        foreach ( $terms as $term ) {
            if ( ! term_exists( $term, $taxonomy ) ) {
                wp_insert_term( $term, $taxonomy );
            }
        }
    }
}
```

---

## 3. `includes/meta-boxes.php`
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function claira_tkd_register_meta_boxes() {
    add_meta_box(
        'claira_tkd_grade_content',
        __( 'Contenu du grade', 'claira-tkd-parcours' ),
        'claira_tkd_grade_content_meta_box',
        'tkd_grade',
        'normal',
        'high'
    );
}

function claira_tkd_grade_content_meta_box( $post ) {
    wp_nonce_field( 'claira_tkd_save_meta', 'claira_tkd_nonce' );

    $tech_bras = get_post_meta( $post->ID, '_claira_tkd_tech_bras', true );
    $tech_jambes = get_post_meta( $post->ID, '_claira_tkd_tech_jambes', true );
    $poomsae = get_post_meta( $post->ID, '_claira_tkd_poomsae', true );
    $download_urls = get_post_meta( $post->ID, '_claira_tkd_download_urls', true );
    $video_url = get_post_meta( $post->ID, '_claira_tkd_video_url', true );
    $video_file_id = get_post_meta( $post->ID, '_claira_tkd_video_file_id', true );
    $keup_rank = get_post_meta( $post->ID, '_claira_tkd_keup_rank', true );

    if ( ! is_array( $download_urls ) ) {
        $download_urls = array( '', '', '' );
    }

    ?>
    <p>
        <label for="claira_tkd_tech_bras"><strong><?php esc_html_e( 'Techniques Bras (Isolées)', 'claira-tkd-parcours' ); ?></strong></label><br>
        <textarea id="claira_tkd_tech_bras" name="claira_tkd_tech_bras" rows="3" style="width:100%;"><?php echo esc_textarea( $tech_bras ); ?></textarea>
    </p>
    <p>
        <label for="claira_tkd_tech_jambes"><strong><?php esc_html_e( 'Techniques Jambes (Isolées)', 'claira-tkd-parcours' ); ?></strong></label><br>
        <textarea id="claira_tkd_tech_jambes" name="claira_tkd_tech_jambes" rows="3" style="width:100%;"><?php echo esc_textarea( $tech_jambes ); ?></textarea>
    </p>
    <p>
        <label for="claira_tkd_poomsae"><strong><?php esc_html_e( 'Poomsae', 'claira-tkd-parcours' ); ?></strong></label><br>
        <textarea id="claira_tkd_poomsae" name="claira_tkd_poomsae" rows="2" style="width:100%;"><?php echo esc_textarea( $poomsae ); ?></textarea>
    </p>
    <p><strong><?php esc_html_e( 'Ressources téléchargeables', 'claira-tkd-parcours' ); ?></strong></p>
    <?php for ( $i = 0; $i < 3; $i++ ) : ?>
        <p>
            <input type="text" name="claira_tkd_download_urls[]" value="<?php echo esc_attr( $download_urls[ $i ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'URL de fichier ou ID de média', 'claira-tkd-parcours' ); ?>" style="width:100%;" />
        </p>
    <?php endfor; ?>
    <p>
        <label for="claira_tkd_keup_rank"><strong><?php esc_html_e( 'Rang keup', 'claira-tkd-parcours' ); ?></strong></label><br>
        <select id="claira_tkd_keup_rank" name="claira_tkd_keup_rank" style="width:100%;">
            <?php $keup_options = array( '', '1er keup', '2e keup', '3e keup', '4e keup', '5e keup' ); ?>
            <?php foreach ( $keup_options as $option ) : ?>
                <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $keup_rank, $option ); ?>><?php echo esc_html( $option ? $option : __( 'Aucun', 'claira-tkd-parcours' ) ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="claira_tkd_video_url"><strong><?php esc_html_e( 'URL vidéo (YouTube share, Vimeo, etc.)', 'claira-tkd-parcours' ); ?></strong></label><br>
        <input type="url" id="claira_tkd_video_url" name="claira_tkd_video_url" value="<?php echo esc_attr( $video_url ); ?>" style="width:100%;" />
    </p>
    <p>
        <label for="claira_tkd_video_file_id"><strong><?php esc_html_e( 'Fichier vidéo téléchargeable (optionnel)', 'claira-tkd-parcours' ); ?></strong></label><br>
        <input type="number" id="claira_tkd_video_file_id" name="claira_tkd_video_file_id" value="<?php echo esc_attr( $video_file_id ); ?>" style="width:100%;" />
        <small><?php esc_html_e( 'Entrez l’ID de l’élément média si vous souhaitez proposer un fichier vidéo.', 'claira-tkd-parcours' ); ?></small>
    </p>
    <?php
}

function claira_tkd_save_grade_meta( $post_id, $post ) {
    if ( ! isset( $_POST['claira_tkd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claira_tkd_nonce'] ) ), 'claira_tkd_save_meta' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( 'tkd_grade' !== $post->post_type ) {
        return;
    }

    if ( isset( $_POST['claira_tkd_tech_bras'] ) ) {
        update_post_meta( $post_id, '_claira_tkd_tech_bras', sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_tech_bras'] ) ) );
    }
    
    if ( isset( $_POST['claira_tkd_tech_jambes'] ) ) {
        update_post_meta( $post_id, '_claira_tkd_tech_jambes', sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_tech_jambes'] ) ) );
    }
    
    if ( isset( $_POST['claira_tkd_poomsae'] ) ) {
        update_post_meta( $post_id, '_claira_tkd_poomsae', sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_poomsae'] ) ) );
    }

    if ( isset( $_POST['claira_tkd_download_urls'] ) ) {
        $urls = array_map( 'esc_url_raw', array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['claira_tkd_download_urls'] ) ) ) );
        update_post_meta( $post_id, '_claira_tkd_download_urls', $urls );
    }

    if ( isset( $_POST['claira_tkd_video_url'] ) ) {
        update_post_meta( $post_id, '_claira_tkd_video_url', esc_url_raw( wp_unslash( $_POST['claira_tkd_video_url'] ) ) );
    }

    if ( isset( $_POST['claira_tkd_video_file_id'] ) ) {
        update_post_meta( $post_id, '_claira_tkd_video_file_id', absint( wp_unslash( $_POST['claira_tkd_video_file_id'] ) ) );
    }

    if ( isset( $_POST['claira_tkd_keup_rank'] ) ) {
        update_post_meta( $post_id, '_claira_tkd_keup_rank', sanitize_text_field( wp_unslash( $_POST['claira_tkd_keup_rank'] ) ) );
    }
}
```

---

## 4. `includes/shortcodes.php`
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function claira_tkd_get_belt_color_class( $title ) {
    $title = strtolower( $title );

    if ( false !== strpos( $title, 'noir' ) || false !== strpos( $title, 'noire' ) || false !== strpos( $title, 'dan' ) || false !== strpos( $title, 'poom' ) || false !== strpos( $title, 'black' ) ) {
        return 'claira-tkd-belt-black';
    }
    if ( false !== strpos( $title, 'rouge' ) ) {
        return 'claira-tkd-belt-red';
    }
    if ( false !== strpos( $title, 'bleu' ) || false !== strpos( $title, 'bleue' ) ) {
        return 'claira-tkd-belt-blue';
    }
    if ( false !== strpos( $title, 'vert' ) || false !== strpos( $title, 'verte' ) ) {
        return 'claira-tkd-belt-green';
    }
    if ( false !== strpos( $title, 'violet' ) || false !== strpos( $title, 'violette' ) || false !== strpos( $title, 'purp' ) ) {
        return 'claira-tkd-belt-purple';
    }
    if ( false !== strpos( $title, 'orange' ) || false !== strpos( $title, 'organge' ) || false !== strpos( $title, 'ora' ) ) {
        return 'claira-tkd-belt-orange';
    }
    if ( false !== strpos( $title, 'jaune' ) || false !== strpos( $title, 'jau' ) ) {
        return 'claira-tkd-belt-yellow';
    }
    if ( false !== strpos( $title, 'blanc' ) || false !== strpos( $title, 'blanche' ) ) {
        return 'claira-tkd-belt-white';
    }

    return 'claira-tkd-belt-default';
}

function claira_tkd_get_bicolor_class( $title ) {
    $title = strtolower( $title );
    $title = str_replace( array( '/', '-', '_', '\u00A0' ), ' ', $title );
    $title = preg_replace( '/\s+/', ' ', $title );
    $title = trim( $title );

    if ( false !== strpos( $title, 'blanche jaune' ) || false !== strpos( $title, 'jaune blanche' ) || false !== strpos( $title, 'blanc jaune' ) ) {
        return 'claira-tkd-bicolor-white-yellow';
    }
    if ( false !== strpos( $title, 'jaune orange' ) || false !== strpos( $title, 'orange jaune' ) || false !== strpos( $title, 'jau ora' ) || false !== strpos( $title, 'ora jau' ) ) {
        return 'claira-tkd-bicolor-yellow-orange';
    }
    if ( false !== strpos( $title, 'orange verte' ) || false !== strpos( $title, 'verte orange' ) || false !== strpos( $title, 'orange vert' ) || false !== strpos( $title, 'vert orange' ) || false !== strpos( $title, 'ora ver' ) || false !== strpos( $title, 'ver ora' ) ) {
        return 'claira-tkd-bicolor-orange-green';
    }
    if ( false !== strpos( $title, 'rouge noire' ) || false !== strpos( $title, 'noire rouge' ) || false !== strpos( $title, 'rouge noir' ) || false !== strpos( $title, 'noir rouge' ) || false !== strpos( $title, 'rou noi' ) || false !== strpos( $title, 'noi rou' ) ) {
        return 'claira-tkd-bicolor-red-black';
    }
    if ( false !== strpos( $title, 'verte bleue' ) || false !== strpos( $title, 'vert bleu' ) || false !== strpos( $title, 'ver bleu' ) ) {
        return 'claira-tkd-bicolor-green-blue';
    }
    if ( false !== strpos( $title, 'bleue rouge' ) || false !== strpos( $title, 'bleu rouge' ) ) {
        return 'claira-tkd-bicolor-blue-red';
    }

    return '';
}

function claira_tkd_get_grade_display_label( $title ) {
    $clean = preg_replace( '/^ceinture\s+/i', '', trim( $title ) );
    return ucfirst( $clean );
}

function claira_tkd_get_video_embed_html( $video_url ) {
    if ( empty( $video_url ) ) {
        return '';
    }

    $embed_url = '';
    
    if ( preg_match( '/youtu\.be\/([a-zA-Z0-9_-]+)/', $video_url, $matches ) ) {
        $embed_url = 'https://www.youtube.com/embed/' . $matches[1];
    } elseif ( preg_match( '/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/))([a-zA-Z0-9_-]+)/', $video_url, $matches ) ) {
        $embed_url = 'https://www.youtube.com/embed/' . $matches[1];
    } elseif ( preg_match( '/vimeo\.com\/(\d+)/', $video_url, $matches ) ) {
        $embed_url = 'https://player.vimeo.com/video/' . $matches[1];
    }

    if ( $embed_url ) {
        return '<div class="claira-tkd-video-container"><iframe src="' . esc_url( $embed_url ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
    }

    $ext = pathinfo( parse_url( $video_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
    if ( in_array( strtolower( $ext ), array( 'mp4', 'webm', 'ogg', 'mov' ), true ) ) {
        return '<div class="claira-tkd-video-container"><video controls width="100%"><source src="' . esc_url( $video_url ) . '" type="video/' . esc_attr( $ext ) . '"></video></div>';
    }

    return '<div class="claira-tkd-video-container"><iframe src="' . esc_url( $video_url ) . '" frameborder="0" allowfullscreen></iframe></div>';
}

function claira_tkd_parcours_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'order' => 'ASC',
        'age'   => '',
    ), $atts, 'claira_tkd_parcours' );

    $query_args = array(
        'post_type'      => 'tkd_grade',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'order'          => strtoupper( $atts['order'] ),
        'orderby'        => 'title',
    );

    if ( ! empty( $atts['age'] ) ) {
        $age_input = trim( $atts['age'] );
        $term = get_term_by( 'name', $age_input, 'tkd_age_group' );
        if ( ! $term ) {
            $term = get_term_by( 'slug', sanitize_title( $age_input ), 'tkd_age_group' );
        }

        if ( $term && ! is_wp_error( $term ) ) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'tkd_age_group',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ),
            );
        } else {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'tkd_age_group',
                    'field'    => 'slug',
                    'terms'    => sanitize_title( $age_input ),
                ),
            );
        }
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        return '<p>' . esc_html__( 'Aucun grade disponible pour ce parcours.', 'claira-tkd-parcours' ) . '</p>';
    }

    $posts = $query->posts;

    // Tri personnalisé par rang keup
    usort( $posts, function($a, $b) {
        $a_keup = get_post_meta( $a->ID, '_claira_tkd_keup_rank', true );
        $b_keup = get_post_meta( $b->ID, '_claira_tkd_keup_rank', true );
        
        preg_match( '/\d+/', $a_keup, $a_match );
        preg_match( '/\d+/', $b_keup, $b_match );
        $a_num = isset( $a_match[0] ) ? intval( $a_match[0] ) : 0;
        $b_num = isset( $b_match[0] ) ? intval( $b_match[0] ) : 0;

        if ( $a_num === $b_num ) {
            return strcmp( $a->post_title, $b->post_title );
        }
        return $a_num > $b_num ? -1 : 1;
    });

    ob_start();
    ?>
    <div class="claira-tkd-parcours">
        <div class="claira-tkd-parcours-wrapper">
            <div class="claira-tkd-parcours-hero" role="img" aria-label="<?php esc_attr_e( 'Visuel du parcours Taekwondo', 'claira-tkd-parcours' ); ?>">
                <div class="claira-tkd-list" role="list">
                    <?php foreach ( $posts as $post ) : setup_postdata( $post );
                                    $grade_id = $post->ID;
                                    $tech_bras = get_post_meta( $grade_id, '_claira_tkd_tech_bras', true );
                                    $tech_jambes = get_post_meta( $grade_id, '_claira_tkd_tech_jambes', true );
                                    $poomsae = get_post_meta( $grade_id, '_claira_tkd_poomsae', true );
                                    $download_urls = get_post_meta( $grade_id, '_claira_tkd_download_urls', true );
                                    $video_url = get_post_meta( $grade_id, '_claira_tkd_video_url', true );
                                    $video_file_id = get_post_meta( $grade_id, '_claira_tkd_video_file_id', true );
                                    $age_terms = get_the_terms( $grade_id, 'tkd_age_group' );
                                    $age_label = $age_terms ? esc_html( $age_terms[0]->name ) : '';
                                    $keup_rank = get_post_meta( $grade_id, '_claira_tkd_keup_rank', true );
                                    $belt_class = claira_tkd_get_belt_color_class( get_the_title( $post ) );
                                    $bicolor_class = claira_tkd_get_bicolor_class( get_the_title( $post ) );
                                    $display_title = claira_tkd_get_grade_display_label( get_the_title( $post ) );
                                    $modal_id = 'claira-tkd-grade-' . $grade_id;
                                ?>
                                <?php $pill_meta = array_filter( array( $keup_rank, $age_label ) ); ?>
                                <button class="claira-tkd-card claira-tkd-grade-pill <?php echo esc_attr( trim( $belt_class . ' ' . $bicolor_class ) ); ?>" type="button" role="listitem" data-modal="<?php echo esc_attr( $modal_id ); ?>">
                                    <span class="claira-tkd-card-title"><?php echo esc_html( $display_title ); ?></span>
                                    <?php if ( ! empty( $pill_meta ) ) : ?>
                                        <span class="claira-tkd-card-meta"><?php echo esc_html( implode( ' · ', $pill_meta ) ); ?></span>
                                    <?php endif; ?>
                                </button>

                                <div class="claira-tkd-modal" id="<?php echo esc_attr( $modal_id ); ?>" aria-hidden="true">
                                    <div class="claira-tkd-modal-panel" role="dialog" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
                                        <button class="claira-tkd-modal-close" type="button" aria-label="<?php esc_attr_e( 'Fermer', 'claira-tkd-parcours' ); ?>">×</button>
                                        <header class="claira-tkd-modal-header">
                                            <h2 id="<?php echo esc_attr( $modal_id ); ?>-title"><?php echo esc_html( $display_title ); ?></h2>
                                            <?php if ( ! empty( $pill_meta ) ) : ?>
                                                <p class="claira-tkd-modal-tags"><?php echo esc_html( implode( ' · ', $pill_meta ) ); ?></p>
                                            <?php endif; ?>
                                        </header>
                                        <div class="claira-tkd-modal-details">
                                            <p><strong><?php esc_html_e( 'Couleur de la ceinture', 'claira-tkd-parcours' ); ?> :</strong> <?php echo esc_html( $display_title ); ?></p>
                                            <p><strong><?php esc_html_e( 'Rang keup', 'claira-tkd-parcours' ); ?> :</strong> <?php echo esc_html( $keup_rank ? $keup_rank : __( 'Non défini', 'claira-tkd-parcours' ) ); ?></p>
                                        </div>
                                        <div class="claira-tkd-modal-body">
                                            <?php if ( $tech_bras ) : ?>
                                                <div class="claira-tkd-section">
                                                    <h3><?php esc_html_e( 'Techniques Bras (Isolées)', 'claira-tkd-parcours' ); ?></h3>
                                                    <p><?php echo wp_kses_post( nl2br( $tech_bras ) ); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ( $tech_jambes ) : ?>
                                                <div class="claira-tkd-section">
                                                    <h3><?php esc_html_e( 'Techniques Jambes (Isolées)', 'claira-tkd-parcours' ); ?></h3>
                                                    <p><?php echo wp_kses_post( nl2br( $tech_jambes ) ); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ( $poomsae ) : ?>
                                                <div class="claira-tkd-section">
                                                    <h3><?php esc_html_e( 'Poomsae', 'claira-tkd-parcours' ); ?></h3>
                                                    <p><?php echo wp_kses_post( nl2br( $poomsae ) ); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ( ! empty( $download_urls ) ) : ?>
                                                <div class="claira-tkd-resources">
                                                    <h3><?php esc_html_e( 'Téléchargements', 'claira-tkd-parcours' ); ?></h3>
                                                    <ul>
                                                        <?php foreach ( $download_urls as $download_url ) :
                                                            if ( empty( $download_url ) ) {
                                                                continue;
                                                            }

                                                            $label = esc_html__( 'Télécharger la ressource', 'claira-tkd-parcours' );
                                                            $url = esc_url( $download_url );
                                                            if ( is_numeric( $download_url ) ) {
                                                                $attachment_url = wp_get_attachment_url( absint( $download_url ) );
                                                                if ( $attachment_url ) {
                                                                    $url = esc_url( $attachment_url );
                                                                }
                                                            }
                                                        ?>
                                                            <li><a href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ( $video_url || $video_file_id ) : ?>
                                                <div class="claira-tkd-video">
                                                    <h3><?php esc_html_e( 'Vidéo de démonstration', 'claira-tkd-parcours' ); ?></h3>
                                                    <?php if ( $video_url ) : ?>
                                                        <?php echo claira_tkd_get_video_embed_html( $video_url ); ?>
                                                    <?php endif; ?>
                                                    <?php if ( $video_file_id && wp_get_attachment_url( $video_file_id ) ) : ?>
                                                        <div class="claira-tkd-video-container" style="margin-top: 10px;">
                                                            <video controls width="100%">
                                                                <source src="<?php echo esc_url( wp_get_attachment_url( $video_file_id ) ); ?>">
                                                            </video>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="claira-tkd-modal-backdrop"></div>
                                </div>
                                <?php endforeach; wp_reset_postdata(); ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
```
