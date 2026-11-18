<?php

namespace TkdProjets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lecture seule de TKD Gala (étape 5, 18/11/2026) : un projet de type « Gala » est relié à une
 * édition du gala, et en affiche les chiffres.
 *
 * TKD Gala n'est pas modifiée : on n'appelle que ses méthodes publiques (Tkd_Gala_DB::reglages(),
 * ::inscriptions(), Tkd_Gala_Mails::totaux(), Tkd_Gala_Candidatures::externes()). Aucune donnée
 * personnelle (noms, dates de naissance) ne sort d'ici : seulement des totaux.
 * Si une de ces méthodes disparaît, available() renvoie faux et l'onglet Gala ne s'affiche plus.
 */
final class Gala {

	public static function available(): bool {
		return class_exists( '\Tkd_Gala_DB' ) && class_exists( '\Tkd_Gala_Mails' )
			&& method_exists( '\Tkd_Gala_DB', 'reglages' ) && method_exists( '\Tkd_Gala_DB', 'inscriptions' )
			&& method_exists( '\Tkd_Gala_Mails', 'totaux' );
	}

	/** Édition en cours dans les réglages du gala (ex. « 2027 »), ou ''. */
	public static function currentEdition(): string {
		return self::available() ? (string) ( \Tkd_Gala_DB::reglages()['edition'] ?? '' ) : '';
	}

	/** Date du gala (AAAA-MM-JJ) dans les réglages, ou null. */
	public static function eventDate(): ?string {
		return self::available() ? Validation::date( \Tkd_Gala_DB::reglages()['date_gala'] ?? '' ) : null;
	}

	/**
	 * Totaux d'une édition.
	 *
	 * @return array<string,mixed>|null null si TKD Gala est absente.
	 */
	public static function summary( string $edition ): ?array {
		if ( ! self::available() ) {
			return null;
		}
		$r       = \Tkd_Gala_DB::reglages();
		$current = (string) ( $r['edition'] ?? '' );
		$tot     = array(
			'participants' => 0,
			'repas'        => 0,
			'montant'      => 0.0,
			'demos'        => 0,
			'sans_audio'   => 0,
			'planches'     => 0,
			'sans_date'    => 0,
		);
		$inscriptions = \Tkd_Gala_DB::inscriptions( $edition );
		$payes        = 0.0;
		foreach ( $inscriptions as $ins ) {
			$t = \Tkd_Gala_Mails::totaux( $ins );
			foreach ( array_keys( $tot ) as $k ) {
				$tot[ $k ] += $t[ $k ] ?? 0;
			}
			if ( ! empty( $ins->paye ) ) {
				$payes += (float) ( $t['montant'] ?? 0 );
			}
		}
		$duree = 0;
		if ( method_exists( '\Tkd_Gala_DB', 'demos_edition' ) ) {
			$duree = array_sum( array_map( static fn( $d ) => (int) $d->duree_sec, \Tkd_Gala_DB::demos_edition( $edition ) ) );
		}
		$retenues = 0;
		if ( class_exists( '\Tkd_Gala_Candidatures' ) && method_exists( '\Tkd_Gala_Candidatures', 'externes' ) ) {
			$retenues = count( array_filter( \Tkd_Gala_Candidatures::externes( $edition ), static fn( $x ) => 1 === (int) $x->retenue ) );
		}
		$prix = (float) ( $r['prix_repas'] ?? 0 );

		return array(
			'edition'               => $edition,
			'edition_en_cours'      => $edition === $current,
			'titre'                 => (string) ( $r['titre'] ?? 'Gala' ),
			'date_gala'             => $edition === $current ? Validation::date( $r['date_gala'] ?? '' ) : null,
			'date_limite'           => $edition === $current ? Validation::date( $r['date_limite'] ?? '' ) : null,
			'inscriptions_ouvertes' => $edition === $current && method_exists( '\Tkd_Gala_DB', 'inscriptions_ouvertes' ) && \Tkd_Gala_DB::inscriptions_ouvertes(),
			'clubs'                 => count( $inscriptions ),
			'demonstrateurs'        => (int) $tot['participants'],
			'repas'                 => (int) $tot['repas'],
			'prix_repas'            => $prix,
			'montant_repas'         => round( (float) $tot['montant'], 2 ),
			'montant_repas_payes'   => round( $payes, 2 ),
			'demos'                 => (int) $tot['demos'],
			'duree_sec'             => (int) $duree,
			'planches'              => (int) $tot['planches'],
			'pistes_manquantes'     => (int) $tot['sans_audio'],
			'dates_manquantes'      => (int) $tot['sans_date'],
			'candidatures_retenues' => $retenues,
			'admin_url'             => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=tkd-gala' ) : '',
		);
	}

	/**
	 * Ligne de budget proposée pour les repas (sans WordPress, testée) : recette, catégorie 70.
	 *
	 * @return array{sens:string,categorie:string,libelle:string,montant:float}|null null s'il n'y a aucun repas.
	 */
	public static function repasSuggestion( int $repas, float $prix ): ?array {
		if ( $repas <= 0 || $prix <= 0 ) {
			return null;
		}
		$prixTxt = rtrim( rtrim( number_format( $prix, 2, ',', '' ), '0' ), ',' );
		return array(
			'sens'      => 'recette',
			'categorie' => '70',
			'libelle'   => 'Repas du gala (' . $repas . ' × ' . $prixTxt . ' €)',
			'montant'   => round( $repas * $prix, 2 ),
		);
	}
}
