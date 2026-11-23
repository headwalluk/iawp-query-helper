<?php
/**
 * Runs a named query from raw input: the one path shared by REST routes and abilities.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Validates input, checks IAWP is ready, resolves ranges, takes a slot and wraps the result.
 *
 * @since 0.1.0
 */
class Executor {

	/**
	 * IAWP environment.
	 *
	 * @since 0.1.0
	 * @var Environment
	 */
	private Environment $environment;

	/**
	 * Query library.
	 *
	 * @since 0.1.0
	 * @var Query_Registry
	 */
	private Query_Registry $registry;

	/**
	 * Date range resolver.
	 *
	 * @since 0.1.0
	 * @var Date_Range_Resolver
	 */
	private Date_Range_Resolver $resolver;

	/**
	 * SQL runner.
	 *
	 * @since 0.1.0
	 * @var Query_Runner
	 */
	private Query_Runner $runner;

	/**
	 * Create the executor.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment         $environment IAWP environment.
	 * @param Query_Registry      $registry    Query library.
	 * @param Date_Range_Resolver $resolver    Date range resolver.
	 * @param Query_Runner        $runner      SQL runner.
	 */
	public function __construct( Environment $environment, Query_Registry $registry, Date_Range_Resolver $resolver, Query_Runner $runner ) {
		$this->environment = $environment;
		$this->registry    = $registry;
		$this->resolver    = $resolver;
		$this->runner      = $runner;
	}

	/**
	 * Run a named query and return the response envelope.
	 *
	 * Permission is checked by the caller (REST permission_callback or ability permission_callback).
	 *
	 * @since 0.1.0
	 *
	 * @param string $query_name Query name.
	 * @param mixed  $input      Raw input: an array of parameters, or null.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( string $query_name, mixed $input ): array|\WP_Error {
		$query  = $this->registry->get( $query_name );
		$result = $this->check_ready( $query );

		if ( true === $result && $query instanceof Query ) {
			$params = $this->prepare_input( $query, $input );
			$result = is_wp_error( $params ) ? $params : $this->run_query( $query, $params );
		}

		return $result;
	}

	/**
	 * Check the query exists and IAWP can answer it right now.
	 *
	 * @since 0.1.0
	 *
	 * @param Query|null $query Query, or null when the name was unknown.
	 * @return true|\WP_Error
	 */
	private function check_ready( ?Query $query ): true|\WP_Error {
		if ( ! $query instanceof Query ) {
			$result = new \WP_Error( ERROR_UNKNOWN_QUERY, __( 'There is no query with that name.', 'iawp-query-helper' ), array( 'status' => 404 ) );
		} elseif ( ! $this->environment->is_available() ) {
			$result = new \WP_Error( ERROR_IAWP_MISSING, __( 'Independent Analytics is not active on this site, or its tables are missing.', 'iawp-query-helper' ), array( 'status' => 503 ) );
		} elseif ( $this->environment->is_migrating() ) {
			$result = new \WP_Error(
				ERROR_MIGRATING,
				__( 'Independent Analytics is updating its database. Retry in a few minutes.', 'iawp-query-helper' ),
				array(
					'status'      => 503,
					'retry_after' => BUSY_RETRY_AFTER_SECONDS * 6,
				)
			);
		} elseif ( $query->is_pro_only() && ! $this->environment->is_pro() ) {
			$result = new \WP_Error( ERROR_REQUIRES_PRO, __( 'This query needs a licensed copy of Independent Analytics Pro.', 'iawp-query-helper' ), array( 'status' => 403 ) );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Fill defaults, then validate and sanitise the input against the query's schema.
	 *
	 * @since 0.1.0
	 *
	 * @param Query $query Query.
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function prepare_input( Query $query, mixed $input ): array|\WP_Error {
		$schema = $query->get_input_schema( $this->resolver->get_periods() );
		$params = is_array( $input ) ? $input : array();

		// REST and abilities may hand over empty strings for unset query arguments.
		$params = array_filter( $params, fn( mixed $value ): bool => null !== $value && '' !== $value );

		foreach ( $schema['properties'] as $name => $property ) {
			if ( ! array_key_exists( $name, $params ) && array_key_exists( 'default', $property ) ) {
				$params[ $name ] = $property['default'];
			}
		}

		// Validate before sanitising: sanitising drops unknown keys, which should be refused instead.
		$is_valid = rest_validate_value_from_schema( $params, $schema, 'input' );
		$params   = is_wp_error( $is_valid ) ? $params : rest_sanitize_value_from_schema( $params, $schema, 'input' );

		if ( is_wp_error( $is_valid ) ) {
			$result = new \WP_Error( ERROR_INVALID_PARAM, $is_valid->get_error_message(), array( 'status' => 400 ) );
		} elseif ( $query->is_paged() && ( (int) $params['page'] - 1 ) * (int) $params['per_page'] > MAX_GROUPED_ROW_OFFSET ) {
			$result = new \WP_Error(
				ERROR_INVALID_PARAM,
				sprintf(
					/* translators: %d: maximum row offset. */
					_n( 'Paging stops after %d row. Narrow the range or add a filter instead.', 'Paging stops after %d rows. Narrow the range or add a filter instead.', MAX_GROUPED_ROW_OFFSET, 'iawp-query-helper' ),
					MAX_GROUPED_ROW_OFFSET
				),
				array( 'status' => 400 )
			);
		} else {
			$result = $params;
		}

		return $result;
	}

	/**
	 * Resolve ranges, run the query in a slot and wrap the result.
	 *
	 * @since 0.1.0
	 *
	 * @param Query                $query  Query.
	 * @param array<string, mixed> $params Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function run_query( Query $query, array $params ): array|\WP_Error {
		$max_days   = min( $query->get_max_range_days(), get_filtered_int( FILTER_MAX_RANGE_DAYS, DEFAULT_MAX_RANGE_DAYS, MIN_MAX_RANGE_DAYS, DEFAULT_MAX_RANGE_DAYS ) );
		$range      = $this->resolver->resolve( $params, $max_days );
		$comparison = null;

		if ( $range instanceof Date_Range ) {
			$comparison = $this->resolver->get_comparison( $range, (string) ( $params['compare'] ?? COMPARE_NONE ) );
		}

		if ( is_wp_error( $range ) ) {
			$result = $range;
		} else {
			$context = new Query_Context( $params, $range, $comparison );
			$output  = $this->runner->with_slot( fn() => $query->run( $context ) );
			$result  = is_wp_error( $output ) ? $output : $this->build_envelope( $query, $context, $output );
		}

		return $result;
	}

	/**
	 * Wrap query output in the response envelope.
	 *
	 * @since 0.1.0
	 *
	 * @param Query                $query   Query.
	 * @param Query_Context        $context Context it ran with.
	 * @param array<string, mixed> $output  Output from Query::run().
	 * @return array<string, mixed>
	 */
	private function build_envelope( Query $query, Query_Context $context, array $output ): array {
		$comparison  = $context->get_comparison();
		$has_range   = $query->uses_date_range();
		$has_compare = $has_range && $comparison instanceof Date_Range;

		$meta = array(
			'query'           => $query->get_name(),
			'range'           => $has_range ? $context->get_range()->to_array() : null,
			'compare'         => $has_compare ? array_merge( array( 'mode' => $context->get_param( 'compare' ) ), $comparison->to_array() ) : null,
			'generated_at'    => gmdate( 'c' ),
			'iawp_version'    => $this->environment->get_iawp_version(),
			'iawp_db_version' => $this->environment->get_db_version(),
			'is_pro'          => $this->environment->is_pro(),
			'schema_untested' => $this->environment->is_schema_untested(),
		);

		if ( $query->uses_cursor() ) {
			$has_more = ! empty( $output['has_more'] );

			$meta['pagination'] = array(
				'per_page'    => $context->get_per_page(),
				'has_more'    => $has_more,
				'next_cursor' => $has_more ? (string) ( $output['next_cursor'] ?? '' ) : null,
			);
		} elseif ( $query->is_paged() ) {
			$has_more = ! empty( $output['has_more'] );

			// The next page would start past the paging limit, so prepare_input() would refuse it.
			$is_capped = $has_more && $context->get_page() * $context->get_per_page() > MAX_GROUPED_ROW_OFFSET;

			$meta['pagination'] = array(
				'page'      => $context->get_page(),
				'per_page'  => $context->get_per_page(),
				'has_more'  => $has_more,
				'next_page' => ( $has_more && ! $is_capped ) ? $context->get_page() + 1 : null,
				'is_capped' => $is_capped,
			);
		} else {
			// Not paginated.
		}

		$envelope = array(
			'meta' => $meta,
			'rows' => $output['rows'] ?? array(),
		);

		// Extra series a query returns (previous_rows, series...) pass through as they are.
		foreach ( $output as $key => $value ) {
			if ( ! in_array( $key, array( 'rows', 'has_more', 'next_cursor' ), true ) ) {
				$envelope[ $key ] = $value;
			}
		}

		return $envelope;
	}
}
