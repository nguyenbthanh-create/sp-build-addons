<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function claira_tkd_register_admin_menu() {
    $hook = add_menu_page(
        __( 'TKD Parcours', 'claira-tkd-parcours' ),
        __( 'TKD Parcours', 'claira-tkd-parcours' ),
        'edit_posts',
        'claira-tkd-parcours',
        'claira_tkd_render_admin_page',
        'dashicons-awards'
    );

    add_submenu_page(
        'claira-tkd-parcours',
        __( 'Grades', 'claira-tkd-parcours' ),
        __( 'Grades', 'claira-tkd-parcours' ),
        'edit_posts',
        'claira-tkd-parcours',
        'claira_tkd_render_admin_page'
    );

    // Traiter le formulaire avant tout envoi de HTML (admin-header.php est
    // déjà rendu au moment où le callback de la page est appelé, donc un
    // wp_safe_redirect() lancé depuis là arrive trop tard : "headers already
    // sent" et page blanche). Ce hook se déclenche avant cet envoi.
    add_action( "load-{$hook}", 'claira_tkd_process_admin_page_form' );
}

/**
 * Stocke/récupère le message d'erreur éventuel du traitement du formulaire,
 * pour le transmettre de claira_tkd_process_admin_page_form() (exécuté tôt,
 * sur le hook load-{page}) à claira_tkd_render_admin_page() (exécuté après
 * l'en-tête admin).
 */
function claira_tkd_admin_page_message( $set = null ) {
    static $message = '';
    if ( null !== $set ) {
        $message = $set;
    }
    return $message;
}

function claira_tkd_process_admin_page_form() {
    if ( ! isset( $_POST['claira_tkd_admin_action'] ) ) {
        return;
    }

    $handle_res = claira_tkd_handle_frontend_admin_form();
    if ( is_string( $handle_res ) ) {
        claira_tkd_admin_page_message( $handle_res );
    }
}

function claira_tkd_render_admin_page() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( "Vous n'avez pas les droits nécessaires pour accéder à cette page.", 'claira-tkd-parcours' ) );
    }

    $message = '';
    if ( isset( $_GET['created'] ) ) {
        $message = '<div class="claira-tkd-admin-message success">' . esc_html__( 'Grade ajouté avec succès.', 'claira-tkd-parcours' ) . '</div>';
    } elseif ( isset( $_GET['updated'] ) ) {
        $message = '<div class="claira-tkd-admin-message success">' . esc_html__( 'Grade mis à jour avec succès.', 'claira-tkd-parcours' ) . '</div>';
    } elseif ( isset( $_GET['deleted'] ) ) {
        $message = '<div class="claira-tkd-admin-message success">' . esc_html__( 'Grade supprimé.', 'claira-tkd-parcours' ) . '</div>';
    } elseif ( isset( $_GET['imported'] ) ) {
        $created = isset( $_GET['import_created'] ) ? absint( wp_unslash( $_GET['import_created'] ) ) : 0;
        $updated = isset( $_GET['import_updated'] ) ? absint( wp_unslash( $_GET['import_updated'] ) ) : 0;
        $message = '<div class="claira-tkd-admin-message success">' . sprintf(
            /* translators: 1: nombre de grades créés, 2: nombre de grades mis à jour */
            esc_html__( 'Import terminé : %1$d grade(s) créé(s), %2$d grade(s) mis à jour.', 'claira-tkd-parcours' ),
            $created,
            $updated
        ) . '</div>';
    }

    $handle_res = claira_tkd_admin_page_message();
    if ( $handle_res ) {
        $message = $handle_res;
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

    $keup_options_by_age = claira_tkd_get_keup_options_by_age();

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
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'TKD Parcours', 'claira-tkd-parcours' ); ?></h1>

        <div class="claira-tkd-admin">
            <?php if ( $message ) : ?>
                <div class="claira-tkd-admin-message"><?php echo wp_kses_post( $message ); ?></div>
            <?php endif; ?>

            <section class="claira-tkd-admin-import">
                <h2><?php esc_html_e( 'Import du référentiel', 'claira-tkd-parcours' ); ?></h2>
                <p><?php esc_html_e( 'Crée ou met à jour en une fois tous les grades du programme de progression (Enfant, Ado, Adulte) à partir du référentiel technique du club.', 'claira-tkd-parcours' ); ?></p>
                <form method="post">
                    <?php wp_nonce_field( 'claira_tkd_admin_action', 'claira_tkd_admin_nonce' ); ?>
                    <input type="hidden" name="claira_tkd_admin_action" value="bulk_import" />
                    <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Importer le référentiel ? Les grades déjà importés seront mis à jour (leurs techniques, jambes et poomsae seront écrasés par le référentiel).', 'claira-tkd-parcours' ) ); ?>');">
                        <?php esc_html_e( 'Importer / Mettre à jour les grades', 'claira-tkd-parcours' ); ?>
                    </button>
                </form>
            </section>

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
                <style>
                    .claira-tkd-admin-pill {
                        display: inline-block !important;
                        width: 18px !important;
                        height: 18px !important;
                        min-width: 18px !important;
                        border-radius: 50% !important;
                        border: 1px solid rgba(255,255,255,0.4) !important;
                        box-sizing: border-box !important;
                        flex-shrink: 0 !important;
                        margin-right: 8px !important;
                        vertical-align: middle !important;
                    }
                    .claira-tkd-admin-pill.claira-tkd-belt-white { background: #ffffff !important; }
                    .claira-tkd-admin-pill.claira-tkd-belt-yellow { background: #ffd600 !important; }
                    .claira-tkd-admin-pill.claira-tkd-belt-orange { background: #f37021 !important; }
                    .claira-tkd-admin-pill.claira-tkd-belt-green { background: #00a651 !important; }
                    .claira-tkd-admin-pill.claira-tkd-belt-purple { background: #7b2cbf !important; }
                    .claira-tkd-admin-pill.claira-tkd-belt-blue { background: #0072ce !important; }
                    .claira-tkd-admin-pill.claira-tkd-belt-red { background: #ed1c24 !important; }
                    .claira-tkd-admin-pill.claira-tkd-belt-black { background: #000000 !important; border-color: #777 !important; }
                    .claira-tkd-admin-pill.claira-tkd-bicolor-white-yellow { background: linear-gradient(180deg, #ffffff 50%, #ffd600 50%) !important; }
                    .claira-tkd-admin-pill.claira-tkd-bicolor-yellow-orange { background: linear-gradient(180deg, #ffd600 50%, #f37021 50%) !important; }
                    .claira-tkd-admin-pill.claira-tkd-bicolor-orange-green { background: linear-gradient(180deg, #f37021 50%, #00a651 50%) !important; }
                    .claira-tkd-admin-pill.claira-tkd-bicolor-red-black { background: linear-gradient(180deg, #ed1c24 50%, #000000 50%) !important; }
                    .claira-tkd-admin-pill.claira-tkd-bicolor-green-blue { background: linear-gradient(180deg, #00a651 50%, #0072ce 50%) !important; }
                    .claira-tkd-admin-pill.claira-tkd-bicolor-blue-red { background: linear-gradient(180deg, #0072ce 50%, #ed1c24 50%) !important; }
                </style>
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
                        <?php if ( ! empty( $grades ) && is_array( $grades ) ) : ?>
                            <?php
                            $age_order_map = array( 'Baby' => 1, 'Enfant' => 2, 'Adolescent' => 3, 'Adulte' => 4 );
                            usort( $grades, function( $g1, $g2 ) use ( $age_order_map ) {
                                $terms1 = get_the_terms( $g1->ID, 'tkd_age_group' );
                                $terms2 = get_the_terms( $g2->ID, 'tkd_age_group' );

                                $age1 = ( $terms1 && ! is_wp_error( $terms1 ) && isset( $terms1[0]->name ) ) ? $terms1[0]->name : '';
                                $age2 = ( $terms2 && ! is_wp_error( $terms2 ) && isset( $terms2[0]->name ) ) ? $terms2[0]->name : '';

                                $order1 = isset( $age_order_map[ $age1 ] ) ? $age_order_map[ $age1 ] : 99;
                                $order2 = isset( $age_order_map[ $age2 ] ) ? $age_order_map[ $age2 ] : 99;

                                if ( $order1 !== $order2 ) {
                                    return ( $order1 < $order2 ) ? -1 : 1;
                                }

                                $keup1 = (string) get_post_meta( $g1->ID, '_claira_tkd_keup_rank', true );
                                $keup2 = (string) get_post_meta( $g2->ID, '_claira_tkd_keup_rank', true );

                                preg_match( '/\d+/', $keup1, $m1 );
                                preg_match( '/\d+/', $keup2, $m2 );

                                $num1 = isset( $m1[0] ) ? (int) $m1[0] : 0;
                                $num2 = isset( $m2[0] ) ? (int) $m2[0] : 0;

                                if ( $num1 === $num2 ) {
                                    return strcmp( (string) $g1->post_title, (string) $g2->post_title );
                                }

                                return ( $num1 > $num2 ) ? -1 : 1;
                            } );
                            ?>
                            <?php foreach ( $grades as $grade ) : ?>
                                <?php
                                $age = get_the_terms( $grade->ID, 'tkd_age_group' );
                                $keup_rank = get_post_meta( $grade->ID, '_claira_tkd_keup_rank', true );
                                $belt_class = claira_tkd_get_belt_color_class( get_the_title( $grade ) );
                                $bicolor_class = claira_tkd_get_bicolor_class( get_the_title( $grade ) );
                                ?>
                                <tr>
                                    <td>
                                        <span class="claira-tkd-admin-pill <?php echo esc_attr( trim( $belt_class . ' ' . $bicolor_class ) ); ?>"></span>
                                        <span><?php echo esc_html( get_the_title( $grade ) ); ?></span>
                                    </td>
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
    </div>
    <?php
    $keup_json = wp_json_encode( $keup_options_by_age );
    ?>
    <script type="text/javascript">
    (function () {
        var ageSelect = document.getElementById('claira_tkd_age_group');
        var keupSelect = document.getElementById('claira_tkd_keup_rank');
        if (!ageSelect || !keupSelect) {
            return;
        }

        var optionsByAge = <?php echo $keup_json; ?>;
        function buildOptions(ageName) {
            var options = optionsByAge[ageName] || {};
            var currentValue = keupSelect.value;
            keupSelect.innerHTML = '';

            var defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.text = '<?php echo esc_js( __( 'Choisir un rang keup', 'claira-tkd-parcours' ) ); ?>';
            keupSelect.appendChild(defaultOption);

            Object.keys(options).forEach(function(key) {
                var option = document.createElement('option');
                option.value = key;
                option.text = options[key];
                if (currentValue === key) {
                    option.selected = true;
                }
                keupSelect.appendChild(option);
            });
        }

        ageSelect.addEventListener('change', function () {
            var selectedLabel = ageSelect.options[ageSelect.selectedIndex] ? ageSelect.options[ageSelect.selectedIndex].text : '';
            buildOptions(selectedLabel);
        });

        var initialLabel = ageSelect.options[ageSelect.selectedIndex] ? ageSelect.options[ageSelect.selectedIndex].text : '';
        buildOptions(initialLabel);
    })();
    </script>
    <?php
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

    if ( 'bulk_import' === $action ) {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            return '<div class="claira-tkd-admin-message error">' . esc_html__( "Vous n'avez pas les droits nécessaires pour lancer l'import.", 'claira-tkd-parcours' ) . '</div>';
        }

        $result = claira_tkd_run_bulk_import();
        $redirect_url = remove_query_arg( array( 'edit_grade', 'created', 'updated', 'deleted', 'imported', 'import_created', 'import_updated' ) );
        $redirect_url = add_query_arg( array(
            'imported'       => '1',
            'import_created' => $result['created'],
            'import_updated' => $result['updated'],
        ), $redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }

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
