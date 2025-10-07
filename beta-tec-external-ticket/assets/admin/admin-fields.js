(function($){
	// Vi lytter etter klikk på "Lagre"/"Oppdater" bilett i ET-panelet, og når modal lukkes.
	// Begge deler skjer i samme DOM-område (tickets metabox). Vi fallback'er også på blur av feltene.

	function saveForContainer($container) {
		var ticketId = parseInt($container.attr('data-ticket-id'), 10);
		if (!ticketId) return;

		var url   = $container.find('[data-beta-ext="url"]').val() || '';
		var label = $container.find('[data-beta-ext="label"]').val() || '';

		// Ikke spam AJAX hvis begge er tomme og det heller ikke finnes eksisterende meta – men siden vi ikke vet,
		// sender vi likevel (server rydder opp ved tomme verdier).
		$.post(BETA_TEC_EXT_ADMIN.ajaxurl, {
			action:   'beta_tec_save_ext_meta',
			nonce:    BETA_TEC_EXT_ADMIN.nonce,
			ticket_id: ticketId,
			url:      url,
			label:    label
		}).done(function(resp){
			if (resp && resp.success) {
				$container.find('.beta-ext-status').text(BETA_TEC_EXT_ADMIN.i18n.saved).fadeIn(150).delay(800).fadeOut(250);
			} else {
				$container.find('.beta-ext-status').text(BETA_TEC_EXT_ADMIN.i18n.failed).css('color','#d63638').show();
			}
		}).fail(function(){
			$container.find('.beta-ext-status').text(BETA_TEC_EXT_ADMIN.i18n.failed).css('color','#d63638').show();
		});
	}

	function bind() {
		var $root = $('.tribe-tickets__tickets'); // Metaboksen wrapper

		// Lagre når våre felter mister fokus
		$root.on('blur', '[data-beta-ext="url"], [data-beta-ext="label"]', function(){
			var $c = $(this).closest('[data-beta-ext="container"]');
			saveForContainer($c);
		});

		// Lagre når ET sin Save/Update-knapp trykkes i raden
		$root.on('click', '.tribe-tickets__item-actions-save, .tribe-tickets__item-actions-update', function(){
			var $c = $(this).closest('.tribe-tickets__ticket, .tribe-tickets__tickets-item, [data-beta-ext="container"]').find('[data-beta-ext="container"]').first();
			if ($c.length) saveForContainer($c);
		});

		// Lagre når modalen lukkes (sikkerhetsnett)
		$(document).on('click', '.tribe-dialog__close, .tribe-common-c-btn--secondary', function(){
			$('[data-beta-ext="container"]').each(function(){ saveForContainer($(this)); });
		});
	}

	$(bind);
})(jQuery);
