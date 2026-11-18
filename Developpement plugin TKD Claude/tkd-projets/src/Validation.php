<?php

namespace TkdProjets;

/**
 * Nettoyage des valeurs reçues, sans WordPress (testé dans outils/tests/TkdProjetsValidationTest.php).
 * Les textes libres passent en plus par sanitize_text_field() / sanitize_textarea_field() dans l'API.
 */
final class Validation {

	public const STATUSES   = array( 'todo', 'doing', 'done' );
	public const PRIORITIES = array( 'low', 'normal', 'high' );
	public const PROJECT_STATUSES = array( 'en_cours', 'termine' );

	/** Date AAAA-MM-JJ valide, sinon null. */
	public static function date( $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $value;
		}
		return null;
	}

	/** Couleur #rrggbb (minuscules), sinon la couleur par défaut. */
	public static function color( $value, string $default = '#2271b1' ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', $value ) ) {
			if ( 4 === strlen( $value ) ) {
				$value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
			}
			return strtolower( $value );
		}
		return $default;
	}

	/** Valeur d'une liste fermée, sinon la valeur par défaut. */
	public static function oneOf( $value, array $allowed, string $default ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	public static function percent( $value ): int {
		return max( 0, min( 100, (int) $value ) );
	}

	/** Coupe un texte à $max caractères (UTF-8). */
	public static function cut( string $value, int $max ): string {
		return mb_substr( $value, 0, $max );
	}

	/**
	 * Liste d'identifiants positifs, sans doublon, au plus $max.
	 *
	 * @param mixed $value Tableau ou « 12,15 ».
	 * @return int[]
	 */
	public static function ids( $value, int $max = 20 ): array {
		$ids = is_array( $value ) ? $value : explode( ',', (string) $value );
		$ids = array_map( static fn( $id ) => is_numeric( $id ) ? (int) $id : 0, $ids );
		$ids = array_values( array_unique( array_filter( $ids, static fn( $id ) => $id > 0 ) ) );
		return array_slice( $ids, 0, $max );
	}

	/**
	 * Coins d'une zone : [[lat, lng], …], de 3 à 500 points valides, sinon null.
	 *
	 * @param mixed $value Tableau ou JSON.
	 */
	public static function coords( $value ): ?array {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$out = array();
		foreach ( $value as $pt ) {
			if ( ! is_array( $pt ) || count( $pt ) < 2 || ! is_numeric( $pt[0] ?? null ) || ! is_numeric( $pt[1] ?? null ) ) {
				continue;
			}
			$lat = (float) $pt[0];
			$lng = (float) $pt[1];
			if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
				continue;
			}
			$out[] = array( round( $lat, 6 ), round( $lng, 6 ) );
			if ( count( $out ) >= 500 ) {
				break;
			}
		}
		return count( $out ) >= 3 ? $out : null;
	}

	/**
	 * Équipe d'un projet : [ user_id => rôle ] à partir de [ { user_id, role }, … ].
	 *
	 * @param mixed $value
	 * @return array<int,string>
	 */
	public static function team( $value ): array {
		$out = array();
		if ( ! is_array( $value ) ) {
			return $out;
		}
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$uid  = is_numeric( $row['user_id'] ?? null ) ? (int) $row['user_id'] : 0;
			$role = $row['role'] ?? '';
			if ( $uid > 0 && in_array( $role, Policy::TEAM_ROLES, true ) ) {
				// Si une personne apparaît deux fois, le rôle de responsable l'emporte.
				$out[ $uid ] = ( Policy::RESPONSABLE === ( $out[ $uid ] ?? '' ) ) ? Policy::RESPONSABLE : $role;
			}
			if ( count( $out ) >= 200 ) {
				break;
			}
		}
		return $out;
	}
}
