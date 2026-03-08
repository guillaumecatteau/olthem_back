<?php

add_theme_support('post-thumbnails');
add_theme_support('menus');

function olthem_theme_setup() {
	register_nav_menus(array(
		'header' => 'Header menu',
		'footer' => 'Footer menu',
	));
}
add_action('after_setup_theme', 'olthem_theme_setup');

function olthem_enqueue_assets() {
	$theme_version = wp_get_theme()->get('Version');

	wp_enqueue_style(
		'olthem-style',
		get_stylesheet_uri(),
		array(),
		$theme_version
	);

	// CSS is generated from SCSS source files under /scss/src.
	wp_enqueue_style(
		'olthem-app-style',
		get_template_directory_uri() . '/scss/app.css',
		array('olthem-style'),
		$theme_version
	);

	wp_enqueue_script(
		'olthem-app-script',
		get_template_directory_uri() . '/js/app.js',
		array(),
		$theme_version,
		true
	);

	wp_localize_script('olthem-app-script', 'OLTHEM_APP', array(
		'homeUrl' => home_url('/'),
		'apiProxyBase' => rest_url('olthem/v1/proxy/'),
		'nonce' => wp_create_nonce('wp_rest'),
	));
}
add_action('wp_enqueue_scripts', 'olthem_enqueue_assets');

function olthem_script_as_module($tag, $handle, $src) {
	if ($handle !== 'olthem-app-script') {
		return $tag;
	}

	return '<script type="module" src="' . esc_url($src) . '"></script>';
}
add_filter('script_loader_tag', 'olthem_script_as_module', 10, 3);

function olthem_register_rewrites() {
	add_rewrite_tag('%olthem_spa%', '(.+)');

	add_rewrite_rule('^(header|projet|thematiques|ressources|ateliers|partenaires)/?$', 'index.php?olthem_spa=$matches[1]', 'top');
	add_rewrite_rule('^blog/?$', 'index.php?olthem_spa=blog', 'top');
	add_rewrite_rule('^blog/([^/]+)/?$', 'index.php?olthem_spa=blog/$matches[1]', 'top');
	add_rewrite_rule('^admin-tool/?$', 'index.php?olthem_spa=admin-tool', 'top');
	add_rewrite_rule('^admin-tool/(.+)/?$', 'index.php?olthem_spa=admin-tool/$matches[1]', 'top');
}
add_action('init', 'olthem_register_rewrites');

function olthem_flush_rewrite_rules() {
	olthem_register_rewrites();
	flush_rewrite_rules();
}
add_action('after_switch_theme', 'olthem_flush_rewrite_rules');

function olthem_query_vars($vars) {
	$vars[] = 'olthem_spa';
	return $vars;
}
add_filter('query_vars', 'olthem_query_vars');

function olthem_force_front_page_template($template) {
	$spa_route = get_query_var('olthem_spa');
	if (!empty($spa_route)) {
		$front_template = locate_template(array('front-page.php'));
		if (!empty($front_template)) {
			return $front_template;
		}
	}

	return $template;
}
add_filter('template_include', 'olthem_force_front_page_template');

function olthem_get_external_api_base_url() {
	$env_value = getenv('OLTHEM_EXTERNAL_API_BASE');
	if (!empty($env_value)) {
		return trailingslashit($env_value);
	}

	return trailingslashit('https://api.example.com/');
}

function olthem_rest_proxy_callback(WP_REST_Request $request) {
	$base_url = olthem_get_external_api_base_url();
	if (empty($base_url)) {
		return new WP_Error('olthem_missing_api_base', 'External API base URL is not configured.', array('status' => 500));
	}

	$path = ltrim((string) $request->get_param('path'), '/');
	$target_url = $base_url . $path;

	$method = $request->get_method();
	$headers = array(
		'Accept' => 'application/json',
	);

	$authorization = $request->get_header('authorization');
	if (!empty($authorization)) {
		$headers['Authorization'] = $authorization;
	}

	$args = array(
		'method' => $method,
		'timeout' => 20,
	);

	$query_params = $request->get_query_params();
	unset($query_params['path']);
	if (!empty($query_params)) {
		$target_url = add_query_arg($query_params, $target_url);
	}

	if (!in_array($method, array('GET', 'HEAD'), true)) {
		$raw_body = $request->get_body();
		if (!empty($raw_body)) {
			$args['body'] = $raw_body;
			$headers['Content-Type'] = 'application/json';
		} else {
			$json_payload = $request->get_json_params();
			if (!empty($json_payload)) {
				$args['body'] = wp_json_encode($json_payload);
				$headers['Content-Type'] = 'application/json';
			}
		}
	}

	$args['headers'] = $headers;

	$response = wp_remote_request($target_url, $args);
	if (is_wp_error($response)) {
		return new WP_Error('olthem_proxy_failed', $response->get_error_message(), array('status' => 502));
	}

	$status_code = wp_remote_retrieve_response_code($response);
	$response_body = wp_remote_retrieve_body($response);
	$decoded_json = json_decode($response_body, true);

	if (json_last_error() === JSON_ERROR_NONE) {
		return new WP_REST_Response($decoded_json, $status_code);
	}

	return new WP_REST_Response(array('raw' => $response_body), $status_code);
}

function olthem_register_rest_routes() {
	register_rest_route('olthem/v1', '/proxy/(?P<path>.*)', array(
		'methods' => WP_REST_Server::ALLMETHODS,
		'callback' => 'olthem_rest_proxy_callback',
		'permission_callback' => '__return_true',
	));
}
add_action('rest_api_init', 'olthem_register_rest_routes');

add_action('acf/init', function() {
	if (function_exists('acf_add_options_page')) {
		acf_add_options_page(array(
			'page_title' => 'General Settings',
			'menu_title' => 'Options',
			'menu_slug' => 'general-settings',
			'capability' => 'edit_posts',
			'redirect' => false,
		));
	}
});

