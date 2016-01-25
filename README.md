# Virtuemart 2 - SveaWebPay WebPay payment module installation guide


##Version 2.5.3
This module supports invoice and payment plan payments in Sweden, Finland, Norway, Denmark, Netherlands and Germany, as well as creditcard and direct bank payments.
Admin functions such as Deliver, Confirm, Credit and Cancel orders is implemented into Virtuemarts admin functions.
This module is updated for the latest payment systems at SveaWebPay.

If you experience technical issues with this module, or you have feature suggestions, please submit an issue on the Github issue list.

## Requirements
Joomla 2.5, VirtueMart 2.0+, PHP 5.3+
The module has been developed and tested using Joomla 2.5.9-17, VirtueMart 2.0.17-26a.

The module is tested Rupostel One Page Checkout 2.0 on Joomla 2.5.11 and Virtuemart 2.0.26d

## Installation Instructions

These instructions detail how to install the various SveaWebPay payment methods -- SveaWebPay Invoice, SveaWebPay Payment Plan, SveaWebPay Directbank and SveaWebPay Card -- in your Virtuemart 2 shop, as well as how to install and configure individual instances of these payment methods.

We assume that you have a working installation of Joomla and Virtuemart 2 to begin with.

Note: if you are upgrading from an older version of the module, make sure to delete the old paymentmethod instances (taking note of your settings) before upgrading to the new module. You then need to re-create your paymentmethods and re-enter your settings.

### Installing the SveaWebPay Payment Methods in VirtueMart 2

Before we can configure the individual instances of the payment methods in virtuemart, we have to install the payment methods themselves. Each payment method, as well as their common support file package, is installed as a joomla extension.

1. Download or clone the Virtuemart2-module from github. If you downloaded the files as a zip, unzip the files to a local directory.
2. Now make new zip archives from each folder under src, i.e. svealib.zip, sveainvoice.zip, sveapartpayment.zip, sveadirect.zip and sveacard.zip.
3. Log into your joomla installation as administrator, then go to Extensions -> Extension Manager and install the zipped packages, starting with svealib.zip.
4. Continue by installing the rest of the SveaWebPay payment methods (we recommend that you always install all four SveaWebPay payment methods).
5. Go to Extensions -> Plug-in manager and activate the SveaWebPay modules you are going to use.

### Individual Payment Method Installation
In the joomla administration interface, select components/virtuemart. You should now be in the virtuemart control panel. Select payment methods. You should then see a list of all installed virtuemart payment methods.

To add a new payment method instance, press the "new" icon. You will then be presented with the Information tab of the new payment method instance.

## SveaWebPay Payment Method Instance Configuration

### SveaWebPay Invoice
Install one instance of the SveaWebPay Invoice payment method for each country that you wish to accept invoice payments from. If you plan on accepting invoice payments from customers in several countries, you will need to configure multiple instances of the method, each instance should accept payments from one country only, as each client id is valid for one country only. See further under client id and country settings below.

To make sure the right payment with the right country specific fields are shown, force the customer to register country by choosing Configuration -> Checkout->*Only registered users can checkout* in the shop configuration.

In payment method selection, for registered users, we present the instance corresponding to the user country, if given. The invoice payment method may also be used by unregistered users -- we then present all method instances, and it is up to the user to select the correct instance corresponding to the customer country.

For countries where Get Address functionality is provided, we will also pre-fill the returned address information as a convenience for the user.

#### Invoice Information tab settings
![SveaWebPay Invoice Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Invoice_information.PNG "SveaWebPay Invoice Information tab")

* Payment Name -- set to "SveaWebPay Invoice" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- an optional short description of the payment method.
* Payment Method -- select "SveaWebPay Invoice" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user. We recommend presenting invoice as the first choice.

#### Invoice Configuration tab settings

![SveaWebPay Invoice Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Invoice_configuration.PNG "SveaWebPay Invoice Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance country (language) from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in SveaWebPay test environment. Test credentials provided by SveaWebPay must be used.

* Client id, username and password -- Fill out the required fields client no, username and password. In an production environment, use your SveaWebPay account credentials for the desired country. For testing purposes, make sure to use the supplied test account credentials. If you have lost your credentials information, please contact your SveaWebPay account manager.

* Accepted Currency -- currency to accept payments in. The currency setting must match the country corresponding to this instance client id. The following countries & currencies are accepted by the invoice payment method: Sweden (SEK), Norway (NOK), Denmark (DKK), Finland, Germany, Netherlands (EUR). If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the country corresponding to this instance client id.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user. Use the values found in your SveaWebPay account credentials.

* Payment Info -- Enter a message to display with the order, as well as on the post-checkout confirmation thank you-page.
* Status Order Created -- the virtuemart status given to an order after it has been accepted by SveaWebPay.
* Shipping same as billing -- determines whether to use the svea billing address for both shipping and billing. It will ignore if customer tries to change the shipping address. Should be set to true if your contract with Svea does not tell otherwise.

* Autodeliver order -- Set this to "YES" to auto deliver the order. Note that this functionality must first be enabled in the SveaWebPay admin panel. Please contact your SveaWebPay account manager if you have further questions about this.
* Status Order Delivered -- the virtuemart status given to an order after it has been (auto-)delivered to SveaWebPay.

* SveaWebPay invoice fee -- if you charge extra for orders made with this payment method, set the fee, excluding tax, here.
* Tax -- select the invoice fee tax rate from the dropdown list. The tax rate should be applicable in the same name as the payment method instance client id used. See also under Troubleshooting below.
* Show Product Price Widget -- If set to true, the Svea Product Price Widget will be shown on product pages, displaying the minimum invoice amount to pay. Note: Only applicable if Svea buys the invoices, and for private customers. Only applies in Sweden, Norway, Finland and the Netherlands (see Product Price Widget threshold below). Please contact your Svea account manager if you have further questions.
* Product Price Widget threshold -- If Show Product Price Widget is set to true, the Svea Product Price Widget will be displayed if the product price equals or exceeds this amount. If not set, the Product Price Widget will be displayed regardless of product price.
- **Limitations:** You can only have the widget activated on one Invoice method to apply this feature.

### SveaWebPay Payment Plan
Install one instance of the SveaWebPay Payment Plan payment method for each country that you wish to accept payment plan payments from. If you plan on accepting payment plan payments from customers in several countries, you will need to configure multiple instances of the method, each instance should accept payments from one country only, as each client id is valid for one country only. See further under client id and country settings below.

To make sure the right payment with the right country specific fields are shown, force the customer to register country by choosing Configuration -> Checkout->*Only registered users can checkout* in the shop configuration.

In payment method selection, for registered users, we present the instance corresponding to the user country, if given. The payment plan payment method may also be used by unregistered users -- we then present all method instances, and it is up to the user to select the correct instance corresponding to the customer country.

For countries where Get Address functionality is provided, we will also pre-fill the returned address information as a convenience for the user.

The following countries & currencies are accepted by the payment plan payment method: Sweden (SEK), Norway (NOK), Denmark (DKK), Finland, Germany, Netherlands (EUR).

#### Payment Plan instance installation
In the joomla administration interface, select components/virtuemart. You should now be in the virtuemart control panel. Select payment methods. You should then see a list of all installed virtuemart payment methods.

To add a new payment method instance, press the "new" icon. You will then be presented with the Information tab of the new payment method instance.

#### Payment Plan Information tab settings
![SveaWebPay Payment Plan Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Part_Payment_information.PNG "SveaWebPay Payment Plan Information tab")

* Payment Name -- set to "SveaWebPay Payment Plan" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- an optional short description of the payment method.
* Payment Method -- select "SveaWebPay Payment Plan" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user. We recommend presenting part payment as the second choice.

#### Payment Plan Configuration tab settings
![SveaWebPay Payment Plan Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Part_Payment_configuration.PNG "SveaWebPay Payment Plan Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance country (language) from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in SveaWebPay test environment. Test credentials provided by SveaWebPay must be used.

* Client id, username and password -- Fill out the required fields client no, username and password. In an production environment, use your SveaWebPay account credentials for the desired country. For testing purposes, make sure to use the supplied test account credentials. If you have lost your credentials information, please contact your SveaWebPay account manager.

* Accepted Currency -- currency to accept payments in. The currency setting must match the country corresponding to this instance client id. The following countries & currencies are accepted by the invoice payment method: Sweden (SEK), Norway (NOK), Denmark (DKK), Finland, Germany, Netherlands (EUR). If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the country corresponding to this instance client id.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user. Use the values found in your SveaWebPay account credentials.

* Payment Info -- Enter a message to display with the order, in the checkout, as well as on the post-checkout confirmation thank you-page. Enter info about the initialfee here.
* Status Order Created -- determines whether to use the svea billing address for both shipping and billing. It will ignore if customer tries to change the shipping address. Should be set to true if your contract with Svea does not tell otherwise.
* Shipping same as billing -- determines whether to use the svea billing address for both shipping and billing. It will ignore if customer tries to change the shipping address. Should be set to true if your contract with Svea does not tell otherwise.

* Autodeliver order -- Set this to "YES" to auto deliver the order. Note that this functionality must first be enabled in the SveaWebPay admin panel. Please contact your SveaWebPay account manager if you have further questions about this.
* Status Order Delivered -- the virtuemart status given to an order after it has been (auto-)delivered to SveaWebPay.
* Show Product Price Widget -- If set to true, the Svea Product Price Widget will be shown on product pages, displaying the minimum payment plan amount to pay each month. Only applies in Sweden, Norway, Finland and the Netherlands. Please contact your Svea account manager if you have further questions.
- **Limitations:** You can only have the widget activated on one PaymentPlan method to apply this feature.

### SveaWebPay Card payment
Install one or more instances of the SveaWebPay Card payment method. The instances will be presented to all users regardless of registration status.

#### Card Information tab settings
![SveaWebPay Card Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Card_information.PNG "SveaWebPay Card Information tab")

* Payment Name -- set to "SveaWebPay Card" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- an optional short description of the payment method.
* Payment Method -- select "SveaWebPay Card" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user.

#### Card Configuration tab settings
![SveaWebPay Card Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Card_configuration.PNG "SveaWebPay Card Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance language from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in SveaWebPay test environment. Test credentials provided by SveaWebPay must be used.

* Merchant id, Secret Word -- Fill out the required fields merchant id and secret word. Note that you have been provided a different pair for test and production, make sure you enter each in the correct place. If you have lost your credentials information, please contact your SveaWebPay account manager.

* Accepted Currency -- currency to accept payments in. If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the countries in which to show this payment method, or leave empty for all countries.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user.

* Payment Info -- Enter a message to display with the order, as well as on the post-checkout confirmation thank you-page. May be left blank if desired.
* Status for successful payment -- the virtuemart status given to an order after it has been accepted.

### SveaWebPay Direct Bank payment
Install one or more instances of the SveaWebPay Direct Bank payment method. The instances will be presented to all users regardless of registration status.

#### Direct Bank Information tab settings
![SveaWebPay Direct Bank Information tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Direct_information.PNG "SveaWebPay Direct Bank Information tab")

* Payment Name -- set to "SveaWebPay Direkt Bank" or the equivalent in your language.
* Sef Alias -- no need to change the default
* Published -- if set to "Yes", this payment method will be available for use by your customers.
* Payment Description -- an optional short description of the payment method.
* Payment Method -- select "SveaWebPay Directbank" from the dropdown list of payment methods.
* Shopper Group -- if needed, set the shopper group here.
* List Order -- defines the order in which the available payment methods are presented to the user.

#### Direct Bank Configuration tab settings
![SveaWebPay Direct Bank Configuration tab] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Direct_configuration.PNG "SveaWebPay Direct Bank Configuration tab")

* Logos -- select the logo file corresponding to the payment method instance language from the dropdown list.
* Test mode -- If set to Yes, payment and get address requests are made in SveaWebPay test environment. Test credentials provided by SveaWebPay must be used.

* Merchant id, Secret Word -- Fill out the required fields merchant id and secret word. Note that you have been provided a different pair for test and production, make sure you enter each in the correct place. If you have lost your credentials information, please contact your SveaWebPay account manager.

* Accepted Currency -- currency to accept payments in. If set to "Default Vendor Currency", the payment method will use the shop global settings.
* Country -- select the countries in which to show this payment method, or leave empty for all countries.
* Minimum Amount, Maximum Amount -- the order value must fall within these limits for the payment method to be shown as available to the user.

* Payment Info -- Enter a message to display with the order, as well as on the post-checkout confirmation thank you-page. May be left blank if desired.
* Status for successful payment -- the virtuemart status given to an order after it has been accepted.

## Administrating orders
The module is integrated with the Virtuemart admin functions. This means that when you change a status on an order and the status matches the status for an action,
a request will be sent to Svea and perform the action. The actions available per paymentmethod is:

| Method        | Deliver order | Cancel order  |   Credit order    | Auto Deliver order  |
|---------------|:-------------:|:-------------:|:-----------------:|:-------------------:|
| Invoice       |   *           |   *           |   *               |   *                 |
| Paymentplan   |   *           |   *           |                   |   *                 |
| Card          |   *           |   *           |   *               |                     |
| Direct bank   |               |               |   *               |                     |

The Virtuemart _Cancel_ and _Refund_ status will perform corresponding _cancel/annul_ and _Credit order_ in Svea systems. To _Deliver/Confirm_
to Svea systmes, the status first have to set in the module configuration.

![Deliver order status configuration] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/Deliver_status_configuration.PNG "SveaWebPay Order status Configuration")

## Additional VirtueMart configurations

To configure additional VirtueMart settings, log in to your Joomla installation as administrator, and select Components -> VirtueMart in the menu.
In the lefthand VirtueMart menu, the following settings are relevant to SveaWebPay payment methods:

### Products
#### Taxes & Calculation rules submenu
If you use the SveaWebPay Invoice payment method and charge your users an invoice fee, please see under Troubleshooting below.

### Shop
#### Shop submenu/Vendor tab
* Currency -- this is the "Default Vendor Currency" refered to in the various payment methods above.
* List of accepted currencies -- make sure this list includes all of the currencies allowed by your various SveaWebPay payment methods instances.

### Configuration
#### Configuration submenu/Shop tab
* Languages Settings -- we provide translations for the following languages in our payment module: Swedish (sv-SE), Norwegian (no-NO), Danish (da-DK), Finnish (fi-FI), English (en-GB), German (de-DE), Dutch (nl-NL). Note that you have to have the language installed for the payment module translations to be included.

If you install a supported language after the module, please copy the language translation files to the corresponding language folder manually, or reinstall the svealib payment module and they will be included.

#### Configuration submenu/Checkout tab
* Only registred users can checkout -- this must be unchecked for unregisterd users to be able to checkout.
* One Page Checkout enabled -- this has not been tested in release 2.0 of the payment modules.
* Enable Automatic Selected Payment -- for SveaWebPay Invoice and Paymentplan only, the "Select payment" link will show up even when this option is checked. This is due to these methods needing additional customer credentials which is collected during the "Select payment" step.

## Troubleshooting

### Card and Direct Bank payments: SSL certificates
When using Card and Direct Bank payments, the request made from this module to SveaWebPays systems is made through a redirected form. The response of the payment is then sent back to the module via POST or GET. Which is used is selectable through your SveaWebPay account admin, if you have further questions about this, please contact your SveaWebPay account manager.

Have in mind that a long response string sent via GET could get cut off in some browsers and especially in some servers due to server limitations.
Our recommendation to solve this is to check the PHP configuration of the server and set it to accept at least 512 characters.

As our servers are using SSL certificates, when using POST to fetch the response from a payment, the users browser prompts the user whether to continue or not, should the receiving site not also have a certificate. If the customer then clicks cancel, the payment process is aborted. To avoid this, make sure your server holds a valid SSL certificate. We recommend that you purchase a SSL certificate from your provider.

### Invoice payments: invoice fee discount tax calculation error
There's a bug in how VirtueMart calculates the discount vat in orders containing products with different tax rates, when a SveaWebPay Invoice fee is applied to the order. The bug involves the discount mean vat rate being calculated incorrectly, due to the invoice fee being included along with the product subtotal in the calculation. The sums are correct, but the vat tax rate is wrong. To workaround this, follow the below procedure:

Create a separate tax rule to use for your invoice fee. In VirtueMart admin, go to the Products and Taxes & Calculation rules. Add a new rule with the following: "Vat tax per product", "+%", <your vat rate>. Then go to Shop -> Payment methods, and for your SveaWebPay Invoice payment method instances set the "Tax" setting to use this vat rule. The Discount vat should now be correct on checkout.

### Onepage Rupostel compatibility fix
The Svea Virtuemart module will, for fraud reasons, overwrite the billing address. For that reason the Rupostel onepage plugin will go back to checkout to warn the shopper.
This de-selects the Svea payment. To avoide this from happening, you need to outcomment a section in the onepage code. In file components/com_onepage/controllers/opc.php on row 1814 - 1840 as followed:
![Rupostel code] (https://raw.github.com/sveawebpay/virtuemart2-module/develop/docs/image/rupostel_fix.PNG "Rupostel onepage fix")

### Tax rules: invoice and payment plan supported tax rate types
For the invoice and payment plan payment methods (only), the SveaWebPay module only supports Tax & Calculation Rules using the "Tax" and "VatTax" Types of Arithmetic Operation.