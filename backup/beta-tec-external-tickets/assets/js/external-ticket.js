(function($){
  var tmap = {}, pmap = {};

  function init(){
    if (typeof BetaTecExt !== 'object') return;
    tmap = BetaTecExt.ticketMap || {};
    pmap = BetaTecExt.productMap || {};

    // Kjør umiddelbart + observer re-render
    enhanceOnce();

    var root = document.querySelector('.tribe-tickets__tickets-wrapper') || document.querySelector('.tribe-tickets');
    if (root){
      var mo = new MutationObserver(function(){ enhanceOnce(); });
      mo.observe(root, { childList: true, subtree: true });
    }

    // Sikring: stopper submit når det kun er eksterne
    $(document).on('submit', '#tribe-tickets__tickets-form', function(e){
      var $form = $(this);
      var $rows = $form.find('.tribe-tickets__tickets-item');
      if (!$rows.length) return;

      var externals = getExternalRows($rows);
      if (externals.length === $rows.length && externals.length > 0){
        e.preventDefault();
        // Send til første ekstern (evt. kunne du vise meny)
        var data = externals[0].data;
        logClick(data.productId || 0, data.ticketId || 0, window.tribe?.events?.eventId || 0);
        window.location.href = data.url;
      }
    });
  }

  function enhanceOnce(){
    var $rows = $('.tribe-tickets__tickets-item');
    if (!$rows.length) return;

    var externals = getExternalRows($rows);

    // Per-rad CTA og skjul qty
    externals.forEach(function(item){
      var $row = item.$row;
      var data = item.data;

      $row.addClass('beta-tec-ext-row');
      $row.find('.tribe-tickets__tickets-item-quantity, .quantity, .buttons_added').hide();
      $row.find('input.qty, input[type="number"]').prop('disabled', true).attr('readonly','readonly').val(1);

      if (!$row.find('.beta-tec-ext-btn').length){
        var label = data.label || (BetaTecExt.i18n ? BetaTecExt.i18n.buy : 'Buy ticket');
        var $btn = $('<a/>', {
          href: data.url,
          class: 'button beta-tec-ext-btn tribe-common-c-btn tribe-common-c-btn--small',
          text: label
        }).on('click', function(){ logClick(data.productId||0, data.ticketId||0, window.tribe?.events?.eventId || 0); });

        var $priceArea = $row.find('.tribe-tickets__tickets-item-extra-price');
        if ($priceArea.length){
          $priceArea.append($('<div class="beta-tec-ext-cta" style="margin-top:.75rem;"></div>').append($btn));
        } else {
          $row.append($('<div class="beta-tec-ext-cta" style="margin-top:.75rem;"></div>').append($btn));
        }
      }
    });

    // Hvis alle radene er eksterne → skjul footerens submit
    var $form = $('#tribe-tickets__tickets-form');
    if ($form.length && externals.length && externals.length === $rows.length){
      $form.find('.tribe-tickets__tickets-footer').hide();
    }
  }

  function getExternalRows($rows){
    var out = [];
    $rows.each(function(){
      var $row = $(this);
      var data = resolveRowData($row);
      if (data) out.push({ $row: $row, data: data });
    });
    return out;
  }

  function resolveRowData($row){
    // 1) ticket-id
    var tid = parseInt($row.attr('data-ticket-id'), 10);
    if (tid && tmap[tid]) return tmap[tid];

    // 2) product-id via klassestreng "post-####"
    var classes = ($row.attr('class') || '').split(/\s+/);
    for (var i=0;i<classes.length;i++){
      var m = /^post-(\d+)$/.exec(classes[i]);
      if (m){
        var pid = parseInt(m[1],10);
        if (pid && pmap[pid]) return pmap[pid];
      }
    }

    // 3) ikke kjent/ekstern
    return null;
  }

  function logClick(productId, ticketId, eventId){
    try {
      var payload = {
        action: 'beta_tec_ext_click',
        nonce:  BetaTecExt.nonce,
        product_id: productId || 0,
        ticket_id:  ticketId   || 0,
        event_id:   parseInt(eventId || 0, 10),
        ref: window.location.href
      };
      if (navigator.sendBeacon){
        var fd = new FormData();
        Object.keys(payload).forEach(function(k){ fd.append(k, payload[k]); });
        navigator.sendBeacon(BetaTecExt.ajax, fd);
      } else {
        $.post(BetaTecExt.ajax, payload);
      }
    } catch(e){}
  }

  $(document).ready(init);
})(jQuery);
