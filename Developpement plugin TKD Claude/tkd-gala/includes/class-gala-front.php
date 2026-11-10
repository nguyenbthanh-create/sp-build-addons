<?php
/**
 * Formulaire public d'inscription au gala — shortcode [tkd_gala_inscription].
 *
 * Pas de compte WordPress : à l'envoi, le club reçoit par mail un lien personnel
 * (?gala=<jeton de 64 caractères>) qui rouvre le même formulaire prérempli. Tout reste
 * modifiable jusqu'à la date limite des réglages ; ensuite la page n'affiche plus qu'un
 * récapitulatif. Un administrateur connecté peut toujours modifier (correction tardive).
 *
 * Pas de nonce WordPress sur ce formulaire public : la page peut être servie depuis un
 * cache, ce qui rendrait le nonce périmé, et la modification d'une inscription exige
 * déjà le jeton secret envoyé dans le formulaire (une requête forgée ne le connaît pas).
 * Protection anti-robots : champ piège invisible + nombre d'envois limité par adresse IP.
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Front {

	private static ?self $instance = null;

	const MAX_PARTICIPANTS = 200;

	/** @var string[] erreurs de la dernière soumission (formulaire réaffiché avec la saisie) */
	private array $erreurs = [];
	private ?array $saisie = null;

	public static function get_instance(): self {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'tkd_gala_inscription', [ $this, 'render' ] );
		add_action( 'template_redirect', [ $this, 'traiter_envoi' ] );
	}

	// ─── Adresses ───────────────────────────────────────────────────────────────

	/** Page publiée qui porte le shortcode, '' si aucune. */
	public static function url_page(): string {
		global $wpdb;
		$id = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[tkd_gala_inscription%' LIMIT 1" );
		return $id ? (string) get_permalink( (int) $id ) : home_url( '/' );
	}

	public static function lien_club( string $token ): string {
		return add_query_arg( 'gala', $token, self::url_page() );
	}

	// ─── Traitement des envois (avant tout affichage, pour pouvoir rediriger) ──

	public function traiter_envoi(): void {
		if ( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) !== 'post' || empty( $_GET['gala_envoi'] ) ) return;

		// Envoi plus lourd que post_max_size : PHP vide entièrement $_POST et $_FILES.
		if ( empty( $_POST ) ) {
			$this->erreurs[] = 'L\'envoi est trop lourd pour le serveur (taille maximale : ' . size_format( wp_max_upload_size() ) . ' au total). Envoyez une piste audio plus légère (MP3 de préférence), ou une seule piste à la fois.';
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['tkd_gala_action'] ?? '' ) );
		if ( $action === 'lien' )        $this->traiter_lien_perdu();
		if ( $action === 'enregistrer' ) $this->traiter_inscription();
	}

	private static function ip(): string {
		return md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
	}

	/** Compte un envoi pour cette IP ; false si la limite horaire est atteinte. */
	private static function limite_ok( string $quoi, int $max ): bool {
		$cle = 'tkd_gala_' . $quoi . '_' . self::ip();
		$n   = intval( get_transient( $cle ) );
		if ( $n >= $max ) return false;
		set_transient( $cle, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	private function traiter_lien_perdu(): void {
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( empty( $_POST['site_web'] ) && is_email( $email ) && self::limite_ok( 'lien', 5 ) ) {
			foreach ( Tkd_Gala_DB::inscriptions_par_email( $email ) as $ins ) Tkd_Gala_Mails::lien( $ins );
		}
		// Même réponse que l'adresse soit connue ou non : on ne révèle pas qui est inscrit.
		wp_safe_redirect( add_query_arg( 'lien_envoye', '1', self::url_page() ) );
		exit;
	}

	private function traiter_inscription(): void {
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$ins   = $token !== '' ? Tkd_Gala_DB::inscription_par_token( $token ) : null;
		$admin = current_user_can( 'manage_options' );

		if ( $token !== '' && ! $ins ) { $this->erreurs[] = 'Lien invalide : utilisez le lien reçu par mail.'; return; }
		if ( $ins && ! Tkd_Gala_DB::modifiable() && ! $admin ) { $this->erreurs[] = 'La date limite est passée : l\'inscription ne peut plus être modifiée. Contactez l\'organisation.'; return; }
		if ( ! $ins && ! Tkd_Gala_DB::inscriptions_ouvertes() && ! $admin ) { $this->erreurs[] = 'Les inscriptions sont fermées.'; return; }

		// Champ piège rempli = robot : on fait comme si tout s'était bien passé.
		if ( ! empty( $_POST['site_web'] ) ) { wp_safe_redirect( self::url_page() ); exit; }
		if ( ! $ins && ! $admin && ! self::limite_ok( 'insc', 10 ) ) { $this->erreurs[] = 'Trop d\'envois depuis cette connexion. Réessayez dans une heure.'; return; }

		[ $data, $erreurs ] = self::valider( wp_unslash( $_POST ), $ins );

		// Pistes audio : contrôlées avant tout enregistrement.
		$fichiers = [];
		foreach ( array_keys( $data['demos'] ) as $cle ) {
			$f = $_FILES[ 'audio_' . $cle ] ?? null;
			if ( ! $f || intval( $f['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE ) continue;
			$err = Tkd_Gala_Audio::verifier( $f );
			if ( $err !== '' ) $erreurs[] = 'Piste audio : ' . $err;
			else $fichiers[ $cle ] = $f;
		}

		if ( $erreurs ) {
			$this->erreurs = $erreurs;
			$this->saisie  = $data;
			if ( $fichiers ) $this->erreurs[] = 'Rien n\'a été enregistré : sélectionnez à nouveau vos fichiers audio avant de renvoyer.';
			return;
		}

		$res      = Tkd_Gala_DB::enregistrer( $ins, $data );
		$audio_ko = 0;
		foreach ( $fichiers as $cle => $f ) {
			if ( ! Tkd_Gala_Audio::enregistrer_envoi( $f, $res['demo_ids'][ $cle ] ) ) $audio_ko++;
		}

		$apres = Tkd_Gala_DB::inscription( $res['id'] );
		Tkd_Gala_Mails::confirmation( $apres, ! $ins );
		Tkd_Gala_Mails::organisation( $apres, ! $ins );

		$args = [ 'gala' => $apres->token, 'enregistre' => $ins ? 'modif' : 'nouveau' ];
		if ( $audio_ko ) $args['audio_ko'] = $audio_ko;
		wp_safe_redirect( add_query_arg( $args, self::url_page() ) );
		exit;
	}

	// ─── Validation ─────────────────────────────────────────────────────────────

	private static function txt( $v, int $max = 150 ): string {
		return mb_substr( trim( sanitize_text_field( (string) $v ) ), 0, $max );
	}

	/** Date saisie (AAAA-MM-JJ ou JJ/MM/AAAA) → AAAA-MM-JJ, '' si invalide. */
	public static function date_iso( string $s ): string {
		$s = trim( $s );
		if ( preg_match( '#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $s, $m ) ) $s = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) return '';
		return $s;
	}

	/**
	 * Lit et contrôle le formulaire. Renvoie toujours les données nettoyées (pour réafficher
	 * le formulaire tel que saisi) et la liste des erreurs (vide = enregistrable).
	 *
	 * @return array{0: array, 1: string[]}
	 */
	public static function valider( array $post, ?object $ins ): array {
		$r       = Tkd_Gala_DB::reglages();
		$err     = [];
		$c       = (array) ( $post['club'] ?? [] );
		$club    = [
			'nom'          => self::txt( $c['nom'] ?? '' ),
			'affiliation'  => self::txt( $c['affiliation'] ?? '', 50 ),
			'representant' => self::txt( $c['representant'] ?? '' ),
			'role'         => self::txt( $c['role'] ?? '', 100 ),
			'telephone'    => self::txt( $c['telephone'] ?? '', 30 ),
			'email'        => sanitize_email( (string) ( $c['email'] ?? '' ) ),
		];
		if ( $club['nom'] === '' )          $err[] = 'Indiquez le nom du club.';
		if ( $club['representant'] === '' ) $err[] = 'Indiquez le nom du représentant.';
		if ( $club['role'] === '' )         $err[] = 'Indiquez le rôle du représentant au sein du club.';
		if ( strlen( preg_replace( '/\D/', '', $club['telephone'] ) ) < 10 ) $err[] = 'Indiquez un numéro de téléphone valide.';
		if ( ! is_email( $club['email'] ) ) $err[] = 'Indiquez une adresse mail valide : votre lien personnel y sera envoyé.';

		$participants = [];
		$lignes_ko    = [];
		$aujourdhui   = current_time( 'Y-m-d' );
		foreach ( (array) ( $post['dp'] ?? [] ) as $p ) {
			$p   = (array) $p;
			$nom = self::txt( $p['nom'] ?? '', 100 );
			$pre = self::txt( $p['prenom'] ?? '', 100 );
			$nai = (string) ( $p['naissance'] ?? '' );
			if ( $nom === '' && $pre === '' && trim( $nai ) === '' ) continue; // ligne vide
			$iso = self::date_iso( $nai );
			$participants[] = [ 'nom' => $nom, 'prenom' => $pre, 'naissance' => $iso !== '' ? $iso : trim( $nai ), 'repas' => ! empty( $p['repas'] ) ];
			if ( $nom === '' || $pre === '' || $iso === '' || $iso > $aujourdhui || $iso < '1920-01-01' ) $lignes_ko[] = count( $participants );
		}
		if ( ! $participants ) $err[] = 'Ajoutez au moins un démonstrateur.';
		if ( count( $participants ) > self::MAX_PARTICIPANTS ) $err[] = 'Trop de démonstrateurs (' . self::MAX_PARTICIPANTS . ' au maximum).';
		if ( $lignes_ko ) $err[] = 'Démonstrateurs : nom, prénom et date de naissance valide obligatoires (ligne' . ( count( $lignes_ko ) > 1 ? 's ' : ' ' ) . implode( ', ', $lignes_ko ) . ').';

		$couleurs = Tkd_Gala_DB::couleurs();
		$anciennes = [];
		if ( $ins ) foreach ( Tkd_Gala_DB::demos( intval( $ins->id ) ) as $d ) $anciennes[ intval( $d->id ) ] = $d;
		// Une seule démonstration par club (10/11/2026) : seul le premier bloc reçu compte ;
		// une ancienne inscription qui en avait plusieurs ne garde que la première à l'enregistrement.
		$demos = [];
		foreach ( (array) ( $post['d'] ?? [] ) as $cle => $d ) {
			$cle = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $cle ) );
			if ( $cle === '' ) continue;
			$d   = (array) $d;
			$id  = intval( $d['id'] ?? 0 );
			if ( $id && ! isset( $anciennes[ $id ] ) ) $id = 0; // id d'une autre inscription : ignoré
			$demos[ $cle ] = [
				'id'        => $id,
				'titre'     => '', // plus de nom de démonstration
				'duree_sec' => max( 0, intval( $d['min'] ?? 0 ) ) * 60 + max( 0, min( 59, intval( $d['sec'] ?? 0 ) ) ),
				'couleur1'  => self::txt( $d['c1'] ?? '', 50 ),
				'couleur2'  => self::txt( $d['c2'] ?? '', 50 ),
				'planches'  => intval( $d['planches'] ?? 0 ),
				'audio_nom' => $id ? (string) $anciennes[ $id ]->audio_nom : '',
			];
			break;
		}
		if ( ! $demos ) $demos = self::data_vide()['demos']; // bloc vide : les contrôles ci-dessous signalent durée et couleurs
		$data = [ 'club' => $club, 'participants' => $participants, 'demos' => $demos ];
		foreach ( $demos as $d ) {
			$nom = 'Démonstration : ';
			if ( $d['duree_sec'] <= 0 ) $err[] = $nom . 'indiquez sa durée.';
			elseif ( $d['duree_sec'] > intval( $r['duree_max'] ) ) $err[] = $nom . 'durée maximale ' . self::duree( intval( $r['duree_max'] ) ) . '.';
			if ( ! in_array( $d['couleur1'], $couleurs, true ) || ! in_array( $d['couleur2'], $couleurs, true ) ) $err[] = $nom . 'choisissez deux couleurs dans les listes.';
			elseif ( $d['couleur1'] === $d['couleur2'] ) $err[] = $nom . 'choisissez deux couleurs différentes.';
			if ( $d['planches'] < 0 || $d['planches'] > intval( $r['planches_max'] ) ) $err[] = $nom . 'nombre de planches entre 0 et ' . intval( $r['planches_max'] ) . '.';
		}

		$data['consentement'] = ! empty( $post['consentement'] );
		if ( ! $data['consentement'] ) $err[] = 'Cochez l\'attestation d\'accord des démonstrateurs (et de leurs représentants légaux pour les mineurs).';
		$data['reglement'] = ! empty( $post['reglement'] );
		if ( ! $data['reglement'] && (string) $r['url_reglement'] !== '' ) $err[] = 'Cochez la case d\'acceptation du règlement des démonstrations.';

		return [ $data, $err ];
	}

	// ─── Affichage ──────────────────────────────────────────────────────────────

	/** 185 → « 3 min 05 » */
	public static function duree( int $s ): string {
		return intdiv( $s, 60 ) . ' min ' . str_pad( (string) ( $s % 60 ), 2, '0', STR_PAD_LEFT );
	}

	public static function date_fr( string $iso ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m ) ) return $iso;
		return $m[3] . '/' . $m[2] . '/' . $m[1];
	}

	/** Données du formulaire à partir d'une inscription enregistrée. */
	private static function data_depuis_base( object $ins ): array {
		$p = [];
		foreach ( Tkd_Gala_DB::participants( intval( $ins->id ) ) as $x ) {
			$p[] = [ 'nom' => $x->nom, 'prenom' => $x->prenom, 'naissance' => (string) $x->date_naissance, 'repas' => (bool) $x->repas ];
		}
		$d = [];
		foreach ( Tkd_Gala_DB::demos( intval( $ins->id ) ) as $x ) {
			$d[ 'e' . $x->id ] = [
				'id' => intval( $x->id ), 'titre' => '', 'duree_sec' => intval( $x->duree_sec ),
				'couleur1' => $x->couleur1, 'couleur2' => $x->couleur2, 'planches' => intval( $x->planches ), 'audio_nom' => $x->audio_nom,
			];
			break; // une seule démonstration par club
		}
		if ( ! $d ) $d = self::data_vide()['demos'];
		return [
			'club'         => [ 'nom' => $ins->club_nom, 'affiliation' => $ins->affiliation, 'representant' => $ins->representant, 'role' => $ins->role, 'telephone' => $ins->telephone, 'email' => $ins->email ],
			'participants' => $p,
			'demos'        => $d,
			'consentement' => ! empty( $ins->consentement ),
			'reglement'    => ! empty( $ins->reglement ),
		];
	}

	private static function data_vide(): array {
		return [
			'club'         => array_fill_keys( [ 'nom', 'affiliation', 'representant', 'role', 'telephone', 'email' ], '' ),
			'participants' => [ [ 'nom' => '', 'prenom' => '', 'naissance' => '', 'repas' => false ] ],
			'demos'        => [ 'n0' => [ 'id' => 0, 'titre' => '', 'duree_sec' => 0, 'couleur1' => '', 'couleur2' => '', 'planches' => 0, 'audio_nom' => '' ] ],
			'consentement' => false,
			'reglement'    => false,
		];
	}

	public function render( $atts = [] ): string {
		wp_enqueue_style( 'tkd-gala', TKD_GALA_URL . 'assets/gala.css', [], TKD_GALA_VERSION . '-' . filemtime( TKD_GALA_DIR . 'assets/gala.css' ) );
		wp_enqueue_script( 'tkd-gala', TKD_GALA_URL . 'assets/gala-front.js', [], TKD_GALA_VERSION . '-' . filemtime( TKD_GALA_DIR . 'assets/gala-front.js' ), true );

		$r     = Tkd_Gala_DB::reglages();
		$token = sanitize_text_field( wp_unslash( $_GET['gala'] ?? $_POST['token'] ?? '' ) );
		$ins   = $token !== '' ? Tkd_Gala_DB::inscription_par_token( $token ) : null;
		$admin = current_user_can( 'manage_options' );

		ob_start();
		echo '<div class="tkd-gala">';
		$this->entete( $r );

		if ( ! empty( $_GET['gala_lien'] ) || ! empty( $_GET['lien_envoye'] ) ) {
			$this->form_lien_perdu( ! empty( $_GET['lien_envoye'] ) );
		} elseif ( $token !== '' && ! $ins ) {
			echo '<div class="tkd-gala-alerte tkd-gala-ko"><p>🔒 Ce lien est invalide. Utilisez le lien reçu par mail, ou demandez-le à nouveau.</p></div>';
			$this->lien_vers_lien_perdu();
		} else {
			$editable = $ins ? ( Tkd_Gala_DB::modifiable() || $admin ) : ( Tkd_Gala_DB::inscriptions_ouvertes() || $admin );
			if ( $ins && ! empty( $_GET['enregistre'] ) ) $this->confirmation( $ins, $_GET['enregistre'] === 'nouveau', intval( $_GET['audio_ko'] ?? 0 ) );
			if ( $this->erreurs ) {
				echo '<div class="tkd-gala-alerte tkd-gala-ko" role="alert"><p><strong>L\'inscription n\'a pas été enregistrée :</strong></p><ul>';
				foreach ( $this->erreurs as $e ) echo '<li>' . esc_html( $e ) . '</li>';
				echo '</ul></div>';
			}
			if ( ! $editable ) {
				if ( $ins ) $this->recapitulatif( $ins );
				else {
					echo '<div class="tkd-gala-alerte"><p>' . ( empty( $r['ouvert'] ) ? 'Les inscriptions ne sont pas ouvertes.' : 'Les inscriptions sont closes.' ) . '</p></div>';
					$this->lien_vers_lien_perdu();
				}
			} else {
				if ( $admin && $ins && ! Tkd_Gala_DB::modifiable() ) echo '<div class="tkd-gala-alerte"><p>Date limite passée : vous pouvez modifier parce que vous êtes administrateur.</p></div>';
				$this->formulaire( $this->saisie ?? ( $ins ? self::data_depuis_base( $ins ) : self::data_vide() ), $ins, $r );
			}
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	private function entete( array $r ): void {
		echo '<div class="tkd-gala-entete"><h2>' . esc_html( $r['titre'] . ( $r['edition'] ? ' ' . $r['edition'] : '' ) ) . '</h2>';
		$infos = [];
		if ( $r['date_gala'] )   $infos[] = '📅 Gala le ' . esc_html( self::date_fr( $r['date_gala'] ) );
		if ( $r['date_limite'] ) $infos[] = '⏳ Inscriptions et modifications jusqu\'au ' . esc_html( self::date_fr( $r['date_limite'] ) ) . ' inclus';
		if ( $infos ) echo '<p>' . implode( ' · ', $infos ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- éléments échappés ci-dessus
		echo '</div>';
	}

	private function lien_vers_lien_perdu(): void {
		echo '<p class="tkd-gala-petit"><a href="' . esc_url( add_query_arg( 'gala_lien', '1', self::url_page() ) ) . '">Club déjà inscrit ? Recevoir à nouveau mon lien personnel</a></p>';
	}

	private function form_lien_perdu( bool $envoye ): void {
		if ( $envoye ) {
			echo '<div class="tkd-gala-alerte tkd-gala-ok"><p>Si une inscription existe avec cette adresse, le lien personnel vient d\'y être envoyé. Pensez à regarder dans les courriers indésirables.</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( add_query_arg( 'gala_envoi', '1', self::url_page() ) ); ?>" class="tkd-gala-bloc">
			<h3>Recevoir à nouveau mon lien personnel</h3>
			<p>Saisissez l'adresse mail indiquée lors de l'inscription de votre club.</p>
			<input type="hidden" name="tkd_gala_action" value="lien">
			<p class="tkd-gala-piege" aria-hidden="true"><label>Site web <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></p>
			<p><input type="email" name="email" required autocomplete="email" placeholder="adresse@club.fr" class="tkd-gala-champ"></p>
			<p><button type="submit" class="tkd-gala-btn">Envoyer le lien</button></p>
			<p class="tkd-gala-petit"><a href="<?php echo esc_url( self::url_page() ); ?>">← Retour au formulaire</a></p>
		</form>
		<?php
	}

	private function confirmation( object $ins, bool $nouvelle, int $audio_ko ): void {
		$r = Tkd_Gala_DB::reglages();
		echo '<div class="tkd-gala-alerte tkd-gala-ok"><p><strong>✅ ' . ( $nouvelle ? 'Inscription enregistrée.' : 'Modifications enregistrées.' ) . '</strong> Un récapitulatif a été envoyé à ' . esc_html( $ins->email ) . '.</p>'
			. '<p>Ce lien personnel permet de revenir modifier l\'inscription' . ( $r['date_limite'] ? ' jusqu\'au ' . esc_html( self::date_fr( $r['date_limite'] ) ) : '' ) . ' — gardez-le et ne le partagez pas :</p>'
			. '<p class="tkd-gala-lien"><input type="text" readonly value="' . esc_attr( self::lien_club( $ins->token ) ) . '" onclick="this.select()"></p></div>';
		if ( $audio_ko ) {
			echo '<div class="tkd-gala-alerte tkd-gala-ko"><p>⚠️ ' . intval( $audio_ko ) . ' piste(s) audio n\'ont pas pu être enregistrées sur le serveur. Renvoyez-les, ou contactez l\'organisation.</p></div>';
		}
	}

	private function formulaire( array $data, ?object $ins, array $r ): void {
		$couleurs = Tkd_Gala_DB::couleurs();
		$c        = $data['club'];
		$prix     = (float) $r['prix_repas'];
		$max_min  = intdiv( intval( $r['duree_max'] ), 60 );
		$audio_mo = min( (float) $r['audio_max_mo'], wp_max_upload_size() / MB_IN_BYTES );
		$champ    = static function ( string $nom, string $label, string $val, string $type = 'text', bool $requis = true, string $aide = '', string $auto = '' ) {
			echo '<p class="tkd-gala-ligne"><label for="tg-' . esc_attr( $nom ) . '">' . esc_html( $label ) . ( $requis ? ' <span class="tkd-gala-requis">*</span>' : '' ) . '</label>'
				. '<input class="tkd-gala-champ" id="tg-' . esc_attr( $nom ) . '" type="' . esc_attr( $type ) . '" name="club[' . esc_attr( $nom ) . ']" value="' . esc_attr( $val ) . '"' . ( $requis ? ' required' : '' ) . ( $auto ? ' autocomplete="' . esc_attr( $auto ) . '"' : '' ) . '>'
				. ( $aide ? '<span class="tkd-gala-aide">' . esc_html( $aide ) . '</span>' : '' ) . '</p>';
		};
		?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( add_query_arg( 'gala_envoi', '1', self::url_page() ) ); ?>" class="tkd-gala-form" data-prix-repas="<?php echo esc_attr( (string) $prix ); ?>" novalidate>
			<input type="hidden" name="tkd_gala_action" value="enregistrer">
			<input type="hidden" name="token" value="<?php echo esc_attr( $ins->token ?? '' ); ?>">
			<p class="tkd-gala-piege" aria-hidden="true"><label>Site web <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></p>

			<section class="tkd-gala-bloc">
				<h3>1. Club</h3>
				<?php
				$champ( 'nom', 'Nom du club', $c['nom'], 'text', true, '', 'organization' );
				$champ( 'affiliation', 'Numéro d\'affiliation FFTDA', $c['affiliation'], 'text', false, 'Si votre club est affilié à la FFTDA.' );
				$champ( 'representant', 'Représentant', $c['representant'], 'text', true, 'Prénom et nom de la personne à contacter.', 'name' );
				$champ( 'role', 'Rôle au sein du club', $c['role'], 'text', true, 'Ex. : président, entraîneur, secrétaire…' );
				$champ( 'telephone', 'Numéro de téléphone', $c['telephone'], 'tel', true, '', 'tel' );
				$champ( 'email', 'Mail', $c['email'], 'email', true, 'Votre lien personnel pour modifier l\'inscription y sera envoyé.', 'email' );
				?>
			</section>

			<section class="tkd-gala-bloc">
				<h3>2. Démonstrateurs</h3>
				<p class="tkd-gala-aide">Une ligne par démonstrateur. Vous avez déjà la liste dans un tableur (Excel, LibreOffice, Google Sheets) ? Sélectionnez les colonnes <em>Nom, Prénom, Date de naissance</em> (et éventuellement <em>Repas</em> : oui / non), copiez, puis utilisez « Coller depuis un tableur ».</p>
				<div class="tkd-gala-table-wrap">
					<table class="tkd-gala-table">
						<thead><tr><th>#</th><th>Nom</th><th>Prénom</th><th>Date de naissance</th><th>Repas <span class="tkd-gala-aide">(+<?php echo esc_html( self::montant( $prix ) ); ?>)</span></th><th></th></tr></thead>
						<tbody class="tkd-gala-participants">
						<?php foreach ( $data['participants'] as $i => $p ) $this->ligne_participant( (string) $i, $p ); ?>
						</tbody>
					</table>
				</div>
				<p class="tkd-gala-actions">
					<button type="button" class="tkd-gala-btn tkd-gala-btn-sec" data-gala="ajout-participant">➕ Ajouter un démonstrateur</button>
					<button type="button" class="tkd-gala-btn tkd-gala-btn-sec" data-gala="ouvrir-collage">📋 Coller depuis un tableur</button>
				</p>
				<div class="tkd-gala-collage" hidden>
					<label for="tg-collage">Collez ici les lignes copiées (une ligne par démonstrateur) :</label>
					<textarea id="tg-collage" rows="6" placeholder="DUPONT&#9;Léa&#9;12/03/2012&#9;oui"></textarea>
					<p class="tkd-gala-actions">
						<button type="button" class="tkd-gala-btn" data-gala="importer-collage">Ajouter ces lignes</button>
						<button type="button" class="tkd-gala-btn tkd-gala-btn-sec" data-gala="fermer-collage">Annuler</button>
						<span class="tkd-gala-collage-msg" aria-live="polite"></span>
					</p>
				</div>
				<p class="tkd-gala-total" aria-live="polite"></p>
				<p class="tkd-gala-aide">Repas réservés aux démonstrateurs. <?php echo esc_html( $r['paiement'] ); ?></p>
			</section>

			<section class="tkd-gala-bloc">
				<h3>3. Démonstration</h3>
				<p class="tkd-gala-aide">Une démonstration par club, de <?php echo esc_html( self::duree( intval( $r['duree_max'] ) ) ); ?> au maximum. Piste audio : MP3, M4A, AAC ou WAV, <?php echo esc_html( number_format_i18n( $audio_mo, 0 ) ); ?> Mo au maximum — vous pourrez l'ajouter ou la remplacer plus tard avec votre lien personnel.</p>
				<div class="tkd-gala-demos">
				<?php foreach ( $data['demos'] as $cle => $d ) $this->bloc_demo( (string) $cle, $d, $couleurs, $max_min, intval( $r['planches_max'] ), $ins ); ?>
				</div>
			</section>

			<section class="tkd-gala-bloc">
				<h3>4. Accord, données personnelles et règlement</h3>
				<p class="tkd-gala-aide"><?php echo esc_html( $r['texte_rgpd'] ); ?></p>
				<p><label class="tkd-gala-case"><input type="checkbox" name="consentement" value="1" required<?php checked( ! empty( $data['consentement'] ) ); ?>> J'atteste avoir l'accord des démonstrateurs, et de leurs représentants légaux pour les mineurs, pour la transmission de ces informations. <span class="tkd-gala-requis">*</span></label></p>
				<?php if ( (string) $r['url_reglement'] !== '' ) : ?>
				<p><label class="tkd-gala-case"><input type="checkbox" name="reglement" value="1" required<?php checked( ! empty( $data['reglement'] ) ); ?>> <span>J'ai lu et j'accepte, au nom du club et de ses démonstrateurs, le <a href="<?php echo esc_url( $r['url_reglement'] ); ?>" target="_blank" rel="noopener">règlement des démonstrations (PDF)</a>.</span> <span class="tkd-gala-requis">*</span></label></p>
				<?php endif; ?>
			</section>

			<p class="tkd-gala-envoi"><button type="submit" class="tkd-gala-btn tkd-gala-btn-principal"><?php echo $ins ? 'Enregistrer les modifications' : 'Envoyer l\'inscription'; ?></button></p>

			<template class="tkd-gala-tpl-participant"><?php $this->ligne_participant( '__i__', [ 'nom' => '', 'prenom' => '', 'naissance' => '', 'repas' => false ] ); ?></template>		</form>
		<?php if ( ! $ins ) $this->lien_vers_lien_perdu();
	}

	public static function montant( float $m ): string {
		return number_format_i18n( $m, floor( $m ) == $m ? 0 : 2 ) . ' €';
	}

	private function ligne_participant( string $i, array $p ): void {
		// Pas « p » : WordPress lit ce nom dans $_POST comme identifiant d'article (page 404 au réaffichage).
		$n = 'dp[' . $i . ']';
		?>
		<tr>
			<td class="tkd-gala-num"></td>
			<td><input type="text" name="<?php echo esc_attr( $n ); ?>[nom]" value="<?php echo esc_attr( $p['nom'] ); ?>" aria-label="Nom" autocomplete="off"></td>
			<td><input type="text" name="<?php echo esc_attr( $n ); ?>[prenom]" value="<?php echo esc_attr( $p['prenom'] ); ?>" aria-label="Prénom" autocomplete="off"></td>
			<td><input type="date" name="<?php echo esc_attr( $n ); ?>[naissance]" value="<?php echo esc_attr( $p['naissance'] ); ?>" aria-label="Date de naissance" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></td>
			<td class="tkd-gala-centre"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[repas]" value="1" aria-label="Repas"<?php checked( ! empty( $p['repas'] ) ); ?>></td>
			<td><button type="button" class="tkd-gala-suppr" data-gala="suppr-participant" title="Retirer ce démonstrateur" aria-label="Retirer ce démonstrateur">✕</button></td>
		</tr>
		<?php
	}

	private function bloc_demo( string $cle, array $d, array $couleurs, int $max_min, int $planches_max, ?object $ins ): void {
		$n   = 'd[' . $cle . ']';
		$min = intdiv( intval( $d['duree_sec'] ), 60 );
		$sec = intval( $d['duree_sec'] ) % 60;
		$sel = static function ( string $nom, string $val ) use ( $couleurs ) {
			echo '<select name="' . esc_attr( $nom ) . '"><option value="">— Choisir —</option>';
			foreach ( $couleurs as $co ) echo '<option value="' . esc_attr( $co ) . '"' . selected( $val, $co, false ) . '>' . esc_html( $co ) . '</option>';
			echo '</select>';
		};
		?>
		<fieldset class="tkd-gala-demo" data-audio="<?php echo $d['audio_nom'] !== '' ? '1' : ''; ?>">
			<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo intval( $d['id'] ); ?>">
			<p class="tkd-gala-ligne"><label>Durée <span class="tkd-gala-requis">*</span></label>
				<span class="tkd-gala-duree">
					<select name="<?php echo esc_attr( $n ); ?>[min]" aria-label="Minutes"><?php for ( $m = 0; $m <= $max_min; $m++ ) echo '<option value="' . intval( $m ) . '"' . selected( $min, $m, false ) . '>' . intval( $m ) . '</option>'; ?></select> min
					<select name="<?php echo esc_attr( $n ); ?>[sec]" aria-label="Secondes"><?php for ( $s = 0; $s < 60; $s += 5 ) echo '<option value="' . intval( $s ) . '"' . selected( $sec - $sec % 5, $s, false ) . '>' . esc_html( str_pad( (string) $s, 2, '0', STR_PAD_LEFT ) ) . '</option>'; ?></select> s
				</span></p>
			<div class="tkd-gala-ligne"><label>Piste audio</label>
				<div>
				<?php if ( $d['audio_nom'] !== '' && $ins && $d['id'] ) : ?>
					<p class="tkd-gala-audio-actuel">🎵 <?php echo esc_html( $d['audio_nom'] ); ?></p>
					<audio controls preload="none" src="<?php echo esc_url( Tkd_Gala_Audio::url_ecoute( intval( $d['id'] ), $ins->token ) ); ?>"></audio>
					<span class="tkd-gala-aide">Pour la remplacer, choisissez un autre fichier :</span>
				<?php endif; ?>
					<input type="file" name="audio_<?php echo esc_attr( $cle ); ?>" accept=".mp3,.m4a,.aac,.wav,audio/*">
				</div>
			</div>
			<p class="tkd-gala-ligne"><label>Scénographie : 2 couleurs dominantes <span class="tkd-gala-requis">*</span></label>
				<span class="tkd-gala-couleurs"><?php $sel( $n . '[c1]', $d['couleur1'] ); $sel( $n . '[c2]', $d['couleur2'] ); ?></span></p>
			<p class="tkd-gala-ligne"><label>Nombre de planches de casse (~3 mm)</label>
				<input class="tkd-gala-champ tkd-gala-court" type="number" min="0" max="<?php echo intval( $planches_max ); ?>" step="1" name="<?php echo esc_attr( $n ); ?>[planches]" value="<?php echo intval( $d['planches'] ); ?>">
				<span class="tkd-gala-aide">(<?php echo intval( $planches_max ); ?> au maximum, une planche par participant)</span></p>
		</fieldset>
		<?php
	}

	/** Après la date limite : lecture seule. */
	private function recapitulatif( object $ins ): void {
		echo '<div class="tkd-gala-alerte"><p>🔒 La date limite est passée : l\'inscription ne peut plus être modifiée en ligne. Pour un changement, contactez l\'organisation.</p></div>';
		echo '<div class="tkd-gala-bloc"><pre class="tkd-gala-recap">' . esc_html( Tkd_Gala_Mails::recap_texte( $ins ) ) . '</pre></div>';
	}
}
