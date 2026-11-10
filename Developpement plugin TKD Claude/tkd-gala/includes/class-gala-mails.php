<?php
/**
 * Mails du gala (texte brut, wp_mail) :
 *   - au représentant du club : récapitulatif + lien personnel, à chaque enregistrement ;
 *   - à l'organisation : nouvelle inscription ou modification, avec le récapitulatif ;
 *   - « lien perdu » : renvoi du lien personnel à l'adresse enregistrée.
 *
 * @package TKD_Gala
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Tkd_Gala_Mails {

	private static function envoyer( string $a, string $sujet, string $corps, string $repondre_a = '' ): bool {
		$h = [ 'Content-Type: text/plain; charset=UTF-8' ];
		if ( is_email( $repondre_a ) ) $h[] = 'Reply-To: ' . $repondre_a;
		return is_email( $a ) && wp_mail( $a, $sujet, $corps, $h );
	}

	private static function prefixe(): string {
		$r = Tkd_Gala_DB::reglages();
		return '[' . get_option( 'blogname', 'Club' ) . '] ' . $r['titre'] . ( $r['edition'] ? ' ' . $r['edition'] : '' );
	}

	/** Totaux d'une inscription : démonstrateurs, repas, montant des repas, démonstrations, audio manquants. */
	public static function totaux( object $ins ): array {
		$p     = Tkd_Gala_DB::participants( intval( $ins->id ) );
		$d     = Tkd_Gala_DB::demos( intval( $ins->id ) );
		$repas = count( array_filter( $p, static fn( $x ) => (int) $x->repas === 1 ) );
		return [
			'participants' => count( $p ),
			'repas'        => $repas,
			'montant'      => $repas * (float) Tkd_Gala_DB::reglages()['prix_repas'],
			'demos'        => count( $d ),
			'sans_audio'   => count( array_filter( $d, static fn( $x ) => (string) $x->audio_fichier === '' ) ),
			'planches'     => array_sum( array_map( static fn( $x ) => (int) $x->planches, $d ) ),
		];
	}

	/** Récapitulatif lisible d'une inscription (mails et page en lecture seule). */
	public static function recap_texte( object $ins ): string {
		$r = Tkd_Gala_DB::reglages();
		$t = self::totaux( $ins );
		$s  = "CLUB\n";
		$s .= '  Nom : ' . $ins->club_nom . "\n";
		if ( $ins->affiliation !== '' ) $s .= '  Affiliation FFTDA : ' . $ins->affiliation . "\n";
		$s .= '  Représentant : ' . $ins->representant . ' (' . $ins->role . ")\n";
		$s .= '  Téléphone : ' . $ins->telephone . "\n";
		$s .= '  Mail : ' . $ins->email . "\n\n";

		$s .= 'DÉMONSTRATEURS (' . $t['participants'] . ")\n";
		foreach ( Tkd_Gala_DB::participants( intval( $ins->id ) ) as $p ) {
			$s .= '  • ' . $p->nom . ' ' . $p->prenom . ' — né(e) le ' . Tkd_Gala_Front::date_fr( (string) $p->date_naissance ) . ( (int) $p->repas ? ' — repas' : '' ) . "\n";
		}
		$s .= '  Repas : ' . $t['repas'] . ' × ' . Tkd_Gala_Front::montant( (float) $r['prix_repas'] ) . ' = ' . Tkd_Gala_Front::montant( (float) $t['montant'] ) . ( $t['repas'] ? ' — ' . $r['paiement'] : '' ) . "\n\n";

		$s .= "DÉMONSTRATION\n";
		foreach ( Tkd_Gala_DB::demos( intval( $ins->id ) ) as $d ) {
			$s .= '  Durée : ' . Tkd_Gala_Front::duree( (int) $d->duree_sec ) . "\n";
			$s .= '  Couleurs : ' . $d->couleur1 . ' et ' . $d->couleur2 . ' · Planches : ' . intval( $d->planches ) . "\n";
			$s .= '  Piste audio : ' . ( $d->audio_nom !== '' ? $d->audio_nom : '⚠️ pas encore envoyée' ) . "\n";
		}
		return $s;
	}

	public static function confirmation( object $ins, bool $nouvelle ): bool {
		$r     = Tkd_Gala_DB::reglages();
		$sujet = self::prefixe() . ' — ' . ( $nouvelle ? 'inscription enregistrée' : 'inscription modifiée' ) . ' (' . $ins->club_nom . ')';
		$c  = 'Bonjour ' . $ins->representant . ",\n\n";
		$c .= $nouvelle ? "L'inscription de votre club au gala est bien enregistrée. Merci !\n\n" : "Les modifications de votre inscription au gala sont bien enregistrées.\n\n";
		$c .= "Votre lien personnel, pour revenir modifier l'inscription" . ( $r['date_limite'] ? ' jusqu\'au ' . Tkd_Gala_Front::date_fr( $r['date_limite'] ) . ' inclus' : '' ) . " :\n";
		$c .= Tkd_Gala_Front::lien_club( $ins->token ) . "\n";
		$c .= "Ce lien est personnel : ne le partagez pas.\n\n";
		$c .= "RÉCAPITULATIF\n\n" . self::recap_texte( $ins ) . "\n";
		$c .= '-- ' . "\n" . get_option( 'blogname', 'Club' );
		return self::envoyer( $ins->email, $sujet, $c, (string) $r['email_orga'] );
	}

	public static function organisation( object $ins, bool $nouvelle ): bool {
		$r     = Tkd_Gala_DB::reglages();
		$t     = self::totaux( $ins );
		$sujet = self::prefixe() . ' — ' . ( $nouvelle ? 'nouvelle inscription' : 'modification' ) . ' : ' . $ins->club_nom
			. ' (' . $t['participants'] . ' démonstrateurs, ' . $t['repas'] . ' repas)';
		$c  = ( $nouvelle ? 'Nouvelle inscription au gala.' : 'Une inscription au gala vient d\'être modifiée.' ) . "\n\n";
		$c .= self::recap_texte( $ins ) . "\n";
		$c .= 'Toutes les inscriptions : ' . admin_url( 'admin.php?page=tkd-gala' ) . "\n";
		return self::envoyer( (string) $r['email_orga'], $sujet, $c, $ins->email );
	}

	public static function lien( object $ins ): bool {
		$r     = Tkd_Gala_DB::reglages();
		$sujet = self::prefixe() . ' — votre lien personnel (' . $ins->club_nom . ')';
		$c  = 'Bonjour ' . $ins->representant . ",\n\n";
		$c .= "Voici le lien personnel de l'inscription de " . $ins->club_nom . ' au gala' . ( $r['date_limite'] ? ' (modifiable jusqu\'au ' . Tkd_Gala_Front::date_fr( $r['date_limite'] ) . ' inclus)' : '' ) . " :\n";
		$c .= Tkd_Gala_Front::lien_club( $ins->token ) . "\n\n";
		$c .= "Si vous n'avez pas demandé ce lien, ignorez ce message.\n\n";
		$c .= '-- ' . "\n" . get_option( 'blogname', 'Club' );
		return self::envoyer( $ins->email, $sujet, $c, (string) $r['email_orga'] );
	}
}
