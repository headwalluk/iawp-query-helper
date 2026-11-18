<?php
/**
 * Abilities API registration: one ability per named query.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Registers the ability category and `iawp-query-helper/<query-name>` abilities.
 *
 * Core serves them at /wp-json/wp-abilities/v1. The MCP Adapter sees them only when IS_MCP_ENABLED is true.
 *
 * @since 0.1.0
 */
class Abilities {

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
	 * Date range resolver, for the period list in each input schema.
	 *
	 * @since 0.1.0
	 * @var Date_Range_Resolver
	 */
	private Date_Range_Resolver $resolver;

	/**
	 * Create the registrar.
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
	 * Register the ability category. Hooked to wp_abilities_api_categories_init.
	 *
	 * @since 0.1.0
	 */
	public function register_category(): void {
		if ( $this->environment->is_iawp_loaded() ) {
			wp_register_ability_category(
				ABILITY_CATEGORY,
				array(
					'label'       => _x( 'Independent Analytics', 'ability category label; product name', 'iawp-query-helper' ),
					'description' => __( 'Read-only queries over Independent Analytics visitor and view data.', 'iawp-query-helper' ),
				)
			);
		}
	}

	/**
	 * Register an ability for each query this site can offer. Hooked to wp_abilities_api_init.
	 *
	 * @since 0.1.0
	 */
	public function register_abilities(): void {
		if ( $this->environment->is_iawp_loaded() ) {
			foreach ( $this->registry->get_offered( $this->environment->is_pro() ) as $query ) {
				$query_name = $query->get_name();

				wp_register_ability(
					ABILITY_NAMESPACE . '/' . $query_name,
					array(
						'label'               => $query->get_label(),
						'description'         => $query->get_description(),
						'category'            => ABILITY_CATEGORY,
						'input_schema'        => $query->get_input_schema( $this->resolver->get_periods() ),
						'output_schema'       => $query->get_output_schema(),
						'execute_callback'    => fn( mixed $input = null ): array|\WP_Error => $this->executor->execute( $query_name, $input ),
						'permission_callback' => fn(): true|\WP_Error => check_query_permission(),
						'meta'                => array(
							'annotations'  => array(
								'readonly'    => true,
								'destructive' => false,
								'idempotent'  => true,
							),
							// `public` arrived in WordPress 7.1; `show_in_rest` covers 6.9 and 7.0.
							'public'       => true,
							'show_in_rest' => true,
							// The adapter reads an explicit mcp.public before falling back to `public`.
							'mcp'          => array( 'public' => IS_MCP_ENABLED ),
						),
					)
				);
			}
		}
	}
}
