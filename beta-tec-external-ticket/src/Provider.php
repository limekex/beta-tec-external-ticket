<?php
namespace BetaTEC\Tickets;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vi må registrere en DI Service Provider slik TEC kan resolve merchant-IDen
 * "tickets-commerce-external" via tribe() containeren – likt som Manual/Stripe.
 *
 * TEC bruker lucatume/di52. I nyere builds ligger baseklassen i
 * \TEC\Common\lucatume\DI52\ServiceProvider, men noen eldre har \tad_DI52_ServiceProvider.
 * Vi støtter begge ved å alias'e den som _SvcBase.
 */

if ( class_exists( '\TEC\Common\lucatume\DI52\ServiceProvider' ) ) {
	class_alias( '\TEC\Common\lucatume\DI52\ServiceProvider', __NAMESPACE__ . '\_SvcBase' );
} elseif ( class_exists( '\tad_DI52_ServiceProvider' ) ) {
	class_alias( '\tad_DI52_ServiceProvider', __NAMESPACE__ . '\_SvcBase' );
} else {
	// Fail-safe no-op base (skulle ikke inntreffe på TEC).
	abstract class _SvcBase {
		protected $container;
		public function __construct( $container ) { $this->container = $container; }
		public function register() {}
	}
}

/**
 * Binder vår merchant-implementasjon inn i containeren under ID'en som
 * gatewayens static $merchant peker på: "tickets-commerce-external".
 */
class External_Service_Provider extends _SvcBase {
	public function register() {
		// Registrer som singleton slik TEC kan resolve via tribe( 'tickets-commerce-external' )
		$this->container->singleton( 'tickets-commerce-external', External_Merchant::class );
	}
}
