<?php
/**
 * Main Plugin Class
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Wires every hook and owns the shared instances.
 *
 * @since 0.1.0
 */
class Plugin {

	/**
	 * Single instance of the class.
	 *
	 * @since 0.1.0
	 * @var ?Plugin
	 */
	private static ?Plugin $instance = null;

	/**
	 * IAWP environment; created on first use.
	 *
	 * @since 0.1.0
	 * @var ?Environment
	 */
	private ?Environment $environment = null;

	/**
	 * SQL runner; created on first use.
	 *
	 * @since 0.1.0
	 * @var ?Query_Runner
	 */
	private ?Query_Runner $runner = null;

	/**
	 * Date range resolver; created on first use.
	 *
	 * @since 0.1.0
	 * @var ?Date_Range_Resolver
	 */
	private ?Date_Range_Resolver $resolver = null;

	/**
	 * Query library; created on first use.
	 *
	 * @since 0.1.0
	 * @var ?Query_Registry
	 */
	private ?Query_Registry $registry = null;

	/**
	 * Executor; created on first use.
	 *
	 * @since 0.1.0
	 * @var ?Executor
	 */
	private ?Executor $executor = null;

	/**
	 * Get single instance of the class.
	 *
	 * @since 0.1.0
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The IAWP environment.
	 *
	 * @since 0.1.0
	 * @return Environment
	 */
	public function get_environment(): Environment {
		if ( is_null( $this->environment ) ) {
			$this->environment = new Environment();
		}

		return $this->environment;
	}

	/**
	 * The SQL runner.
	 *
	 * @since 0.1.0
	 * @return Query_Runner
	 */
	public function get_runner(): Query_Runner {
		if ( is_null( $this->runner ) ) {
			$this->runner = new Query_Runner();
		}

		return $this->runner;
	}

	/**
	 * The date range resolver.
	 *
	 * @since 0.1.0
	 * @return Date_Range_Resolver
	 */
	public function get_resolver(): Date_Range_Resolver {
		if ( is_null( $this->resolver ) ) {
			$this->resolver = new Date_Range_Resolver( $this->get_environment() );
		}

		return $this->resolver;
	}

	/**
	 * The query library.
	 *
	 * @since 0.1.0
	 * @return Query_Registry
	 */
	public function get_registry(): Query_Registry {
		if ( is_null( $this->registry ) ) {
			$this->registry = new Query_Registry( $this->get_environment(), $this->get_runner() );
		}

		return $this->registry;
	}

	/**
	 * The executor.
	 *
	 * @since 0.1.0
	 * @return Executor
	 */
	public function get_executor(): Executor {
		if ( is_null( $this->executor ) ) {
			$this->executor = new Executor( $this->get_environment(), $this->get_registry(), $this->get_resolver(), $this->get_runner() );
		}

		return $this->executor;
	}

	/**
	 * Initialise hooks.
	 *
	 * @since 0.1.0
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Supports must-use installs, where activation hooks don't fire.
		add_action( 'admin_init', array( $this, 'check_first_run' ), 1 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @since 0.1.0
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'iawp-query-helper', false, dirname( IAWPQH_BASENAME ) . '/languages' );
	}

	/**
	 * Record or update the stored version.
	 *
	 * @since 0.1.0
	 */
	public function check_first_run(): void {
		$stored_version = get_option( OPTION_VERSION );

		if ( false === $stored_version ) {
			add_option( OPTION_VERSION, IAWPQH_VERSION, '', false );
		} elseif ( IAWPQH_VERSION !== $stored_version ) {
			update_option( OPTION_VERSION, IAWPQH_VERSION, false );
		} else {
			// Stored version is already current.
		}
	}

	/**
	 * Register REST routes. Hooked to rest_api_init.
	 *
	 * @since 0.1.0
	 */
	public function register_rest_routes(): void {
		$controller = new Rest_Controller( $this->get_environment(), $this->get_registry(), $this->get_executor(), $this->get_resolver() );
		$controller->register_routes();
	}

	/**
	 * Register the ability category. Hooked to wp_abilities_api_categories_init.
	 *
	 * @since 0.1.0
	 */
	public function register_ability_category(): void {
		$this->get_abilities()->register_category();
	}

	/**
	 * Register abilities. Hooked to wp_abilities_api_init.
	 *
	 * @since 0.1.0
	 */
	public function register_abilities(): void {
		$this->get_abilities()->register_abilities();
	}

	/**
	 * Warn administrators when IAWP is missing or its schema is newer than we have tested.
	 *
	 * @since 0.1.0
	 */
	public function admin_notices(): void {
		$environment = $this->get_environment();

		if ( ! current_user_can( 'activate_plugins' ) ) {
			// Only administrators can act on these notices.
		} elseif ( ! $environment->is_iawp_loaded() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: plugin name. */
						__( '%s needs Independent Analytics (free or Pro) to be active. Its REST routes and abilities are switched off until then.', 'iawp-query-helper' ),
						PLUGIN_NAME
					)
				)
			);
		} elseif ( $environment->is_schema_untested() ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: IAWP database version, 3: highest tested version. */
						__( '%1$s has not been tested with Independent Analytics database version %2$d (tested up to %3$d). Queries still run; check for an update.', 'iawp-query-helper' ),
						PLUGIN_NAME,
						$environment->get_db_version(),
						IAWP_HIGHEST_TESTED_SCHEMA
					)
				)
			);
		} else {
			// IAWP present and schema tested: nothing to say.
		}
	}

	/**
	 * A new Abilities registrar.
	 *
	 * @since 0.1.0
	 * @return Abilities
	 */
	private function get_abilities(): Abilities {
		return new Abilities( $this->get_environment(), $this->get_registry(), $this->get_executor(), $this->get_resolver() );
	}
}
