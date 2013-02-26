<?php

defined('_JEXEC') or die('Restricted access');


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

class plgVmPaymentSvea extends vmPSPlugin {

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
		return $this->createTableSQL('Payment Standard Table');
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
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)'
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

        //$cart->setCartIntoSession ();
		$lang     = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);
		$vendorId = 0;


		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$this->getPaymentCurrency($method, true);

		// END printing out HTML Form code (Payment Extra Info)
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
		$dbValues['cost_per_transaction']        = $method->cost_per_transaction;
		$dbValues['cost_percent_total']          = $method->cost_percent_total;
		$dbValues['payment_currency']            = $currency_code_3;
		$dbValues['payment_order_total']         = $totalInPaymentCurrency;
		$dbValues['tax_id']                      = $method->tax_id;
		$this->storePSPluginInternalData($dbValues);
        
        
            //SVEA settings and include
            include("svea_files/SveaConfig.php");
           // SveaConfig::getConfig()->setTestMode(true);
            $config = SveaConfig::getConfig();
            $config->merchantId = $method->merchantid;
            $config->secret = $method->secretword;
            $paymentRequest = new SveaPaymentRequest();
            $sveaOrder = new SveaOrder();
            $paymentRequest->order = $sveaOrder;

            foreach($order['items'] as $items){

                $sveaOrderRow = new SveaOrderRow();
                $sveaOrderRow->amount = number_format($items->product_final_price,2,'','');
                $sveaOrderRow->vat = number_format($items->product_tax,2,'','');
                $sveaOrderRow->name = $items->order_item_name;
                $sveaOrderRow->quantity = $items->product_quantity;
                $sveaOrderRow->unit = "st";

                $sveaOrder->addOrderRow($sveaOrderRow);
            }

            //If shipment is set
            if ($order['details']['BT']->order_shipment > 0){

                $shipmentVAT   = $order['details']['BT']->order_shipment_tax;
                $shipmentPrice = $order['details']['BT']->order_shipment + $shipmentVAT;

                $sveaOrderRow = new SveaOrderRow();            
                $sveaOrderRow->amount = number_format($shipmentPrice,2,'','');
                $sveaOrderRow->vat = number_format($shipmentVAT,2,'','');
                $sveaOrderRow->name = "Fraktkostnad";
                $sveaOrderRow->quantity = "1";
                $sveaOrderRow->unit = "st";

                $sveaOrder->addOrderRow($sveaOrderRow);
            }

            //If campaign is set
            if ($order['details']['BT']->coupon_discount > 0){

                $vat = $order['details']['BT']->coupon_discount * 0.2;

                $sveaOrderRow = new SveaOrderRow();            
                $sveaOrderRow->amount = - number_format($order['details']['BT']->coupon_discount,2,'','');
                $sveaOrderRow->vat = - number_format($vat,2,'','');
                $sveaOrderRow->name = $order['details']['BT']->coupon_code;
                $sveaOrderRow->quantity = "1";
                $sveaOrderRow->unit = "st";

                $sveaOrder->addOrderRow($sveaOrderRow);
            }

            $sveaOrder->amount = number_format($order['details']['BT']->order_total,2,'','');
            $sveaOrder->customerRefno = "test".$order['details']['BT']->virtuemart_order_id. rand(0, 100);
            $sveaOrder->returnUrl = JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid'));
            $sveaOrder->vat = number_format($order['details']['BT']->order_tax,2,'','');
            $sveaOrder->currency = $currency_code_3;         
            $paymentRequest->createPaymentMessage();
            $html  = '<html><head><title>Skickar till svea</title></head><body><div style="margin: auto; text-align: center;">Skickar till SveaWebPay...<br /><img src="'.JURI::root ().'images/stories/virtuemart/payment/svea/sveaLoader.gif" /></div>';
                    
            //Testmode check
            if ($method->testmode == 1){
                $html .= $paymentRequest->getPaymentForm(true);
            }else{
                $html .= $paymentRequest->getPaymentForm(false);
            }       
        
            $html .= ' <script type="text/javascript">';
                    $html .= ' document.sveaPaymentForm.submit();';
                    $html .= ' </script></body></html>'; 
          
            $cart->_confirmDone = FALSE;
            $cart->_dataValidated = FALSE;

            $modelOrder = VmModel::getModel ('orders');

            $order['order_status'] = 'X';
            $order['customer_notified'] = 0;
            //$order['comments'] = '';
            $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

            JRequest::setVar ('html', $html);
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
          
    //SVEA settings and include  
    require_once('svea_files/SveaConfig.php');   
    
    if (!class_exists ('VirtueMartModelOrders')) {
		require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
	}
    
    $modelOrder = VmModel::getModel ('orders');
    
    $order_number = JRequest::getString ('on', '');
    $virtuemart_paymentmethod_id = JRequest::getString ('pm', '');
    $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
    
	$order = $modelOrder->getOrder ($virtuemart_order_id);
     
    if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
		return NULL; // Another method was selected, do nothing
	}
    
        	   
       
    //GETs
    $response = $_REQUEST['response'];
    $mac = $_REQUEST['mac'];
    $merchantid = $_REQUEST['merchantid'];
    $secretWord = $method->secretword;  
    $resp = new SveaPaymentResponse($response);
    //WIP
    
   // print_r($resp->payment);die();
    $xmlDecoded = base64_decode($resp->payment);
   
    $simpleXml = new SimpleXMLElement($xmlDecoded);
   
   
    
    if($resp->validateMac($mac,$secretWord) == true){
        if ($resp->statuscode == '0'){
            /**
             * Bugfix 2013-02-25 for not adding invoicefee when using paypage
             * By Anneli Halld'n
             */
             //check if the paymentmethod begins with SVEAINVOICE
            if(substr((string)$simpleXml->transaction->paymentmethod,0,11) == "SVEAINVOICE"){
                $priceExMoms = $order['details']['BT']->order_subtotal;
             
                //assume the difference is the invoicefee    
                $invoiceFee = ((int)$simpleXml->transaction->amount * 0.01) - $order['details']['BT']->order_total;
                if($invoiceFee > 0){
                    $priceExMoms = $invoiceFee / 1.25;                    
                }               
                //update total
                $order['details']['BT']->order_total = (int)$simpleXml->transaction->amount * 0.01;
               // $order['details']['BT']->order_subtotal = ((int)$simpleXml->transaction->amount * 0.01) / 1.25;
                $order['details']['BT']->order_tax =   $order['details']['BT']->order_total - $order['details']['BT']->order_subtotal;           
                //update the whole order
                $db = JFactory::getDbo();
                $prefix = $db->getPrefix();              
                $db->select($prefix.'virtuemart_orders');
                $q =    'UPDATE '.$prefix.'virtuemart_orders SET  
                        `order_payment`= '. $priceExMoms.',
                        `order_total`= '. ($order['details']['BT']->order_salesPrice + $invoiceFee).',
                        `order_payment_tax`= '. ($invoiceFee - $priceExMoms).',
                        `order_billTaxAmount`= '. (($invoiceFee - $priceExMoms) +  $order['details']['BT']->order_billTaxAmount).'
                        WHERE `virtuemart_order_id` = '.$order['details']['BT']->virtuemart_order_id;              
               $query = $db->setQuery($q);
               $db->execute($query);

            }
            /**
             * bugfix end
             */
            
            $order['order_status'] = 'C';
        	$order['customer_notified'] = 1;
        	$order['comments'] = '';
        	$modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
            
        }else{
            $order['order_status'] = 'X';
        	$order['customer_notified'] = 0;
        	$order['comments'] = '';
        	$modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
            return FALSE;
        }
    }else{
        die('knas');
       	return FALSE;
    }
    
     /* Load the cart helper */
    if (!class_exists('VirtueMartCart'))
	   require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
	
    $cart = VirtueMartCart::getCart ();
	$cart->emptyCart ();
    return TRUE;
	}
 
}

// No closing tag
