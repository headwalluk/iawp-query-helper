<?php
/**
 * Turns request parameters into date ranges, and works out comparison ranges.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Resolves `period` presets and `start`/`end` dates, using the same preset definitions as the IAWP dashboard.
 *
 * @since 0.1.0
 */
class Date_Range_Resolver {

	/**
	 * Source of the week start day.
	 *
	 * @since 0.1.0
	 * @var Environment
	 */
	private Environment $environment;

	/**
	 * Fixed "now" for tests; null means the real time.
	 *
	 * @since 0.1.0
	 * @var \DateTimeImmutable|null
	 */
	private ?\DateTimeImmutable $now;

	/**
	 * Create a resolver.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment             $environment IAWP environment.
	 * @param \DateTimeImmutable|null $now         Optional fixed time, for tests.
	 */
	public function __construct( Environment $environment, ?\DateTimeImmutable $now = null ) {
		$this->environment = $environment;
		$this->now         = $now;
	}

	/**
	 * Every period preset this resolver accepts.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public function get_periods(): array {
		return array(
			PERIOD_TODAY,
			PERIOD_YESTERDAY,
			PERIOD_LAST_7_DAYS,
			PERIOD_LAST_30_DAYS,
			PERIOD_LAST_60_DAYS,
			PERIOD_LAST_90_DAYS,
			PERIOD_THIS_WEEK,
			PERIOD_LAST_WEEK,
			PERIOD_THIS_MONTH,
			PERIOD_LAST_MONTH,
			PERIOD_LAST_3_MONTHS,
			PERIOD_LAST_6_MONTHS,
			PERIOD_LAST_12_MONTHS,
			PERIOD_YEAR_TO_DATE,
			PERIOD_LAST_YEAR,
		);
	}

	/**
	 * Resolve `period`, or `start` and `end`, into a range no longer than $max_days.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $params   Validated request parameters.
	 * @param int                  $max_days Longest range allowed.
	 * @return Date_Range|\WP_Error
	 */
	public function resolve( array $params, int $max_days ): Date_Range|\WP_Error {
		$period     = isset( $params['period'] ) ? (string) $params['period'] : '';
		$start_text = isset( $params['start'] ) ? (string) $params['start'] : '';
		$end_text   = isset( $params['end'] ) ? (string) $params['end'] : '';

		if ( '' !== $period && ( '' !== $start_text || '' !== $end_text ) ) {
			$result = $this->invalid( __( 'Use either period, or start and end, not both.', 'iawp-query-helper' ) );
		} elseif ( '' === $start_text && '' !== $end_text ) {
			$result = $this->invalid( __( 'end needs a start date as well.', 'iawp-query-helper' ) );
		} elseif ( '' !== $start_text ) {
			$result = $this->resolve_dates( $start_text, $end_text );
		} else {
			$result = $this->resolve_period( '' !== $period ? $period : DEFAULT_PERIOD );
		}

		if ( $result instanceof Date_Range ) {
			$result = $this->check_length( $result, $max_days );
		}

		return $result;
	}

	/**
	 * The range to compare against, or null for COMPARE_NONE.
	 *
	 * @since 0.1.0
	 *
	 * @param Date_Range $range Current range.
	 * @param string     $mode  One of the COMPARE_* constants.
	 * @return Date_Range|null
	 */
	public function get_comparison( Date_Range $range, string $mode ): ?Date_Range {
		$result = null;

		if ( COMPARE_PREVIOUS_YEAR === $mode ) {
			$result = new Date_Range(
				$this->shift_years( $range->get_start_day(), -1 ),
				$this->shift_years( $range->get_end_day(), -1 )
			);
		} elseif ( COMPARE_PREVIOUS_PERIOD === $mode ) {
			$previous_end   = $range->get_start_day()->modify( '-1 day' );
			$previous_start = $previous_end->modify( sprintf( '-%d days', $range->get_day_count() - 1 ) );
			$result         = new Date_Range( $previous_start, $previous_end );
		} else {
			// COMPARE_NONE: no comparison.
		}

		return $result;
	}

	/**
	 * Today at local midnight.
	 *
	 * @since 0.1.0
	 *
	 * @return \DateTimeImmutable
	 */
	public function get_today(): \DateTimeImmutable {
		$now = $this->now ?? new \DateTimeImmutable( 'now' );

		return $now->setTimezone( wp_timezone() )->setTime( 0, 0, 0 );
	}

	/**
	 * Resolve explicit `Y-m-d` dates; `end` defaults to today.
	 *
	 * @since 0.1.0
	 *
	 * @param string $start_text Start date.
	 * @param string $end_text   End date, or empty for today.
	 * @return Date_Range|\WP_Error
	 */
	private function resolve_dates( string $start_text, string $end_text ): Date_Range|\WP_Error {
		$start_day = $this->parse_date( $start_text );
		$end_day   = '' === $end_text ? $this->get_today() : $this->parse_date( $end_text );

		if ( is_null( $start_day ) ) {
			$result = $this->invalid( __( 'start must be a real date in Y-m-d format.', 'iawp-query-helper' ) );
		} elseif ( is_null( $end_day ) ) {
			$result = $this->invalid( __( 'end must be a real date in Y-m-d format.', 'iawp-query-helper' ) );
		} elseif ( $start_day > $end_day ) {
			$result = $this->invalid( __( 'start must not be after end.', 'iawp-query-helper' ) );
		} else {
			$result = new Date_Range( $start_day, $end_day );
		}

		return $result;
	}

	/**
	 * Resolve a period preset, defined as the IAWP dashboard defines it.
	 *
	 * @since 0.1.0
	 *
	 * @param string $period One of the PERIOD_* constants.
	 * @return Date_Range|\WP_Error
	 */
	private function resolve_period( string $period ): Date_Range|\WP_Error {
		$today  = $this->get_today();
		$result = null;

		switch ( $period ) {
			case PERIOD_TODAY:
				$result = new Date_Range( $today, $today );
				break;
			case PERIOD_YESTERDAY:
				$result = new Date_Range( $today->modify( '-1 day' ), $today->modify( '-1 day' ) );
				break;
			case PERIOD_LAST_7_DAYS:
				$result = new Date_Range( $today->modify( '-6 days' ), $today );
				break;
			case PERIOD_LAST_30_DAYS:
				$result = new Date_Range( $today->modify( '-29 days' ), $today );
				break;
			case PERIOD_LAST_60_DAYS:
				$result = new Date_Range( $today->modify( '-59 days' ), $today );
				break;
			case PERIOD_LAST_90_DAYS:
				$result = new Date_Range( $today->modify( '-89 days' ), $today );
				break;
			case PERIOD_THIS_WEEK:
				$result = $this->get_week( $today, 0 );
				break;
			case PERIOD_LAST_WEEK:
				$result = $this->get_week( $today, -7 );
				break;
			case PERIOD_THIS_MONTH:
				$result = new Date_Range( $today->modify( 'first day of this month' ), $today->modify( 'last day of this month' ) );
				break;
			case PERIOD_LAST_MONTH:
				$result = new Date_Range( $today->modify( 'first day of last month' ), $today->modify( 'last day of last month' ) );
				break;
			case PERIOD_LAST_3_MONTHS:
				$result = new Date_Range( $today->modify( 'first day of last month' )->modify( '-2 months' ), $today->modify( 'last day of last month' ) );
				break;
			case PERIOD_LAST_6_MONTHS:
				$result = new Date_Range( $today->modify( 'first day of last month' )->modify( '-5 months' ), $today->modify( 'last day of last month' ) );
				break;
			case PERIOD_LAST_12_MONTHS:
				$result = new Date_Range( $today->modify( 'first day of last month' )->modify( '-11 months' ), $today->modify( 'last day of last month' ) );
				break;
			case PERIOD_YEAR_TO_DATE:
				$result = new Date_Range( $today->setDate( (int) $today->format( 'Y' ), 1, 1 ), $today );
				break;
			case PERIOD_LAST_YEAR:
				$last_year = (int) $today->format( 'Y' ) - 1;
				$result    = new Date_Range( $today->setDate( $last_year, 1, 1 ), $today->setDate( $last_year, 12, 31 ) );
				break;
			default:
				$result = $this->invalid( __( 'Unknown period.', 'iawp-query-helper' ) );
				break;
		}

		return $result;
	}

	/**
	 * The seven-day week containing today, moved by $offset_days, starting on IAWP's configured first day.
	 *
	 * @since 0.1.0
	 *
	 * @param \DateTimeImmutable $today       Today at local midnight.
	 * @param int                $offset_days Days to move the week by (0 or -7).
	 * @return Date_Range
	 */
	private function get_week( \DateTimeImmutable $today, int $offset_days ): Date_Range {
		$days_since_start = ( (int) $today->format( 'w' ) - $this->environment->get_week_start_day() + 7 ) % 7;
		$week_start       = $today->modify( sprintf( '-%d days', $days_since_start ) )->modify( sprintf( '%+d days', $offset_days ) );

		return new Date_Range( $week_start, $week_start->modify( '+6 days' ) );
	}

	/**
	 * Move a day by whole years; 29 February becomes 28 February in a non-leap year.
	 *
	 * @since 0.1.0
	 *
	 * @param \DateTimeImmutable $day   Day to move.
	 * @param int                $years Years to move by.
	 * @return \DateTimeImmutable
	 */
	private function shift_years( \DateTimeImmutable $day, int $years ): \DateTimeImmutable {
		$year           = (int) $day->format( 'Y' ) + $years;
		$month          = (int) $day->format( 'n' );
		$first_of_month = $day->setDate( $year, $month, 1 );
		$day_of_month   = min( (int) $day->format( 'j' ), (int) $first_of_month->format( 't' ) );

		return $day->setDate( $year, $month, $day_of_month );
	}

	/**
	 * Parse a strict `Y-m-d` date in the site timezone, or null if it isn't a real date.
	 *
	 * @since 0.1.0
	 *
	 * @param string $date_text Date to parse.
	 * @return \DateTimeImmutable|null
	 */
	private function parse_date( string $date_text ): ?\DateTimeImmutable {
		$result = null;
		$parsed = \DateTimeImmutable::createFromFormat( '!' . DATE_FORMAT, $date_text, wp_timezone() );

		// createFromFormat() rolls 2026-02-30 over to 2 March, so check the round trip.
		if ( $parsed instanceof \DateTimeImmutable && $parsed->format( DATE_FORMAT ) === $date_text ) {
			$result = $parsed;
		}

		return $result;
	}

	/**
	 * Refuse a range longer than $max_days.
	 *
	 * @since 0.1.0
	 *
	 * @param Date_Range $range    Range to check.
	 * @param int        $max_days Longest range allowed.
	 * @return Date_Range|\WP_Error
	 */
	private function check_length( Date_Range $range, int $max_days ): Date_Range|\WP_Error {
		$result = $range;

		if ( $range->get_day_count() > $max_days ) {
			$result = new \WP_Error(
				ERROR_RANGE_TOO_BIG,
				sprintf(
					/* translators: %d: maximum days allowed in one range. */
					_n( 'A range can cover at most %d day. Split this one into shorter ranges.', 'A range can cover at most %d days. Split this one into shorter ranges.', $max_days, 'iawp-query-helper' ),
					$max_days
				),
				array( 'status' => 400 )
			);
		}

		return $result;
	}

	/**
	 * An invalid-parameter error.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message What was wrong.
	 * @return \WP_Error
	 */
	private function invalid( string $message ): \WP_Error {
		return new \WP_Error( ERROR_INVALID_PARAM, $message, array( 'status' => 400 ) );
	}
}
