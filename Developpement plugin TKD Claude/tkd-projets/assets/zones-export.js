/* TKD Projets — export des zones de la carte en plan imprimable (PNG ou PDF, A4 ou A3). */
(function () {
	'use strict';

	const DPI = 150; // résolution du fichier exporté
	const TILE_SCALE = 1.5; // 1 pixel de tuile OSM = 1,5 pixel exporté (textes de la carte lisibles à l'impression)
	const MAX_ZOOM = 18; // zoom maximal du plan (évite un agrandissement excessif d'une petite zone)
	const PAPER = { A4: [210, 297], A3: [297, 420] }; // millimètres, portrait
	const PT_PER_MM = 72 / 25.4;

	const mm = (v) => Math.round((v / 25.4) * DPI);

	/* --------------------------- Projection --------------------------- */

	/** Web Mercator : coordonnées « monde » en pixels de tuile au zoom 0 (0 à 256). */
	function project(lat, lng) {
		const s = Math.sin((Math.max(-85.0511, Math.min(85.0511, lat)) * Math.PI) / 180);
		return {
			x: ((lng + 180) / 360) * 256,
			y: (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * 256,
		};
	}

	/* ----------------------------- Tuiles ----------------------------- */

	function loadImage(url) {
		return new Promise((resolve) => {
			const img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = () => resolve(img);
			img.onerror = () => resolve(null);
			img.src = url;
		});
	}

	/** Charge les tuiles 6 par 6 (règles d'usage d'OpenStreetMap). */
	async function loadTiles(list, template, onProgress) {
		const out = new Array(list.length);
		let next = 0;
		let done = 0;
		async function worker() {
			while (next < list.length) {
				const i = next++;
				const t = list[i];
				out[i] = await loadImage(template.replace('{s}', 'a').replace('{z}', t.z).replace('{x}', t.x).replace('{y}', t.y));
				onProgress(++done, list.length);
			}
		}
		await Promise.all([worker(), worker(), worker(), worker(), worker(), worker()]);
		return out;
	}

	/* ----------------------------- Dessin ----------------------------- */

	function ellipsize(c, text, maxW) {
		if (c.measureText(text).width <= maxW) {
			return text;
		}
		let t = text;
		while (t.length > 1 && c.measureText(t + '…').width > maxW) {
			t = t.slice(0, -1);
		}
		return t + '…';
	}

	/** Longueur « ronde » (1, 2 ou 5 × 10ⁿ mètres) proche de la cible. */
	function niceMeters(target) {
		const p = Math.pow(10, Math.floor(Math.log10(target)));
		const f = target / p;
		return (f >= 5 ? 5 : f >= 2 ? 2 : 1) * p;
	}

	function fmtInt(n) {
		return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
	}

	/** Centre visuel approximatif d'un polygone (centroïde de surface). */
	function centroid(pts) {
		let a = 0;
		let cx = 0;
		let cy = 0;
		for (let i = 0; i < pts.length; i++) {
			const p = pts[i];
			const q = pts[(i + 1) % pts.length];
			const f = p.x * q.y - q.x * p.y;
			a += f;
			cx += (p.x + q.x) * f;
			cy += (p.y + q.y) * f;
		}
		if (Math.abs(a) < 1e-9) {
			return pts.reduce((s, p) => ({ x: s.x + p.x / pts.length, y: s.y + p.y / pts.length }), { x: 0, y: 0 });
		}
		return { x: cx / (3 * a), y: cy / (3 * a) };
	}

	/**
	 * Dessine le plan complet sur un canvas.
	 *
	 * @param {Object} o { zones, title, subtitle, paper, landscape, tiles, userName, onProgress }
	 */
	async function renderPlan(o) {
		const size = PAPER[o.paper];
		const pw = mm(o.landscape ? size[1] : size[0]);
		const ph = mm(o.landscape ? size[0] : size[1]);
		const canvas = document.createElement('canvas');
		canvas.width = pw;
		canvas.height = ph;
		const c = canvas.getContext('2d');
		const font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
		c.fillStyle = '#fff';
		c.fillRect(0, 0, pw, ph);

		const margin = mm(10);
		const gap = mm(4);

		/* En-tête */
		c.fillStyle = '#1d2327';
		c.textBaseline = 'alphabetic';
		c.font = '600 ' + mm(6) + 'px ' + font;
		c.fillText(ellipsize(c, o.title, pw - 2 * margin), margin, margin + mm(5.5));
		c.fillStyle = '#50575e';
		c.font = mm(3.4) + 'px ' + font;
		c.fillText(ellipsize(c, o.subtitle, pw - 2 * margin), margin, margin + mm(11));
		const headH = mm(14);

		/* Légende : colonnes et hauteur */
		const cols = Math.max(1, Math.min(o.zones.length, o.landscape ? 3 : 2));
		const itemH = mm(10);
		const maxRows = Math.max(1, Math.floor((ph * 0.3) / itemH));
		const rowsNeeded = Math.ceil(o.zones.length / cols);
		const rows = Math.min(rowsNeeded, maxRows);
		const shown = Math.min(o.zones.length, rows * cols - (rowsNeeded > maxRows ? 1 : 0));
		const legendH = rows * itemH + mm(8);
		const footH = mm(6);

		/* Zone de carte */
		const mapX = margin;
		const mapY = margin + headH + gap;
		const mapW = pw - 2 * margin;
		const mapH = ph - mapY - gap - legendH - footH - margin;

		/* Cadrage : toutes les zones sélectionnées tiennent dans le cadre */
		const polys = o.zones.map((z) => z.coords.map((p) => project(p[0], p[1])));
		const all = [].concat.apply([], polys);
		const minX = Math.min.apply(null, all.map((p) => p.x));
		const maxX = Math.max.apply(null, all.map((p) => p.x));
		const minY = Math.min.apply(null, all.map((p) => p.y));
		const maxY = Math.max.apply(null, all.map((p) => p.y));
		const cssW = mapW / TILE_SCALE; // dimensions du cadre en « pixels écran »
		const cssH = mapH / TILE_SCALE;
		const pad = 40;
		const fit = Math.min((cssW - 2 * pad) / Math.max(maxX - minX, 1e-9), (cssH - 2 * pad) / Math.max(maxY - minY, 1e-9));
		const Z = Math.min(MAX_ZOOM, Math.log2(fit));
		const scale = Math.pow(2, Z);
		const left = ((minX + maxX) / 2) * scale - cssW / 2;
		const top = ((minY + maxY) / 2) * scale - cssH / 2;
		const toMap = (p) => ({ x: p.x * scale - left, y: p.y * scale - top });

		/* Tuiles nécessaires */
		const tz = Math.max(0, Math.min(19, Math.ceil(Z)));
		const k = Math.pow(2, Z - tz); // taille d'une tuile = 256 × k pixels écran
		const n = Math.pow(2, tz);
		const tl = { x: left / k, y: top / k };
		const list = [];
		for (let ty = Math.floor(tl.y / 256); ty <= Math.floor((tl.y + cssH / k) / 256); ty++) {
			for (let tx = Math.floor(tl.x / 256); tx <= Math.floor((tl.x + cssW / k) / 256); tx++) {
				if (ty >= 0 && ty < n) {
					list.push({ z: tz, x: ((tx % n) + n) % n, y: ty, dx: tx * 256 * k - left, dy: ty * 256 * k - top });
				}
			}
		}
		const imgs = await loadTiles(list, o.tiles, o.onProgress);
		const missing = imgs.filter((i) => !i).length;

		c.save();
		c.beginPath();
		c.rect(mapX, mapY, mapW, mapH);
		c.clip();
		c.translate(mapX, mapY);
		c.scale(TILE_SCALE, TILE_SCALE);
		c.fillStyle = '#e5e3df';
		c.fillRect(0, 0, cssW, cssH);
		c.imageSmoothingQuality = 'high';
		list.forEach((t, i) => {
			if (imgs[i]) {
				// léger débord pour éviter les fines lignes entre tuiles
				c.drawImage(imgs[i], t.dx, t.dy, 256 * k + 0.6, 256 * k + 0.6);
			}
		});

		/* Zones */
		polys.forEach((poly, i) => {
			const z = o.zones[i];
			const pts = poly.map(toMap);
			c.beginPath();
			pts.forEach((p, j) => (j ? c.lineTo(p.x, p.y) : c.moveTo(p.x, p.y)));
			c.closePath();
			c.globalAlpha = 0.22;
			c.fillStyle = z.color;
			c.fill();
			c.globalAlpha = 1;
			c.lineWidth = 3;
			c.lineJoin = 'round';
			c.strokeStyle = z.color;
			c.setLineDash(z.assignee ? [] : [8, 6]);
			c.stroke();
			c.setLineDash([]);
		});

		/* Noms des zones */
		c.textAlign = 'center';
		c.textBaseline = 'middle';
		c.font = '700 13px ' + font;
		c.lineJoin = 'round';
		polys.forEach((poly, i) => {
			const p = toMap(centroid(poly));
			c.lineWidth = 4;
			c.strokeStyle = 'rgba(255,255,255,.95)';
			c.strokeText(o.zones[i].name, p.x, p.y);
			c.fillStyle = '#1d2327';
			c.fillText(o.zones[i].name, p.x, p.y);
		});
		c.restore();

		/* Cadre */
		c.strokeStyle = '#8c8f94';
		c.lineWidth = Math.max(1, mm(0.3));
		c.strokeRect(mapX, mapY, mapW, mapH);

		/* Échelle et nord */
		const latC = Math.atan(Math.sinh(Math.PI * (1 - (2 * ((minY + maxY) / 2)) / 256))); // radians
		const metersPerOut = (40075016.686 * Math.cos(latC)) / (256 * scale) / TILE_SCALE; // mètres par pixel exporté
		const bar = niceMeters(metersPerOut * mm(35));
		const barW = bar / metersPerOut;
		const bx = mapX + mm(4);
		const by = mapY + mapH - mm(5);
		c.fillStyle = 'rgba(255,255,255,.88)';
		c.fillRect(bx - mm(2), by - mm(6.5), barW + mm(32), mm(9));
		c.fillStyle = '#1d2327';
		c.fillRect(bx, by - mm(1.2), barW, mm(1.2));
		c.fillRect(bx, by - mm(2.4), mm(0.4), mm(2.4));
		c.fillRect(bx + barW - mm(0.4), by - mm(2.4), mm(0.4), mm(2.4));
		c.textAlign = 'left';
		c.textBaseline = 'alphabetic';
		c.font = mm(2.8) + 'px ' + font;
		c.fillText(bar >= 1000 ? bar / 1000 + ' km' : bar + ' m', bx, by - mm(3.2));
		const ratio = metersPerOut * (DPI / 0.0254);
		const r = Math.pow(10, Math.floor(Math.log10(ratio)) - 1);
		c.fillText('Échelle ≈ 1:' + fmtInt(Math.round(ratio / r) * r), bx + barW + mm(3), by);

		const nx = mapX + mapW - mm(8);
		const ny = mapY + mm(6);
		c.fillStyle = 'rgba(255,255,255,.88)';
		c.beginPath();
		c.arc(nx, ny + mm(2.5), mm(5), 0, Math.PI * 2);
		c.fill();
		c.fillStyle = '#1d2327';
		c.beginPath();
		c.moveTo(nx, ny - mm(1.5));
		c.lineTo(nx + mm(2), ny + mm(3.5));
		c.lineTo(nx, ny + mm(2.4));
		c.lineTo(nx - mm(2), ny + mm(3.5));
		c.closePath();
		c.fill();
		c.textAlign = 'center';
		c.font = '700 ' + mm(2.6) + 'px ' + font;
		c.fillText('N', nx, ny + mm(6.6));

		/* Légende */
		const ly = mapY + mapH + gap;
		c.textAlign = 'left';
		c.fillStyle = '#1d2327';
		c.font = '600 ' + mm(3.6) + 'px ' + font;
		c.fillText('Légende', margin, ly + mm(4));
		const colW = mapW / cols;
		for (let i = 0; i < shown; i++) {
			const z = o.zones[i];
			const x = margin + Math.floor(i / rows) * colW;
			const y = ly + mm(7) + (i % rows) * itemH;
			c.fillStyle = z.color;
			c.globalAlpha = 0.35;
			c.fillRect(x, y, mm(5), mm(5));
			c.globalAlpha = 1;
			c.strokeStyle = z.color;
			c.lineWidth = mm(0.5);
			c.setLineDash(z.assignee ? [] : [mm(1), mm(0.8)]);
			c.strokeRect(x, y, mm(5), mm(5));
			c.setLineDash([]);
			const tx = x + mm(7);
			const tw = colW - mm(9);
			c.fillStyle = '#1d2327';
			c.font = '600 ' + mm(3.2) + 'px ' + font;
			const who = z.assignee ? o.userName(z.assignee) : 'Non affectée';
			c.fillText(ellipsize(c, z.name + ' — ' + (who || 'Non affectée'), tw), tx, y + mm(3.4));
			if (z.notes) {
				c.fillStyle = '#50575e';
				c.font = mm(2.8) + 'px ' + font;
				c.fillText(ellipsize(c, z.notes.replace(/\s+/g, ' '), tw), tx, y + mm(7.4));
			}
		}
		if (shown < o.zones.length) {
			const i = shown;
			c.fillStyle = '#50575e';
			c.font = 'italic ' + mm(3) + 'px ' + font;
			c.fillText('… et ' + (o.zones.length - shown) + ' autre(s) zone(s)', margin + Math.floor(i / rows) * colW, ly + mm(7) + (i % rows) * itemH + mm(3.4));
		}

		/* Pied de page */
		c.fillStyle = '#646970';
		c.font = mm(2.6) + 'px ' + font;
		c.textAlign = 'left';
		c.fillText('Fond de carte © contributeurs OpenStreetMap — openstreetmap.org/copyright', margin, ph - margin);
		c.textAlign = 'right';
		c.fillText(o.paper + (o.landscape ? ' paysage' : ' portrait'), pw - margin, ph - margin);

		return { canvas: canvas, missing: missing, landscape: o.landscape };
	}

	/* ------------------------------- PDF ------------------------------ */

	/** PDF d'une page contenant une image JPEG pleine page (sans bibliothèque externe). */
	function jpegToPdf(jpeg, imgW, imgH, pageW, pageH) {
		const enc = new TextEncoder();
		const parts = [];
		const offsets = [];
		let length = 0;
		const push = (data) => {
			const bytes = typeof data === 'string' ? enc.encode(data) : data;
			parts.push(bytes);
			length += bytes.length;
		};
		const obj = (n, body) => {
			offsets[n] = length;
			push(n + ' 0 obj\n' + body + '\nendobj\n');
		};
		const w = pageW.toFixed(2);
		const h = pageH.toFixed(2);
		const content = 'q ' + w + ' 0 0 ' + h + ' 0 0 cm /Im0 Do Q';

		push('%PDF-1.4\n');
		push(new Uint8Array([0x25, 0xe2, 0xe3, 0xcf, 0xd3, 0x0a]));
		obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
		obj(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
		obj(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + w + ' ' + h + '] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>');
		offsets[4] = length;
		push('4 0 obj\n<< /Type /XObject /Subtype /Image /Width ' + imgW + ' /Height ' + imgH
			+ ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpeg.length + ' >>\nstream\n');
		push(jpeg);
		push('\nendstream\nendobj\n');
		obj(5, '<< /Length ' + content.length + ' >>\nstream\n' + content + '\nendstream');
		const xref = length;
		let table = 'xref\n0 6\n0000000000 65535 f \n';
		for (let i = 1; i <= 5; i++) {
			table += String(offsets[i]).padStart(10, '0') + ' 00000 n \n';
		}
		push(table + 'trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');
		return new Blob(parts, { type: 'application/pdf' });
	}

	function canvasBlob(canvas, type, quality) {
		return new Promise((resolve, reject) => {
			canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Export impossible.'))), type, quality);
		});
	}

	function download(blob, name) {
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = name;
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(() => URL.revokeObjectURL(url), 5000);
	}

	function slug(s) {
		return (s || 'plan').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '').toLowerCase() || 'plan';
	}

	/* ----------------------------- Fenêtre ---------------------------- */

	/**
	 * Ouvre la fenêtre d'export au-dessus de l'éditeur de carte.
	 *
	 * @param {HTMLElement} host Conteneur de l'éditeur.
	 * @param {Object}      o    { task, zones, selected, userName, esc, notify, tiles }
	 */
	function open(host, o) {
		const esc = o.esc;
		if (!o.zones.length) {
			o.notify('Dessinez au moins une zone avant d\'exporter un plan.', 'error');
			return;
		}
		const pref = (key, def) => {
			try {
				return window.localStorage.getItem('tkdp-export-' + key) || def;
			} catch (e) {
				return def;
			}
		};
		const savePref = (key, val) => {
			try {
				window.localStorage.setItem('tkdp-export-' + key, val);
			} catch (e) {
				/* stockage indisponible : sans conséquence */
			}
		};

		const box = document.createElement('div');
		box.className = 'tkdp-export';
		box.innerHTML = '<form class="tkdp-export-box" role="dialog" aria-modal="true" aria-label="Exporter un plan">'
			+ '<div class="tkdp-map-head"><h2>Exporter un plan</h2><button type="button" class="tkdp-close" data-export="close" aria-label="Fermer">×</button></div>'
			+ '<fieldset><legend>Zones à inclure</legend>'
			+ '<label class="tkdp-check tkdp-export-all"><input type="checkbox" name="all"> <strong>Toutes les zones</strong></label>'
			+ '<div class="tkdp-export-zones">'
			+ o.zones.map((z, i) => '<label class="tkdp-check"><input type="checkbox" name="zone" value="' + i + '"'
				+ (o.selected < 0 || o.selected === i ? ' checked' : '') + '> <span class="tkdp-swatch" style="background:' + esc(z.color) + '"></span> '
				+ esc(z.name) + '</label>').join('')
			+ '</div></fieldset>'
			+ '<div class="tkdp-export-row">'
			+ '<fieldset><legend>Format</legend>'
			+ ['A4', 'A3'].map((f) => '<label class="tkdp-check"><input type="radio" name="paper" value="' + f + '"' + (pref('paper', 'A4') === f ? ' checked' : '') + '> ' + f + '</label>').join('')
			+ '</fieldset>'
			+ '<fieldset><legend>Orientation</legend>'
			+ [['auto', 'Automatique'], ['portrait', 'Portrait'], ['landscape', 'Paysage']].map((v) => '<label class="tkdp-check"><input type="radio" name="orient" value="' + v[0] + '"'
				+ (pref('orient', 'auto') === v[0] ? ' checked' : '') + '> ' + v[1] + '</label>').join('')
			+ '</fieldset>'
			+ '<fieldset><legend>Fichier</legend>'
			+ [['pdf', 'PDF'], ['png', 'PNG (image)']].map((v) => '<label class="tkdp-check"><input type="radio" name="type" value="' + v[0] + '"'
				+ (pref('type', 'pdf') === v[0] ? ' checked' : '') + '> ' + v[1] + '</label>').join('')
			+ '</fieldset></div>'
			+ '<p class="description">Le plan est cadré automatiquement sur les zones choisies, avec une légende, l\'échelle et le nord.</p>'
			+ '<p class="tkdp-export-status" aria-live="polite"></p>'
			+ '<div class="tkdp-map-buttons"><button type="button" class="button" data-export="close">Annuler</button>'
			+ '<button type="submit" class="button button-primary">⬇ Exporter</button></div>'
			+ '</form>';
		host.appendChild(box);

		const form = box.querySelector('form');
		const allBox = form.querySelector('[name=all]');
		const zoneBoxes = Array.from(form.querySelectorAll('[name=zone]'));
		const status = form.querySelector('.tkdp-export-status');
		const submit = form.querySelector('[type=submit]');
		let busy = false;

		function syncAll() {
			const n = zoneBoxes.filter((b) => b.checked).length;
			allBox.checked = n === zoneBoxes.length;
			allBox.indeterminate = n > 0 && n < zoneBoxes.length;
			submit.disabled = busy || n === 0;
		}
		allBox.addEventListener('change', () => {
			zoneBoxes.forEach((b) => {
				b.checked = allBox.checked;
			});
			syncAll();
		});
		zoneBoxes.forEach((b) => b.addEventListener('change', syncAll));
		syncAll();

		function close() {
			if (busy) {
				return;
			}
			document.removeEventListener('keydown', onKey, true);
			box.remove();
		}
		function onKey(e) {
			if (e.key === 'Escape') {
				e.stopPropagation();
				close();
			}
		}
		document.addEventListener('keydown', onKey, true);
		box.addEventListener('click', (e) => {
			if (e.target === box || e.target.closest('[data-export="close"]')) {
				close();
			}
		});

		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			const zones = zoneBoxes.filter((b) => b.checked).map((b) => o.zones[Number(b.value)]).filter((z) => z.coords && z.coords.length >= 3);
			if (!zones.length) {
				return;
			}
			const paper = form.querySelector('[name=paper]:checked').value;
			const orient = form.querySelector('[name=orient]:checked').value;
			const type = form.querySelector('[name=type]:checked').value;
			savePref('paper', paper);
			savePref('orient', orient);
			savePref('type', type);

			let landscape = orient === 'landscape';
			if (orient === 'auto') {
				const pts = [].concat.apply([], zones.map((z) => z.coords.map((p) => project(p[0], p[1]))));
				const w = Math.max.apply(null, pts.map((p) => p.x)) - Math.min.apply(null, pts.map((p) => p.x));
				const h = Math.max.apply(null, pts.map((p) => p.y)) - Math.min.apply(null, pts.map((p) => p.y));
				landscape = w > h;
			}

			busy = true;
			submit.disabled = true;
			status.textContent = 'Préparation du plan…';
			try {
				const today = new Date().toLocaleDateString('fr-FR');
				const res = await renderPlan({
					zones: zones,
					paper: paper,
					landscape: landscape,
					tiles: o.tiles,
					userName: o.userName,
					title: o.task.title,
					subtitle: (zones.length === o.zones.length ? 'Toutes les zones (' + zones.length + ')' : zones.length === 1 ? 'Zone : ' + zones[0].name : zones.length + ' zones : ' + zones.map((z) => z.name).join(', '))
						+ ' · Plan exporté le ' + today,
					onProgress: (d, n) => {
						status.textContent = 'Chargement du fond de carte… ' + Math.round((d / n) * 100) + ' %';
					},
				});
				status.textContent = 'Création du fichier…';
				const base = slug(o.task.title) + '-' + (zones.length === 1 ? slug(zones[0].name) : zones.length === o.zones.length ? 'toutes-les-zones' : zones.length + '-zones')
					+ '-' + paper.toLowerCase();
				if (type === 'png') {
					download(await canvasBlob(res.canvas, 'image/png'), base + '.png');
				} else {
					const jpeg = new Uint8Array(await (await canvasBlob(res.canvas, 'image/jpeg', 0.92)).arrayBuffer());
					const size = PAPER[paper];
					const pw = (landscape ? size[1] : size[0]) * PT_PER_MM;
					const ph = (landscape ? size[0] : size[1]) * PT_PER_MM;
					download(jpegToPdf(jpeg, res.canvas.width, res.canvas.height, pw, ph), base + '.pdf');
				}
				busy = false;
				if (res.missing) {
					status.textContent = '';
					o.notify('Plan exporté, mais ' + res.missing + ' morceau(x) du fond de carte n\'ont pas pu être chargés.', 'error');
				} else {
					o.notify('Plan exporté.');
				}
				close();
			} catch (err) {
				busy = false;
				syncAll();
				status.textContent = '';
				o.notify((err && err.message) || 'L\'export du plan a échoué.', 'error');
			}
		});
	}

	window.TKDPZoneExport = { open: open, renderPlan: renderPlan };
})();
