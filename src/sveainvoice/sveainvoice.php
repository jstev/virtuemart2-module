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

    /**
     *  note on workaround for VM2 (2.0.24a-26a(current) ) discount tax calculations

        Invoicefee contributes to subTotal when weighting tax classes => discount tax is wrong.

        Invoice fee: invoice fee is included in the cart subTotal when VM calculates the "percentage" attribute, by which the discount amount is weighted.

        Example
        cart includes
        1* product priced at 100 @25%
        Shipping is 4 @25%
        Coupon for 12,5 off (10% or fixed 12,5 off)
        Cart shows 117,5, tax given as 23,5 => all is well
        Choose invoice payment
        Invoice fee of 29 @25% is added.
        Cart now shows 153,75 (ok), tax given as 30,17 (should be 30,75)

        This is due to discountTaxAmount being -3,08 instead of -2,5 in the cart. discountTaxAmount is set in calculationh.php on row 873, where it is scaled by the "percentage" factor. Percentage is calculated as the taxClass subtotal / the cart salesprice which becomes 1,232 when the invoicefee of 29 is added to subTotal.

        ## Invoice payments: discount vat calculation error
        There's a bug in how VirtueMart calculates the discount vat when Svea Invoicefee applies to an order. The bug involves the discount vat amount being scaled due to the invoice fee being included with the subtotal. The sums are correct, but the vat tax is wrong. To avoid this, use the below invoice vat workaround.

        Workaround: Create a separate tax rule to use for invoice fee. In VM2 Admin, go to Products/Taxes & Calculation rules. Add a new rule with the following:
        "Vat tax per product", "+%", <your vat rate>. Then go to Shop/Payment methods and under Svea Invoice set VMPAYMENT_SVEA_TAX to use this rule. Discount vat will now be correct on checkout.


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

    class plgVmPaymentSveainvoice extends vmPSPlugin {

            function __construct(& $subject, $config) {
                    parent::__construct($subject, $config);
                    // 		vmdebug('Plugin stuff',$subject, $config);
                    $this->_loggable   = true;
                    $this->tableFields = array_keys($this->getTableSQLFields());

                    $varsToPush = $this->getVarsToPush();
                    $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);



            }

            /**

             * Svea adds new columns if table already exists
             * @author Val?rie Isaksen
             */
            public function getVmPluginCreateTableSQL() {
                //is there already a svea table for this payment?
                $q = 'SHOW TABLES LIKE "%sveainvoice%"';
                $db = JFactory::getDBO();
                $db->setQuery($q);
                $table_exists = $db->loadResult();
                //if there is add columns
                if(is_string($table_exists) && strpos($table_exists, 'sveainvoice') != FALSE){
                    //get all columns and check for the new ones
                    $q = 'SHOW COLUMNS FROM '.$table_exists;
                    $db->setQuery($q);
                    $columns = $db->loadAssocList();
                   $svea_order_id = FALSE;
                   $svea_invoice_id = FALSE;
                   $svea_creditinvoice_id = FALSE;
                    foreach ($columns as $column) {
                        if(in_array( 'svea_order_id',$column)){
                            $svea_order_id = TRUE;
                        }  elseif (in_array( 'svea_invoice_id',$column)) {
                            $svea_invoice_id = TRUE;
                        }  elseif (in_array( 'svea_creditinvoice_id',$column)) {
                            $svea_creditinvoice_id = TRUE;
                        }
                    }

                    $q1 = $svea_order_id ? '' : ' ADD svea_order_id INT(1) UNSIGNED';
                    $q2 = $svea_invoice_id ? '' : ' ADD svea_invoice_id VARCHAR(64)';
                    $q3 = $svea_creditinvoice_id ? '' : ' ADD svea_creditinvoice_id VARCHAR(64)';
                    //run if anything needs to be added
                    if($svea_order_id == false || $svea_invoice_id == false || $svea_creditinvoice_id == false ){
                        $query = "ALTER TABLE `" . $this->_tablename . "`" .
                        $q1 . (($q1 != '' && $q2 != '') ? ',' : '') .
                        $q2 . (($q2 != '' && $q3 != '') ? ',' : '') .
                        $q3;
                        $db->setQuery($query);
                        $db->query();
                    }

                }
                return $this->createTableSQL('Payment Svea Invoice Table');
            }

            /**
             * Fields to create the payment table
             * @return string SQL Fileds
             */
            function getTableSQLFields() {
                    $SQLfields = array(
                            'id'                            => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
                            'virtuemart_order_id'           => 'int(1) UNSIGNED',
                            'order_number'                  => 'char(64)',
                            'virtuemart_paymentmethod_id'   => 'mediumint(1) UNSIGNED',
                            'payment_name'                  => 'varchar(5000)',
                            'payment_order_total'           => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
                            'payment_currency'              => 'char(3)',
                            'cost_per_transaction'          => 'decimal(10,2)',
                            'tax_id'                        => 'smallint(1)',

                            'svea_order_id'                 => 'int(1) UNSIGNED',
                            'svea_invoice_id'               => 'varchar(64)',
                            'svea_creditinvoice_id'         => 'varchar(64)',
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
                    //while processing set to pending
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
                    $this->getPaymentCurrency($method);

                    $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
                    $db = JFactory::getDBO();
                    $db->setQuery($q);
                    $currency_code_3        = $db->loadResult();
                    $paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
                    $totalInPaymentCurrency = $paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false);
                    $cd                     = CurrencyDisplay::getInstance($cart->pricesCurrency);

                //Svea Create order
                $sveaConfig = "";
                try {
                    $sveaConfig = $method->testmode == TRUE ? new SveaVmConfigurationProviderTest($method) : new SveaVmConfigurationProviderProd($method);
                    $svea = WebPay::createOrder($sveaConfig);
               } catch (Exception $e) {
                    vmError ($e->getMessage (), $e->getMessage ());
                    return NULL;
               }
                //set order items
                $svea = SveaHelper::formatOrderRows($svea, $order,$method->payment_currency);
                //set invoice fee
                $svea = SveaHelper::formatInvoiceFee($svea,$order,$method->payment_currency);
                //set shipping
                $svea = SveaHelper::formatShippingRows($svea,$order,$method->payment_currency);
                //add coupons
                $svea = SveaHelper::formatCoupon($svea,$order,$method->payment_currency);
                $countryId = $order['details']['BT']->virtuemart_country_id;
                if(isset($countryId) == FALSE){
                    return;
                }
                $countryCode = shopFunctions::getCountryByID($countryId,'country_2_code');
                //add customer
                $svea = SveaHelper::formatCustomer($svea,$order,$countryCode);

                try {
                    $svea = $svea
                          ->setCountryCode($countryCode)
                          ->setCurrency($currency_code_3)
                          ->setClientOrderNumber($order['details']['BT']->order_number)
                          ->setOrderDate(date('c'))
                          ->useInvoicePayment()
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
                    if($countryCode == "NO" || $countryCode == "DK" || $countryCode == "NL"){
                        $logoImg = "http://cdn.svea.com/sveafinans/rgb_svea-finans_small.png";
                    } else {
                        $logoImg = "http://cdn.svea.com/sveaekonomi/rgb_ekonomi_small.png";
                    }
                    $html =  '<img src="'.$logoImg.'" /><br /><br />';
                    $html .= '<div class="vmorder-done">' . "\n";
                    $html .= '<div class="vmorder-done-payinfo">'.JText::sprintf('VMPAYMENT_SVEA_INVOICE').'</div>';
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
                    $order['comments'] = 'Order Created at Svea. Svea orderId: '.$svea->sveaOrderId;
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
             *
             * Display stored payment data for an order
             *
             */
            function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
                if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL;
		} // Another method was selected, do nothing
                if (!($paymentTable = $this->_getInternalData ($virtuemart_order_id))) {
			return '';
		}
                    $html = '<table class="adminlist">' . "\n";
                    $html .= $this->getHtmlHeaderBE();
                    $html .= $this->getHtmlRowBE( 'VMPAYMENT_SVEA_PAYMENTMETHOD',$paymentTable->payment_name);
                    $html .= $this->getHtmlRowBE('VMPAYMENT_SVEA_INVOICEFEE',$paymentTable->cost_per_transaction);
                    $html .= $this->getHtmlRowBE('Approved amount',$paymentTable->svea_approved_amount);
                    $html .= $this->getHtmlRowBE('Expiration date',$paymentTable->svea_expiration_date);
                    $html .= $this->getHtmlRowBE('Svea order id',$paymentTable->svea_order_id);
                    $html .= $this->getHtmlRowBE('Svea invoice id',$paymentTable->svea_invoice_id);
                    $html .= $this->getHtmlRowBE('Svea credit invoice id',$paymentTable->svea_creditinvoice_id);
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
             * getCosts() will return the invoice fee for Svea Invoice payment method
             * @override
             *
             * @param VirtueMartCart $cart
             * @param type $method
             * @param type $cart_prices
             * @return type cost_per_transaction -- as defined by invoice config setting (should be given ex vat)
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
                return true;
            }

            foreach ($this->methods as $method) {
                 if($method->product_display == "1"){

                $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
                $price   = $paymentCurrency->convertCurrencyTo($method->payment_currency,$product->prices['salesPrice'],FALSE);
                $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();
                $currency_decimals = $currency_code_3 == 'EUR' ? 1 : 0;
                $display = SveaHelper::getCurrencySymbols($method->payment_currency);
                if( sizeof($method->countries)== 1 ) // single country configured in payment method, use this for unregistered users
                {
                    $country = ShopFunctions::getCountryByID($method->countries[0],'country_2_code');
                } else {
                    return;
                }
                if($price >= $method->min_amount_product ){
                    $lowest_to_pay = $this->svea_get_invoice_lowest($country);//TODO:hämta countrycode med id:$method->countries
                    $prices = array();
                    $prices[] = '<h4 style="display:block;  list-style-position:outside; margin: 5px 10px 10px 10px">'.
                         JText::sprintf("VMPAYMENT_INVOICE").'</h4>';
                    $price = $product->prices['salesPrice'] * 0.03 < $lowest_to_pay ? $lowest_to_pay : $product->prices['salesPrice'] * 0.03;

                    $prices[] = '<div class="svea_product_price_item" style="display:block; margin: 5px 10px 10px 10px">
                                    <div style="float:left;">'.
                                    JText::sprintf("VMPAYMENT_SVEA_TEXT_PRODUCT_DESC").
                                   '</div>
                                    <div style="color: #002A46;
                                                width:90%;
                                                margin-left: 80px;
                                                margin-right: auto;
                                                float:left;">
                                        <strong >'.
                                          round($price,$currency_decimals)." ".$display[0]->currency_symbol.
                                        '</strong>
                                    </div>
                                </div>';


                    $view = array();
                    $view['logo_background'] = ($country == "NO" || $country == "DK" || $country == "NL") ? "svea_finans_background" : "svea_background";
                    $view['price_list'] = $prices;
                    $view['lowest_price'] =  round($price,$currency_decimals);
                    $view['currency_display'] =  $display[0]->currency_symbol;
                    $view['line'] = '<img width="163" height="1" src="'. JURI::root(TRUE) . '/plugins/vmpayment/svealib/assets/images/svea/grey_line.png" />';
                    $view['text_from'] = JText::sprintf("VMPAYMENT_SVEA_TEXT_FROM")." ";

                 //check if paymentplan is activated and if product_display is activated!
                    $q  = 'SELECT `payment_params` FROM `#__virtuemart_paymentmethods` WHERE `published`=1 AND `payment_element`= "sveapaymentplan" ';
                    $db = JFactory::getDBO();
                    $db->setQuery($q);
                    $paymentplanInfo = $db->loadResult();
                    $paymentplanArray = explode("|", $paymentplanInfo);
                    $paymentPlanProductActive = 0;
                    foreach ($paymentplanArray as $key => $value) {
                        $pair = explode("=", $value);
                        if($pair[0] == "product_display" && $pair[1] == '"1"'){
                            $paymentPlanProductActive = 1;
                        }

                    }
                     $session = JFactory::getSession();
                     $paymentplan_view = $session->get('svea_paymentplan_product_price');
                     //have not rendered paymentplan yet
                     if($paymentplan_view == NULL ){
                         //is using product display in paymentplan
                         if($paymentPlanProductActive == 1){
                             //fill with invoice, but do not publish
                              $session->set('svea_invoice_product_price',$view);
                             //only use invoice for display
                         }  else {
                             $viewProduct['svea_invoice'] = $view;
                             $sveaString = $this->renderByLayout('productprice_layout', $viewProduct, 'svealib', 'payment');
                             $productDisplay[] = $sveaString;
                         }


                     }elseif ($paymentplan_view != NULL) {
                         $viewProduct['svea_paymentplan'] = $paymentplan_view;
                         $viewProduct['svea_invoice'] = $view;
                         $sveaString = $this->renderByLayout('productprice_layout', $viewProduct, 'svealib', 'payment');
                         //loads template in Vm product display
                         $session->clear('svea_paymentplan_product_price');
                         $productDisplay[] = $sveaString;

                     //empty paymentplan
                     }
                    }

                }

            }
            return TRUE;
	}

         private function svea_get_invoice_lowest($svea_country_code) {
        switch ($svea_country_code) {
            case "SE":
                return 50;
                break;
            case "NO":
                return 100;
                break;
            case "FI":
                return 10;
                break;
            case "DK":
                return 100;
                break;
            /** not yet available
            case "NL":
                return 100;
                break;
            case "DE":
                return 100;
                break;
             *
             */

            default:
                break;
        }
    }

            /**
             *
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
                        return FALSE;
                    }
                    $session = JFactory::getSession();
                    $this->saveDataFromSelectPayment( JRequest::get(), $session );  // store passed credentials in session
                    $this->populateBillToFromGetAddressesData( $cart,$session ); // set BT address with passed data
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

                $countryCode = $request['svea__countryCode__'.$methodId];
                $customerType = $request['svea__customertype__'.$methodId];

                //prepare errormessage
                // getAddress countries need the addressSelector
                if( $countryCode == 'SE' ||
                    $countryCode == 'DK' ||
                    ($countryCode == 'NO' && $customerType == 'svea_invoice_customertype_company')
                )
                {
                    if( !array_key_exists( "svea__addressSelector__".$methodId, $request ) )    // no addresselector => did not press getAddress
                    {
                        throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
                    }
                }

                // FI, NO private need SSN
                if( $countryCode == 'FI' ||
                    ($countryCode == 'NO' && $customerType == 'svea_invoice_customertype_private')
                )
                {
                    if( $request["svea__ssn__".$methodId] == "" ) // i.e. field was left blank
                    {
                        throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
                    }
                }

                // DE, NL need address fields, but that is checked by virtuemart (BillTo), so no worry of ours
                // DE, NL need birthdate
                if( ($countryCode == 'DE' && $customerType == 'svea_invoice_customertype_private') ||
                    ($countryCode == 'NL' && $customerType == 'svea_invoice_customertype_private')
                )
                {
                    if( ( $request["svea__birthday__".$methodId] == "" ) ||
                        ( $request["svea__birthmonth__".$methodId] == "" ) ||
                        ( $request["svea__birthyear__".$methodId] == "" )
                    )
                    {
                        throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
                    }
                }
                if( ($countryCode == 'NL' && $customerType == 'svea_invoice_customertype_private')
                )
                {
                    if( ( $request["svea__initials__".$methodId] == "" )
                    )
                    {
                        throw new Exception( JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS"));
                    }
                }
                if( ($countryCode == 'NL' && $customerType == 'svea_invoice_customertype_company')
                )
                {
                    if( ( $request["svea__ssn__".$methodId] == "" )
                    )
                    {
                       throw new Exception(JText::sprintf("VMPAYMENT_SVEA_TEXT_REQUIRED_FIELDS") );
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
                $countryCode = $request['svea__countryCode__'.$methodId];
                $customerType = $request['svea__customertype__'.$methodId];
                $svea_prefix = "svea";
                foreach ($request as $key => $value) {
                    $svea_key = "";
                    $request_explode = explode('__', $key);
                    //if this is svea's and it is the selected method
                    if(( $request_explode[0] == $svea_prefix) && $methodId == $request_explode[2])     // store keys in the format "svea_xxx"
                    {
                        // trim addressSelector, methodId
                        $svea_attribute = $request_explode[1]; //substr($key, strlen($svea_prefix)+1, -(strlen(strval($methodId))+1) ); // svea_xxx_## => xxx
                        $svea_prefix = $request_explode[0]; //$svea_prefix."_".$svea_attribute;

                     $session->set($svea_prefix."_".$svea_attribute, $value);
                    //methodId wasn't the last param, therefore probably an addresselector
                    }  elseif (( $request_explode[0] == $svea_prefix) && $methodId == $request_explode[3]) {
                                                // getAddress countries have the addressSelector address fields set
                        if( $countryCode == 'SE' ||
                            $countryCode == 'DK' ||
                            ($countryCode == 'NO' && $customerType == 'svea_invoice_customertype_company')
                        )
                        {
                            $svea_attribute = $request_explode[2];
                        }
                     $session->set($svea_prefix."_".$svea_attribute, $value);
                    }

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
                foreach ($this->methods as $method)
                {
                    if ($this->checkConditions ($cart, $method, $cart->pricesUnformatted))
                    {
                        $methodSalesPrice = $this->calculateSalesPrice ($cart, $method, $cart->pricesUnformatted);
                        //svea convert to paymentmethod currency to display invoicefee instead
                        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
                        $formattedMethodSalesPrice = $paymentCurrency->convertCurrencyTo($method->payment_currency,$methodSalesPrice,FALSE);

                        $method->$method_name = $this->renderPluginName ($method);
                        $html_string = $this->getPluginHtml ($method, $selected, $formattedMethodSalesPrice);
                        //include svea stuff on editpayment page
                        if(isset( $cart->BT['virtuemart_country_id']))  // BillTo is set, so use country (i.e registered user)
                        {
                            $countryId =  $cart->BT['virtuemart_country_id'];
                        }
                        elseif( sizeof($method->countries)== 1 ) // single country configured in payment method, use this for unregistered users
                        {
                            $countryId = $method->countries[0];
                        }
                        else //empty or several countries configured in payment method
                        {
                            return FALSE; //do not know what country, therefore don´t know what fields to show.
                        }
                        $countryCode = shopFunctions::getCountryByID($countryId,'country_2_code');
                        $html_string .= $this->getSveaGetAddressHtml($method->virtuemart_paymentmethod_id,$countryCode,$method->shipping_billing);
                        $html[] = $html_string;
                        //svea stuff end
                    }
                }
                if(!empty($html))
                {
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
			if (!is_array ($logo_list)) {
				$logo_list = (array)$logo_list;
			}
			foreach ($logo_list as $logo) {
                            //get logo from cdn even if logo from folder is selected
                            if($logo == 'SveaFinans.png'){
                                $url = "http://cdn.svea.com/sveafinans/rgb_svea-finans_small.png";
                            } else {
                                $url = "http://cdn.svea.com/sveaekonomi/rgb_ekonomi_small.png";
                            }
				$alt_text = substr ($logo, 0, strpos ($logo, '.'));
				$img .= '<span class="vmCartPaymentLogo" ><img height="50" align="middle" src="' . $url . '"  alt="' . $alt_text . '" /></span> ';
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


            public function plgVmOnCheckoutCheckDataPayment( VirtueMartCart $cart ) {
                $session = JFactory::getSession();
                $this->populateBillToFromGetAddressesData( $cart,$session );
                return true;
            }


            /**
             * Fills in cart billto address from  getAddresses data stored in the session
             * @param VirtueMartCart $cart
             * @param JSession $session
             * @return boolean
             */
            private function populateBillToFromGetAddressesData( VirtueMartCart $cart,$session )
            {
                if (!($method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                    return NULL; // Another method was selected, do nothing
                }
                if (!$this->selectedThisElement($method->payment_element)) {
                    return false;
                }
                $countryId = "";
                if( sizeof($method->countries)== 1 ) // single country configured in payment method, use this for unregistered users
                {
                    $countryId = $method->countries[0];
                }
                if( $cart->BT == 0 ) $cart->BT = array(); // fix for "uninitialised" BT

                if( $session->get('svea_customertype') == 'svea_invoice_customertype_company' )
                {
                    if($session->get('svea_fullName') != '' && $session->get('svea_fullName') != NULL) { $cart->BT['company'] = $session->get('svea_fullName'); }
                }

                if($session->get('svea_firstName') != '' && $session->get('svea_firstName') != NULL) { $cart->BT['first_name'] = $session->get('svea_firstName'); }
                if($session->get('svea_lastName') != '' && $session->get('svea_lastName') != NULL) { $cart->BT['last_name'] = $session->get('svea_lastName'); }
                if($session->get('svea_street') != '' && $session->get('svea_street') != NULL) { $cart->BT['address_1'] = $session->get('svea_street'); }
                if($session->get('svea_address_2') != '' && $session->get('svea_address_2') != NULL) { $cart->BT['address_2'] = $session->get('svea_address_2'); }
                if($session->get('svea_zipCode') != '' && $session->get('svea_zipCode') != NULL) { $cart->BT['zip'] = $session->get('svea_zipCode'); }
                if($session->get('svea_locality') != '' && $session->get('svea_locality') != NULL) { $cart->BT['city'] = $session->get('svea_locality'); }

//                $cart->BT['virtuemart_country_id'] =
//                $session->get('svea_virtuemart_country_id', !empty($cart->BT['virtuemart_country_id']) ? $cart->BT['virtuemart_country_id'] : $countryId);
               //ship to if module setup says so if Vm-customer setting not already will do that for us
                if(isset($method) && $method->shipping_billing == '1' && $cart->STsameAsBT == 0){
                    if( $cart->ST == 0 ) $cart->ST = array(); // fix for "uninitialised" ST

                    if( $session->get('svea_customertype') == 'svea_invoice_customertype_company' )
                    {
                        if($session->get('svea_fullName') != '' && $session->get('svea_fullName') != NULL) { $cart->ST['company'] = $session->get('svea_fullName'); }

                    }

                    if($session->get('svea_firstName') != '' && $session->get('svea_firstName') != NULL) { $cart->ST['first_name'] = $session->get('svea_firstName'); }
                    if($session->get('svea_lastName') != '' && $session->get('svea_lastName') != NULL) { $cart->ST['last_name'] = $session->get('svea_lastName'); }
                    if($session->get('svea_street') != '' && $session->get('svea_street') != NULL) { $cart->ST['address_1'] = $session->get('svea_street'); }
                    if($session->get('svea_address_2') != '' && $session->get('svea_address_2') != NULL) { $cart->ST['address_2'] = $session->get('svea_address_2'); }
                    if($session->get('svea_zipCode') != '' && $session->get('svea_zipCode') != NULL) { $cart->ST['zip'] = $session->get('svea_zipCode'); }
                    if($session->get('svea_locality') != '' && $session->get('svea_locality') != NULL) { $cart->ST['city'] = $session->get('svea_locality'); }

//                    $cart->ST['virtuemart_country_id'] =
//                    $session->get('svea_virtuemart_country_id', !empty($cart->ST['virtuemart_country_id']) ? $cart->ST['virtuemart_country_id'] : $countryId);
                }
//                // keep other cart attributes, if set. also, vm does own validation on checkout.
                return true;
            }

            /**
             * This method is fired when showing when printing an Order
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
                                    ->setInvoiceDistributionType($method->distributiontype)
                                            ->deliverInvoiceOrder()
                                                ->doRequest();
                    } catch (Exception $e) {
                        vmError ('Svea error: '.$e->getMessage () . ' Order was not delivered.','Svea error: '.$e->getMessage () . ' Order was not delivered.');
                        return FALSE;
                    }
                     if($svea->accepted == 1){
                        $query = 'UPDATE #__virtuemart_payment_plg_sveainvoice
                                SET `svea_invoice_id` = "' . $svea->invoiceId . '"' .
                                'WHERE `order_number` = "' . $paymentTable->order_number.'"';

                        $db->setQuery($query);
                        $db->query();
                        return TRUE;
                     } else {
                        vmError ('Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not delivered.', 'Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not delivered.');
                        return FALSE;
                     }
                //Cancel order
                } elseif ($_formData->order_status == $method->status_denied) {
                    try {
                        $svea = WebPayAdmin::cancelOrder($sveaConfig)
                                ->setOrderId($paymentTable->svea_order_id)
                                ->setCountryCode($country)
                                ->cancelInvoiceOrder()
                                    ->doRequest();

                    } catch (Exception $e) {
                        vmError ('Svea error: '.$e->getMessage () . ' Order was not cancelled.', 'Svea error: '.$e->getMessage () . ' Order was not cancelled.');
                        return FALSE;
                    }
                     if($svea->accepted == 1){
                        return TRUE;
                     } else {
                        vmError ('Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not cancelled.', 'Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not cancelled.');
                        return FALSE;
                     }
                // Refund order
                } elseif ($_formData->order_status == 'R') {
                    try {
                       $svea_query = WebPayAdmin::queryOrder($sveaConfig)
                                    ->setOrderId($paymentTable->svea_order_id)
                                    ->setCountryCode($country)
                                    ->queryInvoiceOrder()
                                        ->doRequest();
                    } catch (Exception $e) {
                        vmError ('Svea error: '.$e->getMessage () . ' Order was not refunded.', 'Svea error: '.$e->getMessage () . ' Order was not refunded.');
                        return FALSE;
                    }
                    if($svea_query->accepted != 1){
                        vmError ('Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not refunded.', 'Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not refunded.');
                        return FALSE;
                    }
                    $row_numbers = array();
                    foreach ($svea_query->numberedOrderRows as $value) {
                        $row_numbers[] = $value->rowNumber;
                    }
                    try {
                        $svea = WebPayAdmin::creditOrderRows($sveaConfig)
                                ->setCountryCode($country)
                                ->setRowsToCredit($row_numbers)
                                ->addNumberedOrderRows($svea_query->numberedOrderRows)
                                ->setInvoiceId($paymentTable->svea_invoice_id)
                                ->setInvoiceDistributionType($method->distributiontype)
                                 ->creditInvoiceOrderRows()
                                    ->doRequest();
                    } catch (Exception $e) {
                        vmError ('Svea error: '.$e->getMessage () . ' Order was not refunded.', 'Svea error: '.$e->getMessage () . ' Order was not refunded.');
                        return FALSE;
                    }
                    if($svea->accepted == TRUE) {
                         $query = 'UPDATE #__virtuemart_payment_plg_sveainvoice
                                SET `svea_creditinvoice_id` = "' . $svea->creditInvoiceId . '"' .
                                'WHERE `order_number` = "' . $paymentTable->order_number.'"';

                        $db->setQuery($query);
                        $db->query();
                        return TRUE;
                    }  else {
                        vmError ('Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not refunded.', 'Svea Error: '. $svea->resultcode . ' : ' .$svea->errormessage . ' Order was not refunded.');
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

            function plgVmOnPaymentResponseReceived(&$virtuemart_order_id, &$html) {
                return NULL;
            }
             *
             */

        /**
         * will catch ajaxcall
         * Svea getAddress->doRequest()
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
            if(JRequest::getVar('type') == 'getAddress'){
                try {
                  $svea = WebPay::getAddresses($sveaConfig)
                          ->setOrderTypeInvoice()
                          ->setCountryCode(JRequest::getVar('countrycode'));
                    if(JRequest::getVar('customertype')== "svea_invoice_customertype_company"){
                      $svea = $svea->setCompany(JRequest::getVar('svea_ssn'));
                    }  else {
                      $svea = $svea->setIndividual(JRequest::getVar('svea_ssn'));
                    }
                   $svea = $svea->doRequest();
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

                        $returnArray[] =  array(
                            "fullName"  => $name,
                            "firstName" => $ci->firstName,
                            "lastName" => $ci->lastName,
                            "street"    => $ci->street,
                            "address_2" => $ci->coAddress,
                            "zipCode"  => (string)$ci->zipCode,
                            "locality"  => $ci->locality,
                            "addressSelector" => $ci->addressSelector,
                            "virtuemart_country_id" => ShopFunctions::getCountryIDByName(JRequest::getVar('countrycode'))
                        );
                     }
                }
            }
            echo json_encode($returnArray);
            jexit();
            }

        /**
         * Svea GetAddress view for html and jQuery
         * @return string
         */
        public function getSveaGetAddressHtml($paymentId,$countryCode,$shipping_billing) {
            $session = JFactory::getSession();
            $inputFields = '';
            $getAddressButton = '';
            $checkedCompany = "";
            $checkedPrivate = "checked";
            if($session->get("svea__customertype_$paymentId")== "svea_invoice_customertype_company"){
                $checkedCompany = "checked";
                $checkedPrivate = "";
            }
            //show customerype for all
            $inputFields .= '
                <fieldset id="svea_customertype_div_'.$paymentId.'">
                    <div>
                    <label for= "svea_invoice_customertype_private">'.JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_PRIVATE").'</label>
                    <input type="radio" value="svea_invoice_customertype_private" name="svea__customertype__'.
                        $paymentId.'"'.$checkedPrivate.' id="svea_invoice_customertype_private" >
                    </div>
                    <div>
                    <label for= "svea_invoice_customertype_company">'.JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_COMPANY").'</label>
                    <input type="radio" value="svea_invoice_customertype_company" name="svea__customertype__'.
                        $paymentId.'"'.$checkedCompany.' id="svea_invoice_customertype_company">
                    </div>
                </fieldset>';

            // NORDIC credentials form fields
            // get ssn & selects private/company for SE, NO, DK, FI
            if(     $countryCode == "SE" ||
                    $countryCode == "DK" ||
                    $countryCode == "NO" ||
                    $countryCode == "FI")
            {
                $inputFields .=
                '
                    <fieldset id="svea_ssn_div_'.$paymentId.'">
                        <label id="svea_ssn_fieldset'.$paymentId.'" for="svea_ssn_'.$paymentId.'">'.JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_SS_NO").'</label>
                        <label id="svea_vat_fieldset'.$paymentId.'" for="svea_ssn_'.$paymentId.'" style="display:none" >'.JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_VATNO").'</label>
                        <input type="text" id="svea_ssn_'.$paymentId.'" name="svea__ssn__'.$paymentId.
                            '" value="'.$session->get("svea__ssn_$paymentId").'" class="required" />
                        <span id="svea_getaddress_starred_'.$paymentId.'" style="color: red; "> * </span>
                    </fieldset>
               ';
            }

            // EU credentials form fields
            // if customer is located in Netherlands or DE, get birth date
            elseif( $countryCode == "NL" ||
                    $countryCode == "DE")
            {
                //Days, to 31
                $days = "";
                $zero = "";
                for($d = 1; $d <= 31; $d++){
                    $selected = "";
                    $val = $d;
                    if($d < 10)
                        $val = "$d";
                    if($session->get("svea__birthday_$paymentId") == $val)
                        $selected = "selected";

                    $days .= "<option value='$val' $selected>$d</option>";
                }
                $birthDay = "<select name='svea__birthday__".$paymentId."' id='birthDay_".$paymentId."'>$days</select>";

                //Months to 12
                $months = "";
                for($m = 1; $m <= 12; $m++){
                    $selected = "";
                    $val = $m;
                    if($m < 10)
                        $val = "$m";

                    if($session->get("svea__birthmonth_$paymentId") == $val)
                      $selected = "selected";

                    $months .= "<option value='$val' $selected>$m</option>";
                }
                $birthMonth = "<select name='svea__birthmonth__".$paymentId."' id='birthMonth_".$paymentId."'>$months</select>";

                //Years from 1913 to date('Y')
                $years = '';

                for($y = 1913; $y <= date('Y'); $y++){
                    $selected = "";
                     if($session->get("svea__birthyear_$paymentId") == $y)
                        $selected = "selected";

                    $years .= "<option value='$y' $selected>$y</option>";
                }
                $birthYear = "<select name='svea__birthyear__".$paymentId."' id='birthYear_".$paymentId."'>$years</select>";

                $inputFields .=
                '
                    <fieldset id="svea_birthdate_'.$paymentId.'">
                         <label for="svea_birthdate_'.$paymentId.'">'.JText::sprintf ("VMPAYMENT_SVEA_FORM_TEXT_BIRTHDATE").'</label>'
                        . $birthDay . $birthMonth . $birthYear .
                    '</fieldset>
                ';

                // if customer is located in Netherlands, get initials
                if($countryCode == "NL"){
                    $inputFields .=
                      '<fieldset id="svea_nl_initials_fieldset_'.$paymentId.'">'.
                        JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_INITIALS").': <input type="text" id="svea_initials_'.$paymentId.'" value="'.
                            $session->get("svea__initials_$paymentId").'" name="svea__initials__'.$paymentId.'" class="required" /><span style="color: red; "> * </span>
                        </fieldset>';

                }
                if($countryCode == "NL" || $countryCode == "DE"){
                    $inputFields .=
                    '<fieldset id="svea_nl_de_vat_fieldset_'.$paymentId.'">
                        <label id="svea_nl_de_vat_label'.$paymentId.'" for="svea_ssn_'.$paymentId.'">'.JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_VATNO").'</label>
                        <input type="text" id="svea_nl_de_vat_'.$paymentId.'" name="svea__ssn__'.$paymentId.
                            '" value="'.$session->get("svea__ssn_$paymentId").'" class="required" />
                        <span style="color: red; "> * </span>
                    </fieldset>';
                }
            }

            // pass along the selected method country (used in plgVmOnSelectCheckPayment when checking that all required fields are set)
            $inputFields .=
                '<input type="hidden" id="svea_countryCode_'.$paymentId.'" value="'.$countryCode.'" name="svea__countryCode__'.$paymentId.'" />
            ';

            // show getAddressButton, if applicable
            if( $countryCode == "SE" ||
                $countryCode == "DK" ||
                $countryCode == "NO" )      // shown to company customers only
            {
                $getAddressButton =
                ' <fieldset>
                    <input type="button" id="svea_getaddress_submit_'.$paymentId.'" value="'.JText::sprintf("VMPAYMENT_SVEA_FORM_TEXT_GET_ADDRESS").'" />

                </fieldset>';
            }

            //box for form
            $html =
            '
                <fieldset id="svea_getaddress_'.$paymentId.'">
                    <input type="hidden" id="paymenttypesvea_'.$paymentId.'" value="'. $paymentId . '" />'
                    .$inputFields.
                    '<div id="svea_getaddress_error_'.$paymentId.'" style="color: red; "></div>'
                    .$getAddressButton.
                    '<div id="svea_address_div_'.$paymentId.'"></div>
                </fieldset>
                <input type="hidden" name="svea_shipping_billing" id="svea_shipping_billing_'.$paymentId.'" value="'.$shipping_billing.'" />
            ';
            /**
             * Adjustment to compatibility to RuposTel onestep plugin:
             * var name checked is overwritten. new var name is svea_picked
             */
            //hide show get address div
            $javascript =
            '   <script type="text/javascript">

                jQuery(document).ready(function($) {
                    var svea_picked_'.$paymentId.' = jQuery("input[name=\'virtuemart_paymentmethod_id\']:checked").val();

                    var sveaid_'.$paymentId.' = jQuery("#paymenttypesvea_'.$paymentId.'").val();
                    if(svea_picked_'.$paymentId.' != sveaid_'.$paymentId.'){
                        jQuery("#svea_getaddress_'.$paymentId.'").hide();
                        jQuery("#svea_getaddress_starred_'.$paymentId.'").hide();
                    }else{
                        jQuery("#svea_getaddress_'.$paymentId.'").show();
                        jQuery("#svea_getaddress_starred_'.$paymentId.'").hide();
                    }

                    jQuery("input[name=\'virtuemart_paymentmethod_id\']").change( function()
                    {
                        svea_picked_'.$paymentId.' = jQuery("input[name=\'virtuemart_paymentmethod_id\']:checked").val();
                        if(svea_picked_'.$paymentId.' == sveaid_'.$paymentId.'){
                            jQuery("#svea_getaddress_'.$paymentId.'").show();
                            jQuery("#svea_getaddress_starred_'.$paymentId.'").show();
                        }else{
                            jQuery("#svea_getaddress_'.$paymentId.'").hide();
                            jQuery("#svea_getaddress_starred_'.$paymentId.'").hide();
                        }
                    });
            ';
            /**
             * rupostel onestep adjustments, and probably for others too
             */
            $javascript .= '

                function rupostel_autofill_address(data,customer_type){
                    //if only one address in data
                    if(customer_type == "svea_invoice_customertype_company") {
                        if(data[0].fullName.length > 0) {
                            if($("#company_field").length > 0) { if(data[0].fullName.length > 0){ $("#company_field").val(data[0].fullName); } }
                        }
                    }
                    if($("#first_name_field").length > 0) { if(data[0].firstName.length > 0){ $("#first_name_field").val(data[0].firstName); } }
                    if($("#last_name_field").length > 0) { if(data[0].lastName.length > 0){ $("#last_name_field").val(data[0].lastName); } }
                    if($("#address_1_field").length > 0) { if(data[0].street.length > 0){ $("#address_1_field").val(data[0].street); } }
                    if($("#address_2_field").length > 0) { if(data[0].address_2.length > 0){ $("#address_2_field").val(data[0].address_2); } }
                    if($("#zip_field").length > 0) { if(data[0].zipCode.length > 0){ $("#zip_field").val(data[0].zipCode); } }
                    if($("#city_field").length > 0) { if(data[0].locality.length > 0){ $("#city_field").val(data[0].locality); } }
                    if(data[0].virtuemart_country_id.length > 0){ $("#virtuemart_country_id").val(data[0].virtuemart_country_id); }

                    if($("#svea_shipping_billing_'.$paymentId.'").val() == "1") {
                         if(customer_type == "svea_invoice_customertype_company") {
                            if(data[0].fullName.length > 0) {
                                if($("#shipto_company_field").length > 0) { if(data[0].fullName.length > 0){ $("#shipto_company_field").val(data[0].fullName); } }
                            }
                        }
                        if($("#shipto_first_name_field").length > 0) { if(data[0].firstName.length > 0){ $("#shipto_first_name_field").val(data[0].firstName); } }
                        if($("#shipto_last_name_field").length > 0) { if(data[0].lastName.length > 0){ $("#shipto_last_name_field").val(data[0].lastName); } }
                        if($("#shipto_address_1_field").length > 0) { if(data[0].street.length > 0){ $("#shipto_address_1_field").val(data[0].street); } }
                        if($("#shipto_address_2_field").length > 0) { if(data[0].address_2.length > 0){ $("#shipto_address_2_field").val(data[0].address_2); } }
                        if($("#shipto_zip_field").length > 0) { if(data[0].zipCode.length > 0){ $("#shipto_zip_field").val(data[0].zipCode); } }
                        if($("#shipto_city_field").length > 0) { if(data[0].locality.length > 0){ $("#shipto_city_field").val(data[0].locality); } }
                        if(data[0].virtuemart_country_id.length > 0){ $("#shipto_virtuemart_country_id").val(data[0].virtuemart_country_id); }
                        //trigger show shipment address so customer see it has changed.
                        if($("#sachone").length > 0){
                             $("#sachone").prop("checked", true);
                             var sa = $("#sachone").get(0);
                             $("#idsa").show();
                        }
                    }

                }';
            // change text on ssn on customer_type change
            $javascript .= "
                $('#svea_vat_fieldset".$paymentId."').hide();
                $('#svea_nl_de_vat_fieldset_".$paymentId."').hide();

               jQuery(\"input:radio[name='svea__customertype__".$paymentId."']\").click(function(){
                   var svea_picked_customertype_$paymentId =  jQuery(\"input:radio[name='svea__customertype__".$paymentId."']:checked\").val();
                    if (svea_picked_customertype_$paymentId == 'svea_invoice_customertype_private'){

                         $('#svea_ssn_fieldset".$paymentId."').show();
                         $('#svea_vat_fieldset".$paymentId."').hide();
                        //NL and DE
                         $('#svea_birthdate_".$paymentId."').show();
                        //nl
                        $('#svea_nl_de_vat_fieldset_".$paymentId."').hide();
                        $('#svea_nl_initials_fieldset_".$paymentId."').show();
                    }else{

                        $('#svea_ssn_fieldset".$paymentId."').hide();
                        $('#svea_vat_fieldset".$paymentId."').show();
                        //NL and DE
                        $('#svea_birthdate_".$paymentId."').hide();

                        //NL
                        $('#svea_nl_de_vat_fieldset_".$paymentId."').show();
                        $('#svea_nl_initials_fieldset_".$paymentId."').hide();
                    }
                });
            ";

            //ajax for getAddress
            $sveaUrlAjax = juri::root () . 'index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=sveainvoice';

            $javascript .=
            "
                jQuery('#svea_getaddress_submit_$paymentId').unbind('click').click(function (){
                    jQuery('#svea_ssn_$paymentId').removeClass('invalid');

                    var svea_ssn_$paymentId = jQuery('#svea_ssn_$paymentId').val();
                    var customertype_$paymentId = jQuery('#svea_customertype_div_".$paymentId." input:checked').val();

                    if(svea_ssn_$paymentId == '')
                    {
                        jQuery('#svea_ssn_$paymentId').addClass('invalid');".  // TODO translation for below required error?
                        "jQuery('#svea_getaddress_error_$paymentId').empty().append('Svea Error: * required');
                    }
                    else
                    {
                        var countrycode_$paymentId = '$countryCode';
                        var url_$paymentId = '$sveaUrlAjax';

                        jQuery.ajax({
                            type: 'GET',
                            data: {
                                sveaid: sveaid_$paymentId,
                                type: 'getAddress',
                                svea_ssn: svea_ssn_$paymentId,
                                customertype: customertype_$paymentId,
                                countrycode: countrycode_$paymentId
                            },
                            url: url_$paymentId,".

                            // callback for getaddress return
                            "success: function(data){
                                var json_$paymentId = JSON.parse(data);
                                if (json_$paymentId.svea_error) // display error
                                {
                                    jQuery('#svea_getaddress_error_$paymentId').empty().append('<br>'+json_$paymentId.svea_error).show();
                                }
                                else // handle response address data
                                {
                                     rupostel_autofill_address(json_$paymentId,customertype_$paymentId); //adjustment to fit rupostel plugin

                                        jQuery('#svea_address_div_$paymentId').empty().append(
                                            '<select id=\"sveaAddressDiv_$paymentId\" name=\"svea__addressSelector__$paymentId\"></select>'
                                        );

                                        jQuery.each(json_$paymentId,function(key,value){

                                            // show addressSelector dropdown with addresses
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<option value=\"'+value.addressSelector+'\">'+value.fullName+' '+value.street+
                                                    ' '+value.zipCode+' '+value.locality+'</option>'
                                            );

                                            // for each addressSelector, also store hidden address fields to pass on to next step
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_firstName\" name=\"svea__'+value.addressSelector+'__firstName__".$paymentId."\" value=\"'+value.firstName+'\" />'
                                            );
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_lastName\" name=\"svea__'+value.addressSelector+'__lastName__".$paymentId."\" value=\"'+value.lastName+'\" />'
                                            );
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_fullName\" name=\"svea__'+value.addressSelector+'__fullName__".$paymentId."\" value=\"'+value.fullName+'\" />'
                                            );
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_street\" name=\"svea__'+value.addressSelector+'__street__".$paymentId."\" value=\"'+value.street+'\" />'
                                            );
                                           jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_address_2\" name=\"svea__'+value.addressSelector+'__address_2__".$paymentId."\" value=\"'+value.address_2+'\" />'
                                            );
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_zipCode\" name=\"svea__'+value.addressSelector+'__zipCode__".$paymentId."\" value=\"'+value.zipCode+'\" />'
                                            );
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_locality\" name=\"svea__'+value.addressSelector+'__locality__".$paymentId."\" value=\"'+value.locality+'\" />'
                                            );
                                            jQuery('#sveaAddressDiv_$paymentId').append(
                                                '<input type=\"text\" id=\"svea_'+value.addressSelector+'_virtuemart_country_id\" name=\"svea__'+value.addressSelector+'__virtuemart_country_id__".$paymentId."\" value=\"'+value.virtuemart_country_id+'\" />'
                                            );
                                        });
                                    if(customertype_$paymentId == 'svea_invoice_customertype_company') // company, may get several addresses
                                    {
                                        //empty
                                    }
                                    else // private individual, only one address
                                    {
                                        jQuery('#svea_address_div_$paymentId select').hide();   // hide dropdown for individuals
                                        jQuery('#svea_address_div_$paymentId').append(          // show individual address
                                            '<div id=\"sveaAddressDiv_$paymentId\">'+
                                                //'<strong>'+json_$paymentId"."[0].fullName+'</strong><br> '+
                                                '<strong>'+json_$paymentId"."[0].firstName+' '+
                                                json_$paymentId"."[0].lastName+'</strong><br> '+
                                                json_$paymentId"."[0].street+' <br> '+
                                                json_$paymentId"."[0].zipCode+' '+json_$paymentId"."[0].locality+
                                            '</div>'
                                        );
                                    }
                                    jQuery('#svea_address_div_$paymentId').show();
                                    jQuery('#svea_getaddress_error_$paymentId').hide();
                                }
                            }
                        });
                    }
                });
            ";

            $javascript .=
            "
                jQuery('#svea_form_$paymentId').parents('form').submit( function(){
                    var svea_action_$paymentId = jQuery('#svea_form_$paymentId').parents('form').attr('action');

                    var form_$paymentId = jQuery('<form id=\"svea_form_$paymentId\"></form>');
                    form.attr('method', 'post');
                    form.attr('action', svea_action_$paymentId);

                    var sveaform_$paymentId = jQuery(form_$paymentId).append('form#svea_form_$paymentId');
                    jQuery(document.body).append(sveaform_$paymentId);
                    sveaform_$paymentId.submit();
                    return false;

                    });

                });
            ";

            // add javascript that hides Norway getAddress field unless company is selected

            if( $countryCode == "NO" ) {
                $javascript .=
                "
                    jQuery(document).ready(function() {

                        jQuery(\"input[type=radio][name='svea__customertype__".$paymentId."']\").click( function() {

                            var svea_picked_payment = jQuery(\"input:radio[name='svea__customertype__".$paymentId."']:checked\").val();
                            switch( svea_picked_payment )
                            {

                                // if private selected, hide getAddress button
                                case 'svea_invoice_customertype_private':
                                    jQuery('#svea_getaddress_submit_".$paymentId."').hide();
                                break;

                                // if company selected, show getAddress button
                                case 'svea_invoice_customertype_company':

                                    jQuery('#svea_getaddress_submit_".$paymentId."').show();
                                break;
                            }
                        });
                        jQuery('#svea_getaddress_submit_".$paymentId."').hide();    // hidden to start with
                    });
                ";
            }

            $javascript .=  '</script>';

            return $html.$javascript;
        }



    }

    // No closing tag
