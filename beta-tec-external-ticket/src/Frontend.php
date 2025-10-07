<?php
namespace BetaTEC\Tickets;

if ( ! defined( 'ABSPATH' ) ) exit;

use TEC\Tickets\Commerce\Ticket; // <— NY
use TEC\Tickets\Commerce\Module; // du har denne fra før

/**
 * Frontend-tilpasninger for eksterne billetter.
 */
class Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

public function enqueue() {
	// Kjør på alle enkelt-arrangement (slugen hos deg er "arrangement", post type er tribe_events).
	if ( ! is_singular( 'tribe_events' ) ) {
		return;
	}

	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return;
	}

	// Bygg map over eksterne billetter (kan bli tom – det er greit).
		// Bygg map over eksterne billetter (DB-trygg måte – uavhengig av provider-API).
	$tickets = [];

    $q = new \WP_Query([
      'post_type'      => Ticket::POSTTYPE, // 'tec_tc_ticket'
      'post_status'    => 'any',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'meta_query'     => [
        [
          'key'   => Ticket::$event_relation_meta_key, // '_tec_tickets_commerce_event'
          'value' => (string) $post_id,
        ],
        [
          'key'     => Admin::META_URL,   // vår ekstern-url
          'compare' => 'EXISTS',
        ],
      ],
    ]);

    $ids = $q->posts;

    if ( $ids ) {
      foreach ( $ids as $tid ) {
        $url   = (string) get_post_meta( $tid, Admin::META_URL, true );
        if ( ! $url ) continue;
        $label = (string) get_post_meta( $tid, Admin::META_LABEL, true );

        $tickets[(int) $tid] = [
          'url'   => esc_url_raw( $url ),
          'label' => $label ?: __( 'Kjøp hos partner', 'beta-tec-external-ticket' ),
        ];
      }
    }


	// Enqueue ALLTID scriptet på single event – selv om $tickets er tomt.
	wp_enqueue_script(
		'beta-tec-ext-frontend',
		BETA_TEC_EXT_URL . 'assets/frontend/external.js',
		[ 'jquery' ],
		BETA_TEC_EXT_VER,
		true
	);

	wp_localize_script(
		'beta-tec-ext-frontend',
		'BETA_TEC_EXT_FE',
		[
			'tickets' => $tickets, // tom map er OK
			'i18n'    => [
				'defaultLabel' => __( 'Kjøp hos partner', 'beta-tec-external-ticket' ),
			],
		]
	);

	// Litt CSS (frivillig)
	$css = '.beta-ext-btn{display:inline-block;padding:.6em 1.1em;border-radius:4px;text-decoration:none}
	.beta-ext-btn.avada{background:linear-gradient(#e0b769,#c9942f);color:#3a2d12;font-weight:700}
	.beta-ext-hide{display:none!important}';
	
  // Diagnostikk: skriv en kommentar i HTML-kilden med det vi fant
add_action( 'wp_footer', function () use ( $tickets ) {
	echo "\n<!-- beta-tec-ext tickets: " . esc_html( wp_json_encode( array_keys( $tickets ) ) ) . " -->\n";
}, 999 );

  
  // Bruk et alltid-registrert handle (wp-block-library finnes nesten alltid); hvis ikke: legg i <head> via wp_add_inline_script isteden.
	
  wp_add_inline_style( 'wp-block-library', $css );
}

}
