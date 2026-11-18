<?php
/**
 * TKD Projets — ligne de budget proposée pour les repas du gala (tkd-projets/src/Gala.php).
 */

use PHPUnit\Framework\TestCase;
use TkdProjets\Gala;

final class TkdProjetsGalaTest extends TestCase {

	public function test_recette_repas_categorie_70(): void {
		$s = Gala::repasSuggestion( 42, 5.0 );
		$this->assertSame( 'recette', $s['sens'] );
		$this->assertSame( '70', $s['categorie'] );
		$this->assertSame( 'Repas du gala (42 × 5 €)', $s['libelle'] );
		$this->assertSame( 210.0, $s['montant'] );
	}

	public function test_prix_avec_centimes(): void {
		$s = Gala::repasSuggestion( 3, 7.5 );
		$this->assertSame( 'Repas du gala (3 × 7,5 €)', $s['libelle'] );
		$this->assertSame( 22.5, $s['montant'] );
	}

	public function test_aucun_repas_aucune_suggestion(): void {
		$this->assertNull( Gala::repasSuggestion( 0, 5.0 ) );
		$this->assertNull( Gala::repasSuggestion( 10, 0.0 ) );
	}

	public function test_sans_tkd_gala_rien_n_est_lu(): void {
		// Ici, TKD Gala n'est pas chargée : l'onglet Gala ne doit pas apparaître.
		$this->assertFalse( Gala::available() );
	}
}
