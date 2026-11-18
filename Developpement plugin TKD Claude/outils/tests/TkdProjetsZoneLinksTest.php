<?php
/**
 * TKD Projets — liens privés « Ma carte » (tkd-projets/src/ZoneLinks.php)
 * et fichiers acceptés en pièce jointe (tkd-projets/src/Attachments.php).
 */

use PHPUnit\Framework\TestCase;
use TkdProjets\Attachments;
use TkdProjets\ZoneLinks;

final class TkdProjetsZoneLinksTest extends TestCase {

	public function test_cle_aleatoire_et_empreinte(): void {
		$a = ZoneLinks::newKey();
		$this->assertTrue( ZoneLinks::isKeyFormat( $a ) );
		$this->assertNotSame( $a, ZoneLinks::newKey() );
		$this->assertFalse( ZoneLinks::isKeyFormat( 'ABCDEFabcdef0123456789abcdef0123' ) ); // ancien format 1.x
		$this->assertFalse( ZoneLinks::isKeyFormat( $a . 'x' ) );
		$h = ZoneLinks::hash( $a, 'secret' );
		$this->assertSame( 64, strlen( $h ) );
		$this->assertNotSame( $h, ZoneLinks::hash( $a, 'autre-secret' ) );
		$this->assertStringNotContainsString( $a, $h );
	}

	public function test_expiration(): void {
		// 30 jours après la date la plus tardive (tâche ou évènement).
		$this->assertSame( '2027-01-14 23:59:59', ZoneLinks::expiry( '2026-12-15', '2026-12-10', '2026-11-18' ) );
		$this->assertSame( '2027-01-18 23:59:59', ZoneLinks::expiry( '2026-12-15', '2026-12-19', '2026-11-18' ) );
		// Jamais moins de 7 jours après l'envoi.
		$this->assertSame( '2026-11-25 23:59:59', ZoneLinks::expiry( '2026-09-01', null, '2026-11-18' ) );
		// Aucune date : 90 jours.
		$this->assertSame( '2027-02-16 23:59:59', ZoneLinks::expiry( null, '', '2026-11-18' ) );
	}

	public function test_lien_refuse(): void {
		$ok = (object) array( 'expires_at' => '2026-12-31 23:59:59', 'revoked_at' => null );
		$this->assertSame( '', ZoneLinks::refusal( $ok, 'doing', 'en_cours', '2026-11-18 10:00:00' ) );
		$this->assertSame( 'expired', ZoneLinks::refusal( $ok, 'doing', 'en_cours', '2027-01-01 00:00:00' ) );
		$this->assertSame( 'finished', ZoneLinks::refusal( $ok, 'done', 'en_cours', '2026-11-18 10:00:00' ) );
		$this->assertSame( 'finished', ZoneLinks::refusal( $ok, 'todo', 'termine', '2026-11-18 10:00:00' ) );
		$coupe = (object) array( 'expires_at' => '2026-12-31 23:59:59', 'revoked_at' => '2026-11-18 09:00:00' );
		$this->assertSame( 'revoked', ZoneLinks::refusal( $coupe, 'doing', 'en_cours', '2026-11-18 10:00:00' ) );
	}

	public function test_pieces_jointes_hors_dossiers_proteges(): void {
		$tmp  = sys_get_temp_dir();
		$base = $tmp . '/tkdp-uploads-' . uniqid();
		foreach ( array( '2026/11', 'sp-compta/2026/11', 'tkd-gala/2027' ) as $d ) {
			mkdir( "$base/$d", 0777, true );
		}
		file_put_contents( "$base/2026/11/affiche.pdf", 'x' );
		file_put_contents( "$base/sp-compta/2026/11/facture.pdf", 'x' );
		file_put_contents( "$base/tkd-gala/2027/musique.mp3", 'x' );
		file_put_contents( "$tmp/tkdp-hors-uploads.txt", 'x' );

		$this->assertTrue( Attachments::isPublicPath( "$base/2026/11/affiche.pdf", $base ) );
		$this->assertFalse( Attachments::isPublicPath( "$base/sp-compta/2026/11/facture.pdf", $base ), 'justificatif SP Compta' );
		$this->assertFalse( Attachments::isPublicPath( "$base/tkd-gala/2027/musique.mp3", $base ), 'piste du gala' );
		$this->assertFalse( Attachments::isPublicPath( "$base/2026/11/../../sp-compta/2026/11/facture.pdf", $base ), 'chemin détourné' );
		$this->assertFalse( Attachments::isPublicPath( "$tmp/tkdp-hors-uploads.txt", $base ) );
		$this->assertFalse( Attachments::isPublicPath( "$base/absent.pdf", $base ) );
	}
}
