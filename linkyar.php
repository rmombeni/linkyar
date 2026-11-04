<?php
/**
 * Plugin Name:          لینکیار (LinkYar)
 * Plugin URI:           https://github.com/rmombeni/linkyar
 * Description:          دستیار لینک‌سازی داخلی وردپرس — پیشنهاد لینک در ویرایشگر، اتولینک هوشمند و گزارش لینک‌های شکسته.
 * Version:              1.0.0
 * Requires at least:    6.0
 * Tested up to:         6.7
 * Requires PHP:         7.4
 * Author:               Reza Mombeni
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          linkyar
 * Domain Path:          /languages
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

define( 'PLYAR_VERSION', '1.0.0' );
define( 'PLYAR_FILE', __FILE__ );
define( 'PLYAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLYAR_URL', plugin_dir_url( __FILE__ ) );

require_once PLYAR_DIR . 'includes/class-i18n.php';
require_once PLYAR_DIR . 'includes/class-jalali.php';
require_once PLYAR_DIR . 'includes/class-assets.php';
require_once PLYAR_DIR . 'includes/class-settings.php';
require_once PLYAR_DIR . 'includes/class-suggestions.php';
require_once PLYAR_DIR . 'includes/class-autolink.php';
require_once PLYAR_DIR . 'includes/class-report.php';
require_once PLYAR_DIR . 'includes/class-dashboard.php';
require_once PLYAR_DIR . 'includes/class-plugin.php';

// Activation stores the plugin version only; all other defaults are lazy-loaded.
register_activation_hook( __FILE__, array( 'Linkyar_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Linkyar_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Linkyar_Plugin', 'instance' ) );
