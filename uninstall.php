<?php
/**
 * Uninstall handler: removes every option/transient owned by LinkYar.
 *
 * @package LinkYar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$linkyar_options = array(
	'linkyar_version',
	'linkyar_suggestions',
	'linkyar_autolink_settings',
	'linkyar_general',
	'linkyar_autolink_rules',
	'linkyar_link_report',
	'linkyar_scan_state',
	'linkyar_onboarded',
	'linkyar_dash_cache',
);

foreach ( $linkyar_options as $linkyar_option ) {
	delete_option( $linkyar_option );
	delete_site_option( $linkyar_option );
}

delete_transient( 'linkyar_rules_cache' );
