/* TKD Projets — interface d'administration (sans dépendance externe). */
(function () {
	'use strict';

	const root = document.getElementById('tkdp-app');
	const cfg = window.TKDP || {};
	if (!root || !window.wp || !wp.apiFetch) {
		return;
	}

	const api = (path, opts) => wp.apiFetch(Object.assign({ path: '/tkdp/v1' + path }, opts || {}));

	/* ------------------------------------------------------------------ */
	/* Libellés                                                            */
	/* ------------------------------------------------------------------ */

	const STATUS = { todo: 'À faire', doing: 'En cours', done: 'Terminé' };
	const PRIORITY = { low: 'Basse', normal: 'Normale', high: 'Haute' };
	const PROJECT_STATUS = { en_cours: 'En cours', termine: 'Terminé' };
	const TYPES = cfg.types || {};
	const TEAM_ROLES = cfg.teamRoles || { responsable: 'Responsable', membre: 'Membre' };
	const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
	const MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
	const DAYS_SHORT = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
	const ZOOMS = {
		day: { label: 'Jour', width: 34 },
		week: { label: 'Semaine', width: 16 },
		month: { label: 'Mois', width: 5 },
	};

	/* ------------------------------------------------------------------ */
	/* État                                                                */
	/* ------------------------------------------------------------------ */

	const state = {
		projects: [],
		users: [],
		roles: [],
		canMailRole: false,
		usersLoaded: !cfg.canListUsers, // sans ce droit, la liste des personnes n'est pas demandée
		project: null,
		tasks: [],
		view: readPref('view', 'gantt'),
		zoom: readPref('zoom', 'week'),
	};

	function readPref(key, fallback) {
		try {
			return window.localStorage.getItem('tkdp-' + key) || fallback;
		} catch (e) {
			return fallback;
		}
	}

	function savePref(key, value) {
		try {
			window.localStorage.setItem('tkdp-' + key, value);
		} catch (e) {
			/* stockage indisponible : on ignore */
		}
	}

	/* ------------------------------------------------------------------ */
	/* Outils                                                              */
	/* ------------------------------------------------------------------ */

	const DAY = 86400000;

	function esc(value) {
		return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, (c) => ({
			'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
		}[c]));
	}

	/** "AAAA-MM-JJ" -> numéro de jour (UTC, sans souci d'heure d'été). */
	function toDay(str) {
		if (!str) {
			return null;
		}
		const p = str.split('-').map(Number);
		return Math.round(Date.UTC(p[0], p[1] - 1, p[2]) / DAY);
	}

	function fromDay(n) {
		return new Date(n * DAY).toISOString().slice(0, 10);
	}

	function dateOf(n) {
		return new Date(n * DAY);
	}

	function fmtDate(str) {
		if (!str) {
			return '—';
		}
		const d = dateOf(toDay(str));
		return d.getUTCDate() + ' ' + MONTHS_SHORT[d.getUTCMonth()] + ' ' + d.getUTCFullYear();
	}

	/** Numéro de semaine ISO. */
	function isoWeek(n) {
		const weekday = (dateOf(n).getUTCDay() + 6) % 7;
		const thursday = n - weekday + 3;
		const year = dateOf(thursday).getUTCFullYear();
		const jan4 = Math.round(Date.UTC(year, 0, 4) / DAY);
		const firstThursday = jan4 - ((dateOf(jan4).getUTCDay() + 6) % 7) + 3;
		return 1 + Math.round((thursday - firstThursday) / 7);
	}

	const TODAY = toDay(cfg.today) || Math.floor(Date.now() / DAY);

	function fmtSize(bytes) {
		if (!bytes) {
			return '';
		}
		if (bytes < 1024 * 1024) {
			return Math.max(1, Math.round(bytes / 1024)) + ' Ko';
		}
		return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' Mo';
	}

	function clip(t) {
		const n = (t.attachments || []).length;
		const z = t.zone_count || 0;
		return (n ? ' <span class="tkdp-clip" title="' + n + ' pièce' + (n > 1 ? 's' : '') + ' jointe' + (n > 1 ? 's' : '') + '">📎 ' + n + '</span>' : '')
			+ (z ? ' <span class="tkdp-clip" title="' + z + ' zone' + (z > 1 ? 's' : '') + ' sur la carte">🗺️ ' + z + '</span>' : '');
	}

	function openZones(task) {
		if (!window.TKDPZones) {
			notify('Le module de carte n\'est pas chargé.', 'error');
			return;
		}
		window.TKDPZones.open(task, {
			api: api,
			users: state.users,
			esc: esc,
			notify: notify,
			fail: fail,
			cfg: cfg,
			onSaved: (count) => {
				task.zone_count = count;
				const btn = document.querySelector('.tkdp-modal [data-modal="zones"]');
				if (btn) {
					btn.textContent = zonesLabel(task);
				}
				render();
			},
		});
	}

	function zonesLabel(t) {
		return '🗺️ Carte des zones' + (t.zone_count ? ' (' + t.zone_count + ')' : '');
	}

	function initials(name) {
		return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('');
	}

	function assigneeLabel(t) {
		return t.assignee_role ? 'Tous : ' + (t.role_name || t.assignee_role) : t.assignee_name;
	}

	function avatar(t) {
		const label = assigneeLabel(t);
		if (!label) {
			return '';
		}
		return '<span class="tkdp-avatar' + (t.assignee_role ? ' is-role' : '') + '" title="' + esc(label) + '">'
			+ esc(t.assignee_role ? '👥' : initials(label)) + '</span>';
	}

	/** Bouton d'accès direct à la carte de la tâche (bleu si des zones y sont dessinées). */
	function mapButton(t) {
		const z = t.zone_count || 0;
		const title = z ? 'Ouvrir la carte (' + z + ' zone' + (z > 1 ? 's' : '') + ')' : 'Ouvrir la carte (aucune zone pour l\'instant)';
		return '<button type="button" class="tkdp-mapbtn' + (z ? ' has-zones' : '') + '" data-action="zones" data-id="' + t.id + '"'
			+ ' title="' + esc(title) + '" aria-label="' + esc(title) + '">'
			+ '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M15 5.1 9 3 3 5v16l6-2.1 6 2.1 6-2V3l-6 2.1zM10 5.4l4 1.4v11.8l-4-1.4V5.4zM5 6.4l3-1v11.8l-3 1V6.4zm14 11.2-3 1V6.8l3-1v11.8z"/></svg>'
			+ '</button>';
	}

	/** Compte à rebours vers la date de l'évènement du projet : J-45, Jour J, J+2… */
	function countdown(date) {
		const n = toDay(date);
		if (n === null) {
			return null;
		}
		const days = n - TODAY;
		const label = days > 0 ? 'J-' + days : days === 0 ? 'Jour J' : 'J+' + -days;
		const when = days > 1 ? 'dans ' + days + ' jours' : days === 1 ? 'demain' : days === 0 ? 'aujourd\'hui' : 'il y a ' + -days + ' jour' + (days < -1 ? 's' : '');
		const cls = days < 0 ? 'is-past' : days <= 7 ? 'is-soon' : days <= 30 ? 'is-near' : '';
		return { day: n, days: days, label: label, cls: cls, title: 'Évènement le ' + fmtDate(date) + ' (' + when + ')' };
	}

	function countdownBadge(date) {
		const c = countdown(date);
		return c ? '<span class="tkdp-countdown ' + c.cls + '" title="' + esc(c.title) + '">🎉 ' + esc(c.label) + '</span>' : '';
	}

	function taskRange(t) {
		const s = toDay(t.start_date);
		const e = toDay(t.end_date);
		if (s === null && e === null) {
			return null;
		}
		return { start: s === null ? e : s, end: e === null ? s : e };
	}

	function isLate(t) {
		const r = taskRange(t);
		return t.status !== 'done' && r && r.end < TODAY;
	}

	function sortedByDate(tasks) {
		return tasks.slice().sort((a, b) => {
			const ra = taskRange(a);
			const rb = taskRange(b);
			if (!ra && !rb) {
				return a.position - b.position;
			}
			if (!ra) {
				return 1;
			}
			if (!rb) {
				return -1;
			}
			return ra.start - rb.start || ra.end - rb.end || a.id - b.id;
		});
	}

	function notify(message, type) {
		const box = document.createElement('div');
		box.className = 'tkdp-toast' + (type === 'error' ? ' is-error' : '');
		box.textContent = message;
		document.body.appendChild(box);
		setTimeout(() => box.classList.add('is-hiding'), 2600);
		setTimeout(() => box.remove(), 3000);
	}

	function fail(err) {
		notify((err && err.message) || 'Une erreur est survenue.', 'error');
	}

	/* ------------------------------------------------------------------ */
	/* Chargement des données                                              */
	/* ------------------------------------------------------------------ */

	async function loadHome() {
		try {
			const res = await Promise.all([api('/projects'), state.usersLoaded ? null : api('/users')]);
			state.projects = res[0];
			if (res[1]) {
				state.users = res[1].users;
				state.roles = res[1].roles.filter((r) => r.assignable);
				state.canMailRole = !!res[1].canMailRole;
				state.usersLoaded = true;
			}
			state.project = null;
			state.tasks = [];
			const link = /^#projet-(\d+)$/.exec(window.location.hash);
			if (link && state.projects.some((p) => p.id === Number(link[1]))) {
				openProject(Number(link[1]));
				return;
			}
			render();
		} catch (e) {
			root.innerHTML = '<div class="notice notice-error"><p>' + esc(e.message || 'Impossible de charger les projets.') + '</p></div>';
		}
	}

	async function openProject(id) {
		try {
			const project = state.projects.find((p) => p.id === id);
			state.tasks = await api('/projects/' + id + '/tasks');
			state.project = project;
			history.replaceState(null, '', '#projet-' + id);
			render();
		} catch (e) {
			fail(e);
		}
	}

	async function refreshProjectStats() {
		try {
			state.projects = await api('/projects');
			if (state.project) {
				state.project = state.projects.find((p) => p.id === state.project.id) || state.project;
			}
		} catch (e) {
			/* les statistiques se mettront à jour au prochain chargement */
		}
	}

	/* ------------------------------------------------------------------ */
	/* Rendu général                                                       */
	/* ------------------------------------------------------------------ */

	function render() {
		root.innerHTML = state.project ? renderProject() : renderHome();
		if (state.project && state.view === 'kanban') {
			bindKanban();
		}
		if (state.project && state.view === 'gantt') {
			bindGantt();
		}
	}

	function progressBar(pct, color) {
		return '<span class="tkdp-progress"><span style="width:' + pct + '%;background:' + esc(color || '') + '"></span></span>';
	}

	/* ----------------------------- Accueil ----------------------------- */

	function typeBadge(p) {
		const t = TYPES[p.type] || TYPES.autre || { icon: '📁', label: 'Projet' };
		return '<span class="tkdp-type">' + esc(t.icon + ' ' + t.label) + '</span>';
	}

	function teamLine(p) {
		if (!p.team || !p.team.length) {
			return '';
		}
		const names = p.team.slice().sort((a, b) => (a.role === b.role ? 0 : a.role === 'responsable' ? -1 : 1))
			.map((m) => esc(m.name) + (m.role === 'responsable' ? ' <em>(responsable)</em>' : ''));
		return '<p class="tkdp-team-line">👥 ' + names.join(', ') + '</p>';
	}

	function renderHome() {
		let html = '<div class="tkdp-head"><h1>Projets</h1><div class="tkdp-head-actions">'
			+ (cfg.isGestionnaire ? '<button class="button button-primary" data-action="new-project">+ Nouveau projet</button>' : '') + '</div></div>';

		if (!state.projects.length) {
			return html + '<div class="tkdp-empty"><p><strong>Aucun projet pour l\'instant.</strong></p>'
				+ (cfg.isGestionnaire
					? '<p>Créez votre premier projet (gala, fête, compétition) : les tâches habituelles peuvent être ajoutées automatiquement.</p>'
					+ '<button class="button button-primary" data-action="new-project">Créer un projet</button>'
					: '<p>Vous verrez ici les projets dont vous faites partie de l\'équipe.</p>')
				+ '</div>';
		}

		const current = state.projects.filter((p) => p.status !== 'termine');
		const finished = state.projects.filter((p) => p.status === 'termine');
		html += renderCards(current);
		if (finished.length) {
			html += '<h2 class="tkdp-subtitle">Projets terminés</h2>' + renderCards(finished);
		}
		return html;
	}

	function renderCards(projects) {
		let html = '<div class="tkdp-cards">';
		projects.forEach((p) => {
			const pct = p.task_count ? Math.round((p.done_count / p.task_count) * 100) : 0;
			const dates = p.first_date ? fmtDate(p.first_date) + ' → ' + fmtDate(p.last_date) : 'Pas encore de dates';
			html += '<div class="tkdp-card' + (p.status === 'termine' ? ' is-finished' : '') + '" style="--tkdp-color:' + esc(p.color) + '" data-action="open-project" data-id="' + p.id + '">'
				+ '<div class="tkdp-card-title">' + esc(p.name) + (p.status === 'termine' ? '' : countdownBadge(p.event_date)) + '</div>'
				+ '<div class="tkdp-card-meta">' + typeBadge(p) + '</div>'
				+ (p.event_date ? '<div class="tkdp-card-meta">Évènement le ' + esc(fmtDate(p.event_date)) + '</div>' : '')
				+ (p.description ? '<div class="tkdp-card-desc">' + esc(p.description) + '</div>' : '')
				+ '<div class="tkdp-card-meta">' + esc(dates) + '</div>'
				+ progressBar(pct, p.color)
				+ '<div class="tkdp-card-meta">' + p.done_count + ' / ' + p.task_count + ' tâche' + (p.task_count > 1 ? 's' : '') + ' terminée' + (p.done_count > 1 ? 's' : '') + ' · ' + pct + ' %</div>'
				+ '</div>';
		});
		return html + '</div>';
	}

	/* ----------------------------- Projet ------------------------------ */

	function renderProject() {
		const p = state.project;
		const tabs = { list: 'Liste', kanban: 'Kanban', gantt: 'Gantt' };
		let html = '<p><a href="#" data-action="home">← Tous les projets</a></p>'
			+ '<div class="tkdp-head"><h1><span class="tkdp-dot" style="background:' + esc(p.color) + '"></span>' + esc(p.name)
			+ (p.event_date ? countdownBadge(p.event_date) + '<span class="tkdp-event-date">' + esc(fmtDate(p.event_date)) + '</span>' : '')
			+ ' ' + typeBadge(p) + (p.status === 'termine' ? ' <span class="tkdp-badge is-done">Projet terminé</span>' : '') + '</h1>'
			+ '<div class="tkdp-head-actions">'
			+ (p.can.edit_project ? '<button class="button" data-action="edit-project">Modifier le projet</button>' : '')
			+ (p.can.create_task ? '<button class="button button-primary" data-action="new-task">+ Nouvelle tâche</button>' : '') + '</div></div>';

		if (p.description) {
			html += '<p class="tkdp-desc">' + esc(p.description) + '</p>';
		}
		html += teamLine(p);
		if (p.my_role === 'membre') {
			html += '<p class="description">Vous êtes membre de l\'équipe : vous pouvez mettre à jour les tâches qui vous sont attribuées.</p>';
		}

		html += '<div class="tkdp-toolbar"><div class="tkdp-tabs">';
		Object.keys(tabs).forEach((k) => {
			html += '<button class="tkdp-tab' + (state.view === k ? ' is-active' : '') + '" data-action="view" data-view="' + k + '">' + tabs[k] + '</button>';
		});
		html += '</div>';
		if (state.view === 'gantt') {
			html += '<div class="tkdp-tabs tkdp-zoom">';
			Object.keys(ZOOMS).forEach((k) => {
				html += '<button class="tkdp-tab' + (state.zoom === k ? ' is-active' : '') + '" data-action="zoom" data-zoom="' + k + '">' + ZOOMS[k].label + '</button>';
			});
			html += '<button class="tkdp-tab" data-action="scroll-today">Aujourd\'hui</button></div>';
		}
		html += '</div>';

		if (!state.tasks.length) {
			return html + '<div class="tkdp-empty"><p><strong>Ce projet n\'a pas encore de tâches.</strong></p>'
				+ (p.can.create_task ? '<button class="button button-primary" data-action="new-task">Ajouter une tâche</button>' : '') + '</div>';
		}

		if (state.view === 'list') {
			html += renderList();
		} else if (state.view === 'kanban') {
			html += renderKanban();
		} else {
			html += renderGantt();
		}
		return html;
	}

	/* ------------------------------ Liste ------------------------------ */

	function statusBadge(status) {
		return '<span class="tkdp-badge is-' + status + '">' + STATUS[status] + '</span>';
	}

	function priorityBadge(priority) {
		return priority === 'normal' ? '' : '<span class="tkdp-prio is-' + priority + '">' + PRIORITY[priority] + '</span>';
	}

	function renderList() {
		let html = '<table class="widefat striped tkdp-table"><thead><tr>'
			+ '<th>Tâche</th><th>Statut</th><th>Priorité</th><th>Responsable</th><th>Début</th><th>Fin</th><th>Avancement</th><th></th>'
			+ '</tr></thead><tbody>';
		sortedByDate(state.tasks).forEach((t) => {
			html += '<tr>'
				+ '<td><a href="#" data-action="edit-task" data-id="' + t.id + '"><strong>' + esc(t.title) + '</strong></a>' + clip(t)
				+ (isLate(t) ? ' <span class="tkdp-late">En retard</span>' : '') + '</td>'
				+ '<td>' + statusBadge(t.status) + '</td>'
				+ '<td>' + (PRIORITY[t.priority] || '') + '</td>'
				+ '<td>' + (esc(assigneeLabel(t)) || '—') + '</td>'
				+ '<td>' + fmtDate(t.start_date) + '</td>'
				+ '<td>' + fmtDate(t.end_date) + '</td>'
				+ '<td class="tkdp-cell-progress">' + progressBar(t.progress, state.project.color) + ' ' + t.progress + ' %</td>'
				+ '<td class="tkdp-row-actions">' + [
					'<button class="button-link" data-action="edit-task" data-id="' + t.id + '">' + (t.access === 'none' ? 'Voir' : 'Modifier') + '</button>',
					state.project.can.zones ? '<button class="button-link" data-action="zones" data-id="' + t.id + '">Carte</button>' : '',
					state.project.can.delete_task ? '<button class="button-link tkdp-danger" data-action="delete-task" data-id="' + t.id + '">Supprimer</button>' : '',
				].filter(Boolean).join(' | ') + '</td>'
				+ '</tr>';
		});
		return html + '</tbody></table>';
	}

	/* ------------------------------ Kanban ----------------------------- */

	function renderKanban() {
		let html = '<div class="tkdp-kanban">';
		Object.keys(STATUS).forEach((status) => {
			const tasks = state.tasks.filter((t) => t.status === status).sort((a, b) => a.position - b.position);
			html += '<div class="tkdp-col" data-status="' + status + '">'
				+ '<div class="tkdp-col-head">' + statusBadge(status) + ' <span class="tkdp-count">' + tasks.length + '</span></div>'
				+ '<div class="tkdp-col-body">';
			tasks.forEach((t) => {
				html += '<div class="tkdp-kcard' + (t.access === 'none' ? ' is-locked' : '') + '" draggable="' + (t.access === 'none' ? 'false' : 'true') + '" data-id="' + t.id + '">'
					+ '<div class="tkdp-kcard-title">' + esc(t.title) + '</div>'
					+ '<div class="tkdp-kcard-meta">' + priorityBadge(t.priority) + clip(t)
					+ (isLate(t) ? '<span class="tkdp-late">En retard</span>' : '')
					+ (t.end_date ? '<span>📅 ' + fmtDate(t.end_date) + '</span>' : '') + '</div>'
					+ (t.status === 'doing' ? progressBar(t.progress, state.project.color) : '')
					+ avatar(t)
					+ '</div>';
			});
			html += (state.project.can.create_task ? '<button class="tkdp-add" data-action="new-task" data-status="' + status + '">+ Ajouter</button>' : '<span class="tkdp-add-placeholder"></span>') + '</div></div>';
		});
		return html + '</div>';
	}

	function bindKanban() {
		let dragged = null;
		let moved = false;

		root.querySelectorAll('.tkdp-kcard').forEach((card) => {
			card.addEventListener('dragstart', (e) => {
				dragged = card;
				moved = false;
				card.classList.add('is-dragging');
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', card.dataset.id);
			});
			card.addEventListener('dragend', () => {
				card.classList.remove('is-dragging');
				root.querySelectorAll('.tkdp-col').forEach((c) => c.classList.remove('is-over'));
				if (moved) {
					saveKanban(card);
				}
				dragged = null;
			});
			card.addEventListener('click', () => openTaskModal(state.tasks.find((t) => t.id === Number(card.dataset.id))));
		});

		root.querySelectorAll('.tkdp-col').forEach((col) => {
			const body = col.querySelector('.tkdp-col-body');
			col.addEventListener('dragover', (e) => {
				if (!dragged) {
					return;
				}
				e.preventDefault();
				col.classList.add('is-over');
				const after = Array.from(body.querySelectorAll('.tkdp-kcard:not(.is-dragging)')).find((c) => {
					const box = c.getBoundingClientRect();
					return e.clientY < box.top + box.height / 2;
				});
				body.insertBefore(dragged, after || body.querySelector('.tkdp-add, .tkdp-add-placeholder'));
				moved = true;
			});
			col.addEventListener('dragleave', (e) => {
				if (!col.contains(e.relatedTarget)) {
					col.classList.remove('is-over');
				}
			});
			col.addEventListener('drop', (e) => e.preventDefault());
		});
	}

	async function saveKanban(card) {
		const id = Number(card.dataset.id);
		const status = card.closest('.tkdp-col').dataset.status;
		const task = state.tasks.find((t) => t.id === id);
		const order = Array.from(root.querySelectorAll('.tkdp-kcard')).map((c) => Number(c.dataset.id));
		try {
			if (task.status !== status) {
				const data = { status: status };
				if (status === 'done') {
					data.progress = 100;
				} else if (task.status === 'done' && task.progress === 100) {
					data.progress = status === 'todo' ? 0 : 50;
				}
				Object.assign(task, await api('/tasks/' + id, { method: 'PUT', data: data }));
				refreshProjectStats();
			}
			if (state.project.can.reorder) {
				await api('/projects/' + state.project.id + '/reorder', { method: 'POST', data: { order: order } });
				order.forEach((tid, i) => {
					const t = state.tasks.find((x) => x.id === tid);
					if (t) {
						t.position = i;
					}
				});
			}
		} catch (e) {
			fail(e);
		}
		render();
	}

	/* ------------------------------ Gantt ------------------------------ */

	const ROW = 38;
	const HEAD_BASE = 52; // mois + semaines/jours/mois
	const DAY_ROW = 18; // ligne des jours (vue Semaine)
	let gantt = null; // géométrie du dernier rendu

	function renderGantt() {
		const tasks = sortedByDate(state.tasks);
		const dayW = ZOOMS[state.zoom].width;
		const showDayRow = state.zoom === 'week';
		const HEAD = HEAD_BASE + (showDayRow ? DAY_ROW : 0);
		const ranges = tasks.map(taskRange).filter(Boolean);

		const event = countdown(state.project.event_date);
		const marks = event ? [TODAY, event.day] : [TODAY];
		let min = Math.min(...marks, ...ranges.map((r) => r.start)) - 7;
		let max = Math.max(...marks, ...ranges.map((r) => r.end)) + 14;

		if (state.zoom === 'month') {
			const d = dateOf(min);
			min = Math.round(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1) / DAY);
		} else {
			min -= (dateOf(min).getUTCDay() + 6) % 7; // commence un lundi
		}
		const minWidth = Math.max(600, (root.clientWidth || 1000) - 300);
		max = Math.max(max, min + Math.ceil(minWidth / dayW));

		const days = max - min + 1;
		const width = days * dayW;
		const height = HEAD + tasks.length * ROW;
		const x = (n) => (n - min) * dayW;
		gantt = { min: min, dayW: dayW, tasks: tasks, scrollLeft: gantt ? gantt.scrollLeft : undefined };

		let svg = '<svg class="tkdp-gantt-svg" width="' + width + '" height="' + height + '" xmlns="http://www.w3.org/2000/svg">'
			+ '<defs><marker id="tkdp-arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="7" markerHeight="7" orient="auto">'
			+ '<path d="M0,0 L8,4 L0,8 z" fill="#8c8f94"/></marker></defs>';

		// Lignes alternées
		tasks.forEach((t, i) => {
			if (i % 2) {
				svg += '<rect x="0" y="' + (HEAD + i * ROW) + '" width="' + width + '" height="' + ROW + '" fill="#f6f7f7"/>';
			}
		});

		// Week-ends, quadrillage et en-tête
		let header = '';
		for (let n = min; n <= max; n++) {
			const d = dateOf(n);
			const wd = d.getUTCDay();
			const xx = x(n);
			if (state.zoom !== 'month' && (wd === 0 || wd === 6)) {
				svg += '<rect x="' + xx + '" y="' + HEAD + '" width="' + dayW + '" height="' + (height - HEAD) + '" fill="#1d23270a"/>';
			}
			const isMonthStart = d.getUTCDate() === 1;
			const isMonday = wd === 1;
			const grid = state.zoom === 'day' || (state.zoom === 'week' && isMonday) || (state.zoom === 'month' && isMonthStart);
			if (grid) {
				svg += '<line x1="' + xx + '" y1="' + 26 + '" x2="' + xx + '" y2="' + height + '" stroke="#dcdcde"/>';
			}
			const daysLeftInMonth = Math.round(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 1) / DAY) - n;
			if (isMonthStart || (n === min && daysLeftInMonth * dayW > 130)) {
				const label = state.zoom === 'month' ? (d.getUTCMonth() === 0 || n === min ? String(d.getUTCFullYear()) : '') : MONTHS[d.getUTCMonth()] + ' ' + d.getUTCFullYear();
				if (label) {
					header += '<text x="' + (xx + 6) + '" y="17" class="tkdp-h1">' + label + '</text>';
				}
				header += '<line x1="' + xx + '" y1="0" x2="' + xx + '" y2="' + 26 + '" stroke="#c3c4c7"/>';
			}
			if (state.zoom === 'day') {
				header += '<text x="' + (xx + dayW / 2) + '" y="' + (HEAD_BASE - 8) + '" class="tkdp-h2' + (n === TODAY ? ' is-today' : '') + '" text-anchor="middle">'
					+ DAYS_SHORT[wd] + ' ' + d.getUTCDate() + '</text>';
			} else if (state.zoom === 'week' && isMonday) {
				header += '<text x="' + (xx + 3.5 * dayW) + '" y="' + (HEAD_BASE - 8) + '" class="tkdp-h2" text-anchor="middle">S' + isoWeek(n) + '</text>';
			} else if (state.zoom === 'month' && isMonthStart) {
				header += '<text x="' + (xx + 15 * dayW) + '" y="' + (HEAD_BASE - 8) + '" class="tkdp-h2" text-anchor="middle">' + MONTHS_SHORT[d.getUTCMonth()] + '</text>';
			}
			// Vue Semaine : numéro de chaque jour sous la semaine
			if (showDayRow) {
				const cls = 'tkdp-h3' + (wd === 0 || wd === 6 ? ' is-weekend' : '') + (n === TODAY ? ' is-today' : '');
				if (!isMonday) {
					header += '<line x1="' + xx + '" y1="' + HEAD_BASE + '" x2="' + xx + '" y2="' + HEAD + '" stroke="#f0f0f1"/>';
				}
				header += '<text x="' + (xx + dayW / 2) + '" y="' + (HEAD - 5) + '" class="' + cls + '" text-anchor="middle">'
					+ '<title>' + DAYS_SHORT[wd] + ' ' + d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()] + '</title>' + d.getUTCDate() + '</text>';
			}
		}
		svg += '<rect x="0" y="0" width="' + width + '" height="' + HEAD + '" fill="#fff"/>' + header
			+ '<line x1="0" y1="' + 26 + '" x2="' + width + '" y2="' + 26 + '" stroke="#dcdcde"/>'
			+ (showDayRow ? '<line x1="0" y1="' + HEAD_BASE + '" x2="' + width + '" y2="' + HEAD_BASE + '" stroke="#dcdcde"/>' : '')
			+ '<line x1="0" y1="' + HEAD + '" x2="' + width + '" y2="' + HEAD + '" stroke="#c3c4c7"/>';

		// Dépendances
		const rowOf = {};
		tasks.forEach((t, i) => {
			rowOf[t.id] = i;
		});
		tasks.forEach((t, i) => {
			const dep = t.depends_on ? tasks[rowOf[t.depends_on]] : null;
			const r1 = dep ? taskRange(dep) : null;
			const r2 = taskRange(t);
			if (!r1 || !r2) {
				return;
			}
			const x1 = x(r1.end + 1);
			const y1 = HEAD + rowOf[dep.id] * ROW + ROW / 2;
			const x2 = x(r2.start);
			const y2 = HEAD + i * ROW + ROW / 2;
			const path = x2 - x1 >= 16
				? 'M' + x1 + ',' + y1 + ' H' + (x1 + 8) + ' V' + y2 + ' H' + x2
				: 'M' + x1 + ',' + y1 + ' H' + (x1 + 8) + ' V' + (y2 - ROW / 2) + ' H' + (x2 - 10) + ' V' + y2 + ' H' + x2;
			svg += '<path d="' + path + '" fill="none" stroke="#8c8f94" stroke-width="1.5" marker-end="url(#tkdp-arrow)"/>';
		});

		// Barres
		tasks.forEach((t, i) => {
			const r = taskRange(t);
			const y = HEAD + i * ROW;
			if (!r) {
				svg += '<text x="' + (x(TODAY) + 8) + '" y="' + (y + ROW / 2 + 4) + '" class="tkdp-nodate" data-action="edit-task" data-id="' + t.id + '">Pas de dates — cliquez pour en ajouter</text>';
				return;
			}
			const bx = x(r.start);
			const bw = (r.end - r.start + 1) * dayW;
			const color = t.status === 'done' ? '#00a32a' : state.project.color;
			svg += '<g class="tkdp-bar" data-id="' + t.id + '">'
				+ '<rect class="tkdp-bar-bg" x="' + bx + '" y="' + (y + 9) + '" width="' + bw + '" height="20" rx="4" fill="' + esc(color) + '" fill-opacity="0.3"'
				+ (isLate(t) ? ' stroke="#d63638" stroke-width="1.5"' : '') + '/>'
				+ '<rect class="tkdp-bar-fill" x="' + bx + '" y="' + (y + 9) + '" width="' + (bw * t.progress) / 100 + '" height="20" rx="4" fill="' + esc(color) + '"/>'
				+ '<rect class="tkdp-handle" x="' + (bx + bw - 7) + '" y="' + (y + 9) + '" width="7" height="20" rx="3"/>'
				+ '<text class="tkdp-bar-label" x="' + (bx + bw + 6) + '" y="' + (y + ROW / 2 + 4) + '">' + esc(t.title)
				+ (t.progress ? ' · ' + t.progress + ' %' : '') + '</text>'
				+ '<title>' + esc(t.title) + ' — ' + fmtDate(t.start_date || t.end_date) + ' → ' + fmtDate(t.end_date || t.start_date) + '</title>'
				+ '</g>';
		});

		// Aujourd'hui
		svg += '<line x1="' + (x(TODAY) + dayW / 2) + '" y1="' + 26 + '" x2="' + (x(TODAY) + dayW / 2) + '" y2="' + height + '" stroke="#d63638" stroke-width="1.5" stroke-dasharray="4 3"/>';
		// Date de l'évènement : trait plein + étiquette dans l'en-tête
		if (event) {
			const ex = x(event.day) + dayW / 2;
			const color = esc(state.project.color);
			const text = '🎉 Évènement · ' + event.label;
			const w = text.length * 6.4 + 14;
			const lx = Math.min(Math.max(ex - w / 2, 2), width - w - 2);
			svg += '<g class="tkdp-event-mark"><title>' + esc(event.title) + '</title>'
				+ '<line x1="' + ex + '" y1="26" x2="' + ex + '" y2="' + height + '" stroke="' + color + '" stroke-width="2.5"/>'
				+ '<rect x="' + lx + '" y="4" width="' + w + '" height="18" rx="9" fill="' + color + '"/>'
				+ '<text x="' + (lx + w / 2) + '" y="17" class="tkdp-event-label" text-anchor="middle">' + esc(text) + '</text></g>';
		}
		svg += '</svg>';

		// Colonne de gauche
		let left = '<div class="tkdp-gantt-left"><div class="tkdp-gantt-lhead" style="height:' + HEAD + 'px">Tâches</div>';
		tasks.forEach((t) => {
			left += '<div class="tkdp-gantt-row" style="height:' + ROW + 'px" data-action="edit-task" data-id="' + t.id + '" title="' + esc(t.title) + '">'
				+ '<span class="tkdp-sdot is-' + t.status + '"></span><span class="tkdp-gantt-title">' + esc(t.title) + '</span>'
				+ (state.project.can.zones ? mapButton(t) : '')
				+ avatar(t)
				+ '</div>';
		});
		left += '</div>';

		return '<div class="tkdp-gantt">' + left + '<div class="tkdp-gantt-scroll">' + svg + '</div></div>'
			+ '<p class="description">' + (state.project.can.edit_task
				? 'Astuce : faites glisser une barre pour décaler la tâche, ou tirez son bord droit pour changer sa durée. Un simple clic ouvre la tâche.'
				: 'Un clic sur une barre ouvre la tâche.') + '</p>';
	}

	function scrollGanttToToday(smooth) {
		const scroller = root.querySelector('.tkdp-gantt-scroll');
		if (scroller && gantt) {
			const left = (TODAY - gantt.min) * gantt.dayW - scroller.clientWidth / 3;
			scroller.scrollTo({ left: Math.max(0, left), behavior: smooth ? 'smooth' : 'auto' });
		}
	}

	function bindGantt() {
		const scroller = root.querySelector('.tkdp-gantt-scroll');
		if (!scroller) {
			return;
		}
		if (typeof gantt.scrollLeft === 'number') {
			scroller.scrollLeft = gantt.scrollLeft;
		} else {
			scrollGanttToToday(false);
		}
		scroller.addEventListener('scroll', () => {
			gantt.scrollLeft = scroller.scrollLeft;
		});

		root.querySelectorAll('.tkdp-bar').forEach((g) => {
			g.addEventListener('pointerdown', (e) => startDrag(e, g));
		});
	}

	function startDrag(e, g) {
		if (e.button !== 0) {
			return;
		}
		e.preventDefault();
		const id = Number(g.dataset.id);
		const task = state.tasks.find((t) => t.id === id);
		if (task.access !== 'full') {
			openTaskModal(task); // seules les personnes qui peuvent tout modifier déplacent les dates
			return;
		}
		const r = taskRange(task);
		const mode = e.target.classList.contains('tkdp-handle') ? 'resize' : 'move';
		const bg = g.querySelector('.tkdp-bar-bg');
		const fill = g.querySelector('.tkdp-bar-fill');
		const handle = g.querySelector('.tkdp-handle');
		const label = g.querySelector('.tkdp-bar-label');
		const base = {
			w: parseFloat(bg.getAttribute('width')),
			hx: parseFloat(handle.getAttribute('x')),
			lx: parseFloat(label.getAttribute('x')),
		};
		const startX = e.clientX;
		let delta = 0;
		let movedPx = 0;

		g.setPointerCapture(e.pointerId);
		g.classList.add('is-dragging');

		const onMove = (ev) => {
			movedPx = Math.max(movedPx, Math.abs(ev.clientX - startX));
			delta = Math.round((ev.clientX - startX) / gantt.dayW);
			if (mode === 'move') {
				g.setAttribute('transform', 'translate(' + delta * gantt.dayW + ',0)');
			} else {
				delta = Math.max(delta, r.start - r.end); // durée minimale : 1 jour
				const w = base.w + delta * gantt.dayW;
				bg.setAttribute('width', w);
				fill.setAttribute('width', (w * task.progress) / 100);
				handle.setAttribute('x', base.hx + delta * gantt.dayW);
				label.setAttribute('x', base.lx + delta * gantt.dayW);
			}
		};

		const onUp = async () => {
			g.removeEventListener('pointermove', onMove);
			g.removeEventListener('pointerup', onUp);
			g.removeEventListener('pointercancel', onUp);
			g.classList.remove('is-dragging');

			if (movedPx < 4) {
				openTaskModal(task);
				return;
			}
			if (!delta) {
				render();
				return;
			}
			const data = mode === 'move'
				? { start_date: fromDay(r.start + delta), end_date: fromDay(r.end + delta) }
				: { start_date: fromDay(r.start), end_date: fromDay(r.end + delta) };
			try {
				Object.assign(task, await api('/tasks/' + id, { method: 'PUT', data: data }));
				refreshProjectStats();
			} catch (err) {
				fail(err);
			}
			render();
		};

		g.addEventListener('pointermove', onMove);
		g.addEventListener('pointerup', onUp);
		g.addEventListener('pointercancel', onUp);
	}

	/* ------------------------------------------------------------------ */
	/* Fenêtres (formulaires)                                              */
	/* ------------------------------------------------------------------ */

	/** submitLabel === false : fenêtre en lecture seule (un seul bouton « Fermer »). */
	function openModal(title, body, onSubmit, extraButtons, submitLabel) {
		closeModal();
		const wrap = document.createElement('div');
		wrap.className = 'tkdp-modal-wrap';
		wrap.innerHTML = '<form class="tkdp-modal" novalidate>'
			+ '<div class="tkdp-modal-head"><h2>' + esc(title) + '</h2><button type="button" class="tkdp-close" aria-label="Fermer">×</button></div>'
			+ '<div class="tkdp-modal-body">' + body + '</div>'
			+ '<div class="tkdp-modal-foot">' + (extraButtons || '<span></span>')
			+ (submitLabel === false
				? '<span><button type="button" class="button tkdp-cancel">Fermer</button></span>'
				: '<span><button type="button" class="button tkdp-cancel">Annuler</button> '
				+ '<button type="submit" class="button button-primary">' + esc(submitLabel || 'Enregistrer') + '</button></span>')
			+ '</div></form>';
		document.body.appendChild(wrap);

		const form = wrap.querySelector('form');
		const first = form.querySelector('input, textarea, select');
		if (first) {
			first.focus();
		}
		wrap.addEventListener('mousedown', (e) => {
			if (e.target === wrap) {
				closeModal();
			}
		});
		wrap.querySelector('.tkdp-close').addEventListener('click', closeModal);
		wrap.querySelector('.tkdp-cancel').addEventListener('click', closeModal);
		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			const btn = form.querySelector('[type=submit]');
			if (!btn) {
				return;
			}
			btn.disabled = true;
			try {
				const keepOpen = await onSubmit(Object.fromEntries(new FormData(form).entries()), form);
				if (keepOpen === false) {
					btn.disabled = false;
				} else {
					closeModal();
				}
			} catch (err) {
				fail(err);
				btn.disabled = false;
			}
		});
		return wrap;
	}

	function closeModal() {
		const open = document.querySelector('.tkdp-modal-wrap');
		if (open) {
			open.remove();
		}
	}

	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && !document.body.classList.contains('tkdp-map-open')) {
			closeModal();
		}
	});

	function options(map, selected) {
		return Object.keys(map).map((k) => '<option value="' + esc(k) + '"' + (String(k) === String(selected) ? ' selected' : '') + '>' + esc(map[k]) + '</option>').join('');
	}

	/* ----------------------------- Projet ------------------------------ */

	/** Types proposés à la création, plus le type actuel du projet s'il n'est plus proposé. */
	function typeOptions(current) {
		return Object.keys(TYPES).filter((k) => TYPES[k].proposed || k === current)
			.map((k) => '<option value="' + esc(k) + '"' + (k === current ? ' selected' : '') + '>' + esc(TYPES[k].icon + ' ' + TYPES[k].label) + '</option>').join('');
	}

	function templateLabel(type) {
		const n = (TYPES[type] || {}).count || 0;
		return n ? 'Ajouter les ' + n + ' tâches habituelles d\'un projet « ' + TYPES[type].label + ' » (datées à partir de la date de l\'évènement)' : '';
	}

	function openProjectModal(project) {
		const firstType = Object.keys(TYPES).find((k) => TYPES[k].proposed) || 'autre';
		const p = project || { name: '', description: '', color: (TYPES[firstType] || {}).color || '#2271b1', event_date: '', type: firstType, status: 'en_cours', team: [], can: { manage_members: true } };
		const canTeam = !project || p.can.manage_members;
		const body = '<p><label>Nom du projet *<input type="text" name="name" required maxlength="190" value="' + esc(p.name) + '"></label></p>'
			+ '<div class="tkdp-grid">'
			+ '<label>Type<select name="type">' + typeOptions(p.type) + '</select></label>'
			+ (project ? '<label>État<select name="status">' + options(PROJECT_STATUS, p.status) + '</select></label>' : '<span></span>')
			+ '</div>'
			+ '<p class="description tkdp-type-hint">' + esc((TYPES[p.type] || {}).hint || '') + '</p>'
			+ '<p><label>Date de l\'évènement<input type="date" name="event_date" value="' + esc(p.event_date || '') + '"></label>'
			+ '<span class="description">Affiche un compte à rebours (J-X) et un repère sur le diagramme de Gantt.</span></p>'
			+ '<p><label>Description<textarea name="description" rows="3">' + esc(p.description) + '</textarea></label></p>'
			+ '<p><label>Couleur <input type="color" name="color" value="' + esc(p.color) + '"></label></p>'
			+ (canTeam && cfg.canListUsers ? '<div class="tkdp-files"><span class="tkdp-files-label">Équipe</span><ul class="tkdp-team-list"></ul>'
				+ '<select class="tkdp-team-add" aria-label="Ajouter une personne à l\'équipe"></select>'
				+ '<p class="description">Les responsables gèrent tout le projet ; les membres voient le projet et mettent à jour leurs tâches. '
				+ 'Une personne à qui l\'on attribue une tâche rejoint l\'équipe comme membre.</p></div>' : '')
			+ (project ? '' : '<p class="tkdp-check tkdp-template"><label><input type="checkbox" name="apply_template" value="1" checked> <span></span></label></p>');
		const del = project && p.can.delete_project
			? '<button type="button" class="button-link tkdp-danger" data-modal="delete-project">Supprimer le projet</button>'
			: '';

		const team = (p.team || []).map((m) => ({ user_id: m.user_id, name: m.name, role: m.role }));

		const wrap = openModal(project ? 'Modifier le projet' : 'Nouveau projet', body, async (data) => {
			if (!data.name.trim()) {
				throw new Error('Le nom du projet est obligatoire.');
			}
			if (canTeam && cfg.canListUsers) {
				data.team = team.map((m) => ({ user_id: m.user_id, role: m.role }));
			}
			if (!project) {
				data.apply_template = !!data.apply_template;
			}
			if (project) {
				const saved = await api('/projects/' + project.id, { method: 'PUT', data: data });
				Object.assign(project, saved);
				render();
				notify('Projet enregistré.');
			} else {
				const saved = await api('/projects', { method: 'POST', data: data });
				state.projects.unshift(saved);
				notify('Projet créé.');
				openProject(saved.id);
			}
		}, del);

		const form = wrap.querySelector('form');
		const typeSel = form.querySelector('[name=type]');
		const tpl = form.querySelector('.tkdp-template');
		const refreshType = () => {
			const t = TYPES[typeSel.value] || {};
			form.querySelector('.tkdp-type-hint').textContent = t.hint || '';
			if (tpl) {
				const label = templateLabel(typeSel.value);
				tpl.hidden = !label;
				tpl.querySelector('span').textContent = label;
				tpl.querySelector('input').checked = !!label;
				if (t.color) {
					form.querySelector('[name=color]').value = t.color;
				}
			}
		};
		typeSel.addEventListener('change', refreshType);
		if (tpl) {
			refreshType();
		}

		// Équipe : liste des personnes avec leur rôle, et menu pour en ajouter.
		const teamList = form.querySelector('.tkdp-team-list');
		const teamAdd = form.querySelector('.tkdp-team-add');
		const renderTeam = () => {
			if (!teamList) {
				return;
			}
			teamList.innerHTML = team.length ? team.map((m, i) => '<li><span class="tkdp-team-name">' + esc(m.name) + '</span>'
				+ '<select data-index="' + i + '" aria-label="Rôle de ' + esc(m.name) + '">' + options(TEAM_ROLES, m.role) + '</select>'
				+ '<button type="button" class="tkdp-file-remove" data-index="' + i + '" aria-label="Retirer ' + esc(m.name) + '">×</button></li>').join('')
				: '<li class="tkdp-file-empty">Personne pour l\'instant' + (cfg.isGestionnaire ? ' (les gestionnaires voient tous les projets).' : '.') + '</li>';
			const free = state.users.filter((u) => !team.some((m) => m.user_id === u.id));
			teamAdd.innerHTML = '<option value="">+ Ajouter une personne…</option>'
				+ free.map((u) => '<option value="' + u.id + '">' + esc(u.name) + '</option>').join('');
		};
		if (teamList) {
			renderTeam();
			teamList.addEventListener('change', (e) => {
				const sel = e.target.closest('select[data-index]');
				if (sel) {
					team[Number(sel.dataset.index)].role = sel.value;
				}
			});
			teamList.addEventListener('click', (e) => {
				const btn = e.target.closest('.tkdp-file-remove');
				if (btn) {
					team.splice(Number(btn.dataset.index), 1);
					renderTeam();
				}
			});
			teamAdd.addEventListener('change', () => {
				const u = state.users.find((x) => x.id === Number(teamAdd.value));
				if (u) {
					team.push({ user_id: u.id, name: u.name, role: team.length ? 'membre' : 'responsable' });
					renderTeam();
				}
			});
		}

		const delBtn = wrap.querySelector('[data-modal="delete-project"]');
		if (delBtn) {
			delBtn.addEventListener('click', async () => {
				if (!window.confirm('Supprimer définitivement le projet « ' + project.name + ' » et toutes ses tâches ? Cette action est irréversible.')) {
					return;
				}
				try {
					await api('/projects/' + project.id, { method: 'DELETE' });
					closeModal();
					notify('Projet supprimé.');
					loadHome();
				} catch (e) {
					fail(e);
				}
			});
		}
	}

	/* ------------------------------ Tâche ------------------------------ */

	function openTaskModal(task, defaults) {
		const t = task || Object.assign({
			title: '', description: '', status: 'todo', priority: 'normal', assignee: 0,
			start_date: fromDay(TODAY), end_date: fromDay(TODAY + 6), progress: 0, depends_on: null,
		}, defaults || {});

		// Droits : « full » (tout), « own » (sa tâche : statut, avancement, description, pièces jointes), « none » (lecture).
		const access = task ? task.access : 'full';
		const full = access === 'full';
		const own = access === 'own';
		const lock = (editable) => (editable ? '' : ' disabled');
		const canZones = state.project.can.zones;

		const deps = { 0: '— Aucune —' };
		state.tasks.filter((x) => !task || x.id !== task.id).forEach((x) => {
			deps[x.id] = x.title;
		});

		const body = (own ? '<p class="description">Cette tâche vous est attribuée : vous pouvez changer son statut, son avancement, sa description et ses pièces jointes.</p>' : '')
			+ (access === 'none' ? '<p class="description">Lecture seule : seuls les responsables du projet et la personne attribuée modifient cette tâche.</p>' : '')
			+ '<p><label>Titre *<input type="text" name="title" required maxlength="255" value="' + esc(t.title) + '"' + lock(full) + '></label></p>'
			+ '<p><label>Description<textarea name="description" rows="3"' + lock(full || own) + '>' + esc(t.description) + '</textarea></label></p>'
			+ '<div class="tkdp-grid">'
			+ '<label>Statut<select name="status"' + lock(full || own) + '>' + options(STATUS, t.status) + '</select></label>'
			+ '<label>Priorité<select name="priority"' + lock(full) + '>' + options(PRIORITY, t.priority) + '</select></label>'
			+ '<label>Début<input type="date" name="start_date" value="' + esc(t.start_date || '') + '"' + lock(full) + '></label>'
			+ '<label>Date limite<input type="date" name="end_date" value="' + esc(t.end_date || '') + '"' + lock(full) + '></label>'
			+ '<label>Attribuer à' + assignSelect(t, !full) + '</label>'
			+ '<label>Dépend de<select name="depends_on"' + lock(full) + '>' + options(deps, t.depends_on || 0) + '</select></label>'
			+ '</div>'
			+ '<p><label>Avancement : <output>' + t.progress + '</output> %'
			+ '<input type="range" name="progress" min="0" max="100" step="5" value="' + t.progress + '"' + lock(full || own) + '></label></p>'
			+ '<div class="tkdp-files"><span class="tkdp-files-label">Pièces jointes</span><ul class="tkdp-file-list"></ul>'
			+ (!full && !own ? ''
				: window.wp && wp.media && cfg.canUpload
					? '<button type="button" class="button" data-modal="add-file">📎 Ajouter une pièce jointe</button>'
					: '<p class="description">Votre compte ne peut pas envoyer de fichiers.</p>')
			+ '</div>'
			+ (canZones ? '<div class="tkdp-files"><span class="tkdp-files-label">Carte</span>'
				+ (task
					? '<button type="button" class="button" data-modal="zones">' + esc(zonesLabel(task)) + '</button>'
					+ ' <span class="description">Délimitez des secteurs (ex. distribution de flyers) et affectez-les à des personnes.</span>'
					: '<p class="description">Enregistrez d\'abord la tâche pour pouvoir dessiner des zones sur une carte.</p>')
				+ '</div>' : '')
			+ (full ? '<p class="tkdp-check"><label><input type="checkbox" name="notify" value="1" checked> '
				+ 'Prévenir par e-mail <span>(si la tâche est attribuée à quelqu\'un de nouveau ou si sa date limite change)</span></label></p>' : '');
		const del = task && state.project.can.delete_task ? '<button type="button" class="button-link tkdp-danger" data-modal="delete-task">Supprimer la tâche</button>' : '';
		const title = !task ? 'Nouvelle tâche' : access === 'none' ? 'Tâche' : 'Modifier la tâche';

		const wrap = openModal(title, body, async (data) => {
			if (full) {
				if (!data.title.trim()) {
					throw new Error('Le titre de la tâche est obligatoire.');
				}
				if (data.start_date && data.end_date && data.end_date < data.start_date) {
					throw new Error('La date de fin doit être postérieure ou égale à la date de début.');
				}
				const assign = data.assign || '';
				delete data.assign;
				data.assignee = assign.indexOf('u:') === 0 ? Number(assign.slice(2)) : 0;
				data.assignee_role = assign.indexOf('r:') === 0 ? assign.slice(2) : '';
				data.notify = data.notify ? 1 : 0;
			}
			data.attachments = files.map((f) => f.id);

			const saved = task
				? await api('/tasks/' + task.id, { method: 'PUT', data: data })
				: await api('/projects/' + state.project.id + '/tasks', { method: 'POST', data: data });
			const sent = saved.notified;
			delete saved.notified;
			if (task) {
				Object.assign(task, saved);
			} else {
				state.tasks.push(saved);
			}
			if (sent === 0) {
				notify('Tâche enregistrée, mais l\'e-mail n\'a pas pu être envoyé. Vérifiez la configuration e-mail du site.', 'error');
			} else if (sent > 0) {
				notify((task ? 'Tâche enregistrée' : 'Tâche ajoutée') + ' — e-mail envoyé à ' + sent + ' personne' + (sent > 1 ? 's' : '') + '.');
			} else {
				notify(task ? 'Tâche enregistrée.' : 'Tâche ajoutée.');
			}
			await refreshProjectStats();
			render();
		}, del, access === 'none' ? false : undefined);

		const form = wrap.querySelector('form');

		// Pièces jointes (médiathèque WordPress)
		const files = (t.attachments || []).slice();
		const list = form.querySelector('.tkdp-file-list');
		const renderFiles = () => {
			list.innerHTML = files.length ? files.map((f, i) => '<li>'
				+ (f.thumb ? '<img src="' + esc(f.thumb) + '" alt="">' : '<span class="tkdp-file-icon">📄</span>')
				+ '<a href="' + esc(f.url) + '" target="_blank" rel="noopener">' + esc(f.filename || f.title) + '</a>'
				+ '<span class="tkdp-file-size">' + fmtSize(f.size) + '</span>'
				+ (full || own ? '<button type="button" class="tkdp-file-remove" data-index="' + i + '" aria-label="Retirer">×</button>' : '') + '</li>').join('')
				: '<li class="tkdp-file-empty">Aucune pièce jointe.</li>';
		};
		renderFiles();
		list.addEventListener('click', (e) => {
			const btn = e.target.closest('.tkdp-file-remove');
			if (btn) {
				files.splice(Number(btn.dataset.index), 1);
				renderFiles();
			}
		});
		const addFile = form.querySelector('[data-modal="add-file"]');
		if (addFile) {
			let frame = null;
			addFile.addEventListener('click', () => {
				if (!frame) {
					frame = wp.media({ title: 'Ajouter des pièces jointes', button: { text: 'Joindre à la tâche' }, multiple: true });
					frame.on('select', () => {
						frame.state().get('selection').toJSON().forEach((a) => {
							if (!files.some((f) => f.id === a.id)) {
								files.push({
									id: a.id,
									title: a.title,
									filename: a.filename,
									url: a.url,
									size: a.filesizeInBytes || 0,
									thumb: a.type === 'image' && a.sizes ? (a.sizes.thumbnail || a.sizes.full || {}).url || '' : '',
								});
							}
						});
						renderFiles();
					});
				}
				frame.open();
			});
		}

		const zonesBtn = form.querySelector('[data-modal="zones"]');
		if (zonesBtn) {
			zonesBtn.addEventListener('click', () => openZones(task));
		}

		const range = form.querySelector('[name=progress]');
		const status = form.querySelector('[name=status]');
		range.addEventListener('input', () => {
			form.querySelector('output').textContent = range.value;
		});
		status.addEventListener('change', () => {
			if (status.value === 'done') {
				range.value = 100;
				range.dispatchEvent(new Event('input'));
			}
		});

		const delBtn = wrap.querySelector('[data-modal="delete-task"]');
		if (delBtn) {
			delBtn.addEventListener('click', () => {
				closeModal();
				deleteTask(task.id);
			});
		}
	}

	/** Liste « Attribuer à » : un rôle entier, ou une personne classée par rôle. */
	function assignSelect(t, disabled) {
		const current = t.assignee_role ? 'r:' + t.assignee_role : (t.assignee ? 'u:' + t.assignee : '');
		const opt = (value, label) => '<option value="' + esc(value) + '"' + (value === current ? ' selected' : '') + '>' + esc(label) + '</option>';
		const known = state.roles.map((r) => r.slug);
		let html = '<select name="assign"' + (disabled ? ' disabled' : '') + '>' + opt('', '— Personne —');

		// Attribution actuelle absente des listes (liste non chargée, ou rôle non proposé) : affichée quand même.
		const listed = (t.assignee && state.users.some((u) => u.id === t.assignee))
			|| (t.assignee_role && state.canMailRole && state.roles.some((r) => r.slug === t.assignee_role));
		if (current && !listed) {
			html += opt(current, assigneeLabel(t) || '(attribution actuelle)');
		}

		if (state.canMailRole && state.roles.length) {
			html += '<optgroup label="Tout un rôle (chacun reçoit l\'e-mail)">';
			state.roles.forEach((r) => {
				html += opt('r:' + r.slug, 'Tous les « ' + r.name + ' » (' + r.count + ')');
			});
			html += '</optgroup>';
		}
		state.roles.forEach((r) => {
			const members = state.users.filter((u) => u.roles[0] === r.slug);
			if (members.length) {
				html += '<optgroup label="' + esc(r.name) + '">' + members.map((u) => opt('u:' + u.id, u.name)).join('') + '</optgroup>';
			}
		});
		const others = state.users.filter((u) => known.indexOf(u.roles[0]) === -1);
		if (others.length) {
			html += '<optgroup label="Sans rôle">' + others.map((u) => opt('u:' + u.id, u.name)).join('') + '</optgroup>';
		}
		return html + '</select>';
	}

	async function deleteTask(id) {
		const task = state.tasks.find((t) => t.id === id);
		if (!task || !window.confirm('Supprimer la tâche « ' + task.title + ' » ?')) {
			return;
		}
		try {
			await api('/tasks/' + id, { method: 'DELETE' });
			state.tasks = state.tasks.filter((t) => t.id !== id);
			state.tasks.forEach((t) => {
				if (t.depends_on === id) {
					t.depends_on = null;
				}
			});
			notify('Tâche supprimée.');
			await refreshProjectStats();
			render();
		} catch (e) {
			fail(e);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Clics                                                               */
	/* ------------------------------------------------------------------ */

	root.addEventListener('click', (e) => {
		const el = e.target.closest('[data-action]');
		if (!el || !root.contains(el) || el.closest('.tkdp-kcard')) {
			return;
		}
		e.preventDefault();
		const id = Number(el.dataset.id);

		switch (el.dataset.action) {
			case 'home':
				gantt = null;
				history.replaceState(null, '', window.location.pathname + window.location.search);
				loadHome();
				break;
			case 'new-project':
				openProjectModal(null);
				break;
			case 'open-project':
				gantt = null;
				openProject(id);
				break;
			case 'edit-project':
				openProjectModal(state.project);
				break;
			case 'new-task':
				openTaskModal(null, el.dataset.status ? { status: el.dataset.status } : null);
				break;
			case 'edit-task':
				openTaskModal(state.tasks.find((t) => t.id === id));
				break;
			case 'delete-task':
				deleteTask(id);
				break;
			case 'zones':
				openZones(state.tasks.find((t) => t.id === id));
				break;
			case 'view':
				state.view = el.dataset.view;
				savePref('view', state.view);
				render();
				break;
			case 'zoom':
				state.zoom = el.dataset.zoom;
				savePref('zoom', state.zoom);
				if (gantt) {
					delete gantt.scrollLeft;
				}
				render();
				break;
			case 'scroll-today':
				scrollGanttToToday(true);
				break;
		}
	});

	loadHome();
})();
