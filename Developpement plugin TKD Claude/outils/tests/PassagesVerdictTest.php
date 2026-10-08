<?php
/**
 * Verdict des passages de grade — SP_Cal_Passages::resultat_candidat() (sp_build/includes/class-passages.php),
 * « seule source de vérité » du résultat proposé au jury.
 *
 * Règles vérifiées (cf. regle_admission()) :
 *   keup : avis majoritaire des juges par critère ; égalité → arbitrage du président, sinon « incomplet » ;
 *          admis s'il n'y a aucun « Non acquis » et au plus un « À revoir ».
 *   Poom : moyenne des juges par critère ; admis si moyenne générale ≥ seuil et aucune épreuve sous le plancher.
 */

use PHPUnit\Framework\TestCase;

final class PassagesVerdictTest extends TestCase {

	private const NON = 0, REVOIR = 1, ACQUIS = 2;

	private function crit( string $cle, string $type = 'niveaux', ?float $acquis = null, ?float $revoir = null ): array {
		return [ 'cle' => $cle, 'libelle' => ucfirst( $cle ), 'type' => $type, 'seuil_acquis' => $acquis, 'seuil_revoir' => $revoir ];
	}

	/** Évaluation d'un juge : niveau (keup « niveaux ») ou valeur (note, mesure, Poom). */
	private function ev( int $juge, string $crit, ?int $niveau = null, ?float $valeur = null ): object {
		return (object) [ 'juge_id' => $juge, 'critere' => $crit, 'niveau' => $niveau, 'valeur' => $valeur, 'remarque' => '' ];
	}

	private function verdict( array $criteres, array $evals, string $mode = 'keup', array $arbitrages = [], float $seuil = 5, float $plancher = 3 ): array {
		$cand    = (object) [ 'mode' => $mode, 'criteres' => $criteres, 'arbitrages' => $arbitrages ];
		$passage = (object) [ 'poom_seuil' => $seuil, 'poom_plancher' => $plancher ];
		$p       = tests_instance( SP_Cal_Passages::class );
		return tests_appeler( $p, 'resultat_candidat', $cand, $evals, $passage, [ 1 => 'Juge A', 2 => 'Juge B', 3 => 'Juge C' ] );
	}

	// ── keup ──

	public function test_keup_tout_acquis_admis(): void {
		$r = $this->verdict( [ $this->crit( 'poomsae' ), $this->crit( 'kibon' ) ], [
			$this->ev( 1, 'poomsae', self::ACQUIS ), $this->ev( 2, 'poomsae', self::ACQUIS ),
			$this->ev( 1, 'kibon', self::ACQUIS ),   $this->ev( 2, 'kibon', self::ACQUIS ),
		] );
		$this->assertSame( 'admis', $r['proposition'] );
	}

	public function test_keup_un_seul_a_revoir_reste_admis(): void {
		$r = $this->verdict( [ $this->crit( 'poomsae' ), $this->crit( 'kibon' ) ], [
			$this->ev( 1, 'poomsae', self::REVOIR ), $this->ev( 2, 'poomsae', self::REVOIR ),
			$this->ev( 1, 'kibon', self::ACQUIS ),
		] );
		$this->assertSame( 'admis', $r['proposition'] );
		$this->assertSame( '0 non acquis, 1 à revoir.', $r['raison'] );
	}

	public function test_keup_deux_a_revoir_ajourne(): void {
		$r = $this->verdict( [ $this->crit( 'poomsae' ), $this->crit( 'kibon' ) ], [
			$this->ev( 1, 'poomsae', self::REVOIR ), $this->ev( 1, 'kibon', self::REVOIR ),
		] );
		$this->assertSame( 'ajourne', $r['proposition'] );
	}

	public function test_keup_un_non_acquis_ajourne(): void {
		$r = $this->verdict( [ $this->crit( 'poomsae' ), $this->crit( 'kibon' ) ], [
			$this->ev( 1, 'poomsae', self::NON ), $this->ev( 1, 'kibon', self::ACQUIS ),
		] );
		$this->assertSame( 'ajourne', $r['proposition'] );
	}

	public function test_keup_avis_majoritaire_des_juges(): void {
		// 2 juges sur 3 disent « acquis » : le critère est acquis, désaccord signalé.
		$r = $this->verdict( [ $this->crit( 'poomsae' ) ], [
			$this->ev( 1, 'poomsae', self::ACQUIS ), $this->ev( 2, 'poomsae', self::NON ), $this->ev( 3, 'poomsae', self::ACQUIS ),
		] );
		$this->assertSame( 'admis', $r['proposition'] );
		$this->assertSame( self::ACQUIS, $r['criteres'][0]['niveau'] );
		$this->assertTrue( $r['criteres'][0]['desaccord'] );
	}

	public function test_keup_egalite_sans_arbitrage_incomplet(): void {
		$r = $this->verdict( [ $this->crit( 'poomsae' ) ], [
			$this->ev( 1, 'poomsae', self::ACQUIS ), $this->ev( 2, 'poomsae', self::NON ),
		] );
		$this->assertSame( 'incomplet', $r['proposition'] );
		$this->assertSame( 'egalite', $r['criteres'][0]['statut'] );
	}

	public function test_keup_egalite_tranchee_par_le_president(): void {
		$r = $this->verdict( [ $this->crit( 'poomsae' ) ], [
			$this->ev( 1, 'poomsae', self::ACQUIS ), $this->ev( 2, 'poomsae', self::NON ),
		], 'keup', [ 'poomsae' => self::ACQUIS ] );
		$this->assertSame( 'admis', $r['proposition'] );
		$this->assertTrue( $r['criteres'][0]['arbitre'] );
	}

	public function test_keup_critere_sans_evaluation_incomplet(): void {
		$r = $this->verdict( [ $this->crit( 'poomsae' ), $this->crit( 'kibon' ) ], [ $this->ev( 1, 'poomsae', self::ACQUIS ) ] );
		$this->assertSame( 'incomplet', $r['proposition'] );
		$this->assertSame( 'manquant', $r['criteres'][1]['statut'] );
	}

	public function test_keup_sans_aucun_critere_incomplet(): void {
		$r = $this->verdict( [], [] );
		$this->assertSame( 'incomplet', $r['proposition'] );
		$this->assertStringContainsString( 'Aucun critère', $r['raison'] );
	}

	public function test_keup_note_sur_10_convertie_par_les_seuils_par_defaut(): void {
		// Type « note » sans seuils : acquis ≥ 5, à revoir ≥ 4, sinon non acquis.
		$c = [ $this->crit( 'n' ) ];
		$c[0]['type'] = 'note';
		$this->assertSame( self::ACQUIS, $this->verdict( $c, [ $this->ev( 1, 'n', null, 5 ) ] )['criteres'][0]['niveau'] );
		$this->assertSame( self::REVOIR, $this->verdict( $c, [ $this->ev( 1, 'n', null, 4.5 ) ] )['criteres'][0]['niveau'] );
		$this->assertSame( self::NON,    $this->verdict( $c, [ $this->ev( 1, 'n', null, 3.9 ) ] )['criteres'][0]['niveau'] );
	}

	public function test_keup_mesure_avec_seuils(): void {
		// Ex. pompes : acquis à 20, à revoir à 15.
		$c = [ $this->crit( 'pompes', 'mesure', 20, 15 ) ];
		$this->assertSame( self::ACQUIS, $this->verdict( $c, [ $this->ev( 1, 'pompes', null, 20 ) ] )['criteres'][0]['niveau'] );
		$this->assertSame( self::REVOIR, $this->verdict( $c, [ $this->ev( 1, 'pompes', null, 15 ) ] )['criteres'][0]['niveau'] );
		$this->assertSame( self::NON,    $this->verdict( $c, [ $this->ev( 1, 'pompes', null, 14 ) ] )['criteres'][0]['niveau'] );
	}

	public function test_remarques_des_juges_ne_comptent_pas_comme_critere(): void {
		$rem = $this->ev( 2, '_remarque' );
		$rem->remarque = 'Bonne énergie';
		$r = $this->verdict( [ $this->crit( 'poomsae' ) ], [ $this->ev( 1, 'poomsae', self::ACQUIS ), $rem ] );
		$this->assertSame( 'admis', $r['proposition'] );
		$this->assertSame( [ [ 'juge' => 'Juge B', 'texte' => 'Bonne énergie' ] ], $r['remarques'] );
	}

	// ── Poom ──

	public function test_poom_moyenne_au_seuil_admis(): void {
		$r = $this->verdict( [ $this->crit( 'a', 'note' ), $this->crit( 'b', 'note' ) ], [
			$this->ev( 1, 'a', null, 6 ), $this->ev( 2, 'a', null, 4 ),   // moyenne 5
			$this->ev( 1, 'b', null, 5 ), $this->ev( 2, 'b', null, 5 ),   // moyenne 5
		], 'poom', [], 5, 3 );
		$this->assertSame( 'admis', $r['proposition'] );
		$this->assertEquals( 5, $r['moyenne'] );
	}

	public function test_poom_moyenne_sous_le_seuil_ajourne(): void {
		$r = $this->verdict( [ $this->crit( 'a', 'note' ) ], [ $this->ev( 1, 'a', null, 4.9 ) ], 'poom', [], 5, 3 );
		$this->assertSame( 'ajourne', $r['proposition'] );
		$this->assertStringContainsString( '< 5', $r['raison'] );
	}

	public function test_poom_une_epreuve_sous_le_plancher_ajourne_malgre_la_moyenne(): void {
		$r = $this->verdict( [ $this->crit( 'a', 'note' ), $this->crit( 'b', 'note' ) ], [
			$this->ev( 1, 'a', null, 9 ), $this->ev( 1, 'b', null, 2.5 ),   // moyenne 5,75 mais b < 3
		], 'poom', [], 5, 3 );
		$this->assertSame( 'ajourne', $r['proposition'] );
		$this->assertStringContainsString( 'plancher', $r['raison'] );
	}

	public function test_poom_note_bornee_entre_0_et_10(): void {
		$r = $this->verdict( [ $this->crit( 'a', 'note' ) ], [ $this->ev( 1, 'a', null, 14 ) ], 'poom' );
		$this->assertEquals( 10, $r['criteres'][0]['moyenne'] );
	}

	public function test_poom_mesure_ramenee_sur_10(): void {
		// 15 pompes pour un seuil « acquis » de 20 → 7,5/10.
		$r = $this->verdict( [ $this->crit( 'pompes', 'mesure', 20 ) ], [ $this->ev( 1, 'pompes', null, 15 ) ], 'poom' );
		$this->assertEquals( 7.5, $r['criteres'][0]['moyenne'] );
	}

	public function test_poom_desaccord_signale_a_partir_de_2_points(): void {
		$r = $this->verdict( [ $this->crit( 'a', 'note' ) ], [ $this->ev( 1, 'a', null, 7 ), $this->ev( 2, 'a', null, 5 ) ], 'poom' );
		$this->assertTrue( $r['criteres'][0]['desaccord'] );
		$r = $this->verdict( [ $this->crit( 'a', 'note' ) ], [ $this->ev( 1, 'a', null, 7 ), $this->ev( 2, 'a', null, 5.5 ) ], 'poom' );
		$this->assertFalse( $r['criteres'][0]['desaccord'] );
	}
}
