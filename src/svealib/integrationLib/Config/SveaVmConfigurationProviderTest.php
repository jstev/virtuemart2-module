<?php
$root = realpath(dirname(__FILE__));
require_once "$root/../Includes.php";

/**
 * @implements ConfigurationProvider interface
 * This class returns virtuemart payment metod configuration data in a manner 
 * the Svea integration package (i.e. the included svealib) can use
 */
class SveaVmConfigurationProviderTest implements ConfigurationProvider
{
    public $config; // this contains the virtuemart payment method instance

    public function __construct($config) {
         $this->config = $config;
    }
    /**
     * @param type $type
     * @param type $country
     * @return type
     */
    public function getClientNumber($type, $country) {
        $lowertype = strtolower($type);
        if($lowertype == "paymentplan"){
            return $this->config->clientid_paymentplan;
        }  else {
            return $this->config->clientid_invoice;
        }
    }

    public function getEndPoint($type) {
        $type = strtoupper($type);
        if($type == "HOSTED"){
            return   Svea\SveaConfig::SWP_TEST_URL;;
        }elseif($type == "INVOICE" || $type == "PAYMENTPLAN"){
             return Svea\SveaConfig::SWP_TEST_WS_URL;
         }elseif($type == "HOSTED_ADMIN"){
             return Svea\SveaConfig::SWP_TEST_HOSTED_ADMIN_URL;
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
        return $this->config->merchantid_test;  // same for card and directbank     
    }
    /**
     * getter for this payment method instance (test) secret
     * 
     * @param $type -- not used
     * @param $country -- not used
     * @return returns this payment method instance (test) secret
     */
    public function getSecret($type, $country) {
        return $this->config->secret_test;  // same for card and directbank
    }
    /**
     * @param type $type
     * @param type $country
     * @return type
     */
    public function getPassword($type, $country) {
        $lowertype = strtolower($type);
        if($lowertype == "paymentplan"){
            return $this->config->password_paymentplan;
        }else{
            return $this->config->password_invoice;
        }

    }

    /**
     * @param type $type
     * @param type $country
     * @return type
     */
    public function getUsername($type, $country) {
        $lowertype = strtolower($type);
        if($lowertype == "paymentplan"){
            return $this->config->username_paymentplan;
        }  else {
             return $this->config->username_invoice;
        }
    }
}