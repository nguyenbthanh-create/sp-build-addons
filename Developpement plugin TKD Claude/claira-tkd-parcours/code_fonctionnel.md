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

---

## 5. `includes/admin-frontend.php`
```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function claira_tkd_admin_frontend_shortcode( $atts ) {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        return '<div class="claira-tkd-admin-blocked">' . esc_html__( 'Accès réservé aux gestionnaires. Veuillez vous connecter avec un compte autorisé pour modifier le parcours.', 'claira-tkd-parcours' ) . '</div>';
    }

    $message = '';
    if ( isset( $_GET['created'] ) ) {
        $message = '<div class="claira-tkd-admin-message success">' . esc_html__( 'Grade ajouté avec succès.', 'claira-tkd-parcours' ) . '</div>';
    } elseif ( isset( $_GET['updated'] ) ) {
        $message = '<div class="claira-tkd-admin-message success">' . esc_html__( 'Grade mis à jour avec succès.', 'claira-tkd-parcours' ) . '</div>';
    } elseif ( isset( $_GET['deleted'] ) ) {
        $message = '<div class="claira-tkd-admin-message success">' . esc_html__( 'Grade supprimé.', 'claira-tkd-parcours' ) . '</div>';
    }

    if ( isset( $_POST['claira_tkd_admin_action'] ) ) {
        $handle_res = claira_tkd_handle_frontend_admin_form();
        if ( is_string( $handle_res ) ) {
            $message = $handle_res;
        }
    }

    $edit_grade_id = isset( $_GET['edit_grade'] ) ? absint( wp_unslash( $_GET['edit_grade'] ) ) : 0;
    $edit_grade = $edit_grade_id ? get_post( $edit_grade_id ) : null;
    if ( $edit_grade && 'tkd_grade' !== $edit_grade->post_type ) {
        $edit_grade = null;
    }

    $age_terms = get_terms( array( 'taxonomy' => 'tkd_age_group', 'hide_empty' => false ) );
    $age_terms = claira_tkd_sort_terms_by_order( $age_terms, array( 'Baby', 'Enfant', 'Adolescent', 'Adulte' ) );

    $title = $edit_grade ? $edit_grade->post_title : '';
    $tech_bras = $edit_grade ? get_post_meta( $edit_grade->ID, '_claira_tkd_tech_bras', true ) : '';
    $tech_jambes = $edit_grade ? get_post_meta( $edit_grade->ID, '_claira_tkd_tech_jambes', true ) : '';
    $poomsae = $edit_grade ? get_post_meta( $edit_grade->ID, '_claira_tkd_poomsae', true ) : '';
    $download_urls = $edit_grade ? get_post_meta( $edit_grade->ID, '_claira_tkd_download_urls', true ) : array( '', '', '' );
    $video_url = $edit_grade ? get_post_meta( $edit_grade->ID, '_claira_tkd_video_url', true ) : '';
    $video_file_id = $edit_grade ? get_post_meta( $edit_grade->ID, '_claira_tkd_video_file_id', true ) : '';
    $selected_age = $edit_grade ? wp_get_post_terms( $edit_grade->ID, 'tkd_age_group', array( 'fields' => 'ids' ) ) : array();
    $selected_keup = $edit_grade ? get_post_meta( $edit_grade->ID, '_claira_tkd_keup_rank', true ) : '';

    $keup_options_by_age = array(
        'Baby' => array(
            '19e' => '19e',
            '18e' => '18e',
            '17e' => '17e',
            '16e' => '16e',
            '15e' => '15e',
            '14e' => '14e',
            '13e' => '13e',
        ),
        'Enfant' => array(
            '16e' => '16e',
            '15e' => '15e',
            '14e' => '14e',
            '13e' => '13e',
            '12e' => '12e',
            '11e' => '11e',
            '10e' => '10e',
            '9e' => '9e',
            '8e' => '8e',
            '7e' => '7e',
            '6e' => '6e',
            '5e' => '5e',
            '4e' => '4e',
            '3e' => '3e',
            '2e' => '2e',
            '1e' => '1e',
            'II Poom' => 'II Poom',
            'Y Poom' => 'Y Poom',
            'Sam Poom' => 'Sam Poom',
        ),
        'Adolescent' => array(
            '10e' => '10e',
            '9e' => '9e',
            '8e' => '8e',
            '7e' => '7e',
            '6e' => '6e',
            '5e' => '5e',
            '4e' => '4e',
            '3e' => '3e',
            '2e' => '2e',
            '1e' => '1e',
            'II Poom' => 'II Poom',
            '1er Dan' => '1er Dan',
        ),
        'Adulte' => array(
            '10e' => '10e',
            '9e' => '9e',
            '8e' => '8e',
            '7e' => '7e',
            '6e' => '6e',
            '5e' => '5e',
            '4e' => '4e',
            '3e' => '3e',
            '2e' => '2e',
            '1e' => '1e',
            'II Poom' => 'II Poom',
            '1er Dan' => '1er Dan',
        ),
    );

    $selected_age_name = '';
    if ( ! empty( $selected_age ) ) {
        $selected_term = get_term( $selected_age[0], 'tkd_age_group' );
        if ( $selected_term && ! is_wp_error( $selected_term ) ) {
            $selected_age_name = $selected_term->name;
        }
    }

    $age_based_keup_options = array();
    if ( $selected_age_name && isset( $keup_options_by_age[ $selected_age_name ] ) ) {
        $age_based_keup_options = $keup_options_by_age[ $selected_age_name ];
    } else {
        $age_based_keup_options = array_unique( array_merge( ...array_values( $keup_options_by_age ) ) );
    }

    if ( ! is_array( $download_urls ) ) {
        $download_urls = array( '', '', '' );
    }

    $grades = get_posts( array(
        'post_type'      => 'tkd_grade',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    ob_start();
    ?>
    <div class="claira-tkd-admin">
        <?php if ( $message ) : ?>
            <div class="claira-tkd-admin-message"><?php echo wp_kses_post( $message ); ?></div>
        <?php endif; ?>

        <section class="claira-tkd-admin-form">
            <h2 style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span><?php echo esc_html( $edit_grade ? __( 'Modifier un grade', 'claira-tkd-parcours' ) : __( 'Ajouter un grade', 'claira-tkd-parcours' ) ); ?></span>
                <?php if ( $edit_grade ) : ?>
                    <a class="button button-secondary" href="<?php echo esc_url( remove_query_arg( 'edit_grade' ) ); ?>" style="font-size:0.85rem; text-transform:none;">+ <?php esc_html_e( 'Saisir un autre grade', 'claira-tkd-parcours' ); ?></a>
                <?php endif; ?>
            </h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'claira_tkd_admin_action', 'claira_tkd_admin_nonce' ); ?>
                <input type="hidden" name="claira_tkd_admin_action" value="<?php echo esc_attr( $edit_grade ? 'edit' : 'create' ); ?>" />
                <?php if ( $edit_grade ) : ?>
                    <input type="hidden" name="claira_tkd_grade_id" value="<?php echo esc_attr( $edit_grade->ID ); ?>" />
                <?php endif; ?>

                <p>
                    <label for="claira_tkd_title"><?php esc_html_e( 'Titre du grade', 'claira-tkd-parcours' ); ?></label><br>
                    <input type="text" id="claira_tkd_title" name="claira_tkd_title" value="<?php echo esc_attr( $title ); ?>" style="width:100%;" required />
                </p>

                <p>
                    <label for="claira_tkd_tech_bras"><?php esc_html_e( 'Techniques Bras (Isolées)', 'claira-tkd-parcours' ); ?></label><br>
                    <textarea id="claira_tkd_tech_bras" name="claira_tkd_tech_bras" rows="3" style="width:100%;"><?php echo esc_textarea( $tech_bras ); ?></textarea>
                </p>
                <p>
                    <label for="claira_tkd_tech_jambes"><?php esc_html_e( 'Techniques Jambes (Isolées)', 'claira-tkd-parcours' ); ?></label><br>
                    <textarea id="claira_tkd_tech_jambes" name="claira_tkd_tech_jambes" rows="3" style="width:100%;"><?php echo esc_textarea( $tech_jambes ); ?></textarea>
                </p>
                <p>
                    <label for="claira_tkd_poomsae"><?php esc_html_e( 'Poomsae', 'claira-tkd-parcours' ); ?></label><br>
                    <textarea id="claira_tkd_poomsae" name="claira_tkd_poomsae" rows="2" style="width:100%;"><?php echo esc_textarea( $poomsae ); ?></textarea>
                </p>

                <p>
                    <label for="claira_tkd_age_group"><?php esc_html_e( 'Tranche d’âge', 'claira-tkd-parcours' ); ?></label><br>
                    <select id="claira_tkd_age_group" name="claira_tkd_age_group" style="width:100%;">
                        <option value=""><?php esc_html_e( 'Aucune', 'claira-tkd-parcours' ); ?></option>
                        <?php foreach ( $age_terms as $term ) : ?>
                            <option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $selected_age, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="claira_tkd_keup_rank"><?php esc_html_e( 'Rang keup', 'claira-tkd-parcours' ); ?></label><br>
                    <select id="claira_tkd_keup_rank" name="claira_tkd_keup_rank" style="width:100%;">
                        <option value=""><?php esc_html_e( 'Choisir un rang keup', 'claira-tkd-parcours' ); ?></option>
                        <?php foreach ( $age_based_keup_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_keup, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <fieldset>
                    <legend><?php esc_html_e( 'Ressources téléchargeables', 'claira-tkd-parcours' ); ?></legend>
                    <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                        <p>
                            <input type="text" name="claira_tkd_download_urls[]" value="<?php echo esc_attr( $download_urls[ $i ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'URL de fichier (PDF, image, doc)', 'claira-tkd-parcours' ); ?>" style="width:100%;" />
                        </p>
                    <?php endfor; ?>
                </fieldset>

                <p>
                    <label for="claira_tkd_video_url"><?php esc_html_e( 'URL vidéo externe (YouTube share link, Vimeo)', 'claira-tkd-parcours' ); ?></label><br>
                    <input type="url" id="claira_tkd_video_url" name="claira_tkd_video_url" value="<?php echo esc_attr( $video_url ); ?>" style="width:100%;" placeholder="https://youtu.be/..." />
                </p>

                <p>
                    <label for="claira_tkd_video_file"><?php esc_html_e( 'Envoyer / Remplacer un fichier vidéo local (MP4, WebM)', 'claira-tkd-parcours' ); ?></label><br>
                    <?php if ( $video_file_id && wp_get_attachment_url( $video_file_id ) ) : ?>
                        <div style="margin: 5px 0; padding: 8px; background: #222; border-radius: 4px;">
                            <strong><?php esc_html_e( 'Vidéo actuelle :', 'claira-tkd-parcours' ); ?></strong>
                            <a href="<?php echo esc_url( wp_get_attachment_url( $video_file_id ) ); ?>" target="_blank" style="color:#38bdf8; text-decoration:underline;">
                                <?php echo esc_html( basename( get_attached_file( $video_file_id ) ) ); ?>
                            </a>
                            <label style="margin-left:15px; color:#ef4444;">
                                <input type="checkbox" name="claira_tkd_delete_video_file" value="1" /> <?php esc_html_e( 'Supprimer cette vidéo', 'claira-tkd-parcours' ); ?>
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="claira_tkd_video_file" name="claira_tkd_video_file" accept="video/*" style="width:100%;" />
                    <input type="hidden" name="claira_tkd_video_file_id" value="<?php echo esc_attr( $video_file_id ); ?>" />
                    <small style="color:#94a3b8;"><?php esc_html_e( 'Sélectionnez un fichier vidéo depuis votre ordinateur pour l’héberger sur le site.', 'claira-tkd-parcours' ); ?></small>
                </p>

                <p>
                    <button type="submit" class="claira-tkd-admin-submit button button-primary">
                        <?php echo esc_html( $edit_grade ? __( 'Mettre à jour le grade', 'claira-tkd-parcours' ) : __( 'Ajouter le grade', 'claira-tkd-parcours' ) ); ?>
                    </button>
                    <?php if ( $edit_grade ) : ?>
                        <a class="claira-tkd-admin-cancel button" href="<?php echo esc_url( remove_query_arg( 'edit_grade' ) ); ?>"><?php esc_html_e( 'Annuler', 'claira-tkd-parcours' ); ?></a>
                    <?php endif; ?>
                </p>
            </form>
        </section>

        <section class="claira-tkd-admin-list">
            <h2><?php esc_html_e( 'Grades existants', 'claira-tkd-parcours' ); ?></h2>
            <table class="claira-tkd-admin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Titre', 'claira-tkd-parcours' ); ?></th>
                        <th><?php esc_html_e( 'Tranche d’âge', 'claira-tkd-parcours' ); ?></th>
                        <th><?php esc_html_e( 'Rang keup', 'claira-tkd-parcours' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'claira-tkd-parcours' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $grades ) : ?>
                        <?php foreach ( $grades as $grade ) : ?>
                            <?php $age = get_the_terms( $grade->ID, 'tkd_age_group' ); ?>
                            <?php $keup_rank = get_post_meta( $grade->ID, '_claira_tkd_keup_rank', true ); ?>
                            <tr>
                                <td><?php echo esc_html( get_the_title( $grade ) ); ?></td>
                                <td><?php echo esc_html( $age ? $age[0]->name : '' ); ?></td>
                                <td><?php echo esc_html( $keup_rank ); ?></td>
                                <td>
                                    <a class="claira-tkd-admin-action" href="<?php echo esc_url( add_query_arg( 'edit_grade', $grade->ID ) ); ?>"><?php esc_html_e( 'Éditer', 'claira-tkd-parcours' ); ?></a>
                                    <form method="post" class="claira-tkd-admin-delete-form" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer ce grade ?', 'claira-tkd-parcours' ) ); ?>');">
                                        <?php wp_nonce_field( 'claira_tkd_admin_action', 'claira_tkd_admin_nonce' ); ?>
                                        <input type="hidden" name="claira_tkd_admin_action" value="delete" />
                                        <input type="hidden" name="claira_tkd_grade_id" value="<?php echo esc_attr( $grade->ID ); ?>" />
                                        <button type="submit" class="claira-tkd-admin-action button button-secondary"><?php esc_html_e( 'Supprimer', 'claira-tkd-parcours' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'Aucun grade créé pour le moment.', 'claira-tkd-parcours' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

function claira_tkd_sort_terms_by_order( $terms, $order_names = array() ) {
    if ( empty( $terms ) || empty( $order_names ) || ! is_array( $terms ) ) {
        return $terms;
    }

    $order_map = array_flip( $order_names );
    usort( $terms, function( $a, $b ) use ( $order_map ) {
        $a_index = isset( $order_map[ $a->name ] ) ? $order_map[ $a->name ] : PHP_INT_MAX;
        $b_index = isset( $order_map[ $b->name ] ) ? $order_map[ $b->name ] : PHP_INT_MAX;

        if ( $a_index === $b_index ) {
            return strcmp( $a->name, $b->name );
        }

        return $a_index < $b_index ? -1 : 1;
    } );

    return $terms;
}

function claira_tkd_handle_frontend_admin_form() {
    if ( ! isset( $_POST['claira_tkd_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claira_tkd_admin_nonce'] ) ), 'claira_tkd_admin_action' ) ) {
        return '<div class="claira-tkd-admin-message error">' . esc_html__( 'La validation de sécurité a échoué.', 'claira-tkd-parcours' ) . '</div>';
    }

    $action = sanitize_text_field( wp_unslash( $_POST['claira_tkd_admin_action'] ) );
    $grade_id = isset( $_POST['claira_tkd_grade_id'] ) ? absint( wp_unslash( $_POST['claira_tkd_grade_id'] ) ) : 0;

    if ( 'delete' === $action && $grade_id ) {
        if ( current_user_can( 'delete_post', $grade_id ) ) {
            wp_delete_post( $grade_id, true );
            $redirect_url = remove_query_arg( array( 'edit_grade', 'created', 'updated', 'deleted' ) );
            $redirect_url = add_query_arg( 'deleted', '1', $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        return '<div class="claira-tkd-admin-message error">' . esc_html__( 'Impossible de supprimer ce grade.', 'claira-tkd-parcours' ) . '</div>';
    }

    $title = isset( $_POST['claira_tkd_title'] ) ? sanitize_text_field( wp_unslash( $_POST['claira_tkd_title'] ) ) : '';
    $tech_bras = isset( $_POST['claira_tkd_tech_bras'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_tech_bras'] ) ) : '';
    $tech_jambes = isset( $_POST['claira_tkd_tech_jambes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_tech_jambes'] ) ) : '';
    $poomsae = isset( $_POST['claira_tkd_poomsae'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claira_tkd_poomsae'] ) ) : '';
    $download_urls = isset( $_POST['claira_tkd_download_urls'] ) && is_array( $_POST['claira_tkd_download_urls'] ) ? $_POST['claira_tkd_download_urls'] : array();
    $download_urls = array_filter( array_map( 'sanitize_text_field', array_map( 'wp_unslash', $download_urls ) ) );
    $download_urls = array_values( $download_urls );
    $video_url = isset( $_POST['claira_tkd_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['claira_tkd_video_url'] ) ) : '';
    $video_file_id = isset( $_POST['claira_tkd_video_file_id'] ) ? absint( wp_unslash( $_POST['claira_tkd_video_file_id'] ) ) : 0;
    $age_group = isset( $_POST['claira_tkd_age_group'] ) ? absint( wp_unslash( $_POST['claira_tkd_age_group'] ) ) : 0;
    $keup_rank = isset( $_POST['claira_tkd_keup_rank'] ) ? sanitize_text_field( wp_unslash( $_POST['claira_tkd_keup_rank'] ) ) : '';

    if ( empty( $title ) ) {
        return '<div class="claira-tkd-admin-message error">' . esc_html__( 'Le titre du grade est requis.', 'claira-tkd-parcours' ) . '</div>';
    }

    $post_data = array(
        'post_title'   => $title,
        'post_content' => '',
        'post_type'    => 'tkd_grade',
        'post_status'  => 'publish',
    );

    if ( $grade_id && 'edit' === $action ) {
        $post_data['ID'] = $grade_id;
    }

    $new_grade_id = wp_insert_post( $post_data, true );
    if ( is_wp_error( $new_grade_id ) ) {
        return '<div class="claira-tkd-admin-message error">' . esc_html__( 'Une erreur est survenue lors de la sauvegarde du grade.', 'claira-tkd-parcours' ) . '</div>';
    }

    update_post_meta( $new_grade_id, '_claira_tkd_tech_bras', $tech_bras );
    update_post_meta( $new_grade_id, '_claira_tkd_tech_jambes', $tech_jambes );
    update_post_meta( $new_grade_id, '_claira_tkd_poomsae', $poomsae );
    update_post_meta( $new_grade_id, '_claira_tkd_download_urls', $download_urls );
    update_post_meta( $new_grade_id, '_claira_tkd_video_url', $video_url );
    update_post_meta( $new_grade_id, '_claira_tkd_keup_rank', $keup_rank );

    // Action de suppression du fichier vidéo si coché
    if ( isset( $_POST['claira_tkd_delete_video_file'] ) && '1' === $_POST['claira_tkd_delete_video_file'] ) {
        update_post_meta( $new_grade_id, '_claira_tkd_video_file_id', '' );
    }

    // Gestion de l'upload de fichier vidéo local depuis l’ordinateur
    if ( ! empty( $_FILES['claira_tkd_video_file']['name'] ) ) {
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $attachment_id = media_handle_upload( 'claira_tkd_video_file', $new_grade_id );
        if ( ! is_wp_error( $attachment_id ) ) {
            update_post_meta( $new_grade_id, '_claira_tkd_video_file_id', $attachment_id );
        }
    } elseif ( ! isset( $_POST['claira_tkd_delete_video_file'] ) ) {
        update_post_meta( $new_grade_id, '_claira_tkd_video_file_id', $video_file_id );
    }

    $taxonomies = array(
        'tkd_age_group' => $age_group ? array( $age_group ) : array(),
    );

    foreach ( $taxonomies as $taxonomy => $terms ) {
        wp_set_post_terms( $new_grade_id, $terms, $taxonomy, false );
    }

    $param = ('create' === $action) ? 'created' : 'updated';
    $redirect_url = remove_query_arg( array( 'edit_grade', 'created', 'updated', 'deleted' ) );
    $redirect_url = add_query_arg( $param, '1', $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
```

---

## 6. `includes/enqueue.php`
```php
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
    global $post_type;

    if ( 'tkd_grade' !== $post_type ) {
        return;
    }

    wp_enqueue_style( 'claira-tkd-admin-style', CLAIRA_TKD_PLUGIN_URL . 'assets/css/style.css', array(), CLAIRA_TKD_PLUGIN_VERSION );
}
```

---

## 7. `assets/css/style.css`
```css
.claira-tkd-parcours {
  font-family: 'Arial', sans-serif;
  margin: 0 auto;
  padding: 1rem;
}

.claira-tkd-parcours-wrapper {
  position: relative;
  border-radius: 12px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
  background: #111111;
  overflow: hidden;
  color: #f1f5f9;
}

.claira-tkd-parcours-hero {
  background: transparent;
  position: relative;
  padding: 1.5rem;
}

.claira-tkd-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 900px;
  margin: 0 auto;
}

/* Belt Cards (Rows) */
.claira-tkd-card {
  align-items: center;
  background: #1e1e1e;
  border: 1px solid #333;
  border-radius: 8px;
  color: #f8fafc;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  flex-wrap: nowrap;
  min-height: 56px;
  padding: 0.75rem 1rem 0.75rem 3.5rem;
  position: relative;
  text-align: left;
  transition: all 0.2s ease;
  width: 100%;
}

.claira-tkd-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 36px;
  border-radius: 8px 0 0 8px;
  background: var(--belt-color, #64748b);
}

.claira-tkd-card-meta {
  color: #94a3b8;
  font-size: 0.85rem;
  margin-left: auto;
}

.claira-tkd-card:hover,
.claira-tkd-card:focus {
  background: #2a2a2a;
  border-color: #555;
  transform: translateX(4px);
}

.claira-tkd-card-title {
  font-size: 1rem;
  font-weight: bold;
  text-transform: uppercase;
}

/* Belt Colors matching PDF */
.claira-tkd-belt-default { --belt-color: #94a3b8; }
.claira-tkd-belt-white { --belt-color: #ffffff; }
.claira-tkd-belt-yellow { --belt-color: #ffd600; }
.claira-tkd-belt-orange { --belt-color: #f37021; }
.claira-tkd-belt-green { --belt-color: #00a651; }
.claira-tkd-belt-purple { --belt-color: #7b2cbf; }
.claira-tkd-belt-blue { --belt-color: #0072ce; }
.claira-tkd-belt-red { --belt-color: #ed1c24; }
.claira-tkd-belt-black { --belt-color: #000000; }

/* In case black blends with card background */
.claira-tkd-belt-black .claira-tkd-card::before,
.claira-tkd-belt-black.claira-tkd-card::before { border-right: 1px solid #333; }

/* Bicolor Belts Gradient Pastille */
.claira-tkd-bicolor-white-yellow::before,
.claira-tkd-card.claira-tkd-bicolor-white-yellow::before {
  background: linear-gradient(180deg, #ffffff 50%, #ffd600 50%) !important;
}
.claira-tkd-bicolor-yellow-orange::before,
.claira-tkd-card.claira-tkd-bicolor-yellow-orange::before {
  background: linear-gradient(180deg, #ffd600 50%, #f37021 50%) !important;
}
.claira-tkd-bicolor-orange-green::before,
.claira-tkd-card.claira-tkd-bicolor-orange-green::before {
  background: linear-gradient(180deg, #f37021 50%, #00a651 50%) !important;
}
.claira-tkd-bicolor-red-black::before,
.claira-tkd-card.claira-tkd-bicolor-red-black::before {
  background: linear-gradient(180deg, #ed1c24 50%, #000000 50%) !important;
}
.claira-tkd-bicolor-green-blue::before,
.claira-tkd-card.claira-tkd-bicolor-green-blue::before {
  background: linear-gradient(180deg, #00a651 50%, #0072ce 50%) !important;
}
.claira-tkd-bicolor-blue-red::before,
.claira-tkd-card.claira-tkd-bicolor-blue-red::before {
  background: linear-gradient(180deg, #0072ce 50%, #ed1c24 50%) !important;
}

/* Modal Dark Theme */
.claira-tkd-modal {
  align-items: center;
  display: none;
  inset: 0;
  justify-content: center;
  position: fixed;
  z-index: 10000;
}
.claira-tkd-modal.open {
  display: flex;
}
.claira-tkd-modal-backdrop {
  background: rgba(0, 0, 0, 0.85);
  position: absolute;
  inset: 0;
}

.claira-tkd-modal-panel {
  background: #1a1a1a;
  border: 1px solid #333;
  border-radius: 12px;
  box-shadow: 0 24px 60px rgba(0,0,0,0.5);
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
  z-index: 1;
  color: #e2e8f0;
  animation: modalPop 0.3s ease;
}

@keyframes modalPop {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.claira-tkd-modal-close {
  background: rgba(255,255,255,0.1);
  border: none;
  border-radius: 50%;
  color: #fff;
  cursor: pointer;
  font-size: 1.5rem;
  width: 36px;
  height: 36px;
  position: absolute;
  right: 1.5rem;
  top: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.claira-tkd-modal-close:hover {
  background: #ed1c24;
}

.claira-tkd-modal-header {
  padding: 2rem;
  border-bottom: 1px solid #333;
}
.claira-tkd-modal-header h2 {
  margin: 0;
  font-size: 1.8rem;
  color: #fff;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.claira-tkd-modal-tags {
  color: #ffd600;
  margin-top: 0.5rem;
  font-weight: bold;
}

.claira-tkd-modal-details {
  background: #111;
  padding: 1rem 2rem;
  display: flex;
  gap: 2rem;
  border-bottom: 1px solid #333;
}
.claira-tkd-modal-details p {
  margin: 0;
  font-size: 0.95rem;
  color: #94a3b8;
}
.claira-tkd-modal-details strong {
  color: #fff;
}

.claira-tkd-modal-body {
  padding: 2rem;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.claira-tkd-section {
  background: #222;
  padding: 1.5rem;
  border-radius: 8px;
  border-top: 3px solid #ed1c24;
}
.claira-tkd-section h3 {
  margin-top: 0;
  color: #ffd600;
  font-size: 1.1rem;
  text-transform: uppercase;
  margin-bottom: 1rem;
}
.claira-tkd-section p {
  line-height: 1.6;
  margin: 0;
  font-size: 0.95rem;
}

.claira-tkd-resources, .claira-tkd-video {
  grid-column: 1 / -1;
  background: #1a1a1a;
  border: 1px solid #333;
  padding: 1.5rem;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.claira-tkd-video-container {
  position: relative;
  padding-bottom: 56.25%;
  height: 0;
  overflow: hidden;
  border-radius: 8px;
  background: #000;
  margin-top: 0.5rem;
}
.claira-tkd-video-container iframe,
.claira-tkd-video-container video {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border: 0;
}

.claira-tkd-resources h3, .claira-tkd-video h3 {
  margin: 0;
  color: #fff;
  text-transform: uppercase;
  font-size: 1.1rem;
}

.claira-tkd-resources ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
.claira-tkd-resources a, .claira-tkd-video a {
  background: #333;
  color: #fff;
  text-decoration: none;
  padding: 0.75rem 1.25rem;
  border-radius: 4px;
  font-weight: bold;
  transition: background 0.2s;
  display: inline-block;
  font-size: 0.9rem;
}
.claira-tkd-resources a:hover, .claira-tkd-video a:hover {
  background: #ed1c24;
}

@media (max-width: 768px) {
  .claira-tkd-parcours {
    padding: 0.5rem;
  }
  .claira-tkd-card {
    padding: 0.75rem 1rem 0.75rem 3rem;
  }
  .claira-tkd-card::before {
    width: 24px;
  }
  .claira-tkd-modal-panel {
    width: 100%;
    margin: 0;
    border-radius: 0;
    height: 100%;
    max-height: 100vh;
  }
  .claira-tkd-modal-details {
    flex-direction: column;
    gap: 0.5rem;
  }
}

/* ADMIN Frontend (Dark Theme PDF Inspired - High Specificity) */
.claira-tkd-admin,
div.claira-tkd-admin {
  background-color: #111111 !important;
  border: 1px solid #333333 !important;
  border-radius: 12px !important;
  padding: 2rem !important;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4) !important;
  color: #f1f5f9 !important;
}
.claira-tkd-admin h2 {
  font-size: 1.4rem !important;
  margin-bottom: 1.2rem !important;
  color: #ffd600 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 2px solid #ed1c24 !important;
  padding-bottom: 0.5rem !important;
}
.claira-tkd-admin-form,
.claira-tkd-admin-list {
  margin-bottom: 2.5rem !important;
}

.claira-tkd-admin label,
.claira-tkd-admin legend,
.claira-tkd-admin p {
  color: #ffffff !important;
  font-weight: bold;
  font-size: 0.95rem;
}

.claira-tkd-admin input[type="text"],
.claira-tkd-admin input[type="url"],
.claira-tkd-admin input[type="number"],
.claira-tkd-admin input[type="file"],
.claira-tkd-admin textarea,
.claira-tkd-admin select {
  background-color: #1e1e1e !important;
  border: 1px solid #444444 !important;
  border-radius: 6px !important;
  color: #ffffff !important;
  padding: 0.75rem 1rem !important;
  margin-top: 0.3rem !important;
  margin-bottom: 1rem !important;
  box-sizing: border-box !important;
  font-family: inherit !important;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.claira-tkd-admin input[type="text"]:focus,
.claira-tkd-admin input[type="url"]:focus,
.claira-tkd-admin input[type="number"]:focus,
.claira-tkd-admin input[type="file"]:focus,
.claira-tkd-admin textarea:focus,
.claira-tkd-admin select:focus {
  border-color: #ffd600 !important;
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(255, 214, 0, 0.2) !important;
}

.claira-tkd-admin fieldset {
  border: 1px solid #333333 !important;
  border-radius: 8px !important;
  padding: 1.2rem !important;
  margin-bottom: 1.5rem !important;
  background-color: #1a1a1a !important;
}

.claira-tkd-admin button.button-primary,
.claira-tkd-admin .button-primary,
.claira-tkd-admin input[type="submit"] {
  background-color: #ed1c24 !important;
  background: #ed1c24 !important;
  border-color: #ed1c24 !important;
  color: #ffffff !important;
  font-weight: bold !important;
  padding: 0.6rem 1.4rem !important;
  border-radius: 6px !important;
  cursor: pointer !important;
  transition: background 0.2s ease !important;
  text-transform: uppercase !important;
}

.claira-tkd-admin button.button-primary:hover,
.claira-tkd-admin .button-primary:hover {
  background-color: #c1121f !important;
  background: #c1121f !important;
  border-color: #c1121f !important;
}

.claira-tkd-admin .button-secondary,
.claira-tkd-admin-cancel {
  background-color: #333333 !important;
  background: #333333 !important;
  border-color: #444444 !important;
  color: #ffffff !important;
  padding: 0.6rem 1.4rem !important;
  border-radius: 6px !important;
  cursor: pointer !important;
  text-decoration: none !important;
  display: inline-block !important;
  font-size: 0.9rem !important;
}

.claira-tkd-admin .button-secondary:hover,
.claira-tkd-admin-cancel:hover {
  background-color: #444444 !important;
  background: #444444 !important;
  color: #ffffff !important;
}

.claira-tkd-admin-message {
  border-radius: 8px !important;
  margin-bottom: 1.5rem !important;
  padding: 1rem 1.2rem !important;
  font-weight: 500 !important;
}
.claira-tkd-admin-message.success {
  background-color: #14532d !important;
  border: 1px solid #22c55e !important;
  color: #f0fdf4 !important;
}
.claira-tkd-admin-message.error {
  background-color: #7f1d1d !important;
  border: 1px solid #ef4444 !important;
  color: #fef2f2 !important;
}

.claira-tkd-admin-table,
table.claira-tkd-admin-table {
  border-collapse: collapse !important;
  width: 100% !important;
  background-color: #1e1e1e !important;
  border-radius: 8px !important;
  overflow: hidden !important;
  border: 1px solid #333333 !important;
}
.claira-tkd-admin-table th {
  background-color: #2a2a2a !important;
  color: #ffd600 !important;
  text-transform: uppercase !important;
  font-size: 0.85rem !important;
  letter-spacing: 0.5px !important;
  border-bottom: 2px solid #333333 !important;
}
.claira-tkd-admin-table th,
.claira-tkd-admin-table td {
  border: 1px solid #333333 !important;
  padding: 0.9rem 1rem !important;
  text-align: left !important;
}
.claira-tkd-admin-table td {
  color: #e2e8f0 !important;
  background-color: #1e1e1e !important;
}

.claira-tkd-admin-action {
  color: #38bdf8 !important;
  display: inline-block !important;
  margin-right: 1rem !important;
  text-decoration: none !important;
  font-weight: bold !important;
}
.claira-tkd-admin-action:hover {
  text-decoration: underline !important;
  color: #ffd600 !important;
}

.claira-tkd-admin-delete-form {
  display: inline-block !important;
  margin: 0 !important;
}
.claira-tkd-admin-blocked {
  background-color: #291e11 !important;
  border: 1px solid #f59e0b !important;
  border-radius: 8px !important;
  padding: 1.2rem !important;
  color: #fef3c7 !important;
}
```

---

## 8. `assets/js/script.js`
```javascript
(function (document) {
    var openButtons = document.querySelectorAll('.claira-tkd-card');
    var closeButtons = document.querySelectorAll('.claira-tkd-modal-close');
    var modals = document.querySelectorAll('.claira-tkd-modal');

    function openModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        
        var iframes = modal.querySelectorAll('iframe');
        iframes.forEach(function(iframe) {
            var src = iframe.src;
            iframe.src = '';
            iframe.src = src;
        });
        
        var videos = modal.querySelectorAll('video');
        videos.forEach(function(video) {
            video.pause();
        });
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.dataset.modal);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = button.closest('.claira-tkd-modal');
            if (modal) {
                closeModal(modal);
            }
        });
    });

    modals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.classList.contains('claira-tkd-modal-backdrop')) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            modals.forEach(function (modal) {
                if (modal.classList.contains('open')) {
                    closeModal(modal);
                }
            });
        }
    });
})(document);
```
