<?php
/**
 * Admin settings page.
 *
 * @package WP2TNCMS
 */

namespace WP2TNCMS\Admin;

use WP2TNCMS\Auth\TokenManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the exporter settings screen under Settings -> WP 2 TNCMS.
 *
 * Lets an administrator view the API base URL, copy or regenerate the bearer
 * token, and enable or disable the exporter. All actions are capability and
 * nonce guarded.
 */
final class SettingsPage {

	const MENU_SLUG     = 'wp-2-tncms';
	const CAPABILITY    = 'manage_options';
	const REGEN_ACTION  = 'wp2tncms_regenerate_token';
	const SETTINGS_PAGE = 'wp2tncms_settings';

	/**
	 * Token manager.
	 *
	 * @var TokenManager
	 */
	private $tokens;

	/**
	 * Constructor.
	 *
	 * @param TokenManager $tokens Token manager.
	 */
	public function __construct( TokenManager $tokens ) {
		$this->tokens = $tokens;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::REGEN_ACTION, array( $this, 'handle_regenerate' ) );
	}

	/**
	 * Add the settings menu entry.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'WP 2 TNCMS Exporter', 'wp-2-tncms' ),
			__( 'WP 2 TNCMS', 'wp-2-tncms' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the exporter-enabled setting.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_PAGE,
			TokenManager::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_enabled' ),
				'default'           => true,
			)
		);
	}

	/**
	 * Sanitize the enabled checkbox value.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function sanitize_enabled( $value ) {
		return ! empty( $value );
	}

	/**
	 * Handle the regenerate-token form submission.
	 *
	 * @return void
	 */
	public function handle_regenerate() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-2-tncms' ) );
		}

		check_admin_referer( self::REGEN_ACTION );

		$this->tokens->generate_token();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::MENU_SLUG,
					'token_generated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$token    = $this->tokens->get_token();
		$enabled  = $this->tokens->is_enabled();
		$base_url = esc_url( rest_url( WP2TNCMS_REST_NAMESPACE ) );
		$notice   = isset( $_GET['token_generated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP 2 TNCMS Exporter', 'wp-2-tncms' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'A new export token has been generated.', 'wp-2-tncms' ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'API Endpoint', 'wp-2-tncms' ); ?></h2>
			<p><code><?php echo esc_html( $base_url ); ?></code></p>

			<h2><?php esc_html_e( 'Bearer Token', 'wp-2-tncms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Send this token in the Authorization header as: Bearer <token>.', 'wp-2-tncms' ); ?>
			</p>
			<p>
				<input
					type="text"
					readonly="readonly"
					class="regular-text code"
					style="width:100%;max-width:640px;"
					value="<?php echo esc_attr( $token ); ?>"
					onfocus="this.select();"
				/>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::REGEN_ACTION ); ?>" />
				<?php wp_nonce_field( self::REGEN_ACTION ); ?>
				<?php
				submit_button(
					'' === $token
						? __( 'Generate Token', 'wp-2-tncms' )
						: __( 'Regenerate Token', 'wp-2-tncms' ),
					'secondary'
				);
				?>
			</form>

			<h2><?php esc_html_e( 'Exporter Status', 'wp-2-tncms' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable exporter', 'wp-2-tncms' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( TokenManager::OPTION_ENABLED ); ?>"
									value="1"
									<?php checked( $enabled ); ?>
								/>
								<?php esc_html_e( 'Allow authenticated TNCMS export requests.', 'wp-2-tncms' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
