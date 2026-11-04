<?php
/**
 * Admin asset loader (CSS/JS) — only on plugin pages and the post editor.
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin.css + admin.js with localized strings and nonces.
 */
class Linkyar_Assets {

	/**
	 * Admin page slugs that need the full admin bundle.
	 *
	 * @var string[]
	 */
	private $admin_pages = array(
		'toplevel_page_linkyar',
		'linkyar_page_linkyar_suggestions',
		'linkyar_page_linkyar_autolink',
		'linkyar_page_linkyar_report',
		'linkyar_page_linkyar_settings',
		'linkyar_page_linkyar_wizard',
	);

	/**
	 * Hook the enqueue callbacks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 20 );
	}

	/**
	 * Enqueue the bundle on plugin admin pages only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin( $hook ) {
		if ( ! in_array( $hook, $this->admin_pages, true ) ) {
			return;
		}

		$this->enqueue( true );
	}

	/**
	 * Enqueue the bundle on post screens when the suggestions meta box is shown.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_editor( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$settings = Linkyar_Settings::suggestions();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only ID.
		$post    = $post_id ? get_post( $post_id ) : get_post();

		if ( ! $post ) {
			return;
		}

		$post_types = Linkyar_Suggestions::post_types();
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// Block editor needs wp-data/wp-blocks to insert paragraph blocks.
		$use_block  = function_exists( 'use_block_editor_for_post_type' )
			? use_block_editor_for_post_type( $post->post_type )
			: true;
		$dependencies = $use_block ? array( 'wp-data', 'wp-blocks' ) : array();

		$this->enqueue( false, $dependencies );
	}

	/**
	 * Register + enqueue shared styles/scripts and localize config.
	 *
	 * @param bool   $is_plugin_page Whether we are on a plugin admin page.
	 * @param array  $dependencies   Extra script dependencies (editor).
	 */
	private function enqueue( $is_plugin_page, $dependencies = array() ) {
		$version = PLYAR_VERSION;

		wp_enqueue_style(
			'linkyar-admin',
			PLYAR_URL . 'admin/css/admin.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'linkyar-admin',
			PLYAR_URL . 'admin/js/admin.js',
			$dependencies,
			$version,
			true
		);

		$strings = array(
			'loading'          => __( 'در حال بارگذاری…', 'linkyar' ),
			'searching'        => __( 'در حال جستجو…', 'linkyar' ),
			'no_suggestions'   => __( 'پستی مرتبط پیدا نشد', 'linkyar' ),
			'insert_link'      => __( 'درج لینک', 'linkyar' ),
			'copy_link'        => __( 'کپی لینک', 'linkyar' ),
			'copied'           => __( 'لینک کپی شد', 'linkyar' ),
			'refresh'          => __( 'تازه‌سازی', 'linkyar' ),
			'link_added'       => __( 'لینک اضافه شد', 'linkyar' ),
			'saved'            => __( 'ذخیره شد', 'linkyar' ),
			'deleted'          => __( 'حذف شد', 'linkyar' ),
			'error'            => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'linkyar' ),
			'confirm_delete'   => __( 'قانون حذف شود؟', 'linkyar' ),
			'confirm_delete_r' => __( 'این نتیجه حذف شود؟', 'linkyar' ),
			'confirm'          => __( 'تأیید', 'linkyar' ),
			'cancel'           => __( 'انصراف', 'linkyar' ),
			'start_scan'       => __( 'شروع اسکن', 'linkyar' ),
			'scanning'         => __( 'در حال بررسی %1$s از %2$s…', 'linkyar' ),
			'extracting'       => __( 'در حال جمع‌آوری لینک‌ها…', 'linkyar' ),
			'scan_done'        => __( 'اسکن کامل شد', 'linkyar' ),
			'rescan'           => __( 'اسکن مجدد', 'linkyar' ),
			'delete_result'    => __( 'حذف نتیجه', 'linkyar' ),
			'nothing_scanned'  => __( 'هنوز اسکنی انجام نشده است.', 'linkyar' ),
			'autolink_on'      => __( 'اتولینک فعال شد', 'linkyar' ),
			'autolink_off'     => __( 'اتولینک غیرفعال شد', 'linkyar' ),
			'keyword_required' => __( 'کلیدواژه را وارد کنید', 'linkyar' ),
			'target_required'  => __( 'صفحهٔ هدف را انتخاب کنید', 'linkyar' ),
			'wizard_finished'  => __( 'راه‌اندازی کامل شد', 'linkyar' ),
			'skip'             => __( 'رد شدن', 'linkyar' ),
			'next'             => __( 'بعدی', 'linkyar' ),
			'back'             => __( 'قبلی', 'linkyar' ),
			'finish'           => __( 'پایان', 'linkyar' ),
			'rule_added'       => __( 'قانون اضافه شد', 'linkyar' ),
			'rule_updated'     => __( 'قانون به‌روزرسانی شد', 'linkyar' ),
			'link_copied'      => __( 'کپی شد', 'linkyar' ),
		);

		wp_localize_script(
			'linkyar-admin',
			'LinkyarAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces'  => array(
					'suggestions' => wp_create_nonce( 'linkyar_suggestions' ),
					'rules'       => wp_create_nonce( 'linkyar_rules' ),
					'scan'        => wp_create_nonce( 'linkyar_scan' ),
					'settings'    => wp_create_nonce( 'linkyar_settings' ),
					'wizard'      => wp_create_nonce( 'linkyar_wizard' ),
				),
				'strings' => $strings,
				'isRtl'   => is_rtl(),
				'isPluginPage' => (bool) $is_plugin_page,
			)
		);
	}
}
