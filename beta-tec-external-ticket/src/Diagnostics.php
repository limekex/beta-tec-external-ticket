<?php
namespace BetaTEC\Tickets;

if (!defined('ABSPATH')) exit;

class Diagnostics {
    public function __construct() {
        add_action('admin_menu', [ $this, 'menu' ]);
    }

    public function menu(){
        add_submenu_page(
            'edit.php?post_type=tribe_events',
            __('TEC External – Diagnostics', 'beta-tec-external-ticket'),
            __('TEC External – Diagnostics', 'beta-tec-external-ticket'),
            'manage_options',
            'beta-tec-external-diag',
            [ $this, 'render' ]
        );
        add_submenu_page(
            'tools.php',
            __('TEC External – Diagnostics', 'beta-tec-external-ticket'),
            __('TEC External – Diagnostics', 'beta-tec-external-ticket'),
            'manage_options',
            'beta-tec-external-diag',
            [ $this, 'render' ]
        );
    }

    public function render(){
        $tec_ver = defined('TRIBE_EVENTS_FILE') ? \Tribe__Main::VERSION : 'n/a';
        $tickets_ver = defined('TRIBE_TICKETS_FILE') ? \Tribe__Tickets__Main::VERSION : 'n/a';
        $hooks = [
            'tec_tickets_commerce_gateways'             => has_filter('tec_tickets_commerce_gateways'),
            'tribe_tickets_process_ticket'              => has_action('tribe_tickets_process_ticket'),
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TEC External – Diagnostics', 'beta-tec-external-ticket'); ?></h1>
            <table class="widefat striped">
                <tbody>
                    <tr><td><?php esc_html_e('Events Calendar version', 'beta-tec-external-ticket'); ?></td><td><?php echo esc_html($tec_ver); ?></td></tr>
                    <tr><td><?php esc_html_e('Event Tickets version', 'beta-tec-external-ticket'); ?></td><td><?php echo esc_html($tickets_ver); ?></td></tr>
                </tbody>
            </table>
            <h2 style="margin-top:1em;"><?php esc_html_e('Hook availability', 'beta-tec-external-ticket'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th>Hook</th><th>Available</th></tr></thead>
                <tbody>
                <?php foreach($hooks as $k=>$v): ?>
                    <tr><td><code><?php echo esc_html($k); ?></code></td><td><?php echo $v ? 'yes' : 'no'; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
