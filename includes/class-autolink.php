<?php
/**
 * Feature 2 — Smart auto-linking (اتولینک هوشمند).
 *
 * Rules live in the `linkyar_autolink_rules` option (single array),
 * cached in a 12h transient and flushed on every save/delete/toggle.
 * Frontend replacement runs only on singular views via the_content/the_excerpt.
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autolink rules CRUD + content replacement engine.
 */
class Linkyar_Autolink {

	/**
	 * Hook filters + AJAX handlers.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'filter_content' ), 20 );

		add_action( 'wp_ajax_linkyar_save_rule', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_linkyar_delete_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_linkyar_toggle_rule', array( $this, 'ajax_toggle_rule' ) );
	}

	/* ---------------------------------------------------------------------
	 * Rules storage
	 * ------------------------------------------------------------------- */

	/**
	 * All rules (from cache or option).
	 *
	 * @return array[]
	 */
	public static function get_rules() {
		$cached = get_transient( 'linkyar_rules_cache' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rules = get_option( 'linkyar_autolink_rules', array() );
		$rules = is_array( $rules ) ? $rules : array();

		set_transient( 'linkyar_rules_cache', $rules, 12 * HOUR_IN_SECONDS );

		return $rules;
	}

	/**
	 * Persist rules and flush the cache.
	 *
	 * @param array[] $rules Rules list.
	 */
	public static function save_rules( $rules ) {
		update_option( 'linkyar_autolink_rules', array_values( $rules ), false );
		delete_transient( 'linkyar_rules_cache' );
	}

	/**
	 * Find a rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array|null
	 */
	public static function find_rule( $rule_id ) {
		foreach ( self::get_rules() as $rule ) {
			if ( (int) $rule['id'] === (int) $rule_id ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Next auto-increment rule ID.
	 *
	 * @return int
	 */
	private static function next_id() {
		$max = 0;
		foreach ( self::get_rules() as $rule ) {
			$max = max( $max, (int) $rule['id'] );
		}

		return $max + 1;
	}

	/**
	 * Build a sanitized rule array from raw input.
	 *
	 * @param array  $input    Raw input.
	 * @param int|null $rule_id Existing rule ID (null = new).
	 * @return array|WP_Error
	 */
	public static function build_rule( $input, $rule_id = null ) {
		$keywords_raw = isset( $input['keywords'] ) ? sanitize_textarea_field( $input['keywords'] ) : '';
		$keywords     = array_values(
			array_filter(
				array_map(
					'trim',
					preg_split( '/[,،\n]+/u', $keywords_raw )
				)
			)
		);

		if ( empty( $keywords ) ) {
			return new WP_Error( 'linkyar_no_keyword', __( 'کلیدواژه را وارد کنید', 'linkyar' ) );
		}

		$target_post = isset( $input['target_post'] ) ? absint( $input['target_post'] ) : 0;
		$target_url  = isset( $input['target_url'] ) ? esc_url_raw( trim( (string) $input['target_url'] ) ) : '';

		if ( $target_post ) {
			$permalink = get_permalink( $target_post );
			if ( ! $permalink ) {
				return new WP_Error( 'linkyar_bad_target', __( 'صفحهٔ هدف را انتخاب کنید', 'linkyar' ) );
			}
			$target_url = $permalink;
		} elseif ( '' === $target_url ) {
			return new WP_Error( 'linkyar_no_target', __( 'صفحهٔ هدف را انتخاب کنید', 'linkyar' ) );
		}

		$exclude_cats = array();
		if ( ! empty( $input['exclude_categories'] ) && is_array( $input['exclude_categories'] ) ) {
			foreach ( $input['exclude_categories'] as $cat_id ) {
				$cat_id = absint( $cat_id );
				if ( $cat_id ) {
					$exclude_cats[] = $cat_id;
				}
			}
		}

		return array(
			'id'                 => $rule_id ? (int) $rule_id : self::next_id(),
			'keywords'           => implode( ', ', array_slice( $keywords, 0, 20 ) ),
			'target_post'        => $target_post,
			'target_url'         => $target_url,
			'max_links'          => max( 1, min( 50, isset( $input['max_links'] ) ? absint( $input['max_links'] ) : 1 ) ),
			'case_sensitive'     => empty( $input['case_sensitive'] ) ? 0 : 1,
			'only_first'         => empty( $input['only_first'] ) ? 0 : 1,
			'exclude_categories' => array_values( array_unique( $exclude_cats ) ),
			'enabled'            => empty( $input['enabled'] ) ? 0 : 1,
		);
	}

	/**
	 * Split a stored keyword string into tokens.
	 *
	 * @param string $keywords Raw comma-separated string.
	 * @return string[]
	 */
	public static function keyword_list( $keywords ) {
		$parts = preg_split( '/[,،]+/u', (string) $keywords );

		return array_values( array_filter( array_map( 'trim', is_array( $parts ) ? $parts : array() ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin page — «قوانین اتولینک»
	 * ------------------------------------------------------------------- */

	/**
	 * Render the autolink rules screen (settings row + CRUD table + slide-in form).
	 */
	public function render_page() {
		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'linkyar' ) );
		}

		$settings = Linkyar_Settings::autolink();
		$rules    = self::get_rules();

		$target_types = Linkyar_Settings::included_post_types();
		$targets      = get_posts(
			array(
				'post_type'      => $target_types,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$categories   = get_categories( array( 'hide_empty' => false ) );

		$cta = sprintf(
			'<button type="button" class="linkyar-btn linkyar-btn--primary" id="linkyar-add-rule"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> %s</button>',
			esc_html__( 'افزودن قانون', 'linkyar' )
		);
		?>
		<div class="wrap linkyar-wrap linkyar-rules"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'linkyar_rules' ) ); ?>">

			<?php
			Linkyar_Plugin::page_header(
				'dashicons-randomize',
				__( 'قوانین اتولینک', 'linkyar' ),
				__( 'کلیدواژه‌ها را به‌صورت خودکار به صفحهٔ هدف لینک کنید — بدون تداخل با لینک‌های موجود.',
					'linkyar' ),
				$cta
			);
			?>

			<form method="post" action="options.php" class="linkyar-card linkyar-settings-form">
				<?php settings_fields( 'linkyar_autolink_group' ); ?>
				<div class="linkyar-inline-settings">
					<label class="linkyar-toggle">
						<input type="checkbox" name="linkyar_autolink_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
						<span class="linkyar-toggle__track" aria-hidden="true"></span>
						<span class="linkyar-toggle__label">
							<?php esc_html_e( 'اتولینک فعال', 'linkyar' ); ?>
							<?php Linkyar_Plugin::help( __( 'کل قابلیت اتولینک را روشن/خاموش می‌کند.', 'linkyar' ) ); ?>
						</span>
					</label>

					<label class="linkyar-inline-label" for="linkyar-default-max">
						<?php esc_html_e( 'حداکثر پیش‌فرض لینک در هر پست', 'linkyar' ); ?>
						<input type="number" id="linkyar-default-max" name="linkyar_autolink_settings[default_max]" min="1" max="20"
							value="<?php echo esc_attr( max( 1, (int) $settings['default_max'] ) ); ?>" class="linkyar-input linkyar-input--sm" />
					</label>

					<?php submit_button( __( 'ذخیره تغییرات', 'linkyar' ), 'primary linkyar-btn' ); ?>
				</div>
			</form>

			<div class="linkyar-card linkyar-table-card">
				<?php if ( empty( $rules ) ) : ?>
					<?php
					Linkyar_Plugin::empty_state(
						'dashicons-randomize',
						__( 'هنوز قانونی تعریف نشده', 'linkyar' ),
						__( 'اولین قانون اتولینک را بسازید تا کلیدواژه‌ها خودکار لینک شوند.', 'linkyar' ),
						'<button type="button" class="linkyar-btn linkyar-btn--primary" id="linkyar-add-rule-empty">' . esc_html__( 'افزودن قانون', 'linkyar' ) . '</button>'
					);
					?>
				<?php else : ?>
					<div class="linkyar-table-scroll">
						<table class="linkyar-table linkyar-rules-table" id="linkyar-rules-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'کلیدواژه‌ها', 'linkyar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'صفحهٔ هدف', 'linkyar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'حداکثر لینک', 'linkyar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'وضعیت', 'linkyar' ); ?></th>
									<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'linkyar' ); ?></span></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rules as $rule ) : ?>
									<?php $this->render_rule_row( $rule ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>

			<!-- Slide-in add/edit panel -->
			<div class="linkyar-panel-backdrop" id="linkyar-rule-backdrop" hidden></div>
			<aside class="linkyar-panel" id="linkyar-rule-panel" role="dialog" aria-modal="true"
				aria-labelledby="linkyar-rule-panel-title" hidden>
				<div class="linkyar-panel__head">
					<h2 class="linkyar-panel__title" id="linkyar-rule-panel-title"><?php esc_html_e( 'افزودن قانون', 'linkyar' ); ?></h2>
					<button type="button" class="linkyar-icon-btn" id="linkyar-rule-close" aria-label="<?php esc_attr_e( 'بستن', 'linkyar' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>

				<input type="hidden" id="linkyar-rule-id" value="0" />

				<div class="linkyar-field">
					<label for="linkyar-rule-keywords"><?php esc_html_e( 'کلیدواژه‌ها', 'linkyar' ); ?> *</label>
					<textarea id="linkyar-rule-keywords" class="linkyar-textarea linkyar-rule-input"
						placeholder="<?php esc_attr_e( 'مثال: افزونه وردپرس, سئوی سایت', 'linkyar' ); ?>"></textarea>
					<span class="linkyar-field__error" id="linkyar-rule-keywords-error" hidden><?php esc_html_e( 'کلیدواژه را وارد کنید', 'linkyar' ); ?></span>
					<p class="linkyar-field__hint">
						<?php esc_html_e( 'پیش‌نمایش:', 'linkyar' ); ?>
						<span class="linkyar-keyword-preview" id="linkyar-keyword-preview">—</span>
					</p>
				</div>

				<div class="linkyar-field">
					<label for="linkyar-rule-target-post"><?php esc_html_e( 'صفحهٔ هدف', 'linkyar' ); ?> *</label>
					<select id="linkyar-rule-target-post" class="linkyar-select linkyar-rule-input">
						<option value="0"><?php esc_html_e( '— انتخاب کنید —', 'linkyar' ); ?></option>
						<?php foreach ( $targets as $target ) : ?>
							<option value="<?php echo esc_attr( $target->ID ); ?>">
								<?php echo esc_html( $target->post_title ? wp_trim_words( $target->post_title, 8 ) : '#' . $target->ID ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="linkyar-field__hint"><?php esc_html_e( 'یا آدرس دلخواه:', 'linkyar' ); ?></p>
					<input type="url" id="linkyar-rule-target-url" class="linkyar-input linkyar-rule-input" dir="ltr"
						placeholder="https://example.com/page/" />
					<span class="linkyar-field__error" id="linkyar-rule-target-error" hidden><?php esc_html_e( 'صفحهٔ هدف را انتخاب کنید', 'linkyar' ); ?></span>
				</div>

				<div class="linkyar-field">
					<label for="linkyar-rule-max"><?php esc_html_e( 'حداکثر لینک در هر پست', 'linkyar' ); ?></label>
					<input type="number" id="linkyar-rule-max" class="linkyar-input linkyar-input--sm" min="1" max="50"
						value="<?php echo esc_attr( max( 1, (int) $settings['default_max'] ) ); ?>" />
				</div>

				<div class="linkyar-field">
					<label class="linkyar-toggle">
						<input type="checkbox" id="linkyar-rule-first" />
						<span class="linkyar-toggle__track" aria-hidden="true"></span>
						<span class="linkyar-toggle__label"><?php esc_html_e( 'فقط اولین مورد', 'linkyar' ); ?></span>
					</label>
				</div>
				<div class="linkyar-field">
					<label class="linkyar-toggle">
						<input type="checkbox" id="linkyar-rule-case" />
						<span class="linkyar-toggle__track" aria-hidden="true"></span>
						<span class="linkyar-toggle__label"><?php esc_html_e( 'حساس به حروف (انگلیسی)', 'linkyar' ); ?></span>
					</label>
				</div>
				<div class="linkyar-field">
					<label class="linkyar-toggle">
						<input type="checkbox" id="linkyar-rule-enabled" checked />
						<span class="linkyar-toggle__track" aria-hidden="true"></span>
						<span class="linkyar-toggle__label"><?php esc_html_e( 'قانون فعال', 'linkyar' ); ?></span>
					</label>
				</div>

				<div class="linkyar-field">
					<label for="linkyar-rule-cats"><?php esc_html_e( 'دسته‌های مستثنی', 'linkyar' ); ?></label>
					<select id="linkyar-rule-cats" multiple size="5" class="linkyar-select">
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="linkyar-field__hint"><?php esc_html_e( 'روی پست‌های این دسته‌ها اعمال نمی‌شود.', 'linkyar' ); ?></p>
				</div>

				<div class="linkyar-wizard__actions">
					<button type="button" class="linkyar-btn linkyar-btn--ghost" id="linkyar-rule-cancel"><?php esc_html_e( 'انصراف', 'linkyar' ); ?></button>
					<button type="button" class="linkyar-btn linkyar-btn--primary" id="linkyar-rule-save">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'ذخیره قانون', 'linkyar' ); ?>
					</button>
				</div>
			</aside>
		</div>
		<?php
	}

	/**
	 * Render one rules-table row (also used as the client-side template shape).
	 *
	 * @param array $rule Rule definition.
	 */
	private function render_rule_row( $rule ) {
		$keywords = self::keyword_list( $rule['keywords'] );
		$enabled  = ! empty( $rule['enabled'] );
		?>
		<tr data-id="<?php echo esc_attr( $rule['id'] ); ?>">
			<td data-label="<?php esc_attr_e( 'کلیدواژه‌ها', 'linkyar' ); ?>">
				<div class="linkyar-keywords">
					<?php foreach ( $keywords as $keyword ) : ?>
						<span class="linkyar-chip"><?php echo esc_html( $keyword ); ?></span>
					<?php endforeach; ?>
				</div>
			</td>
			<td data-label="<?php esc_attr_e( 'صفحهٔ هدف', 'linkyar' ); ?>">
				<a class="linkyar-url" href="<?php echo esc_url( $rule['target_url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $rule['target_url'] ); ?>
				</a>
			</td>
			<td data-label="<?php esc_attr_e( 'حداکثر لینک', 'linkyar' ); ?>">
				<?php echo esc_html( Linkyar_Jalali::fa_num( ! empty( $rule['only_first'] ) ? 1 : (int) $rule['max_links'] ) ); ?>
			</td>
			<td data-label="<?php esc_attr_e( 'وضعیت', 'linkyar' ); ?>">
				<label class="linkyar-toggle linkyar-toggle--sm">
					<input type="checkbox" class="linkyar-rule-toggle" data-id="<?php echo esc_attr( $rule['id'] ); ?>" <?php checked( $enabled ); ?> />
					<span class="linkyar-toggle__track" aria-hidden="true"></span>
					<span class="linkyar-toggle__label"><?php echo $enabled ? esc_html__( 'فعال', 'linkyar' ) : esc_html__( 'غیرفعال', 'linkyar' ); ?></span>
				</label>
			</td>
			<td data-label="<?php esc_attr_e( 'عملیات', 'linkyar' ); ?>">
				<div class="linkyar-row-actions">
					<button type="button" class="linkyar-icon-btn linkyar-rule-edit" data-id="<?php echo esc_attr( $rule['id'] ); ?>"
						aria-label="<?php esc_attr_e( 'ویرایش', 'linkyar' ); ?>" title="<?php esc_attr_e( 'ویرایش', 'linkyar' ); ?>">
						<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					</button>
					<button type="button" class="linkyar-icon-btn linkyar-icon-btn--danger linkyar-rule-delete" data-id="<?php echo esc_attr( $rule['id'] ); ?>"
						aria-label="<?php esc_attr_e( 'حذف', 'linkyar' ); ?>" title="<?php esc_attr_e( 'حذف', 'linkyar' ); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					</button>
				</div>
			</td>
		</tr>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * AJAX: CRUD
	 * ------------------------------------------------------------------- */

	/**
	 * Create or update a rule. Nonce + capability checked.
	 */
	public function ajax_save_rule() {
		check_ajax_referer( 'linkyar_rules', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$input   = array(
			'keywords'           => isset( $_POST['keywords'] ) ? wp_unslash( $_POST['keywords'] ) : '',
			'target_post'        => isset( $_POST['target_post'] ) ? absint( $_POST['target_post'] ) : 0,
			'target_url'         => isset( $_POST['target_url'] ) ? wp_unslash( $_POST['target_url'] ) : '',
			'max_links'          => isset( $_POST['max_links'] ) ? absint( $_POST['max_links'] ) : 1,
			'case_sensitive'     => isset( $_POST['case_sensitive'] ) ? 1 : 0,
			'only_first'         => isset( $_POST['only_first'] ) ? 1 : 0,
			'exclude_categories' => isset( $_POST['exclude_categories'] ) && is_array( $_POST['exclude_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['exclude_categories'] ) ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint map above.
			'enabled'            => isset( $_POST['enabled'] ) ? 1 : 0,
		);
		// phpcs:enable

		$existing = $rule_id ? self::find_rule( $rule_id ) : null;
		if ( $rule_id && ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'قانون پیدا نشد.', 'linkyar' ) ), 404 );
		}

		$rule = self::build_rule( $input, $existing ? $rule_id : null );

		if ( is_wp_error( $rule ) ) {
			wp_send_json_error( array( 'message' => $rule->get_error_message() ), 422 );
		}

		$rules = self::get_rules();
		if ( $existing ) {
			foreach ( $rules as $i => $item ) {
				if ( (int) $item['id'] === (int) $rule_id ) {
					$rules[ $i ] = $rule;
					break;
				}
			}
		} else {
			$rules[] = $rule;
		}

		self::save_rules( $rules );

		wp_send_json_success(
			array(
				'rule'    => $rule,
				'message' => $existing ? __( 'قانون به‌روزرسانی شد', 'linkyar' ) : __( 'قانون اضافه شد', 'linkyar' ),
			)
		);
	}

	/**
	 * Delete a rule.
	 */
	public function ajax_delete_rule() {
		check_ajax_referer( 'linkyar_rules', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
		$rules   = self::get_rules();
		$filtered = array_values(
			array_filter(
				$rules,
				static function ( $rule ) use ( $rule_id ) {
					return (int) $rule['id'] !== $rule_id;
				}
			)
		);

		if ( count( $filtered ) === count( $rules ) ) {
			wp_send_json_error( array( 'message' => __( 'قانون پیدا نشد.', 'linkyar' ) ), 404 );
		}

		self::save_rules( $filtered );

		wp_send_json_success( array( 'message' => __( 'حذف شد', 'linkyar' ) ) );
	}

	/**
	 * Toggle a rule enabled/disabled.
	 */
	public function ajax_toggle_rule() {
		check_ajax_referer( 'linkyar_rules', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
		$rules   = self::get_rules();
		$found   = false;
		$enabled = 0;

		foreach ( $rules as $i => $rule ) {
			if ( (int) $rule['id'] === $rule_id ) {
				$rules[ $i ]['enabled'] = empty( $rule['enabled'] ) ? 1 : 0;
				$enabled                = (int) $rules[ $i ]['enabled'];
				$found                  = true;
				break;
			}
		}

		if ( ! $found ) {
			wp_send_json_error( array( 'message' => __( 'قانون پیدا نشد.', 'linkyar' ) ), 404 );
		}

		self::save_rules( $rules );

		wp_send_json_success(
			array(
				'enabled' => $enabled,
				'message' => __( 'ذخیره شد', 'linkyar' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Frontend replacement
	 * ------------------------------------------------------------------- */

	/**
	 * Apply enabled rules to singular content/excerpt.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( is_admin() || ! is_singular() || ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$settings = Linkyar_Settings::autolink();
		if ( empty( $settings['enabled'] ) ) {
			return $content;
		}

		$rules = array();
		foreach ( self::get_rules() as $rule ) {
			if ( empty( $rule['enabled'] ) || '' === trim( (string) $rule['keywords'] ) || empty( $rule['target_url'] ) ) {
				continue;
			}
			$rules[] = $rule;
		}

		if ( empty( $rules ) ) {
			return $content;
		}

		$post_id = get_queried_object_id();
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return $content;
		}

		// Global category exclusions: skip autolink entirely for this post.
		$general     = Linkyar_Settings::general();
		$global_excl = is_array( $general['excluded_category_ids'] ) ? array_map( 'intval', $general['excluded_category_ids'] ) : array();
		$post_cats   = wp_get_post_categories( $post->ID );

		if ( ! empty( $global_excl ) && array_intersect( $global_excl, $post_cats ) ) {
			return $content;
		}

		// 1) Protect <pre>/<code>/<script>/<style> bodies with placeholders.
		$protected = array();
		$content   = preg_replace_callback(
			'#<(pre|code|script|style)\b[^>]*>.*?</\1>#is',
			static function ( $m ) use ( &$protected ) {
				$key = "LINKYAR_PROT_PLACEHOLDER_" . count( $protected ) . "_END";
				$protected[ $key ] = $m[0];

				return $key;
			},
			$content
		);

		if ( ! is_string( $content ) ) {
			return $content;
		}

		// 2) Split on existing anchors — only non-anchor segments get replaced.
		$parts = preg_split( '#(<a\b[^>]*>.*?</a>)#is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			$parts = array( $content );
		}

		// Per-rule replacement budget across the whole content.
		$used = array();
		foreach ( $rules as $rule ) {
			$used[ (int) $rule['id'] ] = 0;
		}

		foreach ( $parts as $i => $part ) {
			// Keep anchor chunks untouched (never nest links).
			if ( preg_match( '#^<a\b#i', $part ) ) {
				continue;
			}

			foreach ( $rules as $rule ) {
				$rule_id = (int) $rule['id'];

				// Per-rule category exclusions.
				if ( ! empty( $rule['exclude_categories'] ) && array_intersect( array_map( 'intval', $rule['exclude_categories'] ), $post_cats ) ) {
					continue;
				}

				$max = ! empty( $rule['only_first'] ) ? 1 : max( 1, (int) $rule['max_links'] );
				if ( $used[ $rule_id ] >= $max ) {
					continue;
				}

				$parts[ $i ] = $this->replace_keywords( $part, $rule, $max, $used[ $rule_id ] );
			}
		}

		$content = implode( '', $parts );

		// 3) Restore protected blocks.
		if ( ! empty( $protected ) ) {
			$content = strtr( $content, $protected );
		}

		return $content;
	}

	/**
	 * Replace keyword occurrences inside one text segment.
	 *
	 * @param string $segment   Text segment (no <a> blocks).
	 * @param array  $rule      Rule definition.
	 * @param int    $max_total Max links for this rule across the whole content.
	 * @param int    $used      Already-used count (by ref, incremented).
	 * @return string
	 */
	private function replace_keywords( $segment, $rule, $max_total, &$used ) {
		$keywords = self::keyword_list( $rule['keywords'] );
		$target   = $rule['target_url'];

		foreach ( $keywords as $keyword ) {
			$allowed = $max_total - $used;
			if ( $allowed <= 0 ) {
				break;
			}

			$pattern = $this->keyword_pattern( $keyword, ! empty( $rule['case_sensitive'] ) );
			if ( '' === $pattern ) {
				continue;
			}

			$segment = preg_replace_callback(
				$pattern,
				static function ( $m ) use ( $target, &$used, $max_total ) {
					// Safety: never exceed the rule budget.
					if ( $used >= $max_total ) {
						return $m[0];
					}

					$used++;

					return sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( $target ),
						$m[0]
					);
				},
				$segment,
				$allowed
			);

			if ( ! is_string( $segment ) ) {
				return '';
			}
		}

		return $segment;
	}

	/**
	 * Build a word-boundary regex for a keyword.
	 *
	 * Persian keywords use lookarounds over Arabic letters + ZWNJ (U+200C)
	 * so «خواهم» never matches inside «می‌خواهم». Latin keywords use \b.
	 *
	 * @param string $keyword       Keyword.
	 * @param bool   $case_sensitive Whether matching is case-sensitive.
	 * @return string Empty string on failure.
	 */
	private function keyword_pattern( $keyword, $case_sensitive ) {
		$quoted = preg_quote( $keyword, '#' );
		if ( '' === $quoted ) {
			return '';
		}

		$has_persian = (bool) preg_match( '/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $keyword );

		if ( $has_persian ) {
			// Arabic letters + ZWNJ act as "part of a word" for boundary checks.
			$bound = '[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\x{200C}]';
			$pattern = '#(?<!' . $bound . ')' . $quoted . '(?!' . $bound . ')#u';
		} else {
			$pattern = '#(?<![\p{L}\p{N}_])' . $quoted . '(?![\p{L}\p{N}_])#u';
		}

		if ( ! $case_sensitive ) {
			$pattern .= 'i';
		}

		// Reject broken patterns instead of triggering a PHP warning.
		if ( false === @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return '';
		}

		return $pattern;
	}
}
