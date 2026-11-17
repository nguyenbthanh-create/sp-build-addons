/**
 * Claira TKD Parcours : module « QCM Dan » (Module B FFTDA).
 * Moteur de recherche + examen blanc, entièrement dans le navigateur.
 * Utilisé par le tableau de bord (mode « admin ») et le shortcode (mode « site »).
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------
	 * Outils
	 * ---------------------------------------------------------------- */

	function el( tag, attrs, enfants ) {
		var n = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				var v = attrs[ k ];
				if ( v === null || v === undefined || v === false ) { return; }
				if ( k === 'text' ) { n.textContent = v; }
				else if ( k === 'html' ) { n.innerHTML = v; }
				else if ( k === 'class' ) { n.className = v; }
				else if ( k.indexOf( 'on' ) === 0 ) { n.addEventListener( k.slice( 2 ), v ); }
				else { n.setAttribute( k, v === true ? '' : v ); }
			} );
		}
		( enfants || [] ).forEach( function ( c ) {
			if ( c === null || c === undefined || c === false ) { return; }
			n.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c );
		} );
		return n;
	}

	function echap( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function melanger( t ) {
		var a = t.slice(), i, j, x;
		for ( i = a.length - 1; i > 0; i-- ) {
			j = Math.floor( Math.random() * ( i + 1 ) );
			x = a[ i ]; a[ i ] = a[ j ]; a[ j ] = x;
		}
		return a;
	}

	var stockage = {
		lire: function ( cle, defaut ) {
			try { var v = window.localStorage.getItem( cle ); return v ? JSON.parse( v ) : defaut; } catch ( e ) { return defaut; }
		},
		ecrire: function ( cle, v ) {
			try { window.localStorage.setItem( cle, JSON.stringify( v ) ); } catch ( e ) { /* stockage indisponible */ }
		}
	};

	var LETTRES = [ 'A', 'B', 'C', 'D' ];

	/* ------------------------------------------------------------------
	 * Moteur de recherche
	 * ---------------------------------------------------------------- */

	var MOTS_VIDES = ( 'a au aux avec ce ces cet cette d de des du elle en est et etre il ils je l la le les leur leurs ' +
		'lui ma mais me mes moi mon n ne nos notre nous on ou par pas pour qu que quel quelle quelles quels qui quoi ' +
		'sa se ses si son sont sur ta te tes toi ton tu un une vos votre vous y combien comment quand doit doivent ' +
		'dans peut peuvent faut t selon parmi lequel laquelle lesquels lesquelles quoi dont cela ceci ca c s' ).split( ' ' );
	var VIDES = {};
	MOTS_VIDES.forEach( function ( m ) { VIDES[ m ] = true; } );

	// Variantes d'écriture ramenées à une forme unique.
	var SYNONYMES = {
		poumse: 'poomsae', pumse: 'poomsae', poomse: 'poomsae', poumsae: 'poomsae',
		kyorugi: 'combat', kyeorugi: 'combat', kyorougui: 'combat', gyeorugi: 'combat',
		tkd: 'taekwondo', taekwon: 'taekwondo',
		jo: 'olympique', jeux: 'olympique', olympiques: 'olympique', olympisme: 'olympique',
		cnosf: 'cnosf', wt: 'wt', wtf: 'wt',
		kukiwon: 'kukkiwon', kukkiwom: 'kukkiwon',
		federal: 'federation', federale: 'federation', federaux: 'federation', federations: 'federation',
		medecin: 'medical', medicale: 'medical', medicaux: 'medical',
		arbitrage: 'arbitre', arbitres: 'arbitre', arbitrer: 'arbitre'
	};

	function normaliser( s ) {
		return String( s ).toLowerCase()
			.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' )
			.replace( /œ/g, 'oe' ).replace( /æ/g, 'ae' )
			.replace( /[’'`]/g, ' ' )
			.replace( /[^a-z0-9]+/g, ' ' )
			.trim();
	}

	// Racine légère : pluriels et quelques terminaisons courantes.
	function racine( m ) {
		m = SYNONYMES[ m ] || m;
		if ( /^\d+$/.test( m ) ) { return m; }
		if ( m.length > 4 ) { m = m.replace( /(aux)$/, 'al' ).replace( /([^s])s$/, '$1' ).replace( /x$/, '' ); }
		if ( m.length > 5 ) { m = m.replace( /(ement|ements)$/, '' ).replace( /(ee|es|e)$/, '' ); }
		return SYNONYMES[ m ] || m;
	}

	function mots( s, garderVides ) {
		return normaliser( s ).split( ' ' ).filter( function ( m ) {
			return m && ( garderVides || ! VIDES[ m ] );
		} ).map( racine );
	}

	function levenshtein1( a, b ) { // distance <= 1 ?
		if ( Math.abs( a.length - b.length ) > 1 ) { return false; }
		var i = 0, j = 0, diff = 0;
		while ( i < a.length && j < b.length ) {
			if ( a[ i ] === b[ j ] ) { i++; j++; continue; }
			if ( ++diff > 1 ) { return false; }
			if ( a.length > b.length ) { i++; } else if ( b.length > a.length ) { j++; } else { i++; j++; }
		}
		return diff + ( a.length - i ) + ( b.length - j ) <= 1;
	}

	function Moteur( donnees ) {
		var self = this;
		this.themes = donnees.themes;
		this.questions = donnees.questions.map( function ( a, i ) {
			var th = donnees.themes[ a[ 0 ] ];
			return { i: i, t: a[ 0 ], n: a[ 1 ], q: a[ 2 ], r: a[ 3 ], ok: a[ 4 ], src: a[ 5 ], id: th.id + '#' + a[ 1 ] };
		} );
		this.parId = {};
		this.questions.forEach( function ( q ) { self.parId[ q.id ] = q; } );

		// Index inversé : mot -> { question: poids max }.
		// Poids : question 3, bonne réponse 3, autres réponses 1, source et thème 1.
		this.index = {};
		this.questions.forEach( function ( q ) {
			var champs = [ [ q.q, 3 ], [ q.r[ q.ok ], 3 ], [ q.src + ' ' + self.themes[ q.t ].nom, 1 ] ];
			q.r.forEach( function ( r, k ) { if ( k !== q.ok ) { champs.push( [ r, 1 ] ); } } );
			q.texte = normaliser( [ q.q ].concat( q.r, [ q.src ] ).join( ' ' ) );
			champs.forEach( function ( c ) {
				mots( c[ 0 ], true ).forEach( function ( m ) {
					var p = self.index[ m ] || ( self.index[ m ] = {} );
					if ( ! p[ q.i ] || p[ q.i ] < c[ 1 ] ) { p[ q.i ] = c[ 1 ]; }
				} );
			} );
		} );
		this.vocabulaire = Object.keys( this.index );
	}

	// Mots de l'index qui correspondent à un mot cherché : identique, préfixe (3 lettres et plus), ou une faute de frappe (5 lettres et plus).
	Moteur.prototype.variantes = function ( m ) {
		if ( m.length < 4 ) { return this.index[ m ] ? [ m ] : []; }
		return this.vocabulaire.filter( function ( v ) {
			if ( v === m ) { return true; }
			if ( m.length >= 4 && v.indexOf( m ) === 0 ) { return true; }
			return m.length >= 5 && levenshtein1( m, v );
		} );
	};

	/**
	 * Recherche.
	 * opts.mode : 'tous' (tous les mots), 'un' (au moins un mot) ; une question collée
	 * (5 mots utiles ou plus) est classée par pertinence. Les "guillemets" imposent une expression exacte.
	 * opts.theme : index de thème ou -1.
	 */
	Moteur.prototype.chercher = function ( texte, opts ) {
		var self = this, expressions = [], reste, termes, scores = {}, trouves = {}, surligner = {};
		opts = opts || {};
		reste = String( texte ).replace( /"([^"]+)"/g, function ( _, e ) { expressions.push( normaliser( e ) ); return ' '; } );
		termes = mots( reste, false );
		if ( ! termes.length ) { termes = mots( reste, true ); }
		// Dédoublonne.
		termes = termes.filter( function ( m, k ) { return termes.indexOf( m ) === k; } );

		var candidats = this.questions.filter( function ( q ) {
			if ( opts.theme >= 0 && q.t !== opts.theme ) { return false; }
			return expressions.every( function ( e ) { return e && q.texte.indexOf( e ) !== -1; } );
		} );
		var ok = {};
		candidats.forEach( function ( q ) { ok[ q.i ] = true; scores[ q.i ] = 0; trouves[ q.i ] = 0; } );

		if ( ! termes.length ) {
			return { liste: candidats, termes: [], expressions: expressions, surligner: surligner, phrase: false };
		}

		termes.forEach( function ( m ) {
			var vu = {};
			self.variantes( m ).forEach( function ( v ) {
				surligner[ v ] = true;
				var p = self.index[ v ];
				Object.keys( p ).forEach( function ( i ) {
					if ( ! ok[ i ] ) { return; }
					var poids = p[ i ] * ( v === m ? 1 : 0.8 );
					if ( ! vu[ i ] || vu[ i ] < poids ) { vu[ i ] = poids; }
				} );
			} );
			Object.keys( vu ).forEach( function ( i ) { scores[ i ] += vu[ i ]; trouves[ i ] += 1; } );
		} );

		var phrase = termes.length >= 5;
		var minimum = opts.mode === 'un' ? 1 : ( phrase ? Math.ceil( termes.length * 0.5 ) : termes.length );
		var liste = candidats.filter( function ( q ) { return trouves[ q.i ] >= minimum; } );
		liste.sort( function ( a, b ) {
			return ( trouves[ b.i ] - trouves[ a.i ] ) || ( scores[ b.i ] - scores[ a.i ] ) || ( a.i - b.i );
		} );
		if ( phrase && liste.length > 15 ) { liste = liste.slice( 0, 15 ); }
		return { liste: liste, termes: termes, expressions: expressions, surligner: surligner, phrase: phrase };
	};

	// Texte HTML avec les mots trouvés surlignés.
	function surligne( texte, res ) {
		if ( ! res || ( ! Object.keys( res.surligner ).length && ! res.expressions.length ) ) { return echap( texte ); }
		var html = String( texte ).replace( /[\p{L}\p{N}]+|[^\p{L}\p{N}]+/gu, function ( morceau ) {
			if ( ! /[\p{L}\p{N}]/u.test( morceau ) ) { return echap( morceau ); }
			var n = normaliser( morceau ), r = racine( n );
			return ( res.surligner[ n ] || res.surligner[ r ] ) ? '<mark>' + echap( morceau ) + '</mark>' : echap( morceau );
		} );
		return html;
	}

	/* ------------------------------------------------------------------
	 * Affichage d'une question (recherche)
	 * ---------------------------------------------------------------- */

	function carteQuestion( moteur, q, res, options ) {
		options = options || {};
		var th = moteur.themes[ q.t ];
		var reponses = el( 'ol', { class: 'claira-qcm-reponses', type: 'A' } );
		q.r.forEach( function ( r, k ) {
			reponses.appendChild( el( 'li', {
				class: k === q.ok ? 'is-bonne' : '',
				html: '<span class="claira-qcm-lettre">' + LETTRES[ k ] + '</span> ' + surligne( r, res ) + ( k === q.ok ? ' <span class="claira-qcm-coche" aria-label="Bonne réponse">✔</span>' : '' )
			} ) );
		} );
		var tete = el( 'div', { class: 'claira-qcm-carte-tete' }, [
			options.selection ? el( 'label', { class: 'claira-qcm-choix', title: 'Ajouter à la sélection' }, [
				el( 'input', { type: 'checkbox', 'data-id': q.id, checked: options.selection.has( q.id ), onchange: function ( e ) { options.onSelect( q.id, e.target.checked ); } } )
			] ) : null,
			el( 'span', { class: 'claira-qcm-theme', text: th.nom + ' · n° ' + q.n } )
		] );
		return el( 'article', { class: 'claira-qcm-carte' + ( options.selection && options.selection.has( q.id ) ? ' is-choisie' : '' ) }, [
			tete,
			el( 'p', { class: 'claira-qcm-question', html: surligne( q.q, res ) } ),
			reponses,
			el( 'p', { class: 'claira-qcm-source' }, [
				'Source : ' + q.src + ' — ',
				el( 'a', { href: th.pdf, target: '_blank', rel: 'noopener', text: 'quiz FFTDA (PDF)' } )
			] )
		] );
	}

	function zoneRecherche( moteur, conteneur, options ) {
		options = options || {};
		var champ = el( 'input', { type: 'search', class: 'claira-qcm-champ', placeholder: 'Mot(s) clé(s) ou question complète…', 'aria-label': 'Rechercher dans les questions' } );
		var selectTheme = el( 'select', { class: 'claira-qcm-select', 'aria-label': 'Thème' }, [ el( 'option', { value: '-1', text: 'Tous les thèmes (' + moteur.questions.length + ' questions)' } ) ] );
		moteur.themes.forEach( function ( t, k ) {
			var nb = moteur.questions.filter( function ( q ) { return q.t === k; } ).length;
			selectTheme.appendChild( el( 'option', { value: String( k ), text: t.nom + ' (' + nb + ')' } ) );
		} );
		var modeTous = el( 'input', { type: 'radio', name: 'claira-qcm-mode-' + Math.random().toString( 36 ).slice( 2 ), value: 'tous', checked: true } );
		var modeUn = el( 'input', { type: 'radio', name: modeTous.name, value: 'un' } );
		var bouton = el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: 'Afficher les résultats' } );
		var info = el( 'p', { class: 'claira-qcm-info', 'aria-live': 'polite' } );
		var resultats = el( 'div', { class: 'claira-qcm-resultats' } );
		var dernier = null;

		function lancer() {
			var texte = champ.value.trim(), theme = parseInt( selectTheme.value, 10 );
			if ( ! texte && theme < 0 ) {
				info.textContent = 'Saisissez un mot, ou choisissez un thème pour afficher toutes ses questions.';
				resultats.innerHTML = '';
				dernier = null;
				if ( options.onResultats ) { options.onResultats( [] ); }
				return;
			}
			var res = moteur.chercher( texte, { mode: modeUn.checked ? 'un' : 'tous', theme: theme } );
			dernier = res;
			afficher();
		}

		function afficher() {
			if ( ! dernier ) { return; }
			var res = dernier;
			resultats.innerHTML = '';
			var n = res.liste.length;
			info.textContent = n === 0
				? 'Aucune question trouvée. Essayez un autre mot, moins de mots, ou « Au moins un mot ».'
				: n + ' question' + ( n > 1 ? 's' : '' ) + ( res.phrase ? ' (les plus proches de votre question, par pertinence)' : '' ) + '.';
			var max = Math.min( n, 200 );
			for ( var k = 0; k < max; k++ ) {
				resultats.appendChild( carteQuestion( moteur, res.liste[ k ], res, options ) );
			}
			if ( n > max ) { resultats.appendChild( el( 'p', { class: 'claira-qcm-info', text: ( n - max ) + ' autres résultats : précisez la recherche.' } ) ); }
			if ( options.onResultats ) { options.onResultats( res.liste ); }
		}

		bouton.addEventListener( 'click', lancer );
		champ.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); lancer(); } } );
		selectTheme.addEventListener( 'change', lancer );
		modeTous.addEventListener( 'change', lancer );
		modeUn.addEventListener( 'change', lancer );

		conteneur.appendChild( el( 'div', { class: 'claira-qcm-barre' }, [
			el( 'div', { class: 'claira-qcm-ligne' }, [ champ, bouton ] ),
			el( 'div', { class: 'claira-qcm-ligne claira-qcm-options' }, [
				selectTheme,
				el( 'label', {}, [ modeTous, ' Tous les mots' ] ),
				el( 'label', {}, [ modeUn, ' Au moins un mot' ] ),
				el( 'span', { class: 'claira-qcm-aide', text: 'Astuce : "entre guillemets" pour une expression exacte.' } )
			] )
		] ) );
		conteneur.appendChild( info );
		conteneur.appendChild( resultats );
		return { rafraichir: afficher, champ: champ };
	}

	/* ------------------------------------------------------------------
	 * Examen (en ligne) : une question par écran, chronomètre, correction
	 * ---------------------------------------------------------------- */

	// Prépare des questions avec réponses mélangées : { q, ordre: [indices d'origine] }.
	function preparer( liste, melangerReponses ) {
		return liste.map( function ( q ) {
			return { q: q, ordre: melangerReponses ? melanger( [ 0, 1, 2, 3 ] ) : [ 0, 1, 2, 3 ] };
		} );
	}

	function lancerExamen( moteur, conteneur, items, minutes, titre, surFin ) {
		var reponses = new Array( items.length ).fill( -1 );
		var courant = 0, fin = Date.now() + minutes * 60000, minuteur = null, termine = false;
		conteneur.innerHTML = '';

		var chrono = el( 'span', { class: 'claira-qcm-chrono', role: 'timer' } );
		var compteur = el( 'span', { class: 'claira-qcm-compteur' } );
		var zone = el( 'div', { class: 'claira-qcm-ecran' } );
		var grille = el( 'div', { class: 'claira-qcm-grille', 'aria-label': 'Aller à la question' } );
		var prec = el( 'button', { type: 'button', class: 'claira-qcm-btn', text: '◀ Précédente' } );
		var suiv = el( 'button', { type: 'button', class: 'claira-qcm-btn', text: 'Suivante ▶' } );
		var rendre = el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: 'Terminer et voir la correction' } );

		conteneur.appendChild( el( 'div', { class: 'claira-qcm-examen' }, [
			el( 'div', { class: 'claira-qcm-examen-tete' }, [ el( 'strong', { text: titre } ), compteur, chrono ] ),
			zone,
			el( 'div', { class: 'claira-qcm-nav' }, [ prec, suiv, rendre ] ),
			grille
		] ) );

		items.forEach( function ( _, k ) {
			grille.appendChild( el( 'button', { type: 'button', text: String( k + 1 ), onclick: function () { courant = k; montrer(); } } ) );
		} );

		function montrer() {
			var it = items[ courant ];
			zone.innerHTML = '';
			compteur.textContent = 'Question ' + ( courant + 1 ) + ' / ' + items.length;
			zone.appendChild( el( 'p', { class: 'claira-qcm-question', text: it.q.q } ) );
			var liste = el( 'div', { class: 'claira-qcm-propositions', role: 'radiogroup' } );
			it.ordre.forEach( function ( orig, pos ) {
				liste.appendChild( el( 'button', {
					type: 'button', role: 'radio', 'aria-checked': reponses[ courant ] === orig ? 'true' : 'false',
					class: 'claira-qcm-proposition' + ( reponses[ courant ] === orig ? ' is-coche' : '' ),
					onclick: function () {
						reponses[ courant ] = orig;
						majGrille();
						if ( courant < items.length - 1 ) { courant++; }
						montrer();
					}
				}, [ el( 'span', { class: 'claira-qcm-lettre', text: LETTRES[ pos ] } ), ' ' + it.q.r[ orig ] ] ) );
			} );
			zone.appendChild( liste );
			prec.disabled = courant === 0;
			suiv.disabled = courant === items.length - 1;
			Array.prototype.forEach.call( grille.children, function ( b, k ) { b.classList.toggle( 'is-courante', k === courant ); } );
		}

		function majGrille() {
			Array.prototype.forEach.call( grille.children, function ( b, k ) { b.classList.toggle( 'is-repondue', reponses[ k ] !== -1 ); } );
		}

		function tic() {
			var reste = Math.max( 0, Math.round( ( fin - Date.now() ) / 1000 ) );
			chrono.textContent = '⏱ ' + Math.floor( reste / 60 ) + ':' + String( reste % 60 ).padStart( 2, '0' );
			chrono.classList.toggle( 'is-urgent', reste <= 60 );
			if ( reste === 0 ) { terminer( true ); }
		}

		function terminer( tempsEcoule ) {
			if ( termine ) { return; }
			var manque = reponses.filter( function ( r ) { return r === -1; } ).length;
			if ( ! tempsEcoule && manque && ! window.confirm( manque + ' question(s) sans réponse. Terminer quand même ?' ) ) { return; }
			termine = true;
			clearInterval( minuteur );
			afficherCorrection( moteur, conteneur, items, reponses, titre, tempsEcoule, Math.round( ( minutes * 60000 - Math.max( 0, fin - Date.now() ) ) / 1000 ), surFin );
		}

		prec.addEventListener( 'click', function () { if ( courant > 0 ) { courant--; montrer(); } } );
		suiv.addEventListener( 'click', function () { if ( courant < items.length - 1 ) { courant++; montrer(); } } );
		rendre.addEventListener( 'click', function () { terminer( false ); } );
		montrer();
		tic();
		minuteur = setInterval( tic, 1000 );
		conteneur.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function afficherCorrection( moteur, conteneur, items, reponses, titre, tempsEcoule, secondes, surFin ) {
		var bons = items.filter( function ( it, k ) { return reponses[ k ] === it.q.ok; } ).length;
		var pct = Math.round( 100 * bons / items.length );
		conteneur.innerHTML = '';
		var bilan = el( 'div', { class: 'claira-qcm-bilan ' + ( pct >= 50 ? 'is-reussi' : 'is-echec' ) }, [
			el( 'p', { class: 'claira-qcm-score', text: bons + ' / ' + items.length } ),
			el( 'p', { text: pct + ' % de bonnes réponses' + ( tempsEcoule ? ' — temps écoulé' : ' — en ' + Math.floor( secondes / 60 ) + ' min ' + ( secondes % 60 ) + ' s' ) } )
		] );
		conteneur.appendChild( el( 'h3', { text: 'Correction : ' + titre } ) );
		conteneur.appendChild( bilan );

		var erreurs = el( 'div', { class: 'claira-qcm-resultats' } );
		items.forEach( function ( it, k ) {
			var q = it.q, juste = reponses[ k ] === q.ok;
			var liste = el( 'ol', { class: 'claira-qcm-reponses' } );
			it.ordre.forEach( function ( orig, pos ) {
				var cls = orig === q.ok ? 'is-bonne' : ( orig === reponses[ k ] ? 'is-fausse' : '' );
				liste.appendChild( el( 'li', { class: cls }, [ el( 'span', { class: 'claira-qcm-lettre', text: LETTRES[ pos ] } ), ' ' + q.r[ orig ] + ( orig === q.ok ? ' ✔' : ( orig === reponses[ k ] ? ' ✘ (votre réponse)' : '' ) ) ] ) );
			} );
			erreurs.appendChild( el( 'article', { class: 'claira-qcm-carte ' + ( juste ? 'is-juste' : 'is-erreur' ) }, [
				el( 'div', { class: 'claira-qcm-carte-tete' }, [
					el( 'span', { class: 'claira-qcm-theme', text: ( k + 1 ) + '. ' + moteur.themes[ q.t ].nom } ),
					el( 'span', { class: 'claira-qcm-verdict', text: juste ? 'Juste' : ( reponses[ k ] === -1 ? 'Sans réponse' : 'Faux' ) } )
				] ),
				el( 'p', { class: 'claira-qcm-question', text: q.q } ),
				liste,
				el( 'p', { class: 'claira-qcm-source', text: 'Source : ' + q.src } )
			] ) );
		} );

		var voirTout = el( 'label', { class: 'claira-qcm-filtre' }, [
			el( 'input', { type: 'checkbox', onchange: function ( e ) { erreurs.classList.toggle( 'is-erreurs-seules', ! e.target.checked ); } } ),
			' Afficher aussi les questions justes'
		] );
		erreurs.classList.add( 'is-erreurs-seules' );
		conteneur.appendChild( voirTout );
		conteneur.appendChild( erreurs );
		conteneur.appendChild( el( 'div', { class: 'claira-qcm-nav' }, [
			el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: 'Nouvel essai', onclick: surFin } )
		] ) );
		if ( surFin.enregistrer ) { surFin.enregistrer( items, reponses ); }
		conteneur.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	/* ------------------------------------------------------------------
	 * Révision par thème (réponse immédiate)
	 * ---------------------------------------------------------------- */

	var CLE_RATEES = 'claira-qcm-ratees';

	function revision( moteur, conteneur ) {
		conteneur.innerHTML = '';
		var ratees = stockage.lire( CLE_RATEES, [] );
		var choix = el( 'select', { class: 'claira-qcm-select', 'aria-label': 'Thème à réviser' } );
		moteur.themes.forEach( function ( t, k ) { choix.appendChild( el( 'option', { value: String( k ), text: t.nom } ) ); } );
		var optRatees = el( 'option', { value: 'ratees', text: 'Mes questions ratées (' + ratees.length + ')' } );
		choix.appendChild( optRatees );
		var ordre = el( 'input', { type: 'checkbox', checked: true } );
		var zone = el( 'div', { class: 'claira-qcm-ecran' } );
		conteneur.appendChild( el( 'p', { class: 'claira-qcm-info', text: 'Choisissez un thème : chaque réponse est corrigée tout de suite. Les questions ratées sont gardées sur cet appareil pour les revoir.' } ) );
		conteneur.appendChild( el( 'div', { class: 'claira-qcm-ligne claira-qcm-options' }, [
			choix,
			el( 'label', {}, [ ordre, ' Ordre aléatoire' ] ),
			el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: 'Commencer', onclick: commencer } )
		] ) );
		conteneur.appendChild( zone );

		var serie = [], k = 0, bons = 0;

		function commencer() {
			ratees = stockage.lire( CLE_RATEES, [] );
			var liste = choix.value === 'ratees'
				? ratees.map( function ( id ) { return moteur.parId[ id ]; } ).filter( Boolean )
				: moteur.questions.filter( function ( q ) { return q.t === parseInt( choix.value, 10 ); } );
			if ( ! liste.length ) { zone.innerHTML = ''; zone.appendChild( el( 'p', { class: 'claira-qcm-info', text: 'Aucune question à revoir pour le moment.' } ) ); return; }
			serie = preparer( ordre.checked ? melanger( liste ) : liste, true );
			k = 0; bons = 0;
			montrer();
		}

		function noter( id, juste ) {
			var t = stockage.lire( CLE_RATEES, [] ).filter( function ( x ) { return x !== id; } );
			if ( ! juste ) { t.push( id ); }
			stockage.ecrire( CLE_RATEES, t );
			optRatees.textContent = 'Mes questions ratées (' + t.length + ')';
		}

		function montrer() {
			zone.innerHTML = '';
			if ( k >= serie.length ) {
				zone.appendChild( el( 'div', { class: 'claira-qcm-bilan ' + ( bons * 2 >= serie.length ? 'is-reussi' : 'is-echec' ) }, [
					el( 'p', { class: 'claira-qcm-score', text: bons + ' / ' + serie.length } ),
					el( 'p', { text: 'Série terminée.' } )
				] ) );
				zone.appendChild( el( 'button', { type: 'button', class: 'claira-qcm-btn', text: 'Recommencer', onclick: commencer } ) );
				return;
			}
			var it = serie[ k ], q = it.q, repondu = false;
			zone.appendChild( el( 'p', { class: 'claira-qcm-compteur', text: 'Question ' + ( k + 1 ) + ' / ' + serie.length + ' · ' + bons + ' juste(s)' } ) );
			zone.appendChild( el( 'p', { class: 'claira-qcm-question', text: q.q } ) );
			var liste = el( 'div', { class: 'claira-qcm-propositions' } );
			var suite = el( 'div', { class: 'claira-qcm-nav' } );
			it.ordre.forEach( function ( orig, pos ) {
				liste.appendChild( el( 'button', {
					type: 'button', class: 'claira-qcm-proposition', 'data-orig': String( orig ),
					onclick: function () {
						if ( repondu ) { return; }
						repondu = true;
						var juste = orig === q.ok;
						if ( juste ) { bons++; }
						noter( q.id, juste );
						Array.prototype.forEach.call( liste.children, function ( b ) {
							var o = parseInt( b.getAttribute( 'data-orig' ), 10 );
							if ( o === q.ok ) { b.classList.add( 'is-bonne' ); }
							else if ( o === orig ) { b.classList.add( 'is-fausse' ); }
							b.disabled = true;
						} );
						suite.appendChild( el( 'p', { class: 'claira-qcm-source', text: ( juste ? '✔ Juste. ' : '✘ Faux. ' ) + 'Source : ' + q.src } ) );
						suite.appendChild( el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: 'Question suivante ▶', onclick: function () { k++; montrer(); } } ) );
					}
				}, [ el( 'span', { class: 'claira-qcm-lettre', text: LETTRES[ pos ] } ), ' ' + q.r[ orig ] ] ) );
			} );
			zone.appendChild( liste );
			zone.appendChild( suite );
		}
	}

	/* ------------------------------------------------------------------
	 * Tableau de bord : sélection, tirage, impression, projection
	 * ---------------------------------------------------------------- */

	var CLE_SELECTION = 'claira-qcm-selection-admin';

	function tableauDeBord( moteur, racineEl ) {
		var selection = new Set( stockage.lire( CLE_SELECTION, [] ).filter( function ( id ) { return moteur.parId[ id ]; } ) );
		var derniersResultats = [];
		var app = racineEl.querySelector( '.claira-qcm-app' );
		app.innerHTML = '';

		var colRecherche = el( 'div', { class: 'claira-qcm-col-recherche' } );
		var panneau = el( 'aside', { class: 'claira-qcm-panneau' } );
		app.appendChild( el( 'div', { class: 'claira-qcm-admin-grille' }, [ colRecherche, panneau ] ) );

		function sauver() { stockage.ecrire( CLE_SELECTION, Array.from( selection ) ); majPanneau(); }
		function basculer( id, oui ) {
			if ( oui ) { selection.add( id ); } else { selection.delete( id ); }
			var c = app.querySelector( 'input[data-id="' + id + '"]' );
			if ( c ) { c.closest( '.claira-qcm-carte' ).classList.toggle( 'is-choisie', oui ); }
			sauver();
		}

		var recherche = zoneRecherche( moteur, colRecherche, {
			selection: selection,
			onSelect: basculer,
			onResultats: function ( l ) { derniersResultats = l; majPanneau(); }
		} );

		// --- Panneau de sélection ---
		var nbSel = el( 'p', { class: 'claira-qcm-nb' } );
		var btnToutRes = el( 'button', { type: 'button', class: 'button', text: 'Ajouter tous les résultats' } );
		var btnVider = el( 'button', { type: 'button', class: 'button', text: 'Vider' } );
		var melangeRep = el( 'input', { type: 'checkbox', checked: true } );
		var melangeQ = el( 'input', { type: 'checkbox' } );
		var titre = el( 'input', { type: 'text', class: 'widefat', placeholder: 'Titre (ex. Examen blanc 1er Dan)' } );
		var duree = el( 'input', { type: 'number', min: '1', max: '120', value: '15', class: 'small-text' } );

		var nbTirage = el( 'input', { type: 'number', min: '1', max: '200', value: '30', class: 'small-text' } );
		var themesTirage = el( 'div', { class: 'claira-qcm-themes-tirage' } );
		moteur.themes.forEach( function ( t, k ) {
			themesTirage.appendChild( el( 'label', {}, [ el( 'input', { type: 'checkbox', value: String( k ), checked: true } ), ' ' + t.nom ] ) );
		} );

		btnToutRes.addEventListener( 'click', function () {
			derniersResultats.forEach( function ( q ) { selection.add( q.id ); } );
			sauver(); recherche.rafraichir();
		} );
		btnVider.addEventListener( 'click', function () {
			if ( selection.size && ! window.confirm( 'Vider la sélection de ' + selection.size + ' questions ?' ) ) { return; }
			selection.clear(); sauver(); recherche.rafraichir();
		} );

		function questionsChoisies() {
			var l = Array.from( selection ).map( function ( id ) { return moteur.parId[ id ]; } ).filter( Boolean );
			return melangeQ.checked ? melanger( l ) : l;
		}
		function exiger() {
			if ( ! selection.size ) { window.alert( 'Sélectionnez d’abord des questions (cases à cocher) ou faites un tirage au sort.' ); return false; }
			return true;
		}

		function tirer() {
			var ths = Array.prototype.filter.call( themesTirage.querySelectorAll( 'input' ), function ( c ) { return c.checked; } ).map( function ( c ) { return parseInt( c.value, 10 ); } );
			var pool = moteur.questions.filter( function ( q ) { return ths.indexOf( q.t ) !== -1; } );
			var n = Math.max( 1, Math.min( parseInt( nbTirage.value, 10 ) || 30, pool.length ) );
			if ( ! pool.length ) { window.alert( 'Cochez au moins un thème.' ); return; }
			if ( selection.size && ! window.confirm( 'Remplacer la sélection actuelle (' + selection.size + ' questions) par ' + n + ' questions tirées au sort ?' ) ) { return; }
			selection.clear();
			melanger( pool ).slice( 0, n ).forEach( function ( q ) { selection.add( q.id ); } );
			sauver(); recherche.rafraichir();
		}

		function voirSelection() {
			var res = { liste: Array.from( selection ).map( function ( id ) { return moteur.parId[ id ]; } ).filter( Boolean ) };
			recherche.champ.value = '';
			var r = colRecherche.querySelector( '.claira-qcm-resultats' );
			var info = colRecherche.querySelector( '.claira-qcm-info' );
			r.innerHTML = '';
			info.textContent = 'Sélection : ' + res.liste.length + ' question(s).';
			res.liste.forEach( function ( q ) { r.appendChild( carteQuestion( moteur, q, null, { selection: selection, onSelect: basculer } ) ); } );
		}

		function enregistrer() {
			if ( ! exiger() ) { return; }
			var f = document.getElementById( 'claira-qcm-form-enregistrer' );
			if ( ! f ) { return; }
			f.elements.ids.value = Array.from( selection ).join( ',' );
			f.elements.titre.value = titre.value.trim();
			f.elements.duree.value = duree.value;
			f.submit();
		}

		function majPanneau() {
			nbSel.textContent = selection.size + ' question' + ( selection.size > 1 ? 's' : '' ) + ' sélectionnée' + ( selection.size > 1 ? 's' : '' );
			btnToutRes.disabled = ! derniersResultats.length;
		}

		panneau.appendChild( el( 'h2', { text: 'Composer un examen blanc' } ) );
		panneau.appendChild( nbSel );
		panneau.appendChild( el( 'p', { class: 'claira-qcm-boutons' }, [
			el( 'button', { type: 'button', class: 'button', text: 'Voir la sélection', onclick: voirSelection } ), btnToutRes, btnVider
		] ) );
		panneau.appendChild( el( 'details', { class: 'claira-qcm-tirage' }, [
			el( 'summary', { text: 'Tirage au sort' } ),
			el( 'p', {}, [ 'Nombre de questions : ', nbTirage, ' ',
				el( 'button', { type: 'button', class: 'button', text: '30', onclick: function () { nbTirage.value = 30; } } ), ' ',
				el( 'button', { type: 'button', class: 'button', text: '15 (5e-6e Dan)', onclick: function () { nbTirage.value = 15; } } ) ] ),
			el( 'p', {}, [
				el( 'button', { type: 'button', class: 'button-link', text: 'Tout cocher', onclick: function () { themesTirage.querySelectorAll( 'input' ).forEach( function ( c ) { c.checked = true; } ); } } ), ' · ',
				el( 'button', { type: 'button', class: 'button-link', text: 'Tout décocher', onclick: function () { themesTirage.querySelectorAll( 'input' ).forEach( function ( c ) { c.checked = false; } ); } } )
			] ),
			themesTirage,
			el( 'p', {}, [ el( 'button', { type: 'button', class: 'button button-secondary', text: 'Tirer au sort', onclick: tirer } ) ] )
		] ) );
		panneau.appendChild( el( 'h3', { text: 'Options' } ) );
		panneau.appendChild( el( 'p', {}, [ el( 'label', {}, [ melangeRep, ' Mélanger l’ordre des réponses' ] ) ] ) );
		panneau.appendChild( el( 'p', {}, [ el( 'label', {}, [ melangeQ, ' Mélanger l’ordre des questions' ] ) ] ) );
		panneau.appendChild( el( 'p', {}, [ titre ] ) );
		panneau.appendChild( el( 'p', {}, [ 'Durée : ', duree, ' min' ] ) );
		panneau.appendChild( el( 'h3', { text: 'Faire passer l’examen' } ) );
		panneau.appendChild( el( 'div', { class: 'claira-qcm-formes' }, [
			el( 'button', { type: 'button', class: 'button button-primary', text: '🖨 Imprimer le sujet', onclick: function () { if ( exiger() ) { imprimer( false ); } } } ),
			el( 'button', { type: 'button', class: 'button', text: '🖨 Imprimer sujet + corrigé', onclick: function () { if ( exiger() ) { imprimer( true ); } } } ),
			el( 'button', { type: 'button', class: 'button', text: '📽 Projeter (en salle)', onclick: function () { if ( exiger() ) { projeter( moteur, preparer( questionsChoisies(), melangeRep.checked ), titreOuDefaut(), parseInt( duree.value, 10 ) || 15 ); } } } ),
			el( 'button', { type: 'button', class: 'button', text: '⏱ Tester ici en ligne', onclick: function () { if ( exiger() ) { testerIci(); } } } ),
			el( 'button', { type: 'button', class: 'button', text: '🔗 Enregistrer comme examen en ligne', onclick: enregistrer } )
		] ) );
		panneau.appendChild( el( 'p', { class: 'description', text: 'Le lien d’un examen enregistré ouvre la même série sur la page du site qui contient [claira_qcm_dan], avec le chronomètre et la correction.' } ) );

		function titreOuDefaut() { return titre.value.trim() || 'Examen blanc — QCM Dan'; }

		function testerIci() {
			var zone = el( 'div', { class: 'claira-qcm claira-qcm-test' } );
			app.innerHTML = '';
			app.appendChild( zone );
			var retour = function () { tableauDeBord( moteur, racineEl ); };
			lancerExamen( moteur, zone, preparer( questionsChoisies(), melangeRep.checked ), parseInt( duree.value, 10 ) || 15, titreOuDefaut(), retour );
		}

		function imprimer( avecCorrige ) {
			var items = preparer( questionsChoisies(), melangeRep.checked );
			var t = titreOuDefaut(), h = '';
			h += '<div class="claira-qcm-feuille"><h1>' + echap( t ) + '</h1>';
			h += '<p class="claira-qcm-entete">Nom : ………………………………… Prénom : ………………………… Date : ……/……/…………  Durée : ' + echap( duree.value ) + ' min — ' + items.length + ' questions — une seule réponse par question.</p>';
			h += '<ol class="claira-qcm-sujet">';
			items.forEach( function ( it ) {
				h += '<li><p>' + echap( it.q.q ) + '</p><ul>';
				it.ordre.forEach( function ( orig, pos ) { h += '<li>☐ <b>' + LETTRES[ pos ] + '</b> ' + echap( it.q.r[ orig ] ) + '</li>'; } );
				h += '</ul></li>';
			} );
			h += '</ol></div>';
			if ( avecCorrige ) {
				h += '<div class="claira-qcm-feuille claira-qcm-saut"><h1>Corrigé — ' + echap( t ) + '</h1><table class="claira-qcm-grille-corrige"><tr>';
				items.forEach( function ( it, k ) {
					h += '<td><b>' + ( k + 1 ) + '</b> ' + LETTRES[ it.ordre.indexOf( it.q.ok ) ] + '</td>';
					if ( ( k + 1 ) % 10 === 0 && k + 1 < items.length ) { h += '</tr><tr>'; }
				} );
				h += '</tr></table><ol class="claira-qcm-sujet">';
				items.forEach( function ( it ) {
					h += '<li><p>' + echap( it.q.q ) + '</p><p><b>' + LETTRES[ it.ordre.indexOf( it.q.ok ) ] + '</b> ' + echap( it.q.r[ it.q.ok ] ) + '<br><small>' + echap( moteur.themes[ it.q.t ].nom ) + ' — ' + echap( it.q.src ) + '</small></p></li>';
				} );
				h += '</ol></div>';
			}
			var zone = document.getElementById( 'claira-qcm-impression' ) || document.body.appendChild( el( 'div', { id: 'claira-qcm-impression' } ) );
			zone.innerHTML = h;
			document.body.classList.add( 'claira-qcm-imprime' );
			var fini = function () { document.body.classList.remove( 'claira-qcm-imprime' ); window.removeEventListener( 'afterprint', fini ); };
			window.addEventListener( 'afterprint', fini );
			window.print();
		}

		// Recharger la sélection d'un examen enregistré.
		document.querySelectorAll( '.claira-qcm-charger' ).forEach( function ( b ) {
			b.onclick = function () {
				selection.clear();
				b.getAttribute( 'data-ids' ).split( ',' ).forEach( function ( id ) { if ( moteur.parId[ id ] ) { selection.add( id ); } } );
				sauver(); voirSelection();
				racineEl.scrollIntoView( { behavior: 'smooth' } );
			};
		} );

		majPanneau();
		recherche.champ.focus();
	}

	/* Projection plein écran : une question à la fois, réponse révélée à la demande. */
	function projeter( moteur, items, titre, minutes ) {
		var k = 0, revele = false, fin = null, minuteur = null;
		var ecran = el( 'div', { class: 'claira-qcm-projection', tabindex: '-1' } );
		var chrono = el( 'button', { type: 'button', class: 'claira-qcm-chrono', title: 'Démarrer / remettre à zéro le chronomètre', text: '⏱ ' + minutes + ':00' } );
		var corps = el( 'div', { class: 'claira-qcm-projection-corps' } );
		var pied = el( 'div', { class: 'claira-qcm-projection-pied' } );
		ecran.appendChild( el( 'div', { class: 'claira-qcm-projection-tete' }, [ el( 'span', { text: titre } ), chrono,
			el( 'button', { type: 'button', class: 'claira-qcm-fermer', text: '✕', title: 'Fermer (Échap)', onclick: fermer } ) ] ) );
		ecran.appendChild( corps );
		ecran.appendChild( pied );
		document.body.appendChild( ecran );
		if ( ecran.requestFullscreen ) { ecran.requestFullscreen().catch( function () {} ); }
		ecran.focus();

		chrono.addEventListener( 'click', function () {
			if ( minuteur ) { clearInterval( minuteur ); minuteur = null; chrono.textContent = '⏱ ' + minutes + ':00'; return; }
			fin = Date.now() + minutes * 60000;
			minuteur = setInterval( function () {
				var r = Math.max( 0, Math.round( ( fin - Date.now() ) / 1000 ) );
				chrono.textContent = '⏱ ' + Math.floor( r / 60 ) + ':' + String( r % 60 ).padStart( 2, '0' );
				chrono.classList.toggle( 'is-urgent', r <= 60 );
				if ( ! r ) { clearInterval( minuteur ); minuteur = null; }
			}, 500 );
		} );

		function montrer() {
			var it = items[ k ];
			corps.innerHTML = '';
			corps.appendChild( el( 'p', { class: 'claira-qcm-projection-num', text: 'Question ' + ( k + 1 ) + ' / ' + items.length } ) );
			corps.appendChild( el( 'p', { class: 'claira-qcm-projection-question', text: it.q.q } ) );
			var l = el( 'ol', { class: 'claira-qcm-projection-reponses' } );
			it.ordre.forEach( function ( orig, pos ) {
				l.appendChild( el( 'li', { class: revele && orig === it.q.ok ? 'is-bonne' : ( revele ? 'is-pale' : '' ) }, [ el( 'b', { text: LETTRES[ pos ] } ), ' ' + it.q.r[ orig ] ] ) );
			} );
			corps.appendChild( l );
			if ( revele ) { corps.appendChild( el( 'p', { class: 'claira-qcm-projection-source', text: 'Source : ' + it.q.src } ) ); }
			pied.innerHTML = '';
			pied.appendChild( el( 'button', { type: 'button', class: 'claira-qcm-btn', text: '◀', disabled: k === 0, onclick: function () { aller( -1 ); } } ) );
			pied.appendChild( el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: revele ? 'Masquer la réponse' : 'Afficher la réponse (Espace)', onclick: basculer } ) );
			pied.appendChild( el( 'button', { type: 'button', class: 'claira-qcm-btn', text: '▶', disabled: k === items.length - 1, onclick: function () { aller( 1 ); } } ) );
		}
		function aller( d ) { k = Math.max( 0, Math.min( items.length - 1, k + d ) ); revele = false; montrer(); }
		function basculer() { revele = ! revele; montrer(); }
		function clavier( e ) {
			if ( e.key === 'ArrowRight' || e.key === 'PageDown' ) { aller( 1 ); }
			else if ( e.key === 'ArrowLeft' || e.key === 'PageUp' ) { aller( -1 ); }
			else if ( e.key === ' ' || e.key === 'r' || e.key === 'R' ) { e.preventDefault(); basculer(); }
			else if ( e.key === 'Escape' && ! document.fullscreenElement ) { fermer(); }
		}
		function fermer() {
			clearInterval( minuteur );
			document.removeEventListener( 'keydown', clavier );
			if ( document.fullscreenElement ) { document.exitFullscreen().catch( function () {} ); }
			ecran.remove();
		}
		document.addEventListener( 'keydown', clavier );
		montrer();
	}

	/* ------------------------------------------------------------------
	 * Site : onglets Rechercher / Examen blanc / Réviser par thème
	 * ---------------------------------------------------------------- */

	function site( moteur, racineEl, cfg ) {
		var app = racineEl.querySelector( '.claira-qcm-app' );
		app.innerHTML = '';

		// Examen enregistré (lien ?examen=CODE) : on lance directement l'écran d'accueil de cet examen.
		if ( cfg.examen ) {
			var items = cfg.examen.ids.map( function ( id ) { return moteur.parId[ id ]; } ).filter( Boolean );
			var zoneEx = el( 'div' );
			var accueil = function () {
				zoneEx.innerHTML = '';
				zoneEx.appendChild( el( 'div', { class: 'claira-qcm-accueil' }, [
					el( 'h3', { text: cfg.examen.titre } ),
					el( 'p', { text: items.length + ' questions — ' + cfg.examen.duree + ' minutes — une seule bonne réponse par question.' } ),
					el( 'p', { class: 'claira-qcm-info', text: 'Le chronomètre démarre au clic. À la fin du temps, la copie est corrigée automatiquement.' } ),
					el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: 'Commencer l’examen', onclick: function () {
						lancerExamen( moteur, zoneEx, preparer( items, true ), cfg.examen.duree, cfg.examen.titre, accueil );
					} } )
				] ) );
			};
			app.appendChild( zoneEx );
			accueil();
			return;
		}

		var onglets = [
			{ id: 'chercher', nom: 'Rechercher' },
			{ id: 'examen', nom: 'Examen blanc' },
			{ id: 'theme', nom: 'Réviser par thème' }
		];
		var barre = el( 'div', { class: 'claira-qcm-onglets', role: 'tablist' } );
		var panneaux = {};
		onglets.forEach( function ( o ) {
			barre.appendChild( el( 'button', { type: 'button', role: 'tab', 'data-onglet': o.id, text: o.nom, onclick: function () { ouvrir( o.id ); } } ) );
			panneaux[ o.id ] = el( 'div', { class: 'claira-qcm-panneau-onglet', role: 'tabpanel', hidden: true } );
		} );
		app.appendChild( barre );
		onglets.forEach( function ( o ) { app.appendChild( panneaux[ o.id ] ); } );

		zoneRecherche( moteur, panneaux.chercher );

		function accueilExamen() {
			var p = panneaux.examen;
			p.innerHTML = '';
			var nb = el( 'select', { class: 'claira-qcm-select' }, [
				el( 'option', { value: String( cfg.questions ), text: cfg.questions + ' questions (1er à 4e Dan)' } ),
				el( 'option', { value: '15', text: '15 questions (5e et 6e Dan)' } )
			] );
			p.appendChild( el( 'div', { class: 'claira-qcm-accueil' }, [
				el( 'p', { text: 'Comme le jour de l’épreuve sur IzziDan : questions tirées au hasard parmi les ' + moteur.questions.length + ' questions officielles, ' + cfg.duree + ' minutes, une seule bonne réponse par question.' } ),
				el( 'p', { class: 'claira-qcm-ligne' }, [ nb,
					el( 'button', { type: 'button', class: 'claira-qcm-btn claira-qcm-btn-principal', text: 'Lancer l’examen blanc', onclick: function () {
						var n = parseInt( nb.value, 10 );
						lancerExamen( moteur, p, preparer( melanger( moteur.questions ).slice( 0, n ), true ), cfg.duree, 'Examen blanc — ' + n + ' questions', accueilExamen );
					} } ) ] )
			] ) );
		}
		accueilExamen();
		revision( moteur, panneaux.theme );

		function ouvrir( id ) {
			Array.prototype.forEach.call( barre.children, function ( b ) {
				var oui = b.getAttribute( 'data-onglet' ) === id;
				b.classList.toggle( 'is-actif', oui );
				b.setAttribute( 'aria-selected', oui ? 'true' : 'false' );
			} );
			Object.keys( panneaux ).forEach( function ( k ) { panneaux[ k ].hidden = k !== id; } );
		}
		ouvrir( 'chercher' );
	}

	/* ------------------------------------------------------------------
	 * Démarrage
	 * ---------------------------------------------------------------- */

	function demarrer( racineEl ) {
		var cfg;
		try { cfg = JSON.parse( racineEl.getAttribute( 'data-config' ) ); } catch ( e ) { return; }
		// Questions fournies par data/qcm-dan.js (chargé juste avant ce fichier).
		var donnees = window.ClairaQcmDonnees;
		if ( ! donnees || ! donnees.questions ) {
			racineEl.querySelector( '.claira-qcm-app' ).innerHTML = '<p class="claira-qcm-info">Impossible de charger les questions. Rechargez la page.</p>';
			return;
		}
		var moteur = new Moteur( donnees );
		if ( cfg.mode === 'admin' ) { tableauDeBord( moteur, racineEl ); } else { site( moteur, racineEl, cfg ); }
	}

	function init() { document.querySelectorAll( '.claira-qcm[data-config]' ).forEach( demarrer ); }
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }

	// Exposé pour les tests.
	window.ClairaQcm = { Moteur: Moteur, normaliser: normaliser, racine: racine };
}() );
