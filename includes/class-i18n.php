<?php
/**
 * Internationalization bootstrap.
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the linkyar text domain on init (required since WP 6.7 to avoid
 * premature translation loading notices).
 */
class Linkyar_I18n {

	/**
	 * Hook the text domain loader.
	 */
	public function init() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations from /languages.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'linkyar', false, dirname( plugin_basename( PLYAR_FILE ) ) . '/languages' );
	}
}
