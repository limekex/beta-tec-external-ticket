<?php
namespace BetaTEC;

if (!defined('ABSPATH')) exit;

class Admin {

    const META_ENABLE = '_beta_tec_ext_enable';
    const META_URL    = '_beta_tec_ext_url';
    const META_LABEL  = '_beta_tec_ext_label';

    public function __construct() {
        // Add fields on product edit screen (General tab)
        add_action('woocommerce_product_options_general_product_data', [$this,'add_fields']);
        add_action('woocommerce_admin_process_product_object',        [$this,'save_fields']);

        // Add a visual flag in product list table
        add_filter('manage_edit-product_columns',                     [$this,'add_admin_col']);
        add_action('manage_product_posts_custom_column',              [$this,'render_admin_col'], 10, 2);
    }

    /**
     * Only show fields for products that look like ET+ tickets (or always show, your call).
     */
    private function is_ticket_product(\WC_Product $product = null) : bool {
        if (!$product) return false;
        // Event Tickets Plus typically sets these metas to relate product <-> event
        $is_ticket = get_post_meta($product->get_id(), '_tribe_wooticket', true);
        // Fallback: also allow manual enabling (so you can test)
        return !empty($is_ticket) || true; // set to true to always show for convenience
    }

    public function add_fields(){
        global $product_object;

        if (!$product_object instanceof \WC_Product) return;
        if (!$this->is_ticket_product($product_object)) return;

        echo '<div class="options_group">';

        // Enable external checkout
        woocommerce_wp_checkbox([
            'id'          => self::META_ENABLE,
            'label'       => __('External checkout for this ticket', 'beta-tec-external-ticket'),
            'description' => __('Replaces the buy button in the Event Tickets form with an external link.', 'beta-tec-external-ticket'),
            'desc_tip'    => true,
            'value'       => $product_object->get_meta(self::META_ENABLE) ? 'yes' : 'no',
        ]);

        // External URL
        woocommerce_wp_text_input([
            'id'          => self::META_URL,
            'label'       => __('External purchase URL', 'beta-tec-external-ticket'),
            'placeholder' => 'https://partner.example/checkout?ref=fagklar',
            'description' => __('Where users complete the purchase.', 'beta-tec-external-ticket'),
            'desc_tip'    => true,
            'value'       => $product_object->get_meta(self::META_URL),
        ]);

        // Button label
        woocommerce_wp_text_input([
            'id'          => self::META_LABEL,
            'label'       => __('Button label', 'beta-tec-external-ticket'),
            'placeholder' => __('Buy ticket', 'beta-tec-external-ticket'),
            'description' => __('Text shown on the external buy button.', 'beta-tec-external-ticket'),
            'desc_tip'    => true,
            'value'       => $product_object->get_meta(self::META_LABEL),
        ]);

        echo '</div>';
    }

    public function save_fields(\WC_Product $product){
        if (!$product instanceof \WC_Product) return;
        if (!$this->is_ticket_product($product)) return;

        $enable = isset($_POST[self::META_ENABLE]) ? 'yes' : 'no';
        $url    = isset($_POST[self::META_URL])    ? esc_url_raw(wp_unslash($_POST[self::META_URL])) : '';
        $label  = isset($_POST[self::META_LABEL])  ? sanitize_text_field(wp_unslash($_POST[self::META_LABEL])) : '';

        $product->update_meta_data(self::META_ENABLE, $enable);
        $product->update_meta_data(self::META_URL, $url);
        $product->update_meta_data(self::META_LABEL, $label);
    }

    public function add_admin_col($cols){
        $cols['beta_tec_ext'] = __('External Ticket', 'beta-tec-external-ticket');
        return $cols;
    }

    public function render_admin_col($col, $post_id){
        if ($col !== 'beta_tec_ext') return;
        $val = get_post_meta($post_id, self::META_ENABLE, true);
        echo $val === 'yes'
            ? '<span class="dashicons dashicons-external"></span>'
            : '&mdash;';
    }
}
