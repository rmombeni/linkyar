<?php
/**
 * Feature 1 — Link suggestions in the editor (پیشنهاد لینک داخلی).
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the editor meta box and serves the suggestions AJAX endpoint.
 */
class Linkyar_Suggestions {

	/**
	 * Persian + English stopwords stripped from the current post title.
	 *
	 * @var string[]
	 */
	private $stopwords = array(
		'و', 'در', 'به', 'از', 'که', 'این', 'آن', 'با', 'برای', 'های', 'را', 'یک',
		'است', 'شد', 'دارد', 'می', 'را', 'هم', 'یا', 'تا', 'کنید', 'شود', 'خود',
		'is', 'the', 'a', 'an', 'of', 'for', 'and', 'or', 'to', 'in', 'on', 'with',
		'how', 'what', 'why', 'your', 'you',
	);

	/**
	 * Hook meta box + AJAX.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'wp_ajax_linkyar_get_suggestions', array( $this, 'ajax_get_suggestions' ) );
	}

	/**
	 * Post types that show the meta box (filterable).
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = array( 'post', 'page' );

		return apply_filters( 'linkyar_suggested_post_types', $types );
	}

	/**
	 * Register the «پیشنهادهای لینک داخلی» meta box.
	 */
	public function add_meta_box() {
		$settings = Linkyar_Settings::suggestions();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$screen_types = self::post_types();
		$excluded     = Linkyar_Settings::general();
		$excluded     = is_array( $excluded['excluded_post_types'] ) ? $excluded['excluded_post_types'] : array();

		foreach ( $screen_types as $type ) {
			if ( in_array( $type, $excluded, true ) ) {
				continue;
			}

			add_meta_box(
				'linkyar-suggestions',
				__( 'پیشنهادهای لینک داخلی', 'linkyar' ),
				array( $this, 'render_meta_box' ),
				$type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Meta box markup — JS fills the list asynchronously.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		$settings = Linkyar_Settings::suggestions();
		?>
		<div id="linkyar-suggestions"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'linkyar_suggestions' ) ); ?>"
			data-count="<?php echo esc_attr( max( 5, min( 15, (int) $settings['count'] ) ) ); ?>">

			<div class="linkyar-sugg__toolbar">
				<input type="search" class="linkyar-sugg__search" placeholder="<?php esc_attr_e( 'فیلتر پیشنهادها…', 'linkyar' ); ?>" aria-label="<?php esc_attr_e( 'فیلتر پیشنهادها', 'linkyar' ); ?>" />
				<button type="button" class="linkyar-sugg__refresh" title="<?php esc_attr_e( 'تازه‌سازی', 'linkyar' ); ?>" aria-label="<?php esc_attr_e( 'تازه‌سازی', 'linkyar' ); ?>">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
				</button>
			</div>

			<div class="linkyar-sugg__list" role="list" aria-live="polite">
				<div class="linkyar-sugg__skeleton" aria-hidden="true"><span></span><span></span><span></span></div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: score sibling posts against the current post title keywords.
	 * Nonce-checked, capability-checked (edit_post), returns JSON.
	 */
	public function ajax_get_suggestions() {
		check_ajax_referer( 'linkyar_suggestions', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'linkyar' ) ), 403 );
		}

		$current = get_post( $post_id );
		if ( ! $current ) {
			wp_send_json_error( array( 'message' => __( 'پست پیدا نشد.', 'linkyar' ) ), 404 );
		}

		$settings = Linkyar_Settings::suggestions();
		$count    = max( 5, min( 15, (int) $settings['count'] ) );

		$keywords = $this->extract_keywords( $current->post_title );
		$results  = $this->score_posts( $current, $keywords, $count );

		wp_send_json_success( array( 'items' => $results ) );
	}

	/**
	 * Strip stopwords from a title and return unique keyword tokens.
	 *
	 * @param string $title Raw post title.
	 * @return string[]
	 */
	private function extract_keywords( $title ) {
		$title = wp_strip_all_tags( (string) $title );
		$words = preg_split( '/[\s،,.:;؟!]+/u', $title, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			$words = array();
		}

		$keywords = array();
		foreach ( $words as $word ) {
			$word  = trim( $word, " \t\n\r\0\x0B«»\"'" );
			$lower = mb_strtolower( $word );

			if ( '' === $word || mb_strlen( $word ) < 2 ) {
				continue;
			}
			if ( in_array( $lower, $this->stopwords, true ) ) {
				continue;
			}

			$keywords[] = $word;
		}

		$keywords = array_values( array_unique( $keywords ) );

		// Fallback: keep the raw title so we still attempt a search.
		if ( empty( $keywords ) && '' !== trim( (string) $title ) ) {
			$keywords = array( trim( $title ) );
		}

		return $keywords;
	}

	/**
	 * Score candidate posts: title match = 3, content match = 1,
	 * +1 bonus when sharing a category with the current post.
	 *
	 * Single WP_Query with 's' (relevance) — progressive keyword reduction
	 * keeps recall high without running one query per keyword.
	 *
	 * @param WP_Post $current  Current post.
	 * @param string[] $keywords Search keywords.
	 * @param int     $count    Max suggestions to return.
	 * @return array[]
	 */
	private function score_posts( $current, $keywords, $count ) {
		$post_types = self::post_types();
		$excluded   = Linkyar_Settings::general();
		$excluded   = is_array( $excluded['excluded_post_types'] ) ? $excluded['excluded_post_types'] : array();
		$post_types = array_values( array_diff( $post_types, $excluded ) );

		if ( empty( $keywords ) || empty( $post_types ) ) {
			return array();
		}

		$current_cats = wp_get_post_categories( $current->ID );

		$candidates = $this->query_candidates( $keywords, $post_types, $current->ID );

		$scored = array();
		foreach ( $candidates as $candidate ) {
			$score   = 0;
			$matched = array();

			$title   = $candidate->post_title;
			$content = $candidate->post_content;

			foreach ( $keywords as $keyword ) {
				$in_title   = false !== mb_stripos( $title, $keyword );
				$in_content = false !== mb_stripos( $content, $keyword );

				if ( $in_title ) {
					$score += 3;
				}
				if ( $in_content ) {
					$score += 1;
				}
				if ( $in_title || $in_content ) {
					$matched[] = $keyword;
				}
			}

			if ( $score > 0 && ! empty( $current_cats ) ) {
				$overlap = array_intersect( $current_cats, wp_get_post_categories( $candidate->ID ) );
				if ( ! empty( $overlap ) ) {
					$score += 1;
				}
			}

			if ( $score <= 0 || empty( $matched ) ) {
				continue;
			}

			$scored[] = array(
				'id'       => $candidate->ID,
				'title'    => get_the_title( $candidate ),
				'url'      => get_permalink( $candidate ),
				'keywords' => array_slice( $matched, 0, 6 ),
				'score'    => $score,
			);
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $scored, 0, $count );
	}

	/**
	 * Run the candidate query: full keyword string first (WP ANDs terms),
	 * then progressively shorter fallbacks for better recall.
	 *
	 * @param string[] $keywords  Keywords.
	 * @param string[] $post_types Allowed post types.
	 * @param int      $exclude_id Post ID to exclude.
	 * @return WP_Post[]
	 */
	private function query_candidates( $keywords, $post_types, $exclude_id ) {
		$attempts = array( implode( ' ', $keywords ) );

		if ( count( $keywords ) > 1 ) {
			$attempts[] = implode( ' ', array_slice( $keywords, 0, max( 1, (int) ceil( count( $keywords ) / 2 ) ) ) ) ;
		}
		if ( count( $keywords ) > 1 ) {
			// Longest single keyword — most distinctive token.
			$sorted = $keywords;
			usort(
				$sorted,
				static function ( $a, $b ) {
					return mb_strlen( $b ) <=> mb_strlen( $a );
				}
			);
			$attempts[] = $sorted[0];
		}

		$attempts = array_values( array_unique( $attempts ) );

		foreach ( $attempts as $search ) {
			$query = new WP_Query(
				array(
					's'              => $search,
					'post_type'      => $post_types,
					'post_status'    => 'publish',
					'posts_per_page' => 25,
					'post__not_in'   => array( $exclude_id ),
					'orderby'        => 'relevance',
					'no_found_rows'  => true,
					'ignore_sticky_posts' => true,
				)
			);

			if ( ! empty( $query->posts ) ) {
				$posts = $query->posts;
				wp_reset_postdata();

				return $posts;
			}

			wp_reset_postdata();
		}

		return array();
	}
}
