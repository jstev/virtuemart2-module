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
     * TODO: språköversättning st
     * TODO: kolla amountExVat convert i klarna handler
     * @param type $svea
     * @param type $products
     * @return type
     */
    static function formatOrderRows($svea,$order){
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
                    ->setAmountExVat(floatval($product->product_item_price))
                    ->setVatPercent(intval($taxPercent))
                    ->setName($product->order_item_name)
                    ->setUnit("unit")
                    ->setArticleNumber($product->virtuemart_product_id)
                    ->setDescription($product->product_attribute)
            );
        }

        return $svea;
    }

    public static function formatCustomer($svea, $order,$countryCode) {
        $session = JFactory::getSession();
        $customerType = $session->get("svea_customertype");
        $pattern = "/^(?:\s)*([0-9]*[A-ZÄÅÆÖØÜßäåæöøüa-z]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]+)(?:\s*)([0-9]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]*[^\s])?(?:\s)*$/";
        preg_match($pattern, $order['details']['BT']->address_1, $addressArr);
        if( !array_key_exists( 2, $addressArr ) ) { $addressArr[2] = ""; } //fix for addresses w/o housenumber
         $ssn = ($session->get('svea_ssn')) ? $session->get('svea_ssn') : 0;
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
    public static function formatCoupon($svea, $order) {
        if (isset($order['details']['BT']->coupon_code)) {
            $svea = $svea->addDiscount(
                            WebPayItem::fixedDiscount()
                                ->setAmountIncVat(floatval($order['details']['BT']->coupon_discount))
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
    public static function formatShippingRows($svea, $order) {
        $shippingTaxPercent = 0;
        foreach ($order['calc_rules'] as $calc_rule) {
				if ($calc_rule->calc_kind== 'shipment') {
					$shippingTaxPercent=$calc_rule->calc_value;
					break;
				}
			}
        $svea = $svea->addFee(
                        WebPayItem::shippingFee()
                            ->setAmountExVat(floatval($order['details']['BT']->order_shipment))
                            ->setName("shipping")
                            ->setVatPercent(floatval($shippingTaxPercent))
                            ->setUnit("unit")
                       );
        return $svea;
    }
}