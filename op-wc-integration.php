<?php
/**
 * Plugin Name: ONTRAPORT-WooCommerce Integration
 * Plugin URI:
 * Description: ONTRAPORT integration for WooCommerce
 * Version: 0.1.0
 * Author: ONTRAPORT
 * Author URI: https://ontraport.com
 */

if (!class_exists("OP_WC")) :

class OP_WC
{
    /**
     * Construct the plugin.
     */
    public function __construct()
    {
        add_action("plugins_loaded", array($this, 'init'));
    }

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        // Checks if WooCommerce is installed.
        if ( class_exists("WC_Integration"))
        {
            // Include our integration class.
            require_once(dirname(__FILE__) . "/include/class-op-wc-integration.php");

            // Register the integration.
            add_filter("woocommerce_integrations", array($this, "add_integration"));
        }
        else
        {
            // throw an admin error if you like
        }
    }

    /**
     * Add a new integration to WooCommerce.
     */
    public function add_integration($integrations)
    {
        $integrations[] = "WC_ONTRAPORT_Integration";
        return $integrations;
    }
}

$OP_WC_Integration = new OP_WC(__FILE__);

endif;