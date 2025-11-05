<?php
/**
 * Plugin Name: WC Pro Franco Cartons
 * Description: Enforce Franco Cartons wholesaler shipping rules for WooCommerce.
 * Author: WeAreFiber
 * Version: 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

use WCProFrancoCartons\Plugin;

if (!function_exists('wc_pro_franco_cartons_boot')) {
    function wc_pro_franco_cartons_boot(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $plugin = new Plugin();
        $plugin->boot();
    }
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', 'wc_pro_franco_cartons_boot');
}
