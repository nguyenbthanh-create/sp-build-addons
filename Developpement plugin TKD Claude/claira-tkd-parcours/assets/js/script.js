(function (document) {
    // Tout bouton portant data-modal ouvre la fiche du grade correspondante :
    // cartes de [claira_tkd_parcours] et pastilles de [claira_tkd_schema_grades].
    var openButtons = document.querySelectorAll('[data-modal]');
    var closeButtons = document.querySelectorAll('.claira-tkd-modal-close');
    var modals = document.querySelectorAll('.claira-tkd-modal');
    var lastTrigger = null;

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

    function closeModal(modal) {
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
})(document);
