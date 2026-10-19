<?php
/**
 * Jours fériés — raccourci « Jours fériés de la saison » de l'annulation par lot (19/10/2026) :
 * SpCalPro_DB::date_paques() et SpCalPro_DB::jours_feries() (sp_build/includes/trait-db-calendrier.php).
 * Dates de Pâques de référence : calendrier grégorien officiel.
 */

use PHPUnit\Framework\TestCase;

final class FauxDbFeries {
	use SpCalPro_DB_Calendrier;
}

final class JoursFeriesTest extends TestCase {

	public function test_dates_de_paques_connues(): void {
		$attendu = [ 2024 => '2024-03-31', 2025 => '2025-04-20', 2026 => '2026-04-05', 2027 => '2027-03-28', 2028 => '2028-04-16', 2038 => '2038-04-25', 2285 => '2285-03-22' ];
		foreach ( $attendu as $annee => $date ) $this->assertSame( $date, FauxDbFeries::date_paques( $annee ), "Pâques $annee" );
	}

	public function test_saison_2026_2027(): void {
		$this->assertSame( [
			'2026-11-01' => 'Toussaint',
			'2026-11-11' => 'Armistice 1918',
			'2026-12-25' => 'Noël',
			'2027-01-01' => 'Jour de l\'an',
			'2027-03-29' => 'Lundi de Pâques',
			'2027-05-01' => 'Fête du Travail',
			'2027-05-06' => 'Ascension',
			'2027-05-08' => 'Victoire 1945',
			'2027-05-17' => 'Lundi de Pentecôte',
			'2027-07-14' => 'Fête nationale',
			'2027-08-15' => 'Assomption',
		], FauxDbFeries::jours_feries( '2026-10-19', '2027-08-31' ) );
	}

	public function test_bornes_incluses_et_periode_vide(): void {
		$this->assertSame( [ '2026-11-11' => 'Armistice 1918' ], FauxDbFeries::jours_feries( '2026-11-11', '2026-11-11' ) );
		$this->assertSame( [], FauxDbFeries::jours_feries( '2026-11-12', '2026-12-24' ) );
	}
}
