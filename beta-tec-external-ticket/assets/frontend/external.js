(function ($) {
  // Version 2
  
    function dbg() {
    if (window.console && console.log) {
      try { console.log.apply(console, arguments); } catch(e) { console.log(arguments); }
    }
  }

  function applyExternalTickets() {
    var cfg = window.BETA_TEC_EXT_FE || {};
    var map = cfg.tickets || {};
    dbg('[beta-ext] tickets map:', map);

    // Hver billettlinje
    var $items = $('.tribe-tickets__tickets-item[data-ticket-id], .tec-tickets__tickets-item[data-ticket-id]');
    if (!$items.length) { dbg('[beta-ext] no items found'); return; }

    var externalCount = 0, totalCount = 0;

    $items.each(function () {
      var $item = $(this);
      var id = parseInt($item.attr('data-ticket-id'), 10);
      if (!id) return;
      totalCount++;

      var info = map[id];
      if (!info) { return; } // ikke ekstern

      externalCount++;
      var label = info.label || (cfg.i18n ? cfg.i18n.defaultLabel : 'Kjøp hos partner');
      var url = info.url;

      // Skjul antallsvelger og sett qty=0
      $item.find('.tribe-tickets__tickets-item-quantity, .tec-tickets__tickets-item-quantity').addClass('beta-ext-hide');
      $item.find('input[type=number].qty').val(0);

      // Legg knapp
      if (!$item.find('.beta-ext-btn').length) {
        var $where = $item.find('.tribe-tickets__tickets-item-extra, .tec-tickets__tickets-item-extra').first();
        if (!$where.length) $where = $item;
        var $btn = $('<a/>', {
          class: 'beta-ext-btn avada',
          href: url,
          target: '_blank',
          rel: 'noopener',
          text: label
        }).css({ marginTop: '8px' });
        $where.append($('<div/>').append($btn));
      }
    });

    // Skjul footer hvis alt er eksternt
    if (totalCount > 0 && externalCount === totalCount) {
      $('.tribe-tickets__tickets-footer, .tec-tickets__tickets-footer').addClass('beta-ext-hide');
      $('#tribe-tickets__tickets-buy, .tribe-tickets__tickets-buy').closest('div, form').addClass('beta-ext-hide');
    }

    dbg('[beta-ext] totals:', { totalCount, externalCount });
  }

  $(function () {
    applyExternalTickets();
    // Prøv igjen ved dynamiske endringer
    var target = document.querySelector('.tribe-common, .tec-common, body');
    if (!target) return;
    var obs = new MutationObserver(function () { applyExternalTickets(); });
    obs.observe(target, { childList: true, subtree: true });
  });
})(jQuery);
