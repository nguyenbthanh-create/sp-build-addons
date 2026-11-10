<?php
/**
 * Annonceurs du gala (10/11/2026) : partenaires annoncés pendant la soirée.
 *
 * Un annonceur (nom, logo de la médiathèque, slogan projeté, message lu par l'animateur) peut
 * avoir plusieurs passages dans le Conducteur (ouverture, entracte…). Chaque passage a sa
 * durée et prend place dans l'ordre de passage commun, entre les démonstrations.
 *
 *   {prefix}tkd_gala_annonceurs  une ligne par annonceur et par édition
 *   {prefix}tkd_gala_annonces    une ligne par passage (durée, numéro d'ordre)
 *
 * Saisie indépendante ; les noms des sponsors de SP Compta sont seulement proposés à la
 * saisie (lecture seule de {prefix}sp_compta_sponsor, rien si SP Compta est absente).
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Annonceurs {

	public static function t_annonceurs(): string { global $wpdb; return $wpdb->prefix . 'tkd_gala_annonceurs'; }
	public static function t_annonces(): string   { global $wpdb; return $wpdb->prefix . 'tkd_gala_annonces'; }

	/** @return string[] requêtes de création, appelées par Tkd_Gala_DB::installer() */
	public static function sql_creer( string $charset ): array {
		return [
			'CREATE TABLE ' . self::t_annonceurs() . " (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				edition varchar(20) NOT NULL DEFAULT '',
				nom varchar(150) NOT NULL DEFAULT '',
				logo_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
				slogan varchar(255) NOT NULL DEFAULT '',
				message text,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY edition (edition)
			) $charset;",
			'CREATE TABLE ' . self::t_annonces() . " (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				annonceur_id bigint(20) UNSIGNED NOT NULL,
				duree_sec int(11) NOT NULL DEFAULT 30,
				ordre_passage int(11) DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY annonceur_id (annonceur_id)
			) $charset;",
		];
	}

	// ─── Annonceurs ─────────────────────────────────────────────────────────────

	/** @return object[] annonceurs de l'édition, par nom, avec nb_passages */
	public static function annonceurs( string $edition ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT a.*, (SELECT COUNT(*) FROM ' . self::t_annonces() . ' p WHERE p.annonceur_id = a.id) AS nb_passages FROM ' . self::t_annonceurs() . ' a WHERE a.edition = %s ORDER BY a.nom ASC, a.id ASC',
			$edition
		) ) ?: [];
	}

	public static function annonceur( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_annonceurs() . ' WHERE id = %d', $id ) );
		return $row ?: null;
	}

	/** Crée (id 0) ou met à jour un annonceur ; renvoie son id. */
	public static function enregistrer( int $id, array $d, string $edition ): int {
		global $wpdb;
		$row = [
			'nom'        => $d['nom'],
			'logo_id'    => $d['logo_id'],
			'slogan'     => $d['slogan'],
			'message'    => $d['message'],
			'updated_at' => current_time( 'mysql' ),
		];
		if ( $id && self::annonceur( $id ) ) {
			$wpdb->update( self::t_annonceurs(), $row, [ 'id' => $id ] );
			return $id;
		}
		$wpdb->insert( self::t_annonceurs(), $row + [ 'edition' => $edition ] );
		return intval( $wpdb->insert_id );
	}

	/** Supprime l'annonceur et ses passages (le logo reste dans la médiathèque). */
	public static function supprimer( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::t_annonces(), [ 'annonceur_id' => $id ] );
		$wpdb->delete( self::t_annonceurs(), [ 'id' => $id ] );
	}

	// ─── Passages ───────────────────────────────────────────────────────────────

	public static function ajouter_passage( int $annonceur_id, int $duree_sec ): void {
		global $wpdb;
		if ( ! self::annonceur( $annonceur_id ) ) return;
		$wpdb->insert( self::t_annonces(), [ 'annonceur_id' => $annonceur_id, 'duree_sec' => max( 0, $duree_sec ), 'ordre_passage' => null ] );
	}

	public static function definir_passage( int $id, ?int $ordre, int $duree_sec ): void {
		global $wpdb;
		$wpdb->update( self::t_annonces(), [ 'ordre_passage' => $ordre, 'duree_sec' => max( 0, $duree_sec ) ], [ 'id' => $id ] );
	}

	public static function supprimer_passage( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::t_annonces(), [ 'id' => $id ] );
	}

	/** Après le gala : annonceurs et passages de l'édition. */
	public static function purger_edition( string $edition ): void {
		foreach ( self::annonceurs( $edition ) as $a ) self::supprimer( intval( $a->id ) );
	}

	/**
	 * Passages de l'édition, au format des lignes du Conducteur (voir
	 * Tkd_Gala_Candidatures::conducteur()) : cle 'a<id du passage>', type 'ann'.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function lignes_conducteur( string $edition ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT p.id, p.duree_sec, p.ordre_passage, a.id AS annonceur_id, a.nom, a.logo_id, a.slogan, a.message FROM ' . self::t_annonces() . ' p INNER JOIN ' . self::t_annonceurs() . ' a ON a.id = p.annonceur_id WHERE a.edition = %s ORDER BY p.id ASC',
			$edition
		) ) ?: [];
		$out = [];
		foreach ( $rows as $p ) {
			$out[] = [
				'cle' => 'a' . $p->id, 'type' => 'ann', 'id' => intval( $p->id ), 'club_nom' => (string) $p->nom, 'titre' => (string) $p->slogan,
				'discipline' => 'Annonce', 'duree_sec' => intval( $p->duree_sec ), 'couleur1' => '', 'couleur2' => '', 'planches' => null, 'participants' => null,
				'audio_fichier' => '', 'audio_nom' => '', 'audio_taille' => 0, 'ordre_passage' => $p->ordre_passage === null ? null : intval( $p->ordre_passage ),
				'inscription_id' => 0, 'edition' => $edition, 'submission_id' => 0,
				'annonceur_id' => intval( $p->annonceur_id ), 'logo_url' => self::logo_url( intval( $p->logo_id ), 'large' ),
				'slogan' => (string) $p->slogan, 'message' => (string) $p->message,
			];
		}
		return $out;
	}

	public static function logo_url( int $logo_id, string $taille = 'medium' ): string {
		if ( ! $logo_id ) return '';
		$src = wp_get_attachment_image_url( $logo_id, $taille );
		return $src ? (string) $src : '';
	}

	// ─── Suggestions depuis SP Compta (lecture seule) ──────────────────────────

	/** @return string[] noms des sponsors enregistrés dans SP Compta, [] si l'extension est absente */
	public static function sponsors_sp_compta(): array {
		global $wpdb;
		$t = $wpdb->prefix . 'sp_compta_sponsor';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) return [];
		return array_values( array_filter( array_map( 'strval', (array) $wpdb->get_col( "SELECT DISTINCT nom FROM {$t} WHERE nom <> '' ORDER BY nom ASC" ) ) ) ); // table interne, sans saisie
	}
}
