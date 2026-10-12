<?php
/**
 * Créneaux mensuels — règle du 12/10/2026 : même jour de la semaine et même rang dans le mois
 * que la première séance (SpCalPro_DB::dates_mensuelles(), sp_build/includes/trait-db-calendrier.php).
 *
 * Avant, on répétait le numéro du jour : la « prépa Dan » du samedi 03/10/2026 tombait le mardi
 * 03/11, le jeudi 03/12, le dimanche 03/01… et n'apparaissait donc jamais un samedi.
 */

use PHPUnit\Framework\TestCase;

final class FauxDbCalendrier {
	use SpCalPro_DB_Calendrier;
}

final class CreneauxMensuelsTest extends TestCase {

	public function test_premier_samedi_de_chaque_mois(): void {
		$dates = FauxDbCalendrier::dates_mensuelles( '2026-10-03', 6, 1, '2026-10-01', '2027-02-28', '2027-07-03' );
		$this->assertSame( [ '2026-10-03', '2026-11-07', '2026-12-05', '2027-01-02', '2027-02-06' ], $dates );
		foreach ( $dates as $d ) $this->assertSame( '6', gmdate( 'N', strtotime( $d ) ), "$d doit être un samedi" );
	}

	public function test_fenetre_d_un_seul_mois(): void {
		$this->assertSame( [ '2026-11-07' ], FauxDbCalendrier::dates_mensuelles( '2026-10-03', 6, 1, '2026-11-01', '2026-11-30' ) );
		$this->assertSame( [], FauxDbCalendrier::dates_mensuelles( '2026-10-03', 6, 1, '2026-09-01', '2026-09-30' ), 'rien avant le début du créneau' );
	}

	public function test_tous_les_deux_mois(): void {
		$this->assertSame( [ '2026-10-03', '2026-12-05', '2027-02-06' ], FauxDbCalendrier::dates_mensuelles( '2026-10-03', 6, 2, '2026-10-01', '2027-02-28' ) );
	}

	public function test_s_arrete_a_la_date_de_fin_du_creneau(): void {
		$this->assertSame( [ '2026-10-03', '2026-11-07' ], FauxDbCalendrier::dates_mensuelles( '2026-10-03', 6, 1, '2026-10-01', '2027-06-30', '2026-11-30' ) );
	}

	public function test_date_de_debut_un_autre_jour_que_celui_du_creneau(): void {
		// Début saisi le jeudi 01/10/2026 pour un créneau du samedi : première séance le samedi 03/10 (1er samedi).
		$this->assertSame( [ '2026-10-03', '2026-11-07' ], FauxDbCalendrier::dates_mensuelles( '2026-10-01', 6, 1, '2026-10-01', '2026-11-30' ) );
	}

	public function test_cinquieme_samedi_absent_prend_le_dernier(): void {
		// Samedi 31/10/2026 = 5e samedi ; novembre 2026 n'en a que 4 → le dernier (28/11) ; janvier 2027 en a 5 (30/01).
		$this->assertSame(
			[ '2026-10-31', '2026-11-28', '2026-12-26', '2027-01-30' ],
			FauxDbCalendrier::dates_mensuelles( '2026-10-31', 6, 1, '2026-10-01', '2027-01-31' )
		);
	}
}
