<?php
/**
 * Documents d'adhésion protégés — SP_Cal_Docs_Adhesion::chemin_relatif() (sp_build/includes/
 * class-docs-adhesion.php) décide quels fichiers passent par le lien réservé au bureau, et
 * refuse toute adresse qui sortirait du dossier des documents.
 */

use PHPUnit\Framework\TestCase;

final class DocsAdhesionTest extends TestCase {

	private const BASE = 'https://tkdclaira.fr/wp-content/uploads/sp-adhesions-docs/';

	public function test_documents_sensibles_reconnus(): void {
		$this->assertSame( 'certificats-medicaux/2026/IMG_2026-1.jpg', SP_Cal_Docs_Adhesion::chemin_relatif( self::BASE . 'certificats-medicaux/2026/IMG_2026-1.jpg' ) );
		$this->assertSame( 'attestations-rc/2026/attestation.pdf', SP_Cal_Docs_Adhesion::chemin_relatif( self::BASE . 'attestations-rc/2026/attestation.pdf' ) );
		$this->assertSame( 'decharges/2025/decharge-1.pdf', SP_Cal_Docs_Adhesion::chemin_relatif( self::BASE . 'decharges/2025/decharge-1.pdf' ) );
		$this->assertSame( 'bons-caf/2026/bon.png', SP_Cal_Docs_Adhesion::chemin_relatif( self::BASE . 'bons-caf/2026/bon.png' ) );
	}

	public function test_meme_adresse_quel_que_soit_le_domaine(): void {
		// La base du site de test garde les adresses de la prod : seul le chemin compte.
		$this->assertSame( 'decharges/2026/d.pdf', SP_Cal_Docs_Adhesion::chemin_relatif( 'https://dev.tkdclaira.fr/wp-content/uploads/sp-adhesions-docs/decharges/2026/d.pdf' ) );
	}

	public function test_photos_et_autres_fichiers_non_concernes(): void {
		$this->assertSame( '', SP_Cal_Docs_Adhesion::chemin_relatif( self::BASE . 'photos/2026/photo.jpg' ) );
		$this->assertSame( '', SP_Cal_Docs_Adhesion::chemin_relatif( 'https://tkdclaira.fr/wp-content/uploads/2026/09/certificat.pdf' ) );
		$this->assertSame( '', SP_Cal_Docs_Adhesion::chemin_relatif( '' ) );
	}

	// ── Documents choisis dans la médiathèque depuis la fiche élève ──

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/tkd-tests-' . uniqid();
		mkdir( $this->dir . '/2026/09', 0777, true );
		file_put_contents( $this->dir . '/2026/09/IMG_2026.jpg', 'image' );
		file_put_contents( $this->dir . '/2026/09/script.php', '<?php' );
		$GLOBALS['tests_uploads']    = [ 'basedir' => $this->dir, 'baseurl' => 'https://tkdclaira.fr/wp-content/uploads' ];
		$GLOBALS['tests_pieces']     = [ 'https://tkdclaira.fr/wp-content/uploads/2026/09/IMG_2026.jpg' => 42 ];
		$GLOBALS['tests_supprimees'] = [];
		$GLOBALS['wpdb']             = new FauxWpdb();
	}

	protected function tearDown(): void {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
		rmdir( $this->dir );
		unset( $GLOBALS['tests_uploads'], $GLOBALS['tests_pieces'], $GLOBALS['tests_supprimees'] );
	}

	public function test_document_de_la_mediatheque_mis_a_l_abri(): void {
		$GLOBALS['wpdb']->valeurs = [ 0, 0, 1 ]; // contenus, métadonnées, fiches (celle-ci seulement)
		$info = [];
		$url  = SP_Cal_Docs_Adhesion::securiser_url( 'https://tkdclaira.fr/wp-content/uploads/2026/09/IMG_2026.jpg', 'certificat_medical', $info );
		$annee = gmdate( 'Y' );
		$this->assertSame( "https://tkdclaira.fr/wp-content/uploads/sp-adhesions-docs/certificats-medicaux/$annee/IMG_2026.jpg", $url );
		$this->assertSame( 'copie', $info['action'] );
		$this->assertFileExists( $this->dir . "/sp-adhesions-docs/certificats-medicaux/$annee/IMG_2026.jpg" );
		$this->assertFileExists( $this->dir . '/sp-adhesions-docs/certificats-medicaux/.htaccess' );
		$this->assertSame( [ 42 ], $GLOBALS['tests_supprimees'], 'La copie publique est retirée de la médiathèque.' );
		$this->assertNotSame( '', SP_Cal_Docs_Adhesion::chemin_relatif( $url ), 'La nouvelle adresse passe par le lien protégé.' );
	}

	public function test_fichier_utilise_ailleurs_garde_dans_la_mediatheque(): void {
		$GLOBALS['wpdb']->valeurs = [ 1, 0, 1 ]; // présent dans un contenu du site
		$info = [];
		SP_Cal_Docs_Adhesion::securiser_url( 'https://tkdclaira.fr/wp-content/uploads/2026/09/IMG_2026.jpg', 'decharge_honneur', $info );
		$this->assertSame( 'copie_gardee', $info['action'] );
		$this->assertSame( [], $GLOBALS['tests_supprimees'] );
	}

	public function test_cas_laisses_tels_quels(): void {
		$cas = [
			[ self::BASE . 'decharges/2026/d.pdf', 'decharge_honneur', 'deja' ],
			[ 'https://autre-site.fr/certificat.pdf', 'certificat_medical', 'ignore' ],
			[ 'https://tkdclaira.fr/wp-content/uploads/2026/09/absent.pdf', 'certificat_medical', 'echec' ],
			[ 'https://tkdclaira.fr/wp-content/uploads/2026/09/script.php', 'certificat_medical', 'echec' ],
			[ 'https://tkdclaira.fr/wp-content/uploads/2026/09/IMG_2026.jpg', 'photo', 'ignore' ],
			[ '', 'bon_caf', 'ignore' ],
		];
		foreach ( $cas as [ $url, $cle, $attendu ] ) {
			$info = [];
			$this->assertSame( $url, SP_Cal_Docs_Adhesion::securiser_url( $url, $cle, $info ), $url );
			$this->assertSame( $attendu, $info['action'], $url );
		}
		$this->assertSame( [], $GLOBALS['tests_supprimees'] );
	}

	public function test_adresses_dangereuses_refusees(): void {
		foreach ( [
			'certificats-medicaux/2026/../../../../wp-config.php',
			'certificats-medicaux/../photos/2026/a.jpg',
			'certificats-medicaux/2026/.htaccess',
			'certificats-medicaux/2026/sous/dossier.pdf',
			'certificats-medicaux/2026/a..pdf',
			'certificats-medicaux/20266/a.pdf',
			'CERTIFICATS-MEDICAUX/2026/a.pdf',
			'certificats-medicaux/2026/a%2F..%2Fb.pdf',
			'certificats-medicaux/2026/a\\..\\b.pdf',
		] as $rel ) {
			$this->assertSame( '', SP_Cal_Docs_Adhesion::chemin_relatif( self::BASE . $rel ), $rel );
		}
	}
}
