<?php
$root = realpath(dirname(__FILE__));
require_once "$root/../Includes.php";

class SveaVmConfigurationProviderProd implements ConfigurationProvider{

    public $config;

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
            return   Svea\SveaConfig::SWP_PROD_URL;;
        }elseif($type == "INVOICE" || $type == "PAYMENTPLAN"){
             return Svea\SveaConfig::SWP_PROD_WS_URL;
         }elseif($type == "HOSTED_ADMIN"){
             return Svea\SveaConfig::SWP_PROD_HOSTED_ADMIN_URL;
        }  else {
           throw new Exception('Invalid type. Accepted values: INVOICE, PAYMENTPLAN, "HOSTED_ADMIN" or HOSTED');
        }
    }
    /**
     *
     * @param type $type
     * @param type $country
     * @return type
     */
    public function getMerchantId($type, $country) {
        $card = $this->config->merchantid_card_prod;
        if($card == ""){
            return $this->config->merchantid_directbank_prod;
        }else{
            return $card;
        }
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
     *
     * @param type $type
     * @param type $country
     * @return type
     */
    public function getSecret($type, $country) {
       $card = $this->config->secret_card_prod;
        if($card == ""){
            return $this->config->secret_directbank_prod;
        }else{
            return $card;
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