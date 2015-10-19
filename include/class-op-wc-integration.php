<?php
/**
 * ONTRAPORT - WooCommerce Integration
 *
 * @package WC_ONTRAPORT_Integration
 * @category Integration
 * @author ONTRAPORT <bburleson@ONTRAPORT.com>
 */

require_once(dirname(__FILE__) . "/Ontraport.php");

if (!class_exists("WC_ONTRAPORT_Integration")) :

class WC_ONTRAPORT_Integration extends WC_Integration
{
    private function _write_log ($log)
    {
        if ($this->debug)
        {
            if (is_array($log) || is_object($log))
            {
                error_log(print_r($log, true));
            }
            else
            {
                error_log($log);
            }
        }
    }

    /**
     * Init and hook in the integration
     */
    public function __construct()
    {
        global $woocommerce;

        $this->id = "wc-op-integration";
        $this->method_title = __("ONTRAPORT Integration", "op-wc-integration");
        $this->method_description = __("An integration between ONTRAPORT and WooCommerce", "op-wc-integration");

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->api_app_id = $this->get_option("api_app_id");
        $this->api_key = $this->get_option("api_key");
        $this->debug = $this->get_option("debug");
        $this->tag_contacts = $this->get_option("tag_contacts");

        // Actions
        add_action("woocommerce_update_options_integration_" . $this->id, array($this, "process_admin_options"));
        // NOTE: This *might* not be the best action to hook into, check WooCommerce docs
        add_action("woocommerce_checkout_order_processed", array($this, "process_order_complete"), 10, 2);
    }

    /**
     * Initialize integration settings form fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            "api_app_id" => array(
                "title"             => __("API App ID", "op-wc-integration"),
                "type"              => "text",
                "description"       => __("Enter your API App ID.", "op-wc-integration"),
                "desc_tip"          => true,
                "default"           => ""
            ),
            "api_key" => array(
                "title"             => __("API Key", "op-wc-integration"),
                "type"              => "text",
                "description"       => __("Enter your API Key.", "op-wc-integration"),
                "desc_tip"          => true,
                "default"           => ""
            ),
            "debug" => array(
                "title"             => __("Debug Log", "op-wc-integration"),
                "type"              => "checkbox",
                "label"             => __("Enable logging", "op-wc-integration"),
                "default"           => "no",
                "description"       => __("Log events such as API requests", "op-wc-integration"),
            ),
            "tag_contacts" => array(
                "title"             => __("Tag Contacts", "op-wc-integration"),
                "type"              => "text",
                "description"       => __("Enter an optional Tag for Contacts that complete a purchase.", "op-wc-integration"),
                "desc_tip"          => true,
                "default"           => ""
            )
        );
    }

    private function _validateKeys($apiAppId, $apiKey)
    {
        $OP = new ONTRAPORT_API($apiAppId, $apiKey, $this->debug);
        return $OP->KeysValidate();
    }

    /**
     * Validate the API key
     * @see validate_settings_fields()
     */
    public function validate_api_key_field($key)
    {
        // get the posted value
        $apiAppId = $_POST[$this->plugin_id . $this->id . '_' . "api_app_id"];
        $apiKey = $_POST[$this->plugin_id . $this->id . '_' . $key];

        // Make a call to validate apiAppId and apiKey
        if (!self::_validateKeys($apiAppId, $apiKey))
        {
            $this->errors["apiKeyValid"] = array(
                "message" => "Sorry, App ID and Api key did not validate. Please check your keys are valid at <a href=\"https://app.ontraport.com\">https://app.ontraport.com</a>"
            );
        }

        return $apiKey;
    }

    /**
     * Validate the API key
     * @see validate_settings_fields()
     */
    public function validate_api_app_id_field($key)
    {
        // get the posted value
        $apiAppId = $_POST[$this->plugin_id . $this->id . '_' . $key];
        $apiKey = $_POST[$this->plugin_id . $this->id . '_' . "api_key"];

        // Make a call to validate apiAppId and apiKey
        if (!self::_validateKeys($apiAppId, $apiKey))
        {
            $this->errors["apiKeyValid"] = array(
                "message" => "Sorry, App ID and Api key did not validate. Please check your keys are valid at <a href=\"https://app.ontraport.com\">https://app.ontraport.com</a>"
            );
        }

        return $apiAppId;
    }

    /**
     * Display errors by overriding the display_errors() method
     * @see display_errors()
     */
    public function display_errors() {
        // loop through each error and display it
        foreach ($this->errors as $error) {
            ?>
            <div class="error">
                <p><?php _e($error["message"], 'woocommerce-integration-demo' ); ?></p>
            </div>
            <?php
        }
        $this->errors = array();
    }

    public function process_order_complete($order_id, $posted) {
        $this->_write_log("Process Order Complete!");

        $cart = WC()->cart;
        $cartContents = $cart->cart_contents;

        foreach ($cartContents as $contents)
        {
            $quantity = $contents["quantity"];
            $productData = $contents["data"];
            $productId = $productData->id;
            $productPost = $productData->post;
            $productTitle = $productPost->post_title;
            $productName= $productPost->post_name;
            $productPrice = $productData->price;
        }

        $cartContentsTotal = $cart->cart_contents_total;
        $taxTotal = $cart->tax_total;
        $subtotal = $cart->subtotal;
        $shippingTotal = $cart->shipping_total;
        $total = $cart->total;

        $purchase = array(
            "quantity" => $quantity,
            "product" => $productTitle,
            "price" => $productPrice,
            "total" => $total
        );

        $customer = array
        (
            "firstname" => $posted["billing_first_name"],
            "lastname" => $posted["billing_last_name"],
            "email" => $posted["billing_email"],
            "phone" => $posted["billing_phone"],
            "address1" => $posted["billing_address_1"],
            "address2" => $posted["billing_address_2"],
            "city" => $posted["billing_city"],
            "state" => $posted["billing_state"],
            "postcode" => $posted["billing_postcode"],
            "country" => $posted["billing_country"]
        );

        $OP = new ONTRAPORT_API($this->api_app_id, $this->api_key, $this->debug);
        $OP->LogTransaction($customer, $purchase);

        if ($this->tag_contacts)
        {
            $OP->TagContact($customer, $this->tag_contacts);
        }
    }
}

endif;