# Virtuemart 2 - Svea WebPay payment module installation guide

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


##Important info
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
