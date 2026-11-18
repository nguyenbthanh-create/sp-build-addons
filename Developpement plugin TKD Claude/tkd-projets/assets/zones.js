/* TKD Projets — éditeur de carte des zones (OpenStreetMap + Leaflet). */
(function () {
	'use strict';

	const PALETTE = ['#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4', '#f032e6', '#9a6324', '#469990', '#000075'];
	let leafletPromise = null;

	/** Charge Leaflet à la première ouverture d'une carte (avec contrôle d'intégrité). */
	function loadLeaflet(assets) {
		if (window.L) {
			return Promise.resolve(window.L);
		}
		if (leafletPromise) {
			return leafletPromise;
		}
		leafletPromise = new Promise((resolve, reject) => {
			const link = document.createElement('link');
			link.rel = 'stylesheet';
			link.href = assets.css;
			link.integrity = assets.cssSri;
			link.crossOrigin = '';
			document.head.appendChild(link);

			const script = document.createElement('script');
			script.src = assets.js;
			script.integrity = assets.jsSri;
			script.crossOrigin = '';
			script.onload = () => resolve(window.L);
			script.onerror = () => {
				leafletPromise = null;
				script.remove();
				reject(new Error('Impossible de charger la carte. Vérifiez votre connexion internet (le site unpkg.com doit être accessible).'));
			};
			document.head.appendChild(script);
		});
		return leafletPromise;
	}

	/**
	 * Ouvre l'éditeur de zones d'une tâche.
	 *
	 * @param {Object} task Tâche (id, title).
	 * @param {Object} ctx  { api, users, esc, notify, fail, cfg, onSaved(count) }
	 */
	async function open(task, ctx) {
		const esc = ctx.esc;
		let L;
		let zones;
		try {
			const res = await Promise.all([loadLeaflet(ctx.cfg.leaflet), ctx.api('/tasks/' + task.id + '/zones')]);
			L = res[0];
			zones = res[1];
		} catch (e) {
			ctx.fail(e);
			return;
		}

		let selected = -1;
		let dirty = false;
		let drawing = null; // { points: [], line, dots }
		let handles = [];

		/* --------------------------- Interface --------------------------- */

		const wrap = document.createElement('div');
		wrap.className = 'tkdp-map-wrap';
		wrap.innerHTML = '<div class="tkdp-map-panel">'
			+ '<div class="tkdp-map-head"><div><h2>Carte des zones</h2><div class="tkdp-map-task">' + esc(task.title) + '</div></div>'
			+ '<button type="button" class="tkdp-close" data-map="close" aria-label="Fermer">×</button></div>'
			+ '<form class="tkdp-map-search"><input type="search" placeholder="Aller à une adresse, une rue…" aria-label="Rechercher une adresse">'
			+ '<button type="submit" class="button">Aller</button></form>'
			+ '<div class="tkdp-map-tools"><button type="button" class="button button-primary tkdp-map-new" data-map="draw">✏️ Dessiner une zone</button>'
			+ '<button type="button" class="button tkdp-map-export" data-map="export" title="Exporter une, plusieurs ou toutes les zones en PNG ou PDF (A4 ou A3)">🖨️ Exporter un plan</button></div>'
			+ '<ul class="tkdp-zone-list"></ul>'
			+ '<div class="tkdp-zone-form"></div>'
			+ '<div class="tkdp-map-foot">'
			+ '<label class="tkdp-check"><input type="checkbox" class="tkdp-zone-notify" checked> Envoyer un e-mail aux personnes nouvellement affectées</label>'
			+ '<div class="tkdp-map-links"><span class="description">Liens « Ma carte » :</span> '
			+ '<button type="button" class="button-link" data-map="links-send" title="Envoie un nouveau lien à chaque personne affectée (l\'ancien cesse de marcher)">Renvoyer à tous</button> | '
			+ '<button type="button" class="button-link tkdp-danger" data-map="links-revoke" title="Les liens déjà envoyés cessent de marcher">Couper les liens</button></div>'
			+ '<div class="tkdp-map-buttons"><button type="button" class="button" data-map="close">Fermer</button>'
			+ '<button type="button" class="button button-primary" data-map="save">Enregistrer la carte</button></div></div>'
			+ '</div>'
			+ '<div class="tkdp-map-main"><div class="tkdp-map"></div>'
			+ '<div class="tkdp-draw-bar" hidden><span class="tkdp-draw-help"></span>'
			+ '<span class="tkdp-draw-actions"><button type="button" class="button" data-map="undo">↶ Annuler le point</button>'
			+ '<button type="button" class="button button-primary" data-map="finish">✓ Terminer la zone</button>'
			+ '<button type="button" class="button-link tkdp-danger" data-map="abort">Abandonner</button></span></div></div>';
		document.body.appendChild(wrap);
		document.body.classList.add('tkdp-map-open');

		const listEl = wrap.querySelector('.tkdp-zone-list');
		const formEl = wrap.querySelector('.tkdp-zone-form');
		const drawBar = wrap.querySelector('.tkdp-draw-bar');

		/* ------------------------------ Carte ----------------------------- */

		const center = ctx.cfg.mapCenter || [42.7613, 2.9567, 15];
		const map = L.map(wrap.querySelector('.tkdp-map')).setView([center[0], center[1]], center[2]);
		L.tileLayer(ctx.cfg.leaflet.tiles, {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
		}).addTo(map);
		setTimeout(() => map.invalidateSize(), 50);

		const zoneLayer = L.layerGroup().addTo(map);

		function drawZones() {
			zoneLayer.clearLayers();
			handles.forEach((h) => h.remove());
			handles = [];
			zones.forEach((z, i) => {
				const isSel = i === selected;
				z._layer = L.polygon(z.coords, {
					color: z.color,
					weight: isSel ? 4 : 2,
					fillOpacity: isSel ? 0.3 : 0.18,
					dashArray: z.assignee ? null : '6 6',
				}).bindTooltip(esc(z.name) + (z.assignee ? ' — ' + esc(userName(z.assignee)) : ' — non affectée'), { sticky: true });
				z._layer.on('click', (e) => {
					if (drawing) {
						return; // le clic est traité par la carte (ajout d'un coin)
					}
					L.DomEvent.stopPropagation(e);
					select(i);
				});
				zoneLayer.addLayer(z._layer);
			});
			if (selected >= 0 && !drawing) {
				addHandles(zones[selected]);
			}
		}

		/** Poignées pour modifier la forme de la zone sélectionnée. */
		function addHandles(z) {
			const vertexIcon = L.divIcon({ className: 'tkdp-vertex', iconSize: [14, 14] });
			const midIcon = L.divIcon({ className: 'tkdp-vertex is-mid', iconSize: [10, 10] });

			z.coords.forEach((pt, j) => {
				const m = L.marker(pt, { draggable: true, icon: vertexIcon, title: 'Glisser pour déplacer · double-clic pour supprimer ce coin' }).addTo(map);
				m.on('drag', () => {
					const ll = m.getLatLng();
					z.coords[j] = [ll.lat, ll.lng];
					z._layer.setLatLngs(z.coords);
				});
				m.on('dragend', () => {
					dirty = true;
					drawZones();
				});
				m.on('dblclick', (e) => {
					L.DomEvent.stopPropagation(e);
					if (z.coords.length <= 3) {
						ctx.notify('Une zone doit garder au moins 3 coins.', 'error');
						return;
					}
					z.coords.splice(j, 1);
					dirty = true;
					drawZones();
				});
				handles.push(m);
			});

			z.coords.forEach((pt, j) => {
				const next = z.coords[(j + 1) % z.coords.length];
				const mid = [(pt[0] + next[0]) / 2, (pt[1] + next[1]) / 2];
				const m = L.marker(mid, { draggable: true, icon: midIcon, title: 'Glisser pour ajouter un coin' }).addTo(map);
				let inserted = false;
				m.on('dragstart', () => {
					z.coords.splice(j + 1, 0, mid);
					inserted = true;
				});
				m.on('drag', () => {
					const ll = m.getLatLng();
					z.coords[j + 1] = [ll.lat, ll.lng];
					z._layer.setLatLngs(z.coords);
				});
				m.on('dragend', () => {
					if (inserted) {
						dirty = true;
					}
					drawZones();
				});
				handles.push(m);
			});
		}

		/* ----------------------------- Dessin ----------------------------- */

		function startDrawing() {
			select(-1);
			drawing = { points: [], line: L.polyline([], { color: '#1d2327', weight: 2, dashArray: '5 5' }).addTo(map), dots: [] };
			map.doubleClickZoom.disable();
			wrap.classList.add('is-drawing');
			drawBar.hidden = false;
			updateDrawBar();
		}

		function addPoint(latlng) {
			drawing.points.push([latlng.lat, latlng.lng]);
			drawing.line.setLatLngs(drawing.points.concat(drawing.points.length > 2 ? [drawing.points[0]] : []));
			drawing.dots.push(L.circleMarker(latlng, { radius: 5, color: '#1d2327', weight: 2, fillColor: '#fff', fillOpacity: 1 }).addTo(map));
			updateDrawBar();
		}

		function updateDrawBar() {
			const n = drawing.points.length;
			wrap.querySelector('.tkdp-draw-help').textContent = n < 3
				? 'Cliquez sur la carte pour placer les coins de la zone (' + n + ' / 3 minimum).'
				: n + ' coins placés. Continuez ou cliquez sur « Terminer la zone ».';
			wrap.querySelector('[data-map="finish"]').disabled = n < 3;
			wrap.querySelector('[data-map="undo"]').disabled = n === 0;
		}

		function stopDrawing() {
			if (!drawing) {
				return;
			}
			drawing.line.remove();
			drawing.dots.forEach((d) => d.remove());
			drawing = null;
			map.doubleClickZoom.enable();
			wrap.classList.remove('is-drawing');
			drawBar.hidden = true;
		}

		function finishDrawing() {
			if (!drawing || drawing.points.length < 3) {
				return;
			}
			const coords = drawing.points.slice();
			stopDrawing();
			zones.push({
				id: 0,
				name: 'Zone ' + (zones.length + 1),
				color: PALETTE[zones.length % PALETTE.length],
				assignee: 0,
				coords: coords,
				notes: '',
			});
			dirty = true;
			select(zones.length - 1);
			const input = formEl.querySelector('[name=zone-name]');
			if (input) {
				input.focus();
				input.select();
			}
		}

		map.on('click', (e) => {
			if (drawing) {
				addPoint(e.latlng);
			} else if (selected >= 0) {
				select(-1);
			}
		});

		/* ---------------------- Liste et formulaire ---------------------- */

		function userName(id) {
			const u = ctx.users.find((x) => x.id === Number(id));
			return u ? u.name : '';
		}

		function renderList() {
			if (!zones.length) {
				listEl.innerHTML = '<li class="tkdp-zone-empty">Aucune zone. Cliquez sur « Dessiner une zone » puis sur la carte pour en délimiter une '
					+ '(par exemple un quartier pour la distribution des flyers).</li>';
				return;
			}
			listEl.innerHTML = zones.map((z, i) => '<li class="' + (i === selected ? 'is-selected' : '') + '" data-index="' + i + '">'
				+ '<span class="tkdp-swatch" style="background:' + esc(z.color) + '"></span>'
				+ '<span class="tkdp-zone-name">' + esc(z.name) + '</span>'
				+ '<span class="tkdp-zone-who' + (z.assignee ? '' : ' is-none') + '">' + (z.assignee ? esc(userName(z.assignee)) : 'Non affectée') + '</span>'
				+ '</li>').join('');
		}

		function renderForm() {
			if (selected < 0) {
				formEl.innerHTML = '';
				return;
			}
			const z = zones[selected];
			const options = '<option value="0">— Personne —</option>' + ctx.users.map((u) => '<option value="' + u.id + '"'
				+ (u.id === z.assignee ? ' selected' : '') + '>' + esc(u.name) + '</option>').join('');
			formEl.innerHTML = '<div class="tkdp-zone-edit">'
				+ '<label>Nom de la zone<input type="text" name="zone-name" maxlength="190" value="' + esc(z.name) + '"></label>'
				+ '<div class="tkdp-zone-row"><label>Affectée à<select name="zone-assignee">' + options + '</select></label>'
				+ '<label class="tkdp-zone-color">Couleur<input type="color" name="zone-color" value="' + esc(z.color) + '"></label></div>'
				+ '<label>Consignes pour cette zone<textarea name="zone-notes" rows="2" placeholder="Ex. : rues des Lilas et des Roses, éviter les boîtes « Stop pub »">'
				+ esc(z.notes) + '</textarea></label>'
				+ '<p class="description">Glissez les points blancs sur la carte pour ajuster la forme, double-cliquez sur un coin pour le supprimer.</p>'
				+ '<div class="tkdp-zone-actions"><button type="button" class="button" data-map="focus">Centrer</button>'
				+ '<button type="button" class="button-link tkdp-danger" data-map="delete">Supprimer la zone</button></div></div>';
		}

		function select(i) {
			selected = i;
			renderList();
			renderForm();
			drawZones();
		}

		formEl.addEventListener('input', (e) => {
			const z = zones[selected];
			if (!z) {
				return;
			}
			if (e.target.name === 'zone-name') {
				z.name = e.target.value;
			} else if (e.target.name === 'zone-notes') {
				z.notes = e.target.value;
			} else if (e.target.name === 'zone-color') {
				z.color = e.target.value;
				z._layer.setStyle({ color: z.color });
			}
			dirty = true;
			renderList();
		});
		formEl.addEventListener('change', (e) => {
			if (e.target.name === 'zone-assignee') {
				zones[selected].assignee = Number(e.target.value);
				dirty = true;
				renderList();
				drawZones();
			} else if (e.target.name === 'zone-name') {
				drawZones();
			}
		});

		listEl.addEventListener('click', (e) => {
			const li = e.target.closest('li[data-index]');
			if (li && !drawing) {
				select(Number(li.dataset.index));
				map.fitBounds(zones[selected]._layer.getBounds(), { padding: [40, 40], maxZoom: 18 });
			}
		});

		/* ---------------------------- Recherche --------------------------- */

		wrap.querySelector('.tkdp-map-search').addEventListener('submit', async (e) => {
			e.preventDefault();
			const q = e.target.querySelector('input').value.trim();
			if (!q) {
				return;
			}
			try {
				const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=fr&accept-language=fr&q=' + encodeURIComponent(q));
				const found = await res.json();
				if (!found.length) {
					ctx.notify('Adresse introuvable.', 'error');
					return;
				}
				map.setView([Number(found[0].lat), Number(found[0].lon)], 17);
			} catch (err) {
				ctx.notify('La recherche d\'adresse n\'est pas disponible.', 'error');
			}
		});

		/* ----------------------------- Actions ---------------------------- */

		function close() {
			if (dirty && !window.confirm('Des modifications de la carte ne sont pas enregistrées. Fermer quand même ?')) {
				return;
			}
			document.removeEventListener('keydown', onKey, true);
			map.remove();
			wrap.remove();
			document.body.classList.remove('tkdp-map-open');
		}

		async function save() {
			if (drawing) {
				ctx.notify('Terminez ou abandonnez la zone en cours de dessin.', 'error');
				return;
			}
			const btn = wrap.querySelector('[data-map="save"]');
			btn.disabled = true;
			try {
				const res = await ctx.api('/tasks/' + task.id + '/zones', {
					method: 'PUT',
					data: {
						zones: zones.map((z) => ({ id: z.id, name: z.name, color: z.color, assignee: z.assignee, coords: z.coords, notes: z.notes })),
						notify: wrap.querySelector('.tkdp-zone-notify').checked ? 1 : 0,
					},
				});
				zones = res.zones;
				dirty = false;
				select(selected < zones.length ? selected : -1);
				ctx.onSaved(zones.length);
				if (typeof res.notified === 'number' && res.notified < res.expected) {
					ctx.notify('Carte enregistrée, mais ' + (res.expected - res.notified) + ' e-mail(s) n\'ont pas pu être envoyés.', 'error');
				} else if (res.notified) {
					ctx.notify('Carte enregistrée — e-mail envoyé à ' + res.notified + ' personne' + (res.notified > 1 ? 's' : '') + '.');
				} else {
					ctx.notify('Carte enregistrée.');
				}
			} catch (e) {
				ctx.fail(e);
			}
			btn.disabled = false;
		}

		/** Renvoie un nouveau lien privé à chaque personne affectée, ou coupe tous les liens de la tâche. */
		async function links(action) {
			if (dirty) {
				ctx.notify('Enregistrez d\'abord la carte.', 'error');
				return;
			}
			if (action === 'revoke' && !window.confirm('Couper tous les liens déjà envoyés pour cette tâche ? Les personnes ne pourront plus ouvrir leur carte.')) {
				return;
			}
			try {
				const res = await ctx.api('/tasks/' + task.id + '/links', { method: 'POST', data: { action: action } });
				if (action === 'revoke') {
					ctx.notify(res.revoked ? res.revoked + ' lien' + (res.revoked > 1 ? 's coupés' : ' coupé') + '.' : 'Aucun lien actif.');
				} else if (res.notified < res.expected) {
					ctx.notify((res.expected - res.notified) + ' e-mail(s) n\'ont pas pu être envoyés.', 'error');
				} else {
					ctx.notify('Nouveau lien envoyé à ' + res.notified + ' personne' + (res.notified > 1 ? 's' : '') + '.');
				}
			} catch (e) {
				ctx.fail(e);
			}
		}

		wrap.addEventListener('click', (e) => {
			const btn = e.target.closest('[data-map]');
			if (!btn) {
				return;
			}
			switch (btn.dataset.map) {
				case 'close':
					close();
					break;
				case 'save':
					save();
					break;
				case 'draw':
					if (!drawing) {
						startDrawing();
					}
					break;
				case 'undo':
					if (drawing && drawing.points.length) {
						drawing.points.pop();
						drawing.dots.pop().remove();
						drawing.line.setLatLngs(drawing.points.concat(drawing.points.length > 2 ? [drawing.points[0]] : []));
						updateDrawBar();
					}
					break;
				case 'finish':
					finishDrawing();
					break;
				case 'export':
					if (drawing) {
						ctx.notify('Terminez ou abandonnez la zone en cours de dessin.', 'error');
					} else if (!window.TKDPZoneExport) {
						ctx.notify('Le module d\'export n\'est pas chargé.', 'error');
					} else {
						window.TKDPZoneExport.open(wrap, {
							task: task,
							zones: zones,
							selected: selected,
							userName: userName,
							esc: esc,
							notify: ctx.notify,
							tiles: ctx.cfg.leaflet.tiles,
						});
					}
					break;
				case 'abort':
					stopDrawing();
					break;
				case 'links-send':
				case 'links-revoke':
					links(btn.dataset.map === 'links-send' ? 'send' : 'revoke');
					break;
				case 'focus':
					map.fitBounds(zones[selected]._layer.getBounds(), { padding: [40, 40], maxZoom: 18 });
					break;
				case 'delete':
					if (window.confirm('Supprimer la zone « ' + zones[selected].name + ' » ?')) {
						zones.splice(selected, 1);
						dirty = true;
						select(-1);
					}
					break;
			}
		});

		function onKey(e) {
			if (e.key !== 'Escape' || wrap.querySelector('.tkdp-export')) {
				return; // la fenêtre d'export gère sa propre touche Échap
			}
			e.stopPropagation();
			if (drawing) {
				stopDrawing();
			} else {
				close();
			}
		}
		document.addEventListener('keydown', onKey, true);

		select(-1);
		if (zones.length) {
			map.fitBounds(L.featureGroup(zones.map((z) => z._layer)).getBounds(), { padding: [40, 40] });
		}
	}

	window.TKDPZones = { open: open };
})();
