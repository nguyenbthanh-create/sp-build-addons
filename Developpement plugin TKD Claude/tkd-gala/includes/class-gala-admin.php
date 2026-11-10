<?php
/**
 * Écran d'administration « Gala » (réservé à manage_options) :
 *   Inscriptions  totaux, liste des clubs, repas payés, détail d'un club, suppression
 *   Conducteur    ordre de passage des démonstrations, heures calculées, écoute des pistes
 *   Réglages      édition, ouverture, date limite, limites, couleurs, textes, purge
 * Exports : CSV des démonstrateurs, CSV du conducteur, ZIP des pistes audio.
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Admin {

	private static ?self $instance = null;
	const CAP  = 'manage_options';
	const SLUG = 'tkd-gala';

	public static function get_instance(): self {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		foreach ( [ 'reglages', 'paye', 'supprimer', 'ordre', 'export', 'zip', 'purger', 'candidatures', 'annonceur', 'annonceur_supprimer', 'passage' ] as $a ) {
			add_action( 'admin_post_tkd_gala_' . $a, [ $this, 'action_' . $a ] );
		}
	}

	public function menu(): void {
		add_menu_page( 'Gala', 'Gala', self::CAP, self::SLUG, [ $this, 'page' ], 'dashicons-awards', 58 );
	}

	private static function url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	private static function garde( string $action ): void {
		if ( ! current_user_can( self::CAP ) ) wp_die( 'Accès refusé', 403 );
		check_admin_referer( 'tkd_gala_' . $action );
	}

	private static function form_debut( string $action, string $attrs = '' ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- attributs écrits ici
		echo '<input type="hidden" name="action" value="tkd_gala_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'tkd_gala_' . $action );
	}

	// ─── Page ───────────────────────────────────────────────────────────────────

	public function page(): void {
		if ( ! current_user_can( self::CAP ) ) wp_die( 'Accès refusé' );
		$r      = Tkd_Gala_DB::reglages();
		$onglet = sanitize_key( $_GET['onglet'] ?? 'inscriptions' );
		$onglets = [ 'inscriptions' => '🥋 Inscriptions', 'candidatures' => '🌐 Candidatures', 'conducteur' => '🎬 Conducteur', 'reglages' => '⚙️ Réglages' ];
		wp_enqueue_style( 'tkd-gala-admin', TKD_GALA_URL . 'assets/gala-admin.css', [], TKD_GALA_VERSION . '-' . filemtime( TKD_GALA_DIR . 'assets/gala-admin.css' ) );

		echo '<div class="wrap tkd-gala-admin"><h1>' . esc_html( $r['titre'] . ' ' . $r['edition'] ) . '</h1>';
		$this->notices( $r );
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $onglets as $k => $l ) echo '<a class="nav-tab' . ( $onglet === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( [ 'onglet' => $k ] ) ) . '">' . esc_html( $l ) . '</a>';
		echo '</nav>';

		if ( $onglet === 'reglages' )       $this->onglet_reglages( $r );
		elseif ( $onglet === 'conducteur' ) $this->onglet_conducteur( $r );
		elseif ( $onglet === 'candidatures' ) $this->onglet_candidatures( $r );
		elseif ( ! empty( $_GET['inscription'] ) ) $this->detail( intval( $_GET['inscription'] ), $r );
		else                                 $this->onglet_inscriptions( $r );
		echo '</div>';
	}

	private function notices( array $r ): void {
		$msgs = [ 'reglages' => 'Réglages enregistrés.', 'paye' => 'Inscription mise à jour.', 'supprime' => 'Inscription supprimée.', 'ordre' => 'Conducteur enregistré.', 'candidatures' => 'Candidatures enregistrées.',
			'annonceur' => 'Annonceur enregistré.', 'annonceur_supprime' => 'Annonceur et ses passages supprimés.', 'passage' => 'Passage ajouté en fin de conducteur : donnez-lui son numéro d\'ordre.' ];
		$ko   = intval( $_GET['audio_ko'] ?? 0 );
		if ( $ko ) echo '<div class="notice notice-error"><p>' . intval( $ko ) . ' piste(s) audio refusée(s) : ' . esc_html( sanitize_text_field( wp_unslash( $_GET['audio_err'] ?? '' ) ) ) . '</p></div>';
		$m    = sanitize_key( $_GET['fait'] ?? '' );
		if ( isset( $msgs[ $m ] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $m ] ) . '</p></div>';
		if ( $m === 'purge' ) echo '<div class="notice notice-success"><p>' . intval( $_GET['n'] ?? 0 ) . ' inscription(s) et leurs pistes audio supprimées.</p></div>';

		$page = Tkd_Gala_Front::url_page();
		if ( $page === home_url( '/' ) ) {
			echo '<div class="notice notice-warning"><p>Aucune page publiée ne contient le shortcode <code>[tkd_gala_inscription]</code> : créez-la, c\'est son adresse qui part dans les mails.</p></div>';
		}
		if ( empty( $r['ouvert'] ) ) echo '<div class="notice notice-info"><p>Les inscriptions sont <strong>fermées</strong> aux nouveaux clubs (onglet Réglages).</p></div>';
		elseif ( ! Tkd_Gala_DB::modifiable() ) echo '<div class="notice notice-info"><p>Date limite passée : les clubs ne peuvent plus s\'inscrire ni modifier.</p></div>';
	}

	// ─── Inscriptions ───────────────────────────────────────────────────────────

	private function onglet_inscriptions( array $r ): void {
		$liste = Tkd_Gala_DB::inscriptions( $r['edition'] );
		$tot   = [ 'participants' => 0, 'repas' => 0, 'montant' => 0, 'demos' => 0, 'sans_audio' => 0, 'planches' => 0 ];
		$par   = [];
		foreach ( $liste as $i ) {
			$par[ $i->id ] = Tkd_Gala_Mails::totaux( $i );
			foreach ( $tot as $k => $v ) $tot[ $k ] = $v + $par[ $i->id ][ $k ];
		}
		$duree = array_sum( array_map( static fn( $d ) => (int) $d->duree_sec, Tkd_Gala_DB::demos_edition( $r['edition'] ) ) ); // clubs de taekwondo seulement (le total soirée est dans le Conducteur)

		echo '<div class="tkd-gala-cartes">';
		foreach ( [
			[ count( $liste ), 'clubs' ], [ $tot['participants'], 'démonstrateurs' ], [ $tot['repas'], 'repas (' . Tkd_Gala_Front::montant( (float) $tot['montant'] ) . ')' ],
			[ $tot['demos'], 'démonstrations' ], [ Tkd_Gala_Front::duree( $duree ), 'de démonstrations' ], [ $tot['planches'], 'planches' ], [ $tot['sans_audio'], 'piste(s) audio manquante(s)' ],
		] as $c ) {
			echo '<div class="tkd-gala-carte"><strong>' . esc_html( (string) $c[0] ) . '</strong><span>' . esc_html( $c[1] ) . '</span></div>';
		}
		echo '</div>';

		echo '<p>';
		foreach ( [ 'participants' => '⬇️ Démonstrateurs et repas (CSV)', 'conducteur' => '⬇️ Démonstrations (CSV)' ] as $type => $l ) {
			echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tkd_gala_export&type=' . $type ), 'tkd_gala_export' ) ) . '">' . esc_html( $l ) . '</a> ';
		}
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tkd_gala_zip' ), 'tkd_gala_zip' ) ) . '">⬇️ Toutes les pistes audio (ZIP)</a>';
		echo ' &nbsp; <a href="' . esc_url( Tkd_Gala_Front::url_page() ) . '" target="_blank" rel="noopener">Voir le formulaire public ↗</a></p>';

		if ( ! $liste ) { echo '<p>Aucune inscription pour l\'édition ' . esc_html( $r['edition'] ) . '.</p>'; return; }

		echo '<table class="widefat striped"><thead><tr><th>Club</th><th>Représentant</th><th>Contact</th><th>Démonstrateurs</th><th>Repas</th><th>Démonstrations</th><th>Repas payés</th><th>Modifiée le</th><th></th></tr></thead><tbody>';
		foreach ( $liste as $i ) {
			$t = $par[ $i->id ];
			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( self::url( [ 'inscription' => $i->id ] ) ) . '">' . esc_html( $i->club_nom ) . '</a></strong>' . ( $i->affiliation !== '' ? '<br><span class="description">FFTDA ' . esc_html( $i->affiliation ) . '</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $i->representant ) . '<br><span class="description">' . esc_html( $i->role ) . '</span></td>';
			echo '<td><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $i->telephone ) ) . '">' . esc_html( $i->telephone ) . '</a><br><a href="mailto:' . esc_attr( $i->email ) . '">' . esc_html( $i->email ) . '</a></td>';
			echo '<td>' . intval( $t['participants'] ) . '</td>';
			echo '<td>' . intval( $t['repas'] ) . ( $t['repas'] ? ' <span class="description">(' . esc_html( Tkd_Gala_Front::montant( (float) $t['montant'] ) ) . ')</span>' : '' ) . '</td>';
			echo '<td>' . intval( $t['demos'] ) . ( $t['sans_audio'] ? ' <span class="tkd-gala-alerte-txt">· ' . intval( $t['sans_audio'] ) . ' sans audio</span>' : '' ) . '</td>';
			echo '<td>' . ( $t['repas'] ? ( (int) $i->paye ? '✅ Payé' : '⏳ À encaisser' ) : '—' ) . '</td>';
			echo '<td>' . esc_html( mysql2date( 'd/m/Y H:i', $i->updated_at ) ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( self::url( [ 'inscription' => $i->id ] ) ) . '">Détail</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function detail( int $id, array $r ): void {
		$i = Tkd_Gala_DB::inscription( $id );
		echo '<p><a href="' . esc_url( self::url() ) . '">← Toutes les inscriptions</a></p>';
		if ( ! $i ) { echo '<p>Inscription introuvable.</p>'; return; }
		$t = Tkd_Gala_Mails::totaux( $i );

		echo '<div class="tkd-gala-detail"><div class="tkd-gala-col">';
		echo '<h2>' . esc_html( $i->club_nom ) . '</h2>';
		echo '<p>' . esc_html( $i->representant . ' — ' . $i->role ) . '<br>📞 ' . esc_html( $i->telephone ) . ' · ✉️ <a href="mailto:' . esc_attr( $i->email ) . '">' . esc_html( $i->email ) . '</a>'
			. ( $i->affiliation !== '' ? '<br>Affiliation FFTDA : ' . esc_html( $i->affiliation ) : '' ) . '</p>';
		echo '<p class="description">Inscrit le ' . esc_html( mysql2date( 'd/m/Y H:i', $i->created_at ) ) . ', modifié le ' . esc_html( mysql2date( 'd/m/Y H:i', $i->updated_at ) ) . '.<br>'
			. ( empty( $i->reglement ) ? '⚠️ Règlement des démonstrations pas encore accepté (inscription antérieure à la case).' : '✅ Règlement des démonstrations accepté le ' . esc_html( mysql2date( 'd/m/Y H:i', $i->reglement ) ) . '.' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( Tkd_Gala_Front::lien_club( $i->token ) ) . '" target="_blank" rel="noopener">✏️ Modifier avec le formulaire du club ↗</a></p>';
		echo '<p class="description">Lien personnel du club (à lui renvoyer si besoin) :<br><input type="text" readonly class="large-text" value="' . esc_attr( Tkd_Gala_Front::lien_club( $i->token ) ) . '" onclick="this.select()"></p>';

		echo '<h3>Démonstrateurs (' . intval( $t['participants'] ) . ') — ' . intval( $t['repas'] ) . ' repas, ' . esc_html( Tkd_Gala_Front::montant( (float) $t['montant'] ) ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>Nom</th><th>Prénom</th><th>Né(e) le</th><th>Repas</th></tr></thead><tbody>';
		foreach ( Tkd_Gala_DB::participants( $id ) as $p ) {
			echo '<tr><td>' . esc_html( $p->nom ) . '</td><td>' . esc_html( $p->prenom ) . '</td><td>' . esc_html( Tkd_Gala_Front::date_fr( (string) $p->date_naissance ) ) . '</td><td>' . ( (int) $p->repas ? '🍽️ Oui' : 'Non' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3>Démonstration</h3>';
		foreach ( Tkd_Gala_DB::demos( $id ) as $d ) $this->carte_demo( $d );
		echo '</div><div class="tkd-gala-col tkd-gala-col-etroite">';

		echo '<div class="postbox"><div class="inside"><h3>Suivi</h3>';
		self::form_debut( 'paye' );
		echo '<input type="hidden" name="id" value="' . intval( $id ) . '">';
		echo '<p><label><input type="checkbox" name="paye" value="1"' . checked( (int) $i->paye, 1, false ) . '> Repas payés (' . esc_html( Tkd_Gala_Front::montant( (float) $t['montant'] ) ) . ')</label></p>';
		echo '<p><label>Note interne<br><textarea name="note_admin" rows="4" class="large-text">' . esc_textarea( (string) $i->note_admin ) . '</textarea></label></p>';
		submit_button( 'Enregistrer', 'primary', 'submit', false );
		echo '</form></div></div>';

		echo '<div class="postbox"><div class="inside"><h3>Supprimer</h3><p class="description">Supprime l\'inscription, ses démonstrateurs, ses démonstrations et ses pistes audio. Irréversible.</p>';
		self::form_debut( 'supprimer', 'onsubmit="return confirm(\'Supprimer définitivement l\\\'inscription de ce club ?\')"' );
		echo '<input type="hidden" name="id" value="' . intval( $id ) . '">';
		submit_button( 'Supprimer l\'inscription', 'delete', 'submit', false );
		echo '</form></div></div>';
		echo '</div></div>';
	}

	private function carte_demo( object $d ): void {
		echo '<div class="tkd-gala-demo-admin"><strong>⏱️ ' . esc_html( Tkd_Gala_Front::duree( (int) $d->duree_sec ) ) . '</strong>'
			. ' · 🎨 ' . esc_html( $d->couleur1 . ' / ' . $d->couleur2 ) . ' · 🪵 ' . intval( $d->planches ) . ' planche(s)<br>';
		echo $this->audio( $d ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans audio()
	}

	/** Lecteur + téléchargement ; $d est une démonstration de club ou une ligne du conducteur commun. */
	private function audio( object $d ): string {
		if ( (string) $d->audio_fichier === '' ) return '<span class="tkd-gala-alerte-txt">⚠️ Pas de piste audio</span>';
		$url = ( $d->type ?? 'tkd' ) === 'ext' ? Tkd_Gala_Audio::url_ecoute_externe( intval( $d->id ) ) : Tkd_Gala_Audio::url_ecoute( intval( $d->id ) );
		return '<audio controls preload="none" src="' . esc_url( $url ) . '"></audio> <a href="' . esc_url( add_query_arg( 'telecharger', '1', $url ) ) . '">⬇️ ' . esc_html( $d->audio_nom ) . '</a> <span class="description">(' . esc_html( size_format( (int) $d->audio_taille ) ) . ')</span>';
	}

	// ─── Conducteur (clubs de taekwondo + candidatures retenues) ───────────────

	/** Heures de passage : [ cle => 'HH:MM' ], d'après l'heure de début, les durées et le battement. */
	public static function heures( array $demos, array $r ): array {
		$h = [];
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $r['heure_debut'], $m ) ) return $h;
		$t = intval( $m[1] ) * 3600 + intval( $m[2] ) * 60;
		foreach ( $demos as $d ) {
			$h[ $d->cle ] = sprintf( '%02d:%02d', intdiv( $t, 3600 ) % 24, intdiv( $t % 3600, 60 ) );
			$t += intval( $d->duree_sec ) + intval( $r['battement_sec'] );
		}
		return $h;
	}

	private function onglet_conducteur( array $r ): void {
		wp_enqueue_media();
		wp_enqueue_script( 'tkd-gala-admin', TKD_GALA_URL . 'assets/gala-admin.js', [ 'jquery' ], TKD_GALA_VERSION . '-' . filemtime( TKD_GALA_DIR . 'assets/gala-admin.js' ), true );

		$lignes = Tkd_Gala_Candidatures::conducteur( $r['edition'] );
		$heures = self::heures( $lignes, $r );
		$total  = array_sum( array_map( static fn( $d ) => (int) $d->duree_sec, $lignes ) ) + max( 0, count( $lignes ) - 1 ) * intval( $r['battement_sec'] );
		$nb_ann = count( array_filter( $lignes, static fn( $d ) => $d->type === 'ann' ) );
		echo '<p class="description">Le déroulé de la soirée : 🥋 clubs de taekwondo (onglet Inscriptions), 🌐 autres disciplines retenues (onglet Candidatures) et 📣 passages d\'annonceurs (plus bas). Numérotez-les dans l\'ordre de passage puis enregistrez. Heures calculées à partir de ' . esc_html( $r['heure_debut'] ) . ', avec ' . intval( $r['battement_sec'] ) . ' s entre deux passages (onglet Réglages). Sans numéro : à la fin.</p>';

		if ( $lignes ) {
			echo '<p class="tkd-gala-projection-liens"><a class="button button-secondary" href="' . esc_url( Tkd_Gala_Projection::url( 'regie' ) ) . '" target="_blank" rel="noopener">🎛️ Projection : écran régie</a> '
				. '<a class="button button-secondary" href="' . esc_url( Tkd_Gala_Projection::url( 'salle' ) ) . '" target="_blank" rel="noopener">📽️ Projection : écran salle</a> '
				. '<span class="description">La régie pilote l\'écran salle quand les deux sont ouverts sur le même ordinateur.</span></p>';
			echo '<p><strong>' . intval( count( $lignes ) - $nb_ann ) . ' démonstration(s)' . ( $nb_ann ? ', ' . intval( $nb_ann ) . ' annonce(s)' : '' ) . ', ' . esc_html( Tkd_Gala_Front::duree( $total ) ) . ' au total</strong> (battements compris)' . ( isset( $heures[ $lignes[ count( $lignes ) - 1 ]->cle ] ) ? ', fin vers ' . esc_html( self::heure_fin( $lignes, $heures, $r ) ) : '' ) . '.</p>';

			self::form_debut( 'ordre' );
			echo '<table class="widefat striped tkd-gala-conducteur"><thead><tr><th>Ordre</th><th>Heure</th><th></th><th>Club / structure / annonceur</th><th>Démonstration / annonce</th><th>Durée</th><th>Couleurs</th><th>Planches</th><th>Piste audio</th></tr></thead><tbody>';
			foreach ( $lignes as $d ) {
				$ext = $d->type === 'ext';
				$ann = $d->type === 'ann';
				echo '<tr' . ( $ann ? ' class="tkd-gala-annonce"' : '' ) . '><td><input type="number" min="1" step="1" name="ordre[' . esc_attr( $d->cle ) . ']" value="' . esc_attr( $d->ordre_passage === null ? '' : (string) $d->ordre_passage ) . '" class="small-text"></td>';
				echo '<td><strong>' . esc_html( $heures[ $d->cle ] ?? '' ) . '</strong></td>';
				echo '<td title="' . ( $ann ? 'Annonceur' : ( $ext ? 'Autre discipline' : 'Taekwondo' ) ) . '">' . ( $ann ? '📣' : ( $ext ? '🌐' : '🥋' ) ) . '</td>';
				if ( $ann ) {
					echo '<td>' . ( $d->logo_url !== '' ? '<img class="tkd-gala-logo-mini" src="' . esc_url( $d->logo_url ) . '" alt=""> ' : '' ) . '<strong>' . esc_html( $d->club_nom ) . '</strong></td>';
					echo '<td>' . ( $d->slogan !== '' ? '<span class="tkd-gala-slogan">' . esc_html( $d->slogan ) . '</span>' : '' )
						. ( $d->message !== '' ? '<div class="tkd-gala-message">' . nl2br( esc_html( $d->message ) ) . '</div>' : '' ) . '</td>';
					echo '<td><input type="number" min="0" step="5" name="duree[' . esc_attr( $d->cle ) . ']" value="' . intval( $d->duree_sec ) . '" class="small-text" aria-label="Durée en secondes"> s</td>';
					echo '<td colspan="2"></td><td><label class="tkd-gala-retirer"><input type="checkbox" name="retirer[' . esc_attr( $d->cle ) . ']" value="1"> Retirer ce passage</label></td></tr>';
					continue;
				}
				echo '<td>' . esc_html( $d->club_nom ) . ( $ext && $d->discipline !== '' ? '<br><span class="description">' . esc_html( $d->discipline ) . '</span>' : '' ) . '</td>';
				echo '<td>' . esc_html( $d->titre !== '' ? $d->titre : '—' ) . ( $ext && $d->participants ? '<br><span class="description">' . intval( $d->participants ) . ' participant(s)</span>' : '' ) . '</td>';
				echo '<td>' . esc_html( Tkd_Gala_Front::duree( (int) $d->duree_sec ) ) . '</td>';
				// Couleurs : clubs et, depuis le 11/11/2026, candidatures (champs du formulaire Elementor).
				echo '<td>' . ( (string) $d->couleur1 !== '' ? esc_html( $d->couleur1 . ' / ' . $d->couleur2 ) : '—' ) . '</td><td>' . ( $ext ? '—' : intval( $d->planches ) ) . '</td>';
				echo '<td>' . $this->audio( $d ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans audio()
			}
			echo '</tbody></table><p>';
			submit_button( 'Enregistrer l\'ordre, les durées et les retraits', 'primary', 'submit', false );
			echo ' <button type="button" class="button" onclick="window.print()">🖨️ Imprimer</button></p></form>';
		} else {
			echo '<p>Aucune démonstration ni annonce pour l\'instant.</p>';
		}

		$this->bloc_annonceurs( $r );
	}

	/** Annonceurs de l'édition : liste, ajout d'un passage, fiche (création / modification). */
	private function bloc_annonceurs( array $r ): void {
		$liste = Tkd_Gala_Annonceurs::annonceurs( $r['edition'] );
		$edit  = isset( $_GET['annonceur'] ) ? Tkd_Gala_Annonceurs::annonceur( intval( $_GET['annonceur'] ) ) : null;
		if ( $edit && (string) $edit->edition !== (string) $r['edition'] ) $edit = null;

		echo '<div class="tkd-gala-annonceurs"><h2 id="annonceurs">📣 Annonceurs</h2>';
		echo '<p class="description">Partenaires annoncés pendant la soirée. Le <strong>slogan</strong> est projeté avec le logo sur l\'écran salle ; le <strong>message</strong> est lu par l\'animateur (écran régie et conducteur imprimé). Un annonceur peut passer plusieurs fois : « ➕ Passage » ajoute une ligne au conducteur, à numéroter.</p>';
		if ( $liste ) {
			echo '<table class="widefat striped"><thead><tr><th>Logo</th><th>Annonceur</th><th>Slogan</th><th>Message</th><th>Passages</th><th></th></tr></thead><tbody>';
			foreach ( $liste as $a ) {
				$logo = Tkd_Gala_Annonceurs::logo_url( intval( $a->logo_id ), 'thumbnail' );
				echo '<tr><td>' . ( $logo !== '' ? '<img class="tkd-gala-logo-mini" src="' . esc_url( $logo ) . '" alt="">' : '—' ) . '</td>';
				echo '<td><strong>' . esc_html( $a->nom ) . '</strong></td><td><span class="tkd-gala-slogan">' . esc_html( $a->slogan ) . '</span></td>';
				echo '<td class="description">' . esc_html( mb_strimwidth( (string) $a->message, 0, 140, '…' ) ) . '</td><td>' . intval( $a->nb_passages ) . '</td><td class="tkd-gala-actions-annonceur">';
				self::form_debut( 'passage', 'class="tkd-gala-inline"' );
				echo '<input type="hidden" name="id" value="' . intval( $a->id ) . '"><button type="submit" class="button button-small">➕ Passage</button></form> ';
				echo '<a class="button button-small" href="' . esc_url( self::url( [ 'onglet' => 'conducteur', 'annonceur' => $a->id ] ) . '#fiche-annonceur' ) . '">Modifier</a> ';
				self::form_debut( 'annonceur_supprimer', 'class="tkd-gala-inline" onsubmit="return confirm(\'Supprimer cet annonceur et tous ses passages ?\')"' );
				echo '<input type="hidden" name="id" value="' . intval( $a->id ) . '"><button type="submit" class="button button-small button-link-delete">Supprimer</button></form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		$logo_id  = $edit ? intval( $edit->logo_id ) : 0;
		$logo_url = Tkd_Gala_Annonceurs::logo_url( $logo_id, 'medium' );
		echo '<h3 id="fiche-annonceur">' . ( $edit ? 'Modifier « ' . esc_html( $edit->nom ) . ' »' : 'Ajouter un annonceur' ) . '</h3>';
		self::form_debut( 'annonceur', 'class="tkd-gala-fiche-annonceur"' );
		echo '<input type="hidden" name="id" value="' . intval( $edit->id ?? 0 ) . '"><table class="form-table" role="presentation">';
		$sponsors = Tkd_Gala_Annonceurs::sponsors_sp_compta();
		echo '<tr><th scope="row"><label for="tg-ann-nom">Nom</label></th><td><input type="text" id="tg-ann-nom" name="nom" class="regular-text" required maxlength="150" value="' . esc_attr( $edit->nom ?? '' ) . '"' . ( $sponsors ? ' list="tg-sponsors"' : '' ) . '>';
		if ( $sponsors ) {
			echo '<datalist id="tg-sponsors">';
			foreach ( $sponsors as $s ) echo '<option value="' . esc_attr( $s ) . '">';
			echo '</datalist><p class="description">Commencez à taper : les sponsors enregistrés dans SP Compta sont proposés.</p>';
		}
		echo '</td></tr>';
		echo '<tr><th scope="row">Logo</th><td><div class="tkd-gala-logo-choix"><img class="tkd-gala-logo-apercu" src="' . esc_url( $logo_url ) . '" alt=""' . ( $logo_url === '' ? ' hidden' : '' ) . '>'
			. '<input type="hidden" name="logo_id" value="' . intval( $logo_id ) . '"> <button type="button" class="button" data-tkd-gala="logo-choisir">Choisir dans la médiathèque</button> '
			. '<button type="button" class="button-link" data-tkd-gala="logo-retirer"' . ( $logo_id ? '' : ' hidden' ) . '>Retirer</button></div>'
			. '<p class="description">Image de bonne taille (PNG à fond transparent de préférence) : elle est projetée en grand.</p></td></tr>';
		echo '<tr><th scope="row"><label for="tg-ann-slogan">Slogan</label></th><td><input type="text" id="tg-ann-slogan" name="slogan" class="large-text" maxlength="255" value="' . esc_attr( $edit->slogan ?? '' ) . '"><p class="description">Court : projeté sous le logo.</p></td></tr>';
		echo '<tr><th scope="row"><label for="tg-ann-message">Message d\'annonce</label></th><td><textarea id="tg-ann-message" name="message" rows="5" class="large-text">' . esc_textarea( (string) ( $edit->message ?? '' ) ) . '</textarea><p class="description">Texte lu par l\'animateur.</p></td></tr>';
		if ( ! $edit ) {
			echo '<tr><th scope="row">Passage</th><td><label><input type="checkbox" name="premier_passage" value="1" checked> Ajouter tout de suite un passage au conducteur (' . intval( $r['duree_annonce'] ) . ' s, durée modifiable dans le tableau)</label></td></tr>';
		}
		echo '</table><p>';
		submit_button( $edit ? 'Enregistrer l\'annonceur' : 'Ajouter l\'annonceur', 'primary', 'submit', false );
		if ( $edit ) echo ' <a class="button" href="' . esc_url( self::url( [ 'onglet' => 'conducteur' ] ) . '#annonceurs' ) . '">Annuler</a>';
		echo '</p></form></div>';
	}

	public function action_annonceur(): void {
		self::garde( 'annonceur' );
		$r       = Tkd_Gala_DB::reglages();
		$id      = intval( $_POST['id'] ?? 0 );
		$nom     = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['nom'] ?? '' ) ) ), 0, 150 );
		$logo_id = absint( $_POST['logo_id'] ?? 0 );
		if ( $logo_id && ! wp_attachment_is_image( $logo_id ) ) $logo_id = 0;
		if ( $nom === '' ) wp_die( 'Le nom de l\'annonceur est obligatoire.', '', [ 'back_link' => true ] );
		$existant = $id ? Tkd_Gala_Annonceurs::annonceur( $id ) : null;
		if ( $existant && (string) $existant->edition !== (string) $r['edition'] ) $id = 0;
		$nouveau = ! $existant;
		$id = Tkd_Gala_Annonceurs::enregistrer( $existant ? $id : 0, [
			'nom'     => $nom,
			'logo_id' => $logo_id,
			'slogan'  => mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['slogan'] ?? '' ) ) ), 0, 255 ),
			'message' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) ),
		], (string) $r['edition'] );
		if ( $nouveau && ! empty( $_POST['premier_passage'] ) ) Tkd_Gala_Annonceurs::ajouter_passage( $id, intval( $r['duree_annonce'] ) );
		wp_safe_redirect( self::url( [ 'onglet' => 'conducteur', 'fait' => 'annonceur' ] ) . '#annonceurs' );
		exit;
	}

	public function action_annonceur_supprimer(): void {
		self::garde( 'annonceur_supprimer' );
		$a = Tkd_Gala_Annonceurs::annonceur( intval( $_POST['id'] ?? 0 ) );
		if ( $a ) Tkd_Gala_Annonceurs::supprimer( intval( $a->id ) );
		wp_safe_redirect( self::url( [ 'onglet' => 'conducteur', 'fait' => 'annonceur_supprime' ] ) . '#annonceurs' );
		exit;
	}

	public function action_passage(): void {
		self::garde( 'passage' );
		Tkd_Gala_Annonceurs::ajouter_passage( intval( $_POST['id'] ?? 0 ), intval( Tkd_Gala_DB::reglages()['duree_annonce'] ) );
		wp_safe_redirect( self::url( [ 'onglet' => 'conducteur', 'fait' => 'passage' ] ) );
		exit;
	}

	private static function heure_fin( array $demos, array $heures, array $r ): string {
		$der = $demos[ count( $demos ) - 1 ];
		if ( ! preg_match( '/^(\d{2}):(\d{2})$/', (string) ( $heures[ $der->cle ] ?? '' ), $m ) ) return '';
		$t = intval( $m[1] ) * 3600 + intval( $m[2] ) * 60 + intval( $der->duree_sec );
		return sprintf( '%02d:%02d', intdiv( $t, 3600 ) % 24, intdiv( $t % 3600, 60 ) );
	}

	// ─── Candidatures hors taekwondo (formulaire Elementor) ────────────────────

	private function onglet_candidatures( array $r ): void {
		if ( ! Tkd_Gala_Candidatures::elementor_dispo() ) {
			echo '<p>Les envois de formulaires Elementor ne sont pas disponibles sur ce site (Elementor Pro inactif ou action « Collecter les envois » jamais utilisée).</p>';
			return;
		}
		$cands = Tkd_Gala_Candidatures::candidatures();
		$ext   = Tkd_Gala_Candidatures::externes( $r['edition'] );
		$nb_ret = count( array_filter( $ext, static fn( $x ) => (int) $x->retenue === 1 ) );
		echo '<p class="description">Envois du formulaire Elementor « ' . esc_html( $r['form_candidature'] ) . ' » (nom réglable dans l\'onglet Réglages), lus sans être modifiés : le détail complet reste dans <a href="' . esc_url( admin_url( 'admin.php?page=e-form-submissions' ) ) . '">Elementor → Envois</a>. Cochez les candidatures retenues : elles rejoignent le Conducteur. Leur piste audio, reçue plus tard, se dépose ici.</p>';
		if ( ! $cands ) { echo '<p>Aucune candidature pour l\'instant.</p>'; return; }
		echo '<p><strong>' . count( $cands ) . ' candidature(s), ' . intval( $nb_ret ) . ' retenue(s).</strong></p>';

		self::form_debut( 'candidatures', 'enctype="multipart/form-data"' );
		echo '<table class="widefat striped tkd-gala-candidatures"><thead><tr><th>Retenue</th><th>Structure</th><th>Discipline</th><th>Démonstration</th><th>Contact</th><th>Vidéo</th><th>Technique</th><th>Piste audio</th></tr></thead><tbody>';
		foreach ( $cands as $c ) {
			$v   = $c->v;
			$sid = intval( $c->id );
			$x   = $ext[ $sid ] ?? null;
			$ret = $x && (int) $x->retenue === 1;
			$g   = static fn( string $k ) => esc_html( (string) ( $v[ $k ] ?? '' ) );
			echo '<tr' . ( $ret ? ' class="tkd-gala-retenue"' : '' ) . '>';
			echo '<td><input type="hidden" name="toutes[]" value="' . intval( $sid ) . '"><label><input type="checkbox" name="retenue[' . intval( $sid ) . ']" value="1"' . checked( $ret, true, false ) . '> Retenue</label>'
				. '<br><a class="description" href="' . esc_url( admin_url( 'admin.php?page=e-form-submissions#/' . $sid ) ) . '">Envoi n° ' . intval( $sid ) . '</a><br><span class="description">' . esc_html( mysql2date( 'd/m/Y', $c->created_at ) ) . '</span></td>';
			echo '<td><strong>' . $g( 'struct_nom' ) . '</strong>' . ( ! empty( $v['struct_sigle'] ) ? ' (' . $g( 'struct_sigle' ) . ')' : '' ) . '<br><span class="description">' . $g( 'struct_cp_ville' ) . '</span></td>';
			echo '<td>' . $g( 'disc_nom' ) . ( ! empty( $v['disc_federation'] ) ? '<br><span class="description">' . $g( 'disc_federation' ) . '</span>' : '' ) . '</td>';
			echo '<td><strong>' . $g( 'demo_titre' ) . '</strong><br>' . intval( $v['demo_duree'] ?? 0 ) . ' min · ' . intval( $v['demo_participants'] ?? 0 ) . ' participant(s) · ' . $g( 'demo_ages' )
				. ( ! empty( $v['demo_couleur1'] ) ? '<br><span class="description">🎨 ' . $g( 'demo_couleur1' ) . ' / ' . $g( 'demo_couleur2' ) . '</span>' : '' ) . '</td>';
			echo '<td>' . $g( 'resp_nom' ) . ( ! empty( $v['resp_fonction'] ) ? ' — ' . $g( 'resp_fonction' ) : '' ) . '<br><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) ( $v['resp_tel'] ?? '' ) ) ) . '">' . $g( 'resp_tel' ) . '</a><br><a href="mailto:' . esc_attr( (string) ( $v['resp_email'] ?? '' ) ) . '">' . $g( 'resp_email' ) . '</a></td>';
			echo '<td>' . ( ! empty( $v['video_lien'] ) ? '<a href="' . esc_url( (string) $v['video_lien'] ) . '" target="_blank" rel="noopener noreferrer">▶️ ' . $g( 'video_plateforme' ) . '</a>' : '—' ) . '</td>';
			$tech = trim( implode( ' · ', array_filter( [ $v['tech_materiel'] ?? '', $v['tech_surface'] ?? '', $v['tech_besoins'] ?? '' ] ) ) );
			echo '<td class="description">' . esc_html( mb_strimwidth( $tech, 0, 160, '…' ) ) . '</td>';
			echo '<td>' . ( $x && (string) $x->audio_fichier !== '' ? $this->audio( (object) [ 'type' => 'ext', 'id' => $x->id, 'audio_fichier' => $x->audio_fichier, 'audio_nom' => $x->audio_nom, 'audio_taille' => $x->audio_taille ] ) . '<br>' : '' ) // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans audio()
				. ( $ret ? '<input type="file" name="audio_' . intval( $sid ) . '" accept=".mp3,.m4a,.aac,.wav,audio/*">' : '<span class="description">Après sélection</span>' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table><p>';
		submit_button( 'Enregistrer (sélection et pistes audio)', 'primary', 'submit', false );
		echo '</p></form>';
	}

	public function action_candidatures(): void {
		self::garde( 'candidatures' );
		$edition = (string) Tkd_Gala_DB::reglages()['edition'];
		$valides = [];
		foreach ( Tkd_Gala_Candidatures::candidatures() as $c ) $valides[ intval( $c->id ) ] = true;
		$retenues = array_map( 'intval', array_keys( (array) ( $_POST['retenue'] ?? [] ) ) );
		foreach ( array_map( 'intval', (array) ( $_POST['toutes'] ?? [] ) ) as $sid ) {
			if ( ! isset( $valides[ $sid ] ) ) continue;
			Tkd_Gala_Candidatures::definir_retenue( $sid, in_array( $sid, $retenues, true ), $edition );
		}
		$ko = 0; $err = '';
		foreach ( $retenues as $sid ) {
			$f = $_FILES[ 'audio_' . $sid ] ?? null;
			if ( ! isset( $valides[ $sid ] ) || ! $f || intval( $f['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE ) continue;
			$e = Tkd_Gala_Audio::verifier( $f );
			$x = Tkd_Gala_Candidatures::assurer_externe( $sid, $edition );
			if ( $e !== '' || ! Tkd_Gala_Audio::enregistrer_envoi_externe( $f, intval( $x->id ) ) ) { $ko++; $err = $e !== '' ? $e : 'enregistrement impossible sur le serveur.'; }
		}
		$args = [ 'onglet' => 'candidatures', 'fait' => 'candidatures' ];
		if ( $ko ) $args += [ 'audio_ko' => $ko, 'audio_err' => rawurlencode( $err ) ];
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	// ─── Réglages ───────────────────────────────────────────────────────────────

	private function onglet_reglages( array $r ): void {
		self::form_debut( 'reglages' );
		$ligne = static function ( string $label, string $html, string $aide = '' ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . ( $aide ? '<p class="description">' . esc_html( $aide ) . '</p>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- champs échappés ci-dessous
		};
		$in = static fn( string $k, string $type = 'text', string $extra = '' ) => '<input type="' . esc_attr( $type ) . '" name="r[' . esc_attr( $k ) . ']" value="' . esc_attr( (string) $r[ $k ] ) . '" ' . $extra . '>';
		echo '<table class="form-table" role="presentation">';
		$ligne( 'Titre', $in( 'titre', 'text', 'class="regular-text"' ) );
		$ligne( 'Édition', $in( 'edition', 'text', 'class="small-text"' ), 'Année du gala. Changer d\'édition repart d\'une liste vide (les inscriptions de l\'édition précédente restent en base jusqu\'à leur suppression).' );
		$ligne( 'Inscriptions ouvertes', '<label><input type="checkbox" name="r[ouvert]" value="1"' . checked( ! empty( $r['ouvert'] ), true, false ) . '> Les nouveaux clubs peuvent s\'inscrire</label>' );
		$ligne( 'Date du gala', $in( 'date_gala', 'date' ) );
		$ligne( 'Date limite', $in( 'date_limite', 'date' ), 'Inscriptions et modifications possibles jusqu\'à ce jour inclus. Vide = sans limite.' );
		$ligne( 'Durée maximale', $in( 'duree_max', 'number', 'min="30" step="5" class="small-text"' ) . ' secondes', 'Par démonstration (300 = 5 minutes).' );
		$ligne( 'Planches au maximum', $in( 'planches_max', 'number', 'min="0" step="1" class="small-text"' ), 'Pour la démonstration du club (une planche par participant).' );
		$ligne( 'Prix du repas', $in( 'prix_repas', 'number', 'min="0" step="0.5" class="small-text"' ) . ' €' );
		$ligne( 'Piste audio : taille maximale', $in( 'audio_max_mo', 'number', 'min="1" step="1" class="small-text"' ) . ' Mo', 'Le serveur accepte au plus ' . size_format( wp_max_upload_size() ) . ' par envoi : la limite réelle est la plus petite des deux.' );
		$ligne( 'Mail de l\'organisation', $in( 'email_orga', 'email', 'class="regular-text"' ), 'Reçoit un mail à chaque inscription ou modification.' );
		$ligne( 'Conducteur : début', $in( 'heure_debut', 'time' ) );
		$ligne( 'Conducteur : battement', $in( 'battement_sec', 'number', 'min="0" step="5" class="small-text"' ) . ' secondes', 'Temps entre deux démonstrations.' );
		$ligne( 'Conducteur : durée d\'une annonce', $in( 'duree_annonce', 'number', 'min="0" step="5" class="small-text"' ) . ' secondes', 'Proposée pour chaque nouveau passage d\'annonceur ; modifiable ensuite passage par passage.' );
		$ligne( 'Couleurs proposées', '<textarea name="r[couleurs]" rows="8" class="regular-text">' . esc_textarea( (string) $r['couleurs'] ) . '</textarea>', 'Une couleur par ligne : à voir avec la personne qui gère l\'éclairage.' );
		$ligne( 'Paiement des repas', '<textarea name="r[paiement]" rows="2" class="large-text">' . esc_textarea( (string) $r['paiement'] ) . '</textarea>' );
		$ligne( 'Données personnelles', '<textarea name="r[texte_rgpd]" rows="3" class="large-text">' . esc_textarea( (string) $r['texte_rgpd'] ) . '</textarea>', 'Affiché au-dessus de la case d\'accord du formulaire.' );
		$ligne( 'Règlement des démonstrations', $in( 'url_reglement', 'url', 'class="large-text"' ), 'Adresse du PDF (Médias → le fichier → « Copier l\'URL »). Le formulaire affiche une case obligatoire « J\'accepte le règlement » avec ce lien. Vide : pas de case.' );
		$ligne( 'Formulaire des candidatures', $in( 'form_candidature', 'text', 'class="regular-text"' ), 'Nom exact du formulaire Elementor des autres disciplines (widget Formulaire → « Nom du formulaire ») : ses envois apparaissent dans l\'onglet Candidatures.' );
		echo '</table>';
		submit_button( 'Enregistrer les réglages' );
		echo '</form>';

		echo '<hr><h2>Après le gala : supprimer les données</h2><p class="description">Supprime toutes les inscriptions de l\'édition ' . esc_html( $r['edition'] ) . ' (clubs, démonstrateurs, démonstrations) et toutes les pistes audio. Irréversible : faites d\'abord vos exports.</p>';
		self::form_debut( 'purger', 'onsubmit="return confirm(\'Supprimer définitivement toutes les données de cette édition ?\')"' );
		echo '<p><label><input type="checkbox" name="confirmer" value="1" required> Je confirme la suppression de toutes les données de l\'édition ' . esc_html( $r['edition'] ) . '</label></p>';
		submit_button( 'Supprimer les données de l\'édition', 'delete', 'submit', false );
		echo '</form>';
	}

	// ─── Actions ────────────────────────────────────────────────────────────────

	public function action_reglages(): void {
		self::garde( 'reglages' );
		$in  = (array) wp_unslash( $_POST['r'] ?? [] );
		$txt = static fn( $k ) => sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
		$date = static fn( $k ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in[ $k ] ?? '' ) ) ? (string) $in[ $k ] : '';
		$couleurs = array_unique( array_filter( array_map( static fn( $c ) => mb_substr( sanitize_text_field( $c ), 0, 50 ), explode( "\n", (string) ( $in['couleurs'] ?? '' ) ) ) ) );
		Tkd_Gala_DB::enregistrer_reglages( [
			'titre'         => $txt( 'titre' ) ?: 'Gala des arts martiaux',
			'edition'       => mb_substr( $txt( 'edition' ), 0, 20 ) ?: gmdate( 'Y' ),
			'ouvert'        => empty( $in['ouvert'] ) ? 0 : 1,
			'date_gala'     => $date( 'date_gala' ),
			'date_limite'   => $date( 'date_limite' ),
			'duree_max'     => max( 30, intval( $in['duree_max'] ?? 300 ) ),
			'planches_max'  => max( 0, intval( $in['planches_max'] ?? 20 ) ),
			'prix_repas'    => max( 0, round( (float) str_replace( ',', '.', (string) ( $in['prix_repas'] ?? 5 ) ), 2 ) ),
			'audio_max_mo'  => max( 1, intval( $in['audio_max_mo'] ?? 20 ) ),
			'email_orga'    => is_email( $in['email_orga'] ?? '' ) ? sanitize_email( $in['email_orga'] ) : get_option( 'admin_email' ),
			'heure_debut'   => preg_match( '/^\d{1,2}:\d{2}$/', (string) ( $in['heure_debut'] ?? '' ) ) ? (string) $in['heure_debut'] : '14:00',
			'battement_sec' => max( 0, intval( $in['battement_sec'] ?? 60 ) ),
			'duree_annonce' => max( 0, intval( $in['duree_annonce'] ?? 30 ) ),
			'couleurs'      => implode( "\n", $couleurs ?: Tkd_Gala_DB::couleurs() ),
			'paiement'      => sanitize_textarea_field( (string) ( $in['paiement'] ?? '' ) ),
			'texte_rgpd'    => sanitize_textarea_field( (string) ( $in['texte_rgpd'] ?? '' ) ),
			'url_reglement' => esc_url_raw( trim( (string) ( $in['url_reglement'] ?? '' ) ) ),
			'form_candidature' => mb_substr( $txt( 'form_candidature' ), 0, 60 ) ?: 'Candidature Gala Arts Martiaux 2027',
		] );
		wp_safe_redirect( self::url( [ 'onglet' => 'reglages', 'fait' => 'reglages' ] ) );
		exit;
	}

	public function action_paye(): void {
		self::garde( 'paye' );
		global $wpdb;
		$id = intval( $_POST['id'] ?? 0 );
		$wpdb->update( Tkd_Gala_DB::t_inscriptions(), [
			'paye'       => empty( $_POST['paye'] ) ? 0 : 1,
			'note_admin' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['note_admin'] ?? '' ) ) ),
		], [ 'id' => $id ] );
		wp_safe_redirect( self::url( [ 'inscription' => $id, 'fait' => 'paye' ] ) );
		exit;
	}

	public function action_supprimer(): void {
		self::garde( 'supprimer' );
		Tkd_Gala_DB::supprimer_inscription( intval( $_POST['id'] ?? 0 ) );
		wp_safe_redirect( self::url( [ 'fait' => 'supprime' ] ) );
		exit;
	}

	public function action_ordre(): void {
		self::garde( 'ordre' );
		global $wpdb;
		$edition = Tkd_Gala_DB::reglages()['edition'];
		// Clés du conducteur : « d<id> » démonstration de club, « e<id> » candidature retenue,
		// « a<id> » passage d'annonceur (avec sa durée, et une case pour le retirer).
		$ok      = [];
		foreach ( Tkd_Gala_Candidatures::conducteur( $edition ) as $d ) $ok[ $d->cle ] = $d;
		$durees  = (array) wp_unslash( $_POST['duree'] ?? [] );
		$retirer = (array) wp_unslash( $_POST['retirer'] ?? [] );
		foreach ( (array) wp_unslash( $_POST['ordre'] ?? [] ) as $cle => $v ) {
			$cle = sanitize_key( (string) $cle );
			if ( ! isset( $ok[ $cle ] ) ) continue;
			$v     = trim( sanitize_text_field( (string) $v ) );
			$ordre = $v === '' ? null : max( 1, intval( $v ) );
			$type  = $ok[ $cle ]->type;
			if ( $type === 'ann' ) {
				if ( ! empty( $retirer[ $cle ] ) ) Tkd_Gala_Annonceurs::supprimer_passage( $ok[ $cle ]->id );
				else Tkd_Gala_Annonceurs::definir_passage( $ok[ $cle ]->id, $ordre, intval( $durees[ $cle ] ?? $ok[ $cle ]->duree_sec ) );
			} elseif ( $type === 'ext' ) {
				Tkd_Gala_Candidatures::definir_ordre( $ok[ $cle ]->id, $ordre );
			} else {
				$wpdb->update( Tkd_Gala_DB::t_demos(), [ 'ordre_passage' => $ordre ], [ 'id' => $ok[ $cle ]->id ] );
			}
		}
		wp_safe_redirect( self::url( [ 'onglet' => 'conducteur', 'fait' => 'ordre' ] ) );
		exit;
	}

	public function action_purger(): void {
		self::garde( 'purger' );
		if ( empty( $_POST['confirmer'] ) ) wp_die( 'Confirmation manquante.' );
		$n = Tkd_Gala_DB::purger_edition( Tkd_Gala_DB::reglages()['edition'] );
		wp_safe_redirect( self::url( [ 'onglet' => 'reglages', 'fait' => 'purge', 'n' => $n ] ) );
		exit;
	}

	/** CSV pour Excel (séparateur « ; », UTF-8 avec BOM). */
	private static function csv( string $nom, array $entetes, array $lignes ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $nom . '"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $entetes, ';' );
		foreach ( $lignes as $l ) {
			// Une cellule qui commence par = + - @ serait lue comme une formule par Excel.
			fputcsv( $out, array_map( static fn( $v ) => preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v, $l ), ';' );
		}
		fclose( $out );
		exit;
	}

	public function action_export(): void {
		if ( ! current_user_can( self::CAP ) ) wp_die( 'Accès refusé', 403 );
		check_admin_referer( 'tkd_gala_export' );
		$r = Tkd_Gala_DB::reglages();
		$e = sanitize_file_name( $r['edition'] );

		if ( sanitize_key( wp_unslash( $_GET['type'] ?? '' ) ) === 'conducteur' ) {
			$demos  = Tkd_Gala_Candidatures::conducteur( $r['edition'] ); // démonstrations + annonces
			$heures = self::heures( $demos, $r );
			$l = [];
			foreach ( $demos as $d ) {
				$ext = $d->type !== 'tkd';
				$ann = $d->type === 'ann';
				$l[] = [ $d->ordre_passage, $heures[ $d->cle ] ?? '', $d->discipline, $d->club_nom, $d->titre, Tkd_Gala_Front::duree( (int) $d->duree_sec ), $d->couleur1, $d->couleur2, $ext ? '' : $d->planches, $d->type === 'ext' ? $d->participants : '', $ann ? '' : ( (string) $d->audio_nom !== '' ? $d->audio_nom : 'MANQUANTE' ), $ann ? $d->message : '' ];
			}
			self::csv( 'gala-' . $e . '-conducteur.csv', [ 'Ordre', 'Heure', 'Discipline', 'Club / structure / annonceur', 'Démonstration / slogan', 'Durée', 'Couleur 1', 'Couleur 2', 'Planches', 'Participants', 'Piste audio', 'Message d\'annonce' ], $l );
		}

		$l = [];
		foreach ( Tkd_Gala_DB::participants_edition( $r['edition'] ) as $p ) {
			$l[] = [ $p->club_nom, $p->nom, $p->prenom, Tkd_Gala_Front::date_fr( (string) $p->date_naissance ), (int) $p->repas ? 'oui' : 'non' ];
		}
		self::csv( 'gala-' . $e . '-demonstrateurs.csv', [ 'Club', 'Nom', 'Prénom', 'Date de naissance', 'Repas' ], $l );
	}

	public function action_zip(): void {
		if ( ! current_user_can( self::CAP ) ) wp_die( 'Accès refusé', 403 );
		check_admin_referer( 'tkd_gala_zip' );
		if ( ! class_exists( 'ZipArchive' ) ) wp_die( 'L\'extension PHP « zip » n\'est pas disponible sur ce serveur : téléchargez les pistes une par une depuis le conducteur.' );
		$r     = Tkd_Gala_DB::reglages();
		$demos = Tkd_Gala_Candidatures::demos_conducteur( $r['edition'] ); // clubs + candidatures retenues
		$tmp   = wp_tempnam( 'tkd-gala' );
		$zip   = new ZipArchive();
		if ( $zip->open( $tmp, ZipArchive::OVERWRITE ) !== true ) wp_die( 'Impossible de créer l\'archive.' );
		$n = 0;
		foreach ( $demos as $pos => $d ) {
			$p = Tkd_Gala_Audio::chemin( $d );
			if ( $p === '' || ! is_file( $p ) ) continue;
			$num = $d->ordre_passage !== null ? intval( $d->ordre_passage ) : $pos + 1;
			$nom = sprintf( '%02d - %s%s.%s', $num, $d->club_nom, $d->titre !== '' ? ' - ' . $d->titre : '', strtolower( pathinfo( $p, PATHINFO_EXTENSION ) ) );
			$zip->addFile( $p, sanitize_file_name( remove_accents( $nom ) ) );
			$n++;
		}
		$zip->close();
		if ( ! $n ) { @unlink( $tmp ); wp_die( 'Aucune piste audio pour l\'instant.' ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="gala-' . sanitize_file_name( $r['edition'] ) . '-pistes-audio.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		while ( ob_get_level() ) ob_end_clean();
		readfile( $tmp );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}
}
