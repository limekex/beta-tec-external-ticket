<?php
/**
 * Plugin Name:       TEC External Tickets (Adapter)
 * Description:       Adds "External checkout" to Event Tickets Plus (Woo) tickets: keep native price/UI, but redirect buy button to a partner URL.
 * Version:           0.1.0
 * Author:            Betait
 * Text Domain:       beta-tec-external-ticket
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) exit;

define('BETA_TEC_EXT_VER', '0.1.0');
define('BETA_TEC_EXT_FILE', __FILE__);
define('BETA_TEC_EXT_DIR', plugin_dir_path(__FILE__));
define('BETA_TEC_EXT_URL', plugin_dir_url(__FILE__));

class Beta_TEC_External_Ticket {
    private static $instance = null;

    public static function instance(){
        return self::$instance ?: (self::$instance = new self());
    }

    private function __construct() {
        // i18n
        add_action('init', function(){
            load_plugin_textdomain('beta-tec-external-ticket', false, dirname(plugin_basename(BETA_TEC_EXT_FILE)).'/languages');
        });

        // Admin fields on Woo product (only if it's a ticket product)
        require_once BETA_TEC_EXT_DIR.'includes/Admin.php';
        new \BetaTEC\Admin();

        // Frontend: replace ticket buy with external link + click logging
        require_once BETA_TEC_EXT_DIR.'includes/Frontend.php';
        new \BetaTEC\Frontend();

        // Minimal REST/AJAX for click logging
        add_action('wp_ajax_beta_tec_ext_click', [$this,'log_click']);
        add_action('wp_ajax_nopriv_beta_tec_ext_click', [$this,'log_click']);

        add_filter('woocommerce_add_to_cart_validation', function($valid, $product_id, $quantity) {
        $enable = get_post_meta($product_id, \BetaTEC\Admin::META_ENABLE, true);
        $url    = get_post_meta($product_id, \BetaTEC\Admin::META_URL, true);
        if ('yes' === $enable && $url) {
            // Blokker Woo-cart for våre eksterne produkter
            wc_add_notice( __('This ticket is purchased via an external partner.', 'beta-tec-external-ticket'), 'notice' );
            return false;
        }
        return $valid;
    }, 10, 3);
    }

    /**
     * Store a lightweight click log as comment (or switch to custom table later).
     */
    public function log_click(){
        check_ajax_referer('beta_tec_ext', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $event_id   = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $ticket_id  = isset($_POST['ticket_id']) ? absint($_POST['ticket_id']) : 0;

        if ($product_id) {
            // Keep it simple: comment on product for now
            wp_insert_comment([
                'comment_post_ID'      => $product_id,
                'comment_content'      => sprintf(
                    'External ticket click | event=%d ticket=%d ref=%s',
                    $event_id,
                    $ticket_id,
                    isset($_POST['ref']) ? sanitize_text_field($_POST['ref']) : ''
                ),
                'comment_type'         => 'beta_tec_ext_click',
                'user_id'              => get_current_user_id(),
                'comment_approved'     => 1,
                'comment_author'       => 'beta-tec-external-ticket',
                'comment_author_email' => '',
                'comment_author_url'   => '',
            ]);
        }

        wp_send_json_success();
    }
}

Beta_TEC_External_Ticket::instance();
