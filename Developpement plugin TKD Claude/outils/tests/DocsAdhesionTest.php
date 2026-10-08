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
