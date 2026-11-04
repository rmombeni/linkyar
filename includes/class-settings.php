<?php
/**
 * Settings: Settings API registration, sanitizers and shared getters.
 *
 * Options owned by this plugin:
 *  - linkyar_suggestions       { enabled, count }
 *  - linkyar_autolink_settings { enabled, default_max }
 *  - linkyar_general           { excluded_post_types[], excluded_category_ids[], capability }
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers settings and renders the «پیشنهاد لینک» + «تنظیمات» pages.
 */
class Linkyar_Settings {

	/**
	 * Hook Settings API registration.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the three setting groups with sanitize callbacks.
	 */
	public function register() {
		register_setting(
			'linkyar_suggestions_group',
			'linkyar_suggestions',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_suggestions' ),
				'default'           => $this->default_suggestions(),
			)
		);

		register_setting(
			'linkyar_autolink_group',
			'linkyar_autolink_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_autolink' ),
				'default'           => $this->default_autolink(),
			)
		);

		register_setting(
			'linkyar_general_group',
			'linkyar_general',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_general' ),
				'default'           => $this->default_general(),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Defaults + getters
	 * ------------------------------------------------------------------- */

	/**
	 * Default suggestions settings.
	 *
	 * @return array
	 */
	private function default_suggestions() {
		return array(
			'enabled' => 1,
			'count'   => 8,
		);
	}

	/**
	 * Default autolink settings.
	 *
	 * @return array
	 */
	private function default_autolink() {
		return array(
			'enabled'     => 1,
			'default_max' => 1,
		);
	}

	/**
	 * Default general settings.
	 *
	 * @return array
	 */
	private function default_general() {
		return array(
			'excluded_post_types'    => array(),
			'excluded_category_ids' => array(),
			'capability'             => 'manage_options',
		);
	}

	/**
	 * Suggestions settings (merged with defaults).
	 *
	 * @return array
	 */
	public static function suggestions() {
		$saved = get_option( 'linkyar_suggestions', array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), array(
			'enabled' => 1,
			'count'   => 8,
		) );
	}

	/**
	 * Autolink settings (merged with defaults).
	 *
	 * @return array
	 */
	public static function autolink() {
		$saved = get_option( 'linkyar_autolink_settings', array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), array(
			'enabled'     => 1,
			'default_max' => 1,
		) );
	}

	/**
	 * General settings (merged with defaults).
	 *
	 * @return array
	 */
	public static function general() {
		$saved = get_option( 'linkyar_general', array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), array(
			'excluded_post_types'    => array(),
			'excluded_category_ids' => array(),
			'capability'             => 'manage_options',
		) );
	}

	/**
	 * Capability required to access plugin admin screens.
	 * Filterable via `linkyar_capability`.
	 *
	 * @return string
	 */
	public static function capability() {
		$general = self::general();
		$cap     = ! empty( $general['capability'] ) ? $general['capability'] : 'manage_options';

		return apply_filters( 'linkyar_capability', $cap );
	}

	/**
	 * Post types not excluded by general settings.
	 *
	 * @return string[]
	 */
	public static function included_post_types() {
		$general  = self::general();
		$excluded = is_array( $general['excluded_post_types'] ) ? $general['excluded_post_types'] : array();
		$all      = get_post_types( array( 'public' => true ), 'names' );
		$all      = array_values( array_diff( $all, array( 'attachment' ) ) );

		return array_values( array_diff( $all, $excluded ) );
	}

	/* ---------------------------------------------------------------------
	 * Sanitizers
	 * ------------------------------------------------------------------- */

	/**
	 * Sanitize suggestions settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_suggestions( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled' => empty( $input['enabled'] ) ? 0 : 1,
			'count'   => max( 5, min( 15, isset( $input['count'] ) ? absint( $input['count'] ) : 8 ) ),
		);
	}

	/**
	 * Sanitize autolink settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_autolink( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled'     => empty( $input['enabled'] ) ? 0 : 1,
			'default_max' => max( 1, min( 20, isset( $input['default_max'] ) ? absint( $input['default_max'] ) : 1 ) ),
		);
	}

	/**
	 * Sanitize general settings (post types, categories, capability).
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_general( $input ) {
		$input = is_array( $input ) ? $input : array();

		$excluded_types = array();
		if ( ! empty( $input['excluded_post_types'] ) && is_array( $input['excluded_post_types'] ) ) {
			foreach ( $input['excluded_post_types'] as $type ) {
				$type = sanitize_key( $type );
				if ( $type && post_type_exists( $type ) ) {
					$excluded_types[] = $type;
				}
			}
		}

		$excluded_cats = array();
		if ( ! empty( $input['excluded_category_ids'] ) && is_array( $input['excluded_category_ids'] ) ) {
			foreach ( $input['excluded_category_ids'] as $cat_id ) {
				$cat_id = absint( $cat_id );
				if ( $cat_id ) {
					$excluded_cats[] = $cat_id;
				}
			}
		}

		$allowed_caps = array( 'manage_options', 'edit_others_posts', 'edit_pages', 'edit_posts' );
		$cap          = isset( $input['capability'] ) ? sanitize_key( $input['capability'] ) : 'manage_options';
		if ( ! in_array( $cap, $allowed_caps, true ) ) {
			$cap = 'manage_options';
		}

		return array(
			'excluded_post_types'    => array_values( array_unique( $excluded_types ) ),
			'excluded_category_ids' => array_values( array_unique( $excluded_cats ) ),
			'capability'             => $cap,
		);
	}

	/* ---------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------- */

	/**
	 * «پیشنهاد لینک» settings page (Feature 1 toggles).
	 */
	public function render_suggestions_page() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'linkyar' ) );
		}

		$settings = self::suggestions();
		?>
		<div class="wrap linkyar-wrap">
			<?php
			Linkyar_Plugin::page_header(
				'dashicons-admin-links',
				__( 'پیشنهاد لینک داخلی', 'linkyar' ),
				__( 'کنترل کنید پیشنهادهای لینک داخلی در ویرایشگر نمایش داده شوند و چند پیشنهاد برگردانده شود.', 'linkyar' )
			);
			?>
			<form method="post" action="options.php" class="linkyar-card linkyar-settings-form">
				<?php settings_fields( 'linkyar_suggestions_group' ); ?>

				<div class="linkyar-field">
					<label class="linkyar-toggle">
						<input type="checkbox" name="linkyar_suggestions[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
						<span class="linkyar-toggle__track" aria-hidden="true"></span>
						<span class="linkyar-toggle__label">
							<?php esc_html_e( 'فعال‌سازی پیشنهاد لینک', 'linkyar' ); ?>
							<?php Linkyar_Plugin::help( __( 'متا باکس «پیشنهادهای لینک داخلی» را در صفحهٔ ویرایش پست نمایش می‌دهد.', 'linkyar' ) ); ?>
						</span>
					</label>
					<p class="linkyar-field__hint"><?php esc_html_e( 'کاربران با دسترسی ویرایش پست می‌توانند پیشنهادها را ببینند و با یک کلیک لینک درج کنند.', 'linkyar' ); ?></p>
				</div>

				<div class="linkyar-field">
					<label for="linkyar-suggest-count">
						<?php esc_html_e( 'تعداد پیشنهادها', 'linkyar' ); ?>
						<?php Linkyar_Plugin::help( __( 'بین ۵ تا ۱۵ پیشنهاد؛ پیش‌فرض ۸ است.', 'linkyar' ) ); ?>
					</label>
					<input type="number" id="linkyar-suggest-count" name="linkyar_suggestions[count]" min="5" max="15" step="1"
						value="<?php echo esc_attr( $settings['count'] ); ?>" class="linkyar-input linkyar-input--sm" />
					<p class="linkyar-field__hint"><?php esc_html_e( 'بازه مجاز: ۵ تا ۱۵', 'linkyar' ); ?></p>
				</div>

				<div class="linkyar-form-actions">
					<?php submit_button( __( 'ذخیره تغییرات', 'linkyar' ), 'primary linkyar-btn' ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * «تنظیمات» general page.
	 */
	public function render_general_page() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'linkyar' ) );
		}

		$general     = self::general();
		$post_types  = get_post_types( array( 'public' => true ), 'objects' );
		$categories  = get_categories( array( 'hide_empty' => false ) );
		$excluded    = is_array( $general['excluded_post_types'] ) ? $general['excluded_post_types'] : array();
		$excluded_c  = is_array( $general['excluded_category_ids'] ) ? $general['excluded_category_ids'] : array();
		?>
		<div class="wrap linkyar-wrap">
			<?php
			Linkyar_Plugin::page_header(
				'dashicons-admin-generic',
				__( 'تنظیمات', 'linkyar' ),
				__( 'نوع‌های محتوا و دسته‌هایی که لینکیار باید نادیده بگیرد را مشخص کنید.', 'linkyar' )
			);
			?>
			<form method="post" action="options.php" class="linkyar-card linkyar-settings-form">
				<?php settings_fields( 'linkyar_general_group' ); ?>

				<fieldset class="linkyar-field">
					<legend class="linkyar-field__legend">
						<?php esc_html_e( 'نوع‌های محتوای مستثنی', 'linkyar' ); ?>
						<?php Linkyar_Plugin::help( __( 'در این نوع‌ها هیچ پیشنهاد یا اتولینکی اعمال نمی‌شود.', 'linkyar' ) ); ?>
					</legend>
					<div class="linkyar-check-grid">
						<?php foreach ( $post_types as $type ) : ?>
							<label class="linkyar-check">
								<input type="checkbox" name="linkyar_general[excluded_post_types][]" value="<?php echo esc_attr( $type->name ); ?>"
									<?php checked( in_array( $type->name, $excluded, true ) ); ?> />
								<span><?php echo esc_html( $type->labels->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<fieldset class="linkyar-field">
					<legend class="linkyar-field__legend">
						<?php esc_html_e( 'دسته‌های مستثنی', 'linkyar' ); ?>
						<?php Linkyar_Plugin::help( __( 'اتولینک روی پست‌های این دسته‌ها اعمال نمی‌شود.', 'linkyar' ) ); ?>
					</legend>
					<select name="linkyar_general[excluded_category_ids][]" multiple size="6" class="linkyar-select">
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( in_array( (int) $cat->term_id, array_map( 'intval', $excluded_c ), true ) ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="linkyar-field__hint"><?php esc_html_e( 'برای انتخاب چندتایی Ctrl/Cmd را نگه دارید.', 'linkyar' ); ?></p>
				</fieldset>

				<div class="linkyar-field">
					<label for="linkyar-cap">
						<?php esc_html_e( 'سطح دسترسی مدیریت', 'linkyar' ); ?>
						<?php Linkyar_Plugin::help( __( 'کاربران با این نقش به صفحات لینکیار دسترسی دارند.', 'linkyar' ) ); ?>
					</label>
					<select id="linkyar-cap" name="linkyar_general[capability]" class="linkyar-select linkyar-select--sm">
						<option value="manage_options" <?php selected( 'manage_options' === $general['capability'] ); ?>><?php esc_html_e( 'مدیر کل (manage_options)', 'linkyar' ); ?></option>
						<option value="edit_others_posts" <?php selected( 'edit_others_posts' === $general['capability'] ); ?>><?php esc_html_e( 'ویرایشگر (edit_others_posts)', 'linkyar' ); ?></option>
						<option value="edit_pages" <?php selected( 'edit_pages' === $general['capability'] ); ?>><?php esc_html_e( 'ویرایشگر صفحات (edit_pages)', 'linkyar' ); ?></option>
						<option value="edit_posts" <?php selected( 'edit_posts' === $general['capability'] ); ?>><?php esc_html_e( 'نویسنده (edit_posts)', 'linkyar' ); ?></option>
					</select>
				</div>

				<div class="linkyar-form-actions">
					<?php submit_button( __( 'ذخیره تغییرات', 'linkyar' ), 'primary linkyar-btn' ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}
