<?php
/**
 * Module « QCM Dan » : révision du QCU du Module B de l'examen Dan FFTDA.
 *
 * - Données : data/qcm-dan.json, extrait des 32 quiz officiels de la FFTDA
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

/** URL du fichier de données, versionnée par sa date de modification. */
function claira_tkd_qcm_data_url() {
	$fichier = CLAIRA_TKD_PLUGIN_DIR . 'data/qcm-dan.json';
	$version = file_exists( $fichier ) ? (string) filemtime( $fichier ) : CLAIRA_TKD_PLUGIN_VERSION;
	return add_query_arg( 'v', $version, CLAIRA_TKD_PLUGIN_URL . 'data/qcm-dan.json' );
}

function claira_tkd_qcm_enqueue() {
	wp_enqueue_style( 'claira-tkd-qcm', CLAIRA_TKD_PLUGIN_URL . 'assets/css/qcm-dan.css', array(), CLAIRA_TKD_PLUGIN_VERSION );
	wp_enqueue_script( 'claira-tkd-qcm', CLAIRA_TKD_PLUGIN_URL . 'assets/js/qcm-dan.js', array(), CLAIRA_TKD_PLUGIN_VERSION, true );
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

/** Page du site qui contient le shortcode (pour construire les liens d'examen). */
function claira_tkd_qcm_page_url() {
	$pages = get_posts( array(
		'post_type'      => array( 'page', 'post' ),
		'post_status'    => 'publish',
		's'              => '[claira_qcm_dan',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return $pages ? get_permalink( $pages[0] ) : '';
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
		'dataUrl'   => claira_tkd_qcm_data_url(),
		'questions' => max( 5, min( 100, absint( $atts['questions'] ) ) ),
		'duree'     => max( 1, min( 120, absint( $atts['duree'] ) ) ),
		'examen'    => null,
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
		'dataUrl' => claira_tkd_qcm_data_url(),
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
