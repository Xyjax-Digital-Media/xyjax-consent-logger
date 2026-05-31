<?php
/**
 * Plugin Name: Xyjax Consent Logger
 * Plugin URI: https://xyjax.com/
 * Description: GPL-compatible lightweight cookie consent banner with local consent logging and CSV export.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Xyjax Digital Media
 * Author URI: https://xyjax.com/
 * Text Domain: xyjax-consent-logger
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Xyjax_Consent_Logger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'XYJAX_CONSENT_LOGGER_VERSION', '1.0.0' );
define( 'XYJAX_CONSENT_LOGGER_FILE', __FILE__ );
define( 'XYJAX_CONSENT_LOGGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'XYJAX_CONSENT_LOGGER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
final class Xyjax_Consent_Logger {
	/**
	 * Option name.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'xyjax_consent_logger_settings';

	/**
	 * Consent cookie name.
	 *
	 * @var string
	 */
	private const COOKIE_NAME = 'xyjax_consent_preferences';

	/**
	 * Boot plugin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_xyjax_consent_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_xyjax_save_consent', array( __CLASS__, 'save_consent' ) );
		add_action( 'wp_ajax_nopriv_xyjax_save_consent', array( __CLASS__, 'save_consent' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
		add_shortcode( 'xyjax_consent_preferences', array( __CLASS__, 'preferences_shortcode' ) );
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			consent_version varchar(32) NOT NULL DEFAULT '1.0',
			necessary tinyint(1) NOT NULL DEFAULT 1,
			analytics tinyint(1) NOT NULL DEFAULT 0,
			marketing tinyint(1) NOT NULL DEFAULT 0,
			affiliate tinyint(1) NOT NULL DEFAULT 0,
			page_url text NULL,
			user_agent_hash varchar(64) NULL,
			ip_hash varchar(64) NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY consent_version (consent_version)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( false === get_option( self::OPTION_NAME ) ) {
			add_option( self::OPTION_NAME, self::default_settings() );
		}
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'xyjax-consent-logger', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public static function admin_menu(): void {
		add_options_page(
			esc_html__( 'Xyjax Consent Logger', 'xyjax-consent-logger' ),
			esc_html__( 'Xyjax Consent', 'xyjax-consent-logger' ),
			'manage_options',
			'xyjax-consent-logger',
			array( __CLASS__, 'settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'xyjax_consent_logger_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,string>
	 */
	private static function default_settings(): array {
		return array(
			'enabled'          => '1',
			'consent_version'  => '1.0',
			'banner_title'     => __( 'Cookie Preferences', 'xyjax-consent-logger' ),
			'banner_message'   => __( 'We use cookies to improve this site. You can accept all cookies, reject non-essential cookies, or choose specific categories.', 'xyjax-consent-logger' ),
			'privacy_url'      => home_url( '/privacy-policy/' ),
			'retention_days'   => '365',
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string,mixed> $input Raw settings.
	 * @return array<string,string>
	 */
	public static function sanitize_settings( array $input ): array {
		$defaults = self::default_settings();

		return array(
			'enabled'         => ! empty( $input['enabled'] ) ? '1' : '0',
			'consent_version' => isset( $input['consent_version'] ) ? sanitize_text_field( wp_unslash( $input['consent_version'] ) ) : $defaults['consent_version'],
			'banner_title'    => isset( $input['banner_title'] ) ? sanitize_text_field( wp_unslash( $input['banner_title'] ) ) : $defaults['banner_title'],
			'banner_message'  => isset( $input['banner_message'] ) ? sanitize_textarea_field( wp_unslash( $input['banner_message'] ) ) : $defaults['banner_message'],
			'privacy_url'     => isset( $input['privacy_url'] ) ? esc_url_raw( wp_unslash( $input['privacy_url'] ) ) : $defaults['privacy_url'],
			'retention_days'  => isset( $input['retention_days'] ) ? (string) max( 1, absint( $input['retention_days'] ) ) : $defaults['retention_days'],
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string,string>
	 */
	private static function settings(): array {
		return wp_parse_args( get_option( self::OPTION_NAME, array() ), self::default_settings() );
	}

	/**
	 * Render settings and logs page.
	 *
	 * @return void
	 */
	public static function settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xyjax-consent-logger' ) );
		}

		$settings = self::settings();
		$logs     = self::get_recent_logs();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Xyjax Consent Logger', 'xyjax-consent-logger' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'xyjax_consent_logger_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Enable banner', 'xyjax-consent-logger' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>> <?php echo esc_html__( 'Show consent banner on the frontend', 'xyjax-consent-logger' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="xyjax-consent-version"><?php echo esc_html__( 'Consent version', 'xyjax-consent-logger' ); ?></label></th>
						<td><input id="xyjax-consent-version" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[consent_version]" value="<?php echo esc_attr( $settings['consent_version'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="xyjax-banner-title"><?php echo esc_html__( 'Banner title', 'xyjax-consent-logger' ); ?></label></th>
						<td><input id="xyjax-banner-title" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[banner_title]" value="<?php echo esc_attr( $settings['banner_title'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="xyjax-banner-message"><?php echo esc_html__( 'Banner message', 'xyjax-consent-logger' ); ?></label></th>
						<td><textarea id="xyjax-banner-message" class="large-text" rows="4" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[banner_message]"><?php echo esc_textarea( $settings['banner_message'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="xyjax-privacy-url"><?php echo esc_html__( 'Privacy policy URL', 'xyjax-consent-logger' ); ?></label></th>
						<td><input id="xyjax-privacy-url" class="regular-text" type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[privacy_url]" value="<?php echo esc_url( $settings['privacy_url'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="xyjax-retention-days"><?php echo esc_html__( 'Log retention days', 'xyjax-consent-logger' ); ?></label></th>
						<td><input id="xyjax-retention-days" type="number" min="1" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[retention_days]" value="<?php echo esc_attr( $settings['retention_days'] ); ?>"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php echo esc_html__( 'Recent Consent Logs', 'xyjax-consent-logger' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=xyjax_consent_export_csv' ), 'xyjax_consent_export_csv' ) ); ?>"><?php echo esc_html__( 'Export CSV', 'xyjax-consent-logger' ); ?></a>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Date', 'xyjax-consent-logger' ); ?></th>
						<th><?php echo esc_html__( 'Version', 'xyjax-consent-logger' ); ?></th>
						<th><?php echo esc_html__( 'Necessary', 'xyjax-consent-logger' ); ?></th>
						<th><?php echo esc_html__( 'Analytics', 'xyjax-consent-logger' ); ?></th>
						<th><?php echo esc_html__( 'Marketing', 'xyjax-consent-logger' ); ?></th>
						<th><?php echo esc_html__( 'Affiliate', 'xyjax-consent-logger' ); ?></th>
						<th><?php echo esc_html__( 'Page URL', 'xyjax-consent-logger' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="7"><?php echo esc_html__( 'No consent records yet.', 'xyjax-consent-logger' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log->created_at ); ?></td>
							<td><?php echo esc_html( $log->consent_version ); ?></td>
							<td><?php echo esc_html( self::yes_no( (int) $log->necessary ) ); ?></td>
							<td><?php echo esc_html( self::yes_no( (int) $log->analytics ) ); ?></td>
							<td><?php echo esc_html( self::yes_no( (int) $log->marketing ) ); ?></td>
							<td><?php echo esc_html( self::yes_no( (int) $log->affiliate ) ); ?></td>
							<td><?php echo esc_url( $log->page_url ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Convert boolean to translated yes/no.
	 *
	 * @param int $value Value.
	 * @return string
	 */
	private static function yes_no( int $value ): string {
		return 1 === $value ? __( 'Yes', 'xyjax-consent-logger' ) : __( 'No', 'xyjax-consent-logger' );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$settings = self::settings();

		if ( '1' !== $settings['enabled'] || is_admin() ) {
			return;
		}

		wp_enqueue_style( 'xyjax-consent-logger', XYJAX_CONSENT_LOGGER_URL . 'assets/xyjax-consent.css', array(), XYJAX_CONSENT_LOGGER_VERSION );
		wp_enqueue_script( 'xyjax-consent-logger', XYJAX_CONSENT_LOGGER_URL . 'assets/xyjax-consent.js', array(), XYJAX_CONSENT_LOGGER_VERSION, true );

		wp_localize_script(
			'xyjax-consent-logger',
			'XyjaxConsentLogger',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'xyjax_save_consent' ),
				'cookieName'     => self::COOKIE_NAME,
				'consentVersion' => $settings['consent_version'],
				'title'          => $settings['banner_title'],
				'message'        => $settings['banner_message'],
				'privacyUrl'     => $settings['privacy_url'],
				'labels'         => array(
					'acceptAll'  => __( 'Accept All', 'xyjax-consent-logger' ),
					'rejectAll'  => __( 'Reject Non-Essential', 'xyjax-consent-logger' ),
					'customize'  => __( 'Customize', 'xyjax-consent-logger' ),
					'save'       => __( 'Save Preferences', 'xyjax-consent-logger' ),
					'necessary'  => __( 'Necessary', 'xyjax-consent-logger' ),
					'analytics'  => __( 'Analytics', 'xyjax-consent-logger' ),
					'marketing'  => __( 'Marketing', 'xyjax-consent-logger' ),
					'affiliate'  => __( 'Affiliate', 'xyjax-consent-logger' ),
					'privacy'    => __( 'Privacy Policy', 'xyjax-consent-logger' ),
				),
			)
		);
	}

	/**
	 * Save consent by AJAX.
	 *
	 * @return void
	 */
	public static function save_consent(): void {
		check_ajax_referer( 'xyjax_save_consent', 'nonce' );

		$settings = self::settings();
		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		$data = array(
			'necessary'       => 1,
			'analytics'       => ! empty( $_POST['analytics'] ) ? 1 : 0,
			'marketing'       => ! empty( $_POST['marketing'] ) ? 1 : 0,
			'affiliate'       => ! empty( $_POST['affiliate'] ) ? 1 : 0,
			'page_url'        => $page_url,
			'consent_version' => sanitize_text_field( $settings['consent_version'] ),
		);

		self::insert_log( $data );
		self::purge_old_logs();

		wp_send_json_success( array( 'message' => __( 'Consent saved.', 'xyjax-consent-logger' ) ) );
	}

	/**
	 * Insert a log row.
	 *
	 * @param array<string,mixed> $data Consent data.
	 * @return void
	 */
	private static function insert_log( array $data ): void {
		global $wpdb;

		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'       => current_time( 'mysql', false ),
				'consent_version'  => sanitize_text_field( (string) $data['consent_version'] ),
				'necessary'        => 1,
				'analytics'        => absint( $data['analytics'] ),
				'marketing'        => absint( $data['marketing'] ),
				'affiliate'        => absint( $data['affiliate'] ),
				'page_url'         => esc_url_raw( (string) $data['page_url'] ),
				'user_agent_hash'  => hash( 'sha256', $user_agent . wp_salt( 'auth' ) ),
				'ip_hash'          => hash( 'sha256', $remote_addr . wp_salt( 'auth' ) ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Export logs as CSV.
	 *
	 * @return void
	 */
	public static function export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export consent logs.', 'xyjax-consent-logger' ) );
		}

		check_admin_referer( 'xyjax_consent_export_csv' );

		$logs = self::get_recent_logs( 10000 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=xyjax-consent-logs.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Unable to create CSV export.', 'xyjax-consent-logger' ) );
		}

		fputcsv( $output, array( 'created_at', 'consent_version', 'necessary', 'analytics', 'marketing', 'affiliate', 'page_url', 'user_agent_hash', 'ip_hash' ) );

		foreach ( $logs as $log ) {
			fputcsv(
				$output,
				array(
					$log->created_at,
					$log->consent_version,
					$log->necessary,
					$log->analytics,
					$log->marketing,
					$log->affiliate,
					$log->page_url,
					$log->user_agent_hash,
					$log->ip_hash,
				)
			);
		}

		exit;
	}

	/**
	 * Add suggested privacy policy content.
	 *
	 * @return void
	 */
	public static function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'This site uses Xyjax Consent Logger to remember cookie consent preferences. When a visitor saves consent preferences, the plugin stores the date, time, consent version, selected consent categories, page URL, and hashed technical identifiers such as IP address and browser user agent. These records are stored locally on this website and are used to help demonstrate consent choices.', 'xyjax-consent-logger' ) . '</p>';

		wp_add_privacy_policy_content( 'Xyjax Consent Logger', wp_kses_post( wpautop( $content ) ) );
	}

	/**
	 * Shortcode to reopen banner.
	 *
	 * @return string
	 */
	public static function preferences_shortcode(): string {
		return '<button type="button" class="xyjax-consent-open">' . esc_html__( 'Cookie Preferences', 'xyjax-consent-logger' ) . '</button>';
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Number of logs.
	 * @return array<int,object>
	 */
	private static function get_recent_logs( int $limit = 50 ): array {
		global $wpdb;

		$limit = max( 1, min( 10000, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql( self::table_name() ) . ' ORDER BY created_at DESC LIMIT %d',
				$limit
			)
		);
	}

	/**
	 * Purge old logs according to retention setting.
	 *
	 * @return void
	 */
	private static function purge_old_logs(): void {
		global $wpdb;

		$settings       = self::settings();
		$retention_days = max( 1, absint( $settings['retention_days'] ) );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $retention_days ) );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . esc_sql( self::table_name() ) . ' WHERE created_at < %s',
				$cutoff
			)
		);
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'xyjax_consent_logs';
	}
}

register_activation_hook( __FILE__, array( 'Xyjax_Consent_Logger', 'activate' ) );
Xyjax_Consent_Logger::init();
