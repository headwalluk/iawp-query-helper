<?php
/**
 * Query: individual page views, oldest first, with a keyset cursor.
 *
 * @package IAWP_Query_Helper
 * @since 0.5.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * One row per view in the range. No visitor or session identifiers, so visits can't be joined up.
 *
 * @since 0.5.0
 */
class Raw_Views_Query extends Query {

	/**
	 * Query name.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'raw-views';
	}

	/**
	 * Label.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Raw views', 'ability label; list of individual page views', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return sprintf(
			/* translators: %d: maximum days in one range. */
			__( 'Individual page views from Independent Analytics, oldest first: when each view happened (UTC and site time), the page (title, URL, kind, post ID and type), the visit\'s referrer and referrer type, country code, device type, and whether the view started the visit. Views are those viewed within the range, so totals can differ slightly from the dashboard at the edges of a range. There are no visitor or session identifiers. Ranges are limited to %d days. Prefer the grouped queries for totals and rankings; use this only when individual views are needed. Page through with cursor: pass meta.pagination.next_cursor until has_more is false.', 'iawp-query-helper' ),
			RAW_MAX_RANGE_DAYS
		);
	}

	/**
	 * Paged by cursor.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function uses_cursor(): bool {
		return true;
	}

	/**
	 * No comparison range.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_comparable(): bool {
		return false;
	}

	/**
	 * Shorter ranges than the grouped queries.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function get_max_range_days(): int {
		return RAW_MAX_RANGE_DAYS;
	}

	/**
	 * Row schema.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		$nullable_string = array( 'type' => array( 'string', 'null' ) );

		return array(
			'type'       => 'object',
			'properties' => array(
				'view_id'         => array( 'type' => 'integer' ),
				'viewed_at'       => array(
					'type'        => 'string',
					'description' => __( 'UTC, ISO 8601.', 'iawp-query-helper' ),
				),
				'viewed_at_local' => array(
					'type'        => 'string',
					'description' => __( 'Site time, Y-m-d H:i:s.', 'iawp-query-helper' ),
				),
				'resource_type'   => array( 'type' => 'string' ),
				'title'           => $nullable_string,
				'url'             => $nullable_string,
				'path'            => $nullable_string,
				'post_id'         => array( 'type' => array( 'integer', 'null' ) ),
				'post_type'       => $nullable_string,
				'search_query'    => $nullable_string,
				'not_found_url'   => $nullable_string,
				'referrer'        => $nullable_string,
				'referrer_domain' => $nullable_string,
				'referrer_type'   => $nullable_string,
				'country_code'    => $nullable_string,
				'device_type'     => $nullable_string,
				'is_entrance'     => array(
					'type'        => 'boolean',
					'description' => __( 'Whether this was the first view of its visit.', 'iawp-query-helper' ),
				),
			),
		);
	}

	/**
	 * Fetch one page of views after the cursor.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input and range.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$cursor = $context->get_cursor();

		if ( '' !== (string) $context->get_param( 'cursor', '' ) && is_null( $cursor ) ) {
			$result = new \WP_Error( ERROR_INVALID_PARAM, __( 'That cursor is not valid. Pass meta.pagination.next_cursor unchanged, or omit it for the first page.', 'iawp-query-helper' ), array( 'status' => 400 ) );
		} else {
			$per_page = $context->get_per_page();
			$rows     = $this->get_views( $context->get_range(), $cursor, $per_page + 1 );
			$result   = $rows;

			if ( ! is_wp_error( $rows ) ) {
				$has_more = count( $rows ) > $per_page;
				$rows     = array_slice( $rows, 0, $per_page );
				$last_row = end( $rows );

				$result = array(
					'rows'        => array_map( array( $this, 'shape_row' ), $rows ),
					'has_more'    => $has_more,
					'next_cursor' => ( $has_more && is_array( $last_row ) ) ? $this->make_cursor( $last_row ) : null,
				);
			}
		}

		return $result;
	}

	/**
	 * Views in the range after the cursor, ordered by view time then ID.
	 *
	 * @since 0.5.0
	 *
	 * @param Date_Range                    $range  Range.
	 * @param array{0: string, 1: int}|null $cursor Last row's view time and ID, or null.
	 * @param int                           $limit  Rows to fetch.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function get_views( Date_Range $range, ?array $cursor, int $limit ): array|\WP_Error {
		global $wpdb;

		$views_table          = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table       = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$resources_table      = $this->environment->get_table( IAWP_TABLE_RESOURCES );
		$referrers_table      = $this->environment->get_table( IAWP_TABLE_REFERRERS );
		$referrer_types_table = $this->environment->get_table( IAWP_TABLE_REFERRER_TYPES );
		$countries_table      = $this->environment->get_table( IAWP_TABLE_COUNTRIES );
		$device_types_table   = $this->environment->get_table( IAWP_TABLE_DEVICE_TYPES );
		$lower_bound          = $range->get_start_utc();
		$cursor_sql           = '';
		$cursor_values        = array();

		if ( is_array( $cursor ) ) {
			$lower_bound   = max( $lower_bound, $cursor[0] );
			$cursor_sql    = 'AND ( views.viewed_at > %s OR views.id > %d )';
			$cursor_values = array( $cursor[0], $cursor[1] );
		}

		$values = array_merge( array( $lower_bound, $range->get_end_utc() ), $cursor_values, array( $limit ) );

		// Range on viewed_at with ORDER BY viewed_at, id walks views_viewed_at_index and stops at LIMIT.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; cursor SQL fixed.
		$sql = $wpdb->prepare(
			"SELECT views.id AS view_id,
				views.viewed_at,
				resources.resource AS resource_type,
				resources.cached_title AS title,
				resources.cached_url AS url,
				resources.singular_id AS post_id,
				resources.cached_type AS post_type,
				resources.search_query,
				resources.not_found_url,
				referrers.referrer,
				referrers.domain AS referrer_domain,
				referrer_types.referrer_type,
				countries.country_code,
				device_types.device_type,
				IF(sessions.initial_view_id = views.id, 1, 0) AS is_entrance
			FROM {$views_table} AS views
			INNER JOIN {$resources_table} AS resources ON resources.id = views.resource_id
			LEFT JOIN {$sessions_table} AS sessions ON sessions.session_id = views.session_id
			LEFT JOIN {$referrers_table} AS referrers ON referrers.id = sessions.referrer_id
			LEFT JOIN {$referrer_types_table} AS referrer_types ON referrer_types.id = referrers.referrer_type_id
			LEFT JOIN {$countries_table} AS countries ON countries.country_id = sessions.country_id
			LEFT JOIN {$device_types_table} AS device_types ON device_types.device_type_id = sessions.device_type_id
			WHERE views.viewed_at BETWEEN %s AND %s
				{$cursor_sql}
			ORDER BY views.viewed_at ASC, views.id ASC
			LIMIT %d",
			$values
		);
		// phpcs:enable

		return $this->runner->get_rows( $sql );
	}

	/**
	 * Shape one database row.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function shape_row( array $row ): array {
		$viewed_at = new \DateTimeImmutable( (string) $row['viewed_at'], new \DateTimeZone( 'UTC' ) );
		$url       = isset( $row['url'] ) ? (string) $row['url'] : null;

		return array(
			'view_id'         => (int) $row['view_id'],
			'viewed_at'       => $viewed_at->format( 'Y-m-d\TH:i:s\Z' ),
			'viewed_at_local' => $viewed_at->setTimezone( wp_timezone() )->format( DATETIME_FORMAT ),
			'resource_type'   => (string) ( $row['resource_type'] ?? '' ),
			'title'           => isset( $row['title'] ) ? (string) $row['title'] : null,
			'url'             => $url,
			'path'            => is_null( $url ) ? null : get_url_path( $url ),
			'post_id'         => isset( $row['post_id'] ) ? (int) $row['post_id'] : null,
			'post_type'       => isset( $row['post_type'] ) ? (string) $row['post_type'] : null,
			'search_query'    => isset( $row['search_query'] ) ? (string) $row['search_query'] : null,
			'not_found_url'   => isset( $row['not_found_url'] ) ? (string) $row['not_found_url'] : null,
			'referrer'        => isset( $row['referrer'] ) ? (string) $row['referrer'] : null,
			'referrer_domain' => isset( $row['referrer_domain'] ) && '' !== $row['referrer_domain'] ? (string) $row['referrer_domain'] : null,
			'referrer_type'   => isset( $row['referrer_type'] ) ? (string) $row['referrer_type'] : null,
			'country_code'    => isset( $row['country_code'] ) ? (string) $row['country_code'] : null,
			'device_type'     => isset( $row['device_type'] ) ? (string) $row['device_type'] : null,
			'is_entrance'     => 1 === (int) $row['is_entrance'],
		);
	}

	/**
	 * Cursor for the row after this one: its UTC view time and ID.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, mixed> $row Last database row of the page.
	 * @return string
	 */
	private function make_cursor( array $row ): string {
		$viewed_at = new \DateTimeImmutable( (string) $row['viewed_at'], new \DateTimeZone( 'UTC' ) );

		return $viewed_at->format( 'YmdHis' ) . '-' . (int) $row['view_id'];
	}
}
