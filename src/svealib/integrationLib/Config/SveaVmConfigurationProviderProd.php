<?php
$root = realpath(dirname(__FILE__));
require_once "$root/../Includes.php";

/**
 * @implements ConfigurationProvider interface
 * 
 * This class returns virtuemart payment metod configuration data in a manner 
 * the Svea integration package (i.e. the included svealib) can use
 * 
 * As each payment method is an instance with its own ConfigurationProvider 
 * implementation, methods often just return the configuration setting.
 */
class SveaVmConfigurationProviderProd implements ConfigurationProvider
{
    public $config; // this contains the virtuemart payment method instance

    public function __construct($config) {
         $this->config = $config;
    }
    /**
     * getter for this payment method instance (test) ClientId (ClientNumber)
     */
    public function getClientNumber($type, $country) {
        return property_exists( $this->config, "clientid" ) ? $this->config->clientid : false;  // same for test and prod
    }
    /**
     * getter for this payment method instance (test) password
     */
    public function getPassword($type, $country) {
        return $this->config->password;     // same for test and prod
    }

    /**
     * getter for this payment method instance (test) username
     */
    public function getUsername($type, $country) {
        return $this->config->username;     // same for test and prod
    }

    public function getEndPoint($type) {
        $type = strtoupper($type);
        if($type == "HOSTED"){
            return   Svea\SveaConfig::SWP_PROD_URL;;
        }elseif($type == "INVOICE" || $type == "PAYMENTPLAN"){
             return Svea\SveaConfig::SWP_PROD_WS_URL;
         }elseif($type == "HOSTED_ADMIN"){
             return Svea\SveaConfig::SWP_PROD_HOSTED_ADMIN_URL;
        }  else {
           throw new Exception('Invalid type. Accepted values: INVOICE, PAYMENTPLAN, HOSTED_ADMIN or HOSTED');
        }
    }
    /**
     * getter for this payment method instance (test) merchantid
     * 
     * @param $type -- not used
     * @param $country -- not used
     * @return returns this payment method instance (test) merchantid
     */
    public function getMerchantId($type, $country) {
        return $this->config->merchantid_prod;
    }
    /**
     * getter for this payment method instance (test) secret
     * 
     * @param $type -- not used
     * @param $country -- not used
     * @return returns this payment method instance (test) secret
     */
    public function getSecret($type, $country) {
        return $this->config->secret_prod;
    }
}