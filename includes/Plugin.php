<?php
/**
 * Plugin orchestrator.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS;

use WP2TNCMS\Admin\SettingsPage;
use WP2TNCMS\Auth\Authenticator;
use WP2TNCMS\Auth\TokenManager;
use WP2TNCMS\Rest\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires together the bootstrap, auth, REST and admin layers.
 *
 * Kept intentionally thin: it only composes collaborators and registers hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce the singleton.
	 */
	private function __construct() {}

	/**
	 * Register all WordPress hooks.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$token_manager = new TokenManager();
		$authenticator = new Authenticator( $token_manager );
		$router        = new Router( $authenticator );

		add_action( 'rest_api_init', array( $router, 'register_routes' ) );

		if ( is_admin() ) {
			$settings = new SettingsPage( $token_manager );
			$settings->register();
		}
	}
}
