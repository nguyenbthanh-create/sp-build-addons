/* TKD Projets — page « Ma carte » (lecture seule, ouverte depuis le lien de l'e-mail). */
(function () {
	'use strict';

	const data = window.TKDP_ZONE || { zones: [] };
	const list = document.getElementById('zones');
	const esc = (v) => String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, (c) => ({
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
	}[c]));

	/** Centre approximatif d'une zone (moyenne des coins), pour Google Maps. */
	function centerOf(coords) {
		const n = coords.length || 1;
		const lat = coords.reduce((s, p) => s + p[0], 0) / n;
		const lng = coords.reduce((s, p) => s + p[1], 0) / n;
		return [lat.toFixed(6), lng.toFixed(6)];
	}

	list.innerHTML = data.zones.map((z, i) => {
		const c = centerOf(z.coords);
		return '<div class="zone"><span class="swatch" style="background:' + esc(z.color) + '"></span><div class="body">'
			+ '<strong>' + esc(z.name) + '</strong>'
			+ (z.notes ? '<div class="notes">' + esc(z.notes) + '</div>' : '')
			+ '<div class="actions"><button type="button" class="btn" data-zone="' + i + '">Voir sur la carte</button>'
			+ '<a class="btn" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=' + c[0] + ',' + c[1] + '">Itinéraire (Google Maps)</a></div>'
			+ '</div></div>';
	}).join('');

	if (!window.L) {
		document.getElementById('map').innerHTML = '<p class="err">La carte n\'a pas pu se charger (connexion internet ?). Vos zones sont listées ci-dessous.</p>';
		document.getElementById('locate').hidden = true;
		return;
	}

	const center = data.center || [42.7613, 2.9567, 15];
	const map = L.map('map', { zoomControl: true }).setView([center[0], center[1]], center[2]);
	L.tileLayer(data.leaflet.tiles, {
		maxZoom: 19,
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
	}).addTo(map);

	const layers = data.zones.map((z) => L.polygon(z.coords, { color: z.color, weight: 3, fillOpacity: 0.2 })
		.bindTooltip(esc(z.name), { permanent: true, direction: 'center', className: 'tkdp-zone-label' })
		.addTo(map));

	if (layers.length) {
		map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [24, 24] });
	}

	list.addEventListener('click', (e) => {
		const btn = e.target.closest('[data-zone]');
		if (btn) {
			map.fitBounds(layers[Number(btn.dataset.zone)].getBounds(), { padding: [24, 24] });
			window.scrollTo({ top: 0, behavior: 'smooth' });
		}
	});

	// Position de la personne en temps réel (utile pour la distribution porte à porte)
	let me = null;
	let watch = null;
	document.getElementById('locate').addEventListener('click', (e) => {
		if (!navigator.geolocation) {
			alert('La localisation n\'est pas disponible sur cet appareil.');
			return;
		}
		if (watch !== null) {
			map.setView(me.getLatLng(), Math.max(map.getZoom(), 17));
			return;
		}
		e.target.textContent = '📍 Localisation…';
		watch = navigator.geolocation.watchPosition((pos) => {
			const ll = [pos.coords.latitude, pos.coords.longitude];
			if (!me) {
				me = L.circleMarker(ll, { radius: 8, color: '#fff', weight: 3, fillColor: '#2271b1', fillOpacity: 1 }).addTo(map);
				map.setView(ll, Math.max(map.getZoom(), 17));
				e.target.textContent = '📍 Recentrer sur moi';
			} else {
				me.setLatLng(ll);
			}
		}, () => {
			alert('Impossible d\'obtenir votre position. Autorisez la localisation pour ce site dans les réglages du navigateur.');
			e.target.textContent = '📍 Me localiser';
			watch = null;
		}, { enableHighAccuracy: true, maximumAge: 10000 });
	});
})();
