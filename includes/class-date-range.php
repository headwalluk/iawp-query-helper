<?php
/**
 * An inclusive range of whole days in the site's timezone.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Value object: first and last day, plus the UTC bounds IAWP's datetime columns are compared against.
 *
 * @since 0.1.0
 */
class Date_Range {

	/**
	 * First day, at local midnight.
	 *
	 * @since 0.1.0
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $start_day;

	/**
	 * Last day, at local midnight.
	 *
	 * @since 0.1.0
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $end_day;

	/**
	 * Build a range from two days; any time of day is discarded.
	 *
	 * @since 0.1.0
	 *
	 * @param \DateTimeImmutable $start_day First day, in the site timezone.
	 * @param \DateTimeImmutable $end_day   Last day, in the site timezone.
	 */
	public function __construct( \DateTimeImmutable $start_day, \DateTimeImmutable $end_day ) {
		$this->start_day = $start_day->setTime( 0, 0, 0 );
		$this->end_day   = $end_day->setTime( 0, 0, 0 );
	}

	/**
	 * First day as a DateTimeImmutable at local midnight.
	 *
	 * @since 0.1.0
	 *
	 * @return \DateTimeImmutable
	 */
	public function get_start_day(): \DateTimeImmutable {
		return $this->start_day;
	}

	/**
	 * Last day as a DateTimeImmutable at local midnight.
	 *
	 * @since 0.1.0
	 *
	 * @return \DateTimeImmutable
	 */
	public function get_end_day(): \DateTimeImmutable {
		return $this->end_day;
	}

	/**
	 * First day as `Y-m-d`.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_start_date(): string {
		return $this->start_day->format( DATE_FORMAT );
	}

	/**
	 * Last day as `Y-m-d`.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_end_date(): string {
		return $this->end_day->format( DATE_FORMAT );
	}

	/**
	 * Local midnight at the start of the first day, in UTC.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_start_utc(): string {
		return $this->start_day->setTimezone( new \DateTimeZone( 'UTC' ) )->format( DATETIME_FORMAT );
	}

	/**
	 * Local 23:59:59 on the last day, in UTC. IAWP uses the same inclusive bound.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_end_utc(): string {
		return $this->end_day->setTime( 23, 59, 59 )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( DATETIME_FORMAT );
	}

	/**
	 * Number of days in the range, counting both ends.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_day_count(): int {
		return (int) $this->start_day->diff( $this->end_day )->days + 1;
	}

	/**
	 * The range as it appears in a response's meta block.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string|int>
	 */
	public function to_array(): array {
		return array(
			'start'     => $this->get_start_date(),
			'end'       => $this->get_end_date(),
			'days'      => $this->get_day_count(),
			'timezone'  => $this->start_day->getTimezone()->getName(),
			'start_utc' => $this->get_start_utc(),
			'end_utc'   => $this->get_end_utc(),
		);
	}
}
