<?php
/**
 * Dashboard (plugin home) + first-run onboarding wizard.
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the KPI dashboard and the 3-step onboarding wizard.
 */
class Linkyar_Dashboard {

	/**
	 * Hook AJAX handlers.
	 */
	public function __construct() {
		add_action( 'wp_ajax_linkyar_toggle_autolink', array( $this, 'ajax_toggle_autolink' ) );
		add_action( 'wp_ajax_linkyar_wizard_finish', array( $this, 'ajax_wizard_finish' ) );
	}

	/* ---------------------------------------------------------------------
	 * Dashboard
	 * ------------------------------------------------------------------- */

	/**
	 * KPI + quick actions data (cached 1h, flushed after each scan).
	 *
	 * @return array
	 */
	private function get_stats() {
		$cached = get_transient( 'linkyar_dash_cache' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$types = Linkyar_Settings::included_post_types();
		$types = array_map( 'esc_sql', $types );
		$types = "'" . implode( "','", $types ) . "'";

		$home_like  = '%' . $wpdb->esc_like( '<a href="' . home_url() ) . '%';
		$rel_like   = '%' . $wpdb->esc_like( '<a href="/' ) . '%';
		$ext_check  = '%' . $wpdb->esc_like( '<a href="http' ) . '%';

		// Posts containing at least one same-site (absolute or root-relative) link.
		$with_links = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($types) AND ( post_content LIKE %s OR post_content LIKE %s )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- types whitelisted.
				$home_like,
				$rel_like
			)
		);

		// Weakest pages: published posts without any internal link (newest first).
		$without_links = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($types) AND NOT ( post_content LIKE %s OR post_content LIKE %s ) ORDER BY post_date DESC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- types whitelisted.
				$home_like,
				$rel_like
			)
		);

		unset( $ext_check );

		$report   = Linkyar_Report::get_report();
		$results  = is_array( $report['results'] ) ? $report['results'] : array();
		$broken   = 0;
		$active   = 0;
		$recent   = array();
		foreach ( $results as $row ) {
			if ( isset( $row['status'] ) && 'ok' === $row['status'] ) {
				$active++;
			}
			if ( isset( $row['status'] ) && 'broken' === $row['status'] ) {
				$broken++;
				$recent[] = $row;
			}
		}

		usort(
			$recent,
			static function ( $a, $b ) {
				return (int) $b['checked_at'] <=> (int) $a['checked_at'];
			}
		);
		$recent = array_slice( $recent, 0, 5 );

		$rules_active = 0;
		foreach ( Linkyar_Autolink::get_rules() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$rules_active++;
		}

		$stats = array(
			'posts_with_links' => $with_links,
			'internal_links'   => $active,
			'broken'           => $broken,
			'rules_active'     => $rules_active,
			'weakest'          => array_values( array_map( 'intval', (array) $without_links ) ),
			'recent_broken'    => $recent,
			'last_scan'        => (int) $report['last_scan'],
		);

		set_transient( 'linkyar_dash_cache', $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Render the dashboard screen.
	 */
	public function render_page() {
		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'linkyar' ) );
		}

		$stats         = $this->get_stats();
		$autolink      = Linkyar_Settings::autolink();
		$autolink_on   = ! empty( $autolink['enabled'] );
		$onboarded     = (bool) get_option( 'linkyar_onboarded', false );

		$cta = $onboarded
			? sprintf(
				'<a class="linkyar-btn linkyar-btn--primary" href="%1$s"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> %2$s</a>',
				esc_url( admin_url( 'admin.php?page=linkyar_report&autostart=1' ) ),
				esc_html__( 'شروع اسکن لینک‌ها', 'linkyar' )
			)
			: sprintf(
				'<a class="linkyar-btn linkyar-btn--primary" href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=linkyar_wizard' ) ),
				esc_html__( 'شروع راه‌اندازی', 'linkyar' )
			);

		?>
		<div class="wrap linkyar-wrap linkyar-dashboard"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'linkyar_settings' ) ); ?>">

			<?php
			Linkyar_Plugin::page_header(
				'dashicons-admin-links',
				__( 'داشبورد لینکیار', 'linkyar' ),
				__( 'نمای کلی لینک‌سازی داخلی سایت شما — پیشنهادها، اتولینک و سلامت لینک‌ها.',
					'linkyar' ),
				$cta
			);
			?>

			<div class="linkyar-kpis">
				<div class="linkyar-kpi">
					<span class="linkyar-kpi__icon dashicons dashicons-admin-post" aria-hidden="true"></span>
					<div>
						<span class="linkyar-kpi__value"><?php echo esc_html( Linkyar_Jalali::fa_num( $stats['posts_with_links'] ) ); ?></span>
						<span class="linkyar-kpi__label"><?php esc_html_e( 'پست‌های دارای لینک داخلی', 'linkyar' ); ?></span>
					</div>
				</div>
				<div class="linkyar-kpi">
					<span class="linkyar-kpi__icon dashicons dashicons-admin-links" aria-hidden="true"></span>
					<div>
						<span class="linkyar-kpi__value"><?php echo esc_html( Linkyar_Jalali::fa_num( $stats['internal_links'] ) ); ?></span>
						<span class="linkyar-kpi__label"><?php esc_html_e( 'لینک‌های داخلی سالم', 'linkyar' ); ?></span>
					</div>
				</div>
				<div class="linkyar-kpi linkyar-kpi--danger">
					<span class="linkyar-kpi__icon dashicons dashicons-warning" aria-hidden="true"></span>
					<div>
						<span class="linkyar-kpi__value"><?php echo esc_html( Linkyar_Jalali::fa_num( $stats['broken'] ) ); ?></span>
						<span class="linkyar-kpi__label"><?php esc_html_e( 'لینک‌های خراب', 'linkyar' ); ?></span>
					</div>
				</div>
				<div class="linkyar-kpi">
					<span class="linkyar-kpi__icon dashicons dashicons-randomize" aria-hidden="true"></span>
					<div>
						<span class="linkyar-kpi__value"><?php echo esc_html( Linkyar_Jalali::fa_num( $stats['rules_active'] ) ); ?></span>
						<span class="linkyar-kpi__label"><?php esc_html_e( 'قوانین اتولینک فعال', 'linkyar' ); ?></span>
					</div>
				</div>
			</div>

			<div class="linkyar-grid-2">
				<div class="linkyar-card">
					<h2 class="linkyar-card__title">
						<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
						<?php esc_html_e( 'پست‌های بدون لینک داخلی', 'linkyar' ); ?>
					</h2>
					<?php if ( empty( $stats['weakest'] ) ) : ?>
						<p class="linkyar-muted"><?php esc_html_e( 'همهٔ پست‌ها حداقل یک لینک داخلی دارند. عالیه!', 'linkyar' ); ?></p>
					<?php else : ?>
						<ul class="linkyar-mini-list">
							<?php foreach ( $stats['weakest'] as $pid ) : ?>
								<li>
									<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
										<?php echo esc_html( get_the_title( $pid ) ? wp_trim_words( get_the_title( $pid ), 8 ) : '#' . $pid ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
						<a class="linkyar-link" href="<?php echo esc_url( admin_url( 'admin.php?page=linkyar_suggestions' ) ); ?>">
							<?php esc_html_e( 'مدیریت پیشنهاد لینک →', 'linkyar' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<div class="linkyar-card">
					<h2 class="linkyar-card__title">
						<span class="dashicons dashicons-universal-access-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'لینک‌های خراب اخیر', 'linkyar' ); ?>
					</h2>
					<?php if ( empty( $stats['recent_broken'] ) ) : ?>
						<p class="linkyar-muted"><?php esc_html_e( 'لینک خرابی پیدا نشد — یا هنوز اسکنی انجام نشده است.', 'linkyar' ); ?></p>
					<?php else : ?>
						<ul class="linkyar-mini-list linkyar-mini-list--url">
							<?php foreach ( $stats['recent_broken'] as $row ) : ?>
								<li dir="ltr">
									<a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $row['url'] ); ?>
									</a>
									<span class="linkyar-pill linkyar-pill--danger"><?php echo esc_html( $row['http_code'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<a class="linkyar-link" href="<?php echo esc_url( admin_url( 'admin.php?page=linkyar_report' ) ); ?>">
							<?php esc_html_e( 'مشاهدهٔ گزارش کامل →', 'linkyar' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="linkyar-card linkyar-status">
				<h2 class="linkyar-card__title">
					<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
					<?php esc_html_e( 'وضعیت سامانه', 'linkyar' ); ?>
				</h2>
				<div class="linkyar-status__grid">
					<div class="linkyar-status__item">
						<span class="linkyar-status__label"><?php esc_html_e( 'آخرین اسکن', 'linkyar' ); ?></span>
						<span class="linkyar-status__value">
							<?php echo $stats['last_scan'] ? esc_html( Linkyar_Jalali::since( $stats['last_scan'] ) ) : esc_html__( 'هرگز', 'linkyar' ); ?>
						</span>
					</div>
					<div class="linkyar-status__item">
						<span class="linkyar-status__label"><?php esc_html_e( 'وضعیت اتولینک', 'linkyar' ); ?></span>
						<label class="linkyar-toggle linkyar-toggle--sm">
							<input type="checkbox" id="linkyar-autolink-toggle" <?php checked( $autolink_on ); ?> />
							<span class="linkyar-toggle__track" aria-hidden="true"></span>
							<span class="linkyar-toggle__label" id="linkyar-autolink-state">
								<?php echo $autolink_on ? esc_html__( 'فعال', 'linkyar' ) : esc_html__( 'غیرفعال', 'linkyar' ); ?>
							</span>
						</label>
					</div>
					<div class="linkyar-status__item">
						<span class="linkyar-status__label"><?php esc_html_e( 'نسخه افزونه', 'linkyar' ); ?></span>
						<span class="linkyar-status__value" dir="ltr"><?php echo esc_html( PLYAR_VERSION ); ?></span>
					</div>
				</div>
			</div>

			<div class="linkyar-card">
				<h2 class="linkyar-card__title">
					<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<?php esc_html_e( 'اکشن‌های سریع', 'linkyar' ); ?>
				</h2>
				<div class="linkyar-quick-actions">
					<a class="linkyar-btn linkyar-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=linkyar_report&autostart=1' ) ); ?>">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<?php esc_html_e( 'شروع اسکن لینک‌ها', 'linkyar' ); ?>
					</a>
					<a class="linkyar-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=linkyar_autolink&newrule=1' ) ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'افزودن قانون', 'linkyar' ); ?>
					</a>
					<a class="linkyar-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=linkyar_suggestions' ) ); ?>">
						<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
						<?php esc_html_e( 'ساخت پیشنهاد جدید', 'linkyar' ); ?>
					</a>
					<a class="linkyar-btn linkyar-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=linkyar_wizard' ) ); ?>">
						<span class="dashicons dashicons-carrot" aria-hidden="true"></span>
						<?php esc_html_e( 'راه‌اندازی دوباره', 'linkyar' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: toggle the global autolink switch from the dashboard.
	 */
	public function ajax_toggle_autolink() {
		check_ajax_referer( 'linkyar_settings', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		$settings          = Linkyar_Settings::autolink();
		$settings['enabled'] = empty( $settings['enabled'] ) ? 1 : 0;
		update_option( 'linkyar_autolink_settings', $settings );

		wp_send_json_success(
			array(
				'enabled' => $settings['enabled'],
				'message' => $settings['enabled']
					? __( 'اتولینک فعال شد', 'linkyar' )
					: __( 'اتولینک غیرفعال شد', 'linkyar' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Onboarding wizard
	 * ------------------------------------------------------------------- */

	/**
	 * Render the 3-step wizard: welcome → post types → first rule.
	 */
	public function render_wizard() {
		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'linkyar' ) );
		}

		$general    = Linkyar_Settings::general();
		$excluded   = is_array( $general['excluded_post_types'] ) ? $general['excluded_post_types'] : array();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$categories = get_categories( array( 'hide_empty' => false ) );
		$autolink   = Linkyar_Settings::autolink();

		?>
		<div class="wrap linkyar-wrap linkyar-wizard"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'linkyar_wizard' ) ); ?>">

			<div class="linkyar-wizard__shell">
				<div class="linkyar-wizard__dots" role="tablist" aria-label="<?php esc_attr_e( 'مراحل راه‌اندازی', 'linkyar' ); ?>">
					<span class="linkyar-dot is-active" data-step="1" aria-label="<?php esc_attr_e( 'مرحله ۱', 'linkyar' ); ?>"></span>
					<span class="linkyar-dot" data-step="2" aria-label="<?php esc_attr_e( 'مرحله ۲', 'linkyar' ); ?>"></span>
					<span class="linkyar-dot" data-step="3" aria-label="<?php esc_attr_e( 'مرحله ۳', 'linkyar' ); ?>"></span>
				</div>

				<!-- Step 1: welcome -->
				<section class="linkyar-wizard__step is-active" data-step="1">
					<span class="linkyar-wizard__icon dashicons dashicons-admin-links" aria-hidden="true"></span>
					<h1><?php esc_html_e( 'به لینکیار خوش آمدید', 'linkyar' ); ?></h1>
					<p><?php esc_html_e( 'لینکیار به شما کمک می‌کند لینک‌سازی داخلی سایت‌تان را سریع‌تر، هوشمندتر و سالم‌تر کنید. سه مرحلهٔ کوتاه تا شروع:', 'linkyar' ); ?></p>
					<ol class="linkyar-wizard__list">
						<li><?php esc_html_e( 'نوع‌های محتوایی که می‌خواهید پوشش داده شوند را انتخاب کنید', 'linkyar' ); ?></li>
						<li><?php esc_html_e( 'اولین قانون اتولینک خود را بسازید (اختیاری)', 'linkyar' ); ?></li>
						<li><?php esc_html_e( 'از داشبورد شروع کنید و لینک‌های سایت را اسکن کنید', 'linkyar' ); ?></li>
					</ol>
				</section>

				<!-- Step 2: post types -->
				<section class="linkyar-wizard__step" data-step="2">
					<h1><?php esc_html_e( 'کدام نوع‌ها را شامل شود؟', 'linkyar' ); ?></h1>
					<p><?php esc_html_e( 'نوع‌های محتوایی که تیک می‌خورند در لینکیار استفاده می‌شوند.', 'linkyar' ); ?></p>
					<div class="linkyar-check-grid">
						<?php foreach ( $post_types as $type ) : ?>
							<label class="linkyar-check">
								<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>"
									<?php checked( ! in_array( $type->name, $excluded, true ) ); ?> />
								<span><?php echo esc_html( $type->labels->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</section>

				<!-- Step 3: first autolink rule -->
				<section class="linkyar-wizard__step" data-step="3">
					<h1><?php esc_html_e( 'اولین قانون اتولینک', 'linkyar' ); ?></h1>
					<p><?php esc_html_e( 'مثلاً وقتی کاربر «افزونه وردپرس» را نوشت، خودکار به صفحهٔ معرفی افزونه لینک بخورد. (اختیاری است — می‌توانید رد شوید.)', 'linkyar' ); ?></p>

					<div class="linkyar-field">
						<label for="wizard-keywords"><?php esc_html_e( 'کلیدواژه‌ها (جداشده با ویرگول)', 'linkyar' ); ?></label>
						<input type="text" id="wizard-keywords" class="linkyar-input" placeholder="<?php esc_attr_e( 'مثال: افزونه وردپرس, سئو', 'linkyar' ); ?>" />
					</div>
					<div class="linkyar-field">
						<label for="wizard-target"><?php esc_html_e( 'آدرس صفحهٔ هدف', 'linkyar' ); ?></label>
						<input type="url" id="wizard-target" class="linkyar-input" dir="ltr" placeholder="https://example.com/page/" />
					</div>
					<div class="linkyar-field">
						<label for="wizard-max"><?php esc_html_e( 'حداکثر لینک در هر پست', 'linkyar' ); ?></label>
						<input type="number" id="wizard-max" class="linkyar-input linkyar-input--sm" min="1" max="20"
							value="<?php echo esc_attr( max( 1, (int) $autolink['default_max'] ) ); ?>" />
					</div>
				</section>

				<div class="linkyar-wizard__actions">
					<button type="button" class="linkyar-btn linkyar-btn--ghost" id="wizard-back" hidden>
						<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'قبلی', 'linkyar' ); ?>
					</button>
					<button type="button" class="linkyar-btn linkyar-btn--ghost" id="wizard-skip">
						<?php esc_html_e( 'رد شدن', 'linkyar' ); ?>
					</button>
					<button type="button" class="linkyar-btn linkyar-btn--primary" id="wizard-next">
						<?php esc_html_e( 'بعدی', 'linkyar' ); ?>
						<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					</button>
					<button type="button" class="linkyar-btn linkyar-btn--primary" id="wizard-finish" hidden>
						<?php esc_html_e( 'پایان', 'linkyar' ); ?>
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					</button>
				</div>

				<?php
				// Categories list is reused when the user edits rules later.
				unset( $categories );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: finish (or skip) the wizard — stores selections + onboarded flag.
	 */
	public function ajax_wizard_finish() {
		check_ajax_referer( 'linkyar_wizard', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		$skip = ! empty( $_POST['skip'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.

		if ( ! $skip ) {
			// Save selected post types (excluded = public minus selected).
			$selected = array();
			if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
				foreach ( wp_unslash( $_POST['post_types'] ) as $type ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
					$type = sanitize_key( $type );
					if ( $type && post_type_exists( $type ) ) {
						$selected[] = $type;
					}
				}
			}

			$all_public = array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) ) );
			$general    = Linkyar_Settings::general();
			$general['excluded_post_types'] = array_values( array_diff( $all_public, $selected ) );
			update_option( 'linkyar_general', $general );

			// Optional first rule.
			$keywords = isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
			$target   = isset( $_POST['target'] ) ? esc_url_raw( wp_unslash( $_POST['target'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
			$max      = isset( $_POST['max_links'] ) ? absint( $_POST['max_links'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.

			if ( '' !== trim( $keywords ) && '' !== $target ) {
				$rule = Linkyar_Autolink::build_rule(
					array(
						'keywords'   => $keywords,
						'target_url' => $target,
						'max_links'  => $max,
						'enabled'    => 1,
					)
				);

				if ( ! is_wp_error( $rule ) ) {
					$rules   = Linkyar_Autolink::get_rules();
					$rules[] = $rule;
					Linkyar_Autolink::save_rules( $rules );
				}
			}
		}

		update_option( 'linkyar_onboarded', 1 );

		wp_send_json_success(
			array(
				'redirect' => admin_url( 'admin.php?page=linkyar' ),
				'message'  => __( 'راه‌اندازی کامل شد', 'linkyar' ),
			)
		);
	}
}
