<?php
/**
 * Plugin bootstrap: wires all modules, admin menus and shared UI helpers.
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin container (singleton).
 */
class Linkyar_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Linkyar_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Loaded modules.
	 *
	 * @var array<string, object>
	 */
	public $modules = array();

	/**
	 * Get singleton.
	 *
	 * @return Linkyar_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor: instantiate modules (each hooks itself).
	 */
	private function __construct() {
		$i18n = new Linkyar_I18n();
		$i18n->init();

		$this->modules['settings']     = new Linkyar_Settings();
		$this->modules['assets']       = new Linkyar_Assets();
		$this->modules['suggestions']  = new Linkyar_Suggestions();
		$this->modules['autolink']     = new Linkyar_Autolink();
		$this->modules['report']       = new Linkyar_Report();
		$this->modules['dashboard']    = new Linkyar_Dashboard();

		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'redirect_to_wizard' ) );
	}

	/**
	 * Activation: version storage only (per spec — no heavy work here).
	 */
	public static function activate() {
		update_option( 'linkyar_version', PLYAR_VERSION );
	}

	/**
	 * Deactivation: keep data; cleanup happens in uninstall.php.
	 */
	public static function deactivate() {
		// Intentionally empty — rules/report survive reactivation.
	}

	/**
	 * Register top-level menu + 4 submenus + hidden onboarding wizard.
	 */
	public function register_menus() {
		$cap = Linkyar_Settings::capability();

		add_menu_page(
			__( 'لینکیار', 'linkyar' ),
			__( 'لینکیار', 'linkyar' ),
			$cap,
			'linkyar',
			array( $this->modules['dashboard'], 'render_page' ),
			'dashicons-admin-links',
			61
		);

		add_submenu_page(
			'linkyar',
			__( 'داشبورد', 'linkyar' ),
			__( 'داشبورد', 'linkyar' ),
			$cap,
			'linkyar',
			array( $this->modules['dashboard'], 'render_page' )
		);

		add_submenu_page(
			'linkyar',
			__( 'پیشنهاد لینک', 'linkyar' ),
			__( 'پیشنهاد لینک', 'linkyar' ),
			$cap,
			'linkyar_suggestions',
			array( $this->modules['settings'], 'render_suggestions_page' )
		);

		add_submenu_page(
			'linkyar',
			__( 'اتولینک', 'linkyar' ),
			__( 'اتولینک', 'linkyar' ),
			$cap,
			'linkyar_autolink',
			array( $this->modules['autolink'], 'render_page' )
		);

		add_submenu_page(
			'linkyar',
			__( 'گزارش لینک‌ها', 'linkyar' ),
			__( 'گزارش لینک‌ها', 'linkyar' ),
			$cap,
			'linkyar_report',
			array( $this->modules['report'], 'render_page' )
		);

		add_submenu_page(
			'linkyar',
			__( 'تنظیمات', 'linkyar' ),
			__( 'تنظیمات', 'linkyar' ),
			$cap,
			'linkyar_settings',
			array( $this->modules['settings'], 'render_general_page' )
		);

		// Onboarding wizard — registered but hidden from the menu UI.
		add_submenu_page(
			'linkyar',
			__( 'راه‌اندازی', 'linkyar' ),
			__( 'راه‌اندازی', 'linkyar' ),
			$cap,
			'linkyar_wizard',
			array( $this->modules['dashboard'], 'render_wizard' )
		);

		global $submenu;
		if ( isset( $submenu['linkyar'] ) ) {
			foreach ( $submenu['linkyar'] as $key => $item ) {
				if ( 'linkyar_wizard' === $item[2] ) {
					unset( $submenu['linkyar'][ $key ] );
				}
			}
		}
	}

	/**
	 * First-run: send users to the onboarding wizard until it is completed.
	 * Only fires on the WP dashboard / Plugins screen to avoid hijacking other screens.
	 */
	public function redirect_to_wizard() {
		if ( get_option( 'linkyar_onboarded', false ) ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}
		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			return;
		}

		// Never hijack the plugin's own screens (wizard included).
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page slug.
		if ( '' !== $page && false !== strpos( $page, 'linkyar' ) ) {
			return;
		}

		$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( ! in_array( $pagenow, array( 'index.php', 'plugins.php' ), true ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=linkyar_wizard' ) );
		exit;
	}

	/**
	 * Shared page header (icon + title + description + primary CTA).
	 *
	 * @param string $icon  Dashicons class.
	 * @param string $title Page title.
	 * @param string $desc  One-line description.
	 * @param string $cta   Optional primary CTA HTML (already escaped).
	 */
	public static function page_header( $icon, $title, $desc, $cta = '' ) {
		printf(
			'<div class="linkyar-header"><div class="linkyar-header__text"><span class="linkyar-header__icon" aria-hidden="true"><span class="dashicons %1$s"></span></span><div><h1 class="linkyar-header__title">%2$s</h1><p class="linkyar-header__desc">%3$s</p></div></div><div class="linkyar-header__cta">%4$s</div></div>',
			esc_attr( $icon ),
			esc_html( $title ),
			esc_html( $desc ),
			$cta // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by callers with escaping helpers.
		);
	}

	/**
	 * Shared empty state (icon + title + hint + CTA) — never a bare table.
	 *
	 * @param string $icon  Dashicons class.
	 * @param string $title Empty state title.
	 * @param string $hint  Helper text.
	 * @param string $cta   Optional CTA HTML (already escaped).
	 */
	public static function empty_state( $icon, $title, $hint, $cta = '' ) {
		printf(
			'<div class="linkyar-empty"><span class="linkyar-empty__icon dashicons %1$s" aria-hidden="true"></span><h3 class="linkyar-empty__title">%2$s</h3><p class="linkyar-empty__hint">%3$s</p>%4$s</div>',
			esc_attr( $icon ),
			esc_html( $title ),
			esc_html( $hint ),
			$cta // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by callers with escaping helpers.
		);
	}

	/**
	 * Small inline help tooltip («؟»).
	 *
	 * @param string $text Tooltip body (plain text).
	 */
	public static function help( $text ) {
		printf(
			'<button type="button" class="linkyar-help" aria-label="%1$s" title="%2$s">؟</button>',
			esc_attr__( 'راهنما', 'linkyar' ),
			esc_attr( $text )
		);
	}
}
