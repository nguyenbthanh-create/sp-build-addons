<?php
/**
 * TKD Projets — droits dans un projet (tkd-projets/src/Policy.php).
 */

use PHPUnit\Framework\TestCase;
use TkdProjets\Policy;

final class TkdProjetsPolicyTest extends TestCase {

	public function test_le_gestionnaire_l_emporte_sur_le_role_dans_l_equipe(): void {
		$this->assertSame( 'gestionnaire', Policy::projectRole( true, 'membre' ) );
		$this->assertSame( 'gestionnaire', Policy::projectRole( true, null ) );
	}

	public function test_hors_equipe_aucun_acces(): void {
		$this->assertNull( Policy::projectRole( false, null ) );
		$this->assertNull( Policy::projectRole( false, 'inconnu' ) );
		$this->assertFalse( Policy::can( 'view', null ) );
	}

	public function test_seul_le_gestionnaire_supprime_un_projet_et_ecrit_a_tout_un_role(): void {
		foreach ( array( 'delete_project', 'mail_role' ) as $action ) {
			$this->assertTrue( Policy::can( $action, 'gestionnaire' ) );
			$this->assertFalse( Policy::can( $action, 'responsable' ) );
			$this->assertFalse( Policy::can( $action, 'membre' ) );
		}
	}

	public function test_le_responsable_gere_son_projet(): void {
		foreach ( array( 'view', 'edit_project', 'manage_members', 'create_task', 'edit_task', 'delete_task', 'reorder', 'zones' ) as $action ) {
			$this->assertTrue( Policy::can( $action, 'responsable' ), $action );
		}
	}

	public function test_le_membre_voit_mais_ne_gere_pas(): void {
		$this->assertTrue( Policy::can( 'view', 'membre' ) );
		foreach ( array( 'edit_project', 'manage_members', 'create_task', 'edit_task', 'delete_task', 'reorder', 'zones' ) as $action ) {
			$this->assertFalse( Policy::can( $action, 'membre' ), $action );
		}
	}

	public function test_action_inconnue_refusee(): void {
		$this->assertFalse( Policy::can( 'tout_casser', 'gestionnaire' ) );
	}

	public function test_acces_a_une_tache(): void {
		$this->assertSame( 'full', Policy::taskAccess( 'responsable', false ) );
		$this->assertSame( 'own', Policy::taskAccess( 'membre', true ) );
		$this->assertSame( 'none', Policy::taskAccess( 'membre', false ) );
		$this->assertSame( 'none', Policy::taskAccess( null, true ) );
	}

	public function test_un_membre_ne_modifie_que_les_champs_de_sa_tache(): void {
		$params = array( 'title' => 'x', 'status' => 'done', 'assignee' => 9, 'progress' => 50, 'end_date' => '2027-01-01', 'attachments' => array( 3 ) );
		$this->assertSame( array( 'status' => 'done', 'progress' => 50, 'attachments' => array( 3 ) ), Policy::filterTaskParams( $params, 'own' ) );
		$this->assertSame( array(), Policy::filterTaskParams( $params, 'none' ) );
		$this->assertSame( $params, Policy::filterTaskParams( $params, 'full' ) );
	}
}
