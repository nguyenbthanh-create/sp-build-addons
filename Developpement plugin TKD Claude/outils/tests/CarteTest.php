<?php
/**
 * Carte de membre (sp_build/includes/class-carte.php, 13/10/2026) :
 * cadrage de la photo réglé par le bureau, taille du nom selon sa longueur.
 */

use PHPUnit\Framework\TestCase;

final class CarteTest extends TestCase {

	private static function eleve( $cadrage = null ): object {
		return (object) [
			'id' => 1, 'nom' => 'Brunel', 'prenom' => 'Aude',
			'extra_data' => $cadrage === null ? '' : json_encode( [ 'sexe' => 'F', 'photo_cadrage' => $cadrage ] ),
		];
	}

	public function test_cadrage_ramene_dans_ses_bornes(): void {
		$this->assertSame( [ 50, 50, 1.0 ], SP_Cal_Carte::cadrage_valide( 50, 50, 1 ) );
		$this->assertSame( [ 0, 100, 4.0 ], SP_Cal_Carte::cadrage_valide( -20, 250, 9 ) );
		$this->assertSame( [ 33, 67, 1.0 ], SP_Cal_Carte::cadrage_valide( '32.6', '67.4', '0.2' ) );
		$this->assertSame( [ 0, 0, 1.0 ], SP_Cal_Carte::cadrage_valide( 'abc', '', null ) );
		$this->assertSame( [ 10, 20, 2.35 ], SP_Cal_Carte::cadrage_valide( 10, 20, '2.349' ) );
	}

	public function test_fiche_sans_cadrage_photo_centree(): void {
		$this->assertSame( [ 50, 50, 1.0 ], SP_Cal_Carte::cadrage( self::eleve() ) );
		$this->assertSame( [ 50, 50, 1.0 ], SP_Cal_Carte::cadrage( self::eleve( 'n importe quoi' ) ) );
		$this->assertSame( [ 50, 50, 1.0 ], SP_Cal_Carte::cadrage( (object) [ 'extra_data' => '{pas du json' ] ) );
	}

	public function test_cadrage_enregistre_relu_et_applique(): void {
		$el = self::eleve( [ 'x' => 78, 'y' => 30, 'z' => 1.8 ] );
		$this->assertSame( [ 78, 30, 1.8 ], SP_Cal_Carte::cadrage( $el ) );
		$this->assertSame(
			'object-fit:cover;object-position:78% 30%;transform:scale(1.8);transform-origin:78% 30%;',
			SP_Cal_Carte::style_photo( $el )
		);
		$this->assertStringContainsString( 'transform:scale(1);', SP_Cal_Carte::style_photo( self::eleve() ) );
	}

	public function test_taille_du_nom_diminue_avec_sa_longueur(): void {
		$this->assertSame( 24, SP_Cal_Carte::taille_nom( 'BRUNEL' ) );
		$this->assertSame( 20, SP_Cal_Carte::taille_nom( 'DESCHAMPS-LE' ) );
		$this->assertSame( 12, SP_Cal_Carte::taille_nom( 'DE LA TOUR DU PIN-CHAMBLY' ) );
		// Les lettres accentuées comptent pour une lettre.
		$this->assertSame( 24, SP_Cal_Carte::taille_nom( 'ÉLÉONORÉE' ) );
	}
}
