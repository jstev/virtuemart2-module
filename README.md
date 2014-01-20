# Virtuemart 2 - Svea WebPay payment module installation guide

##Index
* [Requirements] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#requirements)
* [Installation] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#installation)
* [Important info] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#important-info)

## Requirements
Joomla 2.5
VirtueMart 2.0+
Module has been developed and tested using Joomla 2.5.9-17, virtuemart 2.0.17-26a

## Installation instructions

We assume that you have a working installation of Joomla and Virtuemart 2 to begin with. These instructions detail how to install the various SveaWebPay payment methods -- Svea Invoice, Svea Payment Plan, Svea Directbank and Svea Card -- in your Virtuemart 2 shop.

### Installing the Svea payment methods -- overview

1. Download or clone the Virtuemart2-module from github. If you downloaded the files as a zip, unzip the files to a local directory.
2. Now make new zip archives from each folder under src, i.e. svealib.zip, sveainvoice.zip, sveapartpayment.zip, sveadirect.zip and sveacard.zip.
3. Log into your joomla installation as administrator, then go to Extensions -> Extension Manager and install the zipped packages, starting with svealib.zip.
4. Continue by installing the rest of the Svea payment methods (we recommend that you always install all four Svea payment methods).
5. Go to Extensions -> Plug-in manager and activate the Svea modules you are going to use.

// TODO screenshots?

## Payment Method Installation and Configuration

### Svea Invoice payment
Install one instance of the Svea Invoice payment method for each country that you will accept invoice payments from. If you plan on accepting invoice payments from customers in several countries, you will need to configure multiple instances of the Invoice payment method, each instance should accept payments from one country only. See further under client id and country settings below.

For registered users, we present the invoice method corresponding to the user country, if given. The Invoice payment method may also be used by unregistered users, we then present all method instances, and it is up to the user to select the correct instance corresponding to the customer country. 

For countries where Get Address functionality is provided, we will pre-fill the invoice address information as a convenience for the user.

The following currencies are accepted by the invoice payment method:
* Sweden -> SEK, 
* Norway -> NOK, 
* Denmark -> DKK, 
* Finland -> EUR, 
* Germany -> EUR, 
* Netherlands -> EUR

#### Payment Method Installation
In the joomla administration interface, select components/virtuemart. You should now be in the virtuemart control panel. Select payment methods. You should then see a list of all installed virtuemart payment methods.

To add a new payment method instance, press the "new" icon. You will then be presented with the new instance Payment Method Information tab.

#### Payment Method Information tab settings

![Svea Invoice Payment Method Information tab] (https://github.com/sveawebpay/opencart-module/raw/develop/docs/image/Invoice_information.PNG "Svea Invoice Payment Method Information tab")


* Payment Name -- set to "Svea faktura" or the equivalent in your language.
* Payment Description -- "Sverige"
* Payment Method -- select Svea Invoice.
// TODO -- what happens if different languages -- recommend enter in local language/corresponding to country setting? -- See locale files for translations!

#### Configuration tab settings
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
Only one country can be configured per instance of the method.
Paymentplan may be used by unregistered users. We present the all methods, and it is up to the user to select the correct country invoice method.
Allowed currencies per country:
* Sweden -> SEK
* Norway -> NOK
* Denmark -> DKK
* Finland -> EUR
* Germany -> EUR
* Netherlands -> EUR

### Svea Invoice


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


