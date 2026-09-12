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
        'show_ui'            => false,
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
        'show_ui'           => false,
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
