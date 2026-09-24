<?php
/**
 * Plugin Name: TKD Cotisations
 * Plugin URI:  https://tkdclaira.fr
 * Description: Gestion des cotisations, paiements et reçus pour TKD Claira.
 * Version:     1.1.0
 * Author:      TKD Claira
 * Text Domain: tkd-cotisations
 *
 * Contenu :
 * 1. Installation des tables (activation)
 * 2. Gestion des tarifs
 * 3. Gestion des cotisations & paiements
 * 4. Interface admin — Tarifs
 * 5. Interface admin — Initialisation saison
 * 6. Interface admin — Vue globale
 * 7. Bloc cotisation sur fiche élève
 * 8. Emails de rappel
 * 9. Reçu PDF / aperçu impression
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constante chemin plugin — definie une seule fois au chargement
if ( ! defined('TKD_COT_DIR') ) {
    define( 'TKD_COT_DIR', plugin_dir_path( __FILE__ ) );
}

// Generateur PDF interne
if ( ! class_exists('TkdPDF') ) {
    require_once TKD_COT_DIR . 'TkdPDF.php';
}

// ============================================================
// 1. INSTALLATION DES TABLES
// ============================================================

function tkd_cotisations_install() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // Table des tarifs par saison
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sp_cal_cotisation_tarifs (
            id         mediumint NOT NULL AUTO_INCREMENT,
            saison     varchar(20) NOT NULL DEFAULT '',
            libelle    varchar(150) NOT NULL DEFAULT '',
            montant    decimal(8,2) NOT NULL DEFAULT 0,
            categories varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id)
        ) $charset
    ");

    // Table des cotisations par élève
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sp_cal_cotisations (
            id          mediumint NOT NULL AUTO_INCREMENT,
            eleve_id    mediumint NOT NULL,
            saison      varchar(20) NOT NULL DEFAULT '',
            tarif_id    mediumint DEFAULT NULL,
            montant_du  decimal(8,2) NOT NULL DEFAULT 0,
            statut      enum('en_attente','partiel','solde') DEFAULT 'en_attente',
            note        varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY eleve_saison (eleve_id, saison)
        ) $charset
    ");

    // Table des paiements
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sp_cal_cotisation_paiements (
            id             mediumint NOT NULL AUTO_INCREMENT,
            cotisation_id  mediumint NOT NULL,
            date_paiement  date NOT NULL,
            montant        decimal(8,2) NOT NULL DEFAULT 0,
            mode           enum('cheque','virement','helloasso','autre','espece','cheque_ancv','carte_bancaire','pass_sport') NOT NULL,
            reference      varchar(100) NOT NULL DEFAULT '',
            note           varchar(255) NOT NULL DEFAULT '',
            saisi_par      varchar(100) NOT NULL DEFAULT '',
            recu_num       varchar(20)  NOT NULL DEFAULT '',
            date_depot_prevue date NULL DEFAULT NULL,
            date_depot_reelle date NULL DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset
    ");

    // Ajoute 'pass_sport' a l'enum "mode" sur les sites deja installes avant le 11/09/2026
    // (CREATE TABLE IF NOT EXISTS ci-dessus ne touche pas une table existante).
    $wpdb->query("
        ALTER TABLE {$wpdb->prefix}sp_cal_cotisation_paiements
        MODIFY mode enum('cheque','virement','helloasso','autre','espece','cheque_ancv','carte_bancaire','pass_sport') NOT NULL
    ");

    // Ajoute les colonnes de suivi des depots de cheques sur les sites deja installes
    // avant le 24/09/2026 (CREATE TABLE IF NOT EXISTS ci-dessus ne touche pas une table
    // existante) — gere via SHOW COLUMNS pour eviter une ALTER en double a chaque
    // chargement (contrairement au MODIFY ci-dessus, un ADD COLUMN repete echoue).
    $table_paiements = $wpdb->prefix . 'sp_cal_cotisation_paiements';
    if ( empty( $wpdb->get_col( "SHOW COLUMNS FROM {$table_paiements} LIKE 'date_depot_prevue'" ) ) ) {
        $wpdb->query( "ALTER TABLE {$table_paiements} ADD COLUMN date_depot_prevue date NULL DEFAULT NULL AFTER reference" );
    }
    if ( empty( $wpdb->get_col( "SHOW COLUMNS FROM {$table_paiements} LIKE 'date_depot_reelle'" ) ) ) {
        $wpdb->query( "ALTER TABLE {$table_paiements} ADD COLUMN date_depot_reelle date NULL DEFAULT NULL AFTER date_depot_prevue" );
    }
}
// Activation propre via hook plugin
register_activation_hook( __FILE__, 'tkd_cotisations_install' );
// Sécurité : créer les tables si manquantes (mise à jour sans réactivation)
add_action( 'plugins_loaded', 'tkd_cotisations_install' );

// ============================================================
// 2. HELPERS
// ============================================================

function tkd_get_saison_courante() {
    return get_option( 'tkd_saison_courante', '2026/2027' );
}

/**
 * Calcule le libellé de la saison suivante à partir d'une saison au format
 * "AAAA/AAAA" (ex: "2026/2027" → "2027/2028"). Si le format est inattendu,
 * incrémente le premier nombre à 4 chiffres trouvé ; sinon renvoie la saison
 * telle quelle.
 */
function tkd_cot_saison_suivante( $saison ) {
    if ( preg_match( '/^(\d{4})\/(\d{4})$/', trim( $saison ), $m ) ) {
        return ( (int) $m[1] + 1 ) . '/' . ( (int) $m[2] + 1 );
    }
    if ( preg_match( '/(\d{4})/', $saison, $m ) ) {
        return str_replace( $m[1], (string) ( (int) $m[1] + 1 ), $saison );
    }
    return $saison;
}

function tkd_get_tarifs( $saison = null ) {
    global $wpdb;
    $saison = $saison ?? tkd_get_saison_courante();
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sp_cal_cotisation_tarifs WHERE saison = %s ORDER BY libelle",
            $saison
        )
    );
}

function tkd_get_cotisation_eleve( $eleve_id, $saison = null ) {
    global $wpdb;
    $saison = $saison ?? tkd_get_saison_courante();
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sp_cal_cotisations WHERE eleve_id = %d AND saison = %s",
            $eleve_id, $saison
        )
    );
}

function tkd_get_paiements( $cotisation_id ) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sp_cal_cotisation_paiements WHERE cotisation_id = %d ORDER BY date_paiement",
            $cotisation_id
        )
    );
}

function tkd_recalcule_statut( $cotisation_id ) {
    global $wpdb;
    $cotis = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sp_cal_cotisations WHERE id = %d", $cotisation_id
    ));
    if ( ! $cotis ) return;

    $total_paye = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(montant) FROM {$wpdb->prefix}sp_cal_cotisation_paiements WHERE cotisation_id = %d",
        $cotisation_id
    ));

    $statut = 'en_attente';
    if ( $total_paye >= (float) $cotis->montant_du ) {
        $statut = 'solde';
    } elseif ( $total_paye > 0 ) {
        $statut = 'partiel';
    }

    $wpdb->update(
        $wpdb->prefix . 'sp_cal_cotisations',
        [ 'statut' => $statut ],
        [ 'id' => $cotisation_id ]
    );
}

// ============================================================
// 2bis. GESTION DES ACCÈS — visibilité du menu par utilisateur
// ============================================================
// Même principe que le module Trésorerie (sp-compta / Capabilities.php) :
// une capacité WP dédiée, accordée automatiquement au rôle administrateur,
// et en plus (jamais à la place) aux comptes cochés dans Paramètres.

define( 'TKD_COT_CAP', 'tkd_cotisations_manager' );

add_action( 'admin_init', 'tkd_cot_cap_register' );
function tkd_cot_cap_register() {
    $administrateur = get_role( 'administrator' );
    if ( $administrateur && ! $administrateur->has_cap( TKD_COT_CAP ) ) {
        $administrateur->add_cap( TKD_COT_CAP );
    }
}

function tkd_cot_bureau_users() {
    $ids = get_option( 'tkd_cotisations_bureau_users', [] );
    return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
}

/**
 * Accorde la capacité directement aux utilisateurs listés (quel que soit
 * leur rôle) et la retire à ceux précédemment listés qui ne le sont plus.
 * N'affecte jamais l'octroi via le rôle administrateur fait par
 * tkd_cot_cap_register() — un administrateur garde son accès même s'il
 * n'est pas dans cette liste.
 */
function tkd_cot_sync_bureau_users( array $user_ids ) {
    $user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );
    $previous = tkd_cot_bureau_users();

    foreach ( array_diff( $previous, $user_ids ) as $removed_id ) {
        $user = get_user_by( 'id', $removed_id );
        if ( $user ) $user->remove_cap( TKD_COT_CAP );
    }
    foreach ( $user_ids as $added_id ) {
        $user = get_user_by( 'id', $added_id );
        if ( $user ) $user->add_cap( TKD_COT_CAP );
    }

    update_option( 'tkd_cotisations_bureau_users', $user_ids );
}

add_action( 'admin_post_tkd_save_acces_bureau', 'tkd_save_acces_bureau' );
function tkd_save_acces_bureau() {
    check_admin_referer( 'tkd_acces_bureau_nonce' );
    if ( ! current_user_can( TKD_COT_CAP ) ) wp_die( 'Accès refusé' );

    $ids = array_map( 'intval', $_POST['bureau_users'] ?? [] );
    tkd_cot_sync_bureau_users( $ids );

    wp_redirect( add_query_arg( [
        'page'                => 'tkd-cotisations-params',
        'acces_bureau_saved'  => 1,
    ], admin_url( 'admin.php' ) ) );
    exit;
}

// ============================================================
// 3. MENU ADMIN
// ============================================================

add_action( 'admin_menu', function() {
    add_menu_page(
        'Cotisations', 'Cotisations', TKD_COT_CAP,
        'tkd-cotisations', 'tkd_page_cotisations_global',
        'dashicons-money-alt', 26
    );
    add_submenu_page(
        'tkd-cotisations', 'Vue globale', 'Vue globale',
        TKD_COT_CAP, 'tkd-cotisations', 'tkd_page_cotisations_global'
    );
    add_submenu_page(
        'tkd-cotisations', 'Initialiser la saison', 'Initialiser la saison',
        TKD_COT_CAP, 'tkd-cotisations-init', 'tkd_page_init_saison'
    );
    add_submenu_page(
        'tkd-cotisations', 'Gérer les tarifs', 'Gérer les tarifs',
        TKD_COT_CAP, 'tkd-cotisations-tarifs', 'tkd_page_tarifs'
    );
    add_submenu_page(
        'tkd-cotisations', 'Paramètres', 'Paramètres',
        TKD_COT_CAP, 'tkd-cotisations-params', 'tkd_page_params'
    );
});

// ============================================================
// 4. PAGE TARIFS
// ============================================================

function tkd_page_tarifs() {
    global $wpdb;
    $saison = tkd_get_saison_courante();
    // 5 catégories officielles (cohérent avec sp_build depuis le 18/09/2026) — "RENFO" est un
    // code de discipline, pas une catégorie d'âge : les élèves en Renforcement musculaire ont
    // "Tout âge" (non subdivisé par âge, contrairement au Taekwondo).
    $cats   = [ 'Baby', 'Enfant', 'Ado/adulte', 'Adulte', 'Tout âge' ];

    // Sauvegarde
    if ( isset( $_POST['save_tarifs'] ) ) {
        check_admin_referer( 'tkd_tarifs_nonce' );
        // Supprimer les tarifs existants de la saison et réinsérer
        $wpdb->delete( $wpdb->prefix . 'sp_cal_cotisation_tarifs', [ 'saison' => $saison ] );
        if ( ! empty( $_POST['tarifs'] ) ) {
            foreach ( $_POST['tarifs'] as $t ) {
                if ( empty( $t['libelle'] ) ) continue;
                $wpdb->insert( $wpdb->prefix . 'sp_cal_cotisation_tarifs', [
                    'saison'     => $saison,
                    'libelle'    => sanitize_text_field( $t['libelle'] ),
                    'montant'    => floatval( $t['montant'] ),
                    'categories' => sanitize_text_field( implode( ',', $t['categories'] ?? [] ) ),
                ]);
            }
        }
        echo '<div class="updated"><p>Tarifs enregistrés.</p></div>';
    }

    $tarifs = tkd_get_tarifs( $saison );
    ?>
    <div class="wrap">
        <h1>Gérer les tarifs — <?php echo esc_html( $saison ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'tkd_tarifs_nonce' ); ?>
            <table class="widefat" id="tkd-tarifs-table">
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Montant (€)</th>
                        <th>Catégories concernées</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rows = ! empty( $tarifs ) ? $tarifs : [ (object)[ 'libelle'=>'', 'montant'=>'', 'categories'=>'' ] ];
                foreach ( $rows as $i => $t ) :
                    $selected_cats = explode( ',', $t->categories );
                ?>
                    <tr class="tkd-tarif-row">
                        <td><input type="text" name="tarifs[<?php echo $i; ?>][libelle]" value="<?php echo esc_attr( $t->libelle ); ?>" style="width:100%;" placeholder="ex: Cotisation Baby"></td>
                        <td><input type="number" step="0.01" name="tarifs[<?php echo $i; ?>][montant]" value="<?php echo esc_attr( $t->montant ); ?>" style="width:90px;"> €</td>
                        <td>
                            <?php foreach ( $cats as $c ) : ?>
                                <label style="margin-right:10px;">
                                    <input type="checkbox" name="tarifs[<?php echo $i; ?>][categories][]" value="<?php echo $c; ?>"
                                        <?php checked( in_array( $c, $selected_cats ) ); ?>>
                                    <?php echo $c; ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                        <td><button type="button" class="button" style="color:red;" onclick="this.closest('tr').remove()">Supprimer</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button" id="tkd-add-tarif">+ Ajouter un tarif</button>
                <input type="submit" name="save_tarifs" class="button button-primary" value="Enregistrer les tarifs" style="margin-left:20px;">
            </p>
        </form>
    </div>
    <script>
    document.getElementById('tkd-add-tarif').addEventListener('click', function() {
        const tbody = document.querySelector('#tkd-tarifs-table tbody');
        const i = tbody.querySelectorAll('tr').length;
        const cats = ['Baby','Enfant','Ado\/adulte','Adulte','Tout âge'];
        let catHTML = cats.map(c => `<label style="margin-right:10px;"><input type="checkbox" name="tarifs[${i}][categories][]" value="${c}"> ${c}</label>`).join('');
        const row = `<tr class="tkd-tarif-row">
            <td><input type="text" name="tarifs[${i}][libelle]" style="width:100%;" placeholder="ex: Cotisation Baby"></td>
            <td><input type="number" step="0.01" name="tarifs[${i}][montant]" style="width:90px;"> €</td>
            <td>${catHTML}</td>
            <td><button type="button" class="button" style="color:red;" onclick="this.closest('tr').remove()">Supprimer</button></td>
        </tr>`;
        tbody.insertAdjacentHTML('beforeend', row);
    });
    </script>
    <?php
}

// ============================================================
// 5. PAGE INITIALISATION SAISON
// ============================================================

function tkd_page_init_saison() {
    global $wpdb;
    $saison = tkd_get_saison_courante();
    $tarifs = tkd_get_tarifs( $saison );

    // Traitement POST — appliquer tarif
    if ( isset( $_POST['appliquer_tarif'] ) ) {
        check_admin_referer( 'tkd_init_nonce' );
        $tarif_id  = intval( $_POST['tarif_id'] );
        $eleve_ids = array_map( 'intval', $_POST['eleve_ids'] ?? [] );
        $tarif     = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sp_cal_cotisation_tarifs WHERE id = %d", $tarif_id
        ));
        if ( $tarif && ! empty( $eleve_ids ) ) {
            foreach ( $eleve_ids as $eid ) {
                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}sp_cal_cotisations WHERE eleve_id = %d AND saison = %s",
                    $eid, $saison
                ));
                if ( $existing ) {
                    $wpdb->update( $wpdb->prefix . 'sp_cal_cotisations',
                        [ 'tarif_id' => $tarif->id, 'montant_du' => $tarif->montant, 'statut' => 'en_attente' ],
                        [ 'id' => $existing ]
                    );
                } else {
                    $wpdb->insert( $wpdb->prefix . 'sp_cal_cotisations', [
                        'eleve_id'   => $eid,
                        'saison'     => $saison,
                        'tarif_id'   => $tarif->id,
                        'montant_du' => $tarif->montant,
                        'statut'     => 'en_attente',
                    ]);
                }
            }
            echo '<div class="updated"><p><strong>' . count($eleve_ids) . ' élève(s)</strong> mis à jour avec le tarif "' . esc_html($tarif->libelle) . '".</p></div>';
        }
    }

    // Récupérer tous les élèves actifs de la saison courante avec leur cotisation actuelle.
    // Le filtre e.saison exclut les non-renouvelés restés actifs=1 sur l'ancienne saison, en
    // attendant la désactivation manuelle par le bureau (cf. même correctif sur la Vue globale).
    // Une saison vide (fiche ajoutée/modifiée manuellement côté sp_build sans ce champ rempli —
    // ce n'est qu'un placeholder visuel, pas une valeur par défaut) n'est pas traitée comme une
    // ancienne saison : seule une AUTRE saison explicite exclut la fiche.
    $eleves = $wpdb->get_results( $wpdb->prepare(
        "SELECT e.id, e.nom, e.prenom, e.categorie_age,
                c.montant_du, c.statut
         FROM {$wpdb->prefix}sp_cal_eleves e
         LEFT JOIN {$wpdb->prefix}sp_cal_cotisations c ON c.eleve_id = e.id AND c.saison = %s
         WHERE e.actif = 1 AND (e.saison = %s OR e.saison = '' OR e.saison IS NULL)
         ORDER BY e.categorie_age, e.nom, e.prenom",
        $saison, $saison
    ) );

    // Préparer les données JS pour le filtrage client
    $eleves_js = [];
    foreach ( $eleves as $e ) {
        $eleves_js[] = [
            'id'     => $e->id,
            'nom'    => $e->nom . ' ' . $e->prenom,
            'cat'    => $e->categorie_age,
            'statut' => $e->statut ?: 'non_init',
            'montant'=> $e->montant_du ? number_format((float)$e->montant_du, 2) . ' €' : '—',
        ];
    }

    if ( isset($_GET['rappel']) ) {
        echo '<div class="updated"><p>' . intval($_GET['rappel']) . ' email(s) de rappel envoyé(s).</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Initialisation saison — <?php echo esc_html($saison); ?></h1>

        <?php if ( empty($tarifs) ) : ?>
            <div class="notice notice-warning"><p>Aucun tarif défini. <a href="?page=tkd-cotisations-tarifs">Créer les tarifs →</a></p></div>
        <?php else : ?>

        <style>
        .tkd-init-bar {
            background:#fff; border:1px solid #2271b1; border-radius:8px;
            padding:14px 18px; display:flex; align-items:center; gap:12px;
            flex-wrap:wrap; margin-bottom:16px;
        }
        .tkd-init-bar select, .tkd-init-bar input[type=text] {
            padding:6px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px;
        }
        .tkd-init-bar label { font-weight:600; font-size:13px; }
        .tkd-init-count { margin-left:auto; font-size:13px; color:#666; }
        #tkd-eleves-table { border-collapse:collapse; width:100%; }
        #tkd-eleves-table th { background:#f1f1f1; padding:8px 12px; text-align:left; }
        #tkd-eleves-table td { padding:8px 12px; border-bottom:1px solid #f5f5f5; font-size:13px; }
        #tkd-eleves-table tr.hidden-row { display:none; }
        </style>

        <form method="post" id="tkd-init-form">
            <?php wp_nonce_field('tkd_init_nonce'); ?>

            <div class="tkd-init-bar">
                <label>Filtrer :
                    <select id="tkd-filtre-cat">
                        <option value="">Toutes catégories</option>
                        <?php foreach (['Baby','Enfant','Ado/adulte','Adulte','Tout âge'] as $c): ?>
                        <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <input type="checkbox" id="tkd-select-all"> <strong>Tout sélectionner</strong>
                </label>
                <span id="tkd-nb-selectionnes" style="font-size:13px; color:#2271b1; font-weight:600;">0 sélectionné(s)</span>
                <span class="tkd-init-count" id="tkd-nb-affiches"></span>
            </div>

            <div class="tkd-init-bar" style="background:#f0f6fc;">
                <label>Tarif à appliquer :
                    <select name="tarif_id" id="tkd-tarif-select">
                        <?php foreach ($tarifs as $t): ?>
                        <option value="<?php echo $t->id; ?>">
                            <?php echo esc_html($t->libelle . ' — ' . number_format($t->montant,2) . ' €'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" name="appliquer_tarif" class="button button-primary"
                        onclick="return tkdConfirmApply()">
                    ✅ Appliquer aux sélectionnés
                </button>
                <button type="button" class="button" onclick="tkdEnvoyerRappel()"
                        style="margin-left:8px;">
                    📧 Envoyer rappel aux sélectionnés
                </button>
            </div>

            <div style="background:#fff; border:1px solid #ddd; border-radius:8px; overflow:hidden;">
                <table id="tkd-eleves-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Catégorie</th>
                            <th>Cotisation actuelle</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $statut_colors = ['en_attente'=>'#dc3232','partiel'=>'#f0a500','solde'=>'#46b450','non_init'=>'#999'];
                    $statut_labels = ['en_attente'=>'🔴 En attente','partiel'=>'🟡 Partiel','solde'=>'🟢 Soldé','non_init'=>'—'];
                    foreach ($eleves as $e):
                        $st  = $e->statut ?: 'non_init';
                        $col = $statut_colors[$st] ?? '#999';
                        $lbl = $e->montant_du ? ($statut_labels[$st] . ' — ' . number_format((float)$e->montant_du,2) . ' €') : '—';
                    ?>
                    <tr data-cat="<?php echo esc_attr($e->categorie_age); ?>">
                        <td><input type="checkbox" class="tkd-eleve-cb" name="eleve_ids[]" value="<?php echo $e->id; ?>"></td>
                        <td><?php echo esc_html($e->nom); ?></td>
                        <td><?php echo esc_html($e->prenom); ?></td>
                        <td><?php echo esc_html($e->categorie_age); ?></td>
                        <td style="color:<?php echo $col; ?>; font-weight:600;"><?php echo esc_html($lbl); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="tkd-init-bar" style="background:#f0f6fc; margin-top:16px;">
                <label>Tarif :
                    <select id="tkd-tarif-select-bas" onchange="document.getElementById('tkd-tarif-select').value=this.value">
                        <?php foreach ($tarifs as $t): ?>
                        <option value="<?php echo $t->id; ?>">
                            <?php echo esc_html($t->libelle . ' — ' . number_format($t->montant,2) . ' €'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" name="appliquer_tarif" class="button button-primary"
                        onclick="document.getElementById('tkd-tarif-select').value=document.getElementById('tkd-tarif-select-bas').value; return tkdConfirmApply()">
                    ✅ Appliquer aux sélectionnés
                </button>
                <button type="button" class="button" onclick="tkdEnvoyerRappel()">
                    📧 Envoyer rappel aux sélectionnés
                </button>
            </div>

        </form>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="tkd-rappel-form">
            <?php wp_nonce_field('tkd_rappel_nonce'); ?>
            <input type="hidden" name="action" value="tkd_envoyer_rappel">
            <div id="tkd-rappel-ids-container"></div>
        </form>

        <?php endif; ?>
    </div>

    <script>
    (function() {
        var rows   = document.querySelectorAll('#tkd-eleves-table tbody tr');
        var selAll = document.getElementById('tkd-select-all');
        var filtre = document.getElementById('tkd-filtre-cat');
        var nbSel  = document.getElementById('tkd-nb-selectionnes');
        var nbAff  = document.getElementById('tkd-nb-affiches');

        function updateCounts() {
            var visible  = 0;
            var selected = 0;
            rows.forEach(function(r) {
                if (!r.classList.contains('hidden-row')) visible++;
                if (r.querySelector('.tkd-eleve-cb').checked) selected++;
            });
            nbSel.textContent = selected + ' sélectionné(s)';
            nbAff.textContent = visible + ' élève(s) affichés';
        }

        // Filtre catégorie — sans rechargement
        filtre.addEventListener('change', function() {
            var cat = this.value;
            rows.forEach(function(r) {
                var match = !cat || r.dataset.cat === cat;
                r.classList.toggle('hidden-row', !match);
            });
            selAll.checked = false;
            updateCounts();
        });

        // Tout sélectionner (uniquement les visibles)
        selAll.addEventListener('change', function() {
            rows.forEach(function(r) {
                if (!r.classList.contains('hidden-row')) {
                    r.querySelector('.tkd-eleve-cb').checked = selAll.checked;
                }
            });
            updateCounts();
        });

        // Mise à jour compteur à chaque clic checkbox
        document.querySelectorAll('.tkd-eleve-cb').forEach(function(cb) {
            cb.addEventListener('change', updateCounts);
        });

        updateCounts();
    })();

    function tkdConfirmApply() {
        var n = document.querySelectorAll('.tkd-eleve-cb:checked').length;
        if (n === 0) { alert('Aucun élève sélectionné.'); return false; }
        return confirm(n + ' élève(s) sélectionné(s). Appliquer le tarif ?');
    }

    function tkdEnvoyerRappel() {
        var ids = [];
        document.querySelectorAll('.tkd-eleve-cb:checked').forEach(function(cb) { ids.push(cb.value); });
        if (ids.length === 0) { alert('Aucun élève sélectionné.'); return; }
        if (!confirm(ids.length + ' email(s) de rappel à envoyer. Confirmer ?')) return;
        var container = document.getElementById('tkd-rappel-ids-container');
        container.innerHTML = '';
        ids.forEach(function(id) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'eleve_ids[]'; inp.value = id;
            container.appendChild(inp);
        });
        document.getElementById('tkd-rappel-form').submit();
    }
    </script>
    <?php
}


// ============================================================
// 6. PAGE VUE GLOBALE
// ============================================================

function tkd_page_cotisations_global() {
    global $wpdb;
    $saison_courante = tkd_get_saison_courante();
    $filtre_saison   = isset( $_GET['saison'] ) && $_GET['saison'] !== '' ? sanitize_text_field( wp_unslash( $_GET['saison'] ) ) : $saison_courante;
    $filtre_cat      = isset( $_GET['cat'] )    ? sanitize_text_field( $_GET['cat'] )    : '';
    $filtre_st       = isset( $_GET['statut'] ) ? sanitize_text_field( $_GET['statut'] ) : '';

    // Saisons connues = celles ayant deja des cotisations, plus la saison courante meme si vide
    $saisons_connues = $wpdb->get_col( "SELECT DISTINCT saison FROM {$wpdb->prefix}sp_cal_cotisations WHERE saison != '' ORDER BY saison DESC" );
    if ( ! in_array( $saison_courante, $saisons_connues, true ) ) {
        array_unshift( $saisons_connues, $saison_courante );
    }

    $where = "WHERE e.actif = 1";
    // Sur la saison en cours, un adherent qui n'a pas encore renouvele (fiche restee sur l'ancienne
    // saison cote sp_build) ne doit pas apparaitre comme s'il etait deja inscrit cette saison-ci —
    // meme s'il n'a pas encore ete desactive manuellement par le bureau (etape separee et volontaire,
    // cf. class-renouvellement.php). Sur une saison passee (lecture seule), on garde l'ancien
    // comportement : on affiche les actifs actuels tels quels, la fiche ne conservant pas l'historique
    // complet des saisons traversees.
    // Le champ e.saison n'a pas de valeur par defaut sur la fiche sp_build quand un adherent est
    // ajoute/modifie manuellement (juste un placeholder visuel, pas une vraie valeur — cf.
    // class-admin-members.php:507) : une fiche avec saison vide n'est donc pas forcement un
    // non-renouvele, elle peut juste ne jamais avoir ete renseignee. On l'exclut seulement si elle
    // porte une AUTRE saison explicite (signe qu'elle vient reellement de l'ancienne saison).
    if ( $filtre_saison === $saison_courante ) {
        $where .= $wpdb->prepare( " AND (e.saison = %s OR e.saison = '' OR e.saison IS NULL)", $filtre_saison );
    }
    if ( $filtre_cat ) $where .= $wpdb->prepare( " AND e.categorie_age = %s", $filtre_cat );
    $saison_clause = $wpdb->prepare( "c.saison = %s", $filtre_saison );

    $eleves = $wpdb->get_results( "
        SELECT e.id, e.nom, e.prenom, e.categorie_age,
               c.id AS cotis_id, c.montant_du, c.statut,
               COALESCE((SELECT SUM(p.montant) FROM {$wpdb->prefix}sp_cal_cotisation_paiements p WHERE p.cotisation_id = c.id), 0) AS total_paye
        FROM {$wpdb->prefix}sp_cal_eleves e
        LEFT JOIN {$wpdb->prefix}sp_cal_cotisations c ON c.eleve_id = e.id AND $saison_clause
        $where
        ORDER BY e.categorie_age, e.nom, e.prenom
    ");

    // Filtrer par statut côté PHP
    if ( $filtre_st ) {
        $eleves = array_filter( $eleves, fn($e) => ( $e->statut ?? 'non_init' ) === $filtre_st );
    }

    $statut_labels = [ 'en_attente' => '🔴 En attente', 'partiel' => '🟡 Partiel', 'solde' => '🟢 Soldé', 'non_init' => '⚫ Non initialisé' ];
    $statut_colors = [ 'en_attente' => '#dc3232', 'partiel' => '#f0a500', 'solde' => '#46b450', 'non_init' => '#999' ];
    ?>
    <div class="wrap">
        <h1>Cotisations — <?php echo esc_html( $filtre_saison ); ?></h1>

        <div style="margin-bottom:15px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <label>Saison :
                <select onchange="window.location='?page=tkd-cotisations&saison='+encodeURIComponent(this.value)+'&cat=<?php echo esc_js( $filtre_cat ); ?>&statut=<?php echo esc_js( $filtre_st ); ?>'">
                    <?php foreach ( $saisons_connues as $s ) : ?>
                        <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filtre_saison, $s ); ?>><?php echo esc_html( $s ); ?><?php echo $s === $saison_courante ? ' (courante)' : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Catégorie :
                <select onchange="window.location='?page=tkd-cotisations&saison=<?php echo esc_js( $filtre_saison ); ?>&cat='+this.value+'&statut=<?php echo esc_js( $filtre_st ); ?>'">
                    <option value="">Toutes</option>
                    <?php foreach ( [ 'Baby', 'Enfant', 'Ado/adulte', 'Adulte', 'Tout âge' ] as $c ) : ?>
                        <option value="<?php echo esc_attr($c); ?>" <?php selected( $filtre_cat, $c ); ?>><?php echo esc_html($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Statut :
                <select onchange="window.location='?page=tkd-cotisations&saison=<?php echo esc_js( $filtre_saison ); ?>&cat=<?php echo esc_js( $filtre_cat ); ?>&statut='+this.value">
                    <option value="">Tous</option>
                    <?php foreach ( $statut_labels as $k => $v ) : ?>
                        <option value="<?php echo $k; ?>" <?php selected( $filtre_st, $k ); ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php if ( $filtre_saison !== $saison_courante ) : ?>
        <div class="notice notice-info inline"><p>
            Vous consultez une saison passée (<strong><?php echo esc_html( $filtre_saison ); ?></strong>), en lecture. Pour saisir un paiement sur la saison en cours, repassez sur <strong><?php echo esc_html( $saison_courante ); ?></strong>.
        </p></div>
        <?php endif; ?>

        <p style="font-size:13px; color:#666;">
            <?php echo count( $eleves ); ?> adhérent<?php echo count( $eleves ) > 1 ? 's' : ''; ?> affiché<?php echo count( $eleves ) > 1 ? 's' : ''; ?>
        </p>

        <table class="widefat" id="tkd-cotis-table">
            <thead><tr>
                <th id="tkd-sort-nom" style="cursor:pointer;user-select:none;" title="Trier par ordre alphabétique">Nom <span id="tkd-sort-nom-ico" style="color:#9ca3af;">↕</span></th>
                <th>Prénom</th><th>Catégorie</th><th>Dû</th><th>Payé</th><th>Reste</th><th>Statut</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $eleves as $e ) :
                $statut    = $e->statut ?? 'non_init';
                $reste     = max( 0, (float)$e->montant_du - (float)$e->total_paye );
                $color     = $statut_colors[ $statut ] ?? '#999';
                $label     = $statut_labels[ $statut ] ?? '⚫';
            ?>
                <tr data-name="<?php echo esc_attr( mb_strtolower( $e->nom . ' ' . $e->prenom ) ); ?>">
                    <td><?php echo esc_html( $e->nom ); ?></td>
                    <td><?php echo esc_html( $e->prenom ); ?></td>
                    <td><?php echo esc_html( $e->categorie_age ); ?></td>
                    <td><?php echo $e->montant_du ? number_format( $e->montant_du, 2 ) . ' €' : '—'; ?></td>
                    <td><?php echo $e->cotis_id ? number_format( $e->total_paye, 2 ) . ' €' : '—'; ?></td>
                    <td><?php echo $e->cotis_id ? number_format( $reste, 2 ) . ' €' : '—'; ?></td>
                    <td style="color:<?php echo $color; ?>; font-weight:bold;"><?php echo $label; ?></td>
                    <td>
                        <?php if ( $filtre_saison === $saison_courante ) : ?>
                        <a href="?page=tkd-cotisations&action=fiche&eleve_id=<?php echo $e->id; ?>" class="button button-small">Gérer</a>
                        <?php else : ?>
                        <span style="color:#999; font-size:12px;">Lecture seule</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    (function(){
        var th  = document.getElementById('tkd-sort-nom');
        var ico = document.getElementById('tkd-sort-nom-ico');
        if (!th) return;
        var dir = null; // null = ordre serveur (catégorie puis nom), 'asc', 'desc'
        th.addEventListener('click', function(){
            dir = dir === 'asc' ? 'desc' : 'asc';
            ico.textContent = dir === 'asc' ? '▲' : '▼';
            var tbody = document.querySelector('#tkd-cotis-table tbody');
            var rows  = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            rows.sort(function(a, b){
                var cmp = a.dataset.name.localeCompare(b.dataset.name, 'fr');
                return dir === 'asc' ? cmp : -cmp;
            });
            rows.forEach(function(r){ tbody.appendChild(r); });
        });
    })();
    </script>
    <?php
}

// ============================================================
// 7. PARAMÈTRES
// ============================================================

function tkd_page_params() {
    if ( isset( $_POST['save_params'] ) ) {
        check_admin_referer( 'tkd_params_nonce' );
        if ( ! current_user_can( TKD_COT_CAP ) ) wp_die( 'Accès refusé' );

        update_option( 'tkd_saison_courante',   sanitize_text_field( $_POST['saison'] ) );
        update_option( 'tkd_date_fin_saison',   sanitize_text_field( $_POST['date_fin_saison'] ?? '' ) );

        update_option( 'tkd_helloasso_url',   esc_url_raw( $_POST['helloasso_url'] ) );
        update_option( 'tkd_virement_iban',   sanitize_text_field( $_POST['iban'] ) );
        update_option( 'tkd_cheque_ordre',    sanitize_text_field( $_POST['cheque_ordre'] ) );
        echo '<div class="updated"><p>Paramètres enregistrés.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Paramètres cotisations</h1>
        <form method="post">
            <?php wp_nonce_field( 'tkd_params_nonce' ); ?>
            <table class="form-table">
                <tr><th>Saison courante</th><td><input type="text" name="saison" value="<?php echo esc_attr( get_option('tkd_saison_courante','2026/2027') ); ?>" placeholder="2026/2027"></td></tr>
                <tr>
                    <th>Date de fin de saison</th>
                    <td>
                        <input type="date" name="date_fin_saison" value="<?php echo esc_attr( get_option('tkd_date_fin_saison','') ); ?>">
                        <p class="description">Sert de repère : passé cette date, un rappel s'affiche ci-dessous pour clôturer la saison et en démarrer une nouvelle.</p>
                    </td>
                </tr>

                <tr><th>Lien HelloAsso</th><td><input type="url" name="helloasso_url" value="<?php echo esc_attr( get_option('tkd_helloasso_url','') ); ?>" style="width:400px;" placeholder="https://www.helloasso.com/..."></td></tr>
                <tr><th>IBAN virement</th><td><input type="text" name="iban" value="<?php echo esc_attr( get_option('tkd_virement_iban','') ); ?>" style="width:300px;"></td></tr>
                <tr><th>Chèque à l'ordre de</th><td><input type="text" name="cheque_ordre" value="<?php echo esc_attr( get_option('tkd_cheque_ordre','') ); ?>"></td></tr>
            </table>
            <input type="submit" name="save_params" class="button button-primary" value="Enregistrer">
        </form>

        <hr style="margin:30px 0;">

        <h2>Clôture de saison</h2>
        <?php
        $saison_actuelle = tkd_get_saison_courante();
        $saison_suivante = tkd_cot_saison_suivante( $saison_actuelle );
        $date_fin        = get_option( 'tkd_date_fin_saison', '' );
        ?>
        <p style="color:#666; font-size:13px;">
            Clôturer la saison fait passer la <strong>saison courante</strong> de <strong><?php echo esc_html($saison_actuelle); ?></strong>
            à <strong><?php echo esc_html($saison_suivante); ?></strong>. Les cotisations et paiements de
            <?php echo esc_html($saison_actuelle); ?> restent conservés (historique, reçus), mais
            <strong>chaque adhérent repart à zéro</strong> sur la nouvelle saison : aucune cotisation n'est
            initialisée tant que vous n'utilisez pas « Initialiser la saison » pour lui appliquer un tarif.
        </p>
        <?php if ( $date_fin && strtotime( $date_fin ) < current_time( 'timestamp' ) ) : ?>
        <div class="notice notice-warning inline"><p>
            ⚠️ La date de fin de saison (<?php echo esc_html( date_i18n('d/m/Y', strtotime($date_fin)) ); ?>) est dépassée.
            Pensez à clôturer la saison <?php echo esc_html($saison_actuelle); ?> ci-dessous.
        </p></div>
        <?php endif; ?>
        <?php if ( isset($_GET['cloture_ok']) ) : ?>
        <div class="updated"><p>
            ✅ Saison clôturée : <strong><?php echo esc_html( wp_unslash($_GET['ancienne_saison'] ?? '') ); ?></strong>
            → nouvelle saison courante <strong><?php echo esc_html( wp_unslash($_GET['nouvelle_saison'] ?? '') ); ?></strong>.
        </p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('tkd_cloture_nonce'); ?>
            <input type="hidden" name="action" value="tkd_cloturer_saison">
            <button type="submit" class="button button-secondary"
                    onclick="return confirm('Clôturer la saison <?php echo esc_js($saison_actuelle); ?> et démarrer <?php echo esc_js($saison_suivante); ?> ?\n\nLes comptes adhérents de la nouvelle saison repartiront à zéro.')">
                🔒 Clôturer <?php echo esc_html($saison_actuelle); ?> et démarrer <?php echo esc_html($saison_suivante); ?>
            </button>
        </form>

        <hr style="margin:30px 0;">

        <h2>Accès bureau</h2>
        <p style="color:#666; font-size:13px;">
            Cochez les comptes WordPress qui doivent voir le menu <strong>Cotisations</strong> dans le back-office
            et pouvoir l'utiliser, en plus des administrateurs (accès automatique, toujours conservé).
        </p>
        <?php if ( isset($_GET['acces_bureau_saved']) ) : ?>
        <div class="updated"><p>✅ Accès bureau mis à jour.</p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('tkd_acces_bureau_nonce'); ?>
            <input type="hidden" name="action" value="tkd_save_acces_bureau">
            <?php
            $bureau_ids = tkd_cot_bureau_users();
            foreach ( get_users( [ 'orderby' => 'display_name' ] ) as $u ) :
                $checked = in_array( (int) $u->ID, $bureau_ids, true ) ? ' checked' : '';
            ?>
            <label style="display:block; margin:4px 0;">
                <input type="checkbox" name="bureau_users[]" value="<?php echo esc_attr($u->ID); ?>"<?php echo $checked; ?>>
                <?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?>
            </label>
            <?php endforeach; ?>
            <p><button type="submit" class="button button-primary">Enregistrer l'accès bureau</button></p>
        </form>

        <hr style="margin:30px 0;">

        <h2>Recalcul automatique des catégories d'âge</h2>
        <p style="color:#666; font-size:13px;">
            Recalcule la catégorie d'âge (Baby / Enfant / Ado-adulte) de chaque élève actif
            en fonction de leur date de naissance au <strong>1er septembre</strong> de la saison courante.<br>
            Les élèves en <strong>RENFO</strong> sont ignorés. Vous pouvez toujours modifier manuellement sur chaque fiche.
        </p>

        <?php if ( isset($_GET['recalc_ok']) ) : ?>
        <div class="updated"><p>
            ✅ <?php echo intval($_GET['recalc_ok']); ?> élève(s) mis à jour —
            <?php echo intval($_GET['recalc_ign']); ?> inchangé(s) —
            <?php echo intval($_GET['recalc_err']); ?> ignoré(s) (date manquante).
        </p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('tkd_recalc_nonce'); ?>
            <input type="hidden" name="action" value="tkd_recalculer_categories">
            <button type="submit" class="button button-secondary"
                    onclick="return confirm('Recalculer les catégories d\'âge de tous les élèves actifs au 1er septembre <?php echo explode('/', tkd_get_saison_courante())[0]; ?> ?')">
                🔄 Recalculer les catégories d'âge
            </button>
        </form>

        <hr style="margin:20px 0;">

        <h2>Calcul éligibilité 1e Dan</h2>
        <?php
        global $wpdb;
        $dates_dan_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sp_cal_events WHERE titre LIKE '%DAN%' AND saison = %s",
            tkd_get_saison_courante()
        ) );
        if ( ! $dates_dan_count ) : ?>
            <div class="notice notice-warning inline"><p>⚠️ Aucun examen Dan trouvé dans le calendrier pour la saison <strong><?php echo esc_html(tkd_get_saison_courante()); ?></strong>. Créez d'abord les événements dans le calendrier.</p></div>
        <?php else : ?>
        <p style="color:#666; font-size:13px;">
            Calcule l'éligibilité 1e Dan pour chaque élève <strong>Ado/adulte actif</strong>
            en lisant les dates d'examen Dan depuis le calendrier.<br>
            Conditions : <strong>14 ans révolus</strong> à au moins une date d'examen Dan
            <strong>ET</strong> <strong>3 licences minimum</strong>.<br>
            La case "Éligible Dan" reste modifiable manuellement sur chaque fiche.
        </p>
        <?php if ( isset($_GET['eligibilite_ok']) ) : ?>
        <div class="updated"><p>
            ✅ <?php echo intval($_GET['eligibilite_ok']); ?> élève(s) éligibles —
            <?php echo intval($_GET['eligibilite_non']); ?> non éligibles.
        </p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('tkd_eligibilite_nonce'); ?>
            <input type="hidden" name="action" value="tkd_calculer_eligibilite_dan">
            <button type="submit" class="button button-secondary"
                    onclick="return confirm('Calculer l\'éligibilité 1e Dan pour tous les élèves Ado/adulte actifs ?')">
                🥋 Calculer les éligibilités 1e Dan
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php
}

// ============================================================
// 8. FICHE INDIVIDUELLE — SAISIE PAIEMENTS
// ============================================================

// Initialisation individuelle depuis la fiche (cas "non initialisé" : évite de repasser
// par l'écran d'Initialisation en masse pour un seul élève).
add_action( 'wp_ajax_tkd_initialiser_cotisation_eleve', 'tkd_ajax_initialiser_cotisation_eleve' );
function tkd_ajax_initialiser_cotisation_eleve() {
    check_ajax_referer( 'tkd_paiement_nonce', 'nonce' );
    if ( ! current_user_can( TKD_COT_CAP ) ) wp_die( 'Accès refusé' );

    global $wpdb;
    $eleve_id = intval( $_POST['eleve_id'] );
    $tarif_id = intval( $_POST['tarif_id'] );
    $saison   = tkd_get_saison_courante();

    $tarif = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sp_cal_cotisation_tarifs WHERE id = %d", $tarif_id
    ));
    if ( ! $tarif ) wp_send_json_error( 'Tarif introuvable' );

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sp_cal_cotisations WHERE eleve_id = %d AND saison = %s",
        $eleve_id, $saison
    ));
    if ( $existing ) wp_send_json_error( 'Une cotisation existe déjà pour cet élève sur cette saison.' );

    $wpdb->insert( $wpdb->prefix . 'sp_cal_cotisations', [
        'eleve_id'   => $eleve_id,
        'saison'     => $saison,
        'tarif_id'   => $tarif->id,
        'montant_du' => $tarif->montant,
        'statut'     => 'en_attente',
    ]);

    wp_send_json_success( 'Cotisation initialisée avec le tarif "' . $tarif->libelle . '".' );
}

add_action( 'wp_ajax_tkd_ajouter_paiement', 'tkd_ajax_ajouter_paiement' );
function tkd_ajax_ajouter_paiement() {
    check_ajax_referer( 'tkd_paiement_nonce', 'nonce' );
    if ( ! current_user_can( TKD_COT_CAP ) ) wp_die( 'Accès refusé' );

    global $wpdb;
    $eleve_id     = intval( $_POST['eleve_id'] );
    $saison       = tkd_get_saison_courante();
    $montant      = floatval( $_POST['montant'] );
    $mode         = sanitize_text_field( $_POST['mode'] );
    $reference    = sanitize_text_field( $_POST['reference'] ?? '' );
    $note         = sanitize_text_field( $_POST['note'] ?? '' );
    $date_paie    = sanitize_text_field( $_POST['date_paiement'] ?? date('Y-m-d') );
    // Date de depot en banque prevue : pertinente seulement pour les cheques (cf. doleance
    // paiement en plusieurs cheques echelonnes) — vide sinon.
    $date_depot_prevue = sanitize_text_field( $_POST['date_depot_prevue'] ?? '' );
    if ( ! in_array( $mode, [ 'cheque', 'cheque_ancv' ], true ) ) $date_depot_prevue = '';

    if ( $montant <= 0 ) wp_send_json_error( 'Montant invalide' );

    // Récupérer ou créer la cotisation
    $cotis = tkd_get_cotisation_eleve( $eleve_id, $saison );
    if ( ! $cotis ) wp_send_json_error( 'Cotisation non initialisée pour cet élève' );

    // Générer numéro de reçu unique : AAAA-NNN
    $annee    = date('Y');
    $last_num = intval( get_option( 'tkd_recu_last_num_' . $annee, 0 ) );
    $next_num = $last_num + 1;
    $recu_num = $annee . '-' . str_pad( $next_num, 3, '0', STR_PAD_LEFT );
    update_option( 'tkd_recu_last_num_' . $annee, $next_num );

    $wpdb->insert( $wpdb->prefix . 'sp_cal_cotisation_paiements', [
        'cotisation_id'      => $cotis->id,
        'date_paiement'      => $date_paie,
        'montant'            => $montant,
        'mode'               => $mode,
        'reference'          => $reference,
        'note'               => $note,
        'saisi_par'          => wp_get_current_user()->display_name,
        'recu_num'           => $recu_num,
        'date_depot_prevue'  => $date_depot_prevue ?: null,
    ]);
    $paiement_id = $wpdb->insert_id;

    tkd_recalcule_statut( $cotis->id );

    // Facture envoyée seulement quand la cotisation est intégralement soldée (et non plus à
    // chaque paiement) : permet un règlement échelonné en plusieurs chèques sans spammer
    // l'adhérent d'un email par dépôt — cf. doléance du 24/09/2026. Vérifié avant le cas
    // particulier Pass'Sport ci-dessous : c'est parfois justement ce dernier versement
    // (bon Pass'Sport) qui solde la cotisation.
    $cotis_a_jour  = tkd_get_cotisation_eleve( $eleve_id, $saison );
    $devient_solde = $cotis_a_jour && $cotis_a_jour->statut === 'solde';

    if ( $devient_solde ) {
        $eleve = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sp_cal_eleves WHERE id=%d", $eleve_id
        ));
        if ( $eleve ) {
            tkd_envoyer_facture_solde( $eleve, $cotis_a_jour );
        }
    }

    // Pass'Sport : paiement enregistré en interne uniquement, jamais de reçu envoyé à l'adhérent
    if ( $mode === 'pass_sport' ) {
        $msg = 'Paiement Pass\'Sport enregistré en interne — Reçu N° ' . $recu_num . ' (non envoyé à l\'adhérent).';
        if ( $devient_solde ) $msg .= ' Cotisation soldée — facture envoyée par email.';
        wp_send_json_success( $msg );
    }

    if ( $devient_solde ) {
        wp_send_json_success( 'Paiement enregistré — cotisation soldée, facture envoyée par email (Reçu N° ' . $recu_num . ').' );
    }

    wp_send_json_success( 'Paiement enregistré — Reçu N° ' . $recu_num . ' (la facture sera envoyée à l\'adhérent une fois la cotisation intégralement soldée).' );
}

add_action( 'wp_ajax_tkd_supprimer_paiement', 'tkd_ajax_supprimer_paiement' );
function tkd_ajax_supprimer_paiement() {
    check_ajax_referer( 'tkd_paiement_nonce', 'nonce' );
    if ( ! current_user_can( TKD_COT_CAP ) ) wp_die( 'Accès refusé' );

    global $wpdb;
    $paiement_id = intval( $_POST['paiement_id'] );
    $paiement    = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sp_cal_cotisation_paiements WHERE id = %d", $paiement_id
    ));
    if ( ! $paiement ) wp_send_json_error( 'Paiement introuvable' );

    $wpdb->delete( $wpdb->prefix . 'sp_cal_cotisation_paiements', [ 'id' => $paiement_id ] );
    tkd_recalcule_statut( $paiement->cotisation_id );

    wp_send_json_success( 'Paiement supprimé' );
}

add_action( 'wp_ajax_tkd_marquer_depot', 'tkd_ajax_marquer_depot' );
function tkd_ajax_marquer_depot() {
    check_ajax_referer( 'tkd_paiement_nonce', 'nonce' );
    if ( ! current_user_can( TKD_COT_CAP ) ) wp_die( 'Accès refusé' );

    global $wpdb;
    $paiement_id = intval( $_POST['paiement_id'] );
    $depose      = ! empty( $_POST['depose'] );

    $updated = $wpdb->update(
        $wpdb->prefix . 'sp_cal_cotisation_paiements',
        [ 'date_depot_reelle' => $depose ? current_time( 'Y-m-d' ) : null ],
        [ 'id' => $paiement_id ]
    );
    if ( $updated === false ) wp_send_json_error( 'Échec de la mise à jour' );

    wp_send_json_success( $depose ? 'Chèque marqué comme déposé.' : 'Chèque marqué comme non déposé.' );
}

// Page fiche individuelle (appelée depuis vue globale)
add_action( 'admin_init', function() {
    // Aperçu / impression reçu
    if ( isset($_GET['page']) && $_GET['page'] === 'tkd-cotisations'
      && isset($_GET['action']) && $_GET['action'] === 'apercu_recu'
      && isset($_GET['paiement_id']) && current_user_can(TKD_COT_CAP) ) {
        tkd_page_apercu_recu( intval($_GET['paiement_id']) );
        exit;
    }

    if ( isset($_GET['page']) && $_GET['page'] === 'tkd-cotisations'
      && isset($_GET['action']) && $_GET['action'] === 'fiche'
      && isset($_GET['eleve_id']) ) {
        add_action( 'admin_notices', 'tkd_render_fiche_cotisation' );
    }
});

function tkd_render_fiche_cotisation() {
    global $wpdb;
    $eleve_id = intval( $_GET['eleve_id'] );
    $saison   = tkd_get_saison_courante();

    $eleve = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sp_cal_eleves WHERE id = %d", $eleve_id
    ));
    if ( ! $eleve ) return;

    $cotis     = tkd_get_cotisation_eleve( $eleve_id, $saison );
    $paiements = $cotis ? tkd_get_paiements( $cotis->id ) : [];
    $total_paye = array_sum( array_column( (array)$paiements, 'montant' ) );
    $reste      = $cotis ? max( 0, (float)$cotis->montant_du - $total_paye ) : 0;
    $tarifs     = tkd_get_tarifs( $saison );

    $statut_colors = [ 'en_attente' => '#dc3232', 'partiel' => '#f0a500', 'solde' => '#46b450' ];
    $statut_labels = [ 'en_attente' => '🔴 En attente', 'partiel' => '🟡 Partiel', 'solde' => '🟢 Soldé' ];

    $helloasso_url = get_option( 'tkd_helloasso_url', 'https://www.helloasso.com/associations/taekwondo-claira/adhesions/adhesion-2026-2027-sport' );
    $iban          = get_option( 'tkd_virement_iban', '' );
    $cheque_ordre  = get_option( 'tkd_cheque_ordre', '' );
    $nonce         = wp_create_nonce( 'tkd_paiement_nonce' );
    ?>
    <style>
    .tkd-fiche { background:#fff; border:1px solid #ddd; border-radius:8px; padding:24px; margin:16px 0; }
    .tkd-fiche h2 { margin-top:0; }
    .tkd-fiche-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
    .tkd-stat-box { background:#f9f9f9; border:1px solid #eee; border-radius:6px; padding:14px; text-align:center; }
    .tkd-stat-box .val { font-size:28px; font-weight:800; }
    .tkd-stat-box .lbl { font-size:12px; color:#666; margin-top:4px; }
    .tkd-paiement-table { width:100%; border-collapse:collapse; margin-top:16px; }
    .tkd-paiement-table th { background:#f1f1f1; padding:8px 12px; text-align:left; font-size:13px; }
    .tkd-paiement-table td { padding:8px 12px; border-bottom:1px solid #f0f0f0; font-size:13px; }
    .tkd-add-form { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:8px; padding:18px; margin-top:20px; }
    .tkd-add-form h3 { margin-top:0; font-size:15px; }
    .tkd-add-form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
    .tkd-add-form label { font-size:12px; font-weight:600; display:block; margin-bottom:4px; }
    .tkd-add-form input, .tkd-add-form select { width:100%; padding:6px 8px; border:1px solid #ddd; border-radius:4px; }
    .tkd-info-box { background:#e8f4fd; border:1px solid #b8d9f0; border-radius:6px; padding:12px 16px; margin-top:16px; font-size:13px; }
    .tkd-info-box a { color:#0073aa; font-weight:600; }
    </style>

    <div class="tkd-fiche">
        <a href="?page=tkd-cotisations" style="font-size:13px;">← Retour à la vue globale</a>
        <h2 style="margin-top:12px;"><?php echo esc_html( $eleve->nom . ' ' . $eleve->prenom ); ?>
            <span style="font-size:14px; color:#666; font-weight:normal;">— <?php echo esc_html($eleve->categorie_age); ?> — Saison <?php echo esc_html($saison); ?></span>
        </h2>

        <?php if ( ! $cotis ): ?>
            <div class="notice notice-warning" style="margin:0; padding:12px 16px;">
                <p style="margin-top:0;">Aucune cotisation initialisée pour cet élève sur la saison <?php echo esc_html($saison); ?>.</p>
                <?php if ( empty( $tarifs ) ): ?>
                <p style="margin-bottom:0;">Aucun tarif n'est encore défini pour cette saison.
                <a href="?page=tkd-cotisations-tarifs">Créer les tarifs →</a></p>
                <?php else: ?>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <label style="font-weight:600; font-size:13px;">Tarif à appliquer :
                        <select id="tkd-init-tarif-select" style="padding:6px 8px; border:1px solid #ddd; border-radius:4px;">
                            <?php foreach ($tarifs as $t): ?>
                            <option value="<?php echo $t->id; ?>">
                                <?php echo esc_html($t->libelle . ' — ' . number_format($t->montant,2) . ' €'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="button button-primary" id="tkd-btn-init-cotis">Initialiser avec ce tarif</button>
                    <span id="tkd-init-msg" style="font-size:13px;"></span>
                </div>
                <p style="margin-bottom:0;"><a href="?page=tkd-cotisations-init">Ou initialiser plusieurs élèves à la fois →</a></p>
                <?php endif; ?>
            </div>
            <script>
            document.getElementById('tkd-btn-init-cotis')?.addEventListener('click', function() {
                var msg = document.getElementById('tkd-init-msg');
                msg.style.color = '';
                msg.textContent = 'Initialisation…';
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action:   'tkd_initialiser_cotisation_eleve',
                        nonce:    '<?php echo $nonce; ?>',
                        eleve_id: <?php echo $eleve_id; ?>,
                        tarif_id: document.getElementById('tkd-init-tarif-select').value,
                    })
                }).then(r => r.json()).then(r => {
                    if (r.success) { msg.style.color='green'; msg.textContent='✅ ' + r.data; setTimeout(()=>location.reload(),600); }
                    else { msg.style.color='red'; msg.textContent='❌ ' + r.data; }
                });
            });
            </script>
        <?php else: ?>

        <div class="tkd-fiche-grid">
            <div class="tkd-stat-box">
                <div class="val"><?php echo number_format( (float)$cotis->montant_du, 2 ); ?> €</div>
                <div class="lbl">Montant dû</div>
            </div>
            <div class="tkd-stat-box">
                <div class="val" style="color:#46b450;"><?php echo number_format( $total_paye, 2 ); ?> €</div>
                <div class="lbl">Payé</div>
            </div>
            <div class="tkd-stat-box">
                <div class="val" style="color:<?php echo $reste > 0 ? '#dc3232' : '#46b450'; ?>;"><?php echo number_format( $reste, 2 ); ?> €</div>
                <div class="lbl">Reste à payer</div>
            </div>
            <div class="tkd-stat-box">
                <div class="val" style="color:<?php echo $statut_colors[$cotis->statut] ?? '#666'; ?>; font-size:18px;">
                    <?php echo $statut_labels[$cotis->statut] ?? $cotis->statut; ?>
                </div>
                <div class="lbl">Statut</div>
            </div>
        </div>

        <form method="post" style="margin-bottom:16px;">
            <?php wp_nonce_field('tkd_modif_cotis'); ?>
            <input type="hidden" name="tkd_action" value="modifier_montant">
            <input type="hidden" name="eleve_id" value="<?php echo $eleve_id; ?>">
            <label style="font-size:12px; font-weight:600;">Modifier le montant dû :</label>
            <div style="display:flex; gap:8px; align-items:center; margin-top:4px;">
                <input type="number" step="0.01" name="nouveau_montant" value="<?php echo $cotis->montant_du; ?>" style="width:120px; padding:6px; border:1px solid #ddd; border-radius:4px;">
                <span>€</span>
                <select name="tarif_id" style="padding:6px; border:1px solid #ddd; border-radius:4px;">
                    <option value="">— ou choisir un tarif —</option>
                    <?php foreach ($tarifs as $t): ?>
                    <option value="<?php echo $t->id; ?>" data-montant="<?php echo $t->montant; ?>">
                        <?php echo esc_html($t->libelle . ' — ' . number_format($t->montant,2) . ' €'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Mettre à jour</button>
            </div>
        </form>

        <h3 style="margin-bottom:8px;">Historique des paiements</h3>
        <?php if ( empty($paiements) ): ?>
            <p style="color:#999; font-size:13px;">Aucun paiement enregistré.</p>
        <?php else: ?>
        <table class="tkd-paiement-table">
            <thead><tr><th>Date</th><th>Montant</th><th>Mode</th><th>Référence</th><th>Note</th><th>Saisi par</th><th>Dépôt prévu</th><th>Déposé</th><th></th><th>Reçu</th></tr></thead>
            <tbody>
            <?php foreach ($paiements as $p): ?>
            <?php $est_cheque = in_array( $p->mode, [ 'cheque', 'cheque_ancv' ], true ); ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($p->date_paiement)); ?></td>
                <td><strong><?php echo number_format($p->montant, 2); ?> €</strong></td>
                <td><?php
                    $mode_labels = array(
                        'cheque'         => 'Chèque',
                        'espece'         => 'Espèces',
                        'cheque_ancv'    => 'Chèque ANCV',
                        'carte_bancaire' => 'Carte bancaire',
                        'virement'       => 'Virement',
                        'helloasso'      => 'HelloAsso',
                        'pass_sport'     => "Pass'Sport",
                        'autre'          => 'Autre',
                    );
                    echo esc_html( $mode_labels[$p->mode] ?? ucfirst($p->mode) );
                ?></td>
                <td>
                    <?php if ( ! empty($p->recu_num) ) : ?>
                    <a href="?page=tkd-cotisations&action=apercu_recu&paiement_id=<?php echo intval($p->id); ?>"
                       target="_blank" style="font-size:12px;">🖨️ <?php echo esc_html($p->recu_num); ?></a>
                    <?php else: ?>
                    <span style="color:#ccc;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html($p->reference); ?></td>
                <td><?php echo esc_html($p->note); ?></td>
                <td><?php echo esc_html($p->saisi_par); ?></td>
                <td>
                    <?php if ( $est_cheque && ! empty($p->date_depot_prevue) ) : ?>
                        <?php echo date('d/m/Y', strtotime($p->date_depot_prevue)); ?>
                    <?php else: ?>
                        <span style="color:#ccc;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $est_cheque ) : ?>
                    <input type="checkbox" class="tkd-depot-checkbox"
                           data-id="<?php echo $p->id; ?>" data-nonce="<?php echo $nonce; ?>"
                           <?php checked( ! empty( $p->date_depot_reelle ) ); ?>
                           title="<?php echo ! empty($p->date_depot_reelle) ? 'Déposé le ' . esc_attr(date('d/m/Y', strtotime($p->date_depot_reelle))) : 'Pas encore déposé'; ?>">
                    <?php else: ?>
                        <span style="color:#ccc;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="button button-small tkd-del-paiement" style="color:red;"
                            data-id="<?php echo $p->id; ?>" data-nonce="<?php echo $nonce; ?>">
                        Supprimer
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="tkd-add-form">
            <h3>+ Ajouter un paiement</h3>
            <div class="tkd-add-form-grid">
                <div>
                    <label>Date</label>
                    <input type="date" id="tkd-date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label>Montant (€)</label>
                    <input type="number" step="0.01" id="tkd-montant" placeholder="<?php echo $reste; ?>">
                </div>
                <div>
                    <label>Mode de paiement</label>
                    <select id="tkd-mode">
                        <option value="cheque">Chèque</option>
                        <option value="espece">Espèces</option>
                        <option value="cheque_ancv">Chèque ANCV</option>
                        <option value="carte_bancaire">Carte bancaire</option>
                        <option value="virement">Virement</option>
                        <option value="helloasso">HelloAsso</option>
                        <option value="pass_sport">Pass'Sport</option>
                        <option value="autre">Autre</option>
                    </select>
                    <p id="tkd-pass-sport-note" style="display:none; color:#996800; font-size:12px; margin-top:4px;">
                        ℹ️ Pass'Sport : aucun reçu ne sera envoyé à l'adhérent, le paiement reste enregistré en interne uniquement.
                    </p>
                </div>
                <div>
                    <label>Référence (n° chèque, etc.)</label>
                    <input type="text" id="tkd-reference" placeholder="Optionnel">
                </div>
                <div id="tkd-depot-prevue-wrap" style="display:none;">
                    <label>Date de dépôt prévue</label>
                    <input type="date" id="tkd-depot-prevue">
                </div>
                <div>
                    <label>Note</label>
                    <input type="text" id="tkd-note" placeholder="Optionnel">
                </div>
            </div>
            <p style="color:#666; font-size:12px; margin-top:8px;">
                ℹ️ La facture n'est envoyée à l'adhérent qu'une fois la cotisation intégralement soldée — utile pour un règlement en plusieurs chèques.
            </p>
            <button class="button button-primary" style="margin-top:12px;" id="tkd-btn-ajouter">Enregistrer le paiement</button>
            <span id="tkd-msg" style="margin-left:10px; font-size:13px;"></span>
        </div>

        <div class="tkd-info-box">
            <strong>Informations de paiement pour l'adhérent :</strong><br>
            <?php if ($helloasso_url): ?>
            🌐 <strong>HelloAsso :</strong> <a href="<?php echo esc_url($helloasso_url); ?>" target="_blank">Payer en ligne</a><br>
            <?php endif; ?>
            <?php if ($iban): ?>
            🏦 <strong>Virement :</strong> IBAN <?php echo esc_html($iban); ?><?php if ($cheque_ordre): ?> — à l'ordre de <?php echo esc_html($cheque_ordre); ?><?php endif; ?><br>
            <?php endif; ?>
            <?php if ($cheque_ordre): ?>
            📝 <strong>Chèque :</strong> à l'ordre de <?php echo esc_html($cheque_ordre); ?>
            <?php endif; ?>
        </div>

        <?php if ( $cotis && $cotis->statut !== 'solde' ) : ?>
        <div style="margin-top:16px;">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('tkd_rappel_individuel_nonce'); ?>
                <input type="hidden" name="action" value="tkd_envoyer_rappel_individuel">
                <input type="hidden" name="eleve_id" value="<?php echo $eleve_id; ?>">
                <?php
                $dest_email = $eleve->email ?: $eleve->email_parent;
                if ( $dest_email ) : ?>
                <button type="submit" class="button"
                        onclick="return confirm('Envoyer un rappel par email à <?php echo esc_js($eleve->prenom . ' ' . $eleve->nom); ?> ?')">
                    📧 Envoyer un rappel par email
                </button>
                <span style="color:#666; font-size:12px; margin-left:8px;">→ <?php echo esc_html($dest_email); ?></span>
                <?php else : ?>
                <span style="color:#999; font-size:13px;">⚠️ Aucun email renseigné pour cet élève.</span>
                <?php endif; ?>
                <?php if ( isset($_GET['rappel']) ) : ?>
                <span style="color:<?php echo $_GET['rappel'] ? 'green' : 'red'; ?>; margin-left:10px; font-size:13px;">
                    <?php echo $_GET['rappel'] ? '✅ Email envoyé' : '❌ Échec envoi'; ?>
                </span>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <?php endif; // fin if cotis ?>
    </div>

    <script>
    // Sélection tarif → remplit montant
    document.querySelector('select[name="tarif_id"]')?.addEventListener('change', function() {
        var m = this.options[this.selectedIndex].dataset.montant;
        if (m) document.querySelector('input[name="nouveau_montant"]').value = m;
    });

    // Mode Pass'Sport → affiche le rappel "pas de reçu envoyé" ; Chèque/Chèque ANCV → affiche la date de dépôt prévue
    function tkdSyncModePaiement() {
        var modeSelect = document.getElementById('tkd-mode');
        if (!modeSelect) return;
        document.getElementById('tkd-pass-sport-note').style.display = (modeSelect.value === 'pass_sport') ? 'block' : 'none';
        var estCheque = (modeSelect.value === 'cheque' || modeSelect.value === 'cheque_ancv');
        document.getElementById('tkd-depot-prevue-wrap').style.display = estCheque ? 'block' : 'none';
    }
    document.getElementById('tkd-mode')?.addEventListener('change', tkdSyncModePaiement);
    tkdSyncModePaiement(); // état initial : "Chèque" est déjà l'option sélectionnée par défaut

    // Ajout paiement AJAX
    document.getElementById('tkd-btn-ajouter')?.addEventListener('click', function() {
        var msg = document.getElementById('tkd-msg');
        msg.textContent = 'Enregistrement…';
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action:             'tkd_ajouter_paiement',
                nonce:              '<?php echo $nonce; ?>',
                eleve_id:           <?php echo $eleve_id; ?>,
                montant:            document.getElementById('tkd-montant').value,
                mode:               document.getElementById('tkd-mode').value,
                reference:          document.getElementById('tkd-reference').value,
                note:               document.getElementById('tkd-note').value,
                date_paiement:      document.getElementById('tkd-date').value,
                date_depot_prevue:  document.getElementById('tkd-depot-prevue').value,
            })
        }).then(r => r.json()).then(r => {
            if (r.success) { msg.style.color='green'; msg.textContent='✅ ' + r.data; setTimeout(()=>location.reload(),800); }
            else { msg.style.color='red'; msg.textContent='❌ ' + r.data; }
        });
    });

    // Suppression paiement
    document.querySelectorAll('.tkd-del-paiement').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Supprimer ce paiement ?')) return;
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action:      'tkd_supprimer_paiement',
                    nonce:       this.dataset.nonce,
                    paiement_id: this.dataset.id,
                })
            }).then(r => r.json()).then(r => {
                if (r.success) location.reload();
                else alert('Erreur : ' + r.data);
            });
        }.bind(btn));
    });

    // Case à cocher "Déposé"
    document.querySelectorAll('.tkd-depot-checkbox').forEach(function(chk) {
        chk.addEventListener('change', function() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action:      'tkd_marquer_depot',
                    nonce:       this.dataset.nonce,
                    paiement_id: this.dataset.id,
                    depose:      this.checked ? '1' : '',
                })
            }).then(r => r.json()).then(r => {
                if (!r.success) { alert('Erreur : ' + r.data); this.checked = !this.checked; }
                else { location.reload(); }
            });
        }.bind(chk));
    });
    </script>
    <?php

    // Traitement modification montant
    if ( isset($_POST['tkd_action']) && $_POST['tkd_action'] === 'modifier_montant' ) {
        check_admin_referer('tkd_modif_cotis');
        global $wpdb;
        $eid    = intval($_POST['eleve_id']);
        $saison = tkd_get_saison_courante();
        $cotis2 = tkd_get_cotisation_eleve($eid, $saison);
        if ($cotis2) {
            $nouveau = floatval($_POST['nouveau_montant']);
            $wpdb->update($wpdb->prefix . 'sp_cal_cotisations',
                ['montant_du' => $nouveau, 'tarif_id' => $_POST['tarif_id'] ?: null],
                ['id' => $cotis2->id]
            );
            tkd_recalcule_statut($cotis2->id);
        }
    }
}

// ============================================================
// 9. EMAILS DE RAPPEL
// ============================================================

// Envoi manuel depuis la vue globale
/**
 * Fonction commune d'envoi de rappel pour un élève
 */
function tkd_envoyer_rappel_eleve( $eleve_id ) {
    global $wpdb;
    $saison    = tkd_get_saison_courante();
    $helloasso = get_option('tkd_helloasso_url', '');
    $iban      = get_option('tkd_virement_iban', '');
    $cheque    = get_option('tkd_cheque_ordre', '');

    $e = $wpdb->get_row( $wpdb->prepare(
        "SELECT e.*, c.montant_du, c.statut,
                COALESCE((SELECT SUM(p.montant) FROM {$wpdb->prefix}sp_cal_cotisation_paiements p
                          JOIN {$wpdb->prefix}sp_cal_cotisations c2 ON c2.id = p.cotisation_id
                          WHERE c2.eleve_id = e.id AND c2.saison = %s), 0) AS total_paye
         FROM {$wpdb->prefix}sp_cal_eleves e
         LEFT JOIN {$wpdb->prefix}sp_cal_cotisations c ON c.eleve_id = e.id AND c.saison = %s
         WHERE e.id = %d",
        $saison, $saison, $eleve_id
    ));
    if ( ! $e ) return false;

    $dest = $e->email ?: $e->email_parent;
    if ( ! $dest ) return false;

    $reste  = max( 0, (float)$e->montant_du - (float)$e->total_paye );
    $sujet  = "Rappel cotisation TKD Claira — Saison $saison";
    $corps  = "Bonjour " . $e->prenom . " " . $e->nom . ",\n\n";
    $corps .= "Nous vous rappelons que votre cotisation pour la saison $saison est en attente de règlement.\n\n";
    $corps .= "Montant dû : " . number_format( $e->montant_du, 2 ) . " €\n";
    $corps .= "Déjà payé : " . number_format( $e->total_paye, 2 ) . " €\n";
    $corps .= "Reste à payer : " . number_format( $reste, 2 ) . " €\n\n";
    $corps .= "Vous pouvez régler par :\n";
    if ( $helloasso ) $corps .= "• HelloAsso (paiement en ligne) : $helloasso\n";
    if ( $iban )      $corps .= "• Virement bancaire — IBAN : $iban\n";
    if ( $cheque )    $corps .= "• Chèque à l'ordre de : $cheque\n";
    $corps .= "\nMerci de votre confiance.\nL'équipe TKD Claira";

    return wp_mail( $dest, $sujet, $corps, ['Content-Type: text/plain; charset=UTF-8'] );
}

/**
 * Rappel en lot depuis l'initialisation saison (liste d'ids cochés)
 */
add_action( 'admin_post_tkd_envoyer_rappel', 'tkd_envoyer_rappel_lot' );
function tkd_envoyer_rappel_lot() {
    check_admin_referer( 'tkd_rappel_nonce' );
    if ( ! current_user_can(TKD_COT_CAP) ) wp_die('Accès refusé');

    $eleve_ids  = array_map('intval', $_POST['eleve_ids'] ?? [] );
    $nb_envoyes = 0;
    foreach ( $eleve_ids as $eid ) {
        if ( tkd_envoyer_rappel_eleve( $eid ) ) $nb_envoyes++;
    }

    wp_redirect( add_query_arg([
        'page'   => 'tkd-cotisations-init',
        'rappel' => $nb_envoyes,
    ], admin_url('admin.php')) );
    exit;
}

/**
 * Rappel individuel depuis la fiche élève
 */
add_action( 'admin_post_tkd_envoyer_rappel_individuel', 'tkd_envoyer_rappel_individuel' );
function tkd_envoyer_rappel_individuel() {
    check_admin_referer( 'tkd_rappel_individuel_nonce' );
    if ( ! current_user_can(TKD_COT_CAP) ) wp_die('Accès refusé');

    $eleve_id = intval( $_POST['eleve_id'] );
    $ok       = tkd_envoyer_rappel_eleve( $eleve_id );

    wp_redirect( add_query_arg([
        'page'     => 'tkd-cotisations',
        'action'   => 'fiche',
        'eleve_id' => $eleve_id,
        'rappel'   => $ok ? 1 : 0,
    ], admin_url('admin.php')) );
    exit;
}

// Bouton rappel dans la vue globale (notice)
add_action('admin_notices', function() {
    if ( isset($_GET['rappel']) ) {
        $n = intval($_GET['rappel']);
        echo '<div class="updated"><p>' . $n . ' email(s) de rappel envoyé(s) avec succès.</p></div>';
    }
});

// ============================================================
// 9bis. CLÔTURE DE SAISON
// ============================================================

add_action( 'admin_post_tkd_cloturer_saison', 'tkd_cloturer_saison' );
function tkd_cloturer_saison() {
    check_admin_referer( 'tkd_cloture_nonce' );
    if ( ! current_user_can( TKD_COT_CAP ) ) wp_die( 'Accès refusé' );

    $saison_actuelle = tkd_get_saison_courante();
    $saison_suivante = tkd_cot_saison_suivante( $saison_actuelle );

    update_option( 'tkd_saison_courante', $saison_suivante );

    wp_redirect( add_query_arg( [
        'page'            => 'tkd-cotisations-params',
        'cloture_ok'      => 1,
        'ancienne_saison' => $saison_actuelle,
        'nouvelle_saison' => $saison_suivante,
    ], admin_url( 'admin.php' ) ) );
    exit;
}

// ============================================================
// 10. RECALCUL AUTOMATIQUE DES CATÉGORIES D'ÂGE
// ============================================================

function tkd_calculer_categorie_age( $date_naissance_jj_mm, $annee_naissance, $saison ) {
    if ( empty($annee_naissance) || ! is_numeric($annee_naissance) ) return null;
    if ( empty($date_naissance_jj_mm) ) return null;

    $parts = explode('/', $date_naissance_jj_mm);
    if ( count($parts) < 2 ) return null;
    $jour = intval($parts[0]);
    $mois = intval($parts[1]);

    $annee_saison = intval( explode('/', $saison)[0] );
    $ref = mktime(0, 0, 0, 9, 1, $annee_saison);

    $naissance = mktime(0, 0, 0, $mois, $jour, intval($annee_naissance));
    $age = (int) floor( ($ref - $naissance) / (365.25 * 24 * 3600) );

    // 4 tranches, alignées sur la règle fédérale codée côté sp_build
    // (SpCalPro_DB::bascule_categories_septembre()) — avant cette correction, Ado/adulte et
    // Adulte étaient fusionnés ici en une seule tranche "Ado/adulte" (cf. échange du 18/09/2026).
    if ( $age < 6 )       return 'Baby';
    if ( $age <= 10 )     return 'Enfant';
    if ( $age <= 14 )     return 'Ado/adulte';
    return 'Adulte';
}

add_action( 'admin_post_tkd_recalculer_categories', 'tkd_recalculer_categories' );
function tkd_recalculer_categories() {
    check_admin_referer( 'tkd_recalc_nonce' );
    if ( ! current_user_can(TKD_COT_CAP) ) wp_die('Accès refusé');

    global $wpdb;
    $saison = tkd_get_saison_courante();

    $eleves = $wpdb->get_results(
        "SELECT id, date_naissance, annee_naissance, categorie_age
         FROM {$wpdb->prefix}sp_cal_eleves
         WHERE actif = 1
         AND (categorie_saisie != 'RENFO' OR categorie_saisie IS NULL OR categorie_saisie = '')"
    );

    $nb_modifies  = 0;
    $nb_ignores   = 0;
    $nb_erreurs   = 0;

    foreach ( $eleves as $e ) {
        $nouvelle_cat = tkd_calculer_categorie_age( $e->date_naissance, $e->annee_naissance, $saison );

        if ( $nouvelle_cat === null ) {
            $nb_erreurs++;
            continue;
        }

        if ( $nouvelle_cat !== $e->categorie_age ) {
            $wpdb->update(
                $wpdb->prefix . 'sp_cal_eleves',
                [ 'categorie_age' => $nouvelle_cat ],
                [ 'id' => $e->id ]
            );
            $nb_modifies++;
        } else {
            $nb_ignores++;
        }
    }

    wp_redirect( add_query_arg([
        'page'       => 'tkd-cotisations-params',
        'recalc_ok'  => $nb_modifies,
        'recalc_ign' => $nb_ignores,
        'recalc_err' => $nb_erreurs,
    ], admin_url('admin.php')) );
    exit;
}

// ============================================================
// 11. CALCUL ÉLIGIBILITÉ 1E DAN
// ============================================================

add_action( 'admin_post_tkd_calculer_eligibilite_dan', 'tkd_calculer_eligibilite_dan' );
function tkd_calculer_eligibilite_dan() {
    check_admin_referer( 'tkd_eligibilite_nonce' );
    if ( ! current_user_can(TKD_COT_CAP) ) wp_die('Accès refusé');

    global $wpdb;
    $saison = tkd_get_saison_courante();

    $dates_dan = $wpdb->get_col( $wpdb->prepare(
        "SELECT date FROM {$wpdb->prefix}sp_cal_events
         WHERE titre LIKE '%DAN%' AND saison = %s
         ORDER BY date ASC",
        $saison
    ) );

    if ( empty($dates_dan) ) {
        wp_redirect( add_query_arg([
            'page' => 'tkd-cotisations-params',
            'err'  => 'nodate',
        ], admin_url('admin.php')) );
        exit;
    }

    $ts_examens = array_map('strtotime', $dates_dan);

    $eleves = $wpdb->get_results(
        "SELECT id, date_naissance, annee_naissance, nb_licences
         FROM {$wpdb->prefix}sp_cal_eleves
         WHERE actif = 1 AND categorie_age = 'Ado/adulte'"
    );

    $nb_ok  = 0;
    $nb_non = 0;

    foreach ( $eleves as $e ) {
        $cond_age = false;
        $parts    = explode('/', $e->date_naissance);
        if ( count($parts) >= 2 && is_numeric($e->annee_naissance) ) {
            $ts_naiss = mktime(0, 0, 0, intval($parts[1]), intval($parts[0]), intval($e->annee_naissance));
            foreach ( $ts_examens as $ts_ex ) {
                $age = (int) floor( ($ts_ex - $ts_naiss) / (365.25 * 24 * 3600) );
                if ( $age >= 14 ) { $cond_age = true; break; }
            }
        }

        $cond_licences = ( intval($e->nb_licences) >= 3 );
        $eligible = $cond_age && $cond_licences;

        $wpdb->update(
            $wpdb->prefix . 'sp_cal_eleves',
            [ 'eligible_dan' => $eligible ? 1 : 0 ],
            [ 'id' => $e->id ]
        );

        $eligible ? $nb_ok++ : $nb_non++;
    }

    wp_redirect( add_query_arg([
        'page'            => 'tkd-cotisations-params',
        'eligibilite_ok'  => $nb_ok,
        'eligibilite_non' => $nb_non,
    ], admin_url('admin.php')) );
    exit;
}


// ============================================================
// REÇU — Email + Page aperçu
// ============================================================

function tkd_mode_label( $mode ) {
    $labels = array(
        'cheque'         => 'Chèque',
        'espece'         => 'Espèces',
        'cheque_ancv'    => 'Chèque ANCV',
        'carte_bancaire' => 'Carte bancaire',
        'virement'       => 'Virement',
        'helloasso'      => 'HelloAsso',
        'pass_sport'     => "Pass'Sport",
        'autre'          => 'Autre',
    );
    return $labels[$mode] ?? ucfirst($mode);
}

function tkd_html_recu( $eleve, $cotis, $montant, $mode, $reference, $date_paie, $recu_num ) {
    $nom_club     = get_option( 'blogname', 'Taekwondo Claira' );
    $club_adresse = get_option( 'sp_cal_adresse_club', '8 Avenue de l\'Agly, 66530 Claira' );
    $club_email   = get_option( 'sp_cal_email_club',   'tkd.claira@gmail.com' );
    $club_tel     = get_option( 'sp_cal_telephone_club','0634088051' );
    $club_siren   = get_option( 'sp_cal_siren_club',   '811 945 484' );
    $club_ville   = get_option( 'sp_cal_ville_club',   'Claira' );

    // Prénom et nom gérés correctement
    $nom_complet = trim( wp_unslash($eleve->prenom) . ' ' . wp_unslash($eleve->nom) );
    $mode_label  = tkd_mode_label( $mode );
    if ( $reference ) $mode_label .= ' N°' . $reference;
    $date_fmt    = $date_paie ? date_create($date_paie)->format('d/m/Y') : date('d/m/Y');
    $today_fmt   = date('d/m/Y');
    $saison      = $cotis->saison ?? get_option('tkd_saison_courante', date('Y'));
    $objet       = 'Cotisation ' . $saison;
    if ( !empty($eleve->categorie_saisie) ) $objet .= ' - ' . $eleve->categorie_saisie;

    $plugin_dir = TKD_COT_DIR;
    $logo_src   = '';
    $sig_src    = '';
    foreach ( array('logo.jpg','logo.jpeg','logo.png') as $_lf ) {
        if ( file_exists( $plugin_dir . $_lf ) ) {
            $ext  = strtolower( pathinfo($_lf, PATHINFO_EXTENSION) );
            $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
            $logo_src = 'data:' . $mime . ';base64,' . base64_encode( file_get_contents( $plugin_dir . $_lf ) );
            break;
        }
    }
    foreach ( array( 'signature.jpeg', 'Signature.jpeg', 'signature.jpg', 'Signature.jpg' ) as $_sig_file ) {
        if ( file_exists( $plugin_dir . $_sig_file ) ) {
            $sig_src = 'data:image/jpeg;base64,' . base64_encode( file_get_contents( $plugin_dir . $_sig_file ) );
            break;
        }
    }

    $html  = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
    $html .= '<title>Reçu ' . esc_html($recu_num) . '</title>';
    $html .= '<style>';
    $html .= '*{box-sizing:border-box;margin:0;padding:0}';
    $html .= 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:#f1f5f9;}';
    $html .= '.wrap{max-width:680px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);}';
    $html .= '.header{background:#1e3a5f;padding:28px 40px;text-align:center;}';
    $html .= '.header img{height:70px;width:auto;}';
    $html .= '.header h1{color:#fff;font-size:20px;font-weight:800;letter-spacing:1.5px;margin-top:14px;}';
    $html .= '.header p{color:rgba(255,255,255,.7);font-size:12px;margin-top:4px;}';
    $html .= '.body{padding:36px 40px;}';
    $html .= '.recu-num{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:6px 16px;font-size:13px;font-weight:700;color:#1e3a5f;margin-bottom:20px;letter-spacing:.5px;}';
    $html .= '.intro{font-size:14px;color:#374151;margin-bottom:24px;}';
    $html .= '.card{background:#f8fafc;border-radius:10px;padding:20px 24px;margin-bottom:20px;border:1px solid #e2e8f0;}';
    $html .= '.card-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px;}';
    $html .= '.card-row:last-child{border-bottom:none;padding-bottom:0;}';
    $html .= '.card-row .label{color:#6b7280;}';
    $html .= '.card-row .value{font-weight:600;color:#111;text-align:right;}';
    $html .= '.montant-big{text-align:center;background:#f4f8fc;color:#1e3a5f;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:24px;}';
    $html .= '.montant-big .chiffre{font-size:36px;font-weight:800;}';
    $html .= '.montant-big .label{font-size:13px;color:#6b7280;margin-top:4px;}';
    $html .= '.fait{text-align:right;font-size:13px;color:#6b7280;margin:20px 0 10px;}';
    $html .= '.sig-bloc{display:flex;justify-content:space-between;align-items:center;margin:8px 0 24px;padding:0 20px;}';
    $html .= '.sig-bloc img.sig{height:70px;width:auto;}';
    $html .= '.tampon-html{background:#f4f8fc;border:1px solid #1e3a5f;border-radius:6px;padding:12px 16px;text-align:center;min-width:180px;}';
    $html .= '.tampon-nom{font-size:11px;font-weight:800;color:#1e3a5f;letter-spacing:1px;margin-bottom:4px;}';
    $html .= '.tampon-detail{font-size:9px;color:#6b7280;line-height:1.5;}';
    $html .= '.footer{background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 40px;text-align:center;font-size:11px;color:#94a3b8;line-height:1.6;}';
    $html .= '@media print{ * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } body{background:#fff;} .wrap{box-shadow:none;margin:0;border-radius:0;max-width:100%;} .no-print{display:none!important;} }';
    $html .= '</style></head><body>';

    $html .= '<div class="no-print" style="text-align:center;padding:14px;background:#1e3a5f;">';
    $html .= '<button onclick="window.print()" style="background:#fff;color:#1e3a5f;border:none;border-radius:7px;padding:9px 22px;font-size:14px;font-weight:700;cursor:pointer;margin-right:10px;">🖨️ Imprimer / PDF</button>';
    $html .= '<button onclick="window.close()" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:7px;padding:9px 18px;font-size:13px;cursor:pointer;">✕ Fermer</button>';
    $html .= '</div>';

    $html .= '<div class="wrap">';

    $html .= '<div class="header">';
    if ( $logo_src ) {
        $html .= '<img src="' . $logo_src . '" alt="' . esc_attr($nom_club) . '">';
    } else {
        $html .= '<div style="font-size:28px;font-weight:900;color:#fff;letter-spacing:2px;">' . esc_html( strtoupper($nom_club) ) . '</div>';
    }
    $html .= '<h1>REÇU DE PAIEMENT</h1>';
    $html .= '<p>' . esc_html( strtoupper($nom_club) ) . '</p>';
    $html .= '</div>';

    $html .= '<div class="body">';
    $html .= '<span class="recu-num">N° ' . esc_html($recu_num) . '</span>';
    $html .= '<p class="intro">Bonjour <strong>' . esc_html($eleve->prenom) . '</strong>,<br>nous vous confirmons la bonne réception de votre paiement.</p>';

    $html .= '<div class="montant-big">';
    $html .= '<div class="chiffre">' . number_format($montant, 2, ',', ' ') . ' €</div>';
    $html .= '<div class="label">Montant reçu</div>';
    $html .= '</div>';

    $html .= '<div class="card">';
    $html .= '<div class="card-row"><span class="label">Objet</span><span class="value">' . esc_html($objet) . '</span></div>';
    $html .= '<div class="card-row"><span class="label">Adhérent</span><span class="value">' . esc_html($nom_complet) . '</span></div>';
    $html .= '<div class="card-row"><span class="label">Mode de paiement</span><span class="value">' . esc_html($mode_label) . '</span></div>';
    $html .= '<div class="card-row"><span class="label">Date du paiement</span><span class="value">' . esc_html($date_fmt) . '</span></div>';
    $html .= '</div>';

    $html .= '<p class="fait">Fait à ' . esc_html($club_ville) . ', le ' . esc_html($today_fmt) . '</p>';

    $html .= '<div class="sig-bloc">';
    if ( $sig_src ) {
        $html .= '<img src="' . $sig_src . '" class="sig" alt="Signature">';
    } else {
        $html .= '<div></div>'; 
    }
    $html .= '<div class="tampon-html">';
    $html .= '<div class="tampon-nom">' . esc_html( strtoupper($nom_club) ) . '</div>';
    $html .= '<div class="tampon-detail">' . esc_html($club_adresse) . '</div>';
    $html .= '<div class="tampon-detail">' . esc_html($club_email) . ' — ' . esc_html($club_tel) . '</div>';
    $html .= '<div class="tampon-detail">SIREN : ' . esc_html($club_siren) . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>'; // .body

    $html .= '<div class="footer">';
    $html .= esc_html($club_adresse) . ' &nbsp;—&nbsp; ' . esc_html($club_email) . ' &nbsp;—&nbsp; ' . esc_html($club_tel) . '<br>';
    $html .= 'SIREN : ' . esc_html($club_siren) . ' &nbsp;—&nbsp; Association de Loi 1901';
    $html .= '</div>';

    $html .= '</div></body></html>';
    return $html;
}

/**
 * Point d'entrée "facture au solde" : récupère tous les paiements de la cotisation et
 * envoie un seul email récapitulatif (via tkd_envoyer_recu_paiement) au lieu d'un email
 * par paiement — permet un règlement en plusieurs chèques sans spammer l'adhérent
 * (doléance du 24/09/2026).
 */
function tkd_envoyer_facture_solde( $eleve, $cotis ) {
    $paiements = tkd_get_paiements( $cotis->id );

    // Le Pass'Sport n'est jamais notifié à l'adhérent (déjà géré à part, cf. tkd_ajax_ajouter_paiement).
    $paiements_email = array_values( array_filter( (array) $paiements, function( $p ) {
        return $p->mode !== 'pass_sport';
    } ) );
    if ( empty( $paiements_email ) ) return false;

    $total = array_sum( array_column( $paiements_email, 'montant' ) );

    if ( count( $paiements_email ) === 1 ) {
        $p = $paiements_email[0];
        return tkd_envoyer_recu_paiement( $eleve, $cotis, $total, $p->mode, $p->reference, $p->date_paiement, $p->recu_num );
    }

    $dernier = end( $paiements_email );
    return tkd_envoyer_recu_paiement( $eleve, $cotis, $total, $dernier->mode, '', $dernier->date_paiement, $dernier->recu_num, $paiements_email );
}

function tkd_envoyer_recu_paiement( $eleve, $cotis, $montant, $mode, $reference, $date_paie, $recu_num, $detail_paiements = array() ) {
    $dest = $eleve->email ?: $eleve->email_parent;
    if ( ! $dest || ! is_email($dest) ) return false;

    $nom_club     = get_option( 'blogname', 'Taekwondo Claira' );
    $club_adresse = get_option( 'sp_cal_adresse_club', '8 Avenue de l\'Agly, 66530 Claira' );
    $club_email   = get_option( 'sp_cal_email_club',   'tkd.claira@gmail.com' );
    $club_tel     = get_option( 'sp_cal_telephone_club','0634088051' );
    $club_siren   = get_option( 'sp_cal_siren_club',   '811 945 484' );
    $club_ville   = get_option( 'sp_cal_ville_club',   'Claira' );

    $mode_labels = array(
        'cheque'         => 'Chèque',
        'espece'         => 'Espèces',
        'cheque_ancv'    => 'Chèque ANCV',
        'carte_bancaire' => 'Carte bancaire',
        'virement'       => 'Virement',
        'helloasso'      => 'HelloAsso',
        'pass_sport'     => "Pass'Sport",
        'autre'          => 'Autre',
    );
    $mode_label  = ! empty( $detail_paiements )
        ? sprintf( 'Réglé en %d fois', count( $detail_paiements ) )
        : ( $mode_labels[$mode] ?? ucfirst($mode) ) . ( $reference ? ' N°' . $reference : '' );

    $nom_complet = trim( wp_unslash($eleve->prenom) . ' ' . wp_unslash($eleve->nom) );
    $date_fmt    = $date_paie ? date_create($date_paie)->format('d/m/Y') : date('d/m/Y');
    $today_fmt   = date('d/m/Y');
    $saison      = $cotis->saison ?? get_option('tkd_saison_courante', date('Y'));
    $objet       = 'Cotisation ' . $saison;
    if ( ! empty($eleve->categorie_saisie) ) $objet .= ' - ' . wp_unslash($eleve->categorie_saisie);
    $montant_fmt = number_format( $montant, 2, ',', ' ' ) . ' €';

    $subject = '[' . $nom_club . '] Reçu N° ' . $recu_num . ' — Paiement cotisation';

    $body  = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>';
    $body .= '<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">';
    $body .= '<tr><td align="center">';
    $body .= '<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">';

    $body .= '<tr><td style="background:#1e3a5f;padding:28px 40px;text-align:center;">';
    $body .= '<div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;">REÇU DE PAIEMENT</div>';
    $body .= '<div style="font-size:26px;font-weight:800;color:#fff;letter-spacing:1px;">' . esc_html( strtoupper($nom_club) ) . '</div>';
    $body .= '<div style="display:inline-block;background:rgba(255,255,255,.12);border-radius:20px;padding:5px 16px;margin-top:10px;font-size:13px;font-weight:700;color:#fff;letter-spacing:.5px;">N° ' . esc_html($recu_num) . '</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding:32px 40px;">';
    $intro = empty( $detail_paiements )
        ? 'nous vous confirmons la bonne réception de votre paiement.'
        : 'votre cotisation est intégralement réglée — voici le récapitulatif de l\'ensemble de vos paiements.';
    $body .= '<p style="font-size:15px;color:#374151;margin:0 0 24px;">Bonjour <strong>' . esc_html($eleve->prenom) . '</strong>,<br>' . $intro . '</p>';

    $body .= '<div style="background:#f4f8fc;border:1px solid #1e3a5f;border-radius:10px;padding:20px;text-align:center;margin-bottom:24px;">';
    $body .= '<div style="font-size:38px;font-weight:800;color:#1e3a5f;">' . esc_html($montant_fmt) . '</div>';
    $body .= '<div style="font-size:12px;color:#6b7280;margin-top:4px;">Montant reçu</div>';
    $body .= '</div>';

    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:24px;">';
    $rows = array(
        'Objet'            => $objet,
        'Adhérent'         => $nom_complet,
        'Mode de paiement' => $mode_label,
        'Date du paiement' => $date_fmt,
    );
    $first = true;
    foreach ( $rows as $lbl => $val ) {
        $border = $first ? '' : 'border-top:1px solid #e2e8f0;';
        $body .= '<tr>';
        $body .= '<td style="padding:12px 18px;font-size:13px;color:#6b7280;' . $border . '">' . esc_html($lbl) . '</td>';
        $body .= '<td style="padding:12px 18px;font-size:13px;font-weight:600;color:#111;text-align:right;' . $border . '">' . esc_html($val) . '</td>';
        $body .= '</tr>';
        $first = false;
    }
    $body .= '</table>';

    if ( ! empty( $detail_paiements ) ) {
        $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin:0 0 8px;">Détail des règlements</div>';
        $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:24px;">';
        $first_d = true;
        foreach ( $detail_paiements as $dp ) {
            $dp_label = $mode_labels[ $dp->mode ] ?? ucfirst( $dp->mode );
            if ( $dp->reference ) $dp_label .= ' N°' . $dp->reference;
            $dp_date  = $dp->date_paiement ? date_create( $dp->date_paiement )->format( 'd/m/Y' ) : '';
            $border   = $first_d ? '' : 'border-top:1px solid #e2e8f0;';
            $body .= '<tr>';
            $body .= '<td style="padding:10px 18px;font-size:12px;color:#6b7280;' . $border . '">' . esc_html( $dp_date . ' — ' . $dp_label ) . '</td>';
            $body .= '<td style="padding:10px 18px;font-size:12px;font-weight:600;color:#111;text-align:right;' . $border . '">' . esc_html( number_format( $dp->montant, 2, ',', ' ' ) . ' €' ) . '</td>';
            $body .= '</tr>';
            $first_d = false;
        }
        $body .= '</table>';
    }

    $body .= '<p style="font-size:13px;color:#6b7280;text-align:right;margin:0 0 4px;">Fait à ' . esc_html($club_ville) . ', le ' . esc_html($today_fmt) . '</p>';
    $body .= '<p style="font-size:12px;color:#94a3b8;text-align:center;font-style:italic;margin:24px 0 0 0;">Document à conserver</p>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 40px;text-align:center;font-size:11px;color:#94a3b8;line-height:1.6;">';
    $body .= esc_html($club_adresse) . ' &nbsp;—&nbsp; ' . esc_html($club_email) . ' &nbsp;—&nbsp; ' . esc_html($club_tel) . '<br>';
    $body .= 'SIREN : ' . esc_html($club_siren) . ' &nbsp;—&nbsp; Association de Loi 1901';
    $body .= '</td></tr>';

    $body .= '</table></td></tr></table></body></html>';

    $pdf_url = '';
    $pdf_path = tkd_generer_pdf_recu( $eleve, $cotis, $montant, $mode, $reference, $date_paie, $recu_num );
    if ( $pdf_path ) {
        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'];
        $base_url   = $upload_dir['baseurl'];
        $rel_path   = str_replace( $base_dir, '', $pdf_path );
        $token      = basename( $pdf_path, '.pdf' );
        $pdf_url    = admin_url( 'admin-ajax.php' ) . '?action=tkd_download_recu&token=' . urlencode($token);
    }

    if ( $pdf_url ) {
        $body = str_replace(
            '<p style="font-size:12px;color:#94a3b8;text-align:center;font-style:italic;margin:24px 0 0 0;">Document à conserver</p>',
            '<p style="text-align:center;margin:8px 0 0;">'
            . '<a href="' . esc_url($pdf_url) . '" '
            . 'style="display:inline-block;background:#1e3a5f;color:#fff;text-decoration:none;'
            . 'border-radius:8px;padding:11px 24px;font-size:14px;font-weight:700;">'
            . '📄 Télécharger le reçu PDF</a></p>'
            . '<p style="font-size:11px;color:#94a3b8;text-align:center;margin:6px 0 0;">'
            . 'Lien valable 7 jours</p>',
            $body
        );
    }

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    return wp_mail( $dest, $subject, $body, $headers );
}

/**
 * Genere le PDF du recu dans un dossier temporaire securise.
 * Retourne le chemin du fichier ou false en cas d'echec.
 */
function tkd_generer_pdf_recu( $eleve, $cotis, $montant, $mode, $reference, $date_paie, $recu_num ) {
    if ( ! class_exists('TkdPDF') ) return false;

    $mode_labels = array(
        'cheque'         => 'Cheque',
        'espece'         => 'Especes',
        'cheque_ancv'    => 'Cheque ANCV',
        'carte_bancaire' => 'Carte bancaire',
        'virement'       => 'Virement',
        'helloasso'      => 'HelloAsso',
        'pass_sport'     => 'Pass Sport',
        'autre'          => 'Autre',
    );
    $mode_label = $mode_labels[$mode] ?? ucfirst($mode);
    if ( $reference ) $mode_label .= ' N' . $reference;

    $saison = $cotis->saison ?? get_option('tkd_saison_courante', date('Y'));
    $objet  = 'Cotisation ' . $saison;
    if ( ! empty($eleve->categorie_saisie) ) $objet .= ' - ' . wp_unslash($eleve->categorie_saisie);

    $plugin_dir = TKD_COT_DIR;
    $sig_path   = '';
    foreach ( array('signature.jpeg','Signature.jpeg','signature.jpg','Signature.jpg') as $f ) {
        if ( file_exists($plugin_dir . $f) ) { $sig_path = $plugin_dir . $f; break; }
    }

    $data = array(
        'prenom'       => $eleve->prenom,
        'nom'          => mb_strtoupper(wp_unslash($eleve->nom)),
        'montant'      => floatval($montant),
        'objet'        => $objet,
        'mode_label'   => $mode_label,
        'date_paie'    => $date_paie ? date_create($date_paie)->format('d/m/Y') : date('d/m/Y'),
        'recu_num'     => $recu_num,
        'club_nom'     => get_option('blogname', 'Taekwondo Claira'),
        'club_adresse' => get_option('sp_cal_adresse_club', '8 Avenue de l Agly, 66530 Claira'),
        'club_email'   => get_option('sp_cal_email_club',   'tkd.claira@gmail.com'),
        'club_tel'     => get_option('sp_cal_telephone_club','0634088051'),
        'club_siren'   => get_option('sp_cal_siren_club',   '811 945 484'),
        'club_ville'   => get_option('sp_cal_ville_club',   'Claira'),
        'sig_path'     => $sig_path,
        'logo_path'    => TKD_COT_DIR . 'logo.jpg',
    );

    try {
        $gen     = new TkdPDF();
        $pdf     = $gen->generate($data);
        $tmp_dir = wp_upload_dir()['basedir'] . '/tkd-recus/';
        wp_mkdir_p( $tmp_dir );

        // Proteger le dossier
        $htaccess = $tmp_dir . '.htaccess';
        if ( ! file_exists($htaccess) ) {
            file_put_contents($htaccess, "deny from all\n");
        }

        $filename = 'recu_' . sanitize_file_name($recu_num) . '_' . wp_generate_password(8, false) . '.pdf';
        $filepath = $tmp_dir . $filename;
        file_put_contents($filepath, $pdf);
        return $filepath;
    } catch ( Exception $e ) {
        return false;
    }
}

function tkd_page_apercu_recu( $paiement_id ) {
    global $wpdb;
    $p = $wpdb->get_row( $wpdb->prepare(
        "SELECT p.*, c.eleve_id, c.saison, c.montant_du
         FROM {$wpdb->prefix}sp_cal_cotisation_paiements p
         INNER JOIN {$wpdb->prefix}sp_cal_cotisations c ON c.id = p.cotisation_id
         WHERE p.id = %d", $paiement_id
    ));
    if ( ! $p ) wp_die( 'Paiement introuvable.' );

    $eleve = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sp_cal_eleves WHERE id=%d", $p->eleve_id
    ));
    if ( ! $eleve ) wp_die( 'Élève introuvable.' );

    // Si pas encore de numéro, en générer un
    $recu_num = $p->recu_num;
    if ( ! $recu_num ) {
        $annee    = date('Y');
        $last_num = intval( get_option( 'tkd_recu_last_num_' . $annee, 0 ) );
        $next_num = $last_num + 1;
        $recu_num = $annee . '-' . str_pad( $next_num, 3, '0', STR_PAD_LEFT );
        update_option( 'tkd_recu_last_num_' . $annee, $next_num );
        $wpdb->update(
            $wpdb->prefix . 'sp_cal_cotisation_paiements',
            array( 'recu_num' => $recu_num ),
            array( 'id' => $paiement_id )
        );
    }

    echo tkd_html_recu( $eleve, $p, $p->montant, $p->mode, $p->reference, $p->date_paiement, $recu_num );
}

// ============================================================
// TELECHARGEMENT SECURISE DU PDF (lien token)
// ============================================================

add_action( 'wp_ajax_tkd_download_recu',        'tkd_ajax_download_recu' );
add_action( 'wp_ajax_nopriv_tkd_download_recu', 'tkd_ajax_download_recu' );
function tkd_ajax_download_recu() {
    $token = sanitize_text_field( $_GET['token'] ?? '' );
    if ( ! $token ) wp_die( 'Token manquant.' );

    $upload_dir = wp_upload_dir();
    $dir        = $upload_dir['basedir'] . '/tkd-recus/';
    $filepath   = $dir . $token . '.pdf';

    if ( ! file_exists($filepath) ) {
        wp_die( 'Ce recu expire ou invalide.' );
    }

    // Vérifier que le fichier est dans le bon dossier (sécurité)
    $real = realpath($filepath);
    $real_dir = realpath($dir);
    if ( strpos($real, $real_dir) !== 0 ) wp_die( 'Acces refuse.' );

    $filename = 'recu-' . sanitize_file_name($token) . '.pdf';
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize($filepath) );
    header( 'Cache-Control: private' );
    readfile( $filepath );
    exit;
}

// ============================================================
// CRON — Nettoyage PDFs temporaires (> 7 jours)
// ============================================================

add_action( 'tkd_cleanup_recus', 'tkd_do_cleanup_recus' );
function tkd_do_cleanup_recus() {
    $dir = wp_upload_dir()['basedir'] . '/tkd-recus/';
    if ( ! is_dir($dir) ) return;
    $limit = time() - 7 * DAY_IN_SECONDS;
    foreach ( glob($dir . '*.pdf') ?: array() as $file ) {
        if ( filemtime($file) < $limit ) {
            @unlink($file);
        }
    }
}

if ( ! wp_next_scheduled('tkd_cleanup_recus') ) {
    wp_schedule_event( time(), 'daily', 'tkd_cleanup_recus' );
}

// ============================================================
// CRON — Rappel bureau : chèques à déposer
// ============================================================
// Doléance du 24/09/2026 : un règlement en plusieurs chèques (jusqu'à 3 + 1 bon
// Pass'Sport) nécessite des dépôts en banque échelonnés dans le temps — ce rappel
// quotidien évite au bureau de devoir surveiller manuellement les dates prévues.

add_action( 'tkd_rappel_depots_cheques', 'tkd_do_rappel_depots_cheques' );
function tkd_do_rappel_depots_cheques() {
    global $wpdb;
    $today = current_time( 'Y-m-d' );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.montant, p.date_depot_prevue, p.mode, e.nom, e.prenom
         FROM {$wpdb->prefix}sp_cal_cotisation_paiements p
         INNER JOIN {$wpdb->prefix}sp_cal_cotisations c ON c.id = p.cotisation_id
         INNER JOIN {$wpdb->prefix}sp_cal_eleves e ON e.id = c.eleve_id
         WHERE p.mode IN ('cheque','cheque_ancv')
           AND p.date_depot_reelle IS NULL
           AND p.date_depot_prevue IS NOT NULL
           AND p.date_depot_prevue <= %s
         ORDER BY p.date_depot_prevue ASC",
        $today
    ) );
    if ( empty( $rows ) ) return;

    $emails = array_filter( array_map( function( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        return $user ? $user->user_email : '';
    }, tkd_cot_bureau_users() ) );
    if ( empty( $emails ) ) return;

    $mode_labels = array( 'cheque' => 'Chèque', 'cheque_ancv' => 'Chèque ANCV' );

    $sujet = '[TKD Claira] ' . count( $rows ) . ' chèque(s) à déposer';
    $corps = "Bonjour,\n\nLes chèques suivants sont à déposer en banque :\n\n";
    foreach ( $rows as $r ) {
        $retard = $r->date_depot_prevue < $today ? ' — EN RETARD' : '';
        $corps .= '• ' . $r->prenom . ' ' . $r->nom . ' — ' . number_format( $r->montant, 2, ',', ' ' ) . ' € — '
                 . ( $mode_labels[ $r->mode ] ?? $r->mode ) . ' — prévu le ' . date( 'd/m/Y', strtotime( $r->date_depot_prevue ) ) . $retard . "\n";
    }
    $corps .= "\nUne fois déposé, pensez à cocher la case \"Déposé\" sur la fiche cotisation de l'adhérent.\n\nL'équipe TKD Claira";

    wp_mail( $emails, $sujet, $corps, array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

if ( ! wp_next_scheduled( 'tkd_rappel_depots_cheques' ) ) {
    wp_schedule_event( time(), 'daily', 'tkd_rappel_depots_cheques' );
}