<?php
/**
 * REST routes: one GET route per named query.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Registers `GET /wp-json/iawp-query-helper/v1/<query-name>` and hands each request to the executor.
 *
 * @since 0.1.0
 */
class Rest_Controller {

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
	 * Executor.
	 *
	 * @since 0.1.0
	 * @var Executor
	 */
	private Executor $executor;

	/**
	 * Date range resolver, for the period list in each route's arguments.
	 *
	 * @since 0.1.0
	 * @var Date_Range_Resolver
	 */
	private Date_Range_Resolver $resolver;

	/**
	 * Create the controller.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment         $environment IAWP environment.
	 * @param Query_Registry      $registry    Query library.
	 * @param Executor            $executor    Executor.
	 * @param Date_Range_Resolver $resolver    Date range resolver.
	 */
	public function __construct( Environment $environment, Query_Registry $registry, Executor $executor, Date_Range_Resolver $resolver ) {
		$this->environment = $environment;
		$this->registry    = $registry;
		$this->executor    = $executor;
		$this->resolver    = $resolver;
	}

	/**
	 * Register a route for each query this site can offer. Hooked to rest_api_init.
	 *
	 * @since 0.1.0
	 */
	public function register_routes(): void {
		if ( $this->environment->is_iawp_loaded() ) {
			foreach ( $this->registry->get_offered( $this->environment->is_pro() ) as $query ) {
				$schema = $query->get_input_schema( $this->resolver->get_periods() );

				register_rest_route(
					REST_NAMESPACE,
					'/' . $query->get_name(),
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => fn( \WP_REST_Request $request ): \WP_REST_Response => $this->handle_request( $query, $request ),
						'permission_callback' => __NAMESPACE__ . '\check_query_permission',
						'args'                => $schema['properties'],
						'schema'              => fn(): array => array_merge(
							array(
								'$schema' => 'http://json-schema.org/draft-04/schema#',
								'title'   => ABILITY_NAMESPACE . '-' . $query->get_name(),
							),
							$query->get_output_schema()
						),
					)
				);
			}
		}
	}

	/**
	 * Run one query from a REST request.
	 *
	 * @since 0.1.0
	 *
	 * @param Query            $query   Query.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	private function handle_request( Query $query, \WP_REST_Request $request ): \WP_REST_Response {
		$input = array();

		foreach ( array_keys( $query->get_input_schema( $this->resolver->get_periods() )['properties'] ) as $name ) {
			if ( $request->has_param( $name ) ) {
				$input[ $name ] = $request->get_param( $name );
			}
		}

		$result = $this->executor->execute( $query->get_name(), $input );

		if ( is_wp_error( $result ) ) {
			$response   = rest_convert_error_to_response( $result );
			$error_data = $result->get_error_data();

			if ( is_array( $error_data ) && isset( $error_data['retry_after'] ) ) {
				$response->header( 'Retry-After', (string) (int) $error_data['retry_after'] );
			}
		} else {
			$response = new \WP_REST_Response( $result, 200 );
		}

		return $response;
	}
}
