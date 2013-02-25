# Virtuemart 2 - Svea WebPay payment module installation guide

##Index
* [Requirements] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#requirements)
* [Installation] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#installation)
* [Important info] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#important-info)


## Requirements
Joomla 2.5
VirtueMart 2.0+

## Installation
1.	Kör scriptet från install.sql med prefixet från joomlatabellen
1.  Run the install.sql script using your joomla prefix
2.	Kopiera över resterande filer till rootkatalogen för din joomlainstallation
2.  Copy the remaining files to the root catalog of your joomla installation
3.	Gå in under inställningarna för virtuemart, under shop->payment methods
3.  Go to the virtuemart settings to shop -> payment methods
4.	Välj New
4.  Select New
5.	Fyll i SveaWebPay i Payment Name
5.  Fill in SveaWebPay in Payment Name
6.	Ändra published till Yes
6.  Change published to Yes
7.	Fyll eventuell beskrivning i Payment Description, om du tex tänker ta ut en faktureringsavgift eller liknande
7.  Fill in the description if you i.e are planning to take an invoice fee or give some other info
8.	Välj SveaWebPay Payment under Payment group
8.  Select SveaWebPay Payment under Payment group and click save
9.	Klicka på fliken configuration
9.  Select the Configuration tab
10.	 Välj om du vill visa vår logotyp
10. Select if you want to show the SVEA logo
11.	 Fyll i Merchant ID och Secret Word
11. Fill in you Merchant ID and Secret Word

Du kan du använda SveaWebPay:s betalsida med de betalningsmetoder som du har hos Svea.
You can now use SveaWebPays pay page with your configured payment methods

##Important info
Anropet från denna modul till våra system sker genom att ett formulär skickas via en redirect. 
Svaret från betalningen skickas tillbaka till modulen från oss via POST eller GET (valbart i admin).

The request made from this module to SVEAs systems is made through a redirected form. 
The response of the payment is then sent back to the module via POST or GET (selectable in our admin).

###When using GET
Tänk på att en för lång redirect som skickas via GET kan kapas av vissa webbläsare och vissa applikationsservrar filtreras bort då de anses vara ”för långa” av säkerhetsskäl. 
Vi rekommenderar då att man ändrar inställningarna i php på servern så att minst 512 tecken kan tas emot via GET.




###When using POST
Då vår server använder SSL-certifikat får man tänka på att om man använder POST för att skicka respons från oss till en vanlig http sida (utan SSL-certifikat, utan https) så kommer det att komma upp en fråga till kunden vid avslutat köp om att sidan är okrypterad och om denne då vill fortsätta. 
Skulle kunden då klicka avbryt kommer detta leda till att köpet inte registreras. 
Därför rekommenderar vi att ni kontaktar er serverleverantör och ber om ett SSL-certifikat.

Vi kan rekommendera följande certifikatsleverantörer:
* InfraSec:  infrasec.se
* VeriSign : visionssl.se
