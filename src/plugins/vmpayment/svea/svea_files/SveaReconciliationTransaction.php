<?php
/**
 * Class representing an address
 * @package com.epayment.util.implementation;
 *
 */
class SveaReconciliationTransaction {
	
	/**
	 * WebPay Transaction id
	 * @var int
	 */
	public $transactionId;
	/**
	 * Merchant reference
	 * @var String
	 */
	public $customerRefNo;
	/**
	 * Payment method
	 * @var String
	 */
	public $paymentMethod;
	/**
	 * Amount in cents
	 * @var int
	 */
	public $amount;
	/**
	 * Example: 2011-12-30 11:56:03 CET
	 * @var String
	 */
	public $time;
	
}