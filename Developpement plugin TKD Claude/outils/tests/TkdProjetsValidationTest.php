<?php
/**
 * TKD Projets — nettoyage des valeurs reçues (tkd-projets/src/Validation.php).
 */

use PHPUnit\Framework\TestCase;
use TkdProjets\Validation;

final class TkdProjetsValidationTest extends TestCase {

	public function test_dates(): void {
		$this->assertSame( '2027-02-28', Validation::date( ' 2027-02-28 ' ) );
		$this->assertNull( Validation::date( '2027-02-29' ) );
		$this->assertNull( Validation::date( '28/02/2027' ) );
		$this->assertNull( Validation::date( '' ) );
		$this->assertNull( Validation::date( null ) );
		$this->assertNull( Validation::date( array( '2027-01-01' ) ) );
	}

	public function test_couleurs(): void {
		$this->assertSame( '#aabbcc', Validation::color( '#ABC' ) );
		$this->assertSame( '#8e44ad', Validation::color( '#8E44AD' ) );
		$this->assertSame( '#2271b1', Validation::color( 'red' ) );
		$this->assertSame( '#2271b1', Validation::color( '#12345' ) );
		$this->assertSame( '#2271b1', Validation::color( '"><script>' ) );
	}

	public function test_liste_fermee_et_pourcentage(): void {
		$this->assertSame( 'done', Validation::oneOf( 'done', Validation::STATUSES, 'todo' ) );
		$this->assertSame( 'todo', Validation::oneOf( 'DONE', Validation::STATUSES, 'todo' ) );
		$this->assertSame( 'todo', Validation::oneOf( array(), Validation::STATUSES, 'todo' ) );
		$this->assertSame( 100, Validation::percent( 250 ) );
		$this->assertSame( 0, Validation::percent( -5 ) );
	}

	public function test_identifiants(): void {
		$this->assertSame( array( 12, 15 ), Validation::ids( '12,15,12,-3,abc,0' ) );
		$this->assertSame( array( 1, 2 ), Validation::ids( array( 1, '2', 3 ), 2 ) );
	}

	public function test_coins_d_une_zone(): void {
		$this->assertNull( Validation::coords( array( array( 42.7, 2.9 ), array( 42.8, 2.9 ) ) ) ); // 2 coins
		$z = Validation::coords( '[[42.7613001,2.9567],[42.762,2.957],[95,2],[42.761,"x"],[42.76,2.958]]' );
		$this->assertSame( array( array( 42.7613, 2.9567 ), array( 42.762, 2.957 ), array( 42.76, 2.958 ) ), $z );
		$this->assertNull( Validation::coords( 'pas du json' ) );
	}

	public function test_equipe(): void {
		$team = Validation::team(
			array(
				array( 'user_id' => 3, 'role' => 'membre' ),
				array( 'user_id' => '5', 'role' => 'responsable' ),
				array( 'user_id' => 3, 'role' => 'responsable' ),
				array( 'user_id' => 5, 'role' => 'membre' ),       // le rôle de responsable est gardé
				array( 'user_id' => 7, 'role' => 'gestionnaire' ), // rôle refusé
				array( 'user_id' => 0, 'role' => 'membre' ),
				'texte quelconque',
			)
		);
		$this->assertSame( array( 3 => 'responsable', 5 => 'responsable' ), $team );
		$this->assertSame( array(), Validation::team( 'x' ) );
	}
}
