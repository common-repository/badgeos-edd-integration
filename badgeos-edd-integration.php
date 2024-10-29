<?php
/**
 * Plugin Name: BadgeOS EDD Integration
 * Plugin URI: https://wordpress.org/plugins/badgeos-edd-integration/
 * Description: This addon enables you to award BadgeOS badges and points on completion of EDD events
 * Version: 1.2
 * Author: BadgeOS
 * Author URI: https://badgeos.org
 * Text Domain: bosedd
 */

if ( ! defined( 'ABSPATH' ) ) exit;

register_activation_hook( __FILE__, [ 'BOS_EDD', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'BOS_EDD', 'deactivation' ] );
register_uninstall_hook( __FILE__, [ 'BOS_EDD', 'uninstall' ] );

/**
 * Class BOS_EDD
 */
class BOS_EDD {
	const VERSION = '1.2';

	/**
	 * @var self
	 */
	private static $instance = null;

	/**
	 * @since 1.0
	 * @return $this
	 */
	public static function instance() {
		if ( is_null( self::$instance ) && ! ( self::$instance instanceof BOS_EDD ) ) {
			self::$instance = new self;

			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Activation function hook
	 *
	 * @since 1.0
	 * @return void
	 */
	public static function activation() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		update_option( 'bosedd_version', self::VERSION );
		$default_values = get_option( 'bosedd_options' );
		if ( empty( $default_values ) ) {
			$form_data = array();

			update_option( 'bosedd_options', $form_data );
		}
	}

	/**
	 * Deactivation function hook
	 *
	 * @since 1.0
	 * @return void
	 */
	public static function deactivation() {
		delete_option( 'bosedd_options' );
	}

	/**
	 * Uninstall function hook
	 *
	 * @since 1.0
	 * @return void
	 */
	public static function uninstall() {

	}

	/**
	 * Upgrade function hook
	 *
	 * @since 1.0
	 * @return void
	 */
	public function upgrade() {
		if ( get_option( 'bosedd_version' ) != self::VERSION ) {
		}
	}

	/**
	 * Setup Constants
	 */
	private function setup_constants() {

		// Directory
		define( 'BOSEDD_DIR', plugin_dir_path( __FILE__ ) );
		define( 'BOSEDD_DIR_FILE', BOSEDD_DIR . basename( __FILE__ ) );
		define( 'BOSEDD_INCLUDES_DIR', trailingslashit( BOSEDD_DIR . 'includes' ) );
		define( 'BOSEDD_TEMPLATES_DIR', trailingslashit( BOSEDD_DIR . 'templates' ) );
		define( 'BOSEDD_BASE_DIR', plugin_basename( __FILE__ ) );

		// URLS
		define( 'BOSEDD_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );
		define( 'BOSEDD_ASSETS_URL', trailingslashit( BOSEDD_URL . 'assets' ) );
	}

	/**
	 * Include Required Files
	 */
	private function includes() {

		if ( file_exists( BOSEDD_INCLUDES_DIR . 'integration/bos-integration.php' ) ) {
			require_once( BOSEDD_INCLUDES_DIR . 'integration/bos-integration.php' );
		}
	}

	private function hooks() {

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue_scripts' ], 11 );
		add_action( 'admin_notices', [ $this, 'plugin_branding' ] );
		add_action( 'plugins_loaded', [ $this, 'upgrade' ] );
	}

	/**
	 * Enqueue scripts on admin
	 *
	 * @param string $hook
	 * @since 1.0
	 */
	public function admin_enqueue_scripts( $hook ) {

		$screen = get_current_screen();
		$allowed_screens = array( 'badgeos_page_badgeos-edd-integration' );
		foreach ( badgeos_get_achievement_types_slugs() as $achievement_type ) {

			$post_type_object = get_post_type_object( $achievement_type );
			if ( ! is_object( $post_type_object ) ) {
				continue;
			}

			$allowed_screens[] = $post_type_object->name;
		}

		if ( ( isset( $screen->id ) && ! in_array( $screen->id, $allowed_screens ) ) ) {
			return false;
		}

		wp_enqueue_style( 'bosedd-admin-select2-css', BOSEDD_ASSETS_URL . 'css/select2.min.css', null, self::VERSION, null );
		wp_enqueue_script( 'bosedd-admin-select2-js', BOSEDD_ASSETS_URL . 'js/select2.min.js', null, self::VERSION, true );

		wp_enqueue_style( 'jquery-css', BOSEDD_ASSETS_URL . 'css/jquery-ui.css', [], self::VERSION, null );

		// plugin's admin script
		wp_enqueue_script( 'bosedd-admin-script', BOSEDD_ASSETS_URL . 'js/bosedd-admin-script.js', [ 'jquery', 'jquery-ui-tabs' ], self::VERSION, true );
		wp_enqueue_style( 'bosedd-admin-style', BOSEDD_ASSETS_URL . 'css/bosedd-admin-style.css', [ 'jquery-css', 'bosedd-admin-select2-css' ], self::VERSION, null );
	}

	/**
	 * Enqueue scripts on frontend
	 *
	 * @since 1.0
	 */
	public function frontend_enqueue_scripts() {
		wp_enqueue_style( 'bosedd-front-style', BOSEDD_ASSETS_URL . 'css/bosedd-front-style.css' );
	}

	/**
	 * Plugin Branding
	 */
	public function plugin_branding() {

		if ( ! is_admin() || ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			return;
		}

		if ( isset( $_GET['bosedd_dismiss_notice'] ) ) {
			update_user_meta( get_current_user_id(), 'bosedd_review_dismissed', 1 );
		}

		// Branding Notice
		$screen = get_current_screen();
		if ( isset( $screen->id ) && ( strstr( $screen->id, 'badgeos_page_badgeos-edd-integration' ) === false ) ) {
			$user_data = get_userdata( get_current_user_id() );
			$bosedd_review_dismissed = get_user_meta( get_current_user_id(), 'bosedd_review_dismissed', true );
			$dismiss_url = add_query_arg( 'bosedd_dismiss_notice', 1 );

			if ( ! $bosedd_review_dismissed ) {
				?>
                <div class="notice notice-info">
					<?php _e( '<p>Hi <strong>' . esc_html( $user_data->user_nicename ) . '</strong>, thankyou for using Badgeos EDD Integration Addon. If you find our plugin useful kindly take some time to leave a review and a rating for us <a href="https://wooninjas.com/wn-products/badgeos-edd-integration/" target="_blank" ><strong>here</strong></a> </p><p><a href="' . esc_attr( $dismiss_url ) . '">Dismiss Notice</a></p>', 'bosedd' ); ?>
                </div>
				<?php
			}
		}
	}
}

/**
 * Display admin notifications if dependency not found.
 */
function bosedd_ready() {
	if ( ! is_admin() ) {
		return;
	}

	if ( ! class_exists( 'Easy_Digital_Downloads' ) && ! class_exists( 'BadgeOS' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ), true );
		$class = 'notice is-dismissible error';
		$message = __( 'BadgeOS EDD Integration add-on requires <a href="https://wordpress.org/plugins/easy-digital-downloads/" target="_BLANK">EDD</a> & <a href="https://wordpress.org/plugins/badgeos/" target="_blank">BadgeOS</a> plugins to be activated.', 'bosedd' );
		printf( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
	} elseif ( ! class_exists( 'Easy_Digital_Downloads' ) && class_exists( 'BadgeOS' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ), true );
		$class = 'notice is-dismissible error';
		$message = __( 'BadgeOS EDD Integration add-on requires <a href="https://wordpress.org/plugins/easy-digital-downloads/" target="_BLANK">EDD</a> plugin to be activated.', 'bosedd' );
		printf( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
	} elseif ( class_exists( 'Easy_Digital_Downloads' ) && ! class_exists( 'BadgeOS' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ), true );
		$class = 'notice is-dismissible error';
		$message = __( 'BadgeOS EDD Integration add-on requires <a href="https://wordpress.org/plugins/badgeos/" target="_BLANK">BadgeOS</a> plugin to be activated.', 'bosedd' );
		printf( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
	}
}

/**
 * @return EDD_EO|bool
 */
function BOS_EDD() {
	if ( ! class_exists( 'Easy_Digital_Downloads' ) || ! class_exists( 'BadgeOS' ) ) {
		add_action( 'admin_notices', 'bosedd_ready' );
		return false;
	}

	BOS_EDD::instance();
}

add_action( 'plugins_loaded', 'BOS_EDD' );
