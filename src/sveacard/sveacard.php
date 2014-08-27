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

class plgVmPaymentSveacard extends vmPSPlugin {

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
            //is there already a svea table for this payment?
            $q = 'SHOW TABLES LIKE "%sveacard%"';
            $db = JFactory::getDBO();
            $db->setQuery($q);
            $table_exists = $db->loadResult();
            //if there is add columns
            if(is_string($table_exists) && strpos($table_exists, 'sveacard') != FALSE){
                //get all columns and check for the new ones
                $q = 'SHOW COLUMNS FROM '.$table_exists;
                $db->setQuery($q);
                $columns = $db->loadAssocList();
               $cost_per_transaction = FALSE;
               $tax_id = FALSE;
               $svea_transaction_id = FALSE;
                foreach ($columns as $column) {
                    if(in_array( 'cost_per_transaction',$column)){
                        $cost_per_transaction = TRUE;
                    }  elseif (in_array( 'tax_id',$column)) {
                        $tax_id = TRUE;
                    }  elseif (in_array( 'svea_transaction_id',$column)) {
                        $svea_transaction_id = TRUE;
                    }
                }
                $q1 = $cost_per_transaction ? '' : ' ADD cost_per_transaction DECIMAL(10,2)';
                $q2 = $tax_id ? '' : 'ADD tax_id SMALLINT(1)';
                $q3 = $svea_transaction_id ? '' : 'ADD svea_transaction_id VARCHAR(64)';

                $query = "ALTER TABLE vm2_virtuemart_payment_plg_sveacard " .
                        $q1 . ($q1 != '' ? ',' : '') .
                        $q2 . ($q2 != '' ? ',' : '') .
                        $q3;
                $db->setQuery($query);
                $db->query();
            }
            return $this->createTableSQL('Payment Svea Card Table');
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
                        'cost_per_transaction'          => 'decimal(10,2)',
                        'tax_id'                        => 'smallint(1)',

			'svea_transaction_id'         => 'varchar(64)'
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
		$this->getPaymentCurrency($method);

                $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3        = $db->loadResult();
		$paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd                     = CurrencyDisplay::getInstance($cart->pricesCurrency);


            $dbValues['payment_name']                = $this->renderPluginName($method) . '<br />' . $method->payment_info;
            $dbValues['order_number']                = $order['details']['BT']->order_number;
            $dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
            $dbValues['payment_currency']            = $currency_code_3;
            $dbValues['payment_order_total']         = $totalInPaymentCurrency;

            $this->storePSPluginInternalData($dbValues);
            //Svea Create order
            try {
                $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
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

            $svea = SveaHelper::formatCoupon($svea,$order,$method->payment_currency);

            if(isset( $order['details']['BT']->virtuemart_country_id)){
                $countryId =  $order['details']['BT']->virtuemart_country_id;
            }elseif (sizeof($method->countries)== 1) {
               $countryId = $method->countries[0];
            }  else {//empty or several countries configured
                return FALSE;//do not know what country, there for don´t know what fields to show.
            }
            $countryCode = shopFunctions::getCountryByID($countryId,'country_2_code');
            $return_url = JROUTE::_ (JURI::root () .'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' .$order['details']['BT']->order_number .'&pm=' .$order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid'));
            $cancel_url = JROUTE::_ (JURI::root () .'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->virtuemart_order_id);
            //add customer
            $svea = SveaHelper::formatCustomer($svea,$order,$countryCode);
            try {
                 $form = $svea
                         ->setCountryCode("")
                         ->setCurrency($currency_code_3)
                         ->setClientOrderNumber($order['details']['BT']->virtuemart_order_id)
                         ->setOrderDate(date('c'))
                         ->usePaymentMethod(PaymentMethod::KORTCERT)
                             ->setReturnUrl($return_url)
                             //->setCancelUrl($cancel_url)//Not used by Certitrade cardpage
                             //->setCallbackUrl($cancel_url)//Not used by Certitrade cardpage
                                 ->getPaymentForm();

            } catch (Exception $e) {
                 $html = SveaHelper::errorResponse('',$e->getMessage ());
                 vmError ($e->getMessage (), $e->getMessage ());
                 return NULL;
            }
            $html = '<html><head><title>'.JText::sprintf("VMPAYMENT_SVEA_TEXT_REDIRECT").'</title></head><body><div style="margin: auto; text-align: center;"><br /><img src="'.JURI::root ().'/plugins/vmpayment/svealib/assets/images/sveaLoader.gif" /></div>';
            //form
            $fields = $form->htmlFormFieldsAsArray;
            $html .= $fields['form_start_tag'];
            $html .= $fields['input_merchantId'];
            $html .= $fields['input_message'];
            $html .= $fields['input_mac'];
            $html .= $fields['form_end_tag'];

            $html .= ' <script type="text/javascript">';
            $html .= ' document.paymentForm.submit();';
            $html .= ' </script></body></html>';

            $cart->_confirmDone = FALSE;
            $cart->_dataValidated = FALSE;

            $modelOrder = VmModel::getModel ('orders');

            $order['order_status'] = $method->status_denied;//sets to cancel until returned to shop, cause we don't want to save a cancelled order
            $order['customer_notified'] = 0;
            $order['comments'] = '';
            $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

            JRequest::setVar ('html', $html);

	}
        /**
         * Use on payPage cancel

        function plgVmOnUserPaymentCancel () {
                if (!class_exists ('VirtueMartModelOrders')) {
                        require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
                }
                print_r("cancellerad");

               // $this->myFile('plgVmOnUserPaymentCancel - PaysonInvoice');
        }
         *
         */
        /**
         *  public function myFile($arg, $arg2 = NULL) {
         $myFile = "testFile.txt";
         if($myFile == NULL){
                 $myFile = fopen($myFile, "w+");
         }
         $fh = fopen($myFile, 'a') or die("can't open file");
         fwrite($fh, "\r\n".date("Y-m-d H:i:s")." **");
         fwrite($fh, $arg.'**');
         fwrite($fh, $arg2);
         fclose($fh);
        }
         */

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
                $html .= '<tr class="row2"><td>Amount</td><td align="left">'. $paymentTable->payment_order_total.'</td></tr>';
                $html .= '<tr class="row2"><td>Transaction id</td><td align="left">'. $paymentTable->svea_transaction_id.'</td></tr>';

		$html .= '</table>' . "\n";
		return $html;
	}

	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		return 0;
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

                if( empty($cart->BT) )   // i.e. user not logged in -- card payment may still be ok to select (i.e. in case of non-physical products)
                {
                    $returnValue = VmConfig::get('oncheckout_only_registered',0) ? false : true; // return true iff we allow non-registered users to checkout
                }
                else    // we show payment method if registered customer billto address is in configured list of payment method countries
                {
                    $returnValue = $this->addressInAcceptedCountry( $cart->BT, $method->countries );
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
            //is there not a billto country, set to 0
            if (strlen($address['virtuemart_country_id'] == 0)) {
               $address['virtuemart_country_id'] = 0;
            }
            // sanity check on address
            if (!is_array($address)) {
                $address = array();
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
                                $html[] = $this->getSveaCardHtml($method->virtuemart_paymentmethod_id,$cart->pricesUnformatted['basePriceWithTax']);
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
	 * displays the logos of a VirtueMart plugin
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 * @param array $logo_list
	 * @return html with logos
	 */
	protected function displayLogos ($logo_list) {

		$img = "";

		if (!(empty($logo_list))) {

			$url = JURI::root () . 'plugins/vmpayment/svealib/assets/images/';

			//$url = JURI::root () . 'images/stories/virtuemart/' . $this->_psType . '/';
			if (!is_array ($logo_list)) {
				$logo_list = (array)$logo_list;
			}
			foreach ($logo_list as $logo) {
				$alt_text = substr ($logo, 0, strpos ($logo, '.'));
				$img .= '<span class="vmCartPaymentLogo" ><img align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /></span> ';
			}
		}
		return $img;
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
         * Card needs no extra so we return our payment method id.
         * @return int
         */
        function onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter) {
            return parent::onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
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
	 */
	public function plgVmOnUpdateOrderPayment(  $_formData) {
            if (!$this->selectedThisByMethodId ($_formData->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
            }
            if (!($method = $this->getVmPluginMethod ($_formData->virtuemart_paymentmethod_id))) {
                    return NULL; // Another method was selected, do nothing
            }

            if (!($paymentTable = $this->getDataByOrderId($_formData->virtuemart_order_id))) {
                        return FALSE;
            }
            //get countrycode
                $q = 'SELECT `virtuemart_country_id` FROM #__virtuemart_order_userinfos  WHERE virtuemart_order_id=' . $_formData->virtuemart_order_id;
                $db = JFactory::getDBO();
                $db->setQuery($q);
                $country_id = $db->loadResult();
                $country = ShopFunctions::getCountryByID($country_id, 'country_2_code');
            //Deliver order
            if($_formData->order_status == $method->status_shipped){
                try {
                    $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
                    $svea = WebPay::deliverOrder($sveaConfig)
                                ->setOrderId($paymentTable->svea_transaction_id)
                                ->setOrderDate(date('c'))
                                ->setCountryCode($country)
                                        ->deliverCardOrder()
                                            ->doRequest();
                } catch (Exception $e) {
                    vmError ($e->getMessage (), $e->getMessage ());
                    return FALSE;
                }
                 if($svea->accepted == 1){
                       return TRUE;
                 } else {
                    vmError ('Svea Error '. $svea->resultcode . ' : ' .$svea->errormessage, 'Svea Error '. $svea->resultcode . ' : ' .$svea->errormessage);
                     return FALSE;
                 }
            }
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
	function plgVmOnPaymentResponseReceived(&$html) {

            $virtuemart_paymentmethod_id = JRequest::getString ('pm', '');
            if (!($method =  $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
		return NULL; // Another method was selected, do nothing
            }

            if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
            if (!class_exists ('VirtueMartModelOrders')) {
		require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
            }
            if (!class_exists ('CurrencyDisplay')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
            }
            $modelOrder = VmModel::getModel ('orders');
            $order_number = JRequest::getString ('on', '');

            $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
            $order = $modelOrder->getOrder ($virtuemart_order_id);

            $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
            $countryId = $order['details']['BT']->virtuemart_country_id;
            $countryCode = shopFunctions::getCountryByID($countryId,'country_2_code');

            $resp = new SveaResponse($_REQUEST, $countryCode, $sveaConfig);

            if($resp->response->accepted == 1){
                $query = 'UPDATE #__virtuemart_payment_plg_sveacard
                                SET `svea_transaction_id` = "' . $resp->response->transactionId . '"' .
                                'WHERE `order_number` = "' . $order['details']['BT']->order_number.'"';
                $db = JFactory::getDBO();
                $db->setQuery($query);
                $db->query();

                $order['order_status'] = $method->status_success;
                $order['customer_notified'] = 1;
                $order['comments'] = '';
                $modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);

                $logoImg = JURI::root(TRUE) . '/plugins/vmpayment/svealib/assets/images/sveawebpay.png';
                $html =  '<img src="'.$logoImg.'" /><br /><br />';
                $html .= '<div class="vmorder-done">' . "\n";
                $html .= '<div class="vmorder-done-payinfo">'.JText::sprintf('VMPAYMENT_SVEA_CARD').'</div>';
                if (!empty($payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$payment_info = JText::_ ($method->payment_info);
			} else {
				$payment_info = $method->payment_info;
			}
			$html .= "<div>".$this->getHtmlRow (JText::sprintf('VMPAYMENT_SVEA_PAYMENTINFO'), $payment_info, 'class="vmorder-done-payinfo"')."</div>";
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		$html .= '<div class="vmorder-done-nr">'.JText::sprintf('VMPAYMENT_SVEA_ORDERNUMBER').': '. $order['details']['BT']->order_number."</div>";

                $paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
                $totalInPaymentCurrency = $paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false);
//                    $html .= '<div class="vmorder-done-amount">'.JText::sprintf('VMPAYMENT_SVEA_ORDER_TOTAL').': '. $currency->priceDisplay($order['details']['BT']->order_total).'</div>'; // order total
                $html .= '<div class="vmorder-done-amount">'.JText::sprintf('VMPAYMENT_SVEA_ORDER_TOTAL').': '. $currency->priceDisplay($totalInPaymentCurrency).'</div>'; // order total in payment currency
           	$html .= '</div>' . "\n";


            }else{
                $order['order_status'] = $method->status_denied;
                $order['customer_notified'] = 0;
                $html = SveaHelper::errorResponse( $resp->response->resultcode,$resp->response->errormessage);
                $order['comments'] = $html;
                $modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);

                return NULL;
            }


              if (!class_exists('VirtueMartCart'))
                   require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
            $cart = VirtueMartCart::getCart ();
            $cart->emptyCart ();
            return TRUE;
        }

            /**
     * will catch ajaxcall
     * @param type $type
     * @param type $name
     * @param type $render
    public function plgVmOnSelfCallFE($type,$name,&$render) {

    }
             *
             */

    /**
     * @param type $param0
     * @param type $countryCode
     * @return string
     */
    public function getSveaCardHtml($paymentId,$cartTotal) {
         $imageRoot = JURI::root(TRUE) . '/plugins/vmpayment/svealib/assets/images/svea/';

        //box for form
        $html = '<fieldset id="svea_card_'.$paymentId.'">
                    <input type="hidden" id="paymenttypesvea_'.$paymentId.'" value="'. $paymentId . '" />
                    <input type="hidden" id="carttotal_'.$paymentId.'" value="'. $cartTotal . '" />
                   <fieldset>
                      <img src="'.$imageRoot.'KORTCERT.png" />
                       <img src="'.$imageRoot.'AMEX.png" />
                       <img src="'.$imageRoot.'DINERS.png" />
                  </fieldset>
                </fieldset>';
      //start skript and set vars
        $html .= "<script type='text/javascript'>
                    var checked_$paymentId = jQuery('input[name=\'virtuemart_paymentmethod_id\']:checked').val();
                    var sveaid_$paymentId = jQuery('#paymenttypesvea_$paymentId').val();";


       //Document ready start
        $html .= " jQuery(document).ready(function ($){";

         //hide show box
        $html .= "
                        if(checked_$paymentId != sveaid_$paymentId){
                            jQuery('#svea_card_$paymentId').hide();
                        }else{
                            jQuery('#svea_card_$paymentId').show();
                        }
                    ";
        //toggle display form
        $html .=        '
                        jQuery("input[name=\'virtuemart_paymentmethod_id\']").change(function(){
                            checked_'.$paymentId.' = jQuery("input[name=\'virtuemart_paymentmethod_id\']:checked").val();
                            if(checked_'.$paymentId.' == sveaid_'.$paymentId.'){
                                  jQuery("#svea_card_'.$paymentId.'").show();
                            }else{
                                jQuery("#svea_card_'.$paymentId.'").hide();
                            }
                            });';


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
