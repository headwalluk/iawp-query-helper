<?php
/**
 * Everything a query needs to run once its input has been validated.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Value object: validated parameters, the resolved range and any comparison range.
 *
 * @since 0.1.0
 */
class Query_Context {

	/**
	 * Validated input, defaults filled in.
	 *
	 * @since 0.1.0
	 * @var array<string, mixed>
	 */
	private array $params;

	/**
	 * Range being reported on.
	 *
	 * @since 0.1.0
	 * @var Date_Range
	 */
	private Date_Range $range;

	/**
	 * Range to compare with, if any.
	 *
	 * @since 0.1.0
	 * @var Date_Range|null
	 */
	private ?Date_Range $comparison;

	/**
	 * Create a context.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $params     Validated input.
	 * @param Date_Range           $range      Range being reported on.
	 * @param Date_Range|null      $comparison Range to compare with.
	 */
	public function __construct( array $params, Date_Range $range, ?Date_Range $comparison ) {
		$this->params     = $params;
		$this->range      = $range;
		$this->comparison = $comparison;
	}

	/**
	 * A validated parameter, or $fallback when it is absent.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name     Parameter name.
	 * @param mixed  $fallback Value when absent.
	 * @return mixed
	 */
	public function get_param( string $name, mixed $fallback = null ): mixed {
		return $this->params[ $name ] ?? $fallback;
	}

	/**
	 * Range being reported on.
	 *
	 * @since 0.1.0
	 *
	 * @return Date_Range
	 */
	public function get_range(): Date_Range {
		return $this->range;
	}

	/**
	 * Range to compare with, or null.
	 *
	 * @since 0.1.0
	 *
	 * @return Date_Range|null
	 */
	public function get_comparison(): ?Date_Range {
		return $this->comparison;
	}

	/**
	 * Requested page, from 1.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_page(): int {
		return max( 1, (int) $this->get_param( 'page', 1 ) );
	}

	/**
	 * Requested rows per page.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_per_page(): int {
		return max( 1, min( MAX_PER_PAGE, (int) $this->get_param( 'per_page', DEFAULT_PER_PAGE ) ) );
	}

	/**
	 * Row offset for the requested page.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_offset(): int {
		return ( $this->get_page() - 1 ) * $this->get_per_page();
	}
}
