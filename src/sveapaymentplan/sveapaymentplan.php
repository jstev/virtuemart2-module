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
	 * Create the table for this plugin if it does not yet exist.
	 * @author Val?rie Isaksen
	 */
	public function getVmPluginCreateTableSQL() {
            //is there already a svea table for this payment?
            $q = 'SHOW TABLES LIKE "%sveapaymentplan%"';
            $db = JFactory::getDBO();
            $db->setQuery($q);
            $table_exists = $db->loadResult();
            //if there is add columns
            if(is_string($table_exists) && strpos($table_exists, 'sveapaymentplan') != FALSE){
                //get all columns and check for the new ones
                $q = 'SHOW COLUMNS FROM '.$table_exists;
                $db->setQuery($q);
                $columns = $db->loadAssocList();
               $svea_order_id = FALSE;
               $svea_contract_number = FALSE;
                foreach ($columns as $column) {
                    if(in_array( 'svea_order_id',$column)){
                        $svea_order_id = TRUE;
                    }  elseif (in_array( 'svea_contract_number',$column)) {
                        $svea_contract_number = TRUE;
                    }
                }
                $q1 = $svea_order_id ? '' : ' ADD svea_order_id INT(1) UNSIGNED';
                $q2 = $svea_contract_number ? '' : 'ADD svea_contract_number VARCHAR(64)';

                $query = "ALTER TABLE `" . $this->_tablename . "`" .
                        $q1 . ($q1 != '' ? ',' : '') .
                        $q2;
                $db->setQuery($query);
                $db->query();
                }
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

                        'svea_order_id'               => 'int(1) UNSIGNED',
                        'svea_contract_number'        => 'varchar(64)',
                        'svea_approved_amount'        => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
                        'svea_expiration_date'        => 'datetime',
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
		$this->getPaymentCurrency($method);

                $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3        = $db->loadResult();
		$paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd                     = CurrencyDisplay::getInstance($cart->pricesCurrency);

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
             //add coupons
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
                      ->usePaymentPlanPayment($session->get("svea_campaigncode_$method->virtuemart_paymentmethod_id"))
                        ->doRequest();
           } catch (Exception $e) {
                $html = SveaHelper::errorResponse('',$e->getMessage ());
                vmError ($e->getMessage (), $e->getMessage ());
                return NULL;
           }
            if ($svea->accepted == 1) {
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

                  //Print html on thank you page. Will also say "thank you for your order!"
                 $logoImg = JURI::root(TRUE) . '/plugins/vmpayment/svealib/assets/images/sveawebpay.png';

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

                $paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
                $totalInPaymentCurrency = $paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false);
//                    $html .= '<div class="vmorder-done-amount">'.JText::sprintf('VMPAYMENT_SVEA_ORDER_TOTAL').': '. $currency->priceDisplay($order['details']['BT']->order_total).'</div>'; // order total
                $html .= '<div class="vmorder-done-amount">'.JText::sprintf('VMPAYMENT_SVEA_ORDER_TOTAL').': '. $currency->priceDisplay($totalInPaymentCurrency).'</div>'; // order total in payment currency

           	$html .= '</div>' . "\n";
                $modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = $method->status_success;

		$order['comments'] = 'Order created at Svea. Svea orderId: '.$svea->sveaOrderId;

                // autodeliver order if set. Will trigger plgVmOnUpdateOrderPayment()
                if($method->autodeliver == '1'){
                    $order['order_status'] = $method->status_shipped;
                }
                $this->storePSPluginInternalData($dbValues);
                $order['customer_notified'] = 1;
                $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

                  //Overwrite billto address
                SveaHelper::updateBTAddress($svea, $order['details']['BT']->virtuemart_order_id);
                   //Overwrite shipto address but not if Vm will do it for us
                if($method->shipping_billing == '1' && $cart->STsameAsBT == 0){
                    SveaHelper::updateSTAddress($svea, $order['details']['BT']->virtuemart_order_id);
                }
            }
            else {
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

            if (!($paymentTable = $this->_getInternalData ($virtuemart_order_id))) {
                return '';
            }
                $html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE();
                $html .= $this->getHtmlRowBE('VMPAYMENT_SVEA_PAYMENTMETHOD', $paymentTable->payment_name);
//                $html .= '<tr class="row2"><td>' . JText::sprintf('VMPAYMENT_SVEA_INVOICEFEE').'</td><td align="left">'. $paymentTable->cost_per_transaction.'</td></tr>';
                $html .= $this->getHtmlRowBE('Approved amount', $paymentTable->svea_approved_amount);
                $html .= $this->getHtmlRowBE('Expiration date', $paymentTable->svea_expiration_date);
                $html .= $this->getHtmlRowBE('Svea order id', $paymentTable->svea_order_id);
                $html .= $this->getHtmlRowBE('Svea contract number', $paymentTable->svea_contract_number);
		$html .= '</table>' . "\n";
		return $html;
	}

        function _getInternalData ($virtuemart_order_id, $order_number = '') {
		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
		if ($order_number) {
			$q .= " `order_number` = '" . $order_number . "'";
		} else {
			$q .= ' `virtuemart_order_id` = ' . $virtuemart_order_id;
		}

		$db->setQuery ($q);
		if (!($paymentTable = $db->loadObject ())) {
                    return '';
		}

		return $paymentTable;

	}

        /**
         * getCosts() will return the invoice fee for Svea Part payment payment method
         * @override
         *
         * @param VirtueMartCart $cart
         * @param type $method
         * @param type $cart_prices
         * @return type cost_per_transaction -- as defined by part payment config cost_per_transaction setting (should be given ex vat)
         */
	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
            return 0.0;//($method->cost_per_transaction); // TODO remove cost_per_transaction outright
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

            if( empty($cart->BT) ) {  // i.e. user not logged in --
                $returnValue = VmConfig::get('oncheckout_only_registered',0) ? false : true; // return true iff we allow non-registered users to checkout
            }
            else    // we show payment method if registered customer billto address is in configured list of payment method countries
            {
                $returnValue = $this->addressInAcceptedCountry( $cart->BT, $method->countries );
            }
            //Check min and max amount. Copied from standard payment
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
        {   //is there not a billto country, set to 0
                if (strlen($address['virtuemart_country_id'] == 0)) {
                   $address['virtuemart_country_id'] = 0;
                }
                //is address not an array, create array with country 0
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

	/**
         * Svea: Update PaymentplanParams in table
         *
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Val?rie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
            //create Svea table if not exists
            $this->sveaCreateParamsTable();
            //get paymentplan params
            $paymentMethodId = JRequest::getVar('virtuemart_paymentmethod_id'); //ex. 54
            $params = $this->sveaGetPaymentPlanParamsFromServer($paymentMethodId);
            //insert into db
            if($params != NULL){
                try {
                foreach ($params as $param) {
                    $db = JFactory::getDbo();
                    //$query = $db->getQuery(true);
                    $query = "INSERT INTO `#__svea_params_table`
                            (   `campaignCode` ,
                                `description`,
                                `paymentPlanType`,
                                `contractLengthInMonths`,
                                `monthlyAnnuityFactor`,
                                `initialFee`,
                                `notificationFee`,
                                `interestRatePercent`,
                                `numberOfInterestFreeMonths`,
                                `numberOfPaymentFreeMonths`,
                                `fromAmount`,
                                `toAmount`,
                                `timestamp`,
                                `paymentmethodid`,
                                `jpluginid`)
                                VALUES(";
                    foreach ($param as $key => $value)
                        $query .= "'".$value."',";

                    $query .= time().",";
                    $query .= $paymentMethodId.",";
                    $query .= $jplugin_id;
                    $query .= ")";
                    $db->setQuery($query);
                    $db->query();

                }

            } catch (Exception $e) {
                vmError ($e->getMessage (), $e->getMessage ());
                }
            }
            //From vm parent
            return $this->onStoreInstallPluginTable($jplugin_id);
	}
        /**
         * Svea: Create Params table if not exists
         */
        protected function sveaCreateParamsTable(){
            $q  = '
                    CREATE TABLE IF NOT EXISTS `#__svea_params_table`
                    (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `campaignCode` VARCHAR( 100 ) NOT NULL,
                    `description` VARCHAR( 100 ) NOT NULL ,
                    `paymentPlanType` VARCHAR( 100 ) NOT NULL ,
                    `contractLengthInMonths` INT NOT NULL ,
                    `monthlyAnnuityFactor` DOUBLE NOT NULL ,
                    `initialFee` DOUBLE NOT NULL ,
                    `notificationFee` DOUBLE NOT NULL ,
                    `interestRatePercent` INT NOT NULL ,
                    `numberOfInterestFreeMonths` INT NOT NULL ,
                    `numberOfPaymentFreeMonths` INT NOT NULL ,
                    `fromAmount` DOUBLE NOT NULL ,
                    `toAmount` DOUBLE NOT NULL ,
                    `timestamp` INT UNSIGNED NOT NULL,
                    `paymentmethodid` INT NOT NULL,
                    `jpluginid` INT NOT NULL
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1
                ';

            $db = JFactory::getDBO();
            $db->setQuery($q);
            $db->query($q);
        }
        /**
         * Svea: Get PaymentPlanParams and format for db
         * @param type $paymentMethodId
         * @return array of formatted PaymentPlanParams ready for db insert
         */
        protected function sveaGetPaymentPlanParamsFromServer($paymentMethodId){
            if (!($method = $this->getVmPluginMethod($paymentMethodId))) {
                    return NULL; // Another method was selected, do nothing
            }
            $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
            $svea_params = WebPay::getPaymentPlanParams($sveaConfig);
            try {
                 $svea_params = $svea_params
                        ->setCountryCode(JRequest::getVar('countrycode'))
                            ->doRequest();
            } catch (Exception $e) {
                 vmError ($e->getMessage (), $e->getMessage ());
                    return NULL;
            }
            if(isset($svea_params->errormessage) === true && $svea_params->errormessage !== ''){
                return $svea_params->resultcode . " : " . $svea_params->errormessage;

            }else{
                return $this->sveaFormatParams($svea_params);
            }


        }
        /**
         * Svea: Format PaymentPlanParams for db insert
         * @param type $response
         * @return array of params
         */
        protected function sveaFormatParams($response){
            $result = array();
            if ($response == null) {
                return $result;
            } else {
                foreach ($response->campaignCodes as $responseResultItem) {
                        $campaignCode = (isset($responseResultItem->campaignCode)) ? $responseResultItem->campaignCode : "";
                        $description = (isset($responseResultItem->description)) ? $responseResultItem->description : "";
                        $paymentplantype = (isset($responseResultItem->paymentPlanType)) ? $responseResultItem->paymentPlanType : "";
                        $contractlength = (isset($responseResultItem->contractLengthInMonths)) ? $responseResultItem->contractLengthInMonths : "";
                        $monthlyannuityfactor = (isset($responseResultItem->monthlyAnnuityFactor)) ? $responseResultItem->monthlyAnnuityFactor : "";
                        $initialfee = (isset($responseResultItem->initialFee)) ? $responseResultItem->initialFee : "";
                        $notificationfee = (isset($responseResultItem->notificationFee)) ? $responseResultItem->notificationFee : "";
                        $interestratepercentage = (isset($responseResultItem->interestRatePercent)) ? $responseResultItem->interestRatePercent : "";
                        $interestfreemonths = (isset($responseResultItem->numberOfInterestFreeMonths)) ? $responseResultItem->numberOfInterestFreeMonths : "";
                        $paymentfreemonths = (isset($responseResultItem->numberOfPaymentFreeMonths)) ? $responseResultItem->numberOfPaymentFreeMonths : "";
                        $fromamount = (isset($responseResultItem->fromAmount)) ? $responseResultItem->fromAmount : "";
                        $toamount = (isset($responseResultItem->toAmount)) ? $responseResultItem->toAmount : "";

                        $result[] = Array(
                            "campaignCode" => $campaignCode,
                            "description" => $description,
                            "paymentPlanType" => $paymentplantype,
                            "contractLengthInMonths" => $contractlength,
                            "monthlyAnnuityFactor" => $monthlyannuityfactor,
                            "initialFee" => $initialfee,
                            "notificationFee" => $notificationfee,
                            "interestRatePercent" => $interestratepercentage,
                            "numberOfInterestFreeMonths" => $interestfreemonths,
                            "numberOfPaymentFreeMonths" => $paymentfreemonths,
                            "fromAmount" => $fromamount,
                            "toAmount" => $toamount
                        );
                }
            }
            return $result;
        }

        /**
         * Svea: Displays "Pay from x /month on product display
	 * @param $product
	 * @param $productDisplay
	 * @return bool
         *
         */

	function plgVmOnProductDisplayPayment ($product, &$productDisplay) {
            $vendorId = 1;
		if ($this->getPluginMethods ($vendorId) === 0) {
			return FALSE;
		}
                $activated = 0;
                foreach ($this->methods as $method) {

                    if($method->product_display == "1")
                        $activated++;
                }
                //Svea restrictions: Widget can only be active for one instance of Svea Invoice
                if($activated != 1){
                    return FALSE;
                }
                foreach ($this->methods as $method) {
                    if($method->product_display == "1"){
                    $q = "SELECT `campaignCode`,`description`,`paymentPlanType`,`contractLengthInMonths`,
                            `monthlyAnnuityFactor`,`initialFee`, `notificationFee`,`interestRatePercent`,
                            `numberOfInterestFreeMonths`,`numberOfPaymentFreeMonths`,`fromAmount`,`toAmount`
                        FROM `#__svea_params_table`
                        WHERE `timestamp`=(SELECT MAX(timestamp) FROM `#__svea_params_table` WHERE  `paymentmethodid` = '".$method->virtuemart_paymentmethod_id."' )
                        AND  `paymentmethodid` = '".$method->virtuemart_paymentmethod_id."'
                        ORDER BY `monthlyAnnuityFactor` ASC";

                $db = JFactory::getDBO();
                $db->setQuery($q);
                $params = $db->loadAssocList();
                //rebuild list to fit svea paymentPlanPricePerMonth param type
                $arrayWithObj = array();
                foreach ($params as $campaign) {
                    $arrayWithObj[] = (object)$campaign;
                }
                $objectWithArray['campaignCodes'] = $arrayWithObj;
                //run thru method to get price per month
                $currency_decimals = $currency_code_3 == 'EUR' ? 1 : 0;
                $priceList = SveaHelper::paymentPlanPricePerMonth($product->prices['salesPrice'], (object)$objectWithArray,$method->payment_currency);
                    if(sizeof($priceList) > 0){
                        $prices = array();
                        $prices[] = '<h4 style="display:block;  list-style-position:outside; margin: 5px 10px 10px 10px">'.
                                JText::sprintf("VMPAYMENT_PAYMENTPLAN").
                                '</h4>';

                        foreach ($priceList as $value) {
                            $prices[] = '<div class="svea_product_price_item" style="display:block; margin: 5px 10px 10px 10px">'.
                                            "<div style='float:left;'>".
                                                $value['description'] ."
                                            </div>
                                           <div style='color: #002A46;
                                                    width:90%;
                                                    margin-left: 80px;
                                                    margin-right: auto;
                                                    float:left;'>
                                                <strong>".
                                                    round($value['pricePerMonth'],$currency_decimals) . " ".$value['symbol'] .
                                                    "/".JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_MONTH").
                                                "</strong>
                                            </div>
                                        </div>";


                        }

                        $view = array();
                        $view['price_list'] = $prices;
                         $view['lowest_price'] = round($priceList[0]['pricePerMonth']);
                        $view['currency_display'] = $value['symbol'] . "/" . JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_MONTH");
                        $view['line'] = '<img width="163" height="1" src="'. JURI::root(TRUE) . '/plugins/vmpayment/svealib/assets/images/svea/grey_line.png" />';
                        $view['text_from'] = JText::sprintf("VMPAYMENT_SVEA_TEXT_FROM")." ";

                         //check if paymentplan is activated and if product_display is activated!
                        $q  = 'SELECT `payment_params` FROM `#__virtuemart_paymentmethods` WHERE `published`=1 AND `payment_element`= "sveainvoice" ';
                        $db = JFactory::getDBO();
                        $db->setQuery($q);
                        $invoiceInfo = $db->loadResult();
                        $invoiceArray = explode("|", $invoiceInfo);
                        $paymentPlanProductActive = 0;
                        foreach ($invoiceArray as $key => $value) {
                            $pair = explode("=", $value);
                            if($pair[0] == "product_display" && $pair[1] == '"1"'){
                                $paymentPlanProductActive = 1;
                            }

                        }

                        //to load info from both payments in same layout, we use session to store before rendering
                        $session = JFactory::getSession();
                        $invoice_view = $session->get('svea_invoice_product_price');
                        //have not rendered invoice yet
                        if($invoice_view == NULL){
                            //is using product display in invoice
                            if($paymentPlanProductActive == 1){
                                //fill with paymentplan, but do not publish
                                 $session->set('svea_paymentplan_product_price',$view);
                                //only use paymentplan for display
                            }  else {
                                $viewProduct['svea_paymentplan'] = $view;
                                $sveaString = $this->renderByLayout('productprice_layout', $viewProduct, 'svealib', 'payment');
                                $productDisplay[] = $sveaString;
                            }

                        }elseif ($invoice_view != NULL) {
                            $viewProduct['svea_invoice'] = $invoice_view;
                            $viewProduct['svea_paymentplan'] = $view;
                            $sveaString = $this->renderByLayout('productprice_layout', $viewProduct, 'svealib', 'payment');
                            //loads template in Vm product display
                            $session->clear('svea_invoice_product_price');
                               $sveaString = $this->renderByLayout('productprice_layout', $viewProduct, 'svealib', 'payment');
                            //loads template in Vm product display
                            $productDisplay[] = $sveaString;

                        }

                    }
                }

            }
            return TRUE;
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
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart,  &$msg)
        {
            $onSelectCheck = parent::OnSelectCheck($cart);  // parent, should return true
            if( $onSelectCheck )
            {
                try {
                    $this->validateDataFromSelectPayment( JRequest::get() );    // raise exception if missing needed request credentials
                }
                catch( Exception $e ) {
                    $app = JFactory::getApplication ();
                    $app->enqueueMessage ( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS"),'error');
                    $app->redirect (JRoute::_ ('index.php?option=com_virtuemart&view=cart&task=editpayment'));
                    $msg = $app->getError();
                }
                $session = JFactory::getSession();
                $this->saveDataFromSelectPayment( JRequest::get(), $session );  // store passed credentials in session
                $this->populateBillToFromGetAddressesData( $cart, $session ); // set BT address with passed data
            }
            return $onSelectCheck;
        }

       /**
        * Make sure all needed fields to create the order are passed to us from the editpayment page.
        * If not, raise an exception.
        * @param mixed $request
        * @throws Exception
        */
       private function validateDataFromSelectPayment( $request )
       {
            $methodId = $request['virtuemart_paymentmethod_id'];

            $countryCode = $request['svea_countryCode_'.$methodId];

            // getAddress countries need the addressSelector
            if( $countryCode == 'SE' ||
                $countryCode == 'DK'
            )
            {
                if( !array_key_exists( "svea_addressSelector_".$methodId, $request ) )    // no addresselector => did not press getAddress
                {
                    throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
                }
            }

            // FI, NO private need SSN
            if( $countryCode == 'FI' ||
                ($countryCode == 'NO' && $request['svea_customertype_'.$methodId] == 'svea_invoice_customertype_private')
            )
            {
                if( $request["svea_ssn_".$methodId] == "" ) // i.e. field was left blank
                {
                     throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
                }
            }

            // DE, NL need address fields, but that is checked by virtuemart (BillTo), so no worry of ours
            // DE, NL need birthdate
            if( $countryCode == 'DE' ||
                $countryCode == 'NL'
            )
            {
                if( ( $request["svea_birthday_".$methodId] == "" ) ||
                    ( $request["svea_birthmonth_".$methodId] == "" ) ||
                    ( $request["svea_birthyear_".$methodId] == "" )
                )
                {
                    throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
                }
            }
            if( $countryCode == 'NL'
            )
            {
                if( ( $request["svea_initials_".$methodId] == "" )
                )
                {
                    throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
                }
            }
        }

        /**
         * Parse the JRequest::get() parameters passed from editpayment, i.e. the ssn et al and address fields.
         * Writes all relevant svea addressfields corresponding to the selected payment method to the session
         * in the format "svea_xxx" where xxx is i.e. ssn, addresselector, customertype and various address fields
         * @param mixed $request
         * @param JSession $session
         */
        private function saveDataFromSelectPayment( $request, $session )
        {
            $methodId = $request['virtuemart_paymentmethod_id'];
            $countryCode = $request['svea_countryCode_'.$methodId];

            $svea_prefix = "svea";

            foreach ($request as $key => $value) {
                $svea_key = "";
                $request_explode = explode('_', $key);
                //if this is svea's and it is the selected method
                if(( $request_explode[0] == $svea_prefix) && $methodId == $request_explode[2])     // store keys in the format "svea_xxx"
                {
                    // trim addressSelector, methodId
                    $svea_attribute = $request_explode[1]; //substr($key, strlen($svea_prefix)+1, -(strlen(strval($methodId))+1) ); // svea_xxx_## => xxx
                    $svea_prefix = $request_explode[0]; //$svea_prefix."_".$svea_attribute;
                    //methodId wasn't the last param, therefore probably an addresselector
                }  elseif (( $request_explode[0] == $svea_prefix) && $methodId == $request_explode[3]) {

                    // getAddress countries have the addressSelector address fields set
                    if( $countryCode == 'SE' ||
                        $countryCode == 'DK'
                    )
                    {
                        $svea_attribute = $request_explode[2];

                    }
                }
                $session->set($svea_prefix."_".$svea_attribute, $value);
            }
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
        * @override -- adds our Svea getAddress customer credentials form fields to html
        * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
        *
        * @param object  $cart Cart object
        * @param integer $selected ID of the method selected
        * @return boolean True on success, false on failures, null when this plugin was not selected.
        * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
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
	 */
	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
            $session = JFactory::getSession();
            $this->populateBillToFromGetAddressesData( $cart, $session );
            return true;
	}

        /**
         * Fills in cart billto address from  getAddresses data stored in the session
         * @param VirtueMartCart $cart
         * @param JSession $session
         * @return boolean
         */
        private function populateBillToFromGetAddressesData( VirtueMartCart $cart, $session )
        {
            if (!($method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                return NULL; // Another method was selected, do nothing
            }
            $countryId = "";
            if( sizeof($method->countries)== 1 ) // single country configured in payment method, use this for unregistered users
            {
                $countryId = $method->countries[0];
            }
            if( $cart->BT == 0 ) $cart->BT = array(); // fix for "uninitialised" BT

            if( $session->get('svea_customertype') == 'svea_partpayment_customertype_company' )
            {
                $cart->BT['company'] = $session->get('svea_fullName', !empty($cart->BT['company']) ? $cart->BT['company'] : "" );
            }

            $cart->BT['first_name'] = $session->get('svea_firstName', !empty($cart->BT['first_name']) ? $cart->BT['first_name'] : "" );
            $cart->BT['last_name'] = $session->get('svea_lastName', !empty($cart->BT['last_name']) ? $cart->BT['last_name'] : "" );
            $cart->BT['address_1'] = $session->get('svea_street', !empty($cart->BT['address_1']) ? $cart->BT['address_1'] : "" );
            $cart->BT['address_2'] = $session->get('svea_address_2', !empty($cart->BT['address_2']) ? $cart->BT['address_2'] : "");
            $cart->BT['zip'] = $session->get('svea_zipCode', !empty($cart->BT['zip']) ? $cart->BT['zip'] : "");
            $cart->BT['city'] = $session->get('svea_locality', !empty($cart->BT['city']) ? $cart->BT['city'] : "");
            $cart->BT['virtuemart_country_id'] =
            $session->get('svea_virtuemart_country_id', !empty($cart->BT['virtuemart_country_id']) ? $cart->BT['virtuemart_country_id'] : $countryId);

               //Overwrite shipto address but not if Vm will do it for us
                if(isset($method) && $method->shipping_billing == '1' && $cart->STsameAsBT == 0){
                if( $cart->ST == 0 ) $cart->ST = array(); // fix for "uninitialised" ST

                if( $session->get('svea_customertype') == 'svea_invoice_customertype_company' )
                {
                    $cart->ST['company'] = $session->get('svea_fullName', !empty($cart->ST['company']) ? $cart->ST['company'] : "" );
                }
                $cart->ST['first_name'] = $session->get('svea_firstName', !empty($cart->ST['first_name']) ? $cart->ST['first_name'] : "" );
                $cart->ST['last_name'] = $session->get('svea_lastName', !empty($cart->ST['last_name']) ? $cart->ST['last_name'] : "" );
                $cart->ST['address_1'] = $session->get('svea_street', !empty($cart->ST['address_1']) ? $cart->ST['address_1'] : "" );
                $cart->ST['address_2'] = $session->get('svea_address_2', !empty($cart->ST['address_2']) ? $cart->ST['address_2'] : "");
                $cart->ST['zip'] = $session->get('svea_zipCode', !empty($cart->ST['zip']) ? $cart->ST['zip'] : "");
                $cart->ST['city'] = $session->get('svea_locality', !empty($cart->ST['city']) ? $cart->ST['city'] : "");
                $cart->ST['virtuemart_country_id'] =
                $session->get('svea_virtuemart_country_id', !empty($cart->BT['virtuemart_country_id']) ? $cart->BT['virtuemart_country_id'] : $countryId);
            }
            // keep other cart attributes, if set. also, vm does own validation on checkout.
            return true;
        }


	/**
	 * This method is fired when showing when prnting an Order
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

            if (!($paymentTable = $this->_getInternalData ($_formData->virtuemart_order_id))) {
                return '';
            }
            //get countrycode
                $q = 'SELECT `virtuemart_country_id` FROM #__virtuemart_order_userinfos  WHERE virtuemart_order_id=' . $_formData->virtuemart_order_id;
                $db = JFactory::getDBO();
                $db->setQuery($q);
                $country_id = $db->loadResult();
                $country = ShopFunctions::getCountryByID($country_id, 'country_2_code');
                $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
            //Deliver order
            if($_formData->order_status == $method->status_shipped){
                try {
                $svea = WebPay::deliverOrder($sveaConfig)
                                ->setOrderId($paymentTable->svea_order_id)
                                ->setOrderDate(date('c'))
                                ->setCountryCode($country)
                                ->setInvoiceDistributionType(DistributionType::POST)
                                        ->deliverPaymentPlanOrder()
                                            ->doRequest();
                } catch (Exception $e) {
                    vmError ($e->getMessage (), $e->getMessage ());
                    return FALSE;
                }
                 if($svea->accepted == 1){

                    $query = 'UPDATE #__virtuemart_payment_plg_sveapaymentplan
                            SET `svea_contract_number` = "' . $svea->contractNumber . '"' .
                            'WHERE `order_number` = "' . $paymentTable->order_number.'"';

                    $db->setQuery($query);
                    $db->query();
                    return TRUE;
                 } else {
                    vmError ('Svea Error '. $svea->resultcode . ' : ' .$svea->errormessage, 'Svea Error '. $svea->resultcode . ' : ' .$svea->errormessage);
                    return FALSE;
                 }
            //Cancel order
            } elseif ($_formData->order_status == $method->status_denied) {
                    try {
                        $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
                        $svea = WebPayAdmin::cancelOrder($sveaConfig)
                                ->setOrderId($paymentTable->svea_order_id)
                                ->setCountryCode($country)
                                ->cancelPaymentPlanOrder()
                                    ->doRequest();

                    } catch (Exception $e) {
                        vmError ('Svea error: '.$e->getMessage () . ' Order was not cancelled.', 'Svea error: '.$e->getMessage () . ' Order was not cancelled.');
                        return FALSE;
                    }
                     if($svea->accepted == 1){
                        return TRUE;
                     } else {
                        vmError ('Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage, 'Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage);
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
	 print_r('inne i uppdatera linje');die;
//            return null;
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
	  print_r('inne i linje FE');die;
//            return null;
	}

        public function plgVmOnUpdateSingleItem ($table,&$orderdata) {
             print_r('inne i single ');die;
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
        $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
        $returnArray = array();
        //Get address request
        if(JRequest::getVar('type') == 'getAddress'){
            try {
              $svea = WebPay::getAddresses($sveaConfig);
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

                    $returnArray =  array(
                        "fullName"  => $name,
                        "firstName" => $ci->firstName,
                        "lastName" => $ci->lastName,
                        "street"    => $ci->street,
                        "address_2" => $ci->coAddress,
                        "zipCode"  => $ci->zipCode,
                        "locality"  => $ci->locality,
                        "addressSelector" => $ci->addressSelector,
                        "virtuemart_country_id" => ShopFunctions::getCountryIDByName(JRequest::getVar('countrycode'))
                    );
                 }
            }
        }

        //Get payment plan params request
        elseif(JRequest::getVar('type') == 'getParams'){
              if (!class_exists ('CurrencyDisplay')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		}
            $svea_params = WebPay::getPaymentPlanParams($sveaConfig);
            try {
                 $svea_params = $svea_params->setCountryCode(JRequest::getVar('countrycode'))
                    ->doRequest();
            } catch (Exception $e) {
                 vmError ($e->getMessage (), $e->getMessage ());
                    return NULL;
            }
          if(isset($svea_params->errormessage) === true && $svea_params->errormessage !== ''){
                $returnArray = array("svea_error" => "Svea error: " .$svea_params->errormessage);
            } else {
                $price = JRequest::getVar('sveacarttotal');

                $CurrencyCode = SveaHelper::getCurrencyCodeByCountry(JRequest::getVar('countrycode'));
                $currencyId = ShopFunctions::getCurrencyIDByName($CurrencyCode);

                $paymentCurrency = CurrencyDisplay::getInstance($currencyId);
                $formattedPrice   = $paymentCurrency->convertCurrencyTo($currencyId,$price,FALSE);

                $campaigns = WebPay::paymentPlanPricePerMonth($formattedPrice, $svea_params,$currencyId);
                $display = SveaHelper::getCurrencySymbols($currencyId);

                if(sizeof($campaigns->values) > 0){
                    foreach ($campaigns->values as $cc){
                    $returnArray[] = array("campaignCode" => $cc['campaignCode'],
                                            "description" => $cc['description'],
                                            "price_per_month" => round($cc['pricePerMonth'],$display[0]->currency_decimal_place) ." ". $display[0]->currency_symbol. "/",
                                            "per_month" => JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_MONTH")
                                        );

                    }
                }else{
                     $returnArray = array("svea_error" => "error");
                }

            }

        }

        echo json_encode($returnArray);
        jexit();
    }

    /**
     * Sveafix: Javascript vars needs to have unique names, therefore we use arrays with unique keys instead.
     * @param type $paymentId
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
                            <input type="text" id="svea_ssn_'.$paymentId.'" name="svea_ssn_'.$paymentId.'" value="'.$session->get("svea_ssn_$paymentId").'" class="required" />
                            <span id="svea_getaddress_starred_'.$paymentId.'" style="color: red; "> * </span>
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
                $inputFields .=  JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_INITIALS").': <input type="text" id="svea_initials_'.$paymentId.'" value="'.$session->get("svea_initials_$paymentId").'" name="svea_initials_'.$paymentId.'" class="required" /><span style="color: red; "> * </span>';
            }
        }

        // pass along the selected method country (used in plgVmOnSelectCheckPayment when checking that all required fields are set)
        $inputFields .=
            '<input type="hidden" id="svea_countryCode_'.$paymentId.'" value="'.$countryCode.'" name="svea_countryCode_'.$paymentId.'" />
        ';

        // show getAddressButton, if applicable
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
        $per_month = JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_MONTH");
        $error_params =  $error = JText::sprintf("VMPAYMENT_SVEA_DD_NO_CAMPAIGN_ON_AMOUNT");
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
                                    jQuery('#svea_getaddress_error_$paymentId').empty().append('<br>$error_params').show();
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
                                       jQuery('#svea_params_div_$paymentId').append('<li><input type=\"radio\" name=\"svea_campaigncode_$paymentId\" value=\"'+value.campaignCode+'\" '+checkedCampaign+'>&nbsp<strong>'+value.description+'</strong> ('+value.price_per_month+'".$per_month.")</li>');
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
                            jQuery('#svea_getaddress_starred_$paymentId').hide();
                        }else{
                            jQuery('#svea_getaddress_$paymentId').show();
                            jQuery('#svea_getaddress_starred_$paymentId').hide();
                        }
                    ";
        //toggle display form
        $html .=        '
                        jQuery("input[name=\'virtuemart_paymentmethod_id\']").change(function(){
                            checked_'.$paymentId.' = jQuery("input[name=\'virtuemart_paymentmethod_id\']:checked").val();
                            if(checked_'.$paymentId.' == sveaid_'.$paymentId.'){
                                  jQuery("#svea_getaddress_'.$paymentId.'").show();
                                  jQuery("#svea_getaddress_starred_'.$paymentId.'").show();
                            }else{
                                jQuery("#svea_getaddress_'.$paymentId.'").hide();
                                jQuery("#svea_getaddress_starred_'.$paymentId.'").hide();
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
                                var countrycode_$paymentId = '$countryCode';
                                var url_$paymentId = '$sveaUrlAjax';

                                jQuery.ajax({
                                    type: 'GET',
                                    data: {
                                        sveaid: sveaid_".$paymentId.",
                                        type: 'getAddress',
                                        svea_ssn: svea_ssn_".$paymentId.",
                                        countrycode: countrycode_".$paymentId."
                                    },
                                    url: url_".$paymentId.",

                                    // getAddress callback
                                    success: function(data){

                                        var json_$paymentId = JSON.parse(data);

                                        jQuery('#svea_getaddress_error_$paymentId').hide();
                                        if (json_".$paymentId.".svea_error){
                                            jQuery('#svea_getaddress_error_$paymentId').empty().append(' Svea Error: <br>'+json_".$paymentId.".svea_error).show();
                                        }
                                        else // handle response address data
                                        {
                                            jQuery('#svea_address_div_$paymentId').empty();

                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_addressSelector_".$paymentId."\" name=\"svea_addressSelector_".$paymentId."\" value=\"'+json_".$paymentId.".addressSelector+'\" />'
                                            );

                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_firstName_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_firstName_".$paymentId."\" value=\"'+json_".$paymentId.".firstName+'\" />'
                                            );
                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_lastName_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_lastName_".$paymentId."\" value=\"'+json_".$paymentId.".lastName+'\" />'
                                            );
                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_fullName_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_fullName_".$paymentId."\" value=\"'+json_".$paymentId.".fullName+'\" />'
                                            );
                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_street_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_street_".$paymentId."\" value=\"'+json_".$paymentId.".street+'\" />'
                                            );
                                           jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_address_2_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_address_2_".$paymentId."\" value=\"'+json_".$paymentId.".address_2+'\" />'
                                            );
                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_zipCode_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_zipCode_".$paymentId."\" value=\"'+json_".$paymentId.".zipCode+'\" />'
                                            );
                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_locality_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_locality_".$paymentId."\" value=\"'+json_".$paymentId.".locality+'\" />'
                                            );
                                            jQuery('#svea_address_div_$paymentId').append(
                                                '<input type=\"hidden\" id=\"svea_'+json_".$paymentId.".addressSelector+'_virtuemart_country_id_".$paymentId."\" name=\"svea_'+json_".$paymentId.".addressSelector+'".$paymentId."'+'_virtuemart_country_id_".$paymentId."\" value=\"'+json_".$paymentId.".virtuemart_country_id+'\" />'
                                            );

                                            jQuery('#svea_address_div_$paymentId').append(          // show individual address
                                                    '<strong>'+json_$paymentId".".firstName+' '+
                                                    json_$paymentId".".lastName+'</strong><br> '+
                                                    json_$paymentId".".street+' <br> '+
                                                    json_$paymentId".".zipCode+' '+json_$paymentId".".locality
                                           );
                                            jQuery('#svea_address_div_$paymentId').show();
                                            jQuery('#svea_getaddress_error_$paymentId').hide();
                                        }
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
