<?php
// WebPayAdmin class is excluded from Svea namespace

include_once SVEA_REQUEST_DIR . "/Includes.php";

/**
## Introduction
The WebPay and WebPayAdmin classes make up the Svea WebPay API. Together they provide unified entrypoints to the various Svea web services. The API also encompass the support classes ConfigurationProvider, SveaResponse and WebPayItem, as well as various constant container classes.

The WebPay class methods contains the functions needed to create orders and perform payment requests using Svea payment methods. It contains methods to define order contents, send order requests, as well as support methods needed to do this.

The WebPayAdmin class methods are used to administrate orders after they have been accepted by Svea. It includes functions to update, deliver, cancel and credit orders et.al.

### Package design philosophy
In general, a request to Svea using the WebPay API starts out with you creating an instance of an order builder class, which is then built up with data using fluid method calls. At a certain point, a method is used to select which service the request will go against. This method then returns an service request instance of a different class, which handles the request to the service chosen. The service request will return a response object containing the various service responses and/or error codes.

The WebPay API consists of the entrypoint methods in the WebPay and WebPayAdmin classes. These instantiate order builder classes in the Svea namespace, or in some cases request builder classes in the WebService, HostedService and AdminService sub-namespaces.

Given an instance, you then use method calls in the respective classes to populate the order or request instance. For orders, you then choose the payment method and get a request class in return. Send the request and get a service response from Svea in return. In general, the request classes will validate that all required attributes are present, and if not throw an exception stating what is missing for the request in question.

### Synchronous and asynchronous requests
Most service requests are synchronous and return a response immediately. For asynchronous hosted service payment requests, the customer will be redirected to i.e. the selected card payment provider or bank, and you will get a callback to a return url, where where you receive and parse the response.

### Namespaces
The package makes use of PHP namespaces, grouping most classes under the namespace Svea. The entrypoint classes WebPay, WebPayAdmin and associated support classes are excluded from the Svea namespace. See the generated documentation for available classes and methods.

The underlying services and methods are contained in the Svea sub-namespaces WebService, HostedService and AdminService, and may be accessed, though their api and interfaces are subject to change in the future.

### Documentation format
See the provided README.md file for an overview and examples how to utilise the WebPay and WebPayAdmin classes. The complete WebPay Integration package, including the underlying Svea service classes, methods and structures, is documented by generated documentation in the apidoc folder.

### Fluid API
The WebPay and WebPayAdmin entrypoint methods are built as a fluent API so you can use method chaining when implementing it in your code. We recommend making sure that your IDE code completion is enabled to make full use of this feature.

### Development environment
The Svea WebPay PHP integration package is developed and tested using NetBeans IDE 7.3.1 with the phpunit 3.7.24 plugin.
 *
 * @api
 * @version 2.2.3
 * @package WebPay
 *
 * @author Anneli Halld'n, Daniel Brolund, Kristian Grossman-Madsen for Svea WebPay
 */
class WebPayAdmin {

    /**
     * The WebPayAdmin::queryOrder() entrypoint method is used to cancel an order with Svea, 
     * that has not yet been delivered (invoice, payment plan) or been confirmed (card).
     * 
     * Supports Invoice, Payment Plan and Card orders. For Direct Bank orders, use
     * WebPayAdmin::creditOrder() instead.
     *
     *      $request = WebPay::cancelOrder($config)
     *          ->setOrderId($orderId)          // required, use SveaOrderId recieved with createOrder response
     *          ->setCountryCode("SE")          // required, use same country code as in createOrder request
     *          ->cancelInvoiceOrder()          // select request class, use same order type as in createOrder request
     *              ->doRequest()               // and perform the request, returns CloseOrderResult 
     * 
     *          //->cancelPaymentPlanOrder()->doRequest()   // returns CloseOrderResult 
     *          //->cancelCardOrder()->doRequest()          // returns AnnulTransactionResponse           
     *      ; 
     *     
     * The final doRequest() returns either a CloseOrderResult or an AnnulTransactionResponse
     * 
     * @see \Svea\CancelOrderBuilder \Svea\CancelOrderBuilder
     * @see \Svea\WebService\CloseOrderResult Svea\WebService\CloseOrderResult
     * @see \Svea\HostedService\AnnulTransactionResponse \Svea\HostedService\AnnulTransactionResponse
     *
     * @param ConfigurationProvider $config  instance implementing ConfigurationProvider
     * @return Svea\CancelOrderBuilder
     * @throws Exception
     */
    public static function cancelOrder($config = NULL) {
        if( $config == NULL ) { WebPay::throwMissingConfigException(); }
        return new Svea\CancelOrderBuilder($config);
    }

    /**
     * The WebPayAdmin::queryOrder entrypoint method is used to get information about an order.
     *
     * Note that for invoice and payment plan orders, the order rows name and description is merged
     * into the description field in the query response.
     * 
     * Get an query builder instance using the WebPayAdmin::queryOrder entrypoint, then provide 
     * more information about the order and send the request using the queryOrderBuilder methods: 
     *  
     *      $request = WebPay::queryOrder($config)
     *          ->setOrderId()          // required
     *          ->setCountryCode()      // required      
     *          ->queryInvoiceOrder()   // select request class and
     *              ->doRequest()       // perform the request, returns GetOrdersResponse
     *          
     *          //->queryPaymentPlanOrder()->doRequest() // returns GetOrdersResponse
     *          //->queryCardOrder()->doRequest()        // returns QueryTransactionResponse
     *          //->queryDirectBankOrder()->doRequest()  // returns QueryTransactionResponse
     *      ;
     * 
     * The final doRequest() returns either a GetOrdersResponse or an QueryTransactionResponse.
     *
     * @see \Svea\QueryOrderBuilder \Svea\QueryOrderBuilder
     * @see \Svea\AdminService\GetOrdersResponse \Svea\AdminService\GetOrdersResponse
     * @see \Svea\HostedService\QueryTransactionResponse \Svea\HostedService\QueryTransactionResponse
     *
     * @param ConfigurationProvider $config  instance implementing ConfigurationProvider
     * @return Svea\QueryOrderBuilder
     * @throws Exception
     */
    public static function queryOrder( $config = NULL ) {
        if( $config == NULL ) { WebPay::throwMissingConfigException(); }
        return new Svea\QueryOrderBuilder($config);
    }
   
    /**
     * The WebPayAdmin::cancelOrderRows entrypoint method is used to cancel rows in an order before it has been delivered.
     * Supports Invoice, Payment Plan and Card orders. (Direct Bank orders are not supported, see CreditOrderRows instead.)
     * 
     * Get an order builder instance using the WebPayAdmin::cancelOrderRows entrypoint,
     * then provide more information about the transaction and send the request using
     * the cancelOrderRowsBuilder methods:
     *
     *      ->setOrderId()           // required
     *      ->setCountryCode()       // required
     *      ->setRowToCancel()       // required, index of one of the original order row you wish to cancel
     *      ->setRowsToCancel()      // optional
     *      ->addNumberedOrderRow()  // card only, one or more, required with setRow(s)ToCancel
     *      ->addNumberedOrderRows() // card only, optional
     *
     * Finish by selecting the correct ordertype and perform the request:
     *      ->cancelInvoiceOrderRows() // or cancelPaymentPlanOrderRows() or cancelCardOrderRows()
     *          ->doRequest()
     *
     * The final doRequest() returns either a CancelOrderRowsResponse or a LowerTransactionResponse.
     *
     * @see \Svea\CancelOrderRowsBuilder \Svea\CancelOrderRowsBuilder
     * @see \Svea\AdminService\CancelOrderRowsResponse \Svea\AdminService\CancelOrderRowsResponse
     * @see \Svea\HostedService\LowerTransactionResponse \Svea\HostedService\LowerTransactionResponse
     *
     * @param ConfigurationProvider $config  instance implementing ConfigurationProvider
     * @return Svea\CancelOrderRowsBuilder
     * @throws ValidationException
     */
    public static function cancelOrderRows( $config = NULL ) {
        if( $config == NULL ) { WebPay::throwMissingConfigException(); }
        return new Svea\CancelOrderRowsBuilder($config);
    }

    /**
     * The WebPayAdmin::creditOrderRows entrypoint method is used to credit rows in an order after it has been delivered.
     * Supports Invoice, Card and Direct Bank orders. (To credit a Payment Plan order, contact Svea customer service.)
     * 
     * Get an order builder instance using the WebPayAdmin::creditOrderRows entrypoint,
     * then provide more information about the transaction and send the request using
     * the creditOrderRowsBuilder methods:
     * 
     * ->setInvoiceId()                 (invoice only, required)
     * ->setInvoiceDistributionType()   (invoice only, required)
     * ->setOrderId()                   (card and direct bank only, required)
     * ->setCountryCode()               (required)
     * ->addCreditOrderRow()            (optional, use if you want to specify a new credit row, i.e. for amounts not present in the original order)
     * ->addCreditOrderRows()           (optional)
     * ->setRowToCredit()               (optional, index of one of the original order row you wish to credit)
     * ->setRowsToCredit()              (optional)
     * ->addNumberedOrderRow()          (card and direct bank only, required with setRow(s)ToCredit)
     * ->addNumberedOrderRows()         (card and direct bank only, optional)
     *  
     * Finish by instantiating the request type and perform the request:
     * ->creditInvoiceOrderRows() // creditCardOrderRows() or creditDirectBankOrderRows()
     *   ->doRequest()
     *  
     * The final doRequest() returns either a CreditOrderRowsResponse or a CreditTransactionResponse.
     *
     * @param ConfigurationProvider $config
     * @return Svea\CreditOrderRowsBuilder
     * @throws ValidationException
     *
     * @see \Svea\CreditOrderRowsBuilder \Svea\CreditOrderRowsBuilder
     * @see \Svea\AdminService\CreditOrderRowsResponse \Svea\AdminService\CreditOrderRowsResponse
     * @see \Svea\HostedService\CreditTransactionResponse \Svea\HostedService\CreditTransactionResponse
     *
     * @author Kristian Grossman-Madsen for Svea WebPay
     */
    public static function creditOrderRows( $config = NULL ) {
        if( $config == NULL ) { WebPay::throwMissingConfigException(); }
        return new Svea\CreditOrderRowsBuilder($config);
    }

    /**
     * Add order rows to an order. Supports Invoice and Payment Plan orders.
     * (Card and Direct Bank orders are not supported.)
     *
     * Provide information about the new order rows and send the request using
     * addOrderRowsBuilder methods:
     *
     * ->setOrderId()
     * ->setCountryCode()
     * ->addOrderRow() (one or more)
     * ->addOrderRows() (optional)
     *
     * Finish by selecting the correct ordertype and perform the request:
     * ->addInvoiceOrderRows() | addPaymentPlanOrderRows()
     *   ->doRequest()
     *
     * The final doRequest() returns an AddOrderRowsResponse
     *
     * @see \Svea\AddOrderRowsBuilder \Svea\AddOrderRowsBuilder
     * @see \Svea\AdminService\AddOrderRowsResponse \Svea\AdminService\AddOrderRowsResponse
     *
     * @param ConfigurationProvider $config  instance implementing ConfigurationProvider
     * @return Svea\AddOrderRowsBuilder
     * @throws ValidationException
     */
    public static function addOrderRows( $config = NULL ) {
        if( $config == NULL ) { WebPay::throwMissingConfigException(); }
        return new Svea\AddOrderRowsBuilder($config);
    }

    /**
     * Update order rows in a non-delivered invoice or payment plan order.
     * (Card and Direct Bank orders are not supported.)
     *
     * Provide information about the updated order rows and send the request using
     * updateOrderRowsBuilder methods:
     *
     * ->setOrderId()
     * ->setCountryCode()
     * ->updateOrderRow() (one or more)
     * ->updateOrderRows() (optional)
     *
     * Finish by selecting the correct ordertype and perform the request:
     * ->updateInvoiceOrderRows() | updatePaymentPlanOrderRows()
     *   ->doRequest()
     *
     * The final doRequest() returns an UpdateOrderRowsResponse
     *
     * @see \Svea\UpdateOrderRowsBuilder \Svea\UpdateOrderRowsBuilder
     * @see \Svea\AdminService\UpdateOrderRowsResponse \Svea\AdminService\UpdateOrderRowsResponse
     *
     * @param ConfigurationProvider $config  instance implementing ConfigurationProvider
     * @return Svea\UpdateOrderRowsBuilder
     * @throws ValidationException
     */
    public static function updateOrderRows( $config = NULL ) {
        if( $config == NULL ) { WebPay::throwMissingConfigException(); }
        return new Svea\UpdateOrderRowsBuilder($config);
    }

    /**
     * The WebPayAdmin::deliverOrderRows entrypoint method is used to deliver individual order rows.
     * Supports invoice and card orders. (To partially deliver PaymentPlan or Direct Bank orders, please contact Svea.)
     * 
     * Get an order builder instance using the WebPayAdmin::deliverOrderRows entrypoint,
     * then provide more information about the transaction and send the request using
     * the deliverOrderRowsBuilder methods:
     *
     * ->setOrderId()           (invoice, card only, required)
     * ->setCountryCode()       (invoice only, required)
     * ->setRowToDeliver()      (required, index of one of the original order row you wish to cancel)
     * ->setRowsToDeliver()     (optional)
     * ->addNumberedOrderRow()  (card only, one or more, required with setRow(s)ToDeliver)
     * ->addNumberedOrderRows() (card only, optional)
     * 
     * Finish by selecting the correct ordertype and perform the request:
     * ->deliverInvoiceOrderRows() // or ->deliverCardOrderRows()
     *   ->doRequest()
     *
     * The final doRequest() returns a DeliverOrderRowsResponse or ConfirmTransactionResponse
     *
     * @see \Svea\DeliverOrderRowsBuilder \Svea\DeliverOrderRowsBuilder
     * @see \Svea\HostedService\ConfirmTransactionResponse \Svea\HostedService\ConfirmTransactionResponse
     * @see \Svea\AdminService\DeliverOrderRowsResponse \Svea\AdminService\DeliverOrderRowsResponse
     *
     * @param ConfigurationProvider $config  instance implementing ConfigurationProvider
     * @return Svea\DeliverOrderRowsBuilder
     * @throws ValidationException
     */
    public static function deliverOrderRows( $config = NULL ) {
        if( $config == NULL ) { WebPay::throwMissingConfigException(); }
        return new Svea\DeliverOrderRowsBuilder($config);
    }    
        
    /** helper function, throws exception if no config is given */
    private static function throwMissingConfigException() {
        throw new Exception('-missing parameter: This method requires an ConfigurationProvider object as parameter. Create a class that implements class ConfigurationProvider. Set returnvalues to configuration values. Create an object from that class. Alternative use static function from class SveaConfig e.g. SveaConfig::getDefaultConfig(). You can replace the default config values to return your own config values in the method.');
    }
}
