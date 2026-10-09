<?php
/**
 * Dossier par adhérent — SP_Cal_Docs_Adhesion (sp_build/includes/class-docs-adhesion.php) :
 * chemins acceptés (et piégés refusés), photo à adresse signée, rangement d'une fiche
 * (copie dans le dossier de l'adhérent, mise à jour des adresses, retrait de l'original).
 */

use PHPUnit\Framework\TestCase;

final class DocsAdhesionTest extends TestCase {

	private const ANCIEN = 'https://tkdclaira.fr/wp-content/uploads/sp-adhesions-docs/';
	private const BASE   = 'https://tkdclaira.fr/wp-content/uploads/sp-adherents/';

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/tkd-tests-' . uniqid();
		mkdir( $this->dir . '/2026/09', 0777, true );
		mkdir( $this->dir . '/sp-adhesions-docs/certificats-medicaux/2026', 0777, true );
		file_put_contents( $this->dir . '/2026/09/IMG_2026.jpg', 'image' );
		file_put_contents( $this->dir . '/2026/09/script.php', '<?php' );
		file_put_contents( $this->dir . '/sp-adhesions-docs/certificats-medicaux/2026/certif.pdf', '%PDF' );
		$GLOBALS['tests_uploads']    = [ 'basedir' => $this->dir, 'baseurl' => 'https://tkdclaira.fr/wp-content/uploads' ];
		$GLOBALS['tests_pieces']     = [ 'https://tkdclaira.fr/wp-content/uploads/2026/09/IMG_2026.jpg' => 42 ];
		$GLOBALS['tests_supprimees'] = [];
		$GLOBALS['tests_options']    = [];
		$GLOBALS['wpdb']             = new FauxWpdb();
	}

	protected function tearDown(): void {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
		rmdir( $this->dir );
		unset( $GLOBALS['tests_uploads'], $GLOBALS['tests_pieces'], $GLOBALS['tests_supprimees'] );
	}

	// ── Chemins ──

	public function test_chemins_du_dossier_adherent(): void {
		$this->assertSame( '123-k7f2q9ab/certificat-medical-IMG_1.jpg', SP_Cal_Docs_Adhesion::chemin_adherent( self::BASE . '123-k7f2q9ab/certificat-medical-IMG_1.jpg' ) );
		$this->assertSame( 'demandes/x3p1abcd/photo-moi.png', SP_Cal_Docs_Adhesion::chemin_adherent( self::BASE . 'demandes/x3p1abcd/photo-moi.png' ) );
		$photo = SP_Cal_Docs_Adhesion::url_photo( '123-k7f2q9ab/photo-moi.jpg' );
		$this->assertStringStartsWith( 'https://tkdclaira.fr/?sp_photo=', $photo );
		$this->assertSame( '123-k7f2q9ab/photo-moi.jpg', SP_Cal_Docs_Adhesion::chemin_adherent( $photo ) );
	}

	public function test_chemins_pieges_refuses(): void {
		foreach ( [
			'123-k7f2q9ab/../../../wp-config.php',
			'123-k7f2q9ab/.htaccess',
			'123-k7f2q9ab/sous/dossier.pdf',
			'abc-k7f2q9ab/a.pdf',
			'123-K7F2Q9AB/a.pdf',
			'123-k7/a.pdf',
			'demandes/../123-k7f2q9ab/a.pdf',
			'123-k7f2q9ab/a b.pdf',
		] as $rel ) {
			$this->assertSame( '', SP_Cal_Docs_Adhesion::chemin_adherent( self::BASE . $rel ), $rel );
		}
	}

	public function test_signature_de_la_photo(): void {
		$sig = new ReflectionMethod( SP_Cal_Docs_Adhesion::class, 'signature' );
		$a   = $sig->invoke( null, '123-k7f2q9ab/photo-moi.jpg' );
		$this->assertSame( $a, $sig->invoke( null, '123-k7f2q9ab/photo-moi.jpg' ), 'Même clé, même signature.' );
		$this->assertNotSame( $a, $sig->invoke( null, '124-k7f2q9ab/photo-moi.jpg' ), 'Un autre fichier, une autre signature.' );
		$this->assertSame( 24, strlen( $a ) );
	}

	public function test_ancien_rangement_par_type_toujours_lu(): void {
		$this->assertSame( 'certificats-medicaux/2026/IMG_2026-1.jpg', SP_Cal_Docs_Adhesion::chemin_relatif( self::ANCIEN . 'certificats-medicaux/2026/IMG_2026-1.jpg' ) );
		$this->assertSame( '', SP_Cal_Docs_Adhesion::chemin_relatif( self::ANCIEN . 'photos/2026/photo.jpg' ) );
		$this->assertSame( '', SP_Cal_Docs_Adhesion::chemin_relatif( self::ANCIEN . 'certificats-medicaux/2026/../../../../wp-config.php' ) );
	}

	public function test_liens_de_l_administration(): void {
		$this->assertStringContainsString( 'admin-post.php?action=sp_adh_doc&a=', SP_Cal_Docs_Adhesion::lien( self::BASE . '123-k7f2q9ab/decharge-d.pdf' ) );
		$this->assertStringContainsString( 'admin-post.php?action=sp_adh_doc&f=', SP_Cal_Docs_Adhesion::lien( self::ANCIEN . 'decharges/2026/d.pdf' ) );
		$photo = SP_Cal_Docs_Adhesion::url_photo( '123-k7f2q9ab/photo-moi.jpg' );
		$this->assertSame( $photo, SP_Cal_Docs_Adhesion::lien( $photo ), 'Photo signée : déjà lisible telle quelle.' );
		$this->assertSame( 'https://autre.fr/x.pdf', SP_Cal_Docs_Adhesion::lien( 'https://autre.fr/x.pdf' ) );
	}

	public function test_photos_a_trier_cochees_d_office(): void {
		foreach ( [ 'IMG_3469', 'image', 'Image', 'IMG-20260412', '17912137973185733780728289217336', 'PXL_20260101_1200', 'DSC0042', 'WhatsApp Image 2026-04-12' ] as $t ) {
			$this->assertTrue( SP_Cal_Adherents_A_Trier::nom_de_telephone( $t ), $t );
		}
		foreach ( [ 'Salle polyvalentClaira', 'cafPassLoisir', 'logo-pass-sport', 'Image-Contact.jpg', 'Article_anniversaire', 'IllustrationRenfo' ] as $t ) {
			$this->assertFalse( SP_Cal_Adherents_A_Trier::nom_de_telephone( $t ), $t );
		}
	}

	// ── Rangement d'une fiche ──

	public function test_fiche_rangee_dans_le_dossier_de_l_adherent(): void {
		$GLOBALS['wpdb']->lignes = [ (object) [
			'id'         => 7,
			'photo_url'  => 'https://tkdclaira.fr/wp-content/uploads/2026/09/IMG_2026.jpg',          // médiathèque
			'extra_data' => json_encode( [ 'sexe' => 'F', 'documents' => [
				'certificat_medical'      => self::ANCIEN . 'certificats-medicaux/2026/certif.pdf',   // ancien rangement
				'date_certificat_medical' => '2026-09-01',
				'attestation_rc'          => '',
				'bon_caf'                 => 'https://tkdclaira.fr/wp-content/uploads/2026/09/script.php', // type refusé
			] ] ),
		] ];
		$bilan = SP_Cal_Docs_Adhesion::ranger_fiche( 7 );

		$this->assertSame( 2, $bilan['range'] );
		$dossiers = glob( $this->dir . '/sp-adherents/7-*', GLOB_ONLYDIR );
		$this->assertCount( 1, $dossiers );
		$this->assertFileExists( $dossiers[0] . '/photo-IMG_2026.jpg' );
		$this->assertFileExists( $dossiers[0] . '/certificat-medical-certif.pdf' );
		$this->assertFileExists( $this->dir . '/sp-adherents/.htaccess', 'Dossier fermé au web.' );

		// Fiche mise à jour : photo signée, document dans le dossier, le reste intact.
		$maj = $GLOBALS['wpdb']->ecritures[0][1];
		$this->assertSame( 'wp_sp_cal_eleves', $maj[0] );
		$this->assertStringContainsString( '?sp_photo=', $maj[1]['photo_url'] );
		$extra = json_decode( $maj[1]['extra_data'], true );
		$this->assertStringStartsWith( self::BASE . '7-', $extra['documents']['certificat_medical'] );
		$this->assertSame( '2026-09-01', $extra['documents']['date_certificat_medical'] );
		$this->assertSame( 'F', $extra['sexe'] );
		$this->assertStringEndsWith( 'script.php', $extra['documents']['bon_caf'], 'Fichier non pris en charge : laissé tel quel.' );

		// Originaux retirés (plus utilisés nulle part).
		$this->assertFileDoesNotExist( $this->dir . '/sp-adhesions-docs/certificats-medicaux/2026/certif.pdf' );
		$this->assertSame( [ 42 ], $GLOBALS['tests_supprimees'] );
	}

	public function test_original_garde_s_il_sert_ailleurs(): void {
		$GLOBALS['wpdb']->lignes  = [ (object) [ 'id' => 8, 'photo_url' => 'https://tkdclaira.fr/wp-content/uploads/2026/09/IMG_2026.jpg', 'extra_data' => '{}' ] ];
		$GLOBALS['wpdb']->valeurs = [ 0, 0, 1 ]; // fiches, demandes, puis un contenu du site
		$bilan = SP_Cal_Docs_Adhesion::ranger_fiche( 8 );
		$this->assertSame( 1, $bilan['range'] );
		$this->assertSame( 1, $bilan['garde'] );
		$this->assertSame( [], $GLOBALS['tests_supprimees'] );
	}

	public function test_fiche_deja_rangee_ou_fichier_absent(): void {
		$GLOBALS['wpdb']->lignes = [ (object) [ 'id' => 9,
			'photo_url'  => SP_Cal_Docs_Adhesion::url_photo( '9-abcdef12/photo-a.jpg' ),
			'extra_data' => json_encode( [ 'documents' => [ 'decharge_honneur' => 'https://tkdclaira.fr/wp-content/uploads/2026/09/absent.pdf' ] ] ),
		] ];
		$bilan = SP_Cal_Docs_Adhesion::ranger_fiche( 9 );
		$this->assertSame( [ 'deja' => 1, 'range' => 0, 'garde' => 0, 'absent' => 1, 'echec' => 0 ], $bilan );
		$this->assertSame( [], $GLOBALS['wpdb']->ecritures, 'Rien à écrire.' );
	}
}
