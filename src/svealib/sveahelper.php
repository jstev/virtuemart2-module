<?php
defined('_JEXEC') or die('Restricted access');
JHtml::_('behavior.mootools');
JHtml::_('behavior.framework', true);

/**
 * SveaWebPay payment module made from:
 * @version $Id: standard.php,v 1.4 2005/05/27 19:33:57 ei
 *
 * a special type of 'cash on delivey':
 * @author Max Milbers, Val?rie Isaksen
 * @version $Id: standard.php 5122 2011-12-18 22:24:49Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists('Includes.php')) {
    require (  JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'svealib' . DS . 'integrationlib'. DS . 'Includes.php');
}
class SveaHelper {

    /**
     * @param type $svea
     * @param type $products
     * @return type
     */
    static function formatOrderRows($svea,$order,$currency){
        $paymentCurrency = CurrencyDisplay::getInstance($currency);
        $taxPercent = 0;
        foreach ($order['items'] as $product) {
            //tax
            foreach ($order['calc_rules'] as $rule) {
                if($rule->virtuemart_order_item_id == $product->virtuemart_order_item_id && ($rule->calc_kind == 'VatTax' || $rule->calc_kind == 'Tax')){
                    $taxPercent = $rule->calc_value;
                }
            }
             $svea = $svea
                    ->addOrderRow(WebPayItem::orderRow()
                    ->setQuantity(floatval($product->product_quantity))
                    ->setAmountExVat(floatval($paymentCurrency->convertCurrencyTo($currency,$product->product_item_price,FALSE)))
                    ->setVatPercent(intval($taxPercent))
                    ->setName($product->order_item_name)
                    ->setUnit(JText::sprintf ("VMPAYMENT_SVEA_UNIT"))
                    ->setArticleNumber($product->virtuemart_product_id)
                    ->setDescription($product->product_attribute)
            );
        }
        return $svea;
    }

    public static function formatCustomer($svea, $order,$countryCode) {
        $session = JFactory::getSession();
        $customerType = $session->get("svea_customertype");
        if($countryCode == "DE" || $countryCode == "NL") // split streetname and housenumber
        {
            $addressArr = Svea\Helper::splitStreetAddress( $order['details']['BT']->address_1 );
        }
        else // just put entire streetaddress in streetname position
        {
            $addressArr[0] =  $order['details']['BT']->address_1;
            $addressArr[1] =  $order['details']['BT']->address_1;
            $addressArr[2] =  "";
        }

         if ($customerType == "svea_invoice_customertype_company"){

            $item = WebPayItem::companyCustomer();

            $item = $item->setEmail($order['details']['BT']->email)
                         ->setCompanyName($order['details']['BT']->company)
                         ->setStreetAddress($addressArr[1],$addressArr[2])
                         ->setZipCode($order['details']['BT']->zip)
                         ->setLocality($order['details']['BT']->city)
                         ->setIpAddress($order['details']['BT']->ip_address)
                         ->setPhoneNumber(isset($order['details']['BT']->phone_1) ? $order['details']['BT']->phone_1 : $order['details']['BT']->phone_2);
            if($countryCode == "DE" || $countryCode == "NL"){
                $item = $item->setVatNumber( $session->get("svea_ssn") );
            }else{
                $item = $item->setNationalIdNumber( $session->get("svea_ssn"));
                $item = $item->setAddressSelector($session->get("svea_addressselector"));
            }
            $svea = $svea->addCustomerDetails($item);
        }else{
            $item = WebPayItem::individualCustomer();
            //send customer filled address to svea. Svea will use address from getAddress for the invoice.
            $item = $item->setNationalIdNumber( $session->get("svea_ssn"))
                         ->setEmail($order['details']['BT']->email)
                         ->setName($order['details']['BT']->first_name,$order['details']['BT']->last_name)
                         ->setStreetAddress($addressArr[1],$addressArr[2])
                         ->setZipCode($order['details']['BT']->zip)
                         ->setLocality($order['details']['BT']->city)
                         ->setIpAddress($order['details']['BT']->ip_address)
                         ->setPhoneNumber(isset($order['details']['BT']->phone_1) ? $order['details']['BT']->phone_1 : $order['details']['BT']->phone_2);

            if($countryCode == "DE" || $countryCode == "NL"){
                $item = $item->setBirthDate($session->get("svea_birthyear"), $session->get("svea_birthmonth"), $session->get("svea_birthday"));
            }
            if($countryCode == "NL"){
                $item = $item->setInitials($session->get("svea_initials"));
            }
            $svea = $svea->addCustomerDetails($item);
        }

        return $svea;
    }
    /**
     * Coupon goes in as fixed discount inc vat for all
     * @param type $svea
     * @param type $order
     * @return $svea object
     */
    public static function formatCoupon($svea, $order,$currency) {
        $paymentCurrency = CurrencyDisplay::getInstance($currency);
        if (isset($order['details']['BT']->coupon_code)) {

            $svea = $svea->addDiscount(
                            WebPayItem::fixedDiscount()
                                ->setAmountIncVat( -1* floatval($paymentCurrency->convertCurrencyTo($currency,$order['details']['BT']->coupon_discount,FALSE)))
                                ->setName($order['details']['BT']->coupon_code)
                            );
        }
        return $svea;
    }
    /**
     * @param type $svea
     * @param type $order
     * @return type
     */
    public static function formatShippingRows($svea, $order,$currency) {
        $paymentCurrency = CurrencyDisplay::getInstance($currency);
        $shippingTaxPercent = 0;
        foreach ($order['calc_rules'] as $calc_rule) {
                if ($calc_rule->calc_kind== 'shipment') {
                        $shippingTaxPercent=$calc_rule->calc_value;
                        break;
                }
        }
        $svea = $svea->addFee(
                        WebPayItem::shippingFee()
                            ->setAmountExVat(floatval($paymentCurrency->convertCurrencyTo($currency,$order['details']['BT']->order_shipment,FALSE)))
                            ->setName(JText::sprintf("VMPAYMENT_SVEA_SHIPMENT_FEE"))
                            ->setVatPercent(intval($shippingTaxPercent))
                            ->setUnit(JText::sprintf ("VMPAYMENT_SVEA_UNIT"))
                       );
        return $svea;
    }

    public static function formatInvoiceFee($svea, $order, $currency) {
        $paymentCurrency = CurrencyDisplay::getInstance($currency);
        $fee_tax_percent = 0;
        foreach ($order['calc_rules'] as $calc_rule) {
                if ( $calc_rule->calc_kind== 'payment') {
                        $fee_tax_percent = $calc_rule->calc_value;
                        break;
                }
        }
        $svea = $svea->addFee(
                    WebPayItem::invoiceFee()
                        ->setName(JText::sprintf ("VMPAYMENT_SVEA_INVOICEFEE"))
                        ->setAmountExVat(floatval($paymentCurrency->convertCurrencyTo($currency,$order['details']['BT']->order_payment,FALSE)))
                        ->setVatPercent(intval($fee_tax_percent))
                        ->setUnit(JText::sprintf ("VMPAYMENT_SVEA_UNIT"))
                    );
        return $svea;
    }


    public static function errorResponse($resultcode,$errormessage) {
        $const = "VMPAYMENT_SVEA_ERROR_CODE_".(string)$resultcode;
       $errortranslate = JText::sprintf ($const);
       if(preg_match("/^VMPAYMENT_SVEA_ERROR_CODE/", $errortranslate))
                $errortranslate = JText::sprintf ("VMPAYMENT_SVEA_ERROR_CODE_DEFAULT",$resultcode).$errormessage;
        $app = JFactory::getApplication ();
        $app->enqueueMessage ( $errortranslate,'error');
        $app->redirect (JRoute::_ ('index.php?option=com_virtuemart&view=cart'));
        $html = '<div>'.$errormessage. "\n";

        return $html;
    }


    public static function updateBTAddress($svea,$orderId) {
        $data = SveaHelper::buildAddressArray($svea);
        $db = JFactory::getDBO();
        $query = "UPDATE `#__virtuemart_order_userinfos` SET ";
            $row = "";
            $counter = 0;
            foreach ($data as $key => $value){
                $counter == 0 ? $row = "" : $row .= ",";
                $row .= $db->escape($key)." = '".$db->escape($value)."'";
                $counter ++;
            }
            $query .= $row;
            $query .=  ' WHERE `virtuemart_order_id`="' . $orderId . '"
                        AND `address_type`= "BT"';
            $formattedQuery = $db->setQuery($query);
            $db->execute($formattedQuery);
           return TRUE;
    }

    public static function updateSTAddress($svea,$orderId) {
        $data = SveaHelper::buildAddressArray($svea);
        $db = JFactory::getDBO();
        $query = "UPDATE `#__virtuemart_order_userinfos` SET ";
            $row = "";
            $counter = 0;
            foreach ($data as $key => $value){
                $counter == 0 ? $row = "" : $row .= ",";
                $row .= $db->escape($key)." = '".$db->escape($value)."'";
                $counter ++;
            }
            $query .= $row;
            $query .=  ' WHERE `virtuemart_order_id`="' . $orderId . '"
                        AND `address_type`= "ST"';
            $formattedQuery = $db->setQuery($query);
            $db->execute($formattedQuery);
           return TRUE;
    }

    /**
     * Extracts an associative array of address fields from the svea response object.
     * Array keys match virtuemart order_userinfo address fields.
     *
     * @param Svea\CreateOrderEuResponse $svea
     * @return array
     */
    public static function buildAddressArray($svea) {
        $sveaAddresses = array();
        if ($svea->customerIdentity->customerType == 'Company') // Company customer
        {
            if( isset($svea->customerIdentity->firstName) &&  isset($svea->customerIdentity->lastName) ){
                $sveaAddresses["first_name"] = $svea->customerIdentity->firstName;
                $sveaAddresses["last_name"] = $svea->customerIdentity->lastName;
            }elseif( isset($svea->customerIdentity->firstName) == false ||
                    isset($svea->customerIdentity->lastName) == false &&
                    isset($svea->customerIdentity->fullName) ){
                $sveaAddresses["first_name"] = $svea->customerIdentity->fullName;
                $sveaAddresses["last_name"] = "";
            }
            isset($svea->customerIdentity->fullName) ? $sveaAddresses["company"] = $svea->customerIdentity->fullName : "";
            isset($svea->customerIdentity->street) ? $sveaAddresses["address_1"] = $svea->customerIdentity->street : "";
            isset($svea->customerIdentity->houseNumber) ? $sveaAddresses["house_no"] = $svea->customerIdentity->houseNumber : "";
            isset($svea->customerIdentity->coAddress) ? $sveaAddresses["address_2"] = $svea->customerIdentity->coAddress : "";
            isset($svea->customerIdentity->locality) ? $sveaAddresses["city"] = $svea->customerIdentity->locality : "";
            isset($svea->customerIdentity->zipCode) ? $sveaAddresses["zip"] = $svea->customerIdentity->zipCode : "";
        }
        else // private individual customer
        {
            if( isset($svea->customerIdentity->firstName) &&  isset($svea->customerIdentity->lastName) ){
               $sveaAddresses["first_name"] = $svea->customerIdentity->firstName;
               $sveaAddresses["last_name"] = $svea->customerIdentity->lastName;
            }
            elseif( isset($svea->customerIdentity->firstName) == false ||
                    isset($svea->customerIdentity->lastName) == false &&
                    isset($svea->customerIdentity->fullName)){
                $sveaAddresses["first_name"] = $svea->customerIdentity->fullName;
                $sveaAddresses["last_name"] = "";
            }
            isset($svea->customerIdentity->firstName) ? $sveaAddresses["first_name"] = $svea->customerIdentity->firstName : "";
            isset($svea->customerIdentity->lastName) ? $sveaAddresses["last_name"] = $svea->customerIdentity->lastName : "";
            isset($svea->customerIdentity->street) ? $sveaAddresses["address_1"] = $svea->customerIdentity->street : "";
            isset($svea->customerIdentity->houseNumber) ? $sveaAddresses["house_no"] = $svea->customerIdentity->houseNumber : "";
            isset($svea->customerIdentity->coAddress) ? $sveaAddresses["address_2"] = $svea->customerIdentity->coAddress : "";
            isset($svea->customerIdentity->locality) ? $sveaAddresses["city"] = $svea->customerIdentity->locality : "";
            isset($svea->customerIdentity->zipCode) ? $sveaAddresses["zip"] = $svea->customerIdentity->zipCode : "";
        }
        return $sveaAddresses;
    }
    /**
     * Like SveaLIb WebPay::paymentPlanPricePerMonth but without min and max limits
     * @param type $price
     * @param type $params
     * @return array
     */
    public static function paymentPlanPricePerMonth($price, $params) {
        $values = array();
        if (!empty($params)) {
            foreach ($params->campaignCodes as $key => $value) {

                    $pair = array();
                    $pair['pricePerMonth'] = $price * $value->monthlyAnnuityFactor + $value->notificationFee;
                    foreach ($value as $key => $val) {
                        if ($key == "campaignCode") {
                            $pair[$key] = $val;
                        }

                    if($key == "description"){
                        $pair[$key] = $val;
                    }

                    }
                    array_push($values, $pair);

            }
        }
        return $values;
    }

    public static function getCurrencyCodeByCountry($countryCode) {
        switch ($countryCode) {
            case "SE":
                return "SEK";
                break;
            case "NO":
                return "NOK";
                break;
            case "FI":
                return "EUR";
                break;
            case "DK":
                return "DKK";
                break;
            case "NL":
                return "EUR";
                break;
            case "DE":
                return "EUR";
                break;

            default:
                return "SEK";
                break;
        }
    }


}