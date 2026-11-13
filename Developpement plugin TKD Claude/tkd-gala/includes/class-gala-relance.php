<?php
/**
 * Relance automatique des clubs dont l'inscription est incomplète (13/11/2026).
 *
 * Tâche planifiée quotidienne (WP-Cron, déclenchée par les visites du site) : à partir de
 * « date limite − N jours » (Réglages : relance_jours, 7 par défaut) et jusqu'à la date limite,
 * chaque inscription qui a encore quelque chose à compléter (Tkd_Gala_Mails::a_completer() :
 * dates de naissance, piste audio, moins de 15 démonstrateurs) reçoit UNE relance par mail.
 * Les relances envoyées sont notées (option tkd_gala_relances, par édition) ; l'organisation
 * reçoit un compte rendu. Bouton « Relancer maintenant » dans Réglages (envoi immédiat à
 * tous les clubs incomplets, même déjà relancés).
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Relance {

	const HOOK   = 'tkd_gala_relance';
	const OPTION = 'tkd_gala_relances';

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'executer' ] );
		add_action( 'init', [ self::class, 'planifier' ] );
	}

	/** Une exécution par jour, vers 9 h (heure du site). */
	public static function planifier(): void {
		if ( wp_next_scheduled( self::HOOK ) ) return;
		$demain_9h = ( new DateTimeImmutable( 'tomorrow 09:00', wp_timezone() ) )->getTimestamp();
		wp_schedule_event( $demain_9h, 'daily', self::HOOK );
	}

	public static function deplanifier(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Jour de début des relances (Y-m-d), '' si pas de relance (désactivée ou sans date limite). */
	public static function jour_relance(): string {
		$r = Tkd_Gala_DB::reglages();
		if ( empty( $r['relance_active'] ) || (string) $r['date_limite'] === '' ) return '';
		$lim = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $r['date_limite'], wp_timezone() );
		if ( ! $lim ) return '';
		return $lim->modify( '-' . max( 0, intval( $r['relance_jours'] ) ) . ' days' )->format( 'Y-m-d' );
	}

	/** @return array<int, string> [ id d'inscription => date d'envoi ] pour l'édition courante */
	public static function envoyees(): array {
		$e = (string) Tkd_Gala_DB::reglages()['edition'];
		$o = get_option( self::OPTION, [] );
		return is_array( $o ) && isset( $o[ $e ] ) && is_array( $o[ $e ] ) ? $o[ $e ] : [];
	}

	private static function noter( int $id ): void {
		$e = (string) Tkd_Gala_DB::reglages()['edition'];
		$o = get_option( self::OPTION, [] );
		if ( ! is_array( $o ) ) $o = [];
		$o[ $e ][ $id ] = current_time( 'mysql' );
		update_option( self::OPTION, $o, false );
	}

	/** Tâche quotidienne. */
	public static function executer(): void {
		$r     = Tkd_Gala_DB::reglages();
		$debut = self::jour_relance();
		$jour  = current_time( 'Y-m-d' );
		if ( $debut === '' || $jour < $debut || $jour > (string) $r['date_limite'] ) return;
		self::relancer( false );
	}

	/**
	 * Envoie la relance aux inscriptions incomplètes de l'édition ; $toutes = true : y compris
	 * celles déjà relancées (bouton de l'admin). Renvoie le nombre de mails partis.
	 */
	public static function relancer( bool $toutes ): int {
		$r     = Tkd_Gala_DB::reglages();
		$deja  = self::envoyees();
		$clubs = [];
		foreach ( Tkd_Gala_DB::inscriptions( (string) $r['edition'] ) as $ins ) {
			if ( ! $toutes && isset( $deja[ intval( $ins->id ) ] ) ) continue;
			if ( ! Tkd_Gala_Mails::a_completer( $ins ) ) continue;
			if ( Tkd_Gala_Mails::relance( $ins ) ) {
				self::noter( intval( $ins->id ) );
				$clubs[] = Tkd_Gala_DB::nom_equipe( $ins );
			}
		}
		if ( $clubs && is_email( (string) $r['email_orga'] ) ) {
			wp_mail(
				(string) $r['email_orga'],
				'[' . get_option( 'blogname', 'Club' ) . '] ' . $r['titre'] . ' ' . $r['edition'] . ' — relance envoyée à ' . count( $clubs ) . ' club(s)',
				"Relance « inscription à compléter » envoyée à :\n\n  • " . implode( "\n  • ", $clubs ) . "\n\nDétail : " . admin_url( 'admin.php?page=tkd-gala' ) . "\n",
				[ 'Content-Type: text/plain; charset=UTF-8' ]
			);
		}
		return count( $clubs );
	}
}
