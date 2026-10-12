<?php
/**
 * Tenues du renforcement musculaire (sp_build/includes/class-tenues.php, 12/10/2026) :
 * lecture des tailles notées sur les fiches, effet des mouvements sur le stock, besoin de commande.
 */

use PHPUnit\Framework\TestCase;

final class TenuesTest extends TestCase {

	private const TAILLES = [ 'XS', 'S', 'M', 'L', 'XL', 'XXL' ];

	private static function eleve( int $id, string $sexe, string $haut, string $bas, string $discipline = 'RENFO' ): object {
		return (object) [
			'id' => $id, 'nom' => 'NOM' . $id, 'prenom' => 'Prenom', 'categorie_saisie' => $discipline,
			'extra_data' => $sexe === '' ? '' : json_encode( [ 'sexe' => $sexe ] ),
			'taille_tshirt' => $haut, 'taille_pantalon' => $bas,
		];
	}

	public function test_lecture_des_tailles_des_fiches(): void {
		$cas = [
			'M' => 'M', 'm' => 'M', ' l ' => 'L', 'Taille L' => 'L', 'XL' => 'XL', 'xs' => 'XS', 'XXL' => 'XXL',
			'2XL' => 'XXL', '2 xl' => 'XXL', '14 ans / xs' => 'XS', 'S/M' => 'S',
			'' => '', '38' => '', '14 ans' => '', 'XXXL' => '', '3XL' => '', 'Medium' => '',
		];
		foreach ( $cas as $brut => $attendu ) {
			$this->assertSame( $attendu, SP_Cal_Tenues::lire_taille( (string) $brut, self::TAILLES ), "« $brut »" );
		}
	}

	public function test_liste_des_tailles_reglable(): void {
		$this->assertSame( [ 'S', 'M', 'L' ], SP_Cal_Tenues::tailles_depuis_texte( 's, m ; L  m' ) );
		$this->assertSame( self::TAILLES, SP_Cal_Tenues::tailles_depuis_texte( '' ), 'liste vide : tailles par défaut' );
	}

	public function test_effets_des_mouvements(): void {
		$this->assertSame( [ 5, 0 ],  SP_Cal_Tenues::effets_mouvement( 'achat', 5 ) );
		$this->assertSame( [ -1, 1 ], SP_Cal_Tenues::effets_mouvement( 'remise', 1 ) );
		$this->assertSame( [ 1, -1 ], SP_Cal_Tenues::effets_mouvement( 'restitution', 1, 'bon' ) );
		$this->assertSame( [ 0, -1 ], SP_Cal_Tenues::effets_mouvement( 'restitution', 1, 'a_reformer' ), 'pièce à réformer : ne rentre pas en stock' );
		$this->assertSame( [ 0, -1 ], SP_Cal_Tenues::effets_mouvement( 'perte', 1 ) );
		$this->assertSame( [ -2, 0 ], SP_Cal_Tenues::effets_mouvement( 'reforme', 2 ) );
		$this->assertSame( [ -3, 0 ], SP_Cal_Tenues::effets_mouvement( 'inventaire', 3, '', -1 ) );
	}

	public function test_qui_est_concerne(): void {
		$this->assertTrue( SP_Cal_Tenues::est_renfo( self::eleve( 1, 'M', '', '' ) ) );
		$this->assertTrue( SP_Cal_Tenues::est_renfo( self::eleve( 1, 'M', '', '', 'Renfo & Ados/Adultes' ) ) );
		$this->assertFalse( SP_Cal_Tenues::est_renfo( self::eleve( 1, 'M', '', '', 'TKD' ) ) );
	}

	public function test_piece_attendue_selon_le_sexe_et_la_fiche(): void {
		$this->assertSame( [ 'haut_f', 'S' ], SP_Cal_Tenues::attendu( self::eleve( 1, 'F', 's', 'M' ), 'haut', self::TAILLES ) );
		$this->assertSame( [ 'bas_f', 'M' ],  SP_Cal_Tenues::attendu( self::eleve( 1, 'F', 's', 'M' ), 'bas', self::TAILLES ) );
		$this->assertSame( [ 'haut_h', '' ],  SP_Cal_Tenues::attendu( self::eleve( 1, 'M', '', 'L' ), 'haut', self::TAILLES ), 'taille du haut inconnue' );
		$this->assertSame( [ '', 'L' ],       SP_Cal_Tenues::attendu( self::eleve( 1, '', 'L', 'L' ), 'haut', self::TAILLES ), 'sexe inconnu' );
	}

	public function test_statut_d_une_nouvelle_demande(): void {
		$this->assertSame( 'ouverte',    SP_Cal_Tenues::statut_initial( 'taille', 1 ), 'pièce disponible : réservée' );
		$this->assertSame( 'en_attente', SP_Cal_Tenues::statut_initial( 'taille', 0 ), 'pas de stock : en attente' );
		$this->assertSame( 'en_attente', SP_Cal_Tenues::statut_initial( 'remplacement', -1 ) );
		$this->assertSame( 'ouverte',    SP_Cal_Tenues::statut_initial( 'restitution', 0 ), 'une restitution ne dépend pas du stock' );
	}

	public function test_libelle_d_une_demande(): void {
		$d = (object) [ 'nature' => 'taille', 'rendu_modele' => 'haut_f', 'rendu_taille' => 'M', 'souhait_modele' => 'haut_f', 'souhait_taille' => 'L' ];
		$this->assertSame( 'Changement de taille : Haut femme M → Haut femme L', SP_Cal_Tenues::libelle_demande( $d ) );
		$d = (object) [ 'nature' => 'restitution', 'rendu_modele' => 'bas_h', 'rendu_taille' => 'XL', 'souhait_modele' => '', 'souhait_taille' => '' ];
		$this->assertSame( 'Restitution : Bas homme XL', SP_Cal_Tenues::libelle_demande( $d ) );
		$d = (object) [ 'nature' => 'remplacement', 'rendu_modele' => 'bas_f', 'rendu_taille' => 'S', 'souhait_modele' => 'bas_f', 'souhait_taille' => 'S' ];
		$this->assertSame( 'Remplacement (abîmé) : Bas femme S', SP_Cal_Tenues::libelle_demande( $d ) );
	}

	public function test_besoin_de_commande(): void {
		$eleves = [
			self::eleve( 1, 'F', 'S', 'M' ),          // rien : 1 haut femme S + 1 bas femme M
			self::eleve( 2, 'M', 'L', 'L' ),          // a déjà son haut : seulement 1 bas homme L
			self::eleve( 3, 'F', 'S', '' ),           // bas illisible : haut compté, fiche à compléter
			self::eleve( 4, '', 'M', 'M' ),           // sexe inconnu : rien de compté, fiche à compléter
			self::eleve( 5, 'M', 'XL', 'XL' ),        // déjà équipé du haut et du bas
		];
		$detenus = [
			2 => [ [ 'modele' => 'haut_h', 'taille' => 'L', 'nb' => 1 ] ],
			5 => [ [ 'modele' => 'haut_h', 'taille' => 'XL', 'nb' => 1 ], [ 'modele' => 'bas_h', 'taille' => 'XL', 'nb' => 1 ] ],
		];
		$r = SP_Cal_Tenues::besoin( $eleves, $detenus, self::TAILLES );

		$this->assertSame( [ 'haut_f' => [ 'S' => 2 ], 'bas_f' => [ 'M' => 1 ], 'bas_h' => [ 'L' => 1 ] ], $r['besoin'] );
		$this->assertSame( 1, $r['equipes'] );
		$this->assertCount( 2, $r['incomplets'] );
		$this->assertSame( 3, (int) $r['incomplets'][0]['eleve']->id );
		$this->assertSame( [ 'taille du bas (pantalon)' ], $r['incomplets'][0]['manque'] );
		$this->assertSame( [ 'sexe' ], $r['incomplets'][1]['manque'] );
	}
}
