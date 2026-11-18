<?php
/**
 * Runs read-only SQL against IAWP's tables inside the safety rails.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Statement timeout, concurrency slots and error mapping. See docs/how-it-works.md.
 *
 * @since 0.1.0
 */
class Query_Runner {

	/**
	 * Whether the server is MariaDB, once detected.
	 *
	 * @since 0.1.0
	 * @var bool|null
	 */
	private ?bool $is_mariadb = null;

	/**
	 * Statement timeout in seconds, after filtering.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_timeout_seconds(): int {
		return get_filtered_int( FILTER_STATEMENT_TIMEOUT, DEFAULT_STATEMENT_TIMEOUT_SECONDS, MIN_STATEMENT_TIMEOUT_SECONDS, MAX_STATEMENT_TIMEOUT_SECONDS );
	}

	/**
	 * Number of queries allowed to run at once across the site, after filtering.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_slot_count(): int {
		return get_filtered_int( FILTER_CONCURRENCY_SLOTS, DEFAULT_CONCURRENCY_SLOTS, MIN_CONCURRENCY_SLOTS, MAX_CONCURRENCY_SLOTS );
	}

	/**
	 * Run $work while holding a concurrency slot, or return a busy error if none is free.
	 *
	 * @since 0.1.0
	 *
	 * @param callable $work Callback returning the query result.
	 * @return mixed The callback's result, or a WP_Error.
	 */
	public function with_slot( callable $work ): mixed {
		$lock_name = $this->acquire_slot();

		if ( is_null( $lock_name ) ) {
			$result = new \WP_Error(
				ERROR_BUSY,
				__( 'Other analytics queries are already running on this site. Retry shortly.', 'iawp-query-helper' ),
				array(
					'status'      => 429,
					'retry_after' => BUSY_RETRY_AFTER_SECONDS,
				)
			);
		} else {
			try {
				$result = $work();
			} finally {
				$this->release_slot( $lock_name );
			}
		}

		return $result;
	}

	/**
	 * Run one prepared SELECT with the statement timeout applied.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prepared_sql A SELECT already passed through $wpdb->prepare().
	 * @return array<int, array<string, mixed>>|\WP_Error Rows as associative arrays.
	 */
	public function get_rows( string $prepared_sql ): array|\WP_Error {
		global $wpdb;

		$were_errors_suppressed = $wpdb->suppress_errors( true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Reading a third-party table; the SQL was prepared by the caller and only the timeout wrapper is added.
		$rows       = $wpdb->get_results( $this->add_timeout( $prepared_sql ), ARRAY_A );
		$last_error = (string) $wpdb->last_error;

		$wpdb->suppress_errors( $were_errors_suppressed );

		if ( '' !== $last_error ) {
			$result = $this->map_database_error( $last_error );
		} else {
			$result = is_array( $rows ) ? $rows : array();
		}

		return $result;
	}

	/**
	 * Wrap a SELECT in the server's per-statement time limit.
	 *
	 * @since 0.1.0
	 *
	 * @param string $sql A SELECT statement.
	 * @return string
	 */
	public function add_timeout( string $sql ): string {
		$timeout_seconds = $this->get_timeout_seconds();

		if ( $this->is_mariadb() ) {
			$wrapped = sprintf( 'SET STATEMENT max_statement_time=%d FOR %s', $timeout_seconds, ltrim( $sql ) );
		} else {
			// MySQL 5.7.8+ honours the optimiser hint; older servers read it as a comment.
			$wrapped = (string) preg_replace( '/^\s*SELECT\b/i', sprintf( 'SELECT /*+ MAX_EXECUTION_TIME(%d) */', $timeout_seconds * 1000 ), $sql, 1 );
		}

		return $wrapped;
	}

	/**
	 * Whether the database server is MariaDB.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_mariadb(): bool {
		global $wpdb;

		if ( is_null( $this->is_mariadb ) ) {
			$this->is_mariadb = false !== stripos( (string) $wpdb->db_server_info(), 'mariadb' );
		}

		return $this->is_mariadb;
	}

	/**
	 * Name of the lock for one slot, unique to this site's database and table prefix.
	 *
	 * @since 0.1.0
	 *
	 * @param int $slot_number Slot, from 1.
	 * @return string
	 */
	public function get_lock_name( int $slot_number ): string {
		global $wpdb;

		return LOCK_NAME_PREFIX . substr( md5( DB_NAME . '|' . $wpdb->prefix ), 0, 16 ) . '_' . $slot_number;
	}

	/**
	 * Take the first free slot without waiting, or null if all are taken.
	 *
	 * GET_LOCK() is server-wide, so slots are shared by every PHP worker on the site.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null Lock name held.
	 */
	private function acquire_slot(): ?string {
		global $wpdb;

		$held_lock   = null;
		$slot_count  = $this->get_slot_count();
		$slot_number = 1;

		while ( is_null( $held_lock ) && $slot_number <= $slot_count ) {
			$lock_name = $this->get_lock_name( $slot_number );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock; nothing to cache.
			$is_locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) );

			if ( $is_locked ) {
				$held_lock = $lock_name;
			}

			++$slot_number;
		}

		if ( is_null( $held_lock ) ) {
			log( sprintf( 'All %d query slots busy.', $slot_count ) );
		}

		return $held_lock;
	}

	/**
	 * Release a slot taken by acquire_slot().
	 *
	 * @since 0.1.0
	 *
	 * @param string $lock_name Lock name.
	 */
	private function release_slot( string $lock_name ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock; nothing to cache.
		$is_released = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );

		if ( ! $is_released ) {
			// The lock also goes when the connection closes, so this leaks nothing for long.
			log_error( sprintf( 'Could not release query slot %s.', $lock_name ) );
		}
	}

	/**
	 * Turn a database error into a timeout or general database WP_Error, logging it.
	 *
	 * @since 0.1.0
	 *
	 * @param string $database_error Text of $wpdb->last_error.
	 * @return \WP_Error
	 */
	private function map_database_error( string $database_error ): \WP_Error {
		$is_timeout = false;

		foreach ( TIMEOUT_ERROR_MARKERS as $marker ) {
			if ( false !== stripos( $database_error, $marker ) ) {
				$is_timeout = true;
				break;
			}
		}

		if ( $is_timeout ) {
			log_error( sprintf( 'Query stopped at the %d second limit.', $this->get_timeout_seconds() ) );

			$result = new \WP_Error(
				ERROR_TIMEOUT,
				sprintf(
					/* translators: %d: seconds. */
					_n( 'The query took longer than %d second and was stopped. Narrow the date range or add a filter.', 'The query took longer than %d seconds and was stopped. Narrow the date range or add a filter.', $this->get_timeout_seconds(), 'iawp-query-helper' ),
					$this->get_timeout_seconds()
				),
				array( 'status' => 503 )
			);
		} else {
			log_error( sprintf( 'Database error: %s', $database_error ) );

			$result = new \WP_Error(
				ERROR_DATABASE,
				__( 'The analytics query failed. The error has been logged on the server.', 'iawp-query-helper' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}
}
