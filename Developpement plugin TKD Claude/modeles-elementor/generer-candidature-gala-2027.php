<?php
/**
 * Génère le modèle Elementor « Candidature — Gala des Arts Martiaux 2027 » (10/11/2026).
 *
 * Usage :  php generer-candidature-gala-2027.php   →  candidature-gala-2027.json (à côté)
 * Import :  wp-admin → Modèles → Modèles enregistrés → Importer des modèles.
 *
 * Formulaire Elementor Pro en 6 étapes, envoyé une seule fois (pas de modification ensuite),
 * d'après les pages 1 à 7 du dossier PDF. Noms de réglages relevés dans le code d'Elementor
 * Pro 4.3.1 du site (modules/forms/widgets/form.php, actions/email.php, email2.php,
 * submissions/actions/save-to-database.php, fields/step.php, acceptance.php, number.php).
 * Pour changer un texte : modifier ce fichier et régénérer, ou modifier directement dans
 * l'éditeur Elementor après import (ce fichier ne sert alors plus qu'à recréer la page).
 */

$ORGA_EMAIL   = 'contact@tkdclaira.fr';
$DATE_LIMITE  = '15 décembre 2026';
$DATE_GALA    = 'samedi 30 janvier 2027';
$DUREE_MIN    = 10;
$DUREE_MAX    = 15;   // ⚠️ le PDF dit « 10 à 20 » en page 1 et « 10 à 15 » en pages 4 et 5 : à confirmer

$ids = [];
function id7(): string { global $ids; do { $i = substr( bin2hex( random_bytes( 4 ) ), 0, 7 ); } while ( isset( $ids[ $i ] ) ); $ids[ $i ] = 1; return $i; }

function champ( string $type, string $id, string $label, array $o = [] ): array {
	$c = [ '_id' => id7(), 'custom_id' => $id, 'field_type' => $type, 'field_label' => $label, 'width' => (string) ( $o['width'] ?? 100 ) ];
	if ( ! empty( $o['req'] ) ) $c['required'] = 'true';
	foreach ( [ 'placeholder', 'rows', 'field_min', 'field_max', 'acceptance_text', 'field_html' ] as $k ) if ( isset( $o[ $k ] ) ) $c[ $k ] = (string) $o[ $k ];
	if ( isset( $o['options'] ) ) $c['field_options'] = implode( "\n", $o['options'] );
	if ( ! empty( $o['inline'] ) ) $c['inline_list'] = 'elementor-subgroup-inline';
	return $c;
}
function etape( string $id, string $label ): array {
	return [ '_id' => id7(), 'custom_id' => $id, 'field_type' => 'step', 'field_label' => $label, 'previous_button' => '', 'next_button' => '' ];
}
function html( string $id, string $html ): array {
	return [ '_id' => id7(), 'custom_id' => $id, 'field_type' => 'html', 'field_label' => '', 'field_html' => $html, 'width' => '100' ];
}
function liste( array $items ): string {
	return '<ul style="margin:0 0 0 1.2em;padding:0">' . implode( '', array_map( fn( $i ) => '<li>' . $i . '</li>', $items ) ) . '</ul>';
}
$encart = 'background:#f6f7fb;border-left:4px solid #1d2b53;border-radius:6px;padding:12px 16px;margin:4px 0 8px;';
$alerte = 'background:#fdecee;border-left:4px solid #d62839;border-radius:6px;padding:12px 16px;margin:4px 0 8px;';

$champs = [
	// ── 1. Règlement ───────────────────────────────────────────────────────────
	etape( 'etape_reglement', 'Conditions' ),
	html( 'info_conditions', '<div style="' . $alerte . '"><strong>⚠️ Conditions essentielles</strong>' . liste( [
		'Discipline martiale <strong>hors taekwondo</strong>, proposée par une association, un club ou une structure organisée',
		"Démonstration de <strong>$DUREE_MIN à $DUREE_MAX minutes</strong>, obligatoirement <strong>en musique</strong>",
		'Une seule piste audio de bonne qualité (demandée plus tard aux structures retenues)',
		'<strong>Vidéo obligatoire</strong> montrant une démonstration déjà effectuée (lien)',
		'Participation <strong>bénévole et non rémunérée</strong>',
		"Dossier à transmettre <strong>avant le $DATE_LIMITE</strong>",
	] ) . '</div>'
	. '<div style="' . $encart . '">Le dépôt d\'un dossier ne garantit pas la participation au Gala : Taekwondo Claira sélectionne librement les démonstrations (diversité des disciplines, contraintes techniques, de sécurité et d\'organisation) et peut demander des modifications de durée, de contenu ou de conditions techniques. Les horaires sont indicatifs et peuvent être modifiés par l\'organisation. Aucune indemnité ne pourra être réclamée en cas de non-sélection ou de modification de la programmation.</div>' ),
	champ( 'acceptance', 'accepte_reglement', 'Règlement', [ 'req' => 1, 'acceptance_text' => "J'accepte le règlement et les conditions de participation au Gala des Arts Martiaux de Claira." ] ),

	// ── 2. Structure ───────────────────────────────────────────────────────────
	etape( 'etape_structure', 'Structure' ),
	champ( 'text', 'struct_nom', "Raison sociale / Nom de l'association", [ 'req' => 1, 'width' => 66 ] ),
	champ( 'text', 'struct_sigle', 'Sigle', [ 'width' => 33 ] ),
	champ( 'text', 'struct_siret', 'SIRET', [ 'width' => 50 ] ),
	champ( 'text', 'struct_rna', 'N° RNA / W', [ 'width' => 50 ] ),
	champ( 'text', 'struct_siege', 'Siège social (adresse)', [ 'req' => 1, 'width' => 66 ] ),
	champ( 'text', 'struct_cp_ville', 'Code postal / Ville', [ 'req' => 1, 'width' => 33 ] ),
	champ( 'url', 'struct_site', 'Site internet', [ 'width' => 50, 'placeholder' => 'https://' ] ),
	champ( 'text', 'struct_reseaux', 'Réseaux sociaux', [ 'width' => 50, 'placeholder' => 'Facebook, Instagram…' ] ),
	champ( 'radio', 'struct_forme', 'Forme juridique', [ 'req' => 1, 'inline' => 1, 'options' => [ 'Association loi 1901', 'Club sportif', 'Autre' ] ] ),
	champ( 'text', 'struct_forme_autre', 'Si « Autre », précisez', [] ),
	champ( 'text', 'struct_creation', 'Date (ou année) de création', [ 'width' => 50 ] ),
	champ( 'number', 'struct_adherents', 'Nombre approximatif de licenciés / adhérents', [ 'width' => 50, 'field_min' => 0 ] ),
	champ( 'checkbox', 'struct_publics', 'Publics accueillis', [ 'inline' => 1, 'options' => [ 'Enfants', 'Adolescents', 'Adultes', 'Seniors', 'Tous publics' ] ] ),
	champ( 'textarea', 'struct_presentation', 'Présentation de la structure', [ 'req' => 1, 'rows' => 5, 'placeholder' => 'Histoire, activités, valeurs, principales actions…' ] ),

	// ── 3. Contacts ────────────────────────────────────────────────────────────
	etape( 'etape_contacts', 'Contacts' ),
	html( 'titre_responsable', '<strong>Responsable de la structure</strong>' ),
	champ( 'text', 'resp_nom', 'Nom et prénom', [ 'req' => 1, 'width' => 50 ] ),
	champ( 'text', 'resp_fonction', 'Fonction', [ 'req' => 1, 'width' => 50 ] ),
	champ( 'tel', 'resp_tel', 'Téléphone', [ 'req' => 1, 'width' => 50 ] ),
	champ( 'email', 'resp_email', 'Adresse e-mail', [ 'req' => 1, 'width' => 50, 'placeholder' => 'Le récapitulatif de la candidature y sera envoyé' ] ),
	html( 'titre_referent', '<strong>Personne référente pour le Gala</strong> <span style="opacity:.7">(si différente du responsable)</span>' ),
	champ( 'text', 'ref_nom', 'Nom et prénom', [ 'width' => 50 ] ),
	champ( 'text', 'ref_fonction', 'Fonction', [ 'width' => 50 ] ),
	champ( 'tel', 'ref_tel', 'Téléphone', [ 'width' => 50 ] ),
	champ( 'email', 'ref_email', 'E-mail', [ 'width' => 50 ] ),

	// ── 4. Discipline et démonstration ─────────────────────────────────────────
	etape( 'etape_demonstration', 'Démonstration' ),
	champ( 'text', 'disc_nom', 'Discipline présentée', [ 'req' => 1, 'width' => 50 ] ),
	champ( 'text', 'disc_federation', "Fédération / organisme d'affiliation (le cas échéant)", [ 'width' => 50 ] ),
	champ( 'text', 'disc_lieux', 'Lieu(x) habituel(s) de pratique', [ 'width' => 50 ] ),
	champ( 'text', 'disc_villes', 'Ville(s) de pratique', [ 'width' => 50 ] ),
	champ( 'number', 'disc_annees', "Nombre d'années de pratique de la discipline", [ 'width' => 50, 'field_min' => 0 ] ),
	champ( 'text', 'disc_niveaux', 'Principaux niveaux / grades représentés', [ 'width' => 50 ] ),
	champ( 'text', 'demo_titre', 'Titre / nom de la démonstration', [ 'req' => 1 ] ),
	champ( 'number', 'demo_duree', "Durée prévue (minutes, entre $DUREE_MIN et $DUREE_MAX)", [ 'req' => 1, 'width' => 50, 'field_min' => $DUREE_MIN, 'field_max' => $DUREE_MAX ] ),
	champ( 'number', 'demo_participants', 'Nombre de participants prévus', [ 'req' => 1, 'width' => 50, 'field_min' => 1 ] ),
	champ( 'text', 'demo_ages', 'Âge des participants', [ 'req' => 1, 'width' => 50, 'placeholder' => 'Ex. : de 8 à 45 ans' ] ),
	champ( 'number', 'demo_encadrants', "Nombre d'encadrants / responsables présents", [ 'req' => 1, 'width' => 50, 'field_min' => 1 ] ),
	champ( 'textarea', 'demo_contenu', 'Contenu de la démonstration', [ 'req' => 1, 'rows' => 5, 'placeholder' => 'Décrivez brièvement le déroulement prévu de votre prestation.' ] ),
	champ( 'checkbox', 'demo_elements', 'Éléments présentés', [ 'inline' => 1, 'options' => [ 'Techniques martiales', 'Katas / formes', 'Combat / opposition', 'Démonstration technique', 'Acrobatie', 'Armes traditionnelles', 'Démonstration collective', 'Autre' ] ] ),
	champ( 'text', 'demo_elements_autre', 'Si « Autre », précisez', [] ),
	html( 'info_musique', '<div style="' . $encart . '">🎵 <strong>Musique</strong> : la démonstration se déroule obligatoirement en musique. Une seule piste audio de bonne qualité sera demandée aux structures retenues, dans un délai qui leur sera communiqué.</div>' ),

	// ── 5. Technique et vidéo ──────────────────────────────────────────────────
	etape( 'etape_technique', 'Technique et vidéo' ),
	champ( 'textarea', 'tech_surface', 'Surface / espace nécessaire', [ 'rows' => 2 ] ),
	champ( 'checkbox', 'tech_materiel', 'Matériel nécessaire', [ 'inline' => 1, 'options' => [ 'Aucun matériel particulier', 'Tatamis', 'Armes / matériel spécifique', 'Accessoires', 'Autre' ] ] ),
	champ( 'text', 'tech_materiel_autre', 'Si « Autre », précisez', [] ),
	champ( 'textarea', 'tech_materiel_liste', 'Liste détaillée du matériel nécessaire', [ 'rows' => 3 ] ),
	champ( 'textarea', 'tech_besoins', 'Besoins particuliers', [ 'rows' => 3 ] ),
	html( 'info_materiel', '<div style="' . $encart . '">Sauf accord préalable avec l\'organisation, le matériel spécifique nécessaire à la démonstration est apporté par la structure participante.</div>' ),
	html( 'info_video', '<div style="' . $alerte . '">🎬 <strong>Vidéo obligatoire</strong> : elle doit montrer une démonstration <strong>déjà effectuée</strong> par la structure (gala, compétition, manifestation…) pour permettre au comité d\'apprécier le niveau, le contenu et la faisabilité. Une candidature sans vidéo pourra être considérée comme incomplète.<br>Vérifiez que le lien est accessible sans compte : vidéo YouTube « non répertoriée », ou partage Google Drive « Tous les utilisateurs disposant du lien ».</div>' ),
	champ( 'url', 'video_lien', 'Lien vers la vidéo', [ 'req' => 1, 'width' => 66, 'placeholder' => 'https://' ] ),
	champ( 'select', 'video_plateforme', 'Plateforme', [ 'req' => 1, 'width' => 33, 'options' => [ 'YouTube', 'Google Drive', 'Autre' ] ] ),

	// ── 6. Engagement ──────────────────────────────────────────────────────────
	etape( 'etape_engagement', 'Engagement' ),
	champ( 'acceptance', 'dispo_confirme', 'Disponibilité', [ 'req' => 1, 'acceptance_text' => "La structure confirme être disponible le $DATE_GALA et s'engage à respecter les horaires d'arrivée, de répétition et de passage communiqués par l'organisation (susceptibles d'être modifiés)." ] ),
	html( 'info_engagement', '<div style="' . $encart . '"><strong>En déposant ce dossier, la structure s\'engage à :</strong>' . liste( [
		'respecter les consignes de sécurité, d\'organisation et les horaires communiqués ;',
		'informer l\'organisateur de tout élément pouvant avoir une incidence sur la sécurité des participants ou du public ;',
		'faire encadrer ses participants par les personnes compétentes de sa structure pendant leur présence sur l\'événement ;',
		'fournir ultérieurement, si elle est retenue : attestation d\'assurance responsabilité civile, liste définitive des participants, autorisations parentales des mineurs, piste audio définitive.',
	] ) . '</div>' ),
	champ( 'acceptance', 'engagement', 'Engagement', [ 'req' => 1, 'acceptance_text' => 'La structure a pris connaissance des conditions de participation et de sélection et s\'y engage.' ] ),
	html( 'titre_declaration', '<strong>Déclaration</strong>' ),
	champ( 'text', 'decl_nom', 'Nom', [ 'req' => 1, 'width' => 33 ] ),
	champ( 'text', 'decl_prenom', 'Prénom', [ 'req' => 1, 'width' => 33 ] ),
	champ( 'text', 'decl_fonction', 'Fonction au sein de la structure', [ 'req' => 1, 'width' => 33 ] ),
	champ( 'acceptance', 'decl_certifie', 'Certification', [ 'req' => 1, 'acceptance_text' => "Je certifie l'exactitude des informations communiquées et confirme que la participation proposée est bénévole et non rémunérée." ] ),
	[ '_id' => id7(), 'custom_id' => 'piege', 'field_type' => 'honeypot', 'field_label' => '', 'width' => '100' ],
];

$recap_candidat = '<p>Bonjour [field id="resp_nom"],</p>'
	. '<p>Nous avons bien reçu la candidature de <strong>[field id="struct_nom"]</strong> au Gala des Arts Martiaux de Claira (' . $DATE_GALA . '). Merci !</p>'
	. '<p>Le comité organisateur étudiera les dossiers après le ' . $DATE_LIMITE . ' et reviendra vers vous. Pour toute correction, répondez simplement à ce message.</p>'
	. '<p><strong>Copie de votre candidature :</strong></p>[all-fields]'
	. '<p>Taekwondo Claira — ' . $ORGA_EMAIL . ' — tkdclaira.fr</p>';

$form = [
	'id' => id7(), 'elType' => 'widget', 'widgetType' => 'form', 'elements' => [],
	'settings' => [
		'form_name'           => 'Candidature Gala Arts Martiaux 2027',
		'form_fields'         => $champs,
		'input_size'          => 'sm',
		'show_labels'         => 'true',
		'mark_required'       => 'yes',
		'step_type'           => 'number_text',
		'step_next_label'     => 'Suivant',
		'step_previous_label' => 'Précédent',
		'button_text'         => 'Envoyer la candidature',
		'button_width'        => '100',
		'submit_actions'      => [ 'save-to-database', 'email', 'email2' ],
		'email_to'            => $ORGA_EMAIL,
		'email_subject'       => '[Gala 2027] Candidature : [field id="struct_nom"] — [field id="disc_nom"]',
		'email_content'       => '[all-fields]',
		'email_reply_to'      => '[field id="resp_email"]',
		'email_content_type'  => 'html',
		'form_metadata'       => [ 'date', 'time', 'page_url', 'remote_ip' ],
		'email_to_2'          => '[field id="resp_email"]',
		'email_subject_2'     => 'Votre candidature au Gala des Arts Martiaux de Claira',
		'email_content_2'     => $recap_candidat,
		'email_reply_to_2'    => $ORGA_EMAIL,
		'email_content_type_2' => 'html',
		'form_metadata_2'     => [],
		'form_id'             => 'candidature_gala_2027',
		'success_message'     => 'Merci ! Votre candidature a bien été envoyée. Une copie vient de vous être adressée par e-mail (pensez à regarder dans les courriers indésirables).',
		'error_message'       => 'Une erreur est survenue : vérifiez les champs signalés puis réessayez.',
		'required_field_message' => 'Ce champ est obligatoire.',
		'invalid_message'     => 'Valeur invalide.',
		'custom_messages'     => 'yes', // interrupteur sans return_value : « yes »
	],
];

$intro = [
	'id' => id7(), 'elType' => 'widget', 'widgetType' => 'text-editor', 'elements' => [],
	'settings' => [ 'editor' => '<p>Taekwondo Claira lance un appel à candidatures auprès des associations, clubs et organismes pratiquant <strong>une discipline martiale autre que le taekwondo</strong>, pour participer au <strong>Gala des Arts Martiaux</strong> du ' . $DATE_GALA . ' à la Salle Polyvalente de Claira (ouverture des portes 18h00, démonstrations à partir de 19h00).</p><p><strong>Date limite de candidature : ' . $DATE_LIMITE . '.</strong> Le formulaire compte 6 étapes ; préparez le lien de votre vidéo de démonstration.</p>' ],
];
$titre = [
	'id' => id7(), 'elType' => 'widget', 'widgetType' => 'heading', 'elements' => [],
	'settings' => [ 'title' => 'Candidature — Gala des Arts Martiaux 2027', 'header_size' => 'h1' ],
];

$modele = [
	'version'       => '0.4',
	'title'         => 'Candidature Gala Arts Martiaux 2027',
	'type'          => 'page',
	'page_settings' => [],
	'content'       => [ [
		'id' => id7(), 'elType' => 'container', 'isInner' => false,
		'settings' => [ 'content_width' => 'boxed', 'flex_direction' => 'column', 'boxed_width' => [ 'unit' => 'px', 'size' => 860, 'sizes' => [] ] ],
		'elements' => [ $titre, $intro, $form ],
	] ],
];

$json = json_encode( $modele, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
file_put_contents( __DIR__ . '/candidature-gala-2027.json', $json );
echo count( $champs ) . " champs, " . strlen( $json ) . " octets\n";
