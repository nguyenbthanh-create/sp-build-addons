/* Claira TKD Parcours : accès réservé au QCM Dan (imprimé en ligne dans <head> par includes/qcm-dan.php).
 * Accès si l'application du club (/app/, sp_build) est connectée sur cet appareil (localStorage « spcal_jwt »)
 * et si l'adhérent est actif, à jour de son adhésion et de catégorie Ado / Adulte. Résultat gardé 6 h.
 * Pose « claira-qcm-ok » ou « claira-qcm-non » sur <html> et émet l'évènement « claira-qcm-acces ». */
( function () {
	'use strict';
	var d = document.documentElement, CLE = 'claira_qcm_acces', DUREE = 6 * 3600 * 1000;
	function fin( ok ) {
		d.classList.add( ok ? 'claira-qcm-ok' : 'claira-qcm-non' );
		window.ClairaQcmAcces = ok;
		try { document.dispatchEvent( new CustomEvent( 'claira-qcm-acces', { detail: ok } ) ); } catch ( e ) { /* ancien navigateur */ }
	}
	var jwt = null, cache = null;
	try {
		jwt = window.localStorage.getItem( 'spcal_jwt' );
		cache = JSON.parse( window.localStorage.getItem( CLE ) || 'null' );
	} catch ( e ) { /* stockage indisponible */ }
	if ( ! jwt || ! window.fetch || ! window.ClairaQcmAccesApi ) { fin( false ); return; }
	var sig = jwt.slice( -24 );
	if ( cache && cache.s === sig && Date.now() - cache.t < DUREE ) { fin( !! cache.ok ); return; }
	fetch( window.ClairaQcmAccesApi, { headers: { 'X-Spcal-Jwt': jwt }, credentials: 'same-origin' } )
		.then( function ( r ) { return r.ok ? r.json() : null; } )
		.then( function ( m ) {
			var ok = !! ( m && m.adhesion && m.adhesion.statut !== 'expire' && /ado|adulte/i.test( m.categorie_age || '' ) );
			try { window.localStorage.setItem( CLE, JSON.stringify( { s: sig, t: Date.now(), ok: ok } ) ); } catch ( e ) { /* rien */ }
			fin( ok );
		} )
		[ 'catch' ]( function () { fin( false ); } );
}() );
