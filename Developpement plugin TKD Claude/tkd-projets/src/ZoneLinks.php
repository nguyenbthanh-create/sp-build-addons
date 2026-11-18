<?php

namespace TkdProjets;

/**
 * Clés des liens privés « Ma carte », sans WordPress (testé dans outils/tests/TkdProjetsZoneLinksTest.php).
 *
 * - La clé (32 caractères hexadécimaux, 128 bits aléatoires) ne part que dans l'e-mail ;
 *   la base ne garde que son empreinte HMAC-SHA256 (une fuite de la base ne donne aucun lien).
 * - Un lien expire 30 jours après la date limite de la tâche (ou la date de l'évènement si elle
 *   est plus tardive), jamais moins de 7 jours après l'envoi, 90 jours sans aucune date.
 * - Il cesse aussi de marcher si la tâche est terminée, le projet terminé ou le lien coupé.
 */
final class ZoneLinks {

	public const PARAM = 'tkdp-zone';

	public static function newKey(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	public static function isKeyFormat( string $key ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $key );
	}

	public static function hash( string $key, string $secret ): string {
		return hash_hmac( 'sha256', $key, $secret );
	}

	/**
	 * Date d'expiration (AAAA-MM-JJ 23:59:59).
	 *
	 * @param string|null $taskEnd   Date limite de la tâche.
	 * @param string|null $eventDate Date de l'évènement du projet.
	 * @param string      $today     AAAA-MM-JJ.
	 */
	public static function expiry( ?string $taskEnd, ?string $eventDate, string $today ): string {
		$utc   = new \DateTimeZone( 'UTC' );
		$base  = new \DateTimeImmutable( $today, $utc );
		$dates = array_filter( array( Validation::date( $taskEnd ), Validation::date( $eventDate ) ) );
		if ( $dates ) {
			$last   = new \DateTimeImmutable( max( $dates ), $utc );
			$expiry = $last->modify( '+30 days' );
			$min    = $base->modify( '+7 days' );
			if ( $expiry < $min ) {
				$expiry = $min;
			}
		} else {
			$expiry = $base->modify( '+90 days' );
		}
		return $expiry->format( 'Y-m-d' ) . ' 23:59:59';
	}

	/**
	 * Le lien est-il encore utilisable ? Renvoie '' si oui, sinon la raison.
	 *
	 * @param object $link          Ligne de la table des liens (expires_at, revoked_at).
	 * @param string $taskStatus    Statut de la tâche.
	 * @param string $projectStatus Statut du projet.
	 * @param string $now           AAAA-MM-JJ HH:MM:SS.
	 */
	public static function refusal( object $link, string $taskStatus, string $projectStatus, string $now ): string {
		if ( ! empty( $link->revoked_at ) ) {
			return 'revoked';
		}
		if ( (string) $link->expires_at < $now ) {
			return 'expired';
		}
		if ( 'done' === $taskStatus || 'termine' === $projectStatus ) {
			return 'finished';
		}
		return '';
	}
}
