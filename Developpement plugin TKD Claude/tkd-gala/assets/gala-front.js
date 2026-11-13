/* TKD Gala : formulaire d'inscription — lignes de démonstrateurs, collage depuis un tableur,
   total des repas (une seule démonstration par club, sans ajout ni retrait). Sans dépendance. Le serveur refait
   tous les contrôles (Tkd_Gala_Front::valider()) : ce script ne fait que faciliter la saisie. */
(function () {
	'use strict';
	var form = document.querySelector('.tkd-gala-form');
	if (!form) return;

	var tbody   = form.querySelector('.tkd-gala-participants');
	var tplP    = form.querySelector('.tkd-gala-tpl-participant');
	var totalEl = form.querySelector('.tkd-gala-total');
	var collage = form.querySelector('.tkd-gala-collage');
	var prix    = parseFloat(form.getAttribute('data-prix-repas')) || 0;
	var compteur = 0;

	// Clé unique d'une nouvelle ligne (lettres et chiffres seulement, comme l'attend le serveur).
	function cle(prefixe) { compteur++; return prefixe + Date.now().toString(36) + compteur; }

	function depuisModele(tpl, conteneur) {
		var html = tpl.innerHTML.replace(/__i__/g, cle(conteneur === tbody ? 'p' : 'n'));
		var tmp  = document.createElement(conteneur === tbody ? 'tbody' : 'div');
		tmp.innerHTML = html;
		var el = tmp.firstElementChild;
		conteneur.appendChild(el);
		return el;
	}

	function euros(m) {
		return (Math.round(m * 100) / 100).toLocaleString('fr-FR', { minimumFractionDigits: m % 1 ? 2 : 0 }) + ' €';
	}

	// ── Équipe de plusieurs clubs (règlement, art. 2) ───────────────────────
	var minimum   = parseInt(form.getAttribute('data-min-participants'), 10) || 0;
	var blocAssoc = form.querySelector('.tkd-gala-associes');

	function enEquipe() {
		var r = form.querySelector('input[name="club[equipe]"]:checked');
		return !!r && r.value === '1';
	}

	// Clubs de l'équipe : le club référent puis les clubs associés renseignés (sans doublon).
	function clubs() {
		var liste = [], vus = {};
		var champs = [form.querySelector('input[name="club[nom]"]')];
		if (enEquipe()) champs = champs.concat([].slice.call(form.querySelectorAll('input[name="club[associes][]"]')));
		champs.forEach(function (c) {
			var v = c ? c.value.trim() : '';
			if (v && !vus[v.toLowerCase()]) { vus[v.toLowerCase()] = true; liste.push(v); }
		});
		return liste;
	}

	function remplirSelect(sel, liste) {
		var actuel = sel.value || sel.getAttribute('data-valeur') || '';
		sel.innerHTML = '';
		liste.forEach(function (c) {
			var o = document.createElement('option');
			o.value = c; o.textContent = c;
			if (c === actuel) o.selected = true;
			sel.appendChild(o);
		});
		sel.removeAttribute('data-valeur');
	}

	function majEquipe() {
		var equipe = enEquipe();
		form.classList.toggle('tkd-gala-en-equipe', equipe);
		if (blocAssoc) blocAssoc.hidden = !equipe;
		var liste = clubs();
		[].forEach.call(tbody.querySelectorAll('select[name$="[club]"]'), function (s) { remplirSelect(s, liste); });
		var selTpl = tplP.content ? tplP.content.querySelector('select[name$="[club]"]') : null;
		if (selTpl) remplirSelect(selTpl, liste);
	}

	function rafraichir() {
		var lignes = tbody.querySelectorAll('tr');
		var nb = 0, repas = 0, parClub = {};
		[].forEach.call(lignes, function (tr, i) {
			tr.querySelector('.tkd-gala-num').textContent = i + 1;
			var nom = tr.querySelector('input[name$="[nom]"]').value.trim();
			var pre = tr.querySelector('input[name$="[prenom]"]').value.trim();
			if (nom || pre) {
				nb++;
				if (tr.querySelector('input[name$="[repas]"]').checked) {
					repas++;
					var s = tr.querySelector('select[name$="[club]"]');
					var c = s && s.value ? s.value : '';
					parClub[c] = (parClub[c] || 0) + 1;
				}
			}
		});
		var txt = nb + ' démonstrateur' + (nb > 1 ? 's' : '') + ' · ' + repas + ' repas' + (repas ? ' · ' + euros(repas * prix) + ' à régler' : '');
		if (enEquipe() && repas) {
			txt += ' (' + Object.keys(parClub).map(function (c) { return (c || '?') + ' : ' + parClub[c]; }).join(' · ') + ')';
		}
		totalEl.textContent = txt;
		var alerte = form.querySelector('.tkd-gala-minimum');
		if (alerte) alerte.hidden = !(minimum && nb > 0 && nb < minimum);
		aCompleter();
	}

	// « À compléter plus tard » (13/11/2026) : dates de naissance et musique sont facultatives à
	// l'inscription ; on les rappelle sans bloquer l'envoi (rappel aussi par mail et relance).
	function aCompleter() {
		var bloc = form.querySelector('.tkd-gala-a-completer');
		if (!bloc) return;
		var ul = bloc.querySelector('ul');
		var items = [];
		var sansDate = 0;
		[].forEach.call(tbody.querySelectorAll('tr'), function (tr) {
			var nom = tr.querySelector('input[name$="[nom]"]').value.trim();
			var pre = tr.querySelector('input[name$="[prenom]"]').value.trim();
			if ((nom || pre) && !tr.querySelector('input[name$="[naissance]"]').value) sansDate++;
		});
		if (sansDate) items.push(sansDate + ' démonstrateur' + (sansDate > 1 ? 's' : '') + ' sans date de naissance');
		[].forEach.call(form.querySelectorAll('.tkd-gala-demo'), function (fs) {
			var f = fs.querySelector('input[type=file]');
			if (!fs.getAttribute('data-audio') && !(f && f.files && f.files.length)) items.push('piste audio pas encore envoyée');
		});
		ul.innerHTML = '';
		items.forEach(function (t) { var li = document.createElement('li'); li.textContent = t; ul.appendChild(li); });
		var lim = bloc.getAttribute('data-limite');
		if (items.length && lim) {
			var li = document.createElement('li');
			li.textContent = 'à ajouter avec votre lien personnel jusqu\'au ' + lim + ' inclus';
			li.className = 'tkd-gala-a-completer-limite';
			ul.appendChild(li);
		}
		bloc.hidden = !items.length;
	}

	function ligneVide(tr) {
		return [].every.call(tr.querySelectorAll('input[type=text], input[type=date]'), function (i) { return !i.value.trim(); });
	}

	// ── Collage depuis un tableur ────────────────────────────────────────────
	// Colonnes attendues : Nom, Prénom, Date de naissance, (Repas : oui / non).
	function dateIso(s) {
		s = (s || '').trim();
		var m = s.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2}|\d{4})$/);
		if (m) {
			var a = m[3];
			if (a.length === 2) a = (parseInt(a, 10) > new Date().getFullYear() % 100 ? '19' : '20') + a;
			return a + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
		}
		return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : '';
	}

	function importer(texte) {
		var lignes = texte.split(/\r?\n/).filter(function (l) { return l.trim(); });
		var ajout = 0, sansDate = 0;
		// Les lignes vides déjà présentes sont remplacées par le collage.
		[].forEach.call(tbody.querySelectorAll('tr'), function (tr) { if (ligneVide(tr)) tr.remove(); });
		lignes.forEach(function (l, n) {
			var c = l.split(l.indexOf('\t') !== -1 ? '\t' : ';').map(function (x) { return x.trim().replace(/^"|"$/g, ''); });
			if (n === 0 && /^nom$/i.test(c[0] || '')) return; // ligne d'en-tête
			if (!c[0] && !c[1]) return;
			var tr = depuisModele(tplP, tbody);
			tr.querySelector('input[name$="[nom]"]').value = c[0] || '';
			tr.querySelector('input[name$="[prenom]"]').value = c[1] || '';
			var d = dateIso(c[2]);
			tr.querySelector('input[name$="[naissance]"]').value = d;
			if (!d) sansDate++;
			tr.querySelector('input[name$="[repas]"]').checked = /^(o|oui|x|1|y|yes)/i.test(c[3] || '');
			ajout++;
		});
		if (!tbody.querySelector('tr')) depuisModele(tplP, tbody);
		rafraichir();
		return ajout + ' ligne' + (ajout > 1 ? 's' : '') + ' ajoutée' + (ajout > 1 ? 's' : '') + (sansDate ? ' — ' + sansDate + ' date(s) de naissance à compléter' : '') + '.';
	}

	// ── Boutons ──────────────────────────────────────────────────────────────
	form.addEventListener('click', function (e) {
		var b = e.target.closest('[data-gala]');
		if (!b) return;
		var quoi = b.getAttribute('data-gala');

		if (quoi === 'ajout-participant') {
			depuisModele(tplP, tbody).querySelector('input').focus();
		} else if (quoi === 'suppr-participant') {
			b.closest('tr').remove();
			if (!tbody.querySelector('tr')) depuisModele(tplP, tbody);
		} else if (quoi === 'ouvrir-collage') {
			collage.hidden = false;
			collage.querySelector('textarea').focus();
		} else if (quoi === 'fermer-collage') {
			collage.hidden = true;
		} else if (quoi === 'importer-collage') {
			var ta = collage.querySelector('textarea');
			collage.querySelector('.tkd-gala-collage-msg').textContent = importer(ta.value);
			ta.value = '';
		}
		rafraichir();
	});

	form.addEventListener('input', function (e) {
		var n = e.target && e.target.name;
		if (n === 'club[nom]' || n === 'club[associes][]') majEquipe();
		rafraichir();
	});
	form.addEventListener('change', function (e) {
		if (e.target && e.target.name === 'club[equipe]') majEquipe();
		rafraichir();
	});

	// Coller directement dans la première cellule du tableau : même import que le bouton.
	tbody.addEventListener('paste', function (e) {
		var t = (e.clipboardData || window.clipboardData).getData('text');
		if (t && (t.indexOf('\t') !== -1 || t.indexOf('\n') !== -1)) {
			e.preventDefault();
			importer(t);
		}
	});

	// Double envoi évité (pistes audio lourdes : l'envoi peut prendre du temps).
	form.addEventListener('submit', function () {
		var btn = form.querySelector('.tkd-gala-btn-principal');
		btn.disabled = true;
		btn.setAttribute('data-texte', btn.getAttribute('data-texte') || btn.textContent);
		btn.textContent = 'Envoi en cours…';
	});
	// Retour arrière du navigateur (page restaurée depuis son cache) : bouton réactivé.
	window.addEventListener('pageshow', function () {
		var btn = form.querySelector('.tkd-gala-btn-principal');
		btn.disabled = false;
		if (btn.getAttribute('data-texte')) btn.textContent = btn.getAttribute('data-texte');
	});

	majEquipe();
	rafraichir();
	var alerte = document.querySelector('.tkd-gala .tkd-gala-ko, .tkd-gala .tkd-gala-ok');
	if (alerte && alerte.scrollIntoView) alerte.scrollIntoView({ block: 'start' });
})();
