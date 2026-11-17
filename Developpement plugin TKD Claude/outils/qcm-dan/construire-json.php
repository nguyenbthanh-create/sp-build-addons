<?php
/**
 * Transforme la sortie de extraire-quiz.php en fichier de données du module
 * « QCM Dan » de Claira TKD Parcours (claira-tkd-parcours/data/qcm-dan.json).
 *
 * Usage : php construire-json.php quiz-brut.json ../../claira-tkd-parcours/data/qcm-dan.json
 *
 * Format produit (compact) :
 *   { "maj": "AAAA-MM-JJ", "themes": [ {id, nom, titre, source, pdf} ],
 *     "questions": [ [theme_index, numero, question, [A,B,C,D], bonne(0-3), source] ] }
 */

// Libellés courts des thèmes (affichés dans les filtres) et nom réel du PDF sur fftda.fr.
const THEMES = array(
	'quiz_affilier_club_fftda'            => array( 'Affilier son club', 'quiz affilier club fftda.pdf' ),
	'quiz_arbitres_juges_fftda'           => array( 'Arbitres et juges', 'quiz arbitres juges fftda.pdf' ),
	'quiz_carte_professionnelle_fftda'    => array( 'Carte professionnelle', 'quiz carte professionnelle fftda.pdf' ),
	'quiz_cer_fftda'                      => array( "Contrat d'engagement républicain", 'quiz cer fftda.pdf' ),
	'quiz_certificat_medical_fftda'       => array( 'Certificat médical', 'quiz certificat medical fftda.pdf' ),
	'quiz_charte_olympique_fftda'         => array( 'Charte olympique', 'quiz_charte_olympique_fftda.pdf' ),
	'quiz_code_moral_fftda'               => array( 'Code moral et atouts', 'quiz_code_moral_fftda.pdf' ),
	'quiz_creer_club_fftda'               => array( 'Créer un club', 'quiz_creer_club_fftda.pdf' ),
	'quiz_csdge_fftda'                    => array( 'Dan et grades (CSDGE)', 'quiz_csdge_fftda.pdf' ),
	'quiz_dopage_fftda'                   => array( 'Lutte contre le dopage', 'quiz_dopage_fftda.pdf' ),
	'quiz_ethique_deontologie_fftda'      => array( 'Éthique et déontologie', 'quiz_ethique_deontologie_fftda.pdf' ),
	'quiz_filiere_formation_fftda'        => array( 'Filière de formation', 'quiz_filiere_formation_fftda.pdf' ),
	'quiz_habilitation_coach_fftda'       => array( 'Habilitation coach', 'quiz_habilitation_coach_fftda.pdf' ),
	'quiz_haut_niveau_fftda'              => array( 'Haut niveau', 'quiz_haut_niveau_fftda.pdf' ),
	'quiz_historique_federal_fftda'       => array( 'Historique fédéral', 'quiz_historique_federal_fftda.pdf' ),
	'quiz_la_federation_fftda'            => array( 'La Fédération', 'quiz_la_federation_fftda.pdf' ),
	'quiz_laicite_2025_copie'             => array( 'Laïcité et fait religieux', 'quiz_laicite_2025 copie.pdf' ),
	'quiz_le_taekwondo_fftda'             => array( 'Le Taekwondo', 'quiz_le_taekwondo_fftda.pdf' ),
	'quiz_licence_fftda'                  => array( 'La licence', 'quiz_licence_fftda.pdf' ),
	'quiz_licences_passeports_fftda'      => array( 'Licences et passeports (club)', 'quiz_licences_passeports_fftda.pdf' ),
	'quiz_nos_medailles_fftda'            => array( 'Nos médaillés', 'quiz_nos_medailles_fftda.pdf' ),
	'quiz_passeport_sportif_fftda'        => array( 'Passeport sportif', 'quiz_passeport_sportif_fftda.pdf' ),
	'quiz_processus_electoral_fftda'      => array( 'Processus électoral', 'quiz_processus_electoral_fftda.pdf' ),
	'quiz_protege_dents_fftda'            => array( 'Protège-dents', 'quiz_protege_dents_fftda.pdf' ),
	'quiz_proteger_pratiquants_fftda'     => array( 'Protéger les pratiquants', 'quiz_proteger_pratiquants_fftda.pdf' ),
	'quiz_regl_poomsae_fftda'             => array( 'Règlement compétitions poomsae', 'quiz_regl_poomsae_fftda.pdf' ),
	'quiz_reglement_competitions_fftda'   => array( 'Règlement des compétitions', 'quiz_reglement_competitions_fftda.pdf' ),
	'quiz_reglement_disciplinaire_fftda'  => array( 'Règlement disciplinaire', 'quiz_reglement_disciplinaire_fftda.pdf' ),
	'quiz_reglement_ligues_cdt'           => array( 'Règlement ligues et CDT', 'quiz_reglement_ligues_cdt.pdf' ),
	'quiz_regles_minimes_benjamins_fftda' => array( 'Combat minimes et benjamins', 'quiz_regles_minimes_benjamins_fftda.pdf' ),
	'quiz_statuts_fftda'                  => array( 'Statuts FFTDA', 'quiz_statuts_fftda.pdf' ),
	'quiz_statuts_ligues_cdt_fftda'       => array( 'Statuts ligues et CDT', 'quiz_statuts_ligues_cdt_fftda.pdf' ),
);
const BASE_URL = 'https://www.fftda.fr/files/file/DS_DANS/Quiz%20module%20B/';

$brut = json_decode( file_get_contents( $argv[1] ), true );
$out  = array(
	'maj'       => gmdate( 'Y-m-d' ),
	'themes'    => array(),
	'questions' => array(),
);
foreach ( $brut as $t ) {
	if ( ! isset( THEMES[ $t['id'] ] ) ) {
		fwrite( STDERR, "Thème inconnu : {$t['id']} (ajoutez-le à THEMES)\n" );
		exit( 1 );
	}
	list( $nom, $pdf ) = THEMES[ $t['id'] ];
	$ti                = count( $out['themes'] );
	$out['themes'][]   = array(
		'id'     => $t['id'],
		'nom'    => $nom,
		'titre'  => preg_replace( '/^QUESTIONNAIRE\s*—\s*/u', '', $t['titre'] ),
		'source' => preg_replace( '/^Sources?\s*:\s*/u', '', $t['source'] ),
		'pdf'    => BASE_URL . rawurlencode( $pdf ),
	);
	foreach ( $t['questions'] as $q ) {
		if ( null === $q['ok'] ) {
			fwrite( STDERR, "Bonne réponse introuvable : {$t['id']} n°{$q['n']}\n" );
			exit( 1 );
		}
		$out['questions'][] = array( $ti, $q['n'], $q['q'], $q['r'], $q['ok'], $q['src'] );
	}
}
file_put_contents( $argv[2], json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
printf( "%d thèmes, %d questions -> %s\n", count( $out['themes'] ), count( $out['questions'] ), $argv[2] );
