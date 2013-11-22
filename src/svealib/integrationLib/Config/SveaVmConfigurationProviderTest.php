<?php
$root = realpath(dirname(__FILE__));
require_once "$root/../Includes.php";

class SveaVmConfigurationProviderTest implements ConfigurationProvider{

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
        $country = strtolower($country);
        $lowertype = strtolower($type);
          if($lowertype == "paymentplan"){
              switch ($country) {
                  case "se":
                      return $this->config->clientid_paymentplan_se;
                      break;
                  case "no":
                      return $this->config->clientid_paymentplan_no;
                      break;
                  case "fi":
                      return $this->config->clientid_paymentplan_fi;
                      break;
                  case "dk":
                      return $this->config->clientid_paymentplan_dk;
                      break;
                  case "de":
                      return $this->config->clientid_paymentplan_de;
                      break;
                  case "nl":
                      return $this->config->clientid_paymentplan_nl;
                      break;
              }
        }
        switch ($country) {
            case "se":
                 return $this->config->clientid_invoice_se;
                break;
            case "no":
                 return $this->config->clientid_invoice_no;
                break;
            case "fi":
                 return $this->config->clientid_invoice_fi;
                break;
            case "dk":
                 return $this->config->clientid_invoice_dk;
                break;
            case "de":
                 return $this->config->clientid_invoice_de;
                break;
            case "nl":
                 return $this->config->clientid_invoice_nl;
                break;
            default:
                break;
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
        $card = $this->config->merchantid_card_test;
        if($card == ""){
            return $this->config->merchantid_directbank_test;
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
          $country = strtolower($country);
        $lowertype = strtolower($type);
        if($lowertype == "paymentplan"){
             switch ($country) {
                  case "se":
                      return $this->config->password_paymentplan_se;
                      break;
                  case "no":
                      return $this->config->password_paymentplan_no;
                      break;
                  case "fi":
                      return $this->config->password_paymentplan_fi;
                      break;
                  case "dk":
                      return $this->config->password_paymentplan_dk;
                      break;
                  case "de":
                      return $this->config->password_paymentplan_de;
                      break;
                  case "nl":
                      return $this->config->password_paymentplan_nl;
                      break;
                  default:
                      break;
              }

        }
        switch ($country) {
                  case "se":
                      return $this->config->password_invoice_se;
                      break;
                  case "no":
                      return $this->config->password_invoice_no;
                      break;
                  case "fi":
                      return $this->config->password_invoice_fi;
                      break;
                  case "dk":
                      return $this->config->password_invoice_dk;
                      break;
                  case "de":
                      return $this->config->password_invoice_de;
                      break;
                  case "nl":
                      return $this->config->password_invoice_nl;
                      break;
                  default:
                      break;
              }
    }
    /**
     *
     * @param type $type
     * @param type $country
     * @return type
     */
    public function getSecret($type, $country) {
       $card = $this->config->secret_card_test;
        if($card == ""){
            return $this->config->secret_directbank_test;
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
         $country = strtolower($country);
        $lowertype = strtolower($type);
          if($lowertype == "paymentplan"){
              switch ($country) {
                  case "se":
                      return $this->config->username_paymentplan_se;
                      break;
                  case "no":
                      return $this->config->username_paymentplan_no;
                      break;
                  case "fi":
                      return $this->config->username_paymentplan_fi;
                      break;
                  case "dk":
                      return $this->config->username_paymentplan_dk;
                      break;
                  case "de":
                      return $this->config->username_paymentplan_de;
                      break;
                  case "nl":
                      return $this->config->username_paymentplan_nl;
                      break;
                  default:
                      break;
              }

        }
        switch ($country) {
                  case "se":
                      return $this->config->username_invoice_se;
                      break;
                  case "no":
                      return $this->config->username_invoice_no;
                      break;
                  case "fi":
                      return $this->config->username_invoice_fi;
                      break;
                  case "dk":
                      return $this->config->username_invoice_dk;
                      break;
                  case "de":
                      return $this->config->username_invoice_de;
                      break;
                  case "nl":
                      return $this->config->username_invoice_nl;
                      break;
                  default:
                      break;
              }
    }
}