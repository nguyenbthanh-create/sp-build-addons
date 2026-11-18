/* TKD Projets — onglet Gala : chiffres de l'édition reliée (lecture seule, totaux seulement). Étape 5, 18/11/2026. */
(function () {
	'use strict';

	function euros(n) {
		return (Number(n) || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
	}

	function duree(s) {
		const m = Math.floor((s || 0) / 60);
		return m >= 60 ? Math.floor(m / 60) + ' h ' + String(m % 60).padStart(2, '0') : m + ' min';
	}

	/**
	 * @param {HTMLElement} el
	 * @param {Object}      project
	 * @param {Object}      ctx { api, esc, fail, fmtDate }
	 */
	async function mount(el, project, ctx) {
		if (!el) {
			return;
		}
		const esc = ctx.esc;
		el.innerHTML = '<p>Chargement des chiffres du gala…</p>';
		let g;
		try {
			g = await ctx.api('/projects/' + project.id + '/gala');
		} catch (e) {
			el.innerHTML = '<div class="notice notice-error inline"><p>' + esc(e.message || 'Chiffres du gala indisponibles.') + '</p></div>';
			return;
		}
		const carte = (valeur, libelle, alerte) => '<div class="tkdp-gala-card' + (alerte ? ' is-alert' : '') + '"><strong>' + esc(valeur) + '</strong><span>' + esc(libelle) + '</span></div>';
		let html = '<p class="description">' + esc(g.titre) + ' — édition ' + esc(g.edition)
			+ (g.edition_en_cours ? '' : ' (ce n\'est plus l\'édition en cours dans TKD Gala : les données ont pu être supprimées après le gala)') + '.'
			+ ' Chiffres lus dans l\'extension TKD Gala, sans aucune donnée personnelle.</p>';
		if (g.edition_en_cours) {
			html += '<p>' + (g.inscriptions_ouvertes ? '🟢 Inscriptions ouvertes' : '⚪ Inscriptions fermées')
				+ (g.date_limite ? ' · date limite le ' + esc(ctx.fmtDate(g.date_limite)) : '')
				+ (g.date_gala ? ' · gala le ' + esc(ctx.fmtDate(g.date_gala)) : '') + '</p>';
		}
		html += '<div class="tkdp-gala-cards">'
			+ carte(String(g.clubs), 'clubs inscrits')
			+ carte(String(g.demonstrateurs), 'démonstrateurs')
			+ carte(String(g.demos), 'démonstrations de clubs')
			+ carte(String(g.candidatures_retenues), 'candidatures retenues (autres disciplines)')
			+ carte(duree(g.duree_sec), 'de démonstrations (clubs)')
			+ carte(String(g.repas) + ' repas', euros(g.montant_repas) + ' (' + euros(g.prix_repas) + ' l\'un), dont ' + euros(g.montant_repas_payes) + ' marqués payés')
			+ carte(String(g.pistes_manquantes), 'piste(s) audio manquante(s)', g.pistes_manquantes > 0)
			+ carte(String(g.dates_manquantes), 'date(s) de naissance manquante(s)', g.dates_manquantes > 0)
			+ '</div>';
		if (g.repas_suggestion) {
			html += '<p class="description">💶 Dans l\'onglet Budget, la recette « ' + esc(g.repas_suggestion.libelle) + ' » (' + euros(g.repas_suggestion.montant) + ') peut être ajoutée au budget prévu en un clic.</p>';
		}
		if (g.admin_url) {
			html += '<p><a class="button" href="' + esc(g.admin_url) + '">Ouvrir le menu Gala (inscriptions, conducteur, projection)</a></p>';
		}
		el.innerHTML = html;
	}

	window.TKDPGala = { mount: mount };
})();
