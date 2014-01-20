# Virtuemart 2 - Svea WebPay payment module installation guide

##Index
* [Requirements] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#requirements)
* [Installation] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#installation)
* [Important info] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#important-info)

## Requirements
Joomla 2.5
VirtueMart 2.0+
Module has been developed and tested using Joomla 2.5.9-17, virtuemart 2.0.17-26a

## Virtuemart Svea Payment Method Installation Instructions

These instructions detail how to install the various SveaWebPay payment methods -- Svea Invoice, Svea Payment Plan, Svea Directbank and Svea Card -- in your Virtuemart 2 shop, as well as how to install and configure individual instances of these payment methods.

We assume that you have a working installation of Joomla and Virtuemart 2 to begin with. 

### Installing the Svea payment methods -- overview

Before we can configure the individual instances of the payment methods in virtuemart, we have to install the payment methods themselves. Each payment method, as well as their common support file package, is installed as a joomla extension.

1. Download or clone the Virtuemart2-module from github. If you downloaded the files as a zip, unzip the files to a local directory.
2. Now make new zip archives from each folder under src, i.e. svealib.zip, sveainvoice.zip, sveapartpayment.zip, sveadirect.zip and sveacard.zip.
3. Log into your joomla installation as administrator, then go to Extensions -> Extension Manager and install the zipped packages, starting with svealib.zip.
4. Continue by installing the rest of the Svea payment methods (we recommend that you always install all four Svea payment methods).
5. Go to Extensions -> Plug-in manager and activate the Svea modules you are going to use.

// TODO screenshots?
### Payment Method instance installation
In the joomla administration interface, select components/virtuemart. You should now be in the virtuemart control panel. Select payment methods. You should then see a list of all installed virtuemart payment methods.

To add a new payment method instance, press the "new" icon. You will then be presented with the Information tab of the new payment method instance. 

## Svea Payment Method Instance Configuration

### Svea Invoice
Install one instance of the Svea Invoice payment method for each country that you wish to accept invoice payments from. If you plan on accepting invoice payments from customers in several countries, you will need to configure multiple instances of the method, each instance should accept payments from one country only, as each client id is valid for one country only. See further under client id and country settings below.

For registered users, we present the instance corresponding to the user country, if given. The invoice payment method may also be used by unregistered users, we then present all method instances, and it is up to the user to select the correct instance corresponding to the customer country. 

For registered users, we present the instance corresponding to the user country, if given. The Invoice payment method may also be used by unregistered users, we then present all method instances, and it is up to the user to select the correct instance corresponding to the customer country. 

For countries where Get Address functionality is provided, we will also pre-fill the invoice address information as a convenience for the user.

The following countries & currencies are accepted by the invoice payment method: Sweden (SEK), Norway (NOK), Denmark (DKK), Finland, Germany, Netherlands (EUR).

#### Invoice Information tab settings
![Svea Invoice Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Invoice_information.PNG "Svea Invoice Information tab")

* Payment Name -- set to "SveaWebPay Invoice" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- we recommend that the description state what country the payment method instance corresponds to, i.e. "Sweden".
* Payment Method -- select "SveaWebPay Invoice" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user. We recommend presenting invoice as the first choice.

#### Invoice Configuration tab settings

![Svea Invoice Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Invoice_configuration.PNG "Svea Invoice Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance country (language) from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in Svea test environment. Test credentials provided by Svea must be used.

* Client id, username and password -- Fill out the required fields client no, username and password. In an production environment, use your Svea account credentials for the desired country. For testing purposes, make sure to use the supplied test account credentials. If you have lost your credentials information, please contact your Svea account manager.

* Accepted Currency -- currency to accept payments in. If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the country corresponding to this instance client id.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user. Use the values found in your Svea account credentials.  

* Payment Info -- Enter a message to display with the order, as well as on the post-checkout confirmation thank you-page. May be left blank if desired.
* Status Order Created -- the virtuemart status given to an order after it has been accepted by Svea.

* Autodeliver order -- Set this to "YES" to auto deliver the order. Note that this functionality must first be enabled in the Svea admin panel. Please contact your Svea account manager if you have further questions about this.
* Status Order Delivered -- the virtuemart status given to an order after it has been (auto-)delivered to Svea.

* Svea invoice fee -- if you charge extra for orders made with this payment method, set the fee, excluding tax, here.
* Tax -- select the invoice fee tax rate from the dropdown list. See also under Troubleshooting below.

### Svea Payment Plan 
Install one instance of the Svea Payment Plan payment method for each country that you wish to accept payment plan payments from. If you plan on accepting payment plan payments from customers in several countries, you will need to configure multiple instances of the method, each instance should accept payments from one country only, as each client id is valid for one country only. See further under client id and country settings below.

For registered users, we present the instance corresponding to the user country, if given. The Payment Plan payment method may also be used by unregistered users, we then present all method instances, and it is up to the user to select the correct instance corresponding to the customer country. 

For countries where Get Address functionality is provided, we will also pre-fill the invoice address information as a convenience for the user.

The following countries & currencies are accepted by the payment plan payment method: Sweden (SEK), Norway (NOK), Denmark (DKK), Finland, Germany, Netherlands (EUR).

#### Payment Plan instance installation
In the joomla administration interface, select components/virtuemart. You should now be in the virtuemart control panel. Select payment methods. You should then see a list of all installed virtuemart payment methods.

To add a new payment method instance, press the "new" icon. You will then be presented with the Information tab of the new payment method instance. 

#### Payment Plan Information tab settings
![Svea Payment Plan Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Part_Payment_information.PNG "Svea Payment Plan Information tab")

* Payment Name -- set to "SveaWebPay Payment Plan" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- we recommend that the description state what country the payment method instance corresponds to, i.e. "Sweden".
* Payment Method -- select "SveaWebPay Payment Plan" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user. We recommend presenting part payment as the second choice.

#### Payment Plan Configuration tab settings
![Svea Payment Plan Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Part_Payment_configuration.PNG "Svea Payment Plan Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance country (language) from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in Svea test environment. Test credentials provided by Svea must be used.

* Client id, username and password -- Fill out the required fields client no, username and password. In an production environment, use your Svea account credentials for the desired country. For testing purposes, make sure to use the supplied test account credentials. If you have lost your credentials information, please contact your Svea account manager.

* Accepted Currency -- currency to accept payments in. If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the country corresponding to this instance client id.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user. Use the values found in your Svea account credentials.  

* Payment Info -- Enter a message to display with the order, as well as on the post-checkout confirmation thank you-page. May be left blank if desired.
* Status Order Created -- the virtuemart status given to an order after it has been accepted by Svea.

* Autodeliver order -- Set this to "YES" to auto deliver the order. Note that this functionality must first be enabled in the Svea admin panel. Please contact your Svea account manager if you have further questions about this.
* Status Order Delivered -- the virtuemart status given to an order after it has been (auto-)delivered to Svea.

### Svea Card payment
Install one or more instances of the Svea Card payment method. The instances will be presented to all users regardless of registration status. 

#### Card Information tab settings
![Svea Card Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Card_information.PNG "Svea Card Information tab")

* Payment Name -- set to "SveaWebPay Card" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- an optional short description of the payment method.
* Payment Method -- select "SveaWebPay Card" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user.

#### Card Configuration tab settings
![Svea Card Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Card_configuration.PNG "Svea Card Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance language from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in Svea test environment. Test credentials provided by Svea must be used.

* Merchant id, Secret Word -- Fill out the required fields merchant id and secret word. Note that you have been provided a different pair for test and production, make sure you enter each in the correct place. If you have lost your credentials information, please contact your Svea account manager.

* Accepted Currency -- currency to accept payments in. If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the countries in which to show this payment method, or leave empty for all countries.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user.

* Payment Info -- Enter a message to display with the order, as well as on the post-checkout confirmation thank you-page. May be left blank if desired.
* Status for successful payment -- the virtuemart status given to an order after it has been accepted.

### Svea Direct Bank payment
Install one or more instances of the Svea Direct Bank payment method. The instances will be presented to all users regardless of registration status. 

#### C Information tab settings
![Svea Direct Bank Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Direct_information.PNG "Svea Direct Bank Information tab")

* Payment Name -- set to "SveaWebPay Direkt Bank" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- an optional short description of the payment method.
* Payment Method -- select "SveaWebPay Directbank" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user.

#### Card Configuration tab settings
![Svea Direct Bank Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Direct_configuration.PNG "Svea Direct Bank Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance language from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in Svea test environment. Test credentials provided by Svea must be used.

* Merchant id, Secret Word -- Fill out the required fields merchant id and secret word. Note that you have been provided a different pair for test and production, make sure you enter each in the correct place. If you have lost your credentials information, please contact your Svea account manager.

* Accepted Currency -- currency to accept payments in. If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the countries in which to show this payment method, or leave empty for all countries.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user.

* Payment Info -- Enter a message to display with the order, as well as on the post-checkout confirmation thank you-page. May be left blank if desired.
* Status for successful payment -- the virtuemart status given to an order after it has been accepted.

## Additional VirtueMart configurations

To configure additional VirtueMart settings, log in to your Joomla installation as administrator, and select Components -> VirtueMart in the menu.
In the lefthand VirtueMart menu, the following settings are relevant to Svea payment methods:

### Products
#### Taxes & Calculation rules submenu
If you use the Svea Invoice payment method and charge your users an invoice fee, please see under Troubleshooting below.

### Shop
#### Shop submenu/Vendor tab
* Currency -- this is the "Default Vendor Currency" refered to in the various payment methods above.
* List of accepted currencies -- make sure this list includes all of the currencies allowed by your various Svea payment methods instances.

### Configuration
#### Configuration submenu/Shop tab
* Languages Settings -- we provide translations for the following languages in our payment module: Swedish (sv-SE), Norwegian (no-NO), Danish (da-DK), Finnish (fi-FI), English (en-GB), German (de-DE), Dutch (nl-NL).

#### Configuration submenu/Checkout tab
* Only registred users can checkout -- this must be unchecked for unregisterd users to be able to checkout.
* One Page Checkout enabled -- this has not been tested in release 2.0 of the payment modules.
* Enable Automatic Selected Payment -- for Svea Invoice and Paymentplan only, the "Select payment" link will show up even when this option is checked. This is due to these methods needing additional customer credentials which is collected during the "Select payment" step.

## Troubleshooting

### Card and Direct Bank payments: SSL certificates
When using Card and Direct Bank payments, the request made from this module to Sveas systems is made through a redirected form. The response of the payment is then sent back to the module via POST or GET. Which is used is selectable through your Svea account admin, if you have further questions about this, please contact your Svea account manager.

Have in mind that a long response string sent via GET could get cut off in some browsers and especially in some servers due to server limitations.
Our recommendation to solve this is to check the PHP configuration of the server and set it to accept at least 512 characters.

As our servers are using SSL certificates, when using POST to fetch the response from a payment, the users browser prompts the user whether to continue or not, should the receiving site not also have a certificate. If the customer then clicks cancel, the payment process is aborted. To avoid this, make sure your server holds a valid SSL certificate. We recommend that you purchase a SSL certificate from your provider.

### Invoice payments: invoice fee discount tax calculation error
There's a bug in how VirtueMart calculates the discount vat in orders containing products with different tax rates, when a Svea Invoice fee is applied to the order. The bug involves the discount mean vat rate being calculated incorrectly, due to the invoice fee being included along with the product subtotal in the calculation. The sums are correct, but the vat tax rate is wrong. To workaround this, follow the below procedure:

Create a separate tax rule to use for your invoice fee. In VirtueMart admin, go to the Products and Taxes & Calculation rules. Add a new rule with the following: "Vat tax per product", "+%", <your vat rate>. Then go to Shop -> Payment methods, and for your Svea Invoice payment method instances set the "Tax" (VMPAYMENT_SVEA_TAX) setting to use this vat rule. The Discount vat should now be correct on checkout.

