<?php
namespace BetaTEC\Tickets;

if ( ! defined( 'ABSPATH' ) ) exit;

use TEC\Tickets\Commerce\Gateways\Contracts\Abstract_Gateway;

/**
 * Minimal merchant: må ha is_active() (og vi beholder is_connected() for sikkerhets skyld).
 */
class External_Merchant {
	public function is_active(): bool {
		return get_option( Gateway::OPT_ENABLED, '0' ) === '1';
	}
	public function is_connected(): bool {
		// Alias til is_active() – noen builds spør etter denne.
		return $this->is_active();
	}
}

/**
 * External gateway for ET 5.26.x
 * - Returneres som OBJEKT i filteret
 * - Setter typed static props ($key, $name, $label, $merchant)
 * - Overstyrer IKKE parent sine static metoder
 * - Matcher signaturer (get_label() static, get_admin_notices() non-static)
 */
class External_Gateway extends Abstract_Gateway {

	/** Parent leser disse via late static binding – MÅ være initialisert. */
	protected static string $key      = 'external';
	protected static string $name     = 'External (Redirect)';
	protected static string $label    = 'External (Redirect)';
	protected static string $merchant = 'tickets-commerce-external';

	/**
	 * I din build er $settings typed som string og brukes som container-ID i parent.
	 * Sett en harmløs ID (vi overstyrer uansett get_settings() under).
	 */
	protected static string $settings = 'tickets-commerce-external.settings';

	/** I din build er get_label() static – match signaturen. */
	public static function get_label(): string {
		return static::$label;
	}

	/** Admin-notiser i Payments UI (ikke-static). */
	public function get_admin_notices(): array {
		return [];
	}

	/**
	 * Viktig: overstyr get_settings() (ikke-static) for å unngå DI-lookup på $settings.
	 * Returner bare våre felter direkte.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return static::get_settings_fields();
	}

	/**
	 * Settings-felter i Payments (tom foreløpig — kan fylles senere).
	 *
	 * @return array
	 */
	public static function get_settings_fields(): array {
		return [];
	}

	/** Hvilke features gatewayen støtter. */
	public static function get_supported_features(): array {
		return [
			'cart'        => false,
			'refunds'     => false,
			'attendees'   => false,
			'capacity'    => true,
			'pricing'     => true,
			'sale_window' => true,
		];
	}
}




/**
 * Bootstrap og toggle-håndtering.
 */
class Gateway {
	const OPT_ENABLED = 'beta_tec_ext_gateway_enabled';

	/** Binder merchant-IDen i TEC-containeren. Kan trygt kalles flere ganger. */
	public static function bind_merchant_now(): void {
		if ( ! function_exists( '\tribe' ) ) return;
		$container = \tribe();
		if ( ! $container || ! method_exists( $container, 'singleton' ) ) return;

		// Allerede bundet?
		try {
			if ( method_exists( $container, 'get' ) ) {
				$container->get( 'tickets-commerce-external' );
				return;
			}
		} catch ( \Throwable $e ) {
			// ikke bundet – fortsett
		}

		$container->singleton( 'tickets-commerce-external', External_Merchant::class );
	}

	public static function boot(): void {
		// Bind ASAP og på flere hooks for å treffe ulik lasteorden.
		self::bind_merchant_now();
		add_action( 'tribe_common_loaded', [ __CLASS__, 'bind_merchant_now' ], 0 );
		add_action( 'plugins_loaded',      [ __CLASS__, 'bind_merchant_now' ], 0 );
		add_action( 'init',                [ __CLASS__, 'bind_merchant_now' ], 0 );
		add_action( 'admin_init',          [ __CLASS__, 'bind_merchant_now' ], 0 );

		// Registrer gateway-objektet (Manager forventer Abstract_Gateway-objekter).
		add_filter( 'tec_tickets_commerce_gateways', [ __CLASS__, 'register' ] );

		// Toggle som speiler til TEC sitt enabled-flagg.
		add_action( 'admin_init', [ __CLASS__, 'maybe_capture_toggle' ] );
	}

	/** @param array<\TEC\Tickets\Commerce\Gateways\Contracts\Abstract_Gateway> $gateways */
	public static function register( $gateways ): array {
		$gateways[] = new External_Gateway();
		return $gateways;
	}

	public static function maybe_capture_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['beta_tec_ext_gateway_toggle'] ) ) {
			$enabled = $_POST['beta_tec_ext_gateway_toggle'] === '1' ? '1' : '0';
			update_option( self::OPT_ENABLED, $enabled );

			$key      = External_Gateway::get_key(); // leser static::$key på barneklassen
			$tec_flag = sprintf( 'tec_tickets_commerce_gateway_%s_enabled', $key );
			update_option( $tec_flag, $enabled );
		}
	}

	public static function is_enabled(): bool {
		return get_option( self::OPT_ENABLED, '0' ) === '1';
	}
}
