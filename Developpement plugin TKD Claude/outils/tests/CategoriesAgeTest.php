<?php
/**
 * Catégories d'âge du Taekwondo — règle du club (08/10/2026) : la classe scolaire à la rentrée,
 * d'après la seule année de naissance, comme à l'école.
 *   Baby = maternelle · Enfant = primaire (CP → CM2) · Ado/adulte = collège et plus.
 *
 * Trois endroits l'appliquent et doivent donner le même résultat :
 *   - FauxDbMembres::categorie_scolaire() (sp_build/includes/trait-db-membres.php), utilisée par la
 *     bascule de rentrée bascule_categories_septembre() ;
 *   - tkd_calculer_categorie_age() (tkd-cotisations/cotisations.php), bouton « Recalculer » ;
 *   - le formulaire d'adhésion (JavaScript de class-front-adhesion.php) : non testé ici, même formule.
 */

use PHPUnit\Framework\TestCase;

final class FauxDbMembres {
	use SpCalPro_DB_Membres;
	public function table_eleves() { return 'wp_sp_cal_eleves'; }
}

final class CategoriesAgeTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['tests_maintenant'] );
	}

	/** Catégorie attendue d'après le cycle scolaire français (entrée en CP l'année des 6 ans, en 6e l'année des 11 ans). */
	private static function attendue( int $annee_naissance, int $rentree ): string {
		$classe_cp = $annee_naissance + 6;   // rentrée où l'enfant entre au CP
		$classe_6e = $annee_naissance + 11;  // rentrée où il entre en 6e
		if ( $rentree < $classe_cp ) return 'Baby';
		if ( $rentree < $classe_6e ) return 'Enfant';
		return 'Ado/adulte';
	}

	public function test_saison_2026_2027(): void {
		$this->assertSame( 'Baby',       SpCalPro_DB_Test::cat( 2021, 2026 ) ); // GS
		$this->assertSame( 'Baby',       SpCalPro_DB_Test::cat( 2023, 2026 ) ); // PS
		$this->assertSame( 'Enfant',     SpCalPro_DB_Test::cat( 2020, 2026 ) ); // CP
		$this->assertSame( 'Enfant',     SpCalPro_DB_Test::cat( 2016, 2026 ) ); // CM2
		$this->assertSame( 'Ado/adulte', SpCalPro_DB_Test::cat( 2015, 2026 ) ); // 6e
		$this->assertSame( 'Ado/adulte', SpCalPro_DB_Test::cat( 1980, 2026 ) ); // adulte
	}

	public function test_seule_l_annee_de_naissance_compte(): void {
		// Né le 1er janvier ou le 31 décembre 2020 : CP tous les deux à la rentrée 2026.
		$this->assertSame( 'Enfant', tkd_calculer_categorie_age( '01/01', '2020', '2026/2027' ) );
		$this->assertSame( 'Enfant', tkd_calculer_categorie_age( '31/12', '2020', '2026/2027' ) );
		$this->assertSame( 'Enfant', tkd_calculer_categorie_age( '01/09', '2020', '2026/2027' ) ); // ancien défaut : « Baby »
		$this->assertSame( 'Enfant', tkd_calculer_categorie_age( '', '2020', '2026/2027' ) );      // jour/mois inconnus : sans effet
		$this->assertNull( tkd_calculer_categorie_age( '15/03', '', '2026/2027' ) );
	}

	public function test_plus_de_categorie_adulte_attribuee(): void {
		$this->assertSame( 'Ado/adulte', tkd_calculer_categorie_age( '15/03', '1975', '2026/2027' ) );
	}

	public function test_sp_build_et_cotisations_suivent_le_cycle_scolaire_sur_30_rentrees(): void {
		$ecarts = [];
		for ( $rentree = 2020; $rentree <= 2050; $rentree++ ) {
			for ( $annee = $rentree - 25; $annee <= $rentree - 2; $annee++ ) {
				$attendue = self::attendue( $annee, $rentree );
				$sp       = FauxDbMembres::categorie_scolaire( $annee, $rentree );
				$tkd      = tkd_calculer_categorie_age( '15/06', (string) $annee, $rentree . '/' . ( $rentree + 1 ) );
				if ( $sp !== $attendue || $tkd !== $attendue ) $ecarts[] = "rentrée $rentree, né en $annee : sp_build $sp, cotisations $tkd, attendu $attendue";
			}
		}
		$this->assertSame( [], $ecarts );
	}

	public function test_annee_de_la_rentree_selon_la_date(): void {
		$cas = [ '2026-10-08' => 2026, '2027-01-15' => 2026, '2027-05-31' => 2026, '2027-06-01' => 2027, '2027-09-01' => 2027, '2027-12-31' => 2027 ];
		foreach ( $cas as $date => $rentree ) {
			$GLOBALS['tests_maintenant'] = $date;
			$this->assertSame( $rentree, FauxDbMembres::annee_saison_categories(), $date );
		}
	}

	public function test_bascule_apercu_sans_ecriture_et_conforme(): void {
		$GLOBALS['tests_maintenant'] = '2026-09-10';
		$eleves = [];
		foreach ( range( 2001, 2024 ) as $i => $annee ) {
			$eleves[] = (object) [ 'id' => $i + 1, 'nom' => 'N' . $i, 'prenom' => 'P', 'date_naissance' => '01/09',
				'annee_naissance' => (string) $annee, 'categorie_age' => 'Adulte' ];
		}
		$eleves[] = (object) [ 'id' => 99, 'nom' => 'Sans', 'prenom' => 'Annee', 'date_naissance' => '', 'annee_naissance' => '0', 'categorie_age' => 'Baby' ];
		$GLOBALS['wpdb'] = new FauxWpdb();
		$GLOBALS['wpdb']->resultats = [ $eleves ];
		$res = ( new FauxDbMembres() )->bascule_categories_septembre( true );

		$this->assertSame( [], $GLOBALS['wpdb']->ecritures, 'Le mode « aperçu » ne doit rien écrire.' );
		$this->assertStringNotContainsString( 'date_naissance !=', $GLOBALS['wpdb']->requetes[0], 'Le jour de naissance n\'est plus exigé.' );
		$this->assertCount( 24, $res['details'] ); // tous passent d'« Adulte » à leur classe ; l'année 0 est ignorée
		foreach ( $res['details'] as $l ) {
			$this->assertSame( self::attendue( $l['annee'], 2026 ), $l['apres'], 'né en ' . $l['annee'] );
		}
	}
}

/** Raccourci de lecture pour les exemples. */
final class SpCalPro_DB_Test {
	public static function cat( int $annee, int $rentree ): string {
		$sp  = FauxDbMembres::categorie_scolaire( $annee, $rentree );
		$tkd = tkd_calculer_categorie_age( '15/06', (string) $annee, $rentree . '/' . ( $rentree + 1 ) );
		return $sp === $tkd ? $sp : "désaccord sp_build $sp / cotisations $tkd";
	}
}
