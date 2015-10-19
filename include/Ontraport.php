<?php

/**
 * ONTRAPORT API Library
 *
 * @author Ben Burleson <bburleson@ONTRAPORT.com>
 */

define("API_VERSION", "1");
define("API_ENDPOINT", "https://api.ontraport.com/" . API_VERSION);

define("CONTACT_ITEM", 0);
define("TAG_ITEM", 14);
define("PRODUCT_ITEM", 16);

class ONTRAPORT_API
{
    private $_apiAppId = "";
    private $_apiKey = "";
    private $_debug = true;

    private function _write_log ($log)
    {
        if ("yes" === $this->_debug)
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

    function __construct ($apiAppId, $apiKey, $debug=false)
    {
        $this->_apiAppId = $apiAppId;
        $this->_apiKey = $apiKey;
        $this->_debug = $debug;
    }

    /**
     * Create a request to the ONTRAPORT API
     *
     * Resources available to sub-accounts:
     *
     * @param method The HTTP Method, supported: POST, GET, DELETE
     * @param resource The API Resource to submit to
     * @param params The post paramaters to send in an array or a URL-encoded query string
     * @return Decoded response from Twilio
     */
    private function _api_request ($method, $resource, $params)
    {
        if (is_array($params))
        {
            $params = http_build_query($params);
        }
        $this->_write_log("API params:");
        $this->_write_log($params);

        switch ($method)
        {
            case "PUT":
            case "POST":
            case "DELETE":
                $ch = curl_init(API_ENDPOINT . "/" . $resource);
                break;
            case "GET":
                $ch = curl_init(API_ENDPOINT . "/" . $resource . "?" . $params);
                break;
            default:
                trigger_error("Unknown _api_request method: " . $method);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Api-Appid: " . $this->_apiAppId,
            "Api-Key: " . $this->_apiKey
        ));

        if ($method == "POST")
        {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        else if ($method == "PUT")
        {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        else if ($method == "DELETE")
        {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        curl_close($ch);

        $result = json_decode($result, true);

        if (isset($result["uri"]))
        {
            unset($result["uri"]);
        }

        $this->_write_log("API Result:");
        $this->_write_log($result);

        return $result;
    }

    /**
     * @brief See if our keys work!
     *
     * Use
     * https://api.ontraport.com/1/objects?objectID=0&range=1
     * And check the response.
     *
     * @return true if they validate; otherwise false
     */
    public function KeysValidate()
    {
        $this->_write_log("Validating API keys");

        $result = $this->_api_request("GET", "objects", array(
            "objectID" => CONTACT_ITEM,
            "range" => 1
        ));

        return (is_array($result)) ? true : false;
    }

    /**
     * @brief Create a new object with given params
     * @param $objectTypeId int Object Type ID
     * @param $params array New object attributes
     * @return ID of object created or null
     */
    private function _createObject($objectTypeId, $params)
    {
        $this->_write_log("Creating object of type:" . $objectTypeId);
        $this->_write_log($params);

        $params["objectID"] = $objectTypeId;

        $result = $this->_api_request("POST", "objects", $params);

        if (is_array($result["data"]) && array_key_exists("id", $result["data"]))
        {
            return $result["data"]["id"];
        }

        return null;
    }

    private function _createProduct($name, $price)
    {
        $params = array(
            "name" => $name,
            "price" => $price
        );

        return $this->_createObject(PRODUCT_ITEM, $params);
    }

    private function _createContact($contactInfo)
    {
        $params = array(
            "firstname" => $contactInfo["firstname"],
            "lastname" => $contactInfo["lastname"],
            "email" => $contactInfo["email"],
            "cell_phone" => $contactInfo["phone"],
            "address" => $contactInfo["address1"],
            "address2" => $contactInfo["address2"],
            "city" => $contactInfo["city"],
            "state" => $contactInfo["state"],
            "zip" => $contactInfo["postcode"],
            "country" => $contactInfo["country"]
        );

        return $this->_createObject(CONTACT_ITEM, $params);
    }

    private function _createTag($tag)
    {
        $params = array(
            "object_type_id" => 0,
            "tag_name" => $tag
        );

        return $this->_createObject(TAG_ITEM, $params);
    }

    /**
     * @brief Fetch an Object if it exists
     * @param $objectTypeId int Object Type ID
     * @param $condition string search condition
     * @return array API response
     */
    private function _getObjectsByCondition($objectTypeId, $condition)
    {
        $this->_write_log("Getting objects of type:" . $objectTypeId);

        return $this->_api_request("GET", "objects", array(
            "objectID" => $objectTypeId,
            "condition" => $condition
        ));
    }

    private function _getContactByEmail($email)
    {
        $condition = "email='" . $email . "'";
        return $this->_getObjectsByCondition(CONTACT_ITEM, $condition);
    }

    private function _getProductByName($name)
    {
        $condition = "name='" . $name . "'";
        return $this->_getObjectsByCondition(PRODUCT_ITEM, $condition);
    }

    private function _getTagByName($name)
    {
        $condition = "tag_name='" . $name . "'";
        return $this->_getObjectsByCondition(TAG_ITEM, $condition, true);
    }

    /**
     * @brief Return Product ID with given name or false
     * @param $name string Name of Product
     * @return int Product ID or false
     */
    private function _productExists($name)
    {
        $existingProducts = $this->_getProductByName($name);

        foreach ($existingProducts["data"] as $product)
        {
            if ($name == $product["name"])
            {
                return $product["id"];
            }
        }

        return false;
    }

    /**
     * @brief Return Contact ID with given email or false
     * @param $name string Contact email address
     * @return int Contact ID or false
     */
    private function _contactExists($email)
    {
        $existingContacts = $this->_getContactByEmail($email);

        foreach ($existingContacts["data"] as $contact)
        {
            if ($email == $contact["email"])
            {
                return $contact["id"];
            }
        }

        return false;
    }

    /**
     * @brief Return Tag ID with given name or false
     * @param $name string Tag name
     * @return int Tag ID or false
     */
    private function _tagExists($tagName)
    {
        $existingTags = $this->_getTagByName($tagName);

        foreach ($existingTags["data"] as $tag)
        {
            if ($tagName == $tag["tag_name"])
            {
                return $tag["tag_id"];
            }
        }

        return false;
    }

    /**
     * @brief Log a transaction for an existing Contact
     * @param $contactId int Contact ID
     * @param $productId int Product ID
     * @param $purchaseInfo array Purchase information
     */
    private function _logTransaction($contactId, $productId, $purchaseInfo)
    {
        $this->_write_log("Logging transaction for Contact ID:" . $contactId . ", Product ID:" . $productId . " and purchase info:");
        $this->_write_log($purchaseInfo);

        $params = array (
            "contact_id" => $contactId,
            "chargeNow" => "chargeLog",
            "offer" => array(
                "products" => array(
                    array(
                        "quantity" => $purchaseInfo["quantity"],
                        "id" => $productId
                    )
                )
            )
        );

        $result = $this->_api_request("POST", "transaction/processManual", $params);
    }

    /**
     * @brief Log transaction for customer, create things that don't yet exist
     * @param $customer array Customer information
     * @param $purchase array Purchase information
     */
    public function LogTransaction($customer, $purchase)
    {
        $this->_write_log($customer);
        $this->_write_log($purchase);

        // Check if this product exists
        $productId = $this->_productExists($purchase["product"]);

        // If not, create it
        if (!$productId)
        {
            $productId = $this->_createProduct($purchase["product"], $purchase["price"]);
        }

        $this->_write_log("Using Product ID:" . $productId);

        // Check if this Contact exists
        $contactId = $this->_contactExists($customer["email"]);

        // If not, create it
        if (!$contactId)
        {
            $contactId = $this->_createContact($customer);
        }

        $this->_write_log("Using Contact ID:" . $contactId);

        // Log transaction
        $this->_logTransaction($contactId, $productId, $purchase);
    }

    /**
     * @brief Tag a customer, create things that don't yet exist
     * @param $customer array Customer information
     * @param $tag string Tag name
     */
    public function TagContact($customer, $tag)
    {
        // Check if this Contact exists
        $contactId = $this->_contactExists($customer["email"]);

        // If not, create it
        if (!$contactId)
        {
            $contactId = $this->_createContact($customer);
        }

        // Check if this Tag exists
        $tagId = $this->_tagExists($tag);

        // If not, create it
        if (!$tagId)
        {
            $tagId = $this->_createTag($tag);
        }

        $this->_write_log("Tagging Contact ID:" . $contactId . " with Tag ID:" . $tagId);

        $params = array (
            "objectID" => CONTACT_ITEM,
            "add_list" => $tagId,
            "ids" => $contactId
        );

        $result = $this->_api_request("PUT", "objects/tag", $params);
    }
}