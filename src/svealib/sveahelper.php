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

    const SVEA_STATUS_PENDING = "P";
    const SVEA_STATUS_CONFIRMED_BY_SHOPPER = "U";
    const SVEA_STATUS_CONFIRMED = "C";
    const SVEA_STATUS_CANCELLED = "X";
    const SVEA_STATUS_REFUNDED = "R";
    const SVEA_STATUS_SHIPPED = "S";

    /**
     * TODO: språköversättning st
     * TODO: testa amountExVat convert
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
                    ->addOrderRow(Item::orderRow()
                    ->setQuantity(floatval($product->product_quantity))
                    ->setAmountExVat(floatval($paymentCurrency->convertCurrencyTo($currency,$product->product_item_price,FALSE)))
                    ->setVatPercent(intval($taxPercent))
                    ->setName($product->order_item_name)
                    ->setUnit("unit")
                    ->setArticleNumber($product->virtuemart_product_id)
                    ->setDescription($product->product_attribute)
            );
        }

        return $svea;
    }
//svea_ssn
    public static function formatCustomer($svea, $order,$countryCode) {
        $session = JFactory::getSession();
        $customerType = $session->get("svea_customertype");
        $pattern = "/^(?:\s)*([0-9]*[A-ZÄÅÆÖØÜßäåæöøüa-z]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]+)(?:\s*)([0-9]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]*[^\s])?(?:\s)*$/";
        preg_match($pattern, $order['details']['BT']->address_1, $addressArr);
        if( !array_key_exists( 2, $addressArr ) ) { $addressArr[2] = ""; } //fix for addresses w/o housenumber
        $ssn = ($session->get('svea_ssn')) ? $session->get('svea_ssn') : "";
         if ($customerType == "svea_invoice_customertype_company"){

            $item = Item::companyCustomer();

            $item = $item->setEmail($order['details']['BT']->email)
                         ->setCompanyName($order['details']['BT']->company)
                         ->setStreetAddress($addressArr[1],$addressArr[2])
                         ->setZipCode($order['details']['BT']->zip)
                         ->setLocality($order['details']['BT']->city)
                         ->setIpAddress($order['details']['BT']->ip_address)
                         ->setPhoneNumber(isset($order['details']['BT']->phone_1) ? $order['details']['BT']->phone_1 : $order['details']['BT']->phone_2);
            if($countryCode == "DE" || $countryCode == "NL"){
                $item = $item->setVatNumber($ssn );
            }else{
                $item = $item->setNationalIdNumber($ssn);
                $item = $item->setAddressSelector($session->get('svea_addresselector'));
            }
            $svea = $svea->addCustomerDetails($item);
        }else{
            $item = Item::individualCustomer();
            //send customer filled address to svea. Svea will use address from getAddress for the invoice.
            $item = $item->setNationalIdNumber($ssn)
                         ->setEmail($order['details']['BT']->email)
                         ->setName($order['details']['BT']->first_name,$order['details']['BT']->last_name)
                         ->setStreetAddress($addressArr[1],$addressArr[2])
                         ->setZipCode($order['details']['BT']->zip)
                         ->setLocality($order['details']['BT']->city)
                         ->setIpAddress($order['details']['BT']->ip_address)
                         ->setPhoneNumber(isset($order['details']['BT']->phone_1) ? $order['details']['BT']->phone_1 : $order['details']['BT']->phone_2);

            if($countryCode == "DE" || $countryCode == "NL"){
                $item = $item->setBirthDate($session->get('svea_birthyear'), $session->get('svea_birthmonth'), $session->get('svea_birthday'));
            }
            if($countryCode == "NL"){
                $item = $item->setInitials($session->get('svea_initials'));
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
                                ->setAmountIncVat(floatval($paymentCurrency->convertCurrencyTo($currency,$order['details']['BT']->coupon_discount,FALSE)))
                                ->setName($order['details']['BT']->coupon_code)
                            );
        }
        return $svea;
    }
    /**
     * TODO: translate shipping
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
                            ->setName("shipping translate me")
                            ->setVatPercent(intval($shippingTaxPercent))
                            ->setUnit("unit translate me")
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
                        ->setName('Svea fee translate me')
                        ->setAmountExVat(floatval($paymentCurrency->convertCurrencyTo($currency,$order['details']['BT']->order_payment,FALSE)))
                        ->setVatPercent(intval($paymentCurrency))
                        ->setUnit("unit translate me")
                    );
        return $svea;
    }


    public static function errorResponse($resultcode,$errormessage, $method) {

        $order['customer_notified'] = 1;
        $order['customer_notified'] = 0;
        $order['order_status'] = $method->status_denied;
        $order['comments'] = "Translate me Svea error: [". $resultcode . " ] ".$errormessage;
        $app = JFactory::getApplication ();
        $app->enqueueMessage ( "Translate me Svea error: [". $resultcode . " ] ".$errormessage);
        $app->redirect (JRoute::_ ('index.php?option=com_virtuemart&view=cart'));
        $html = '<div>' ."Translate me Svea error: [". $resultcode . " ] ".$errormessage. "\n";

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

    public static function buildAddressArray($svea) {
        $sveaAddresses = array();
        if ($svea->customerIdentity->customerType == 'Company'){
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
            isset($svea->customerIdentity->coAddress) ? $sveaAddresses["address_2"] = $svea->customerIdentity->coAddress : "";
            isset($svea->customerIdentity->locality) ? $sveaAddresses["city"] = $svea->customerIdentity->locality : "";
            isset($svea->customerIdentity->zipCode) ? $sveaAddresses["zip"] = $svea->customerIdentity->zipCode : "";
        }
        else {
            if( isset($svea->customerIdentity->firstName) &&  isset($svea->customerIdentity->lastName) ){
               $sveaAddresses["first_name"] = $svea->customerIdentity->firstName;
               $sveaAddresses["last_name"] = $svea->customerIdentity->lastName;
            }elseif( isset($svea->customerIdentity->firstName) == false ||
                    isset($svea->customerIdentity->lastName) == false &&
                    isset($svea->customerIdentity->fullName)){
                $sveaAddresses["first_name"] = $svea->customerIdentity->fullName;
                $sveaAddresses["last_name"] = "";
            }
            isset($svea->customerIdentity->firstName) ? $sveaAddresses["first_name"] = $svea->customerIdentity->firstName : "";
            isset($svea->customerIdentity->lastName) ? $sveaAddresses["last_name"] = $svea->customerIdentity->lastName : "";
            isset($svea->customerIdentity->street) ? $sveaAddresses["address_1"] = $svea->customerIdentity->street : "";
            isset($svea->customerIdentity->coAddress) ? $sveaAddresses["address_2"] = $svea->customerIdentity->coAddress : "";
            isset($svea->customerIdentity->locality) ? $sveaAddresses["city"] = $svea->customerIdentity->locality : "";
            isset($svea->customerIdentity->zipCode) ? $sveaAddresses["zip"] = $svea->customerIdentity->zipCode : "";

        }
        return $sveaAddresses;
    }


}