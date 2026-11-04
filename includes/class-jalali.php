<?php
/**
 * Jalali (Shamsi) date helpers with graceful WP-Parsidate fallback.
 *
 * Compatibility target: WP-Parsidate (فارسیساز). When its parsidate() function
 * is available every admin date renders as Jalali (e.g. «۴ آبان ۱۴۰۵»);
 * otherwise we fall back to the localized date_i18n().
 *
 * @package LinkYar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static date/number helpers used across all admin screens.
 */
class Linkyar_Jalali {

	/**
	 * Format a Unix timestamp as a Jalali date (and optional time).
	 *
	 * @param int|null  $timestamp  Unix timestamp; defaults to current time.
	 * @param bool      $with_time Append «- H:i» to the output.
	 * @return string
	 */
	public static function format( $timestamp = null, $with_time = false ) {
		$timestamp = $timestamp ? (int) $timestamp : time();
		$format    = $with_time ? 'j F Y - H:i' : 'j F Y';

		// Preferred path: WP-Parsidate converts Gregorian -> Persian for us.
		if ( function_exists( 'parsidate' ) ) {
			$out = parsidate( $format, $timestamp, 'per' );
			if ( is_string( $out ) && '' !== $out ) {
				return $out;
			}
		}

		// Fallback: standard WP localized (Gregorian) date.
		return date_i18n( $format, $timestamp );
	}

	/**
	 * Convert Latin digits to Persian numerals for human-facing values.
	 * Technical values (URLs, HTTP codes, versions) should NOT pass through here.
	 *
	 * @param int|string $value Number or numeric string.
	 * @return string
	 */
	public static function fa_num( $value ) {
		return strtr( (string) $value, array(
			'0' => '۰',
			'1' => '۱',
			'2' => '۲',
			'3' => '۳',
			'4' => '۴',
			'5' => '۵',
			'6' => '۶',
			'7' => '۷',
			'8' => '۸',
			'9' => '۹',
		) );
	}

	/**
	 * Format a relative "time ago" style label in Persian-friendly form.
	 *
	 * @param int|null $timestamp Unix timestamp.
	 * @return string
	 */
	public static function since( $timestamp = null ) {
		$timestamp = $timestamp ? (int) $timestamp : time();
		$diff      = time() - $timestamp;

		if ( $diff < 0 ) {
			$diff = 0;
		}

		$minutes = (int) floor( $diff / MINUTE_IN_SECONDS );
		$hours   = (int) floor( $diff / HOUR_IN_SECONDS );
		$days    = (int) floor( $diff / DAY_IN_SECONDS );

		if ( $minutes < 1 ) {
			return __( 'همین حالا', 'linkyar' );
		}
		if ( $minutes < 60 ) {
			/* translators: %s: number of minutes. */
			return sprintf( _n( '%s دقیقه پیش', '%s دقیقه پیش', $minutes, 'linkyar' ), self::fa_num( $minutes ) );
		}
		if ( $hours < 24 ) {
			/* translators: %s: number of hours. */
			return sprintf( _n( '%s ساعت پیش', '%s ساعت پیش', $hours, 'linkyar' ), self::fa_num( $hours ) );
		}
		if ( $days < 30 ) {
			/* translators: %s: number of days. */
			return sprintf( _n( '%s روز پیش', '%s روز پیش', $days, 'linkyar' ), self::fa_num( $days ) );
		}

		return self::format( $timestamp );
	}
}
