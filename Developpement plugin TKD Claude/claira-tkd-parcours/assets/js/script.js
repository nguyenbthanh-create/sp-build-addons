(function (document) {
    var openButtons = document.querySelectorAll('.claira-tkd-card');
    var closeButtons = document.querySelectorAll('.claira-tkd-modal-close');
    var modals = document.querySelectorAll('.claira-tkd-modal');

    function openModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
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
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.dataset.modal);
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
