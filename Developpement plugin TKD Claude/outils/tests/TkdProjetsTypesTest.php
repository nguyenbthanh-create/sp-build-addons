<?php
/**
 * TKD Projets — types de projets et tâches modèles (tkd-projets/src/Types.php).
 */

use PHPUnit\Framework\TestCase;
use TkdProjets\Types;

final class TkdProjetsTypesTest extends TestCase {

	public function test_trois_types_proposes_gala_fetes_competition(): void {
		$proposes = array_keys( array_filter( Types::all(), static fn( $t ) => $t['proposed'] ) );
		$this->assertSame( array( 'gala', 'fetes', 'competition' ), $proposes );
	}

	public function test_autre_n_est_accepte_que_pour_un_projet_qui_l_a_deja(): void {
		$this->assertFalse( Types::isAllowed( 'autre' ) );
		$this->assertTrue( Types::isAllowed( 'autre', 'autre' ) );
		$this->assertTrue( Types::isAllowed( 'gala', 'autre' ) );
		$this->assertFalse( Types::isAllowed( 'voyage' ) );
	}

	public function test_nature_sp_compta(): void {
		$this->assertSame( 'evenement', Types::nature( 'gala' ) );
		$this->assertSame( 'evenement', Types::nature( 'fetes' ) );
		$this->assertSame( 'competition', Types::nature( 'competition' ) );
		$this->assertSame( 'autre', Types::nature( 'inconnu' ) );
	}

	public function test_taches_datees_a_partir_de_l_evenement(): void {
		$taches = Types::templateTasks( 'fetes', '2026-12-19' );
		$this->assertSame( 'Fixer la date et le lieu', $taches[0]['title'] );
		$this->assertSame( '2026-10-20', $taches[0]['start_date'] ); // J-60
		$this->assertSame( '2026-10-30', $taches[0]['end_date'] );   // J-50
		$derniere = end( $taches );
		$this->assertSame( '2026-12-19', $derniere['start_date'] );  // jour J
		$this->assertSame( '2026-12-26', $derniere['end_date'] );    // J+7
	}

	public function test_debut_jamais_apres_la_fin(): void {
		foreach ( array( 'gala', 'fetes', 'competition' ) as $type ) {
			foreach ( Types::templateTasks( $type, '2027-03-13' ) as $t ) {
				$this->assertLessThanOrEqual( $t['end_date'], $t['start_date'], $type . ' : ' . $t['title'] );
			}
		}
	}

	public function test_sans_date_d_evenement_les_taches_n_ont_pas_de_dates(): void {
		$taches = Types::templateTasks( 'competition', null );
		$this->assertCount( Types::all()['competition']['count'], $taches );
		$this->assertNull( $taches[0]['start_date'] );
		$this->assertNull( Types::templateTasks( 'competition', '2027-02-30' )[0]['end_date'] ); // date invalide
	}

	public function test_changement_d_heure_sans_decalage(): void {
		// 25/10/2026 : passage à l'heure d'hiver ; le calcul se fait en UTC, jour par jour.
		$t = Types::templateTasks( 'fetes', '2026-11-01' );
		$this->assertSame( '2026-09-02', $t[0]['start_date'] );
	}

	public function test_type_inconnu_ou_autre_aucune_tache(): void {
		$this->assertSame( array(), Types::templateTasks( 'voyage', '2027-01-01' ) );
		$this->assertSame( array(), Types::templateTasks( 'autre', '2027-01-01' ) );
	}
}
