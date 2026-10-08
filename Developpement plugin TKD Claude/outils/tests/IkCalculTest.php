<?php
/**
 * Indemnités kilométriques — SP_Cal_IK_Cloture::calcul_mois() (sp_build/includes/class-ik-cloture.php),
 * source unique des montants (récap mensuel par email et clôture du mois).
 *
 * Règle : montant = tarif × km aller-retour × nombre d'allers-retours, + tarif × km exceptionnels
 * du mois ; seuls les comptes ayant le rôle « entraineur » sont comptés.
 */

use PHPUnit\Framework\TestCase;

final class IkCalculTest extends TestCase {

	/**
	 * @param object[] $entraineurs    objets id, nom, roles, km_aller_retour
	 * @param array    $interventions  trainer_id => [ 'ar' => allers-retours, 'jours' => jours ]
	 * @param object[] $km_excep       lignes trainer_id, total_km, detail
	 */
	private function calcul( float $tarif, array $entraineurs, array $interventions, array $km_excep = [] ): array {
		$GLOBALS['tests_options']['sp_cal_tarif_km'] = $tarif;
		$GLOBALS['wpdb'] = new FauxWpdb();
		$GLOBALS['wpdb']->resultats = [ $km_excep ];
		$db = new class( $entraineurs, $interventions ) {
			public function __construct( private array $e, private array $i ) {}
			public function get_interventions_par_trainer( $y, $m ) { return $this->i; }
			public function table_km_exceptionnels() { return 'wp_sp_cal_km_excep'; }
			public function get_trainers( $actifs ) { return $this->e; }
		};
		$ik = tests_instance( SP_Cal_IK_Cloture::class, [ 'db' => $db ] );
		return $ik->calcul_mois( 2026, 10 );
	}

	private function entraineur( int $id, string $nom, ?float $km, string $roles = 'entraineur' ): object {
		return (object) [ 'id' => $id, 'nom' => $nom, 'roles' => $roles, 'km_aller_retour' => $km ];
	}

	public function test_tarif_x_km_x_allers_retours(): void {
		$r = $this->calcul( 0.35, [ $this->entraineur( 1, 'Alice', 24 ) ], [ 1 => [ 'ar' => 8, 'jours' => 8 ] ] );
		$this->assertEqualsWithDelta( 67.20, $r['lignes'][1]['montant'], 0.001 ); // 0,35 × 24 × 8
		$this->assertEqualsWithDelta( 67.20, $r['total'], 0.001 );
	}

	public function test_km_exceptionnels_ajoutes(): void {
		$r = $this->calcul( 0.35, [ $this->entraineur( 1, 'Alice', 24 ) ], [ 1 => [ 'ar' => 2, 'jours' => 2 ] ],
			[ (object) [ 'trainer_id' => 1, 'total_km' => 100, 'detail' => 'Compétition (12/10): 100 km' ] ] );
		$this->assertEqualsWithDelta( 16.80 + 35.00, $r['lignes'][1]['montant'], 0.001 );
		$this->assertSame( 'Compétition (12/10): 100 km', $r['lignes'][1]['excep_detail'] );
	}

	public function test_km_exceptionnels_seuls_sans_cours(): void {
		$r = $this->calcul( 0.35, [ $this->entraineur( 1, 'Alice', 24 ) ], [],
			[ (object) [ 'trainer_id' => 1, 'total_km' => 50, 'detail' => '' ] ] );
		$this->assertEqualsWithDelta( 17.50, $r['lignes'][1]['montant'], 0.001 );
	}

	public function test_sans_cours_ni_km_exceptionnels_absent(): void {
		$r = $this->calcul( 0.35, [ $this->entraineur( 1, 'Alice', 24 ) ], [] );
		$this->assertSame( [], $r['lignes'] );
		$this->assertEquals( 0, $r['total'] );
	}

	public function test_membre_du_bureau_non_entraineur_ignore(): void {
		$r = $this->calcul( 0.35, [ $this->entraineur( 1, 'Bob', 30, 'bureau' ) ], [ 1 => [ 'ar' => 4, 'jours' => 4 ] ] );
		$this->assertSame( [], $r['lignes'] );
	}

	public function test_km_non_renseigne_signale_et_montant_nul(): void {
		$r = $this->calcul( 0.35, [ $this->entraineur( 1, 'Chloé', 0.0 ) ], [ 1 => [ 'ar' => 3, 'jours' => 3 ] ] );
		$this->assertEquals( 0, $r['lignes'][1]['montant'] );
		$this->assertSame( [ 'Chloé' ], $r['sans_km'] );
	}

	public function test_tarif_nul_aucun_montant(): void {
		$r = $this->calcul( 0, [ $this->entraineur( 1, 'Alice', 24 ) ], [ 1 => [ 'ar' => 8, 'jours' => 8 ] ] );
		$this->assertEquals( 0, $r['total'] );
	}

	public function test_total_de_plusieurs_entraineurs_arrondi_au_centime(): void {
		$r = $this->calcul( 0.333, [ $this->entraineur( 1, 'Alice', 10 ), $this->entraineur( 2, 'David', 7, 'entraineur,bureau' ) ],
			[ 1 => [ 'ar' => 1, 'jours' => 1 ], 2 => [ 'ar' => 1, 'jours' => 1 ] ] );
		$this->assertEqualsWithDelta( 3.33, $r['lignes'][1]['montant'], 0.0001 ); // 3,33 (arrondi par ligne)
		$this->assertEqualsWithDelta( 2.33, $r['lignes'][2]['montant'], 0.0001 ); // 2,331 → 2,33
		$this->assertEqualsWithDelta( 5.66, $r['total'], 0.0001 );
	}
}
