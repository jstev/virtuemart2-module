# Virtuemart 2 - Svea WebPay payment module installation guide

##Index
* [Requirements] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#requirements)
* [Installation] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#installation)
* [Important info] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#important-info)


## Requirements
Joomla 2.5
VirtueMart 2.0+

## Installation
1.	K�r scriptet fr�n install.sql med prefixet fr�n joomlatabellen
1.  Run the install.sql script using your joomla prefix
2.	Kopiera �ver resterande filer till rootkatalogen f�r din joomlainstallation
2.  Copy the remaining files to the root catalog of your joomla installation
3.	G� in under inst�llningarna f�r virtuemart, under shop->payment methods
3.  Go to the virtuemart settings to shop -> payment methods
4.	V�lj New
4.  Select New
5.	Fyll i SveaWebPay i Payment Name
5.  Fill in SveaWebPay in Payment Name
6.	�ndra published till Yes
6.  Change published to Yes
7.	Fyll eventuell beskrivning i Payment Description, om du tex t�nker ta ut en faktureringsavgift eller liknande
7.  Fill in the description if you i.e are planning to take an invoice fee or give some other info
8.	V�lj SveaWebPay Payment under Payment group
8.  Select SveaWebPay Payment under Payment group and click save
9.	Klicka p� fliken configuration
9.  Select the Configuration tab
10.	 V�lj om du vill visa v�r logotyp
10. Select if you want to show the SVEA logo
11.	 Fyll i Merchant ID och Secret Word
11. Fill in you Merchant ID and Secret Word

Du kan du anv�nda SveaWebPay:s betalsida med de betalningsmetoder som du har hos Svea.
You can now use SveaWebPays pay page with your configured payment methods

##Important info
Anropet fr�n denna modul till v�ra system sker genom att ett formul�r skickas via en redirect. 
Svaret fr�n betalningen skickas tillbaka till modulen fr�n oss via POST eller GET (valbart i admin).

The request made from this module to SVEAs systems is made through a redirected form. 
The response of the payment is then sent back to the module via POST or GET (selectable in our admin).

###When using GET
T�nk p� att en f�r l�ng redirect som skickas via GET kan kapas av vissa webbl�sare och vissa applikationsservrar filtreras bort d� de anses vara �f�r l�nga� av s�kerhetssk�l. 
Vi rekommenderar d� att man �ndrar inst�llningarna i php p� servern s� att minst 512 tecken kan tas emot via GET.




###When using POST
D� v�r server anv�nder SSL-certifikat f�r man t�nka p� att om man anv�nder POST f�r att skicka respons fr�n oss till en vanlig http sida (utan SSL-certifikat, utan https) s� kommer det att komma upp en fr�ga till kunden vid avslutat k�p om att sidan �r okrypterad och om denne d� vill forts�tta. 
Skulle kunden d� klicka avbryt kommer detta leda till att k�pet inte registreras. 
D�rf�r rekommenderar vi att ni kontaktar er serverleverant�r och ber om ett SSL-certifikat.

Vi kan rekommendera f�ljande certifikatsleverant�rer:
* InfraSec:  infrasec.se
* VeriSign : visionssl.se
