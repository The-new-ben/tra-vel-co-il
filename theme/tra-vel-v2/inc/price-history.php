<?php
/**
 * Price memory: the readings the cache used to throw away (theme 1.36.0).
 *
 * Since 1.33.0 this site has fetched real cheapest fares for every supported
 * destination twice a day, and since 1.33.0 every one of those readings has
 * died with its transient. This file is the recorder that stops that loss. It
 * adds no API call of its own: it writes down what inc/prices.php already went
 * and got. A cache answers "what does it cost now". A history answers "when is
 * it cheapest to fly there", and that second answer only exists if somebody
 * starts keeping the first one.
 *
 * Three rules define the lane, and they are narrow on purpose:
 *
 *   1. One currency. Only the default shekel lane is recorded, which is the
 *      lane cron warms, so every row is comparable to every other row. A dollar
 *      observation is a different upstream call on a different schedule and
 *      mixing the two would produce a series nobody could reason about.
 *   2. One row per destination per hour. Cache warming, a cold homepage and a
 *      decision card can all trigger the same fetch path within seconds, and a
 *      table of near duplicates is a table that lies about how often we looked.
 *   3. One reading per row, never a conclusion. This file stores observations
 *      and computes descriptive statistics over them. It renders nothing.
 *
 * THE PUBLICATION RULE, which is the whole reason this release ships a reader
 * and no user interface: nothing derived from this table may ever be shown to a
 * visitor, or fed to anything that shows something to a visitor, until
 * tra_vel_v2_price_history_is_meaningful() returns true for that destination.
 * That gate demands at least 20 observations spanning at least 14 days. With
 * three readings a "cheapest month" is not a finding, it is an accident of when
 * cron happened to run, and publishing it would be inventing a trend out of
 * noise. The data asset compounds quietly first and speaks later. Release
 * 1.36.0 therefore has no template, no shortcode, no REST route and no public
 * output at all. It only starts the clock.
 *
 * Nothing here may affect a page render. Every database call is wrapped, every
 * failure is swallowed, and the recorder returns false instead of raising.
 * A destination whose history write fails renders exactly as it does today.
 *
 * Every statement is built by $wpdb->prepare. No value ever reaches SQL by
 * string interpolation, and the only interpolated fragment is this site's own
 * table name, which is re-validated against a strict identifier pattern before
 * it is allowed into a query.
 *
 * @package TraVelV2
 */

defined( 'ABSPATH' ) || exit;

define( 'TRA_VEL_V2_PRICE_HISTORY_TABLE', 'tra_vel_price_history' );
define( 'TRA_VEL_V2_PRICE_HISTORY_SCHEMA_OPTION', 'tra_vel_v2_price_history_schema' );
define( 'TRA_VEL_V2_PRICE_HISTORY_SCHEMA_VERSION', '1.0.0' );
define( 'TRA_VEL_V2_PRICE_HISTORY_CURRENCY', 'ils' );
define( 'TRA_VEL_V2_PRICE_HISTORY_MIN_INTERVAL', HOUR_IN_SECONDS );
define( 'TRA_VEL_V2_PRICE_HISTORY_RETENTION_DAYS', 730 );
define( 'TRA_VEL_V2_PRICE_HISTORY_PRUNE_HOOK', 'tra_vel_v2_prune_price_history' );
define( 'TRA_VEL_V2_PRICE_HISTORY_PRUNE_LIMIT', 5000 );
define( 'TRA_VEL_V2_PRICE_HISTORY_WINDOW_DAYS', 90 );
define( 'TRA_VEL_V2_PRICE_HISTORY_MIN_OBSERVATIONS', 20 );
define( 'TRA_VEL_V2_PRICE_HISTORY_MIN_SPAN_DAYS', 14 );
define( 'TRA_VEL_V2_PRICE_HISTORY_MAX_STATE_LENGTH', 32 );

/**
 * The database handle, or null when there is nothing to write to.
 *
 * Called from every path in this file so that a missing or unusable $wpdb is a
 * quiet no-op rather than a fatal on a page that only wanted to show a price.
 *
 * @return \wpdb|object|null Database handle, or null.
 */
function tra_vel_v2_price_history_db() {
	global $wpdb;

	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
		return null;
	}
	foreach ( array( 'prepare', 'query', 'get_row', 'get_var', 'get_results' ) as $method ) {
		if ( ! method_exists( $wpdb, $method ) ) {
			return null;
		}
	}

	return $wpdb;
}

/**
 * The fully qualified history table name, or an empty string.
 *
 * The name is derived from this installation's own table prefix and is then
 * re-validated as a bare SQL identifier. A prefix that cannot survive that
 * check disables the recorder rather than being escaped into a query, because
 * a table name is the one fragment prepare cannot parameterise.
 *
 * @return string Table name, or an empty string.
 */
function tra_vel_v2_price_history_table() {
	$db = tra_vel_v2_price_history_db();
	if ( null === $db ) {
		return '';
	}

	$prefix = isset( $db->prefix ) ? (string) $db->prefix : '';
	$table  = $prefix . TRA_VEL_V2_PRICE_HISTORY_TABLE;

	return preg_match( '/^[A-Za-z0-9_]{1,64}$/', $table ) ? $table : '';
}

/**
 * Create or upgrade the history table, once per schema version.
 *
 * Idempotent twice over: dbDelta itself only emits the difference between the
 * declared shape and the live one, and the recorded schema option means the
 * declaration is not even re-read on a request where nothing changed. The
 * option is written only after the table is proven to exist, so the option is
 * evidence rather than an assumption.
 *
 * @return bool Whether the table is installed at the current schema version.
 */
function tra_vel_v2_price_history_install() {
	$db    = tra_vel_v2_price_history_db();
	$table = tra_vel_v2_price_history_table();
	if ( null === $db || '' === $table ) {
		return false;
	}

	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}
	if ( ! function_exists( 'dbDelta' ) ) {
		return false;
	}

	$charset = method_exists( $db, 'get_charset_collate' ) ? (string) $db->get_charset_collate() : '';

	// Deliberately small. Eight columns, no free text, no visitor data, nothing
	// that is not a property of a published fare. Two indexes: one for the
	// recorder's own freshness question and the reader's window scan, one that
	// makes an identical reading physically unable to land twice.
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			map_state varchar(32) NOT NULL,
			currency char(3) NOT NULL,
			price int(10) unsigned NOT NULL,
			departure_date date NOT NULL,
			return_date date NULL DEFAULT NULL,
			airline varchar(4) NOT NULL DEFAULT '',
			transfers tinyint(3) unsigned NOT NULL DEFAULT 0,
			observed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY state_observed (map_state,observed_at),
			UNIQUE KEY dedupe (map_state,currency,departure_date,price,observed_at)
		) ENGINE=InnoDB {$charset};"
	);

	if ( ! tra_vel_v2_price_history_table_exists() ) {
		delete_option( TRA_VEL_V2_PRICE_HISTORY_SCHEMA_OPTION );
		return false;
	}

	update_option( TRA_VEL_V2_PRICE_HISTORY_SCHEMA_OPTION, TRA_VEL_V2_PRICE_HISTORY_SCHEMA_VERSION, true );

	return true;
}

/**
 * Whether the history table is physically present.
 *
 * @return bool
 */
function tra_vel_v2_price_history_table_exists() {
	$db    = tra_vel_v2_price_history_db();
	$table = tra_vel_v2_price_history_table();
	if ( null === $db || '' === $table ) {
		return false;
	}

	$found = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) );

	return is_string( $found ) && $found === $table;
}

/**
 * Install the table the first time this schema version is seen.
 *
 * @return void
 */
function tra_vel_v2_price_history_maybe_install() {
	if ( TRA_VEL_V2_PRICE_HISTORY_SCHEMA_VERSION === get_option( TRA_VEL_V2_PRICE_HISTORY_SCHEMA_OPTION ) ) {
		return;
	}

	tra_vel_v2_price_history_install();
}
add_action( 'after_switch_theme', 'tra_vel_v2_price_history_maybe_install' );
add_action( 'init', 'tra_vel_v2_price_history_maybe_install' );

/**
 * Whether the recorder and the reader may touch the table at all.
 *
 * The schema option is the gate, and it is only ever written once the table has
 * been observed to exist, so this costs one autoloaded option read and no query
 * on the overwhelming majority of requests.
 *
 * @return bool
 */
function tra_vel_v2_price_history_ready() {
	return '' !== tra_vel_v2_price_history_table()
		&& TRA_VEL_V2_PRICE_HISTORY_SCHEMA_VERSION === get_option( TRA_VEL_V2_PRICE_HISTORY_SCHEMA_OPTION );
}

/**
 * Reduce any candidate to a map state this table will accept.
 *
 * @param mixed $map_state Candidate destination map state.
 * @return string Map state, or an empty string.
 */
function tra_vel_v2_price_history_sanitize_state( $map_state ) {
	$map_state = sanitize_key( (string) $map_state );

	return ( '' !== $map_state && strlen( $map_state ) <= TRA_VEL_V2_PRICE_HISTORY_MAX_STATE_LENGTH ) ? $map_state : '';
}

/**
 * Strict calendar date normalization for anything headed into a date column.
 *
 * @param mixed $value Candidate date string.
 * @return string YYYY-MM-DD, or an empty string.
 */
function tra_vel_v2_price_history_sanitize_date( $value ) {
	if ( function_exists( 'tra_vel_v2_price_sanitize_date' ) ) {
		return (string) tra_vel_v2_price_sanitize_date( $value );
	}
	if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $value, $parts ) ) {
		return '';
	}

	return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? substr( $value, 0, 10 ) : '';
}

/**
 * The UTC instant one observation belongs to.
 *
 * The record carries the moment inc/prices.php retrieved it, and that is the
 * truthful instant to store. Anything unparseable falls back to now, which is
 * within seconds of it on every path that reaches here.
 *
 * @param array<string,mixed> $record Normalized price record.
 * @return int Unix timestamp.
 */
function tra_vel_v2_price_history_observed_timestamp( $record ) {
	$fetched = isset( $record['fetched_at'] ) && is_string( $record['fetched_at'] ) ? strtotime( $record['fetched_at'] ) : false;
	$now     = time();
	if ( ! is_int( $fetched ) || $fetched <= 0 ) {
		return $now;
	}

	// A retrieval instant far from now is a clock we cannot trust, and a future
	// row would poison both the hourly guard and the observation span.
	return ( abs( $now - $fetched ) > DAY_IN_SECONDS ) ? $now : $fetched;
}

/**
 * The newest observation instant we hold for one destination, or zero.
 *
 * @param string $map_state Destination map state.
 * @return int Unix timestamp, or zero when there is nothing recorded.
 */
function tra_vel_v2_price_history_last_observed( $map_state ) {
	$db        = tra_vel_v2_price_history_db();
	$table     = tra_vel_v2_price_history_table();
	$map_state = tra_vel_v2_price_history_sanitize_state( $map_state );
	if ( null === $db || '' === $table || '' === $map_state ) {
		return 0;
	}

	$newest = $db->get_var(
		$db->prepare(
			'SELECT MAX(observed_at) FROM `' . $table . '` WHERE map_state = %s AND currency = %s',
			$map_state,
			TRA_VEL_V2_PRICE_HISTORY_CURRENCY
		)
	);
	if ( ! is_string( $newest ) || '' === $newest ) {
		return 0;
	}

	$timestamp = strtotime( $newest . ' UTC' );

	return is_int( $timestamp ) ? $timestamp : 0;
}

/**
 * Record one observation, and never let doing so break anything.
 *
 * This is the only entry point inc/prices.php calls. It cannot raise, it cannot
 * return anything the caller has to handle, and it cannot change what the
 * caller was already going to return.
 *
 * @param string              $map_state Destination map state.
 * @param string              $currency  Lower case currency the record was fetched in.
 * @param array<string,mixed> $record    Normalized price record.
 * @return bool Whether a row was written.
 */
function tra_vel_v2_price_history_record( $map_state, $currency, $record ) {
	try {
		return tra_vel_v2_price_history_write( $map_state, $currency, $record );
	} catch ( Throwable $error ) {
		// Bookkeeping is never allowed to interrupt the render or the cron pass
		// that produced the reading. The price the visitor came for is already
		// cached by the time we get here.
		return false;
	}
}

/**
 * The guarded write itself.
 *
 * Every field is re-derived here rather than trusted, exactly as inc/prices.php
 * re-derives every field it receives from upstream. A record that cannot pass
 * all of it is not recorded, because a row we cannot vouch for is worse than a
 * missing row: it would silently distort every statistic computed later.
 *
 * @param string              $map_state Destination map state.
 * @param string              $currency  Lower case currency the record was fetched in.
 * @param array<string,mixed> $record    Normalized price record.
 * @return bool Whether a row was written.
 */
function tra_vel_v2_price_history_write( $map_state, $currency, $record ) {
	$db    = tra_vel_v2_price_history_db();
	$table = tra_vel_v2_price_history_table();
	if ( null === $db || '' === $table || ! tra_vel_v2_price_history_ready() || ! is_array( $record ) ) {
		return false;
	}

	$map_state = tra_vel_v2_price_history_sanitize_state( $map_state );
	if ( '' === $map_state ) {
		return false;
	}

	// One lane only. The requested currency and the currency the record itself
	// proved must both be the default one, so a dollar reading can never be
	// compared against a shekel reading later on.
	$currency        = strtolower( trim( (string) $currency ) );
	$record_currency = isset( $record['currency'] ) ? strtolower( trim( (string) $record['currency'] ) ) : '';
	if ( TRA_VEL_V2_PRICE_HISTORY_CURRENCY !== $currency || TRA_VEL_V2_PRICE_HISTORY_CURRENCY !== $record_currency ) {
		return false;
	}

	$max_amount = defined( 'TRA_VEL_V2_PRICE_MAX_AMOUNT' ) ? (int) TRA_VEL_V2_PRICE_MAX_AMOUNT : 100000;
	$price      = isset( $record['price'] ) && is_numeric( $record['price'] ) ? (int) $record['price'] : 0;
	if ( $price <= 0 || $price >= $max_amount ) {
		return false;
	}

	$departure_date = tra_vel_v2_price_history_sanitize_date( isset( $record['departure_date'] ) ? $record['departure_date'] : '' );
	if ( '' === $departure_date ) {
		return false;
	}
	$return_date = tra_vel_v2_price_history_sanitize_date( isset( $record['return_date'] ) ? $record['return_date'] : '' );

	$airline = isset( $record['airline'] ) ? strtoupper( trim( (string) $record['airline'] ) ) : '';
	if ( ! preg_match( '/^[A-Z0-9]{2,4}$/', $airline ) ) {
		return false;
	}

	$transfers = isset( $record['transfers'] ) && is_numeric( $record['transfers'] ) ? (int) $record['transfers'] : -1;
	if ( $transfers < 0 || $transfers > 255 ) {
		return false;
	}

	$observed = tra_vel_v2_price_history_observed_timestamp( $record );

	// At most one row per destination per hour. Several surfaces share the one
	// upstream call that produced this record, and a cold cache can drive that
	// call from cron, a homepage and a decision card inside the same minute.
	$last = tra_vel_v2_price_history_last_observed( $map_state );
	if ( $last > 0 && ( $observed - $last ) < TRA_VEL_V2_PRICE_HISTORY_MIN_INTERVAL ) {
		return false;
	}

	// INSERT IGNORE turns the dedupe key into the last line of defence: an
	// identical reading at an identical instant is dropped by the database
	// itself rather than by a race we hope never happens.
	$columns = '(map_state, currency, price, departure_date, return_date, airline, transfers, observed_at)';
	if ( '' === $return_date ) {
		$sql = $db->prepare(
			'INSERT IGNORE INTO `' . $table . '` ' . $columns . ' VALUES (%s, %s, %d, %s, NULL, %s, %d, %s)',
			$map_state,
			TRA_VEL_V2_PRICE_HISTORY_CURRENCY,
			$price,
			$departure_date,
			$airline,
			$transfers,
			gmdate( 'Y-m-d H:i:s', $observed )
		);
	} else {
		$sql = $db->prepare(
			'INSERT IGNORE INTO `' . $table . '` ' . $columns . ' VALUES (%s, %s, %d, %s, %s, %s, %d, %s)',
			$map_state,
			TRA_VEL_V2_PRICE_HISTORY_CURRENCY,
			$price,
			$departure_date,
			$return_date,
			$airline,
			$transfers,
			gmdate( 'Y-m-d H:i:s', $observed )
		);
	}
	if ( ! is_string( $sql ) || '' === $sql ) {
		return false;
	}

	return (int) $db->query( $sql ) > 0;
}

/**
 * Descriptive statistics over what we have actually observed.
 *
 * Nothing in here is a prediction and nothing in here is a recommendation. It
 * counts rows, takes the minimum, maximum and mean of numbers we wrote down,
 * and buckets those readings by the month a traveler would be flying in. The
 * observation window is measured on observed_at, because that is how much
 * history exists; the monthly buckets are measured on departure_date, because
 * that is the question worth asking.
 *
 * @param string $map_state Destination map state.
 * @param int    $days      Observation window in days.
 * @return array<string,mixed>|null Statistics, or null when we hold nothing.
 */
function tra_vel_v2_price_history_stats( $map_state, $days = TRA_VEL_V2_PRICE_HISTORY_WINDOW_DAYS ) {
	$db        = tra_vel_v2_price_history_db();
	$table     = tra_vel_v2_price_history_table();
	$map_state = tra_vel_v2_price_history_sanitize_state( $map_state );
	if ( null === $db || '' === $table || '' === $map_state || ! tra_vel_v2_price_history_ready() ) {
		return null;
	}

	$days   = max( 1, min( TRA_VEL_V2_PRICE_HISTORY_RETENTION_DAYS, (int) $days ) );
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

	$totals = $db->get_row(
		$db->prepare(
			'SELECT COUNT(*) AS observations, MIN(price) AS min_price, MAX(price) AS max_price, AVG(price) AS avg_price,'
				. ' MIN(observed_at) AS first_observed_at, MAX(observed_at) AS last_observed_at'
				. ' FROM `' . $table . '` WHERE map_state = %s AND currency = %s AND observed_at >= %s',
			$map_state,
			TRA_VEL_V2_PRICE_HISTORY_CURRENCY,
			$cutoff
		),
		ARRAY_A
	);
	if ( ! is_array( $totals ) ) {
		return null;
	}

	$observations = isset( $totals['observations'] ) ? (int) $totals['observations'] : 0;
	if ( $observations < 1 ) {
		return null;
	}

	// %% is the documented way to put a literal percent through prepare, so the
	// DATE_FORMAT pattern below arrives at MySQL as %Y-%m and is never mistaken
	// for a placeholder.
	$months = $db->get_results(
		$db->prepare(
			"SELECT DATE_FORMAT(departure_date, '%%Y-%%m') AS month, MIN(price) AS min_price, COUNT(*) AS observations"
				. ' FROM `' . $table . '` WHERE map_state = %s AND currency = %s AND observed_at >= %s AND departure_date IS NOT NULL'
				. ' GROUP BY month ORDER BY month ASC',
			$map_state,
			TRA_VEL_V2_PRICE_HISTORY_CURRENCY,
			$cutoff
		),
		ARRAY_A
	);

	$by_month = array();
	foreach ( is_array( $months ) ? $months : array() as $month ) {
		$label = isset( $month['month'] ) ? (string) $month['month'] : '';
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $label ) ) {
			continue;
		}
		$by_month[] = array(
			'month'        => $label,
			'min'          => (int) ( isset( $month['min_price'] ) ? $month['min_price'] : 0 ),
			'observations' => (int) ( isset( $month['observations'] ) ? $month['observations'] : 0 ),
		);
	}

	return array(
		'observations'      => $observations,
		'min'               => (int) ( isset( $totals['min_price'] ) ? $totals['min_price'] : 0 ),
		'max'               => (int) ( isset( $totals['max_price'] ) ? $totals['max_price'] : 0 ),
		'avg'               => (int) round( (float) ( isset( $totals['avg_price'] ) ? $totals['avg_price'] : 0 ) ),
		'first_observed_at' => isset( $totals['first_observed_at'] ) ? (string) $totals['first_observed_at'] : '',
		'last_observed_at'  => isset( $totals['last_observed_at'] ) ? (string) $totals['last_observed_at'] : '',
		'by_month'          => $by_month,
	);
}

/**
 * Whether this destination's history is allowed to say anything yet.
 *
 * The single gate named in this file's header. Twenty observations spanning a
 * fortnight is the floor at which a minimum stops being the one time we happened
 * to look on a Tuesday. Below it, the honest answer is that we do not know yet,
 * and no surface may imply otherwise.
 *
 * @param string $map_state Destination map state.
 * @return bool
 */
function tra_vel_v2_price_history_is_meaningful( $map_state ) {
	$stats = tra_vel_v2_price_history_stats( $map_state, TRA_VEL_V2_PRICE_HISTORY_RETENTION_DAYS );
	if ( ! is_array( $stats ) || $stats['observations'] < TRA_VEL_V2_PRICE_HISTORY_MIN_OBSERVATIONS ) {
		return false;
	}

	$first = strtotime( $stats['first_observed_at'] . ' UTC' );
	$last  = strtotime( $stats['last_observed_at'] . ' UTC' );
	if ( ! is_int( $first ) || ! is_int( $last ) || $last < $first ) {
		return false;
	}

	return ( $last - $first ) >= ( TRA_VEL_V2_PRICE_HISTORY_MIN_SPAN_DAYS * DAY_IN_SECONDS );
}

/**
 * Drop observations older than the retention horizon.
 *
 * Two years is long enough to compare a season against the same season twice
 * over, which is the shortest window in which "cheapest month" means anything.
 * The delete is bounded so a backlog can never turn one cron tick into a long
 * lock; at eleven destinations and one row an hour the ceiling is orders of
 * magnitude above what a day can produce.
 *
 * @return int Rows deleted.
 */
function tra_vel_v2_price_history_prune() {
	$db    = tra_vel_v2_price_history_db();
	$table = tra_vel_v2_price_history_table();
	if ( null === $db || '' === $table || ! tra_vel_v2_price_history_ready() ) {
		return 0;
	}

	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( TRA_VEL_V2_PRICE_HISTORY_RETENTION_DAYS * DAY_IN_SECONDS ) );

	try {
		$deleted = $db->query(
			$db->prepare(
				'DELETE FROM `' . $table . '` WHERE observed_at < %s ORDER BY id ASC LIMIT %d',
				$cutoff,
				TRA_VEL_V2_PRICE_HISTORY_PRUNE_LIMIT
			)
		);
	} catch ( Throwable $error ) {
		return 0;
	}

	return max( 0, (int) $deleted );
}
add_action( TRA_VEL_V2_PRICE_HISTORY_PRUNE_HOOK, 'tra_vel_v2_price_history_prune' );

/**
 * Keep the daily retention pass scheduled.
 *
 * @return void
 */
function tra_vel_v2_price_history_schedule_prune() {
	if ( ! wp_next_scheduled( TRA_VEL_V2_PRICE_HISTORY_PRUNE_HOOK ) ) {
		wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'daily', TRA_VEL_V2_PRICE_HISTORY_PRUNE_HOOK );
	}
}
add_action( 'after_switch_theme', 'tra_vel_v2_price_history_schedule_prune' );
add_action( 'init', 'tra_vel_v2_price_history_schedule_prune' );

/**
 * Stop pruning for a theme that is no longer serving the site.
 *
 * The recorded observations are deliberately left in place. A theme switch is
 * not a decision to destroy two years of readings, and reactivating the theme
 * has to find the history where it left it.
 *
 * @return void
 */
function tra_vel_v2_price_history_unschedule_prune() {
	$timestamp = wp_next_scheduled( TRA_VEL_V2_PRICE_HISTORY_PRUNE_HOOK );
	while ( $timestamp ) {
		wp_unschedule_event( $timestamp, TRA_VEL_V2_PRICE_HISTORY_PRUNE_HOOK );
		$timestamp = wp_next_scheduled( TRA_VEL_V2_PRICE_HISTORY_PRUNE_HOOK );
	}
}
add_action( 'switch_theme', 'tra_vel_v2_price_history_unschedule_prune' );
