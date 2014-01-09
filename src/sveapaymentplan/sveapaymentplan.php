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
                $order['order_status'] = $method->status_pending;
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
                $sveaConfig = $method->testmode_paymentplan == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
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
             $svea = SveaHelper::formatCustomer($svea,$order,$countryCode,$method->virtuemart_paymentmethod_id);
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
		$dbValues['payment_currency']            = $currency_code_3;
		$dbValues['payment_order_total']         = $totalInPaymentCurrency;
                $dbValues['svea_order_id']               = $svea->sveaOrderId;
                $dbValues['svea_approved_amount']        = $svea->amount;
                $dbValues['svea_expiration_date']        = $svea->expirationDate;

		$this->storePSPluginInternalData($dbValues);
                  //Print html on thank you page. Will also say "thank you for your order!"
                 $logoImg = JURI::root(TRUE) . '/images/stories/virtuemart/payment/sveawebpay.png';
                $html =  '<img src="'.$logoImg.'" /><br /><br />';
                $html .= '<div class="vmorder-done">' . "\n";
                $html .= '<div class="vmorder-done-payinfo">'.JText::sprintf('VMPAYMENT_SVEA_PAYMENTPLAN').'</div>';
		if (!empty($payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$payment_info = JText::_ ($method->payment_info);
			} else {
				$payment_info = $method->payment_info;
			}
			$html .= $this->getHtmlRow (JText::sprintf('VMPAYMENT_SVEA_PAYMENTINFO'), $payment_info, 'class="vmorder-done-payinfo"');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		$html .= '<div class="vmorder-done-nr">'.JText::sprintf('VMPAYMENT_SVEA_ORDERNUMBER').': '. $order['details']['BT']->order_number."</div>";
		$html .= '<div class="vmorder-done-amount">'.JText::sprintf('VMPAYMENT_SVEA_ORDER_TOTAL').': '. $currency->priceDisplay($order['details']['BT']->order_total).'</div>';
           	$html .= '</div>' . "\n";
                $modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = $method->status_success;

		$order['comments'] = 'Order created at Svea. Svea orderId: '.$svea->sveaOrderId;

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
                        $order['comments'] = 'Order delivered at Svea. Svea orderId: '.$svea->sveaOrderId;
                        $order['order_status'] = $method->status_shipped;

                    }

                }

                $order['customer_notified'] = 1;
                $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
                $session->destroy();
            }  else {
                $order['customer_notified'] = 0;
                $order['order_status'] = $method->status_denied;
                 $html = SveaHelper::errorResponse($svea->resultcode,$svea->errormessage);
                $order['comments'] = $html;


            }

            //We delete the old stuff
            $cart->emptyCart ();
            JRequest::setVar ('html', $html);
            return TRUE;

	}

	/**
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
                $html .= '<tr class="row1"><td>' . JText::sprintf('VMPAYMENT_SVEA_PAYMENTMETHOD').'</td><td align="left">'. $paymentTable->payment_name.'</td></tr>';
                $html .= '<tr class="row2"><td>' . JText::sprintf('VMPAYMENT_SVEA_INVOICEFEE').'</td><td align="left">'. $paymentTable->cost_per_transaction.'</td></tr>';
                $html .= '<tr class="row2"><td>Approved amount</td><td align="left">'. $paymentTable->svea_approved_amount.'</td></tr>';
                $html .= '<tr class="row2"><td>Expiration date</td><td align="left">'. $paymentTable->svea_expiration_date.'</td></tr>';
                $html .= '<tr class="row3"><td>Svea orderId</td><td align="left">'. $paymentTable->svea_order_id.'</td></tr>';

		$html .= '</table>' . "\n";
		return $html;
	}

        /**
         * getCosts() will return the invoice fee for Svea Part payment payment method
         *
         * @param VirtueMartCart $cart
         * @param type $method
         * @param type $cart_prices
         * @return type cost_per_transaction -- as defined by part payment config cost_per_transaction setting (should be given ex vat)
         */
	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		return ($method->cost_per_transaction);
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
                $returnValue = FALSE;
                // check valid country
                $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);  // use billing address unless shipping defined

                if( empty($address) ) {  // i.e. user not logged in --
                    $returnValue = VmConfig::get('oncheckout_only_registered',0) ? false : true; // return true iff we allow non-registered users to checkout
                    //$returnValue = false;       // need billto address for this payment method
                }
                else    // we show payment method if registered customer billto address is in configured list of payment method countries
                {

                    $returnValue = $this->addressInAcceptedCountry( $address, $method->countries );
                }
                //Check min and max amount. Copied from standard payment
                // We come from the calculator, the $cart->pricesUnformatted does not exist yet
		//$amount = $cart->pricesUnformatted['billTotal'];
		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			$returnValue = FALSE;
		}
                return $returnValue;
	}

        /**
         * Returns true if address is in the list of accepted countries, or if the countries list is empty (i.e. we accept all countries)
         *
         * @param array $address -- virtuemart address array
         * @param array $countries -- virtuemart list of countries from payment method config
         * @return boolean
         */
        function addressInAcceptedCountry( $address, $countries )
        {
            // sanity check on address
            if (!is_array($address)) {
                $address = array();
                $address['virtuemart_country_id'] = 0;
            }
            if (!isset($address['virtuemart_country_id'])) {
                $address['virtuemart_country_id'] = 0;
            }

            // sanity check on countries
            $countriesArray = array();
            if( !empty($countries) ) {
                if (!is_array($countries)) {
                        $countriesArray[0] = $countries;
                } else {
                        $countriesArray = $countries;
                }
            }

            return (count($countriesArray) == 0 || in_array($address['virtuemart_country_id'], $countriesArray)); // ==0 means all countries
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
                                if(isset( $cart->BT['virtuemart_country_id'])){
                                      $countryId =  $cart->BT['virtuemart_country_id'];
                                }elseif (sizeof($method->countries)== 1) {
                                   $countryId = $method->countries[0];
                                }  else {//empty or several countries configured
                                    return FALSE;//do not know what country, there for don´t know what fields to show.
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
         * OnCheckAutomaticSelectedPayment
         * @override
         * Part payment needs to collect credentials for the credit check. This is done in checkout step 3, payment method selection.
         * By returning 0 even if we're the sole payment method, the vm cart will show the link to checkout step 3, see plgVmOnCheckAutomaticSelectedPayment.
         * @return int
         */
	function onCheckAutomaticSelected (VirtueMartCart $cart, array $cart_prices = array(), &$methodCounter = 0) {

		$virtuemart_pluginmethod_id = 0;

		$nbMethod = $this->getSelectable ($cart, $virtuemart_pluginmethod_id, $cart_prices);
		$methodCounter += $nbMethod;

		if ($nbMethod == NULL) {
			return NULL;
		} else {
			if ($nbMethod == 1) {
				return 0; //parent: $virtuemart_pluginmethod_id;
			} else {
				return 0;
			}
		}
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
                $formattedPrice = JRequest::getVar('sveacarttotal');//TODO: check if needs to format currency
                $campaigns = WebPay::paymentPlanPricePerMonth($formattedPrice, $svea_params);
                 if (!class_exists ('CurrencyDisplay')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		}
                $CurrencyCode = SveaHelper::getCurrencyCodeByCountry(JRequest::getVar('countrycode'));
                $currencyId = ShopFunctions::getCurrencyIDByName($CurrencyCode);
                $currency = CurrencyDisplay::getInstance ();
                if(sizeof($campaigns->values) > 0){
                    foreach ($campaigns->values as $cc){
                    $returnArray[] = array("campaignCode" => $cc['campaignCode'],
                                            "description" => $cc['description'],
                                            "price_per_month" => $currency->priceDisplay($cc['pricePerMonth'], $currencyId,1.0) . "/" . JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_MONTH")
                                        );

                    }
                }else{
                        $error = JText::sprintf("VMPAYMENT_SVEA_DD_NO_CAMPAIGN_ON_AMOUNT");
                     $returnArray = array("svea_error" => "$error");
                }

            }

        }

        echo json_encode($returnArray);
        jexit();
    }

    /**
     * TODO: check if company
     * Sveafix: Javasciript vars needs to have unique names, therfore we use arrays with unique keys instead.
     * @param type $param0
     * @param type $countryCode
     * @return string
     */
    public function getSveaGetPaymentplanHtml($paymentId,$countryCode,$cartTotal) {
        $session = JFactory::getSession();
        $sveaUrlAjax = juri::root () . '/index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=sveapaymentplan';
        $inputFields = '';
        $getAddressButton = '';
        //NORDIC fields
        if($countryCode == "SE" || $countryCode == "DK" || $countryCode == "NO" || $countryCode == "FI"){
             $inputFields .=
                        '
                        <fieldset id="svea_ssn_div_'.$paymentId.'">
                            <label for="svea_ssn_'.$paymentId.'">'.JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_SS_NO").'</label>
                            <input type="text" id="svea_ssn_'.$paymentId.'" name="svea_ssn_'.$paymentId.'" value="'.$session->get("svea_ssn_$paymentId").'" class="required" /><span style="color: red; "> * </span>
                        </fieldset>';
        //EU fields
        }elseif($countryCode == "NL" || $countryCode == "DE"){

             //Days, to 31
            $days = "";
            $zero = "";
            for($d = 1; $d <= 31; $d++){
                $selected = "";
                $val = $d;
                if($d < 10)
                    $val = "$d";

                if($session->get("svea_birthday_$paymentId") == $val)
                  $selected = "selected";

                      $days .= "<option value='$val' $selected>$d</option>";
            }
            $birthDay = "<select name='svea_birthday_".$paymentId."' id='birthDay_".$paymentId."'>$days</select>";

            //Months to 12
            $months = "";
            for($m = 1; $m <= 12; $m++){
                $selected = "";
                $val = $m;
                if($m < 10)
                    $val = "$m";

                if($session->get("svea_birthmonth_$paymentId") == $val)
                    $selected = "selected";

                $months .= "<option value='$val' $selected>$m</option>";
            }
            $birthMonth = "<select name='svea_birthmonth_".$paymentId."' id='birthMonth_".$paymentId."'>$months</select>";

            //Years from 1913 to date('Y')
            $years = '';
            for($y = 1913; $y <= date('Y'); $y++){
                 $selected = "";
                 if($session->get("svea_birthyear_$paymentId") == $y)
                    $selected = "selected";

                $years .= "<option value='$y' $selected>$y</option>";
            }
            $birthYear = "<select name='svea_birthyear_".$paymentId."' id='birthYear_".$paymentId."'>$years</select>";

            $inputFields =  '<label for="svea_birthdate_'.$paymentId.'">'.JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_BIRTHDATE").'</label>
                            <fieldset id="svea_birthdate_'.$paymentId.'">'.
                                $birthDay . $birthMonth . $birthYear
                            .'</fieldset>';
              if($countryCode == "NL"){
                $inputFields .=  JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_INITIALS").': <input type="text" id="svea_initials_'.$paymentId.'" value="'.$session->get('svea_initials').'" name="svea_initials_'.$paymentId.'" class="required" /><span style="color: red; "> * </span>';
            }
        }
        if($countryCode == "SE" || $countryCode == "DK") {
            $getAddressButton =
                        ' <fieldset>
                            <input type="button" id="svea_getaddress_submit_'.$paymentId.'" value="'.JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_GET_ADDRESS").'" />
                        </fieldset>';
        }
        //box for form
        $html = '<fieldset id="svea_getaddress_'.$paymentId.'">
             <input type="hidden" id="paymenttypesvea_'.$paymentId.'" value="'. $paymentId . '" />
             <input type="hidden" id="carttotal_'.$paymentId.'" value="'. $cartTotal . '" />'
                 .$inputFields.
                 '
                 <div id="svea_getaddress_error_'.$paymentId.'" style="color: red; "></div>'
                .$getAddressButton.
                ' <div id="svea_address_div_'.$paymentId.'"></div>
                <ul id="svea_params_div_'.$paymentId.'" style="list-style-type: none;"></ul>
             </fieldset>';
      //start skript and set vars

        $html .= "<script type='text/javascript'>
                    var countrycode_$paymentId = '$countryCode';
                    var url_$paymentId = '$sveaUrlAjax';
                    var checked_$paymentId = jQuery('input[name=\'virtuemart_paymentmethod_id\']:checked').val();
                    var sveacarttotal_$paymentId = jQuery('#carttotal_$paymentId').val();
                    var sveaid_$paymentId = jQuery('#paymenttypesvea_$paymentId').val();

                    ";
        //do ajax to get params
        $campaignSaved = $session->get("svea_campaigncode_$paymentId");
        $html .= " jQuery.ajax({
                            type: 'GET',
                            data: {
                                sveaid: sveaid_".$paymentId.",
                                sveacarttotal: sveacarttotal_".$paymentId.",
                                type: 'getParams',
                                countrycode: countrycode_".$paymentId."
                            },
                            url: url_".$paymentId.",
                            success: function(data){

                                var jsonP_$paymentId = JSON.parse(data);

                                 if (jsonP_$paymentId.svea_error ){
                                    jQuery('#svea_getaddress_error_$paymentId').empty().append('<br>'+jsonP_$paymentId.svea_error).show();
                                }else{
                                    jQuery('#svea_params_div_$paymentId').hide();
                                    var count = 0;
                                    var checkedCampaign = '';
                                     jQuery.each(jsonP_$paymentId,function(key,value){
                                        if('$campaignSaved' == value.campaignCode){
                                            checkedCampaign = 'checked'
                                        }else if(count == 0){
                                            checkedCampaign = 'checked';
                                        }
                                       jQuery('#svea_params_div_$paymentId').append('<li><input type=\"radio\" name=\"svea_campaigncode_$paymentId\" value=\"'+value.campaignCode+'\" '+checkedCampaign+'>&nbsp<strong>'+value.description+'</strong> ('+value.price_per_month+')</li>');
                                       count ++;
                                       checkedCampaign = '';
                                     });
                                     jQuery('#svea_params_div_$paymentId').show();

                                }

                            }
                        });";
       //Document ready start
        $html .= " jQuery(document).ready(function ($){
                         jQuery('#svea_ssn_$paymentId').removeClass('invalid');";

         //hide show box
        $html .= "
                        if(checked_".$paymentId." != sveaid_".$paymentId."){
                            jQuery('#svea_getaddress_$paymentId').hide();
                        }else{
                            jQuery('#svea_getaddress_$paymentId').show();
                        }
                    ";
        //toggle display form
        $html .=        '
                        jQuery("input[name=\'virtuemart_paymentmethod_id\']").change(function(){
                            checked_'.$paymentId.' = jQuery("input[name=\'virtuemart_paymentmethod_id\']:checked").val();
                            if(checked_'.$paymentId.' == sveaid_'.$paymentId.'){
                                  jQuery("#svea_getaddress_'.$paymentId.'").show();
                            }else{
                                jQuery("#svea_getaddress_'.$paymentId.'").hide();
                            }
                            });';

        //ajax to getAddress
        $html .= "jQuery('#svea_getaddress_submit_$paymentId').click(function (){
                         jQuery('#svea_ssn_$paymentId').removeClass('invalid');

                            var svea_ssn_$paymentId = jQuery('#svea_ssn_$paymentId').val();

                            if(svea_ssn_".$paymentId." == ''){
                                jQuery('#svea_ssn_$paymentId').addClass('invalid');
                                jQuery('#svea_getaddress_error_$paymentId').empty().append('Svea Error: * required').show();
                            }else{
                                jQuery.ajax({
                                    type: 'GET',
                                    data: {
                                        sveaid: sveaid_".$paymentId.",
                                        type: 'getAddress',
                                        svea_ssn: svea_ssn_".$paymentId.",
                                        countrycode:countrycode_".$paymentId."
                                    },
                                    url: url_".$paymentId.",
                                    success: function(data){
                                        var json_$paymentId = JSON.parse(data);
                                         jQuery('#svea_getaddress_error_$paymentId').hide();
                                         if (json_".$paymentId.".svea_error){
                                             jQuery('#svea_getaddress_error_$paymentId').empty().append(' Svea Error: <br>'+json_".$paymentId.".svea_error).show();
                                        }else{
                                            jQuery('#svea_address_div_$paymentId').hide();
                                            jQuery('#sveaAddressDiv_$paymentId').remove();
                                            jQuery('#svea_address_div_$paymentId').append('<div id=\"sveaAddressDiv_$paymentId\"><strong>'+json_".$paymentId.".fullName+'</strong><br> '+json_".$paymentId.".street+' <br>'+json_".$paymentId.".zipCode+' '+json_".$paymentId.".locality+'</div>');


                                        }
                                        jQuery('#svea_address_div_$paymentId').show();
                                    }
                                });

                            }

                        });";
        //append form to parent form in Vm
        $html .=        "jQuery('#svea_form_$paymentId').parents('form').submit( function(){

                            var svea_action_$paymentId = jQuery('#svea_form_$paymentId').parents('form').attr('action');

                            var form_$paymentId = jQuery('<form id=\"svea_form_$paymentId\"></form>');

                            form.attr('method', 'post');
                            form.attr('action', svea_action_$paymentId);

                            var sveaform_$paymentId = jQuery(form_$paymentId).append('form#svea_form_$paymentId');
                            jQuery(document.body).append(sveaform_$paymentId);
                            sveaform_$paymentId.submit();
                            return false;

                        });";
        //Document ready end and script end
        $html .= " });
                </script>";



        return $html;
    }

}

// No closing tag
