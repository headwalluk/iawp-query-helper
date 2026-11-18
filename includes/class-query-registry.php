<?php
/**
 * The library of named queries.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Holds every named query, keyed by name.
 *
 * @since 0.1.0
 */
class Query_Registry {

	/**
	 * Queries by name.
	 *
	 * @since 0.1.0
	 * @var array<string, Query>
	 */
	private array $queries = array();

	/**
	 * Build the library.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment  $environment IAWP environment.
	 * @param Query_Runner $runner      SQL runner.
	 */
	public function __construct( Environment $environment, Query_Runner $runner ) {
		$queries = array(
			new Site_Summary_Query( $environment, $runner ),
			new Top_Posts_Query( $environment, $runner ),
			new Traffic_Over_Time_Query( $environment, $runner ),
		);

		foreach ( $queries as $query ) {
			$this->queries[ $query->get_name() ] = $query;
		}
	}

	/**
	 * Every query, keyed by name.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, Query>
	 */
	public function get_all(): array {
		return $this->queries;
	}

	/**
	 * Queries that can be offered: all of them with Pro, only the free ones without.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $is_pro Whether IAWP Pro is licensed.
	 * @return array<string, Query>
	 */
	public function get_offered( bool $is_pro ): array {
		return array_filter(
			$this->queries,
			fn( Query $query ): bool => $is_pro || ! $query->is_pro_only()
		);
	}

	/**
	 * A query by name, or null.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Query name.
	 * @return Query|null
	 */
	public function get( string $name ): ?Query {
		return $this->queries[ $name ] ?? null;
	}
}
