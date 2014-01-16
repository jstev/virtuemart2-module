# Virtuemart 2 - Svea WebPay payment module installation guide

##TODO:
- min version of joomla 2.5, because of hidden fields in configuration xml


##Index
* [Requirements] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#requirements)
* [Installation] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#installation)
* [Important info] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#important-info)


## Requirements
Joomla 2.5
VirtueMart 2.0+

## Installation
1.  Run the install.sql script using your joomla prefix
2.  Copy the remaining files to the root catalog of your joomla installation
3.  Go to the virtuemart settings to shop -> payment methods
4.  Select New
5.  Fill in SveaWebPay in Payment Name
6.  Change published to Yes
7.  Fill in the description if you i.e are planning to take an invoice fee or give some other info
8.  Select SveaWebPay Payment under Payment group and click save
9.  Select the Configuration tab
10. Select if you want to show the SVEA logo in the checkout
11. Fill in you Merchant ID and Secret Word

You can now use SveaWebPays pay page with your configured payment methods

## Payment method Configuration

### Svea Invoice payment
If you plan on accepting Invoice payments from customers in several countries, you will need to configure multiple instances of the Invoice payment method. Each instance should accept payments from one country only, see further under client id and country settings below.

The Invoice payment method may be used by unregistered users -- we present all instances, and it is up to the user to select the correct instance corresponding to the customer country. The provided Get Addresses functionality will avoid the customer needing to enter address data manually.

#### Payment Method Information tab
* Payment Name -- set to "Svea faktura" or the equivalent in your language.
* Payment Description -- "Sverige"
* Payment Method -- select Svea Invoice.
// TODO -- what happens if different languages -- recommend enter in local language/corresponding to country setting? -- See locale files for translations!

#### Configuration tab
* Test mode -- If set to Yes, payment and get address requests are made in Svea test environment. Test credentials provided by Svea must be used.
* Client id, username and password -- Fill out the required fields client no, username and password. In an production environment, use your Svea account credentials for the desired country. For testing purposes, make sure to use the supplied test account credentials.
* Accepted Currency -- TODO
* Country -- select the country corresponding to this client id.
* ...
// TODO other settings -- fix (hide?) sensible defaults for settings that are required but shouldn't need to be changed by user
-- check what the VMConfig::get(name,default) defaults to?
(...)
SVEA_MIN_AMOUNT -- set to 0
SVEA_MAX_AMOUNT -- set to 100000 (or other sufficiently large number)?
SVEA_INVOICEFEE -- set to amount ex. tax
SVEA_COST_PERCENT_TOTAL -- set to 0 (or if fee is a percentage, use this?)
SVEA_TAX -- the tax rate that should apply to the fee/cost percent total

### Svea Paymentplan
Paymentplan may be used by unregistered users. We present the all methods, and it is up to the user to select the correct country invoice method.
Only one country can be configured per instance of the method.
Alowed currencys per country:
* Sweden -> SEK
* Norway -> NOK
* Denmark -> DKK
* Finland -> EUR
* Germany -> EUR
* Netherlands -> EUR

### Svea Invoice
Invoice payment may be used by unregistered users. We present the all methods, and it is up to the user to select the correct country invoice method.
Only one country can be configured per instance of the method.
Alowed currencys per country:
* Sweden -> SEK
* Norway -> NOK
* Denmark -> DKK
* Finland -> EUR
* Germany -> EUR
* Netherlands -> EUR

### Svea Card payment
Card payment may be used by un-registered users (i.e. no user is logged in during checkout). See also "Additional VirtueMart configuration requirements" below

### Svea Direct bank payment
Direct bank payment may only be used by registered users (and the user must be logged in during checkout).

## Additional VirtueMart configuration

To configure additional VirtueMart settings, log in to your Joomla installation as administrator, and select Components/VirtueMart in the menu.
In the lefthand VirtueMart menu, the following settings are relevant to Svea payment methods:

### Configuration
#### Checkout tab
* Only registred users can checkout -- this must be unchecked for unregisterd users to be able to checkout.
* One Page Checkout enabled -- TODO
* Enable Automatic Selected Payment -- for Svea Invoice and Paymentplan only, the "Select payment" link will show up even when this option is checked. This is due to these methods needing additional customer credentials collected in the "Select payment" step.
* On checkout, ask for registration -- TODO
* Only registered users can checkout -- for Svea Invoice and Paymentplan only, we populate the cart Bill to-address for unregistered users with the result of the GetAddress request. This avoids unregistered customers needing to enter their address manually upon checkout.


## Important info
The request made from this module to SVEAs systems is made through a redirected form.
The response of the payment is then sent back to the module via POST or GET (selectable in our admin).





###When using GET
Have in mind that a long response string sent via GET could get cut off in some browsers and especially in some servers due to server limitations.
Our recommendation to solve this is to check the PHP configuration of the server and set it to accept at LEAST 512 characters.


###When using POST
As our servers are using SSL certificates and when using POST to get the response from a payment the users browser propmts the user with a question whether to continue or not, if the receiving site does not have a certificate.
Would the customer then click cancel, the process does not continue.  This does not occur if your server holds a certicifate. To solve this we recommend that you purchase a SSL certificate from your provider.

We can recommend the following certificate providers:
* InfraSec:  infrasec.se
* VeriSign : verisign.com


## Invoice payments: discount vat calculation error
There's a bug in how VirtueMart calculates the discount vat when Svea Invoicefee applies to an order. The bug involves the discount vat amount being scaled due to the invoice fee being included with the subtotal. The sums are correct, but the vat tax is wrong. To avoid this, use the below invoice vat workaround.

Workaround: Create a separate tax rule to use for invoice fee. In VM2 Admin, go to Products/Taxes & Calculation rules. Add a new rule with the following:
"Vat tax per product", "+%", <your vat rate>. Then go to Shop/Payment methods and under Svea Invoice set VMPAYMENT_SVEA_TAX to use this rule. Discount vat
will now be correct on checkout.


