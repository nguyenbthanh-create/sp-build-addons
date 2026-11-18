<?php
/**
 * Module « QCM Dan » : révision du QCU du Module B de l'examen Dan FFTDA.
 *
 * - Données : data/qcm-dan.js, extrait des 32 quiz officiels de la FFTDA
 *   (outils/qcm-dan/ pour régénérer le fichier quand la FFTDA met ses PDF à jour).
 * - Tableau de bord (TKD Parcours > QCM Dan) : recherche par mot(s) avec la bonne
 *   réponse, sélection de questions, tirage au sort, impression sujet / corrigé,
 *   projection, et enregistrement d'examens blancs en ligne (lien à partager).
 * - Site : shortcode [claira_qcm_dan] (recherche, examen blanc chronométré,
 *   révision par thème). Un examen enregistré s'ouvre avec ?examen=CODE.
 *
 * Tout le moteur tourne dans le navigateur (assets/js/qcm-dan.js) : aucune
 * requête externe, aucune donnée personnelle enregistrée sur le serveur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CLAIRA_TKD_QCM_OPTION = 'claira_tkd_qcm_examens';

/**
 * Styles et scripts du module. Les questions sont livrées en JavaScript
 * (data/qcm-dan.js, window.ClairaQcmDonnees) et non en .json : le .htaccess
 * du plugin interdit tous les .json (fichiers de développement).
 * Version des données = date du fichier, pour qu'une mise à jour des
 * questions ne reste pas en cache.
 */
function claira_tkd_qcm_enqueue() {
	$donnees = CLAIRA_TKD_PLUGIN_DIR . 'data/qcm-dan.js';
	$version = file_exists( $donnees ) ? (string) filemtime( $donnees ) : CLAIRA_TKD_PLUGIN_VERSION;
	wp_enqueue_style( 'claira-tkd-qcm', CLAIRA_TKD_PLUGIN_URL . 'assets/css/qcm-dan.css', array(), CLAIRA_TKD_PLUGIN_VERSION );
	wp_enqueue_script( 'claira-tkd-qcm-donnees', CLAIRA_TKD_PLUGIN_URL . 'data/qcm-dan.js', array(), $version, true );
	wp_enqueue_script( 'claira-tkd-qcm', CLAIRA_TKD_PLUGIN_URL . 'assets/js/qcm-dan.js', array( 'claira-tkd-qcm-donnees' ), CLAIRA_TKD_PLUGIN_VERSION, true );
}

/** Examens blancs enregistrés : code => array( titre, ids, duree, cree ). */
function claira_tkd_qcm_examens() {
	$examens = get_option( CLAIRA_TKD_QCM_OPTION, array() );
	return is_array( $examens ) ? $examens : array();
}

/** Identifiants de questions valides (« quiz_xxx#12 »), dédoublonnés, 200 au plus. */
function claira_tkd_qcm_nettoyer_ids( $brut ) {
	$ids = array();
	foreach ( explode( ',', (string) $brut ) as $id ) {
		$id = trim( $id );
		if ( preg_match( '/^[a-z0-9_]{1,80}#\d{1,4}$/', $id ) ) {
			$ids[ $id ] = true;
		}
	}
	return array_slice( array_keys( $ids ), 0, 200 );
}

/** Première page publiée qui contient un shortcode donné (adresse vide si aucune). */
function claira_tkd_qcm_page_avec( $shortcode ) {
	$pages = get_posts( array(
		'post_type'      => array( 'page', 'post' ),
		'post_status'    => 'publish',
		's'              => '[' . $shortcode,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return $pages ? get_permalink( $pages[0] ) : '';
}

/** Page du site qui contient le shortcode (pour construire les liens d'examen). */
function claira_tkd_qcm_page_url() {
	return claira_tkd_qcm_page_avec( 'claira_qcm_dan' );
}

/* --------------------------------------------------------------------------
 * Accès réservé (17/11/2026) : la page et son entrée de menu ne s'affichent
 * qu'aux adhérents actifs, à jour et de catégorie Ado / Adulte, connectés à
 * l'application du club /app/ (sp_build) sur cet appareil. Les adhérents n'ont
 * pas de compte WordPress : on lit la session de l'application (localStorage
 * « spcal_jwt ») et on interroge sp_build (GET spcal/v1/eleve/me), voir
 * assets/js/qcm-acces.js. Les comptes WordPress du club (bureau, encadrement)
 * ont toujours accès. Les questions viennent de PDF publics de la FFTDA : c'est
 * une réservation de confort, pas une protection des données.
 * ----------------------------------------------------------------------- */

const CLAIRA_TKD_QCM_CLASSE_MENU = 'claira-qcm-reserve';

/** Compte WordPress du club (bureau, encadrement) ? Un simple client de la boutique ne compte pas. */
function claira_tkd_qcm_acces_compte() {
	return is_user_logged_in() && (
		current_user_can( 'edit_posts' )
		|| current_user_can( 'sp_gestion_adhesions' )
		|| current_user_can( 'sp_cal_voir_planning' )
	);
}

/** Les entrées de menu qui mènent à une page du QCM reçoivent la classe « réservé ». */
function claira_tkd_qcm_menu_classes( $items ) {
	foreach ( $items as $item ) {
		if ( 'post_type' !== $item->type || ! in_array( $item->object, array( 'page', 'post' ), true ) ) {
			continue;
		}
		$contenu = (string) get_post_field( 'post_content', (int) $item->object_id );
		if ( '' !== $contenu && has_shortcode( $contenu, 'claira_qcm_dan' ) ) {
			$item->classes   = (array) $item->classes;
			$item->classes[] = CLAIRA_TKD_QCM_CLASSE_MENU;
		}
	}
	return $items;
}

/**
 * En tête de chaque page du site : l'entrée réservée est cachée tant que l'accès
 * n'est pas confirmé (classe « claira-qcm-ok » sur <html>), puis le contrôle.
 */
function claira_tkd_qcm_acces_head() {
	echo '<style id="claira-qcm-acces-css">html:not(.claira-qcm-ok) .' . esc_attr( CLAIRA_TKD_QCM_CLASSE_MENU ) . '{display:none!important}</style>' . "\n";
	if ( claira_tkd_qcm_acces_compte() ) {
		wp_print_inline_script_tag( "document.documentElement.classList.add('claira-qcm-ok');window.ClairaQcmAcces=true;" );
		return;
	}
	$script = CLAIRA_TKD_PLUGIN_DIR . 'assets/js/qcm-acces.js';
	if ( ! file_exists( $script ) ) {
		return;
	}
	wp_print_inline_script_tag(
		'window.ClairaQcmAccesApi=' . wp_json_encode( esc_url_raw( rest_url( 'spcal/v1/eleve/me' ) ) ) . ";\n"
		. file_get_contents( $script ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du plugin.
	);
}

/* --------------------------------------------------------------------------
 * Recherches sans résultat (18/11/2026) : quand une recherche de la page du
 * site ne trouve rien, le texte tapé est gardé pour que l'encadrement repère
 * les questions qui manquent ou les mots que le moteur ne comprend pas
 * (exemple : « DAC » avant la 1.14.2). Rien d'autre n'est gardé : ni nom, ni
 * adresse IP. Seuls les adhérents qui ont accès à la page (ou un compte du
 * club) peuvent écrire, 40 fois par heure au plus ; 300 textes au plus.
 * ----------------------------------------------------------------------- */

const CLAIRA_TKD_QCM_OPTION_VIDES = 'claira_tkd_qcm_recherches_vides';
const CLAIRA_TKD_QCM_VIDES_MAX    = 300;

/** Recherches sans résultat : clé => array( texte, mode, theme, nb, premier, dernier ). */
function claira_tkd_qcm_recherches_vides() {
	$vides = get_option( CLAIRA_TKD_QCM_OPTION_VIDES, array() );
	return is_array( $vides ) ? $vides : array();
}

function claira_tkd_qcm_rest_routes() {
	register_rest_route( 'claira-tkd/v1', '/qcm/sans-resultat', array(
		'methods'             => 'POST',
		'callback'            => 'claira_tkd_qcm_rest_sans_resultat',
		'permission_callback' => 'claira_tkd_qcm_rest_peut_noter',
	) );
}

/**
 * Même règle que la page : compte du club, ou session de l'application d'un
 * adhérent Ado / Adulte non expiré (vérifiée auprès de sp_build, mise en cache 6 h).
 */
function claira_tkd_qcm_rest_peut_noter( WP_REST_Request $req ) {
	if ( claira_tkd_qcm_acces_compte() ) {
		return true;
	}
	$jwt = (string) $req->get_header( 'X-Spcal-Jwt' );
	if ( strlen( $jwt ) < 20 || strlen( $jwt ) > 2000 ) {
		return false;
	}
	$cle = 'claira_qcm_jwt_' . md5( $jwt );
	$ok  = get_transient( $cle );
	if ( false === $ok ) {
		$me = new WP_REST_Request( 'GET', '/spcal/v1/eleve/me' );
		$me->set_header( 'X-Spcal-Jwt', $jwt );
		$rep = rest_do_request( $me );
		$d   = $rep->is_error() ? null : $rep->get_data();
		$ok  = ( is_array( $d ) && isset( $d['adhesion']['statut'] ) && 'expire' !== $d['adhesion']['statut']
			&& preg_match( '/ado|adulte/i', (string) ( $d['categorie_age'] ?? '' ) ) ) ? 'oui' : 'non';
		set_transient( $cle, $ok, 6 * HOUR_IN_SECONDS );
	}
	return 'oui' === $ok;
}

function claira_tkd_qcm_rest_sans_resultat( WP_REST_Request $req ) {
	$texte = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $req->get_param( 'texte' ) ) ) );
	if ( '' === $texte || mb_strlen( $texte ) > 300 ) {
		return new WP_Error( 'claira_qcm_texte', 'Texte invalide.', array( 'status' => 400 ) );
	}
	$texte = mb_substr( $texte, 0, 200 );
	$mode  = 'un' === $req->get_param( 'mode' ) ? 'un' : 'tous';
	$theme = substr( preg_replace( '/[^a-z0-9_]/', '', (string) $req->get_param( 'theme' ) ), 0, 80 );

	// 40 envois par heure et par personne (compte ou session de l'application).
	$qui    = is_user_logged_in() ? 'u' . get_current_user_id() : (string) $req->get_header( 'X-Spcal-Jwt' );
	$cle_nb = 'claira_qcm_nb_' . md5( $qui );
	$nb     = (int) get_transient( $cle_nb );
	if ( $nb >= 40 ) {
		return new WP_Error( 'claira_qcm_trop', 'Trop de recherches.', array( 'status' => 429 ) );
	}
	set_transient( $cle_nb, $nb + 1, HOUR_IN_SECONDS );

	$cle   = md5( mb_strtolower( $texte ) . '|' . $mode . '|' . $theme );
	$vides = claira_tkd_qcm_recherches_vides();
	if ( isset( $vides[ $cle ] ) ) {
		$vides[ $cle ]['nb']      = (int) $vides[ $cle ]['nb'] + 1;
		$vides[ $cle ]['dernier'] = time();
	} else {
		$vides[ $cle ] = array( 'texte' => $texte, 'mode' => $mode, 'theme' => $theme, 'nb' => 1, 'premier' => time(), 'dernier' => time() );
	}
	if ( count( $vides ) > CLAIRA_TKD_QCM_VIDES_MAX ) {
		uasort( $vides, static function ( $a, $b ) { return (int) $b['dernier'] - (int) $a['dernier']; } );
		$vides = array_slice( $vides, 0, CLAIRA_TKD_QCM_VIDES_MAX, true );
	}
	update_option( CLAIRA_TKD_QCM_OPTION_VIDES, $vides, false );
	return rest_ensure_response( array( 'ok' => true ) );
}

/* --------------------------------------------------------------------------
 * Shortcode du site
 * ----------------------------------------------------------------------- */

function claira_tkd_qcm_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'titre'      => __( 'Révision du QCM Dan', 'claira-tkd-parcours' ),
		'sous_titre' => __( 'Module B : les questions officielles de la FFTDA', 'claira-tkd-parcours' ),
		'questions'  => 30,
		'duree'      => 15,
	), $atts, 'claira_qcm_dan' );

	$config = array(
		'mode'      => 'site',
		'questions' => max( 5, min( 100, absint( $atts['questions'] ) ) ),
		'duree'     => max( 1, min( 120, absint( $atts['duree'] ) ) ),
		'examen'    => null,
		// Accès réservé : lien vers l'application du club pour se connecter.
		'appUrl'    => claira_tkd_qcm_page_avec( 'sp_cal_app' ),
		// Recherches sans résultat, gardées pour l'encadrement (claira_tkd_qcm_rest_sans_resultat).
		'journal'   => esc_url_raw( rest_url( 'claira-tkd/v1/qcm/sans-resultat' ) ),
		'restNonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
	);

	$code = isset( $_GET['examen'] ) ? sanitize_key( wp_unslash( $_GET['examen'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'un lien partagé.
	if ( $code ) {
		$examens = claira_tkd_qcm_examens();
		if ( isset( $examens[ $code ] ) ) {
			$config['examen'] = array(
				'titre' => $examens[ $code ]['titre'],
				'ids'   => $examens[ $code ]['ids'],
				'duree' => (int) $examens[ $code ]['duree'],
			);
		}
	}

	claira_tkd_qcm_enqueue();

	ob_start();
	?>
	<div class="claira-qcm" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
		<div class="claira-qcm-head">
			<h2><?php echo esc_html( $atts['titre'] ); ?></h2>
			<?php if ( $atts['sous_titre'] ) : ?>
				<p><?php echo esc_html( $atts['sous_titre'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="claira-qcm-app"><p class="claira-qcm-chargement"><?php esc_html_e( 'Chargement des questions…', 'claira-tkd-parcours' ); ?></p></div>
		<noscript><p><?php esc_html_e( 'Cet outil a besoin de JavaScript.', 'claira-tkd-parcours' ); ?></p></noscript>
	</div>
	<?php
	return ob_get_clean();
}

/* --------------------------------------------------------------------------
 * Tableau de bord
 * ----------------------------------------------------------------------- */

function claira_tkd_qcm_register_admin_menu() {
	$hook = add_submenu_page(
		'claira-tkd-parcours',
		__( 'QCM Dan', 'claira-tkd-parcours' ),
		__( 'QCM Dan', 'claira-tkd-parcours' ),
		'edit_posts',
		'claira-tkd-qcm',
		'claira_tkd_qcm_render_admin_page'
	);
	add_action( "load-{$hook}", 'claira_tkd_qcm_process_admin_form' );
	add_action( "load-{$hook}", 'claira_tkd_qcm_enqueue' );
}

function claira_tkd_qcm_admin_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=claira-tkd-qcm' ) );
}

/** Enregistrement / suppression d'un examen blanc (avant l'envoi des en-têtes). */
function claira_tkd_qcm_process_admin_form() {
	if ( ! isset( $_POST['claira_tkd_qcm_action'] ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( "Vous n'avez pas les droits nécessaires.", 'claira-tkd-parcours' ) );
	}
	check_admin_referer( 'claira_tkd_qcm_action', 'claira_tkd_qcm_nonce' );

	$action  = sanitize_key( wp_unslash( $_POST['claira_tkd_qcm_action'] ) );
	$examens = claira_tkd_qcm_examens();

	// Recherches sans résultat : retirer une ligne, ou tout effacer.
	if ( 'vide_supprimer' === $action || 'vides_tout' === $action ) {
		$vides = claira_tkd_qcm_recherches_vides();
		if ( 'vides_tout' === $action ) {
			$vides = array();
		} else {
			unset( $vides[ isset( $_POST['cle'] ) ? sanitize_key( wp_unslash( $_POST['cle'] ) ) : '' ] );
		}
		update_option( CLAIRA_TKD_QCM_OPTION_VIDES, $vides, false );
		wp_safe_redirect( claira_tkd_qcm_admin_url() . '#claira-qcm-vides' );
		exit;
	}

	if ( 'enregistrer' === $action ) {
		$ids   = claira_tkd_qcm_nettoyer_ids( isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '' );
		$titre = isset( $_POST['titre'] ) ? sanitize_text_field( wp_unslash( $_POST['titre'] ) ) : '';
		$duree = isset( $_POST['duree'] ) ? absint( wp_unslash( $_POST['duree'] ) ) : 15;
		if ( ! $ids ) {
			wp_safe_redirect( claira_tkd_qcm_admin_url( array( 'qcm_msg' => 'vide' ) ) );
			exit;
		}
		$code             = strtolower( wp_generate_password( 8, false, false ) );
		$examens[ $code ] = array(
			'titre' => $titre ? $titre : sprintf( /* translators: %s: date */ __( 'Examen blanc du %s', 'claira-tkd-parcours' ), wp_date( 'd/m/Y' ) ),
			'ids'   => $ids,
			'duree' => max( 1, min( 120, $duree ) ),
			'cree'  => time(),
		);
		update_option( CLAIRA_TKD_QCM_OPTION, $examens, false );
		wp_safe_redirect( claira_tkd_qcm_admin_url( array( 'qcm_msg' => 'cree' ) ) . '#claira-qcm-examens' );
		exit;
	}

	if ( 'supprimer' === $action ) {
		$code = isset( $_POST['code'] ) ? sanitize_key( wp_unslash( $_POST['code'] ) ) : '';
		unset( $examens[ $code ] );
		update_option( CLAIRA_TKD_QCM_OPTION, $examens, false );
		wp_safe_redirect( claira_tkd_qcm_admin_url( array( 'qcm_msg' => 'supprime' ) ) . '#claira-qcm-examens' );
		exit;
	}
}

function claira_tkd_qcm_render_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( "Vous n'avez pas les droits nécessaires pour accéder à cette page.", 'claira-tkd-parcours' ) );
	}

	$messages = array(
		'cree'     => array( 'success', __( "Examen blanc enregistré. Copiez son lien ci-dessous pour l'envoyer aux candidats.", 'claira-tkd-parcours' ) ),
		'supprime' => array( 'success', __( 'Examen blanc supprimé.', 'claira-tkd-parcours' ) ),
		'vide'     => array( 'error', __( "Sélectionnez au moins une question avant d'enregistrer un examen.", 'claira-tkd-parcours' ) ),
	);
	$msg = isset( $_GET['qcm_msg'] ) ? sanitize_key( wp_unslash( $_GET['qcm_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$examens  = claira_tkd_qcm_examens();
	$page_url = claira_tkd_qcm_page_url();
	$config   = array(
		'mode'    => 'admin',
	);
	?>
	<div class="wrap claira-qcm-admin">
		<h1><?php esc_html_e( 'QCM Dan : questions du Module B', 'claira-tkd-parcours' ); ?></h1>
		<?php if ( isset( $messages[ $msg ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $messages[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $msg ][1] ); ?></p></div>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e( 'Tapez un ou plusieurs mots, ou collez une question entière : la liste affiche les questions officielles qui les contiennent, avec la bonne réponse. Cochez des questions pour composer un examen blanc à imprimer, projeter ou faire passer en ligne.', 'claira-tkd-parcours' ); ?>
		</p>

		<div class="claira-qcm" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="claira-qcm-app"><p class="claira-qcm-chargement"><?php esc_html_e( 'Chargement des questions…', 'claira-tkd-parcours' ); ?></p></div>
		</div>

		<form id="claira-qcm-form-enregistrer" method="post" action="<?php echo esc_url( claira_tkd_qcm_admin_url() ); ?>" hidden>
			<?php wp_nonce_field( 'claira_tkd_qcm_action', 'claira_tkd_qcm_nonce' ); ?>
			<input type="hidden" name="claira_tkd_qcm_action" value="enregistrer">
			<input type="hidden" name="ids" value="">
			<input type="hidden" name="titre" value="">
			<input type="hidden" name="duree" value="15">
		</form>

		<?php
		$vides = claira_tkd_qcm_recherches_vides();
		uasort( $vides, static function ( $a, $b ) { return (int) $b['dernier'] - (int) $a['dernier']; } );
		?>
		<h2 id="claira-qcm-vides"><?php esc_html_e( 'Recherches sans résultat', 'claira-tkd-parcours' ); ?></h2>
		<p class="description">
			<?php esc_html_e( "Ce que les adhérents ont cherché sur la page du site sans rien trouver : question absente des quiz FFTDA, ou mot que le moteur ne comprend pas. Seul le texte tapé est gardé (ni nom, ni adresse). La colonne « Aujourd'hui » refait la recherche avec les questions actuelles.", 'claira-tkd-parcours' ); ?>
		</p>
		<?php if ( ! $vides ) : ?>
			<p><?php esc_html_e( 'Aucune recherche sans résultat pour le moment.', 'claira-tkd-parcours' ); ?></p>
		<?php else : ?>
			<table class="widefat striped claira-qcm-vides">
				<thead><tr>
					<th><?php esc_html_e( 'Recherche', 'claira-tkd-parcours' ); ?></th>
					<th><?php esc_html_e( 'Fois', 'claira-tkd-parcours' ); ?></th>
					<th><?php esc_html_e( 'Dernière fois', 'claira-tkd-parcours' ); ?></th>
					<th><?php esc_html_e( "Aujourd'hui", 'claira-tkd-parcours' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $vides as $cle => $v ) : ?>
					<tr class="claira-qcm-vide" data-texte="<?php echo esc_attr( $v['texte'] ); ?>" data-mode="<?php echo esc_attr( $v['mode'] ); ?>" data-theme="<?php echo esc_attr( $v['theme'] ); ?>">
						<td><strong><?php echo esc_html( $v['texte'] ); ?></strong><?php echo 'un' === $v['mode'] ? ' <span class="description">' . esc_html__( '(au moins un mot)', 'claira-tkd-parcours' ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( (string) (int) $v['nb'] ); ?></td>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) $v['dernier'] ) ); ?></td>
						<td class="claira-qcm-vide-etat">…</td>
						<td>
							<button type="button" class="button claira-qcm-vide-chercher"><?php esc_html_e( 'Chercher', 'claira-tkd-parcours' ); ?></button>
							<form method="post" action="<?php echo esc_url( claira_tkd_qcm_admin_url() ); ?>" style="display:inline">
								<?php wp_nonce_field( 'claira_tkd_qcm_action', 'claira_tkd_qcm_nonce' ); ?>
								<input type="hidden" name="claira_tkd_qcm_action" value="vide_supprimer">
								<input type="hidden" name="cle" value="<?php echo esc_attr( $cle ); ?>">
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Retirer', 'claira-tkd-parcours' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( claira_tkd_qcm_admin_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Effacer toute la liste des recherches sans résultat ?', 'claira-tkd-parcours' ) ); ?>');">
				<?php wp_nonce_field( 'claira_tkd_qcm_action', 'claira_tkd_qcm_nonce' ); ?>
				<input type="hidden" name="claira_tkd_qcm_action" value="vides_tout">
				<p><button type="submit" class="button"><?php esc_html_e( 'Tout effacer', 'claira-tkd-parcours' ); ?></button></p>
			</form>
		<?php endif; ?>

		<h2 id="claira-qcm-examens"><?php esc_html_e( 'Examens blancs en ligne', 'claira-tkd-parcours' ); ?></h2>
		<?php if ( ! $page_url ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php esc_html_e( 'Aucune page publiée ne contient le shortcode [claira_qcm_dan]. Ajoutez-le à une page pour obtenir les liens à envoyer aux candidats.', 'claira-tkd-parcours' ); ?>
			</p></div>
		<?php endif; ?>
		<?php if ( ! $examens ) : ?>
			<p><?php esc_html_e( 'Aucun examen enregistré pour le moment. Sélectionnez des questions puis « Enregistrer comme examen en ligne ».', 'claira-tkd-parcours' ); ?></p>
		<?php else : ?>
			<table class="widefat striped claira-qcm-examens">
				<thead><tr>
					<th><?php esc_html_e( 'Titre', 'claira-tkd-parcours' ); ?></th>
					<th><?php esc_html_e( 'Questions', 'claira-tkd-parcours' ); ?></th>
					<th><?php esc_html_e( 'Durée', 'claira-tkd-parcours' ); ?></th>
					<th><?php esc_html_e( 'Créé le', 'claira-tkd-parcours' ); ?></th>
					<th><?php esc_html_e( 'Lien pour les candidats', 'claira-tkd-parcours' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( array_reverse( $examens, true ) as $code => $ex ) : ?>
					<?php $lien = $page_url ? add_query_arg( 'examen', $code, $page_url ) : ''; ?>
					<tr>
						<td><strong><?php echo esc_html( $ex['titre'] ); ?></strong></td>
						<td><?php echo esc_html( (string) count( $ex['ids'] ) ); ?></td>
						<td><?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min', 'claira-tkd-parcours' ), (int) $ex['duree'] ) ); ?></td>
						<td><?php echo esc_html( wp_date( 'd/m/Y', (int) $ex['cree'] ) ); ?></td>
						<td>
							<?php if ( $lien ) : ?>
								<input type="text" readonly class="regular-text claira-qcm-lien" value="<?php echo esc_url( $lien ); ?>" onfocus="this.select()">
								<a class="button" href="<?php echo esc_url( $lien ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ouvrir', 'claira-tkd-parcours' ); ?></a>
							<?php else : ?>
								<code>?examen=<?php echo esc_html( $code ); ?></code>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button claira-qcm-charger" data-ids="<?php echo esc_attr( implode( ',', $ex['ids'] ) ); ?>"><?php esc_html_e( 'Recharger la sélection', 'claira-tkd-parcours' ); ?></button>
							<form method="post" action="<?php echo esc_url( claira_tkd_qcm_admin_url() ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer cet examen ? Son lien ne fonctionnera plus.', 'claira-tkd-parcours' ) ); ?>');">
								<?php wp_nonce_field( 'claira_tkd_qcm_action', 'claira_tkd_qcm_nonce' ); ?>
								<input type="hidden" name="claira_tkd_qcm_action" value="supprimer">
								<input type="hidden" name="code" value="<?php echo esc_attr( $code ); ?>">
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Supprimer', 'claira-tkd-parcours' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( "Source : quiz « Module B » publiés par la FFTDA (fftda.fr > Réglementation). L'épreuve officielle se passe sur l'application IzziDan : 30 questions en 15 minutes (15 questions pour les 5e et 6e Dan), une seule tentative.", 'claira-tkd-parcours' ); ?>
		</p>
	</div>
	<?php
}
