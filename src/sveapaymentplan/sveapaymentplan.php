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
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}
 if (!class_exists('Includes.php')) {
    require (  JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'svealib' . DS . 'integrationlib'. DS . 'Includes.php');
}
if(!class_exists('VirtueMartModelOrders')){
    require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
}
if (!class_exists ('SveaHelper')) {
    require ( JPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'svealib' .DS . 'sveahelper.php');
}

class plgVmPaymentSveapaymentplan extends vmPSPlugin {

	function __construct(& $subject, $config) {

		parent::__construct($subject, $config);
		// 		vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable   = true;
		$this->tableFields = array_keys($this->getTableSQLFields());

		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * @author Val?rie Isaksen
	 */
	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment Svea Paymentplan Table');
	}

	/**
	 * Fields to create the payment table
	 * @return string SQL Fileds
	 */
	function getTableSQLFields() {
		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)',
                        'svea_order_id'                 => 'int(1) UNSIGNED',
                        'svea_approved_amount'          => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
                        'svea_expiration_date'          => 'datetime',
		);

		return $SQLfields;
	}

	/**
	 *
	 *
	 * @author Val?rie Isaksen
	 */
	function plgVmConfirmedOrder($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		$lang     = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);
		$vendorId = 0;
		$this->getPaymentCurrency($method, true);

                $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3        = $db->loadResult();
		$paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd                     = CurrencyDisplay::getInstance($cart->pricesCurrency);

  //Svea Create order
            try {
                $sveaConfig = $method->testmode_paymentplan_se == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
                $svea = WebPay::createOrder($sveaConfig);
           } catch (Exception $e) {
                $html = SveaHelper::errorResponse('',$e->getMessage ());
                vmError ($e->getMessage (), $e->getMessage ());
                return NULL;
           }
             //order items
            $svea = SveaHelper::formatOrderRows($svea, $order,$method->payment_currency);
             //add shipping
            $svea = SveaHelper::formatShippingRows($svea,$order,$method->payment_currency);
             //add coupons TODO: kolla checkbetween to rates i opencart
            $svea = SveaHelper::formatCoupon($svea,$order,$method->payment_currency);
            $countryId = $order['details']['BT']->virtuemart_country_id;
            if(isset($countryId) == FALSE){
                return;
            }
            $countryCode = shopFunctions::getCountryByID($countryId,'country_2_code');

             //add customer
             $session = JFactory::getSession();
             $svea = SveaHelper::formatCustomer($svea,$order,$countryCode);
           try {
                $svea = $svea
                      ->setCountryCode($countryCode)
                      ->setCurrency($currency_code_3)
                      ->setClientOrderNumber($order['details']['BT']->virtuemart_order_id)
                      ->setOrderDate(date('c'))
                      ->usePaymentPlanPayment($session->get('svea_campaigncode'))
                        ->doRequest();
           } catch (Exception $e) {
                $html = SveaHelper::errorResponse('',$e->getMessage ());
                vmError ($e->getMessage (), $e->getMessage ());
                return NULL;
           }

            if ($svea->accepted == 1) {
                //override billing address
                SveaHelper::updateBTAddress($svea,$order['details']['BT']->virtuemart_order_id);

		$dbValues['payment_name']                = $this->renderPluginName($method) . '<br />' . $method->payment_info;
		$dbValues['order_number']                = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction']        = $method->cost_per_transaction;
		$dbValues['payment_currency']            = $currency_code_3;
		$dbValues['payment_order_total']         = $totalInPaymentCurrency;
		$dbValues['tax_id']                      = $method->tax_id;
                $dbValues['svea_order_id']               = $svea->sveaOrderId;
                $dbValues['svea_approved_amount']        = $svea->amount;
                $dbValues['svea_expiration_date']        = $svea->expirationDate;

		$this->storePSPluginInternalData($dbValues);
                  //Print html on thank you page. Will also say "thank you for your order!"
                $html = '<div class="vmorder-done">' . "\n";
		$html .= $this->getHtmlRow ('STANDARD_PAYMENT_INFO', $dbValues['payment_name'], 'class="vmorder-done-payinfo"');
		if (!empty($payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$payment_info = JText::_ ($method->payment_info);
			} else {
				$payment_info = $method->payment_info;
			}
			$html .= $this->getHtmlRow ('STANDARD_PAYMENTINFO', $payment_info, 'class="vmorder-done-payinfo"');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		$html .= $this->getHtmlRow ('STANDARD_ORDER_NUMBER', $order['details']['BT']->order_number, "vmorder-done-nr");
		$html .= $this->getHtmlRow ('STANDARD_AMOUNT', $currency->priceDisplay ($order['details']['BT']->order_total), "vmorder-done-amount");
		//$html .= $this->getHtmlRow('STANDARD_INFO', $method->payment_info);
		//$html .= $this->getHtmlRow('STANDARD_AMOUNT', $totalInPaymentCurrency.' '.$currency_code_3);
		$html .= '</div>' . "\n";
                $modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = SveaHelper::SVEA_STATUS_CONFIRMED;

		$order['comments'] = ' Order created at Svea. ';

                  if($method->autodeliver == TRUE){
                    try {
                        $deliverObj = WebPay::deliverOrder($sveaConfig)
                                            ->setCountryCode($countryCode)
                                            ->setOrderId($svea->sveaOrderId)
                                            ->deliverPaymentPlanOrder()
                                                ->doRequest();
                    } catch (Exception $e) {
                        $html = SveaHelper::errorResponse('',$e->getMessage ());
                        vmError ($e->getMessage (), $e->getMessage ());
                        return NULL;
                    }

                    if($deliverObj->accepted == 1){
                        $order['comments'] = 'Order delivered at Svea';
                        $order['order_status'] = SveaHelper::SVEA_STATUS_SHIPPED;

                    }

                }

                $order['customer_notified'] = 1;
                $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
            }  else {
                $order['customer_notified'] = 0;
                $order['order_status'] = SveaHelper::SVEA_STATUS_CANCELLED;
                $order['comments'] = "Translate me Svea error: [". $svea->resultcode . " ] ".$svea->errormessage;
                $html = SveaHelper::errorResponse($svea->resultcode,$svea->errormessage);

            }

            //We delete the old stuff
            $cart->emptyCart ();
            JRequest::setVar ('html', $html);
            return TRUE;

	}

	/**
         * TODO: CHECK WHAT IT DOES
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
		if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
			return NULL;
		}

		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		$html .= '</table>' . "\n";
		return $html;
	}

	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		if (preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions($cart, $method, $cart_prices) {
		$this->convert($method);
		// 		$params = new JParameter($payment->payment_params);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount      = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return false;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address                          = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			return true;
		}

		return false;
	}

	function convert($method) {

		$method->min_amount = (float)$method->min_amount;
		$method->max_amount = (float)$method->max_amount;
	}

	/*
* We must reimplement this triggers for joomla 1.7
*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Val?rie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Val?rie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart,  &$msg) {
            $request = JRequest::get();
            $session = JFactory::getSession();
            foreach ($request as $key => $value) {
                $sveaName = substr($key, 0,4);
                if($sveaName == "svea"){
                    $session->set($key, $value);
                }
            }

            return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

        /**
         * loads Svea getPaymentplanParams html
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
        public function displayListFE (VirtueMartCart $cart, $selected = 0, &$htmlIn) {
            //from parent. keep
            	if ($this->getPluginMethods ($cart->vendorId) === 0) {
			if (empty($this->_name)) {
				vmAdminInfo ('displayListFE cartVendorId=' . $cart->vendorId);
				$app = JFactory::getApplication ();
				$app->enqueueMessage (JText::_ ('COM_VIRTUEMART_CART_NO_' . strtoupper ($this->_psType)));
				return FALSE;
			} else {
				return FALSE;
			}
		}
                //keep end
		$html = array();
		$method_name = $this->_psType . '_name';
		foreach ($this->methods as $method) {
			if ($this->checkConditions ($cart, $method, $cart->pricesUnformatted)) {
				$methodSalesPrice = $this->calculateSalesPrice ($cart, $method, $cart->pricesUnformatted);
				$method->$method_name = $this->renderPluginName ($method);
				$html [] = $this->getPluginHtml ($method, $selected, $methodSalesPrice);
                                //include svea stuff on editpayment page
                                $countryId = $cart->BT['virtuemart_country_id'];
                                if(isset($countryId) == FALSE){
                                    return ;
                                }
                                 $countryCode = shopFunctions::getCountryByID($countryId,'country_2_code');
                                $html[] = $this->getSveaGetPaymentplanHtml($method->virtuemart_paymentmethod_id,$countryCode,$cart->pricesUnformatted['basePriceWithTax']);
                                //svea stuff end

			}
		}
		if (!empty($html)) {
			$htmlIn[] = $html;
			return TRUE;
		}

		return FALSE;

        }

	/**
    * plgVmonSelectedCalculatePricePayment
    * Calculate the price (value, tax_id) of the selected method
    * It is called by the calculator
    * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
    * @author Valerie Isaksen
    * @cart: VirtueMartCart the current cart
    * @cart_prices: array the new cart prices
    * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
    *
    *
    */

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 *
	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}

	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 *
	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}

	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 *
	public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 *
	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int $virtuemart_order_id : payment  order id
	 * @param char $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 *
	public function plgVmOnPaymentNotification() {
	return null;
	}

	/**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	function plgVmOnPaymentResponseReceived(&$virtuemart_order_id, &$html) {

            return NULL;

	}

            /**
     * will catch ajaxcall
     * Svea getAddress->doRequest()
    * Svea getParams->doRequest()
     * @param type $type
     * @param type $name
     * @param type $render
     */

    public function plgVmOnSelfCallFE($type,$name,&$render) {
        if (!($method = $this->getVmPluginMethod(JRequest::getVar('sveaid')))) {
			return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
                return false;
        }
        $sveaconfig = new SveaVmConfigurationProviderTest($method);
        $returnArray = array();
        //Get address request
        if(JRequest::getVar('type') == 'getAddress'){
            try {
              $svea = WebPay::getAddresses($sveaconfig);
              $svea = $svea->setOrderTypePaymentPlan()
                        ->setCountryCode(JRequest::getVar('countrycode'))
                        ->setIndividual(JRequest::getVar('svea_ssn'))
                            ->doRequest();
            } catch (Exception $e) {
                vmError ($e->getMessage (), $e->getMessage ());
                    return NULL;
            }
            if($svea->accepted == 0){
                $returnArray = array("svea_error" => $svea->errormessage);
                vmError ('Svea Error: '.$svea->errormessage, 'Svea Error: '.$svea->errormessage);
            }  else {
                 foreach ($svea->customerIdentity as $ci){
                    $name = ($ci->fullName) ? $ci->fullName : $ci->legalName;

                    $returnArray =  array("fullName"  => $name,
                                    "street"    => $ci->street,
                                    "address_2" => $ci->coAddress,
                                    "zipCode"  => $ci->zipCode,
                                    "locality"  => $ci->locality,
                                    "addressSelector" => $ci->addressSelector
                            );
                 }
            }

        }elseif(JRequest::getVar('type') == 'getParams'){
            $svea_params = WebPay::getPaymentPlanParams($sveaconfig);
            try {
                 $svea_params = $svea_params->setCountryCode(JRequest::getVar('countrycode'))
                    ->doRequest();
            } catch (Exception $e) {
                 vmError ($e->getMessage (), $e->getMessage ());
                    return NULL;
            }
            if (isset($svea_params->errormessage)) {
                $returnArray = array("svea_error" => "Svea error: " .$svea_params->errormessage);
            } else {
                $formattedPrice = round(JRequest::getVar('sveacarttotal'), 2);//TODO: check if needs to format currency
                $campaigns = WebPay::paymentPlanPricePerMonth($formattedPrice, $svea_params);
                if(sizeof($campaigns->values) > 0){
                    foreach ($campaigns->values as $cc){
                    $returnArray[] = array("campaignCode" => $cc['campaignCode'],
                        "description" => $cc['description'],
                        "price_per_month" => (string) $cc['pricePerMonth'] . " " . "get valuta" . "/" . "translateme month");
                    }
                }else{
                     $returnArray = array("svea_error" => "Svea error: Amount is to low-translate me");
                }

            }

        }
        echo json_encode($returnArray);
        jexit();
    }

    /**
     * TODO: check if company
     * @param type $param0
     * @param type $countryCode
     * @return string
     */
    public function getSveaGetPaymentplanHtml($paymentId,$countryCode,$cartTotal) {
         $sveaUrlAjax = juri::root () . '/index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=sveapaymentplan';
         $inputFields = '';
        $getAddressButton = '';
        //NORDIC fields
        if($countryCode == "SE" || $countryCode == "DK" || $countryCode == "NO" || $countryCode == "FI"){
             $inputFields .=
                        '
                        <fieldset id="svea_ssn_div_pp>
                            <label for="svea_ssn_pp">Social security number</label>
                            <input type="text" id="svea_ssn_pp" name="svea_ssn_pp" class="required" /><span style="color: red; "> * </span>
                        </fieldset>';
        //EU fields
        }elseif($countryCode == "NL" || $countryCode == "DE"){

             //Days, to 31
            $days = "";
            $zero = "";
            for($d = 1; $d <= 31; $d++){

                $val = $d;
                if($d < 10)
                    $val = "$d";

                $days .= "<option value='$val'>$d</option>";
            }
            $birthDay = "<select name='svea_birthday' id='birthDay_pp'>$days</select>";

            //Months to 12
            $months = "";
            for($m = 1; $m <= 12; $m++){
                $val = $m;
                if($m < 10)
                    $val = "$m";

                $months .= "<option value='$val'>$m</option>";
            }
            $birthMonth = "<select name='svea_birthmonth' id='birthMonth_pp'>$months</select>";

            //Years from 1913 to 1996
            $years = '';
            for($y = 1913; $y <= 1996; $y++){
                $years .= "<option value='$y'>$y</option>";
            }
            $birthYear = "<select name='svea_birth_year' id='birthYear_pp'>$years</select>";

            $inputFields = $birthDay . $birthMonth . $birthYear;
              if($countryCode == "NL"){
                $inputFields .= ' Initials: <input type="text" id="svea_initials_pp" name="initials" class="required" /><span style="color: red; "> * </span>';
            }
        }
        if($countryCode == "SE" || $countryCode == "DK") {
            $getAddressButton =
                        ' <fieldset>
                            <input type="button" id="svea_getaddress_submit_pp" value="Get Address" />
                        </fieldset>';
        }
        //box for form
        $html = '<fieldset id="svea_getaddress_pp">
             <input type="hidden" id="paymenttypesvea_pp" value="'. $paymentId . '" />
             <input type="hidden" id="carttotal" value="'. $cartTotal . '" />'
                 .$inputFields.
                 '
                 <div id="svea_getaddress_error_pp" style="color: red; "></div>'
                .$getAddressButton.
                ' <div id="svea_address_div_pp"></div>
                <ul id="svea_params_div" style="list-style-type: none;"></ul>
             </fieldset>';
      //start skript and set vars
        $html .= "<script type='text/javascript'>
                    var countrycode_pp = '$countryCode';
                    var url_pp = '$sveaUrlAjax';
                    var checked_pp = jQuery('input[name=\'virtuemart_paymentmethod_id\']:checked').val();
                    var sveacarttotal_pp = jQuery('#carttotal').val();
                    var sveaid_pp = jQuery('#paymenttypesvea_pp').val();";
        //do ajax to get params
        $html .= " jQuery.ajax({
                            type: 'GET',
                            data: {
                                sveaid: sveaid_pp,
                                sveacarttotal: sveacarttotal_pp,
                                type: 'getParams',
                                countrycode: countrycode_pp
                            },
                            url: url_pp,
                            success: function(data){
                                var json = JSON.parse(data);
                                 if (json.svea_error ){
                                    jQuery('#svea_getaddress_error_pp').empty().append('<br>'+json.svea_error).show();
                                }else{
                                    jQuery('#svea_params_div').hide();
                                    var count = 0;
                                    var checkedCampaign = '';
                                     jQuery.each(json,function(key,value){

                                        if(count == 0){
                                            checkedCampaign = 'checked';
                                        }

                                       jQuery('#svea_params_div').append('<li><input type=\"radio\" name=\"svea_campaigncode\" value=\"'+value.campaignCode+'\" '+checkedCampaign+'>&nbsp<strong>'+value.description+'</strong> ('+value.price_per_month+')</li>');
                                       count ++;
                                       checkedCampaign = '';
                                     });
                                     jQuery('#svea_params_div').show();

                                }

                            }
                        });";
       //Document ready start
        $html .= " jQuery(document).ready(function ($){
                         jQuery('#svea_ssn_pp').removeClass('invalid');";

         //hide show box
        $html .= "
                        if(checked_pp != sveaid_pp){
                            jQuery('#svea_getaddress_pp').hide();
                        }else{
                            jQuery('#svea_getaddress_pp').show();
                        }
                    ";
        //toggle display form
        $html .=        '
                        jQuery("input[name=\'virtuemart_paymentmethod_id\']").change(function(){
                            checked_pp = jQuery("input[name=\'virtuemart_paymentmethod_id\']:checked").val();
                            if(checked_pp == sveaid_pp){
                                  jQuery("#svea_getaddress_pp").show();
                            }else{
                                jQuery("#svea_getaddress_pp").hide();
                            }
                            });';

        //ajax to getAddress
        $html .= "jQuery('#svea_getaddress_submit_pp').click(function (){
                         jQuery('#svea_ssn_pp').removeClass('invalid');
                        var svea_ssn_pp = jQuery('#svea_ssn_pp').val();
                            if(svea_ssn_pp == ''){
                                jQuery('#svea_ssn_pp').addClass('invalid');
                                jQuery('#svea_getaddress_error_pp').empty().append('Svea Error: * required').show();
                            }else{
                                jQuery.ajax({
                                    type: 'GET',
                                    data: {
                                        sveaid: sveaid_pp,
                                        type: 'getAddress',
                                        svea_ssn: svea_ssn_pp,
                                        countrycode: countrycode_pp
                                    },
                                    url: url_pp,
                                    success: function(data){
                                        var json = JSON.parse(data);
                                         jQuery('#svea_getaddress_error_pp').hide();
                                         if (json.svea_error){
                                             jQuery('#svea_getaddress_error_pp').empty().append(' Svea Error: <br>'+json.svea_error).show();
                                        }else{
                                            jQuery('#svea_address_div_pp').hide();
                                            jQuery('#sveaAddressDiv_pp').remove();
                                            jQuery('#svea_address_div_pp').append('<div id=\"sveaAddressDiv\"><strong>'+json.fullName+'</strong><br> '+json.street+' <br>'+json.zipCode+' '+json.locality+'</div>');


                                        }
                                        jQuery('#svea_address_div_pp').show();
                                    }
                                });

                            }

                        });";
        //append form to parent form in Vm
        $html .=        "jQuery('#svea_form_pp').parents('form').submit( function(){
                            var action = jQuery('#svea_form_pp').parents('form').attr('action');
                            var form = jQuery('<form id=\"svea_form_pp\"></form>');
                            form.attr('method', 'post');
                            form.attr('action', action);
                            var sveaform = jQuery(form).append('form#svea_form_pp');
                            jQuery(document.body).append(sveaform);
                            sveaform.submit();
                            return false;

                        });";
        //Document ready end and script end
        $html .= " });
                </script>";


        return $html;
    }

}

// No closing tag
