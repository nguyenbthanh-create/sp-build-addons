/* TKD Gala — page Projection (écran salle / écran régie). Sans dépendance.
   Position -1 = écran d'accueil (titre du gala), 0..n-1 = lignes du conducteur.
   Clavier et télécommandes de présentation :
     suivant   → ↓ PageDown Espace Entrée      précédent ← ↑ PageUp Retour arrière
     accueil   Début (Home)                    écran noir B ou « . »        plein écran F
   Sur un même ordinateur, la régie et l'écran salle partagent leur position (BroadcastChannel). */
(function () {
	'use strict';
	var D = JSON.parse(document.getElementById('tkd-proj-donnees').textContent);
	var items = D.items || [];
	var app = document.getElementById('tkd-proj-app');
	var cleStockage = D.canal + '-etat';
	var etat = { index: -1, noir: false, depuis: Date.now() };
	var canal = 'BroadcastChannel' in window ? new BroadcastChannel(D.canal) : null;

	try {
		var lu = JSON.parse(localStorage.getItem(cleStockage) || 'null');
		if (lu && typeof lu.index === 'number' && lu.index >= -1 && lu.index < items.length) etat = lu;
	} catch (e) { /* stockage indisponible : on part de l'accueil */ }

	function el(tag, cls, txt) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		if (txt !== undefined && txt !== null && txt !== '') e.textContent = txt;
		return e;
	}
	function duree(s) { s = Math.max(0, Math.round(s)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
	function icone(it) { return it.type === 'ann' ? '📣' : (it.type === 'ext' ? '🌐' : '🥋'); }

	function changer(index, noir, diffuser) {
		index = Math.max(-1, Math.min(items.length - 1, index));
		var change = index !== etat.index;
		etat = { index: index, noir: !!noir, depuis: change ? Date.now() : etat.depuis };
		try { localStorage.setItem(cleStockage, JSON.stringify(etat)); } catch (e) { /* rien */ }
		if (diffuser !== false && canal) canal.postMessage({ etat: etat });
		rendre();
	}
	function suivant() { changer(etat.index + 1, false); }
	function precedent() { changer(etat.index - 1, false); }

	if (canal) {
		canal.onmessage = function (m) {
			var x = m.data || {};
			if (x.etat) { etat = x.etat; rendre(); }
			else if (x.demande && D.vue === 'regie') canal.postMessage({ etat: etat });
		};
		if (D.vue === 'salle') canal.postMessage({ demande: true });
	}

	// ─── Écran salle ──────────────────────────────────────────────────────────
	function diapo(it) {
		var d = el('div', 'tkd-proj-diapo');
		if (!it) {
			d.classList.add('tkd-proj-accueil');
			d.appendChild(el('div', 'tkd-proj-surtitre', D.date ? D.date : ''));
			d.appendChild(el('h1', 'tkd-proj-titre', D.titre));
			d.appendChild(el('div', 'tkd-proj-edition', D.edition));
			return d;
		}
		if (it.type === 'ann') {
			d.classList.add('tkd-proj-annonce');
			if (it.logo) { var img = el('img', 'tkd-proj-logo'); img.src = it.logo; img.alt = it.nom; d.appendChild(img); }
			else d.appendChild(el('h1', 'tkd-proj-titre', it.nom));
			if (it.slogan) d.appendChild(el('div', 'tkd-proj-slogan', it.slogan));
			return d;
		}
		d.classList.add('tkd-proj-demo');
		if (it.logo) { var lg = el('img', 'tkd-proj-logo-demo'); lg.src = it.logo; lg.alt = ''; d.appendChild(lg); }
		d.appendChild(el('div', 'tkd-proj-surtitre', it.discipline));
		d.appendChild(el('h1', 'tkd-proj-titre', it.nom));
		if (it.titre) d.appendChild(el('div', 'tkd-proj-sous', it.titre));
		return d;
	}

	function rendreSalle() {
		app.innerHTML = '';
		var scene = el('div', 'tkd-proj-scene' + (etat.noir ? ' tkd-proj-noir' : ''));
		if (!etat.noir) scene.appendChild(diapo(items[etat.index]));
		app.appendChild(scene);
	}

	// ─── Écran régie ──────────────────────────────────────────────────────────
	function carte(it, libelle, principal) {
		var c = el('section', 'tkd-proj-carte' + (principal ? ' tkd-proj-principal' : ''));
		c.appendChild(el('div', 'tkd-proj-libelle', libelle));
		if (!it) {
			c.appendChild(el('div', 'tkd-proj-nom', etat.index >= items.length - 1 && principal === false ? 'Fin du déroulé' : 'Accueil : ' + D.titre + ' ' + D.edition));
			return c;
		}
		var tete = el('div', 'tkd-proj-tete');
		tete.appendChild(el('span', 'tkd-proj-icone', icone(it)));
		if (it.heure) tete.appendChild(el('span', 'tkd-proj-heure', 'prévu ' + it.heure));
		tete.appendChild(el('span', 'tkd-proj-duree', duree(it.duree)));
		c.appendChild(tete);
		c.appendChild(el('div', 'tkd-proj-nom', it.nom));
		if (it.type === 'ann') {
			if (it.slogan) c.appendChild(el('div', 'tkd-proj-slogan-r', it.slogan));
			if (principal && it.message) c.appendChild(el('div', 'tkd-proj-message', it.message));
		} else {
			var info = [it.discipline, it.titre].filter(Boolean).join(' · ');
			if (info) c.appendChild(el('div', 'tkd-proj-info', info));
			if (principal) c.appendChild(el('div', 'tkd-proj-audio', it.audio ? '🎵 ' + it.audio : '⚠️ Pas de piste audio'));
		}
		if (principal) {
			var chrono = el('div', 'tkd-proj-chrono');
			chrono.appendChild(el('span', 'tkd-proj-ecoule', ''));
			var barre = el('div', 'tkd-proj-barre'); barre.appendChild(el('div', 'tkd-proj-jauge'));
			chrono.appendChild(barre);
			c.appendChild(chrono);
		}
		return c;
	}

	function bouton(txt, action, cls) {
		var b = el('button', 'tkd-proj-btn' + (cls ? ' ' + cls : ''), txt);
		b.type = 'button';
		b.addEventListener('click', action);
		return b;
	}

	function rendreRegie() {
		app.innerHTML = '';
		var barre = el('header', 'tkd-proj-barre-outils');
		barre.appendChild(bouton('◀ Précédent', precedent));
		barre.appendChild(bouton('Suivant ▶', suivant, 'tkd-proj-btn-principal'));
		barre.appendChild(bouton(etat.noir ? '☀️ Rallumer' : '⬛ Écran noir', function () { changer(etat.index, !etat.noir); }, etat.noir ? 'tkd-proj-btn-alerte' : ''));
		barre.appendChild(bouton('🏠 Accueil', function () { changer(-1, false); }));
		barre.appendChild(bouton('📽️ Ouvrir l\'écran salle', function () { window.open(D.url_salle, 'tkd-gala-salle'); }));
		barre.appendChild(bouton('⛶ Plein écran', pleinEcran));
		barre.appendChild(bouton('↻ Recharger le conducteur', function () { location.reload(); }));
		var lien = el('a', 'tkd-proj-retour', '← Conducteur'); lien.href = D.url_retour; barre.appendChild(lien);
		app.appendChild(barre);

		var pos = el('div', 'tkd-proj-position', (etat.index < 0 ? 'Accueil' : (etat.index + 1) + ' / ' + items.length) + (etat.noir ? ' · ÉCRAN NOIR' : ''));
		app.appendChild(pos);

		var grille = el('div', 'tkd-proj-grille');
		var gauche = el('div', 'tkd-proj-col');
		gauche.appendChild(carte(items[etat.index], 'En cours', true));
		gauche.appendChild(carte(items[etat.index + 1], 'À suivre', false));
		grille.appendChild(gauche);

		var liste = el('ol', 'tkd-proj-liste');
		items.forEach(function (it, i) {
			var li = el('li', i === etat.index ? 'tkd-proj-actif' : (i < etat.index ? 'tkd-proj-passe' : ''));
			li.appendChild(el('span', 'tkd-proj-l-heure', it.heure));
			li.appendChild(el('span', 'tkd-proj-l-icone', icone(it)));
			li.appendChild(el('span', 'tkd-proj-l-nom', it.nom));
			li.appendChild(el('span', 'tkd-proj-l-duree', duree(it.duree)));
			li.addEventListener('click', function () { changer(i, false); });
			liste.appendChild(li);
		});
		grille.appendChild(liste);
		app.appendChild(grille);
		// Seule la liste défile jusqu'au passage en cours (jamais la page : « En cours » reste visible).
		var actif = liste.querySelector('.tkd-proj-actif');
		if (actif) liste.scrollTop = Math.max(0, actif.offsetTop - liste.clientHeight / 3);
		tic();
	}

	function tic() {
		if (D.vue !== 'regie') return;
		var it = items[etat.index];
		var ec = app.querySelector('.tkd-proj-ecoule');
		var jauge = app.querySelector('.tkd-proj-jauge');
		if (!it || !ec) return;
		var s = (Date.now() - etat.depuis) / 1000;
		ec.textContent = duree(s) + ' / ' + duree(it.duree) + (it.duree && s > it.duree ? '  (+' + duree(s - it.duree) + ')' : '');
		ec.parentNode.classList.toggle('tkd-proj-depasse', !!it.duree && s > it.duree);
		if (jauge) jauge.style.width = (it.duree ? Math.min(100, s / it.duree * 100) : 0) + '%';
	}

	function rendre() { if (D.vue === 'salle') rendreSalle(); else rendreRegie(); }

	function pleinEcran() {
		var d = document;
		if (d.fullscreenElement) { if (d.exitFullscreen) d.exitFullscreen(); }
		else if (d.documentElement.requestFullscreen) d.documentElement.requestFullscreen();
	}

	document.addEventListener('keydown', function (e) {
		if (e.altKey || e.ctrlKey || e.metaKey) return;
		var k = e.key;
		if (k === 'ArrowRight' || k === 'ArrowDown' || k === 'PageDown' || k === ' ' || k === 'Enter') { e.preventDefault(); suivant(); }
		else if (k === 'ArrowLeft' || k === 'ArrowUp' || k === 'PageUp' || k === 'Backspace') { e.preventDefault(); precedent(); }
		else if (k === 'Home') { e.preventDefault(); changer(-1, false); }
		else if (k === 'b' || k === 'B' || k === '.') { e.preventDefault(); changer(etat.index, !etat.noir); }
		else if (k === 'f' || k === 'F') { e.preventDefault(); pleinEcran(); }
	});
	if (D.vue === 'salle') document.addEventListener('dblclick', pleinEcran);

	setInterval(tic, 500);
	rendre();
})();
