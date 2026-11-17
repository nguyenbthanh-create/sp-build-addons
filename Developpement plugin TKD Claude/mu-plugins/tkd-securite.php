<?php
/**
 * Plugin Name: TKD Sécurité
 * Description: Masque les identifiants des comptes (API, pages auteur, sitemap, oEmbed), limite les tentatives de connexion (5 échecs = 15 minutes de blocage par adresse IP) et retire le numéro de version de WordPress des pages.
 * Version:     1.0.0
 * Author:      TKD Claira
 *
 * Extension « toujours active » (wp-content/mu-plugins/), indépendante des autres extensions
 * et du thème. Pour la retirer : supprimer ce fichier par FTP. Créée le 17/11/2026 après
 * l'audit du site, qui montrait :
 *   - /wp-json/wp/v2/users et ?author=1 donnaient à tout visiteur les identifiants de
 *     connexion des comptes (dont celui de l'administrateur principal) et leurs noms ;
 *   - wp-login.php acceptait un nombre illimité d'essais de mot de passe.
 *
 * Les comptes connectés ne sont pas concernés par les blocages de l'API et des pages auteur
 * (éditeurs Elementor et Gutenberg). Un compte bloqué par erreur : attendre 15 minutes, ou
 * supprimer ce fichier par FTP le temps de se connecter.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------------------------
 * 1. Identifiants des comptes
 * ------------------------------------------------------------------------------------- */

// Liste et fiches des comptes de l'API : réservées aux comptes connectés.
add_filter( 'rest_pre_dispatch', static function ( $resultat, $serveur, $requete ) {
	if ( is_user_logged_in() || ! $requete instanceof WP_REST_Request ) return $resultat;
	$route = (string) $requete->get_route();
	if ( $route === '/wp/v2/users' || str_starts_with( $route, '/wp/v2/users/' ) ) {
		return new WP_Error( 'rest_forbidden', 'Liste des comptes réservée aux comptes connectés.', [ 'status' => 401 ] );
	}
	return $resultat;
}, 10, 3 );

// ?author=N et /author/identifiant/ : retour à l'accueil pour les visiteurs (avant la
// redirection de WordPress vers /author/identifiant/, qui dévoilerait l'identifiant).
add_action( 'template_redirect', static function () {
	if ( is_user_logged_in() ) return;
	if ( isset( $_GET['author'] ) || is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 0 );

// Pas de liste des comptes dans le sitemap envoyé aux moteurs de recherche.
add_filter( 'wp_sitemaps_add_provider', static fn( $fournisseur, $nom ) => $nom === 'users' ? false : $fournisseur, 10, 2 );

// Aperçus oEmbed (partage de lien) : sans nom ni lien d'auteur.
add_filter( 'oembed_response_data', static function ( $donnees ) {
	unset( $donnees['author_name'], $donnees['author_url'] );
	return $donnees;
} );

/* ---------------------------------------------------------------------------------------
 * 2. Tentatives de connexion
 * ------------------------------------------------------------------------------------- */

const TKD_SECU_ESSAIS = 5;                    // échecs autorisés…
const TKD_SECU_DUREE  = 15 * MINUTE_IN_SECONDS; // …pendant cette durée, puis blocage aussi long

function tkd_secu_cle(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return 'tkd_secu_echecs_' . md5( $ip );
}

// Compte bloqué : refus avant même de vérifier le mot de passe.
add_filter( 'authenticate', static function ( $utilisateur ) {
	if ( (int) get_transient( tkd_secu_cle() ) >= TKD_SECU_ESSAIS ) {
		return new WP_Error( 'tkd_trop_de_tentatives', '<strong>Erreur :</strong> trop de tentatives de connexion. Réessayez dans 15 minutes.' );
	}
	return $utilisateur;
}, 30 );

// Message unique pour un identifiant inconnu ou un mot de passe faux (sinon le message
// « identifiant inconnu » permet de deviner les comptes existants).
add_filter( 'authenticate', static function ( $utilisateur ) {
	if ( is_wp_error( $utilisateur ) && array_intersect( $utilisateur->get_error_codes(), [ 'invalid_username', 'invalid_email', 'incorrect_password' ] ) ) {
		return new WP_Error( 'incorrect_password', '<strong>Erreur :</strong> identifiant ou mot de passe incorrect. <a href="' . esc_url( wp_lostpassword_url() ) . '">Mot de passe oublié ?</a>' );
	}
	return $utilisateur;
}, 99 );

// Pendant le blocage, les nouveaux essais ne le prolongent pas (il se lève 15 minutes après
// le 5e échec).
add_action( 'wp_login_failed', static function ( $identifiant, $erreur = null ) {
	if ( $erreur instanceof WP_Error && $erreur->get_error_code() === 'tkd_trop_de_tentatives' ) return;
	$cle = tkd_secu_cle();
	set_transient( $cle, (int) get_transient( $cle ) + 1, TKD_SECU_DUREE );
}, 10, 2 );

add_action( 'wp_login', static function () {
	delete_transient( tkd_secu_cle() );
} );

/* ---------------------------------------------------------------------------------------
 * 3. Numéro de version de WordPress
 * ------------------------------------------------------------------------------------- */

remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
