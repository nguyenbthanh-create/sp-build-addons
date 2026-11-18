/* TKD Projets — onglet Budget (bureau seulement), relié à SP Compta. Étape 4, 18/11/2026. */
(function () {
	'use strict';

	const SENS = { depense: 'Dépense', recette: 'Recette' };
	const MODES = { CB: 'Carte bancaire', cheque: 'Chèque', especes: 'Espèces', virement: 'Virement', prelevement: 'Prélèvement' };

	function euros(n, signed) {
		const v = Number(n) || 0;
		const txt = Math.abs(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
		if (!signed) {
			return (v < 0 ? '− ' : '') + txt;
		}
		return (v > 0 ? '+ ' : v < 0 ? '− ' : '') + txt;
	}

	/** Montant d'une ligne pour le champ de saisie : 90.5 → « 90,50 » ; texte en cours de saisie gardé tel quel. */
	function montantSaisi(v) {
		if (v === '' || v === null || v === undefined) {
			return '';
		}
		return typeof v === 'number' ? v.toFixed(2).replace('.', ',') : String(v);
	}

	function cls(n) {
		return n > 0 ? 'is-pos' : n < 0 ? 'is-neg' : '';
	}

	/**
	 * @param {HTMLElement} el      Conteneur de l'onglet.
	 * @param {Object}      project Projet (id, name…).
	 * @param {Object}      ctx     { api, esc, notify, fail, fmtDate, today }
	 */
	function mount(el, project, ctx) {
		if (!el) {
			return;
		}
		const esc = ctx.esc;
		const base = '/projects/' + project.id;
		let data = null;
		let lignes = [];

		async function load() {
			try {
				data = await ctx.api(base + '/budget');
				lignes = (data.lignes || []).map((l) => Object.assign({}, l));
				draw();
			} catch (e) {
				el.innerHTML = '<div class="notice notice-error inline"><p>' + esc(e.message || 'Budget indisponible.') + '</p></div>';
			}
		}

		function saisonText() {
			return data.saison ? 'Saison ' + esc(data.saison.libelle) + ' de la trésorerie' : 'Aucune saison trouvée dans la trésorerie';
		}

		function draw() {
			let html = '<p class="description">' + saisonText() + '. Visible du seul bureau. Les montants réalisés viennent des dépenses et recettes rattachées au projet dans la trésorerie.</p>';
			if (!data.linked) {
				html += drawLink();
			} else {
				html += drawSummary() + drawCategories() + drawLines() + (data.canValidate ? drawDepenseForm() : '') + drawMovements();
			}
			el.innerHTML = html;
			bind();
		}

		function drawLink() {
			const options = (data.candidats || []).map((c) => '<option value="' + c.id + '">' + esc(c.nom) + (c.statut === 'cloture' ? ' (clôturé)' : '') + '</option>').join('');
			return '<div class="tkdp-budget-box"><p><strong>Ce projet n\'est pas encore relié à la trésorerie.</strong></p>'
				+ '<p><button type="button" class="button button-primary" data-budget="create"' + (data.saison ? '' : ' disabled') + '>Créer le projet dans la trésorerie</button></p>'
				+ (options ? '<p>ou le relier à un projet déjà créé par la trésorière : <select class="tkdp-budget-existing">' + options + '</select> '
					+ '<button type="button" class="button" data-budget="link">Relier</button></p>' : '')
				+ '<p class="description">Vous pouvez aussi saisir directement les lignes du budget : le projet sera créé dans la trésorerie à l\'enregistrement.</p></div>'
				+ drawLines();
		}

		function drawSummary() {
			const t = data.bilan.totaux;
			const row = (label, prevu, realise, ecart, signed) => '<tr><th>' + label + '</th>'
				+ '<td class="num">' + (t.a_un_budget ? euros(prevu, signed) : '—') + '</td>'
				+ '<td class="num ' + (signed ? cls(realise) : '') + '">' + euros(realise, signed) + '</td>'
				+ '<td class="num">' + (t.a_un_budget ? euros(ecart, true) : '—') + '</td></tr>';
			return '<h3>Bilan</h3><table class="widefat tkdp-budget-table"><thead><tr><th></th><th class="num">Prévu</th><th class="num">Réalisé</th><th class="num">Écart</th></tr></thead><tbody>'
				+ row('Recettes', t.budget_recettes, t.recettes, t.ecart_recettes, false)
				+ row('Dépenses', t.budget_depenses, t.depenses, t.ecart_depenses, false)
				+ row('Résultat', t.budget_net, t.net, t.ecart_net, true)
				+ '</tbody></table>';
		}

		function drawCategories() {
			const cats = data.bilan.categories || [];
			if (!cats.length) {
				return '';
			}
			return '<h3>Par catégorie</h3><table class="widefat striped tkdp-budget-table"><thead><tr><th>Sens</th><th>Catégorie</th><th>Lignes prévues</th>'
				+ '<th class="num">Prévu</th><th class="num">Réalisé</th><th class="num">Écart</th></tr></thead><tbody>'
				+ cats.map((c) => '<tr><td>' + esc(SENS[c.sens] || c.sens) + '</td><td>' + esc(c.libelle_categorie)
					+ (c.hors_budget ? ' <span class="tkdp-late">hors budget</span>' : '') + '</td><td>' + esc(c.libelles.join(', ')) + '</td>'
					+ '<td class="num">' + euros(c.prevu) + '</td><td class="num">' + euros(c.realise) + '</td><td class="num">' + euros(c.ecart, true) + '</td></tr>').join('')
				+ '</tbody></table>';
		}

		function categoryOptions(sens, selected) {
			const list = (data.categories || {})[sens] || {};
			return '<option value="">— Catégorie —</option>' + Object.keys(list).map((code) => '<option value="' + esc(code) + '"'
				+ (String(code) === String(selected) ? ' selected' : '') + '>' + esc(list[code]) + '</option>').join('');
		}

		function drawLines() {
			const rows = lignes.length ? lignes.map((l, i) => '<tr data-index="' + i + '">'
				+ '<td><select data-field="sens">' + Object.keys(SENS).map((s) => '<option value="' + s + '"' + (s === l.sens ? ' selected' : '') + '>' + SENS[s] + '</option>').join('') + '</select></td>'
				+ '<td><select data-field="categorie">' + categoryOptions(l.sens, l.categorie) + '</select></td>'
				+ '<td><input type="text" data-field="libelle" maxlength="191" value="' + esc(l.libelle) + '" placeholder="ex. Location de la salle"></td>'
				+ '<td><input type="text" data-field="montant" inputmode="decimal" class="num" value="' + esc(montantSaisi(l.montant)) + '" placeholder="0,00"> €</td>'
				+ '<td><button type="button" class="tkdp-file-remove" data-budget="remove" aria-label="Retirer la ligne">×</button></td></tr>').join('')
				: '<tr><td colspan="5" class="tkdp-file-empty">Aucune ligne prévue pour l\'instant.</td></tr>';
			const suggestions = (data.suggestions || []).map((s, i) => {
				const deja = lignes.find((l) => l.sens === s.sens && String(l.libelle).indexOf(s.libelle.split(' (')[0]) === 0);
				return '<div class="tkdp-budget-box"><p>💡 Depuis TKD Gala : <strong>' + esc(s.libelle) + '</strong> = ' + euros(s.montant)
					+ (deja ? ' (déjà dans le budget pour ' + euros(Number(String(deja.montant).replace(',', '.')) || 0) + ')' : '') + '. '
					+ '<button type="button" class="button" data-budget="suggest" data-index="' + i + '">' + (deja ? 'Mettre à jour la ligne' : 'Ajouter au budget prévu') + '</button></p></div>';
			}).join('');
			return suggestions + '<h3>Budget prévu</h3><table class="widefat tkdp-budget-lines"><thead><tr><th>Sens</th><th>Catégorie</th><th>Libellé</th><th>Montant prévu</th><th></th></tr></thead>'
				+ '<tbody>' + rows + '</tbody></table>'
				+ '<p><button type="button" class="button" data-budget="add" data-sens="depense">+ Ligne de dépense</button> '
				+ '<button type="button" class="button" data-budget="add" data-sens="recette">+ Ligne de recette</button> '
				+ '<button type="button" class="button button-primary" data-budget="save">Enregistrer le budget prévu</button></p>'
				+ '<p class="description">Le réalisé d\'une ligne est la somme des mouvements du projet classés dans sa catégorie. Plusieurs lignes peuvent partager une catégorie.</p>';
		}

		function drawDepenseForm() {
			const groups = data.sousCategories || {};
			const sous = Object.keys(groups).map((code) => '<optgroup label="' + esc(groups[code].libelle) + '">'
				+ Object.keys(groups[code].sous).map((k) => '<option value="' + esc(k) + '">' + esc(groups[code].sous[k]) + '</option>').join('') + '</optgroup>').join('');
			const modes = '<option value="">—</option>' + (data.modesPaiement || []).map((m) => '<option value="' + esc(m) + '">' + esc(MODES[m] || m) + '</option>').join('');
			return '<h3>Dépense validée par le président</h3>'
				+ '<form class="tkdp-budget-depense">'
				+ '<div class="tkdp-grid">'
				+ '<label>Date<input type="date" name="date" required value="' + esc(ctx.today) + '"></label>'
				+ '<label>Montant (€)<input type="text" name="montant" inputmode="decimal" required placeholder="0,00"></label>'
				+ '<label>Catégorie<select name="sous_categorie" required><option value="">— Choisir —</option>' + sous + '</select></label>'
				+ '<label>Mode de paiement<select name="mode_paiement">' + modes + '</select></label>'
				+ '</div>'
				+ '<p><label>Détail<textarea name="detail" rows="2" placeholder="Fournisseur, objet…"></textarea></label></p>'
				+ '<p><label>Justificatif (photo ou PDF) <input type="file" name="justificatif" accept="image/*,application/pdf"></label></p>'
				+ '<p class="tkdp-check"><label><input type="checkbox" name="confirm" required> Je valide cette dépense : elle entre directement dans la trésorerie, rattachée à ce projet.</label></p>'
				+ '<p><button type="submit" class="button button-primary">Valider la dépense</button></p></form>';
		}

		function drawMovements() {
			const m = data.mouvements || [];
			return '<h3>Mouvements rattachés (' + m.length + ')</h3>'
				+ (m.length ? '<table class="widefat striped tkdp-budget-table"><thead><tr><th>Date</th><th>Type</th><th>Catégorie / provenance</th><th>Détail</th><th class="num">Montant</th></tr></thead><tbody>'
					+ m.map((x) => '<tr><td>' + esc(ctx.fmtDate(x.date)) + '</td><td>' + esc(SENS[x.type] || x.type) + '</td><td>' + esc(x.libelle) + '</td><td>' + esc(x.detail)
						+ '</td><td class="num ' + cls(x.montant) + '">' + euros(x.montant, true) + '</td></tr>').join('')
					+ '</tbody></table>'
					: '<p>Aucun mouvement rattaché. La trésorière peut rattacher des dépenses au projet depuis l\'onglet Projets de la trésorerie.</p>');
		}

		function readLines() {
			el.querySelectorAll('.tkdp-budget-lines tbody tr[data-index]').forEach((tr) => {
				const l = lignes[Number(tr.dataset.index)];
				tr.querySelectorAll('[data-field]').forEach((input) => {
					l[input.dataset.field] = input.value;
				});
			});
		}

		async function call(fn, okMessage) {
			try {
				const res = await fn();
				data = res;
				lignes = (data.lignes || []).map((l) => Object.assign({}, l));
				draw();
				if (okMessage) {
					ctx.notify(okMessage);
				}
			} catch (e) {
				ctx.fail(e);
			}
		}

		function bind() {
			el.querySelectorAll('[data-budget]').forEach((btn) => {
				btn.addEventListener('click', () => {
					const action = btn.dataset.budget;
					if (action === 'create') {
						call(() => ctx.api(base + '/budget/link', { method: 'POST', data: { action: 'create' } }), 'Projet créé dans la trésorerie.');
					} else if (action === 'link') {
						const id = Number(el.querySelector('.tkdp-budget-existing').value);
						call(() => ctx.api(base + '/budget/link', { method: 'POST', data: { action: 'existing', compta_id: id } }), 'Projet relié à la trésorerie.');
					} else if (action === 'add') {
						readLines();
						lignes.push({ sens: btn.dataset.sens, categorie: '', libelle: '', montant: '' });
						draw();
					} else if (action === 'suggest') {
						// Ajoutée (ou mise à jour) dans le tableau ; « Enregistrer le budget prévu » la rend définitive.
						readLines();
						const s = data.suggestions[Number(btn.dataset.index)];
						const deja = lignes.find((l) => l.sens === s.sens && String(l.libelle).indexOf(s.libelle.split(' (')[0]) === 0);
						if (deja) {
							Object.assign(deja, { categorie: s.categorie, libelle: s.libelle, montant: s.montant });
						} else {
							lignes.push({ sens: s.sens, categorie: s.categorie, libelle: s.libelle, montant: s.montant });
						}
						draw();
						ctx.notify('Ligne ajoutée au tableau : cliquez sur « Enregistrer le budget prévu ».');
					} else if (action === 'remove') {
						readLines();
						lignes.splice(Number(btn.closest('tr').dataset.index), 1);
						draw();
					} else if (action === 'save') {
						readLines();
						const payload = lignes.map((l) => ({ sens: l.sens, categorie: String(l.categorie), libelle: l.libelle, montant: String(l.montant) }));
						call(() => ctx.api(base + '/budget', { method: 'PUT', data: { lignes: payload } }), 'Budget prévu enregistré.');
					}
				});
			});
			// Changer le sens d'une ligne : la liste des catégories change avec lui.
			el.querySelectorAll('.tkdp-budget-lines [data-field="sens"]').forEach((sel) => {
				sel.addEventListener('change', () => {
					readLines();
					lignes[Number(sel.closest('tr').dataset.index)].categorie = '';
					draw();
				});
			});
			const form = el.querySelector('.tkdp-budget-depense');
			if (form) {
				form.addEventListener('submit', (e) => {
					e.preventDefault();
					if (!form.reportValidity()) {
						return;
					}
					const fd = new FormData(form);
					fd.delete('confirm');
					const btn = form.querySelector('[type=submit]');
					btn.disabled = true;
					call(() => ctx.api(base + '/depenses', { method: 'POST', body: fd }), 'Dépense enregistrée dans la trésorerie.')
						.finally(() => {
							btn.disabled = false;
						});
				});
			}
		}

		el.innerHTML = '<p>Chargement du budget…</p>';
		load();
	}

	window.TKDPBudget = { mount: mount };
})();
