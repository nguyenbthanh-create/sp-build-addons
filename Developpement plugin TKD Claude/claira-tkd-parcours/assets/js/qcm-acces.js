/* Claira TKD Parcours : accès réservé au QCM Dan (imprimé en ligne dans <head> par includes/qcm-dan.php).
 * Accès si l'application du club (/app/, sp_build) est connectée sur cet appareil :
 * - adhérent (localStorage « spcal_jwt ») actif, à jour de son adhésion et de catégorie Ado / Adulte ;
 * - ou entraîneur ouvert par son lien personnel (localStorage « spcal_trainer_token », 18/11/2026),
 *   vérifié par le serveur (route claira-tkd/v1/qcm/acces-entraineur, qui ne renvoie qu'un oui / non).
 * Résultat gardé 6 h. Pose « claira-qcm-ok » ou « claira-qcm-non » sur <html> et émet l'évènement « claira-qcm-acces ». */
( function () {
	'use strict';
	var d = document.documentElement, CLE = 'claira_qcm_acces', DUREE = 6 * 3600 * 1000;
	function fin( ok ) {
		d.classList.add( ok ? 'claira-qcm-ok' : 'claira-qcm-non' );
		window.ClairaQcmAcces = ok;
		try { document.dispatchEvent( new CustomEvent( 'claira-qcm-acces', { detail: ok } ) ); } catch ( e ) { /* ancien navigateur */ }
	}
	var jwt = null, entr = null, cache = null;
	try {
		jwt = window.localStorage.getItem( 'spcal_jwt' );
		entr = window.localStorage.getItem( 'spcal_trainer_token' );
		cache = JSON.parse( window.localStorage.getItem( CLE ) || 'null' );
	} catch ( e ) { /* stockage indisponible */ }
	if ( ( ! jwt && ! entr ) || ! window.fetch ) { fin( false ); return; }
	var sig = ( jwt || '' ).slice( -24 ) + '|' + ( entr || '' ).slice( -12 );
	if ( cache && cache.s === sig && Date.now() - cache.t < DUREE ) { fin( !! cache.ok ); return; }

	function noter( ok ) {
		try { window.localStorage.setItem( CLE, JSON.stringify( { s: sig, t: Date.now(), ok: ok } ) ); } catch ( e ) { /* rien */ }
		fin( ok );
	}
	function viaEntraineur() {
		if ( ! entr || ! window.ClairaQcmAccesEntr ) { noter( false ); return; }
		fetch( window.ClairaQcmAccesEntr, { headers: { 'X-Spcal-Entraineur': entr }, credentials: 'same-origin' } )
			.then( function ( r ) { return r.ok ? r.json() : null; } )
			.then( function ( m ) { noter( !! ( m && m.ok ) ); } )
			[ 'catch' ]( function () { fin( false ); } );
	}
	if ( ! jwt || ! window.ClairaQcmAccesApi ) { viaEntraineur(); return; }
	fetch( window.ClairaQcmAccesApi, { headers: { 'X-Spcal-Jwt': jwt }, credentials: 'same-origin' } )
		.then( function ( r ) { return r.ok ? r.json() : null; } )
		.then( function ( m ) {
			if ( m && m.adhesion && m.adhesion.statut !== 'expire' && /ado|adulte/i.test( m.categorie_age || '' ) ) { noter( true ); } else { viaEntraineur(); }
		} )
		[ 'catch' ]( viaEntraineur );
}() );
