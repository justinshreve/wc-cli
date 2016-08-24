<?php
if ( ! defined( 'WP_CLI' ) ) {
    return;
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
    return;
}

require_once __DIR__ . '/vendor/restful/RestCommand.php';
require_once __DIR__ . '/vendor/restful/Runner.php';

require_once __DIR__ . '/rest-runner.php';
require_once __DIR__ . '/WC_RestCommand.php';

if ( class_exists( 'WP_CLI' ) ) {
	\WC_CLI\REST_Runner::load_remote_commands();
	WP_CLI::add_hook( 'after_wp_load', '\WC_CLI\REST_Runner::after_wp_load' );
}
