<?php

namespace WC_CLI;

class REST_Runner extends Runner {

    /**
     * Endpoints that should not be made available to CLI.
     */
    private static $disabled_endpoints = array(
        'settings',
        'settings/(?P<group>[\w-]+)',
        'settings/(?P<group>[\w-]+)/batch',
        'settings/(?P<group>[\w-]+)/(?P<id>[\w-]+)',
        'system_status',
    );

    /**
     *
     */
    public static function after_wp_load() {
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server;
        do_action( 'rest_api_init', $wp_rest_server );

        $request = new \WP_REST_Request( 'GET', '/' );
        $request->set_param( 'context', 'help' );
        $response = $wp_rest_server->dispatch( $request );
        $response_data = $response->get_data();
        if ( empty( $response_data ) ) {
            return;
        }

        // Loop through all of our endpoints and register any valid WC endpoints.
        foreach( $response_data['routes'] as $route => $route_data ) {
            // Only register WC endpoints
            if ( substr( $route, 0, 4 ) !== '/wc/' ) {
                continue;
            }
            // Only register endpoints with schemas
            if ( empty( $route_data['schema']['title'] ) ) {
                \WP_CLI::debug( "No schema title found for {$route}, skipping REST command registration.", 'rest' );
                continue;
            }
            // Disable specific endpoints
            $route_pieces   = explode( '/', $route );
            $endpoint_piece = str_replace( '/wc/' . $route_pieces[2] . '/', '', $route );
            if ( in_array( $endpoint_piece, self::$disabled_endpoints ) ) {
                continue;
            }

            self::register_route_commands( new WC_RESTCommand( $route_data['schema']['title'], $route, $route_data['schema'] ), $route, $route_data );
        }
    }

    /**
	 * Register WP-CLI commands for all endpoints on a route
	 *
	 * @param string
	 * @param array $endpoints
	 */
	private static function register_route_commands( $rest_command, $route, $route_data, $command_args = array() ) {
		$parent             = "wc {$route_data['schema']['title']}";
		$supported_commands = array();
        if ( 'customer' !== $route_data['schema']['title'] ) {
            return;
        }

        // Get a list of supported commands for each route.
		foreach ( $route_data['endpoints'] as $endpoint ) {
        //    error_log( print_r ( $endpoint, 1 ) );
			$parsed_args   = preg_match_all( '#\([^\)]+\)#', $route, $matches );
			$resource_id   = ! empty( $matches[0] ) ? array_pop( $matches[0] ) : null;
			$trimmed_route = rtrim( $route );
			$is_singular   = $resource_id === substr( $trimmed_route, - strlen( $resource_id ) );

			$command = '';
			// List a collection
			if ( array( 'GET' ) == $endpoint['methods'] && ! $is_singular ) {
				$supported_commands['list'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}
			// Create a specific resource
			if ( array( 'POST' ) == $endpoint['methods'] && ! $is_singular ) {
				$supported_commands['create'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}
			// Get a specific resource
			if ( array( 'GET' ) == $endpoint['methods'] && $is_singular ) {
				$supported_commands['get'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}
			// Update a specific resource
			if ( in_array( 'POST', $endpoint['methods'] ) && $is_singular ) {
				$supported_commands['update'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}
			// Delete a specific resource
			if ( array( 'DELETE' ) == $endpoint['methods'] && $is_singular ) {
				$supported_commands['delete'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}
		}

		foreach( $supported_commands as $command => $endpoint_args ) {

			$synopsis = array();
			if ( in_array( $command, array( 'delete', 'get', 'update' ) ) ) {
				$synopsis[] = array(
					'name'        => 'id',
					'type'        => 'positional',
					'description' => 'The id for the resource.',
					'optional'    => false,
				);
			}

			foreach ( $endpoint_args as $name => $args ) {
                // Handle nested properties
				$arg_regs[] = array(
					'name'        => $name,
					'type'        => 'assoc',
					'description' => ! empty( $args['description'] ) ? $args['description'] : '',
					'optional'    => empty( $args['required'] ) ? true : false,
				);

                // @todo clean this up a bit..
                // @todo better way to replace "bad" characters
                if ( 'create' === $command || 'update' === $command ) {
                    if ( is_array( $route_data['schema']['properties'][ $name ]['properties'] ) ) {
                        foreach ( $route_data['schema']['properties'][ $name ]['properties'] as $prop_name => $prop_args ) {
                            $arg_regs[] = array(
                                'name'        => $name . "-" . str_replace( '1', 'one', str_replace( '2', 'two', $prop_name ) ),
                                'type'        => 'assoc',
                                'description' => ! empty( $prop_args['description'] ) ? $prop_args['description'] : '',
                                'optional'    => empty( $prop_args['required'] ) ? true : false,
                            );
                        }
                    }
                }

                foreach ( $arg_regs as $arg_reg ) {
    				$synopsis[] = $arg_reg;
                }
			}

			if ( in_array( $command, array( 'list', 'get' ) ) ) {
				$synopsis[] = array(
					'name'        => 'fields',
					'type'        => 'assoc',
					'description' => 'Limit response to specific fields. Defaults to all fields.',
					'optional'    => true,
				);
				$synopsis[] = array(
					'name'        => 'field',
					'type'        => 'assoc',
					'description' => 'Get the value of an individual field.',
					'optional'    => true,
				);
				$synopsis[] = array(
					'name'        => 'format',
					'type'        => 'assoc',
					'description' => 'Render response in a particular format.',
					'optional'    => true,
					'default'     => 'table',
					'options'     => array(
						'table',
						'json',
						'csv',
						'ids',
						'yaml',
						'count',
						'headers',
						'body',
						'envelope',
					),
				);
			}

			if ( in_array( $command, array( 'create', 'update', 'delete' ) ) ) {
				$synopsis[] = array(
					'name'        => 'porcelain',
					'type'        => 'flag',
					'description' => 'Output just the id when the operation is successful.',
					'optional'    => true,
				);
			}

			$methods = array(
				'list'       => 'list_items',
				'create'     => 'create_item',
				'delete'     => 'delete_item',
				'get'        => 'get_item',
				'update'     => 'update_item',
			);

			$before_invoke = null;
			if ( empty( $command_args['when'] ) && \WP_CLI::get_config( 'debug' ) ) {
				$before_invoke = function() {
					if ( ! defined( 'SAVEQUERIES' ) ) {
						define( 'SAVEQUERIES', true );
					}
				};
			}

			\WP_CLI::add_command( "{$parent} {$command}", array( $rest_command, $methods[ $command ] ), array(
				'synopsis'      => $synopsis,
				'when'          => ! empty( $command_args['when'] ) ? $command_args['when'] : '',
				'before_invoke' => $before_invoke,
			) );

			if ( 'update' === $command && array_key_exists( 'get', $supported_commands ) ) {
				$synopsis = array();
				$synopsis[] = array(
					'name'        => 'id',
					'type'        => 'positional',
					'description' => 'The id for the resource.',
					'optional'    => false,
				);
				\WP_CLI::add_command( "{$parent} edit", array( $rest_command, 'edit_item' ), array(
					'synopsis'      => $synopsis,
					'when'          => ! empty( $command_args['when'] ) ? $command_args['when'] : '',
				) );
			}

		}
	}

}
