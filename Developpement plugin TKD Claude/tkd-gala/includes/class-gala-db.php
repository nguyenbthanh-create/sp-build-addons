<?php
/**
 * Tables et réglages du gala.
 *
 * Trois tables, toutes rattachées à une « édition » (année du gala) pour réutiliser
 * l'extension d'une année sur l'autre :
 *   tkd_gala_inscriptions  un club inscrit (représentant, contact, jeton du lien personnel)
 *   tkd_gala_participants  les démonstrateurs du club (repas oui / non)
 *   tkd_gala_demos         les démonstrations du club (durée, audio, couleurs, planches)
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_DB {

	const DB_VERSION = '1.5'; // 1.5 (13/11/2026) : équipes (clubs_associes sur l'inscription, club du participant) ; 1.4 (11/11/2026) : logo_id (médiathèque) sur les inscriptions et les candidatures // 1.1 (10/11/2026) : table des candidatures retenues (Tkd_Gala_Candidatures) ; 1.2 : colonne reglement (acceptation du règlement) ; 1.3 : annonceurs et leurs passages (Tkd_Gala_Annonceurs)
	const OPT_DB     = 'tkd_gala_db_version';
	const OPT_REG    = 'tkd_gala_reglages';

	public static function t_inscriptions(): string { global $wpdb; return $wpdb->prefix . 'tkd_gala_inscriptions'; }
	public static function t_participants(): string { global $wpdb; return $wpdb->prefix . 'tkd_gala_participants'; }
	public static function t_demos(): string        { global $wpdb; return $wpdb->prefix . 'tkd_gala_demos'; }

	// ─── Schéma ─────────────────────────────────────────────────────────────────

	public static function maybe_upgrade(): void {
		if ( get_option( self::OPT_DB ) === self::DB_VERSION ) return;
		self::installer();
	}

	public static function installer(): void {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( 'CREATE TABLE ' . self::t_inscriptions() . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			edition varchar(20) NOT NULL DEFAULT '',
			token char(64) NOT NULL DEFAULT '',
			club_nom varchar(150) NOT NULL DEFAULT '',
			affiliation varchar(50) NOT NULL DEFAULT '',
			representant varchar(150) NOT NULL DEFAULT '',
			role varchar(100) NOT NULL DEFAULT '',
			telephone varchar(30) NOT NULL DEFAULT '',
			email varchar(150) NOT NULL DEFAULT '',
			consentement datetime DEFAULT NULL,
			reglement datetime DEFAULT NULL,
			logo_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			clubs_associes text,
			paye tinyint(1) NOT NULL DEFAULT 0,
			note_admin text,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY edition (edition)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t_participants() . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			inscription_id bigint(20) UNSIGNED NOT NULL,
			nom varchar(100) NOT NULL DEFAULT '',
			prenom varchar(100) NOT NULL DEFAULT '',
			date_naissance date DEFAULT NULL,
			repas tinyint(1) NOT NULL DEFAULT 0,
			club varchar(150) NOT NULL DEFAULT '',
			ordre int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY inscription_id (inscription_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t_demos() . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			inscription_id bigint(20) UNSIGNED NOT NULL,
			titre varchar(150) NOT NULL DEFAULT '',
			duree_sec int(11) NOT NULL DEFAULT 0,
			couleur1 varchar(50) NOT NULL DEFAULT '',
			couleur2 varchar(50) NOT NULL DEFAULT '',
			planches int(11) NOT NULL DEFAULT 0,
			audio_fichier varchar(255) NOT NULL DEFAULT '',
			audio_nom varchar(255) NOT NULL DEFAULT '',
			audio_taille int(11) NOT NULL DEFAULT 0,
			ordre int(11) NOT NULL DEFAULT 0,
			ordre_passage int(11) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY inscription_id (inscription_id)
		) $c;" );

		dbDelta( Tkd_Gala_Candidatures::sql_creer( $c ) );
		foreach ( Tkd_Gala_Annonceurs::sql_creer( $c ) as $sql ) dbDelta( $sql );

		$manque = [];
		foreach ( [ self::t_inscriptions(), self::t_participants(), self::t_demos(), Tkd_Gala_Candidatures::t_externes(), Tkd_Gala_Annonceurs::t_annonceurs(), Tkd_Gala_Annonceurs::t_annonces() ] as $t ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) $manque[] = $t;
		}
		if ( $manque ) {
			// Pas de version enregistrée : nouvel essai au prochain chargement.
			error_log( '[TKD Gala] tables absentes après création : ' . implode( ', ', $manque ) );
			return;
		}
		update_option( self::OPT_DB, self::DB_VERSION );
	}

	// ─── Réglages ───────────────────────────────────────────────────────────────

	public static function defauts(): array {
		return [
			'edition'       => gmdate( 'Y' ),
			'titre'         => 'Gala des arts martiaux',
			'ouvert'        => 0,
			'date_gala'     => '',
			'date_limite'   => '',        // Y-m-d : modifications possibles jusqu'à ce jour inclus
			'duree_max'     => 300,       // secondes par démonstration
			'planches_max'  => 20,        // par démonstration
			'prix_repas'    => 5,
			'audio_max_mo'  => 20,
			'email_orga'    => get_option( 'admin_email' ),
			'heure_debut'   => '14:00',   // conducteur : heure de la première démonstration
			'battement_sec' => 60,        // conducteur : temps entre deux démonstrations
			'duree_annonce' => 30,        // conducteur : durée proposée pour un nouveau passage d'annonceur (secondes)
			'relance_active' => 1,        // relance des inscriptions incomplètes (Tkd_Gala_Relance)
			'relance_jours'  => 7,        // … à partir de date limite − N jours
			'couleurs'      => "Rouge\nBleu\nVert\nJaune\nOrange\nViolet\nRose\nBlanc\nCyan\nOr",
			'paiement'      => 'Les repas sont à régler sur place, le jour du gala.',
			'texte_rgpd'    => "Les noms, prénoms et dates de naissance des démonstrateurs servent uniquement à l'organisation du gala (liste des participants, repas). Ils sont supprimés au plus tard un mois après le gala.",
			// Règlement des démonstrations (PDF de la médiathèque) : case à cocher obligatoire du formulaire.
			'url_reglement' => 'https://tkdclaira.fr/wp-content/uploads/2026/11/Reglement_Demonstrations_Taekwondo_Gala_2027-V2.pdf',
			// Nom du formulaire Elementor des candidatures hors taekwondo (onglet Candidatures).
			'form_candidature' => 'Candidature Gala Arts Martiaux 2027',
		];
	}

	public static function reglages(): array {
		$r = get_option( self::OPT_REG, [] );
		return array_merge( self::defauts(), is_array( $r ) ? $r : [] );
	}

	public static function enregistrer_reglages( array $r ): void {
		update_option( self::OPT_REG, array_merge( self::reglages(), $r ), false );
	}

	/** @return string[] couleurs proposées pour la scénographie */
	public static function couleurs(): array {
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) self::reglages()['couleurs'] ) ) ) );
	}

	/** Les modifications sont-elles encore permises (date limite incluse) ? */
	public static function modifiable(): bool {
		$lim = (string) self::reglages()['date_limite'];
		if ( $lim === '' ) return true;
		return current_time( 'Y-m-d' ) <= $lim;
	}

	/** Nouvelles inscriptions acceptées ? */
	public static function inscriptions_ouvertes(): bool {
		return ! empty( self::reglages()['ouvert'] ) && self::modifiable();
	}

	// ─── Lecture ────────────────────────────────────────────────────────────────

	public static function inscription_par_token( string $token ): ?object {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) return null;
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_inscriptions() . ' WHERE token = %s', $token ) );
		return $row ?: null;
	}

	public static function inscription( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_inscriptions() . ' WHERE id = %d', $id ) );
		return $row ?: null;
	}

	/** @return object[] inscriptions d'une édition, par nom de club */
	public static function inscriptions( string $edition ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t_inscriptions() . ' WHERE edition = %s ORDER BY club_nom ASC, id ASC', $edition ) ) ?: [];
	}

	/** @return object[] inscriptions de l'édition courante pour une adresse mail (lien perdu) */
	public static function inscriptions_par_email( string $email ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::t_inscriptions() . ' WHERE edition = %s AND LOWER(email) = LOWER(%s)',
			self::reglages()['edition'], $email
		) ) ?: [];
	}

	/** @return object[] */
	public static function participants( int $inscription_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t_participants() . ' WHERE inscription_id = %d ORDER BY ordre ASC, id ASC', $inscription_id ) ) ?: [];
	}

	/** @return object[] */
	public static function demos( int $inscription_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t_demos() . ' WHERE inscription_id = %d ORDER BY ordre ASC, id ASC', $inscription_id ) ) ?: [];
	}

	public static function demo( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_demos() . ' WHERE id = %d', $id ) );
		return $row ?: null;
	}

	/**
	 * Toutes les démonstrations d'une édition, dans l'ordre de passage (les démonstrations
	 * sans ordre à la fin, par date d'inscription), avec le nom du club.
	 *
	 * @return object[]
	 */
	public static function demos_edition( string $edition ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT d.*, i.club_nom, i.logo_id, i.clubs_associes FROM ' . self::t_demos() . ' d INNER JOIN ' . self::t_inscriptions() . ' i ON i.id = d.inscription_id
			 WHERE i.edition = %s ORDER BY d.ordre_passage IS NULL, d.ordre_passage ASC, i.id ASC, d.ordre ASC, d.id ASC',
			$edition
		) ) ?: [];
	}

	/** @return object[] participants d'une édition avec le nom du club */
	public static function participants_edition( string $edition ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT p.*, i.club_nom, i.clubs_associes FROM ' . self::t_participants() . ' p INNER JOIN ' . self::t_inscriptions() . ' i ON i.id = p.inscription_id
			 WHERE i.edition = %s ORDER BY i.club_nom ASC, p.nom ASC, p.prenom ASC',
			$edition
		) ) ?: [];
	}

	// ─── Équipes (13/11/2026) ───────────────────────────────────────────────────

	/** Minimum de démonstrateurs par équipe (règlement, article 3) : signalé, jamais bloquant. */
	const MIN_PARTICIPANTS = 15;

	/** @return string[] noms des clubs associés d'une inscription ([] = club seul) */
	public static function associes( object $ins ): array {
		$v = json_decode( (string) ( $ins->clubs_associes ?? '' ), true );
		return is_array( $v ) ? array_values( array_filter( array_map( 'strval', $v ), static fn( $s ) => $s !== '' ) ) : [];
	}

	/** @return string[] tous les clubs de l'équipe, le club référent d'abord */
	public static function clubs_equipe( object $ins ): array {
		return array_merge( [ (string) $ins->club_nom ], self::associes( $ins ) );
	}

	/** Nom affiché (Conducteur, projection) : « TKD Le Barcarès – Tautavel », ou le club seul. */
	public static function nom_equipe( object $ins ): string {
		return implode( ' – ', self::clubs_equipe( $ins ) );
	}

	/** Club d'un démonstrateur (vide pour les inscriptions d'avant les équipes : club référent). */
	public static function club_participant( object $p, object $ins ): string {
		return (string) ( $p->club ?? '' ) !== '' ? (string) $p->club : (string) $ins->club_nom;
	}

	/** @return array<string, int> repas réservés par club de l'équipe */
	public static function repas_par_club( object $ins ): array {
		$out = array_fill_keys( self::clubs_equipe( $ins ), 0 );
		foreach ( self::participants( intval( $ins->id ) ) as $p ) {
			if ( (int) $p->repas !== 1 ) continue;
			$c = self::club_participant( $p, $ins );
			$out[ $c ] = ( $out[ $c ] ?? 0 ) + 1;
		}
		return $out;
	}

	// ─── Écriture ───────────────────────────────────────────────────────────────

	/**
	 * Crée ou met à jour une inscription complète, à partir de données déjà validées
	 * (Tkd_Gala_Front::valider()). Les participants sont remplacés en bloc ; les
	 * démonstrations existantes sont mises à jour par leur id (pour garder leur piste
	 * audio), celles qui ont disparu du formulaire sont supprimées avec leur fichier.
	 *
	 * @param array $data ['club'=>[], 'participants'=>[], 'demos'=>[]]
	 * @return array{id:int, demo_ids:array<int|string,int>} id de l'inscription et, pour chaque
	 *         clé de démonstration du formulaire, l'id enregistré (pour rattacher les fichiers audio)
	 */
	public static function enregistrer( ?object $existante, array $data ): array {
		global $wpdb;
		$now  = current_time( 'mysql' );
		$club = $data['club'];
		$row  = [
			'club_nom'     => $club['nom'],
			'affiliation'  => $club['affiliation'],
			'representant' => $club['representant'],
			'role'         => $club['role'],
			'telephone'    => $club['telephone'],
			'email'        => $club['email'],
			// Équipe (13/11/2026) : noms des clubs associés (2 au plus), [] = club seul.
			'clubs_associes' => wp_json_encode( array_values( (array) ( $club['associes'] ?? [] ) ) ),
			'consentement' => $now,
			'reglement'    => $now, // case « règlement accepté » obligatoire à chaque enregistrement
			'updated_at'   => $now,
		];

		if ( $existante ) {
			$id = intval( $existante->id );
			$wpdb->update( self::t_inscriptions(), $row, [ 'id' => $id ] );
		} else {
			$wpdb->insert( self::t_inscriptions(), $row + [
				'edition'    => self::reglages()['edition'],
				'token'      => bin2hex( random_bytes( 32 ) ),
				'created_at' => $now,
			] );
			$id = intval( $wpdb->insert_id );
		}

		$wpdb->delete( self::t_participants(), [ 'inscription_id' => $id ] );
		foreach ( array_values( $data['participants'] ) as $n => $p ) {
			$wpdb->insert( self::t_participants(), [
				'inscription_id' => $id,
				'nom'            => $p['nom'],
				'prenom'         => $p['prenom'],
				'date_naissance' => $p['naissance'] !== '' ? $p['naissance'] : null,
				'repas'          => $p['repas'] ? 1 : 0,
				'club'           => (string) ( $p['club'] ?? '' ),
				'ordre'          => $n,
			] );
		}

		$anciennes = [];
		foreach ( self::demos( $id ) as $d ) $anciennes[ intval( $d->id ) ] = $d;
		$gardees   = [];
		$demo_ids  = [];
		$n = 0;
		foreach ( $data['demos'] as $cle => $d ) {
			$vals = [
				'titre'     => $d['titre'],
				'duree_sec' => $d['duree_sec'],
				'couleur1'  => $d['couleur1'],
				'couleur2'  => $d['couleur2'],
				'planches'  => $d['planches'],
				'ordre'     => $n++,
			];
			$did = intval( $d['id'] );
			if ( $did && isset( $anciennes[ $did ] ) ) {
				$wpdb->update( self::t_demos(), $vals, [ 'id' => $did ] );
				$gardees[ $did ] = true;
			} else {
				$wpdb->insert( self::t_demos(), $vals + [ 'inscription_id' => $id ] );
				$did = intval( $wpdb->insert_id );
			}
			$demo_ids[ $cle ] = $did;
		}
		foreach ( $anciennes as $did => $d ) {
			if ( isset( $gardees[ $did ] ) ) continue;
			Tkd_Gala_Audio::supprimer_fichier( $d );
			$wpdb->delete( self::t_demos(), [ 'id' => $did ] );
		}

		return [ 'id' => $id, 'demo_ids' => $demo_ids ];
	}

	public static function attacher_audio( int $demo_id, string $fichier, string $nom, int $taille ): void {
		global $wpdb;
		$wpdb->update( self::t_demos(), [ 'audio_fichier' => $fichier, 'audio_nom' => $nom, 'audio_taille' => $taille ], [ 'id' => $demo_id ] );
	}

	public static function supprimer_inscription( int $id ): void {
		global $wpdb;
		foreach ( self::demos( $id ) as $d ) Tkd_Gala_Audio::supprimer_fichier( $d );
		$wpdb->delete( self::t_demos(), [ 'inscription_id' => $id ] );
		$wpdb->delete( self::t_participants(), [ 'inscription_id' => $id ] );
		$wpdb->delete( self::t_inscriptions(), [ 'id' => $id ] );
	}

	/** Efface toutes les données d'une édition (après le gala), pistes audio comprises. */
	public static function purger_edition( string $edition ): int {
		$n = 0;
		foreach ( self::inscriptions( $edition ) as $i ) { self::supprimer_inscription( intval( $i->id ) ); $n++; }
		Tkd_Gala_Candidatures::purger_edition( $edition ); // les candidatures elles-mêmes restent dans Elementor
		Tkd_Gala_Annonceurs::purger_edition( $edition );   // les logos restent dans la médiathèque
		Tkd_Gala_Audio::supprimer_dossier( $edition );
		return $n;
	}
}
