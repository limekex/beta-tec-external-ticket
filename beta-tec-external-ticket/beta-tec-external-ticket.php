<?php
/**
 * Plugin Name:       Tickets Commerce – External Gateway (beta)
 * Description:       Adds an External (Redirect) gateway to Tickets Commerce: show price/capacity locally, redirect checkout per ticket.
 * Version:           0.2.0
 * Author:            Betait
 * Text Domain:       beta-tec-external-ticket
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BETA_TEC_EXT_VER',  '0.2.3' );
define( 'BETA_TEC_EXT_FILE', __FILE__ );
define( 'BETA_TEC_EXT_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BETA_TEC_EXT_URL',  plugin_dir_url( __FILE__ ) );

/**
 * Last inn filer og boot rekkefølge:
 * 1) Provider.php (inneholder External_Service_Provider som binder merchant i DI-container)
 * 2) Gateway.php   (registrerer provider på tribe_common_loaded + gateway i filter)
 * 3) Admin/Frontend/Diagnostics
 */
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'beta-tec-external-ticket', false, dirname( plugin_basename( BETA_TEC_EXT_FILE ) ) . '/languages' );

	// Viktig: Provider.php må lastes før Gateway::boot()
    require_once BETA_TEC_EXT_DIR . 'src/Provider.php';   // (valgfri – kan stå eller droppes)
    require_once BETA_TEC_EXT_DIR . 'src/Gateway.php';
    require_once BETA_TEC_EXT_DIR . 'src/Admin.php';
    require_once BETA_TEC_EXT_DIR . 'src/Frontend.php';
    require_once BETA_TEC_EXT_DIR . 'src/Diagnostics.php';

    /* NYTT: kall tidlig binding direkte her også */
    \BetaTEC\Tickets\Gateway::bind_merchant_now();

    /* Deretter vanlig boot */
    \BetaTEC\Tickets\Gateway::boot();


	// Init øvrige moduler
	new \BetaTEC\Tickets\Admin();
	new \BetaTEC\Tickets\Frontend();
	new \BetaTEC\Tickets\Diagnostics();
}, 5 ); // kjør tidlig nok til at tribe_common_loaded-hooken i Gateway får effekt før Payments-tab bygges

// Liten notice ved aktivering, for sanity
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) return;
	echo '<div class="notice notice-success is-dismissible"><p>'
	     . esc_html__( 'External Gateway (beta) loaded. Enable under Events → External Gateway (or Payments tab).', 'beta-tec-external-ticket' )
	     . '</p></div>';
} );
