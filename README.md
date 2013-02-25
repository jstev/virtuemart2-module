# Virtuemart 2 - Svea WebPay betalmodul installationsguide

##Index
* [Krav] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#Krav)
* [Installation] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#Installation)
* [Viktig info] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#Viktiginfo)


## Krav
Joomla 2.5
VirtueMart 2.0 och uppåt

## Installation
1.	Kör scriptet från install.sql med prefixet från din egen joomlatabell
2.	Kopiera över resterande filer till rootkatalogen för din joomlainstallation
3.	Gå in under inställningarna för virtuemart, under shop->payment methods
4.	Välj New
5.	Fyll i SveaWebPay i Payment Name
6.	Ändra published till Yes
7.	Fyll eventuell beskrivning i Payment Description, om du tex tänker ta ut en faktureringsavgift eller liknande
8.	Välj SveaWebPay Payment under Payment group
9.	Klicka på fliken configuration
10.	 Välj om du vill visa vår logotyp
11.	 Fyll i Merchant ID och Secret Word

Du kan du använda SveaWebPay:s betalsida med de betalningsmetoder som du har hos Svea.

##Viktig info
Anropet från denna modul till våra system sker genom att ett formulär skickas via en redirect. 
Svaret från betalningen skickas tillbaka till modulen från oss via POST eller GET (valbart i admin).

###Vid GET
Tänk på att en för lång redirect som skickas via GET kan kapas av vissa webbläsare och vissa applikationsservrar filtreras bort då de anses vara ”för långa” av säkerhetsskäl. 
Vi rekommenderar då att man ändrar inställningarna i php på servern så att minst 512 tecken kan tas emot via GET.

###Vid POST
Då vår server använder SSL-certifikat får man tänka på att om man använder POST för att skicka respons från oss till en vanlig http sida (utan SSL-certifikat, utan https) så kommer det att komma upp en fråga till kunden vid avslutat köp om att sidan är okrypterad och om denne då vill fortsätta. 
Skulle kunden då klicka avbryt kommer detta leda till att köpet inte registreras. 
Därför rekommenderar vi att ni kontaktar er serverleverantör och ber om ett SSL-certifikat.

Vi kan rekommendera följande certifikatsleverantörer:
* InfraSec:  infrasec.se
* VeriSign : visionssl.se
