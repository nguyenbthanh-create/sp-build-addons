<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Référentiel de progression technique (extrait des tableaux Enfant et Ado/Adulte).
 * Sert de source pour l'import en masse déclenché depuis la page d'administration TKD Parcours.
 */
function claira_tkd_get_import_data() {
    return array(
        // --- Enfant : 16e à Sam Poom ---
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '16e',
            'title'       => 'Blanche',
            'tech_bras'   => 'Montonn Jireugui - Poing niveau moyen',
            'tech_jambes' => 'Ap paldolegui - Levée de jambe',
            'poomsae'     => '',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '15e',
            'title'       => 'Jaune',
            'tech_bras'   => 'Arae Makki - Blocage bas',
            'tech_jambes' => 'Ap Tchagui - Coup de pied de face niveau visage',
            'poomsae'     => '',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '14e',
            'title'       => 'Jaune / Orange',
            'tech_bras'   => 'Momtong Makki - Blocage moyen vers intérieur',
            'tech_jambes' => 'Neryo Tchagui - Coup de pied marteau',
            'poomsae'     => '1/2 Taegeuk 1 (Il Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '13e',
            'title'       => 'Orange',
            'tech_bras'   => 'Olgoul Makki - Blocage visage',
            'tech_jambes' => 'Yop Tchagui - Coup de pied latéral',
            'poomsae'     => 'Taegeuk 1 (Il Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '12e',
            'title'       => 'Orange (*)',
            'tech_bras'   => 'Montonn Doubonn Jireugui - Enchaînement de deux coups de poing',
            'tech_jambes' => 'Bandal Tchagui - Semi-circulaire ventre',
            'poomsae'     => '1/2 Taegeuk 2 (Yi Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '11e',
            'title'       => 'Orange / Verte',
            'tech_bras'   => 'Sonnal Mok Tchigui - Frappe tranchant cou',
            'tech_jambes' => 'Dollyo Tchagui - Circulaire niveau visage',
            'poomsae'     => 'Taegeuk 2 complet',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '10e',
            'title'       => 'Verte',
            'tech_bras'   => "Bakkat Makki - Blocage vers l'extérieur avant-bras",
            'tech_jambes' => 'Dwit Tchagui - Coup de pied arrière',
            'poomsae'     => '1/2 Taegeuk 3 (Sam Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '9e',
            'title'       => 'Verte (*)',
            'tech_bras'   => 'Sonnal montonn Makki - Blocage double tranchant',
            'tech_jambes' => 'Twi-o Ap Tchagui - Coup de pied sauté de face',
            'poomsae'     => 'Taegeuk 3 complet',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '8e',
            'title'       => 'Violette',
            'tech_bras'   => 'Pyon Son Kkeut Jireugui - Pique de doigts',
            'tech_jambes' => 'Furyot Tchagui - Coup de pied fouetté',
            'poomsae'     => '1/2 Taegeuk 4 (Sa Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '7e',
            'title'       => 'Violette (*)',
            'tech_bras'   => 'Jebi Poom Mok Tchigui - Bloc. haut + frappe cou',
            'tech_jambes' => 'Momdolyo Tchagui - Retourné circulaire',
            'poomsae'     => 'Taegeuk 4 complet',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '6e',
            'title'       => 'Bleue',
            'tech_bras'   => 'Palkoup Tchigui - Frappe du coude',
            'tech_jambes' => 'Twi-o Yop Tchagui - Sauté latéral',
            'poomsae'     => '1/2 Taegeuk 5 (Oh Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '5e',
            'title'       => 'Bleue (*)',
            'tech_bras'   => 'Me Joomok Tchigui - Frappe marteau poing',
            'tech_jambes' => 'Twi-o Dollyo Tchagui - Sauté circulaire',
            'poomsae'     => 'Taegeuk 5 complet',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '4e',
            'title'       => 'Bleue (**)',
            'tech_bras'   => 'Eotgoreo Makki - Blocage croisé bas/haut',
            'tech_jambes' => 'Nare Tchagui - Double coup de pied',
            'poomsae'     => 'Taegeuk 6 complet (Youk Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '3e',
            'title'       => 'Rouge',
            'tech_bras'   => 'Batangson Montonn Makki - Blocage paume niveau moyen',
            'tech_jambes' => 'Twi-o Dwit Tchagui - Sauté arrière',
            'poomsae'     => 'Taegeuk 7 complet (Chil Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '2e',
            'title'       => 'Rouge (*)',
            'tech_bras'   => 'Kawi Makki - Blocage en ciseaux',
            'tech_jambes' => 'Twi-o Mondolyo Tchagui - Sauté retourné',
            'poomsae'     => 'Taegeuk 8 complet (Pal Jang)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => '1e',
            'title'       => 'Rouge (**)',
            'tech_bras'   => 'Maîtrise des enchaînements cibles et combinaisons',
            'tech_jambes' => '',
            'poomsae'     => 'Révision Globale (1 à 8)',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => 'Poom',
            'title'       => 'Rouge / Noire',
            'tech_bras'   => 'Techniques spécifiques Koryo & Cibles Multiples',
            'tech_jambes' => '',
            'poomsae'     => 'Koryo Poomsae',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => 'Y Poom',
            'title'       => 'Rouge / Noire',
            'tech_bras'   => 'Techniques spécifiques Keumgang & Maintien équilibre',
            'tech_jambes' => '',
            'poomsae'     => 'Keumgang Poomsae',
        ),
        array(
            'age_groups'  => array( 'Enfant' ),
            'keup_rank'   => 'Sam Poom',
            'title'       => 'Rouge / Noire',
            'tech_bras'   => 'Techniques spécifiques Taebaek & Précision raquettes',
            'tech_jambes' => '',
            'poomsae'     => 'Taebaek Poomsae',
        ),

        // --- Ado & Adulte : 10e à 1er Dan ---
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '10e',
            'title'       => 'Blanche',
            'tech_bras'   => "Joomok Jireugui - Poing niveau moyen\nArae Makki - Blocage bas",
            'tech_jambes' => "Ap Cha Oligui - Levé de jambe tendue\nAp Tchagui - Coup de pied de face",
            'poomsae'     => 'Taegeuk 1 (Il Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '9e',
            'title'       => 'Jaune',
            'tech_bras'   => "Momtong Makki - Blocage moyen intérieur\nOlgoul Makki - Blocage visage",
            'tech_jambes' => "Neryo Tchagui - Coup de pied marteau\nYop Tchagui - Coup de pied latéral",
            'poomsae'     => 'Taegeuk 1 (Il Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '8e',
            'title'       => 'Jaune (*)',
            'tech_bras'   => "Momtong An Makki - Blocage moyen ext.\nSonnal Mok Tchigui - Frappe tranchant cou",
            'tech_jambes' => "Bandal Tchagui - Semi-circulaire\nDollyo Tchagui - Circulaire niveau visage",
            'poomsae'     => 'Taegeuk 2 (Yi Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '7e',
            'title'       => 'Bleue',
            'tech_bras'   => "Bakkat Makki - Blocage vers l'extérieur\nSonnal Makki - Blocage double tranchant",
            'tech_jambes' => "Dwit Tchagui - Coup de pied arrière\nTwi-o Ap Tchagui - Sauté de face",
            'poomsae'     => 'Taegeuk 3 (Sam Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '6e',
            'title'       => 'Bleue (*)',
            'tech_bras'   => "Pyon Son Kkeut Jireugui - Pique de doigts\nJebi Poom Mok Tchigui - Bloc. haut + frappe cou",
            'tech_jambes' => "Houryo Tchagui - Coup de pied fouetté\nMomdolyo Tchagui - Retourné circulaire",
            'poomsae'     => 'Taegeuk 4 (Sa Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '5e',
            'title'       => 'Bleue (**)',
            'tech_bras'   => "Palkoup Tchigui - Frappe du coude\nMe Joomok Tchigui - Frappe marteau poing",
            'tech_jambes' => "Twi-o Yop Tchagui - Sauté latéral\nTwi-o Dollyo Tchagui - Sauté circulaire",
            'poomsae'     => 'Taegeuk 5 (Oh Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '4e',
            'title'       => 'Rouge',
            'tech_bras'   => 'Eotgoreo Makki - Blocage croisé bas/haut',
            'tech_jambes' => 'Nare Tchagui - Double coup de pied',
            'poomsae'     => 'Taegeuk 6 (Youk Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '3e',
            'title'       => 'Rouge (*)',
            'tech_bras'   => 'Batangson Makki - Blocage paume',
            'tech_jambes' => 'Twi-o Dwit Tchagui - Sauté arrière',
            'poomsae'     => 'Taegeuk 7 (Chil Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '2e',
            'title'       => 'Rouge (**)',
            'tech_bras'   => 'Kawi Makki - Blocage en ciseaux',
            'tech_jambes' => 'Twi-o Mondolyo Tchagui - Sauté retourné',
            'poomsae'     => 'Taegeuk 8 (Pal Jang) complet',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '1e',
            'title'       => 'Rouge (***)',
            'tech_bras'   => 'Maîtrise complète des enchaînements - Combinaisons cibles (Sautés + Rotation)',
            'tech_jambes' => '',
            'poomsae'     => 'Révision Globale (1 à 8)',
        ),
        array(
            'age_groups'  => array( 'Adolescent', 'Adulte' ),
            'keup_rank'   => '1er Dan',
            'title'       => 'Noire',
            'tech_bras'   => 'Techniques spécifiques Koryo - Cibles multiples / Précision absolue',
            'tech_jambes' => '',
            'poomsae'     => 'Koryo Poomsae',
        ),
    );
}

/**
 * Retrouve un grade déjà importé pour un rang keup donné, restreint aux
 * tranches d'âge fournies (le même libellé de keup existe dans plusieurs
 * tranches d'âge, donc le rang seul ne suffit pas à identifier le grade).
 */
function claira_tkd_import_find_existing_grade_id( $keup_rank, array $age_term_ids ) {
    if ( empty( $age_term_ids ) ) {
        return 0;
    }

    $query = new WP_Query( array(
        'post_type'      => 'tkd_grade',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'   => '_claira_tkd_keup_rank',
                'value' => $keup_rank,
            ),
        ),
        'tax_query'      => array(
            array(
                'taxonomy' => 'tkd_age_group',
                'field'    => 'term_id',
                'terms'    => $age_term_ids,
            ),
        ),
    ) );

    return $query->have_posts() ? (int) $query->posts[0] : 0;
}

/**
 * Crée ou met à jour tous les grades du référentiel en une seule passe.
 * Un grade existant est retrouvé par (tranche d'âge + rang keup) et mis à
 * jour ; sinon il est créé. Les grades Ado et Adulte partagent le même
 * poste car le référentiel technique est identique pour ces deux tranches.
 */
function claira_tkd_run_bulk_import() {
    $created = 0;
    $updated = 0;

    foreach ( claira_tkd_get_import_data() as $entry ) {
        $age_term_ids = array();
        foreach ( $entry['age_groups'] as $age_name ) {
            $term = get_term_by( 'name', $age_name, 'tkd_age_group' );
            if ( $term && ! is_wp_error( $term ) ) {
                $age_term_ids[] = $term->term_id;
            }
        }

        $existing_id = claira_tkd_import_find_existing_grade_id( $entry['keup_rank'], $age_term_ids );

        $post_data = array(
            'post_title'   => $entry['title'],
            'post_content' => '',
            'post_type'    => 'tkd_grade',
            'post_status'  => 'publish',
        );

        if ( $existing_id ) {
            $post_data['ID'] = $existing_id;
        }

        $grade_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $grade_id ) ) {
            continue;
        }

        update_post_meta( $grade_id, '_claira_tkd_tech_bras', $entry['tech_bras'] );
        update_post_meta( $grade_id, '_claira_tkd_tech_jambes', $entry['tech_jambes'] );
        update_post_meta( $grade_id, '_claira_tkd_poomsae', $entry['poomsae'] );
        update_post_meta( $grade_id, '_claira_tkd_keup_rank', $entry['keup_rank'] );

        if ( $age_term_ids ) {
            wp_set_post_terms( $grade_id, $age_term_ids, 'tkd_age_group', false );
        }

        if ( $existing_id ) {
            $updated++;
        } else {
            $created++;
        }
    }

    return array(
        'created' => $created,
        'updated' => $updated,
    );
}
