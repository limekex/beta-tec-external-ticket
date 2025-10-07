<?php
namespace BetaTEC\Tickets;

if ( ! defined( 'ABSPATH' ) ) exit;

use TEC\Tickets\Commerce\Module;

class Admin {

	const META_URL   = '_beta_ext_url';
	const META_LABEL = '_beta_ext_label';

	const NONCE_ACTION = 'beta_tec_ext_meta';
	const NONCE_NAME   = 'beta_tec_ext_nonce';

	public function __construct() {
		// 1) Registrer meta-keys (REST-kompatibelt)
		add_action( 'init', [ $this, 'register_meta_keys' ] );

		// 2) Render feltene i Event → Ticket Advanced
		add_action( 'tribe_events_tickets_metabox_edit_ajax_advanced', [ $this, 'render_external_fields' ], 20, 3 );

		// 3) Enqueue admin JS på arrangement-editoren
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// 4) Admin-AJAX for lagring (kalles fra vårt JS)
		add_action( 'wp_ajax_beta_tec_save_ext_meta', [ $this, 'ajax_save_ext_meta' ] );

		// 5) Fallbacks (hvis ET faktisk poster feltene)
		add_action( 'tribe_tickets_process_ticket', [ $this, 'save_ticket_meta_from_post' ], 10, 3 );
		add_action( 'save_post_tec_tc_ticket', [ $this, 'fallback_save_on_ticket_save' ], 20, 3 );
	}

	/** Registrer meta keys på tec_tc_ticket (må ha show_in_rest for noen editor-flyter) */
	public function register_meta_keys() : void {
		$args = [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			'auth_callback'=> function() { return current_user_can( 'edit_posts' ); },
		];

		register_post_meta( 'tec_tc_ticket', self::META_URL,   $args );
		register_post_meta( 'tec_tc_ticket', self::META_LABEL, $args );
	}

	/** Enqueue admin JS når vi redigerer tribe_events */
	public function enqueue_admin_assets( $hook ) {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'tribe_events' ) return;

		// Liten styling for kortet i Advanced
		wp_add_inline_style(
			'wp-admin',
			'.beta-ext-card{margin-top:12px;padding:12px;border:1px solid #e2e8f0;border-radius:6px;background:#fff}'
			.'.beta-ext-card h4{margin:0 0 8px;font-size:14px}.beta-ext-field{margin:8px 0}'
			.'.beta-ext-field label{display:block;margin-bottom:4px;font-weight:600}.beta-ext-field input{width:100%}'
		);

		// Admin JS for å lagre meta via AJAX når billetten lagres/oppdateres
		wp_enqueue_script(
			'beta-tec-ext-admin',
			BETA_TEC_EXT_URL . 'assets/admin/external-admin.js',
			[ 'jquery' ],
			BETA_TEC_EXT_VER,
			true
		);

		wp_localize_script(
			'beta-tec-ext-admin',
			'BETA_TEC_EXT_ADMIN',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => [
					'saved'   => __( 'External checkout lagret', 'beta-tec-external-ticket' ),
					'failed'  => __( 'Kunne ikke lagre external-felt. Prøv igjen.', 'beta-tec-external-ticket' ),
				],
			]
		);
	}

	/** Felter i Event → Advanced-seksjonen (server-rendered) */
	public function render_external_fields( $post_id, $provider, $ticket_id ) {
		if ( Module::class !== $provider ) return;

		$ticket_id     = (int) $ticket_id;
		$current_url   = $ticket_id ? (string) get_post_meta( $ticket_id, self::META_URL, true ) : '';
		$current_label = $ticket_id ? (string) get_post_meta( $ticket_id, self::META_LABEL, true ) : '';

		// Navn på feltene: vi bruker ID-bundlede navn slik at JS lett finner dem
		$ns = "beta_ext_url[$ticket_id]";
		$ls = "beta_ext_label[$ticket_id]";

		?>
		<div class="beta-ext-card" data-beta-ext="container" data-ticket-id="<?php echo esc_attr( $ticket_id ); ?>">
			<h4><?php echo esc_html__( 'External checkout', 'beta-tec-external-ticket' ); ?></h4>
			<div class="beta-ext-field">
				<label for="beta-ext-url-<?php echo esc_attr( $ticket_id ); ?>"><?php echo esc_html__( 'External purchase URL', 'beta-tec-external-ticket' ); ?></label>
				<input type="url"
					id="beta-ext-url-<?php echo esc_attr( $ticket_id ); ?>"
					name="<?php echo esc_attr( $ns ); ?>"
					data-beta-ext="url"
					placeholder="<?php echo esc_attr__( 'https://partner.example/checkout', 'beta-tec-external-ticket' ); ?>"
					value="<?php echo esc_attr( $current_url ); ?>" />
			</div>
			<div class="beta-ext-field">
				<label for="beta-ext-label-<?php echo esc_attr( $ticket_id ); ?>"><?php echo esc_html__( 'Button label (optional)', 'beta-tec-external-ticket' ); ?></label>
				<input type="text"
					id="beta-ext-label-<?php echo esc_attr( $ticket_id ); ?>"
					name="<?php echo esc_attr( $ls ); ?>"
					data-beta-ext="label"
					placeholder="<?php echo esc_attr__( 'Kjøp hos partner', 'beta-tec-external-ticket' ); ?>"
					value="<?php echo esc_attr( $current_label ); ?>" />
			</div>
			<!-- Vi viser en diskret status-lapp når AJAX-lagring skjer -->
			<div class="beta-ext-status" style="font-size:12px;color:#2271b1"></div>
		</div>
		<?php
	}

	/** Admin-AJAX: lagre meta fra våre felter (kalles av JS). */
	public function ajax_save_ext_meta() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
		if ( ! $ticket_id || ! current_user_can( 'edit_post', $ticket_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Ugyldig billett eller manglende rettigheter.', 'beta-tec-external-ticket' ) ], 403 );
		}

		$url   = isset($_POST['url'])   ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$label = isset($_POST['label']) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		self::update_meta( $ticket_id, $url, $label );

		wp_send_json_success( [
			'ticket_id' => $ticket_id,
			'url'       => $url,
			'label'     => $label,
		] );
	}

	/** Fallback 1: hvis ET poster feltene via tribe_tickets_process_ticket */
	public function save_ticket_meta_from_post( $ticket, $data, $post_id ) {
		$ticket_id = is_object( $ticket ) ? (int) $ticket->ID : (int) $ticket;
		if ( ! $ticket_id ) return;

		$url   = isset( $_POST['beta_ext_url'][ $ticket_id ] )   ? esc_url_raw( wp_unslash( $_POST['beta_ext_url'][ $ticket_id ] ) )   : '';
		$label = isset( $_POST['beta_ext_label'][ $ticket_id ] ) ? sanitize_text_field( wp_unslash( $_POST['beta_ext_label'][ $ticket_id ] ) ) : '';

		if ( $url || $label ) {
			self::update_meta( $ticket_id, $url, $label );
		}
	}

	/** Fallback 2: på save_post for tec_tc_ticket (noen flyter havner her) */
	public function fallback_save_on_ticket_save( $post_id, $post, $update ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$url   = isset( $_POST['beta_ext_url'][ $post_id ] )   ? esc_url_raw( wp_unslash( $_POST['beta_ext_url'][ $post_id ] ) )   : '';
		$label = isset( $_POST['beta_ext_label'][ $post_id ] ) ? sanitize_text_field( wp_unslash( $_POST['beta_ext_label'][ $post_id ] ) ) : '';

		if ( $url || $label ) {
			self::update_meta( $post_id, $url, $label );
		}
	}

	private static function update_meta( int $ticket_id, string $url, string $label ): void {
		if ( $url !== '' )   update_post_meta( $ticket_id, self::META_URL, $url );
		else                 delete_post_meta( $ticket_id, self::META_URL );

		if ( $label !== '' ) update_post_meta( $ticket_id, self::META_LABEL, $label );
		else                 delete_post_meta( $ticket_id, self::META_LABEL );
	}
}
