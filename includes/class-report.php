<?php
/**
 * Feature 3 — Broken link report (گزارش لینک‌های شکسته).
 *
 * Two-phase AJAX batch scanner:
 *   Phase «extract»: 20 published posts per request → unique internal URLs.
 *   Phase «check»:   25 URL HEAD checks per request → classification.
 * State lives in the `linkyar_scan_state` option; results in `linkyar_link_report`.
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin report page + batch scanner.
 */
class Linkyar_Report {

	/**
	 * Posts extracted per AJAX request (Pass 1).
	 */
	const EXTRACT_BATCH = 20;

	/**
	 * URLs checked per AJAX request (Pass 2).
	 */
	const CHECK_BATCH = 25;

	/**
	 * Hook AJAX + the admin page.
	 */
	public function __construct() {
		add_action( 'wp_ajax_linkyar_scan_links', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_linkyar_delete_report_item', array( $this, 'ajax_delete_item' ) );
		add_action( 'wp_ajax_linkyar_reset_report', array( $this, 'ajax_reset' ) );
	}

	/* ---------------------------------------------------------------------
	 * Options
	 * ------------------------------------------------------------------- */

	/**
	 * Stored report { results: {url: row}, last_scan: int }.
	 *
	 * @return array
	 */
	public static function get_report() {
		$report = get_option( 'linkyar_link_report', array() );

		return is_array( $report ) ? wp_parse_args( $report, array(
			'results'   => array(),
			'last_scan' => 0,
		) ) : array(
			'results'   => array(),
			'last_scan' => 0,
		);
	}

	/**
	 * Persist the report.
	 *
	 * @param array $report Report data.
	 */
	public static function save_report( $report ) {
		update_option( 'linkyar_link_report', $report, false );
	}

	/**
	 * Current scanner state (or null).
	 *
	 * @return array|null
	 */
	private static function get_state() {
		$state = get_option( 'linkyar_scan_state', null );

		return is_array( $state ) ? $state : null;
	}

	/**
	 * Persist scanner state.
	 *
	 * @param array|null $state State data (null deletes it).
	 */
	private static function save_state( $state ) {
		if ( null === $state ) {
			delete_option( 'linkyar_scan_state' );

			return;
		}

		update_option( 'linkyar_scan_state', $state, false );
	}

	/* ---------------------------------------------------------------------
	 * AJAX: scanner
	 * ------------------------------------------------------------------- */

	/**
	 * Batch scan endpoint. Steps: start | extract | check.
	 */
	public function ajax_scan() {
		check_ajax_referer( 'linkyar_scan', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : 'start'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.

		if ( 'start' === $step ) {
			wp_send_json_success( $this->begin_scan() );
		}

		$state = self::get_state();
		if ( ! $state ) {
			wp_send_json_error( array( 'message' => __( 'اسکنی در جریان نیست.', 'linkyar' ) ), 409 );
		}

		if ( 'extract' === $step ) {
			wp_send_json_success( $this->extract_batch() );
		}

		if ( 'check' === $step ) {
			wp_send_json_success( $this->check_batch() );
		}

		wp_send_json_error( array( 'message' => __( 'درخواست نامعتبر.', 'linkyar' ) ), 400 );
	}

	/**
	 * Initialise a fresh scan: count published posts, reset URL map.
	 *
	 * @return array Progress payload.
	 */
	private function begin_scan() {
		global $wpdb;

		$types   = Linkyar_Settings::included_post_types();
		$types   = array_map( 'esc_sql', $types );
		$types   = "'" . implode( "','", $types ) . "'";

		// phpcsignore: table name is not user input.
		$post_count = (int) $wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($types)" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- whitelisted post types.
		);

		self::save_state(
			array(
				'phase'        => 'extract',
				'post_offset'  => 0,
				'posts_total'  => $post_count,
				'urls'         => array(),
				'check_offset' => 0,
				'total'        => 0,
				'started'      => time(),
			)
		);

		return array(
			'phase'     => 'extract',
			'processed' => 0,
			'total'     => $post_count,
			'label'     => __( 'در حال جمع‌آوری لینک‌ها…', 'linkyar' ),
		);
	}

	/**
	 * Pass 1 — extract internal links from a batch of 20 posts.
	 *
	 * @return array Progress payload.
	 */
	private function extract_batch() {
		global $wpdb;

		$state = self::get_state();
		if ( ! $state || 'extract' !== $state['phase'] ) {
			return array(
				'phase'     => 'extract',
				'processed' => 0,
				'total'     => 0,
				'label'     => __( 'اسکنی در جریان نیست.', 'linkyar' ),
			);
		}

		$types   = Linkyar_Settings::included_post_types();
		$types   = array_map( 'esc_sql', $types );
		$types   = "'" . implode( "','", $types ) . "'";
		$offset  = (int) $state['post_offset'];
		$limit   = self::EXTRACT_BATCH;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($types) ORDER BY ID ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- types whitelisted above.
				$limit,
				$offset
			)
		);

		foreach ( (array) $rows as $row ) {
			foreach ( $this->extract_links( $row->post_content ) as $url ) {
				if ( ! isset( $state['urls'][ $url ] ) ) {
					$state['urls'][ $url ] = array();
				}
				if ( ! in_array( (int) $row->ID, $state['urls'][ $url ], true ) ) {
					$state['urls'][ $url ][] = (int) $row->ID;
				}
			}
		}

		$state['post_offset'] += $limit;
		$processed            = min( $state['post_offset'], (int) $state['posts_total'] );

		if ( $state['post_offset'] >= (int) $state['posts_total'] ) {
			$state['phase']        = 'check';
			$state['check_offset'] = 0;
			$state['total']        = count( $state['urls'] );

			// Seed empty result rows so partial progress is visible.
			$report = self::get_report();
			foreach ( $state['urls'] as $url => $sources ) {
				if ( ! isset( $report['results'][ $url ] ) ) {
					$report['results'][ $url ] = array(
						'url'        => $url,
						'status'     => 'pending',
						'http_code'  => 0,
						'final_url'  => '',
						'sources'    => $sources,
						'checked_at' => 0,
					);
				} else {
					$report['results'][ $url ]['sources'] = $sources;
				}
			}
			self::save_report( $report );
		}

		self::save_state( $state );

		return array(
			'phase'     => $state['phase'],
			'processed' => $processed,
			'total'     => (int) $state['posts_total'],
			'label'     => __( 'در حال جمع‌آوری لینک‌ها…', 'linkyar' ),
		);
	}

	/**
	 * Pass 2 — HEAD-check a batch of up to 25 URLs and classify them.
	 *
	 * @return array Progress payload.
	 */
	private function check_batch() {
		$state = self::get_state();
		if ( ! $state || 'check' !== $state['phase'] ) {
			return array(
				'phase'     => 'check',
				'processed' => 0,
				'total'     => 0,
				'label'     => __( 'اسکنی در جریان نیست.', 'linkyar' ),
			);
		}

		$urls    = array_keys( $state['urls'] );
		$total   = count( $urls );
		$offset  = (int) $state['check_offset'];
		$batch   = array_slice( $urls, $offset, self::CHECK_BATCH );
		$report  = self::get_report();

		foreach ( $batch as $url ) {
			$result = $this->check_url( $url );

			$report['results'][ $url ] = array(
				'url'        => $url,
				'status'     => $result['status'],
				'http_code'  => $result['http_code'],
				'final_url'  => $result['final_url'],
				'sources'    => isset( $state['urls'][ $url ] ) ? $state['urls'][ $url ] : array(),
				'checked_at' => time(),
			);
		}

		$state['check_offset'] += self::CHECK_BATCH;
		$processed             = min( $state['check_offset'], $total );

		$done = $state['check_offset'] >= $total;

		if ( $done ) {
			$report['last_scan'] = time();
			self::save_report( $report );
			self::save_state( null );
			delete_transient( 'linkyar_dash_cache' );

			return array(
				'phase'     => 'done',
				'processed' => $total,
				'total'     => $total,
				'label'     => __( 'اسکن کامل شد', 'linkyar' ),
			);
		}

		self::save_report( $report );
		self::save_state( $state );

		return array(
			'phase'     => 'check',
			'processed' => $processed,
			'total'     => $total,
			'label'     => sprintf(
				/* translators: 1: processed count, 2: total count. */
				__( 'در حال بررسی %1$s از %2$s…', 'linkyar' ),
				Linkyar_Jalali::fa_num( $processed ),
				Linkyar_Jalali::fa_num( $total )
			),
		);
	}

	/**
	 * Extract unique same-host href values from post content.
	 *
	 * @param string $content Post content.
	 * @return string[] Normalized absolute URLs.
	 */
	private function extract_links( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return array();
		}

		$found = array();
		if ( ! preg_match_all( '#<a\b[^>]*?\bhref\s*=\s*([\'"])(.*?)\1#is', $content, $matches ) ) {
			return array();
		}

		$home       = home_url();
		$home_host  = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$home_host  = preg_replace( '#^www\.#', '', $home_host );

		foreach ( $matches[2] as $raw ) {
			$href = html_entity_decode( trim( $raw ), ENT_QUOTES, 'UTF-8' );

			if ( '' === $href || 0 === strpos( $href, '#' ) ) {
				continue;
			}
			if ( preg_match( '#^(javascript|mailto|tel|data):#i', $href ) ) {
				continue;
			}

			$absolute = wp_make_link_absolute( $href );
			$parts    = wp_parse_url( $absolute );

			if ( empty( $parts['host'] ) ) {
				continue;
			}

			$host = strtolower( $parts['host'] );
			$host = preg_replace( '#^www\.#', '', $host );

			// Same-host only (www. prefix tolerated).
			if ( $host !== $home_host ) {
				continue;
			}

			// Drop fragments; keep query strings.
			$clean = strtok( $absolute, '#' );
			if ( is_string( $clean ) ) {
				$found[ $clean ] = true;
			}
		}

		return array_keys( $found );
	}

	/**
	 * Check a single URL with wp_remote_head (GET fallback when HEAD is blocked).
	 *
	 * @param string $url Absolute URL.
	 * @return array { status, http_code, final_url }
	 */
	private function check_url( $url ) {
		$args = array(
			'timeout'    => 5,
			'redirection' => 0, // Stay on the first hop so 3xx can be classified.
			'sslverify'  => false,
			'user-agent' => 'LinkYar/' . PLYAR_VERSION . '; ' . home_url( '/' ),
		);

		$response = wp_remote_head( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// Some servers reject HEAD (405/403/501) — retry with a ranged GET.
		if ( is_wp_error( $response ) || in_array( $code, array( 403, 405, 501 ), true ) ) {
			$get_args          = $args;
			$get_args['limit_response_size'] = 2048;
			$response          = wp_remote_get( $url, $get_args );
			$code              = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'status'    => 'error',
				'http_code' => 0,
				'final_url' => '',
			);
		}

		$headers = wp_remote_retrieve_headers( $response );
		$location = is_object( $headers ) && isset( $headers['location'] ) ? (string) $headers['location'] : '';

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'status'    => 'ok',
				'http_code' => $code,
				'final_url' => '',
			);
		}

		if ( 404 === $code || 410 === $code ) {
			return array(
				'status'    => 'broken',
				'http_code' => $code,
				'final_url' => '',
			);
		}

		if ( $code >= 300 && $code < 400 ) {
			return array(
				'status'    => 'redirect',
				'http_code' => $code,
				'final_url' => $location ? wp_make_link_absolute( $location ) : '',
			);
		}

		return array(
			'status'    => 'error',
			'http_code' => $code,
			'final_url' => '',
		);
	}

	/**
	 * AJAX: delete a single report row.
	 */
	public function ajax_delete_item() {
		check_ajax_referer( 'linkyar_scan', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		$url    = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified.
		$report = self::get_report();

		if ( ! isset( $report['results'][ $url ] ) ) {
			wp_send_json_error( array( 'message' => __( 'نتیجه پیدا نشد.', 'linkyar' ) ), 404 );
		}

		unset( $report['results'][ $url ] );
		self::save_report( $report );

		wp_send_json_success( array( 'message' => __( 'حذف شد', 'linkyar' ) ) );
	}

	/**
	 * AJAX: wipe report + any running state.
	 */
	public function ajax_reset() {
		check_ajax_referer( 'linkyar_scan', 'nonce' );

		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		self::save_report(
			array(
				'results'   => array(),
				'last_scan' => 0,
			)
		);
		self::save_state( null );
		delete_transient( 'linkyar_dash_cache' );

		wp_send_json_success( array( 'message' => __( 'گزارش پاک شد', 'linkyar' ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin page
	 * ------------------------------------------------------------------- */

	/**
	 * Render the «گزارش لینک‌ها» screen.
	 */
	public function render_page() {
		if ( ! current_user_can( Linkyar_Settings::capability() ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'linkyar' ) );
		}

		$report    = self::get_report();
		$results   = is_array( $report['results'] ) ? $report['results'] : array();
		$last_scan = (int) $report['last_scan'];
		$scanning  = (bool) self::get_state();

		// Sort: broken first, then redirect, error, pending, ok.
		$order = array(
			'broken'   => 0,
			'redirect' => 1,
			'error'    => 2,
			'pending'  => 3,
			'ok'       => 4,
		);
		uasort(
			$results,
			static function ( $a, $b ) use ( $order ) {
				$sa = isset( $order[ $a['status'] ] ) ? $order[ $a['status'] ] : 9;
				$sb = isset( $order[ $b['status'] ] ) ? $order[ $b['status'] ] : 9;

				return $sa <=> $sb;
			}
		);

		$counts = array(
			'all'      => count( $results ),
			'broken'   => 0,
			'redirect' => 0,
		);
		foreach ( $results as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ]++;
			}
		}

		$cta = sprintf(
			'<button type="button" class="linkyar-btn linkyar-btn--primary" id="linkyar-scan-start"><span class="dashicons dashicons-search" aria-hidden="true"></span> %s</button>',
			esc_html( $last_scan ? __( 'اسکن مجدد', 'linkyar' ) : __( 'شروع اسکن', 'linkyar' ) )
		);

		?>
		<div class="wrap linkyar-wrap linkyar-report"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'linkyar_scan' ) ); ?>">

			<?php
			Linkyar_Plugin::page_header(
				'dashicons-visibility',
				__( 'گزارش لینک‌های شکسته', 'linkyar' ),
				__( 'لینک‌های داخلی سایت را اسکن کنید و لینک‌های خراب یا ریدایرکت‌شده را پیدا کنید.',
					'linkyar' ),
				$cta
			);
			?>

			<?php if ( $last_scan ) : ?>
				<p class="linkyar-meta-line">
					<?php
					printf(
						/* translators: %s: Jalali date/time of last scan. */
						esc_html__( 'آخرین اسکن: %s', 'linkyar' ),
						'<strong>' . esc_html( Linkyar_Jalali::format( $last_scan, true ) ) . '</strong>'
					);
					?>
				</p>
			<?php endif; ?>

			<div class="linkyar-card linkyar-scan-progress" id="linkyar-scan-progress" <?php hidden( ! $scanning ); ?>>
				<div class="linkyar-progress__head">
					<span class="linkyar-progress__label" id="linkyar-scan-label"><?php esc_html_e( 'در حال بارگذاری…', 'linkyar' ); ?></span>
					<span class="linkyar-progress__pct" id="linkyar-scan-pct">۰٪</span>
				</div>
				<div class="linkyar-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="linkyar-progressbar">
					<div class="linkyar-progress__bar" id="linkyar-scan-bar"></div>
				</div>
			</div>

			<?php if ( empty( $results ) && ! $scanning ) : ?>
				<div class="linkyar-card">
					<?php
					Linkyar_Plugin::empty_state(
						'dashicons-admin-links',
						__( 'هنوز اسکنی انجام نشده است', 'linkyar' ),
						__( 'روی «شروع اسکن» بزنید تا لینک‌های داخلی سایت بررسی شوند.', 'linkyar' ),
						'<button type="button" class="linkyar-btn linkyar-btn--primary" id="linkyar-scan-start-empty">' . esc_html__( 'شروع اسکن', 'linkyar' ) . '</button>'
					);
					?>
				</div>
			<?php else : ?>
				<div class="linkyar-tabs" role="tablist" aria-label="<?php esc_attr_e( 'فیلتر نتایج', 'linkyar' ); ?>">
					<button type="button" class="linkyar-tab is-active" role="tab" aria-selected="true" data-filter="all">
						<?php esc_html_e( 'همه', 'linkyar' ); ?>
						<span class="linkyar-badge"><?php echo esc_html( Linkyar_Jalali::fa_num( $counts['all'] ) ); ?></span>
					</button>
					<button type="button" class="linkyar-tab" role="tab" aria-selected="false" data-filter="broken">
						<?php esc_html_e( 'خراب', 'linkyar' ); ?>
						<span class="linkyar-badge linkyar-badge--danger"><?php echo esc_html( Linkyar_Jalali::fa_num( $counts['broken'] ) ); ?></span>
					</button>
					<button type="button" class="linkyar-tab" role="tab" aria-selected="false" data-filter="redirect">
						<?php esc_html_e( 'ریدایرکت', 'linkyar' ); ?>
						<span class="linkyar-badge linkyar-badge--warning"><?php echo esc_html( Linkyar_Jalali::fa_num( $counts['redirect'] ) ); ?></span>
					</button>
				</div>

				<div class="linkyar-card linkyar-table-card">
					<div class="linkyar-table-scroll">
						<table class="linkyar-table" id="linkyar-report-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'لینک', 'linkyar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'وضعیت', 'linkyar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'کد', 'linkyar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'پست‌های مبدأ', 'linkyar' ); ?></th>
									<th scope="col"><?php esc_html_e( 'آخرین بررسی', 'linkyar' ); ?></th>
									<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'linkyar' ); ?></span></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $results as $row ) : ?>
									<?php
									$status  = isset( $row['status'] ) ? $row['status'] : 'pending';
									$status_labels = array(
										'ok'       => __( 'سالم', 'linkyar' ),
										'broken'   => __( 'خراب', 'linkyar' ),
										'redirect' => __( 'ریدایرکت', 'linkyar' ),
										'error'    => __( 'خطا', 'linkyar' ),
										'pending'  => __( 'در انتظار', 'linkyar' ),
									);
									$pill_class = array(
										'ok'       => 'success',
										'broken'   => 'danger',
										'redirect' => 'warning',
										'error'    => 'danger',
										'pending'  => 'muted',
									);
									?>
									<tr data-status="<?php echo esc_attr( $status ); ?>">
										<td data-label="<?php esc_attr_e( 'لینک', 'linkyar' ); ?>">
											<a class="linkyar-url" href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( $row['url'] ); ?>
											</a>
											<?php if ( ! empty( $row['final_url'] ) ) : ?>
												<div class="linkyar-url__final">
													<?php
													printf(
														/* translators: %s: final URL after redirect. */
														esc_html__( 'مقصد: %s', 'linkyar' ),
														'<span dir="ltr">' . esc_html( $row['final_url'] ) . '</span>'
													);
													?>
												</div>
											<?php endif; ?>
										</td>
										<td data-label="<?php esc_attr_e( 'وضعیت', 'linkyar' ); ?>">
											<span class="linkyar-pill linkyar-pill--<?php echo esc_attr( isset( $pill_class[ $status ] ) ? $pill_class[ $status ] : 'muted' ); ?>">
												<?php echo esc_html( isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status ); ?>
											</span>
										</td>
										<td data-label="<?php esc_attr_e( 'کد', 'linkyar' ); ?>">
											<code class="linkyar-code"><?php echo esc_html( $row['http_code'] ? $row['http_code'] : '—' ); ?></code>
										</td>
										<td data-label="<?php esc_attr_e( 'پست‌های مبدأ', 'linkyar' ); ?>">
											<?php echo $this->render_sources( isset( $row['sources'] ) ? $row['sources'] : array() ); ?>
										</td>
										<td data-label="<?php esc_attr_e( 'آخرین بررسی', 'linkyar' ); ?>">
											<?php echo $row['checked_at'] ? esc_html( Linkyar_Jalali::format( $row['checked_at'] ) ) : '—'; ?>
										</td>
										<td data-label="<?php esc_attr_e( 'عملیات', 'linkyar' ); ?>">
											<button type="button" class="linkyar-icon-btn linkyar-delete-item" data-url="<?php echo esc_attr( $row['url'] ); ?>"
												aria-label="<?php esc_attr_e( 'حذف نتیجه', 'linkyar' ); ?>" title="<?php esc_attr_e( 'حذف نتیجه', 'linkyar' ); ?>">
												<span class="dashicons dashicons-trash" aria-hidden="true"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div class="linkyar-form-actions">
					<button type="button" class="linkyar-btn linkyar-btn--ghost linkyar-btn--danger" id="linkyar-report-reset">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'پاک‌سازی گزارش', 'linkyar' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render source post links (first 3 + «+N»).
	 *
	 * @param int[] $post_ids Source post IDs.
	 * @return string
	 */
	private function render_sources( $post_ids ) {
		$post_ids = array_filter( array_map( 'intval', (array) $post_ids ) );

		if ( empty( $post_ids ) ) {
			return '<span class="linkyar-muted">—</span>';
		}

		$parts = array();
		foreach ( array_slice( $post_ids, 0, 3 ) as $pid ) {
			$title = get_the_title( $pid );
			$edit  = get_edit_post_link( $pid );
			$label = $title ? wp_trim_words( $title, 4 ) : '#' . $pid;

			if ( $edit ) {
				$parts[] = sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $edit ),
					esc_html( $label )
				);
			} else {
				$parts[] = '<span>' . esc_html( $label ) . '</span>';
			}
		}

		$extra = count( $post_ids ) - 3;
		if ( $extra > 0 ) {
			$parts[] = '<span class="linkyar-muted">+' . esc_html( Linkyar_Jalali::fa_num( $extra ) ) . '</span>';
		}

		return implode( '، ', $parts );
	}
}
