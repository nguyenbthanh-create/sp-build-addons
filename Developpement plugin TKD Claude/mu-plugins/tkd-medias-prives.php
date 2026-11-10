<?php
/**
 * Plugin Name: TKD Médiathèque non listée
 * Description: Réserve aux comptes connectés la liste des médias de l'API WordPress (/wp-json/wp/v2/media), qui publiait l'adresse de tous les fichiers envoyés sur le site.
 * Version:     1.0.0
 * Author:      TKD Claira
 *
 * Extension « toujours active » (wp-content/mu-plugins/), indépendante des autres extensions
 * et du thème. Pour la retirer : supprimer ce fichier par FTP. Créée le 10/11/2026.
 *
 * Les fichiers publiés sur les pages (images, PDF du règlement…) s'affichent toujours
 * normalement : seule la liste complète, et la fiche d'un média via l'API, sont refusées
 * aux visiteurs non connectés. L'éditeur (Elementor, Gutenberg) est connecté : non concerné.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'rest_pre_dispatch', static function ( $resultat, $serveur, $requete ) {
	if ( is_user_logged_in() || ! $requete instanceof WP_REST_Request ) return $resultat;
	$route = (string) $requete->get_route();
	if ( $route === '/wp/v2/media' || str_starts_with( $route, '/wp/v2/media/' ) ) {
		return new WP_Error( 'rest_forbidden', 'Liste des médias réservée aux comptes connectés.', [ 'status' => 401 ] );
	}
	return $resultat;
}, 10, 3 );
