<?php
/**
 * Base class for a named query.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * One named, parameterised query. Each becomes one ability and one REST route.
 *
 * Metrics follow the IAWP dashboard's definitions; see docs/how-it-works.md.
 *
 * @since 0.1.0
 */
abstract class Query {

	/**
	 * IAWP environment.
	 *
	 * @since 0.1.0
	 * @var Environment
	 */
	protected Environment $environment;

	/**
	 * Runner for SQL inside the safety rails.
	 *
	 * @since 0.1.0
	 * @var Query_Runner
	 */
	protected Query_Runner $runner;

	/**
	 * Create the query.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment  $environment IAWP environment.
	 * @param Query_Runner $runner      SQL runner.
	 */
	public function __construct( Environment $environment, Query_Runner $runner ) {
		$this->environment = $environment;
		$this->runner      = $runner;
	}

	/**
	 * Name used in the ability name and REST route, such as `site-summary`.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Short human-readable label.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	abstract public function get_label(): string;

	/**
	 * Description written for an AI agent choosing a tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * JSON Schema for one row of output.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function get_row_schema(): array;

	/**
	 * Run the query.
	 *
	 * @since 0.1.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error Keys: rows, and optionally has_more, previous_rows.
	 */
	abstract public function run( Query_Context $context ): array|\WP_Error;

	/**
	 * Input properties specific to this query, as JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array();
	}

	/**
	 * This query's own input properties, for another query that wraps it.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_query_properties(): array {
		return $this->get_specific_properties();
	}

	/**
	 * Whether results are paged with `page` and `per_page`.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_paged(): bool {
		return false;
	}

	/**
	 * Whether the query reports on a date range. When false, `meta.range` is null.
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public function uses_date_range(): bool {
		return true;
	}

	/**
	 * Whether results are paged with an opaque `cursor` instead of `page`.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function uses_cursor(): bool {
		return false;
	}

	/**
	 * Whether the query accepts `compare`.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_comparable(): bool {
		return true;
	}

	/**
	 * Longest range this query accepts, in days, before the site's own limit is applied.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function get_max_range_days(): int {
		return DEFAULT_MAX_RANGE_DAYS;
	}

	/**
	 * Whether the query only runs with a licensed copy of IAWP Pro.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_pro_only(): bool {
		return false;
	}

	/**
	 * Whether the response carries a separate `previous_rows` series when comparing.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	protected function has_previous_rows(): bool {
		return false;
	}

	/**
	 * Full input schema: the shared date, comparison and paging properties plus this query's own.
	 *
	 * @since 0.1.0
	 *
	 * @param string[] $periods Accepted period presets.
	 * @return array<string, mixed>
	 */
	public function get_input_schema( array $periods ): array {
		$properties = array(
			'period'  => array(
				'type'        => 'string',
				'enum'        => $periods,
				'description' => __( 'Preset date range, defined as on the Independent Analytics dashboard, in the site timezone. year_to_date is the dashboard\'s "This Year". Defaults to last_30_days. Do not combine with start/end.', 'iawp-query-helper' ),
			),
			'start'   => array(
				'type'        => 'string',
				'pattern'     => DATE_PATTERN,
				'description' => __( 'First day, Y-m-d, site timezone, inclusive. Use instead of period.', 'iawp-query-helper' ),
			),
			'end'     => array(
				'type'        => 'string',
				'pattern'     => DATE_PATTERN,
				'description' => __( 'Last day, Y-m-d, site timezone, inclusive. Defaults to today when start is given.', 'iawp-query-helper' ),
			),
			'compare' => array(
				'type'        => 'string',
				'enum'        => array( COMPARE_NONE, COMPARE_PREVIOUS_PERIOD, COMPARE_PREVIOUS_YEAR ),
				'default'     => COMPARE_NONE,
				'description' => __( 'Also report a comparison range. previous_year is the same dates one year earlier (use with year_to_date for year-on-year); previous_period is the same number of days immediately before.', 'iawp-query-helper' ),
			),
		);

		if ( ! $this->is_comparable() ) {
			unset( $properties['compare'] );
		}

		if ( $this->uses_cursor() ) {
			$properties['cursor']   = array(
				'type'        => 'string',
				'pattern'     => CURSOR_PATTERN,
				'description' => __( 'Continue from the previous page: pass meta.pagination.next_cursor unchanged. Omit for the first page.', 'iawp-query-helper' ),
			);
			$properties['per_page'] = array(
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => MAX_PER_PAGE,
				'default'     => DEFAULT_PER_PAGE,
				'description' => __( 'Rows per page.', 'iawp-query-helper' ),
			);
		}

		if ( $this->is_paged() ) {
			$properties['page']     = array(
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
				'description' => __( 'Page of results, from 1. Check meta.pagination.has_more.', 'iawp-query-helper' ),
			);
			$properties['per_page'] = array(
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => MAX_PER_PAGE,
				'default'     => DEFAULT_PER_PAGE,
				'description' => __( 'Rows per page.', 'iawp-query-helper' ),
			);
		}

		return array(
			'type'                 => 'object',
			'properties'           => array_merge( $properties, $this->get_specific_properties() ),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * Full output schema: the meta block and the rows.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_output_schema(): array {
		$rows_schema = array(
			'type'  => 'array',
			'items' => $this->get_row_schema(),
		);

		$properties = array(
			'meta' => array(
				'type'        => 'object',
				'description' => __( 'Query name, resolved ranges, pagination and IAWP version details.', 'iawp-query-helper' ),
			),
			'rows' => $rows_schema,
		);

		if ( $this->has_previous_rows() ) {
			$properties['previous_rows'] = array_merge(
				$rows_schema,
				array(
					'type'        => array( 'array', 'null' ),
					'description' => __( 'The same series for the comparison range, or null when not comparing.', 'iawp-query-helper' ),
				)
			);
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => array( 'meta', 'rows' ),
		);
	}

	/**
	 * Schema for an object of named integer metrics.
	 *
	 * @since 0.1.0
	 *
	 * @param string[] $metrics Metric names.
	 * @return array<string, array<string, string>>
	 */
	protected function get_integer_properties( array $metrics ): array {
		$properties = array();

		foreach ( $metrics as $metric ) {
			$properties[ $metric ] = array( 'type' => 'integer' );
		}

		return $properties;
	}

	/**
	 * Schema for the nullable `previous` and `change_percent` objects added to a row when comparing.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, array<string, mixed>> $previous_properties Properties of the previous values.
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_comparison_properties( array $previous_properties ): array {
		$change_properties = array();

		foreach ( array_keys( $previous_properties ) as $metric ) {
			$change_properties[ $metric ] = array( 'type' => array( 'number', 'null' ) );
		}

		return array(
			'previous'       => array(
				'type'        => array( 'object', 'null' ),
				'properties'  => $previous_properties,
				'description' => __( 'Values for the comparison range, or null when not comparing.', 'iawp-query-helper' ),
			),
			'change_percent' => array(
				'type'        => array( 'object', 'null' ),
				'properties'  => $change_properties,
				'description' => __( 'Percentage change from previous to current, to 1 decimal place. A metric is null when its previous value was 0.', 'iawp-query-helper' ),
			),
		);
	}

	/**
	 * Unique visitors for a range, counted as the dashboard's headline figure counts them.
	 *
	 * @since 0.5.0
	 *
	 * @param Date_Range $range Range.
	 * @return int|\WP_Error
	 */
	protected function get_site_visitors( Date_Range $range ): int|\WP_Error {
		global $wpdb;

		$views_table    = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table = $this->environment->get_table( IAWP_TABLE_SESSIONS );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix.
		$rows = $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT sessions.visitor_id) AS visitors
				FROM {$sessions_table} AS sessions
				INNER JOIN {$views_table} AS views ON views.session_id = sessions.session_id
				WHERE sessions.created_at BETWEEN %s AND %s
					AND views.viewed_at BETWEEN %s AND %s",
				$range->get_start_utc(),
				$range->get_end_utc(),
				$range->get_start_utc(),
				$range->get_end_utc()
			)
		);
		// phpcs:enable

		return is_wp_error( $rows ) ? $rows : (int) ( $rows[0]['visitors'] ?? 0 );
	}

	/**
	 * Placeholder list for an IN () clause of integers, such as `%d,%d,%d`.
	 *
	 * @since 0.1.0
	 *
	 * @param int $count Number of placeholders.
	 * @return string
	 */
	protected function get_integer_placeholders( int $count ): string {
		return implode( ',', array_fill( 0, max( 1, $count ), '%d' ) );
	}
}
