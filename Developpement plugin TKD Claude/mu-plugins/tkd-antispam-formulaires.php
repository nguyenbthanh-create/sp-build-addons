<?php
/**
 * Plugin Name: TKD Anti-spam formulaires
 * Description: Refuse les liens (http, www, balises de lien) dans les formulaires Elementor listés ci-dessous, en particulier le formulaire de contact visé par les robots.
 * Version:     1.0.0
 * Author:      TKD Claira
 *
 * Extension « toujours active » (wp-content/mu-plugins/), indépendante de sp_build et du
 * thème : elle n'apparaît pas dans la liste des extensions et ne se désactive pas par
 * erreur. Pour la retirer : supprimer ce fichier par FTP. Créée le 10/11/2026.
 *
 * S'appuie sur le crochet « elementor_pro/forms/validation » d'Elementor Pro
 * (modules/forms/classes/form-record.php) : un champ en erreur empêche l'envoi, aucun mail
 * ne part et rien n'est enregistré. Seuls les formulaires nommés dans TKD_ANTISPAM_FORMULAIRES
 * sont filtrés : le formulaire de candidature au gala, lui, demande des liens (vidéo, site).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Noms des formulaires (réglage « Nom du formulaire » du widget Elementor) à filtrer.
const TKD_ANTISPAM_FORMULAIRES = [ 'Contact Form' ];

// Types de champs contrôlés : texte libre seulement (pas l'e-mail, l'URL ni le téléphone).
const TKD_ANTISPAM_TYPES = [ 'text', 'textarea' ];

/** Le texte contient-il un lien ? (adresse web, « www. », balise HTML ou BBCode de lien) */
function tkd_antispam_contient_lien( string $texte ): bool {
	return (bool) preg_match( '~(https?\s*:\s*/\s*/|\bwww\s*\.|<\s*a\s|href\s*=|\[\s*url)~i', $texte );
}

add_action( 'elementor_pro/forms/validation', static function ( $record, $ajax_handler ) {
	$nom = (string) $record->get_form_settings( 'form_name' );
	if ( ! in_array( $nom, TKD_ANTISPAM_FORMULAIRES, true ) ) return;

	foreach ( (array) $record->get( 'fields' ) as $id => $champ ) {
		if ( ! in_array( $champ['type'] ?? '', TKD_ANTISPAM_TYPES, true ) ) continue;
		if ( tkd_antispam_contient_lien( (string) ( $champ['raw_value'] ?? $champ['value'] ?? '' ) ) ) {
			$ajax_handler->add_error( $id, 'Les liens ne sont pas autorisés dans ce formulaire. Retirez l\'adresse web de votre message, ou écrivez-la sans « http » ni « www ».' );
		}
	}
}, 10, 2 );
