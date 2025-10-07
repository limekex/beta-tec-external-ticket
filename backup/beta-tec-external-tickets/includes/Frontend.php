<?php
namespace BetaTEC;

if (!defined('ABSPATH')) exit;

class Frontend {

    private $by_ticket = [];
    private $by_product = [];

    public function __construct() {
        add_action('wp', [$this,'collect_map'], 9);
        add_action('wp_head', [$this,'inline_css'], 11);
        add_action('wp_footer', [$this,'inline_js'], 99);
    }

    public function collect_map(){
        if (!function_exists('tribe_tickets_get_all_event_tickets')) return;
        if (!is_singular('tribe_events')) return;

        $event_id = get_the_ID();
        $tickets  = \tribe_tickets_get_all_event_tickets($event_id);
        if (empty($tickets)) return;

        foreach ($tickets as $ticket) {
            $ticket_id  = method_exists($ticket,'get_id') ? (int)$ticket->get_id() : (isset($ticket->ID) ? (int)$ticket->ID : 0);
            $product_id = method_exists($ticket,'get_product_id') ? (int)$ticket->get_product_id() : 0;

            if (!$ticket_id && !$product_id) continue;

            $enable = $product_id ? (get_post_meta($product_id, Admin::META_ENABLE, true) === 'yes') : false;
            if (!$enable) continue;

            $url   = $product_id ? get_post_meta($product_id, Admin::META_URL, true)   : '';
            $label = $product_id ? get_post_meta($product_id, Admin::META_LABEL, true) : '';

            if (!$url) continue;

            $payload = [
                'url'       => esc_url($url),
                'label'     => $label ?: __('Buy ticket', 'beta-tec-external-ticket'),
                'productId' => $product_id,
                'ticketId'  => $ticket_id,
            ];

            if ($ticket_id)  $this->by_ticket[$ticket_id]   = $payload;
            if ($product_id) $this->by_product[$product_id] = $payload;
        }
    }

    public function inline_css(){
        if (!is_singular('tribe_events')) return;
        echo '<style>
          .beta-tec-ext-btn{display:inline-block;text-decoration:none}
          .beta-tec-ext-row .tribe-tickets__tickets-item-quantity,
          .beta-tec-ext-row .quantity,
          .beta-tec-ext-row .buttons_added{display:none!important}
        </style>';
    }

    public function inline_js(){
        if (!is_singular('tribe_events')) return;

        $data = [
            'ticketMap'  => $this->by_ticket,
            'productMap' => $this->by_product,
            'nonce'      => wp_create_nonce('beta_tec_ext'),
            'ajax'       => admin_url('admin-ajax.php'),
            'i18n'       => ['buy' => __('Buy ticket', 'beta-tec-external-ticket')],
        ];
        echo '<script id="beta-tec-external-inline">window.BetaTecExt='.wp_json_encode($data).';</script>';

        // Ultra-robust DOM-skript – vanilla + jQuery hvis tilstede
        ?>
<script id="beta-tec-external-logic">
(function(){
  var D=document, W=window, B=W.BetaTecExt||{}, tmap=B.ticketMap||{}, pmap=B.productMap||{};
  function q(s,ctx){return (ctx||D).querySelector(s)}
  function qa(s,ctx){return Array.prototype.slice.call((ctx||D).querySelectorAll(s))}
  function has(o){return o && Object.keys(o).length>0}

  // Log klikk (best effort)
  function logClick(pid,tid,eid){
    try{
      var url=B.ajax, fd=new FormData();
      fd.append('action','beta_tec_ext_click');
      fd.append('nonce', B.nonce||'');
      fd.append('product_id', pid||0);
      fd.append('ticket_id', tid||0);
      fd.append('event_id', eid||0);
      fd.append('ref', location.href);
      if (navigator.sendBeacon) navigator.sendBeacon(url,fd); else fetch(url,{method:'POST',body:fd,credentials:'same-origin'});
    }catch(e){}
  }

  function resolveRowData(row){
    var tid=parseInt(row.getAttribute('data-ticket-id')||'0',10);
    if (tid && tmap[tid]) return tmap[tid];
    // fallback via class "post-####"
    var cls=row.className.split(/\s+/);
    for (var i=0;i<cls.length;i++){
      var m=/^post-(\d+)$/.exec(cls[i]); if(m){ var pid=parseInt(m[1],10); if (pid && pmap[pid]) return pmap[pid];}
    }
    return null;
  }

  function enhance(){
    var wrap = q('.tribe-tickets__tickets-wrapper');
    if (!wrap) return;

    var rows = qa('.tribe-tickets__tickets-item', wrap);
    if(!rows.length) return;

    var externals=[];
    rows.forEach(function(row){
      var data=resolveRowData(row);
      if(!data) return;
      row.classList.add('beta-tec-ext-row');

      // Skjul qty
      qa('.tribe-tickets__tickets-item-quantity, .quantity, .buttons_added', row).forEach(function(el){
        el.style.display='none';
      });
      var qty= q('input.qty, input[type="number"]', row);
      if(qty){ qty.value=1; qty.setAttribute('readonly','readonly'); qty.disabled=true; }

      // Per-rad CTA
      if (!q('.beta-tec-ext-btn', row)){
        var btn=D.createElement('a');
        btn.className='button beta-tec-ext-btn tribe-common-c-btn tribe-common-c-btn--small';
        btn.href=data.url;
        btn.textContent=data.label || (B.i18n?B.i18n.buy:'Buy ticket');
        btn.addEventListener('click', function(){
          var eid = (W.tribe && W.tribe.events && W.tribe.events.eventId) ? parseInt(W.tribe.events.eventId,10) : 0;
          logClick(data.productId||0, data.ticketId||0, eid);
        });
        var priceArea = q('.tribe-tickets__tickets-item-extra-price', row);
        var holder=D.createElement('div'); holder.className='beta-tec-ext-cta'; holder.style.marginTop='.75rem';
        holder.appendChild(btn);
        (priceArea||row).appendChild(holder);
      }

      externals.push({row:row,data:data});
    });

    var form = q('#tribe-tickets__tickets-form');
    var footer = form ? q('.tribe-tickets__tickets-footer', form) : null;

    // Hvis alle rader er eksterne -> skjul footer
    if (form && footer && externals.length && externals.length===rows.length){
      footer.style.display='none';
      form.addEventListener('submit', function(ev){
        ev.preventDefault();
        var first=externals[0].data;
        var eid = (W.tribe && W.tribe.events && W.tribe.events.eventId) ? parseInt(W.tribe.events.eventId,10) : 0;
        logClick(first.productId||0, first.ticketId||0, eid);
        location.href=first.url;
      }, {passive:false});
    }
  }

  // Kjør nå + observer reflow
  function ready(fn){ if(D.readyState!=='loading') fn(); else D.addEventListener('DOMContentLoaded', fn); }
  ready(enhance);
  var root = q('.tribe-tickets__tickets-wrapper') || q('.tribe-tickets');
  if (root){
    var mo=new MutationObserver(function(){enhance()});
    mo.observe(root,{childList:true,subtree:true});
  }
})();
</script>
        <?php
    }
}
