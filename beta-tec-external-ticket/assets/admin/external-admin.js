(function($){
  // Hjelper: finn nærmeste ticket container og våre felt
  function getContainerFromNode(node){
    var $row = $(node).closest('.tribe-tickets__ticket, .tribe-tickets__tickets-item, [data-beta-ext="container"]');
    var $container = $row.find('[data-beta-ext="container"]').first();
    return $container.length ? $container : $row;
  }

  // Lagre til vår PHP (har allerede nonce/handler i PHP)
  function saveExternalMeta(ticketId, $container){
    if (!ticketId) return;

    var url   = $container.find('[data-beta-ext="url"]').val()   || '';
    var label = $container.find('[data-beta-ext="label"]').val() || '';

    $.post(BETA_TEC_EXT_ADMIN.ajaxurl, {
      action:    'beta_tec_save_ext_meta',
      nonce:     BETA_TEC_EXT_ADMIN.nonce,
      ticket_id: ticketId,
      url:       url,
      label:     label
    }).done(function(resp){
      var $status = $container.find('.beta-ext-status');
      if (resp && resp.success) {
        $status.text(BETA_TEC_EXT_ADMIN.i18n.saved).css('color', '#2271b1').fadeIn(120).delay(800).fadeOut(200);
      } else {
        $status.text(BETA_TEC_EXT_ADMIN.i18n.failed).css('color', '#d63638').show();
      }
    }).fail(function(){
      $container.find('.beta-ext-status').text(BETA_TEC_EXT_ADMIN.i18n.failed).css('color', '#d63638').show();
    });
  }

  // Etter at TEC har lagret (action=tribe-ticket-add), kall vår lagring
  $(document).ajaxSuccess(function(event, xhr, settings){
    try {
      // Interessant kun for admin-ajax med tribe-ticket-add
      if (!settings || !settings.url || settings.url.indexOf('admin-ajax.php') === -1) return;

      // settings.data kan være string (querystring); sjekk om action=tribe-ticket-add
      var dataStr = typeof settings.data === 'string' ? settings.data : '';
      if (dataStr.indexOf('action=tribe-ticket-add') === -1) return;

      // Respons bør være JSON med info om billetten(e) – TEC returnerer HTML + noe JSON avhengig av versjon.
      // Prøv å parse JSON hvis mulig; hvis ikke, forsøk å lese ticket-id fra DOM etter rerender.
      var resp = null;
      try { resp = JSON.parse(xhr.responseText); } catch(e){ /* kan være HTML */ }

      // Finn ticketId:
      var ticketId = 0;

      // Vanlig mønster: resp.data.ticket_id eller resp.data.tickets[0].id
      if (resp && resp.data) {
        if (resp.data.ticket_id) ticketId = parseInt(resp.data.ticket_id, 10) || 0;
        if (!ticketId && resp.data.tickets && resp.data.tickets.length) {
          ticketId = parseInt(resp.data.tickets[0].id, 10) || 0;
        }
      }

      // Hvis fortsatt 0: forsøk å hente fra siste åpne/editert rad. TEC bytter ut DOM-en, så vi prøver å matche
      // på input[name="ticket_name"] som nylig ble lagret; som fallback, plukk første rad som har våre felt.
      if (!ticketId) {
        var $containers = $('[data-beta-ext="container"]');
        if ($containers.length === 1) {
          // én billett – vi antar det er denne
          var guess = parseInt($containers.attr('data-ticket-id'), 10) || 0;
          if (guess) ticketId = guess;
        }
      }

      // Finn container (etter rerender). Vi faller tilbake til å ta første container hvis vi ikke kan matche bedre.
      var $container = $('[data-beta-ext="container"][data-ticket-id="'+ticketId+'"]');
      if (!$container.length) {
        $container = $('[data-beta-ext="container"]').first();
      }

      // Hvis data-ticket-id var 0 på render, oppdater data-attributtet nå vi har id
      if ($container.length && ticketId) {
        if (parseInt($container.attr('data-ticket-id'), 10) !== ticketId) {
          $container.attr('data-ticket-id', ticketId);
        }
        saveExternalMeta(ticketId, $container);
      }
    } catch(e){
      // stille
    }
  });

  // Bonus: Hvis admin trykker “Oppdater” uten at TEC gjør ajax (uvanlig), la vår egen knapp-lagring skyte manuelt:
  $('.tribe-tickets__tickets').on('click', '.tribe-tickets__item-actions-save, .tribe-tickets__item-actions-update', function(){
    // Her lar vi primært TEC håndtere (ajaxSuccess fanger opp), men om TEC ikke er aktiv, prøver vi best-effort:
    setTimeout(function(){
      var $c = getContainerFromNode(this);
      var id = parseInt($c.attr('data-ticket-id'), 10) || 0;
      if (id) saveExternalMeta(id, $c);
    }.bind(this), 1200); // liten delay for å la TEC gjøre sitt
  });

})(jQuery);
