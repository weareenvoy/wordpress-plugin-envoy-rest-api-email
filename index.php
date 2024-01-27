<?php
/**
 * Plugin Name: Envoy Rest API
 * Description: Envoy Rest API endpoints
 * Author: WeAreEnvoy
 * Author URI: https://www.weareenvoy.com/
 */
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// TO-DO: Create auto-loader so we don't have to require_once()
require_once __DIR__ . '/src/EmailRouting/index.php';
require_once __DIR__ . '/src/EmailRouting/AdminSettings/index.php';

add_action( 'rest_api_init', function(){
    $router = new EmailRouting();
});
