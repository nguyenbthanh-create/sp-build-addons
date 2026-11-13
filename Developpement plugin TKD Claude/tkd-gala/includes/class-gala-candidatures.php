<?php
/**
 * Candidatures des structures hors taekwondo (10/11/2026).
 *
 * Les candidatures arrivent par le formulaire Elementor Pro « Candidature Gala Arts Martiaux
 * 2027 » (modèle dans modeles-elementor/) et restent dans les tables d'Elementor
 * ({prefix}e_submissions / e_submissions_values) : l'extension les LIT seulement, jamais ne
 * les modifie. Ce qui est propre au gala (candidature retenue ou non, ordre de passage, piste
 * audio déposée par l'organisation) est rangé à part, dans {prefix}tkd_gala_externes, une
 * ligne par candidature, reliée par l'id d'envoi Elementor.
 *
 * Le Conducteur réunit les démonstrations des clubs de taekwondo (tkd_gala_demos) et les
 * candidatures retenues, avec un seul ordre de passage : demos_conducteur().
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Candidatures {

	public static function t_externes(): string { global $wpdb; return $wpdb->prefix . 'tkd_gala_externes'; }
	private static function t_envois(): string  { global $wpdb; return $wpdb->prefix . 'e_submissions'; }
	private static function t_valeurs(): string { global $wpdb; return $wpdb->prefix . 'e_submissions_values'; }

	/** Requête de création, appelée par Tkd_Gala_DB::installer(). */
	public static function sql_creer( string $charset ): string {
		return 'CREATE TABLE ' . self::t_externes() . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			edition varchar(20) NOT NULL DEFAULT '',
			submission_id bigint(20) UNSIGNED NOT NULL,
			retenue tinyint(1) NOT NULL DEFAULT 0,
			ordre_passage int(11) DEFAULT NULL,
			audio_fichier varchar(255) NOT NULL DEFAULT '',
			audio_nom varchar(255) NOT NULL DEFAULT '',
			audio_taille int(11) NOT NULL DEFAULT 0,
			logo_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY submission_id (submission_id),
			KEY edition (edition)
		) $charset;";
	}

	public static function init(): void {
		add_action( 'elementor_pro/forms/validation', [ self::class, 'valider_elementor' ], 10, 2 );
	}

	/**
	 * Formulaire de candidature : deux couleurs de scénographie différentes (comme le
	 * formulaire des clubs). Les autres contrôles restent ceux d'Elementor.
	 *
	 * @param object $record       ElementorPro\Modules\Forms\Classes\Form_Record
	 * @param object $ajax_handler ElementorPro\Modules\Forms\Classes\Ajax_Handler
	 */
	public static function valider_elementor( $record, $ajax_handler ): void {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get_form_settings' ) ) return;
		if ( (string) $record->get_form_settings( 'form_name' ) !== (string) Tkd_Gala_DB::reglages()['form_candidature'] ) return;
		$f  = (array) $record->get( 'fields' );
		$c1 = trim( (string) ( $f['demo_couleur1']['value'] ?? '' ) );
		$c2 = trim( (string) ( $f['demo_couleur2']['value'] ?? '' ) );
		if ( $c1 !== '' && $c1 === $c2 && method_exists( $ajax_handler, 'add_error' ) ) {
			$ajax_handler->add_error( 'demo_couleur2', 'Choisissez deux couleurs différentes.' );
		}
	}

	/** Elementor Pro enregistre-t-il les envois sur ce site ? */
	public static function elementor_dispo(): bool {
		static $ok = null;
		if ( $ok === null ) {
			global $wpdb;
			$ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::t_envois() ) ) === self::t_envois();
		}
		return $ok;
	}

	// ─── Lecture des envois Elementor ───────────────────────────────────────────

	/**
	 * Envois du formulaire de candidature (hors corbeille), du plus récent au plus ancien.
	 * Chaque objet porte id, created_at et v : [ id du champ => valeur ].
	 *
	 * @return object[]
	 */
	public static function candidatures(): array {
		if ( ! self::elementor_dispo() ) return [];
		global $wpdb;
		$nom = (string) Tkd_Gala_DB::reglages()['form_candidature'];
		$envois = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, created_at FROM ' . self::t_envois() . " WHERE form_name = %s AND status <> 'trash' ORDER BY created_at DESC, id DESC",
			$nom
		) ) ?: [];
		if ( ! $envois ) return [];
		$ids = array_map( static fn( $e ) => intval( $e->id ), $envois );
		$vals = $wpdb->get_results(
			'SELECT submission_id, `key`, value FROM ' . self::t_valeurs() . ' WHERE submission_id IN (' . implode( ',', $ids ) . ')' // ids entiers ci-dessus
		) ?: [];
		$par = [];
		foreach ( $vals as $v ) $par[ intval( $v->submission_id ) ][ (string) $v->key ] = (string) $v->value;
		foreach ( $envois as $e ) $e->v = $par[ intval( $e->id ) ] ?? [];
		return $envois;
	}

	public static function candidature( int $submission_id ): ?object {
		foreach ( self::candidatures() as $c ) if ( intval( $c->id ) === $submission_id ) return $c;
		return null;
	}

	// ─── Sélection, ordre, piste ───────────────────────────────────────────────

	/** @return object[] lignes tkd_gala_externes de l'édition, indexées par id d'envoi Elementor */
	public static function externes( string $edition ): array {
		global $wpdb;
		$out = [];
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t_externes() . ' WHERE edition = %s', $edition ) ) ?: [] as $x ) {
			$out[ intval( $x->submission_id ) ] = $x;
		}
		return $out;
	}

	public static function externe( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_externes() . ' WHERE id = %d', $id ) );
		return $row ?: null;
	}

	/** Ligne de l'édition pour cet envoi, créée au besoin (non retenue). */
	public static function assurer_externe( int $submission_id, string $edition ): object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_externes() . ' WHERE submission_id = %d', $submission_id ) );
		if ( ! $row ) {
			$wpdb->insert( self::t_externes(), [ 'edition' => $edition, 'submission_id' => $submission_id, 'retenue' => 0, 'updated_at' => current_time( 'mysql' ) ] );
			$row = self::externe( intval( $wpdb->insert_id ) );
		}
		return $row;
	}

	public static function definir_retenue( int $submission_id, bool $retenue, string $edition ): void {
		global $wpdb;
		$x = self::assurer_externe( $submission_id, $edition );
		if ( (bool) $x->retenue === $retenue ) return;
		$wpdb->update( self::t_externes(), [ 'retenue' => $retenue ? 1 : 0, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => intval( $x->id ) ] );
	}

	public static function definir_ordre( int $id, ?int $ordre ): void {
		global $wpdb;
		$wpdb->update( self::t_externes(), [ 'ordre_passage' => $ordre ], [ 'id' => $id ] );
	}

	public static function attacher_audio( int $id, string $fichier, string $nom, int $taille ): void {
		global $wpdb;
		$wpdb->update( self::t_externes(), [ 'audio_fichier' => $fichier, 'audio_nom' => $nom, 'audio_taille' => $taille, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );
	}

	/** Après le gala : lignes de l'édition (les fichiers partent avec le dossier de l'édition). */
	public static function purger_edition( string $edition ): void {
		global $wpdb;
		$wpdb->delete( self::t_externes(), [ 'edition' => $edition ] );
	}

	// ─── Conducteur commun ─────────────────────────────────────────────────────

	/**
	 * Toutes les démonstrations du gala dans l'ordre de passage : celles des clubs de
	 * taekwondo et les candidatures retenues. Sans numéro : à la fin, taekwondo d'abord.
	 * Chaque objet : cle ('d<id>' ou 'e<id>'), type ('tkd' | 'ext'), id, club_nom, titre,
	 * discipline, duree_sec, couleur1, couleur2, planches (null pour une candidature),
	 * participants, audio_fichier, audio_nom, audio_taille, ordre_passage, edition.
	 *
	 * @return object[]
	 */
	public static function demos_conducteur( string $edition ): array {
		$liste = [];
		$rang  = 0;
		foreach ( Tkd_Gala_DB::demos_edition( $edition ) as $d ) {
			$liste[] = self::ligne( [
				'cle' => 'd' . $d->id, 'type' => 'tkd', 'id' => intval( $d->id ), 'club_nom' => Tkd_Gala_DB::nom_equipe( $d ), // équipe : « Club A – Club B » 'titre' => '', // les clubs ne nomment plus leur démonstration
				'discipline' => 'Taekwondo', 'duree_sec' => intval( $d->duree_sec ), 'couleur1' => $d->couleur1, 'couleur2' => $d->couleur2,
				'planches' => intval( $d->planches ), 'participants' => null, 'audio_fichier' => $d->audio_fichier, 'audio_nom' => $d->audio_nom,
				'audio_taille' => intval( $d->audio_taille ), 'ordre_passage' => $d->ordre_passage === null ? null : intval( $d->ordre_passage ),
				// edition null : Tkd_Gala_Audio::chemin() lit l'édition sur l'inscription.
				'inscription_id' => intval( $d->inscription_id ), 'edition' => null, 'submission_id' => 0, 'rang' => $rang++,
				'logo_url' => Tkd_Gala_Logos::url( intval( $d->logo_id ?? 0 ), 'large' ),
			] );
		}
		$externes = self::externes( $edition );
		foreach ( array_reverse( self::candidatures() ) as $c ) { // ordre d'arrivée
			$x = $externes[ intval( $c->id ) ] ?? null;
			if ( ! $x || ! (int) $x->retenue ) continue;
			$v = $c->v;
			$liste[] = self::ligne( [
				'cle' => 'e' . $x->id, 'type' => 'ext', 'id' => intval( $x->id ), 'club_nom' => $v['struct_nom'] ?? '', 'titre' => $v['demo_titre'] ?? '',
				'discipline' => $v['disc_nom'] ?? '', 'duree_sec' => max( 0, intval( $v['demo_duree'] ?? 0 ) ) * 60,
				// Scénographie (11/11/2026) : champs demo_couleur1 / demo_couleur2 du formulaire Elementor ; vides pour les envois antérieurs.
				'couleur1' => (string) ( $v['demo_couleur1'] ?? '' ), 'couleur2' => (string) ( $v['demo_couleur2'] ?? '' ),
				'planches' => null, 'participants' => intval( $v['demo_participants'] ?? 0 ), 'audio_fichier' => $x->audio_fichier, 'audio_nom' => $x->audio_nom,
				'audio_taille' => intval( $x->audio_taille ), 'ordre_passage' => $x->ordre_passage === null ? null : intval( $x->ordre_passage ),
				'inscription_id' => 0, 'edition' => (string) $x->edition, 'submission_id' => intval( $c->id ), 'rang' => $rang++,
				'logo_url' => Tkd_Gala_Logos::url( Tkd_Gala_Logos::candidature( $c, $edition ), 'large' ),
			] );
		}
		return self::trier( $liste );
	}

	/**
	 * Déroulé complet de la soirée : démonstrations (demos_conducteur()) et passages
	 * d'annonceurs (type 'ann', cle 'a<id>', avec logo_url, slogan, message), un seul ordre.
	 * Sans numéro : à la fin, démonstrations d'abord.
	 *
	 * @return object[]
	 */
	public static function conducteur( string $edition ): array {
		$liste = self::demos_conducteur( $edition );
		$rang  = count( $liste );
		foreach ( Tkd_Gala_Annonceurs::lignes_conducteur( $edition ) as $champs ) {
			$liste[] = self::ligne( $champs + [ 'rang' => $rang++ ] );
		}
		return self::trier( $liste );
	}

	/** Ordre de passage, puis rang d'arrivée ; les lignes sans numéro à la fin. */
	private static function trier( array $liste ): array {
		usort( $liste, static function ( $a, $b ) {
			if ( ( $a->ordre_passage === null ) !== ( $b->ordre_passage === null ) ) return $a->ordre_passage === null ? 1 : -1;
			if ( $a->ordre_passage !== $b->ordre_passage ) return $a->ordre_passage <=> $b->ordre_passage;
			return $a->rang <=> $b->rang;
		} );
		return $liste;
	}

	/** Ligne du conducteur : mêmes champs pour une démonstration de club et une candidature. */
	private static function ligne( array $champs ): stdClass {
		$o = new stdClass();
		foreach ( $champs as $k => $v ) $o->$k = $v;
		return $o;
	}
}
