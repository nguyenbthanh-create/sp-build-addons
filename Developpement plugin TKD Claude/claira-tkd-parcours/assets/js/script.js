(function (document) {
    // Tout bouton portant data-modal ouvre la fiche du grade correspondante :
    // cartes de [claira_tkd_parcours] et pastilles de [claira_tkd_schema_grades].
    var openButtons = document.querySelectorAll('[data-modal]');
    var closeButtons = document.querySelectorAll('.claira-tkd-modal-close');
    var modals = document.querySelectorAll('.claira-tkd-modal');
    var lastTrigger = null;
    var SAVED_KEY = 'claira_tkd_saved_grade';

    // Les modales sont rattachées à <body> : un conteneur de constructeur de
    // page (transform, overflow…) ne peut ainsi ni les rogner ni les décaler.
    modals.forEach(function (modal) {
        document.body.appendChild(modal);
    });

    function openModal(modalId, trigger) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }
        lastTrigger = trigger || null;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        var closeButton = modal.querySelector('.claira-tkd-modal-close');
        if (closeButton) {
            closeButton.focus();
        }
    }

    /* ── Modification sur place (comptes autorisés, includes/front-edit.php) ── */

    function isDirty(form) {
        return Array.prototype.some.call(form.querySelectorAll('textarea, input[type="text"], input[type="url"]'), function (field) {
            return field.value !== field.defaultValue;
        });
    }

    function setMessage(form, text, isError) {
        var msg = form.querySelector('.claira-tkd-form-msg');
        if (msg) {
            msg.textContent = text;
            msg.classList.toggle('is-error', !!isError);
        }
    }

    function startEditing(modal) {
        var form = modal.querySelector('.claira-tkd-modal-form');
        if (!form) {
            return;
        }
        modal.classList.add('is-editing');
        form.hidden = false;
        var first = form.querySelector('textarea');
        if (first) {
            first.focus();
        }
    }

    // Renvoie false si l'utilisateur préfère garder ses modifications en cours.
    function stopEditing(modal, askFirst) {
        var form = modal.querySelector('.claira-tkd-modal-form');
        if (!form || !modal.classList.contains('is-editing')) {
            return true;
        }
        if (askFirst && isDirty(form) && !window.confirm('Abandonner les modifications en cours ?')) {
            return false;
        }
        form.reset();
        setMessage(form, '');
        form.hidden = true;
        modal.classList.remove('is-editing');
        return true;
    }

    document.querySelectorAll('[data-edit-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            startEditing(button.closest('.claira-tkd-modal'));
        });
    });

    document.querySelectorAll('[data-edit-cancel]').forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = button.closest('.claira-tkd-modal');
            if (stopEditing(modal, true)) {
                var toggle = modal.querySelector('[data-edit-toggle]');
                if (toggle) {
                    toggle.focus();
                }
            }
        });
    });

    document.querySelectorAll('.claira-tkd-modal-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var modal = form.closest('.claira-tkd-modal');
            var buttons = form.querySelectorAll('button');
            buttons.forEach(function (b) { b.disabled = true; });
            setMessage(form, 'Enregistrement…');

            fetch(form.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: new FormData(form) })
                .then(function (response) {
                    return response.json().catch(function () {
                        // Réponse non JSON (ex. « -1 ») : jeton de sécurité expiré.
                        return { success: false, data: { message: 'Session expirée : rechargez la page puis recommencez.' } };
                    });
                })
                .then(function (result) {
                    if (result && result.success) {
                        // Recharger la page pour afficher le contenu à jour, fiche rouverte.
                        try { window.sessionStorage.setItem(SAVED_KEY, modal.id); } catch (e) {}
                        window.history.replaceState(null, '', '#' + modal.id);
                        window.location.reload();
                        return;
                    }
                    var text = result && result.data && result.data.message ? result.data.message : 'Erreur, réessayez.';
                    setMessage(form, text, true);
                    buttons.forEach(function (b) { b.disabled = false; });
                })
                .catch(function () {
                    setMessage(form, 'Erreur réseau, réessayez.', true);
                    buttons.forEach(function (b) { b.disabled = false; });
                });
        });
    });

    function closeModal(modal) {
        if (!stopEditing(modal, true)) {
            return;
        }
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        var iframes = modal.querySelectorAll('iframe');
        iframes.forEach(function(iframe) {
            var src = iframe.src;
            iframe.src = '';
            iframe.src = src;
        });

        var videos = modal.querySelectorAll('video');
        videos.forEach(function(video) {
            video.pause();
        });

        if (lastTrigger) {
            lastTrigger.focus();
            lastTrigger = null;
        }
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.dataset.modal, button);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = button.closest('.claira-tkd-modal');
            if (modal) {
                closeModal(modal);
            }
        });
    });

    modals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.classList.contains('claira-tkd-modal-backdrop')) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            modals.forEach(function (modal) {
                if (modal.classList.contains('open')) {
                    closeModal(modal);
                }
            });
        }
    });

    // Après un enregistrement : rouvrir la fiche modifiée avec un message de confirmation.
    var savedId = null;
    try { savedId = window.sessionStorage.getItem(SAVED_KEY); window.sessionStorage.removeItem(SAVED_KEY); } catch (e) {}
    if (savedId && window.location.hash === '#' + savedId && document.getElementById(savedId)) {
        var savedModal = document.getElementById(savedId);
        var header = savedModal.querySelector('.claira-tkd-modal-header');
        if (header) {
            var notice = document.createElement('p');
            notice.className = 'claira-tkd-modal-saved';
            notice.setAttribute('role', 'status');
            notice.textContent = 'Modifications enregistrées.';
            header.appendChild(notice);
        }
        openModal(savedId, document.querySelector('[data-modal="' + savedId + '"]'));
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }
})(document);
