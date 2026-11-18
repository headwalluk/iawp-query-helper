<?php
/**
 * Query: form submissions per form for a range. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.5.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * The form figures at the top of the IAWP dashboard: submissions and conversion rate, per form and in total.
 *
 * @since 0.5.0
 */
class Form_Submissions_Query extends Query {

	/**
	 * Metrics reported in `previous` and `change_percent`.
	 *
	 * @since 0.5.0
	 * @var string[]
	 */
	private const METRICS = array( 'submissions', 'conversion_rate_percent' );

	/**
	 * Query name.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form-submissions';
	}

	/**
	 * Label.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Form submissions', 'ability label; contact form entries', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Form submissions tracked by Independent Analytics Pro for a date range, one row per form (contact forms, sign-ups and similar from the supported form plugins), with submissions and conversion rate (submissions as a percentage of all site visitors). The totals object holds the site-wide figures and the visitor count. Matches the form figures on the dashboard. Every tracked form is listed, including those with no submissions in the range. Use compare=previous_year with period=year_to_date for year-on-year.', 'iawp-query-helper' );
	}

	/**
	 * Only with a licensed copy of IAWP Pro.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_pro_only(): bool {
		return true;
	}

	/**
	 * Row schema.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		$metric_properties = $this->get_metric_properties();

		return array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'form_id' => array(
						'type'        => 'integer',
						'description' => __( 'Independent Analytics form ID, for the form_id filter of the other form queries.', 'iawp-query-helper' ),
					),
					'title'   => array( 'type' => 'string' ),
				),
				$metric_properties,
				$this->get_comparison_properties( $metric_properties )
			),
		);
	}

	/**
	 * Output schema, with the site-wide totals.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_output_schema(): array {
		$schema            = parent::get_output_schema();
		$metric_properties = array_merge( $this->get_integer_properties( array( 'visitors' ) ), $this->get_metric_properties() );

		$schema['properties']['totals'] = array(
			'type'        => 'object',
			'description' => __( 'Site-wide submissions, conversion rate and visitors.', 'iawp-query-helper' ),
			'properties'  => array_merge( $metric_properties, $this->get_comparison_properties( $metric_properties ) ),
		);

		return $schema;
	}

	/**
	 * Count submissions for the range and any comparison range.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$forms      = $this->get_forms();
		$current    = is_wp_error( $forms ) ? $forms : $this->get_counts( $context->get_range() );
		$comparison = $context->get_comparison();
		$previous   = null;

		if ( ! is_wp_error( $current ) && $comparison instanceof Date_Range ) {
			$previous = $this->get_counts( $comparison );
		}

		if ( is_wp_error( $current ) ) {
			$result = $current;
		} elseif ( is_wp_error( $previous ) ) {
			$result = $previous;
		} else {
			$result = array(
				'rows'   => $this->shape_rows( $forms, $current, $previous ),
				'totals' => $this->shape_totals( $current, $previous ),
			);
		}

		return $result;
	}

	/**
	 * Schema of the submission metrics.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_metric_properties(): array {
		return array(
			'submissions'             => array( 'type' => 'integer' ),
			'conversion_rate_percent' => array(
				'type'        => 'number',
				'description' => __( 'Submissions as a percentage of site visitors.', 'iawp-query-helper' ),
			),
		);
	}

	/**
	 * Every tracked form, keyed by form ID.
	 *
	 * @since 0.5.0
	 *
	 * @return array<int, string>|\WP_Error Titles by form ID.
	 */
	private function get_forms(): array|\WP_Error {
		$forms_table = $this->environment->get_table( IAWP_TABLE_FORMS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix; no values.
		$rows   = $this->runner->get_rows( "SELECT form_id, cached_form_title AS title FROM {$forms_table} ORDER BY form_id ASC" );
		$result = $rows;

		if ( ! is_wp_error( $rows ) ) {
			$result = array();

			foreach ( $rows as $row ) {
				$result[ (int) $row['form_id'] ] = (string) $row['title'];
			}
		}

		return $result;
	}

	/**
	 * Visitors and submissions per form for one range.
	 *
	 * @since 0.5.0
	 *
	 * @param Date_Range $range Range.
	 * @return array{visitors: int, forms: array<int, int>}|\WP_Error
	 */
	private function get_counts( Date_Range $range ): array|\WP_Error {
		global $wpdb;

		$submissions_table = $this->environment->get_table( IAWP_TABLE_FORM_SUBMISSIONS );
		$views_table       = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table    = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$visitors          = $this->get_site_visitors( $range );
		$range_values      = array( $range->get_start_utc(), $range->get_end_utc() );

		// A submission counts when it was made in the range, from an in-range view of a session that started in the range.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix.
		$rows = is_wp_error( $visitors ) ? $visitors : $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT form_submissions.form_id, COUNT(*) AS submissions
				FROM {$submissions_table} AS form_submissions
				INNER JOIN {$views_table} AS views ON views.id = form_submissions.view_id
				INNER JOIN {$sessions_table} AS sessions ON sessions.session_id = views.session_id
				WHERE form_submissions.created_at BETWEEN %s AND %s
					AND views.viewed_at BETWEEN %s AND %s
					AND sessions.created_at BETWEEN %s AND %s
				GROUP BY form_submissions.form_id",
				array_merge( $range_values, $range_values, $range_values )
			)
		);
		// phpcs:enable

		$result = $rows;

		if ( ! is_wp_error( $rows ) ) {
			$forms = array();

			foreach ( $rows as $row ) {
				$forms[ (int) $row['form_id'] ] = (int) $row['submissions'];
			}

			$result = array(
				'visitors' => $visitors,
				'forms'    => $forms,
			);
		}

		return $result;
	}

	/**
	 * Submissions and conversion rate.
	 *
	 * @since 0.5.0
	 *
	 * @param int $submissions Submissions.
	 * @param int $visitors    Site visitors.
	 * @return array{submissions: int, conversion_rate_percent: float}
	 */
	private function get_metrics( int $submissions, int $visitors ): array {
		return array(
			'submissions'             => $submissions,
			'conversion_rate_percent' => percentage( $submissions, $visitors, 2 ),
		);
	}

	/**
	 * One row per form, most submissions first.
	 *
	 * @since 0.5.0
	 *
	 * @param array<int, string> $forms    Titles by form ID.
	 * @param array              $current  Counts for the range.
	 * @param array|null         $previous Counts for the comparison range, or null.
	 * @return array<int, array<string, mixed>>
	 */
	private function shape_rows( array $forms, array $current, ?array $previous ): array {
		$rows = array();

		foreach ( $forms as $form_id => $title ) {
			$metrics = $this->get_metrics( $current['forms'][ $form_id ] ?? 0, $current['visitors'] );
			$prior   = is_array( $previous ) ? $this->get_metrics( $previous['forms'][ $form_id ] ?? 0, $previous['visitors'] ) : null;

			$rows[] = array_merge(
				array(
					'form_id' => $form_id,
					'title'   => $title,
				),
				$metrics,
				array(
					'previous'       => $prior,
					'change_percent' => is_array( $prior ) ? percent_changes( $metrics, $prior, self::METRICS ) : null,
				)
			);
		}

		usort(
			$rows,
			fn( array $first, array $second ): int => array( $second['submissions'], $first['form_id'] ) <=> array( $first['submissions'], $second['form_id'] )
		);

		return $rows;
	}

	/**
	 * Site-wide totals.
	 *
	 * @since 0.5.0
	 *
	 * @param array      $current  Counts for the range.
	 * @param array|null $previous Counts for the comparison range, or null.
	 * @return array<string, mixed>
	 */
	private function shape_totals( array $current, ?array $previous ): array {
		$metrics = array_merge( array( 'visitors' => $current['visitors'] ), $this->get_metrics( (int) array_sum( $current['forms'] ), $current['visitors'] ) );
		$prior   = null;

		if ( is_array( $previous ) ) {
			$prior = array_merge( array( 'visitors' => $previous['visitors'] ), $this->get_metrics( (int) array_sum( $previous['forms'] ), $previous['visitors'] ) );
		}

		return array_merge(
			$metrics,
			array(
				'previous'       => $prior,
				'change_percent' => is_array( $prior ) ? percent_changes( $metrics, $prior, array_merge( array( 'visitors' ), self::METRICS ) ) : null,
			)
		);
	}
}
