/* TKD Gala — écran d'administration : choix du logo d'un annonceur dans la médiathèque. */
(function ($) {
	'use strict';
	var cadre = null;

	$(document).on('click', '[data-tkd-gala="logo-choisir"]', function (e) {
		e.preventDefault();
		var bloc = $(this).closest('.tkd-gala-logo-choix');
		if (!window.wp || !wp.media) return;
		cadre = wp.media({ title: 'Logo de l\'annonceur', button: { text: 'Utiliser ce logo' }, library: { type: 'image' }, multiple: false });
		cadre.on('select', function () {
			var img = cadre.state().get('selection').first().toJSON();
			var taille = (img.sizes && (img.sizes.medium || img.sizes.full)) || img;
			bloc.find('input[name="logo_id"]').val(img.id);
			bloc.find('.tkd-gala-logo-apercu').attr('src', taille.url).prop('hidden', false);
			bloc.find('[data-tkd-gala="logo-retirer"]').prop('hidden', false);
		});
		cadre.open();
	});

	$(document).on('click', '[data-tkd-gala="logo-retirer"]', function (e) {
		e.preventDefault();
		var bloc = $(this).closest('.tkd-gala-logo-choix');
		bloc.find('input[name="logo_id"]').val('0');
		bloc.find('.tkd-gala-logo-apercu').attr('src', '').prop('hidden', true);
		$(this).prop('hidden', true);
	});
})(jQuery);
