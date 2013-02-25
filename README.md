# Virtuemart 2 - Svea WebPay betalmodul installationsguide

##Index
* [Krav] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#Krav)
* [Installation] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#Installation)
* [Viktig info] (https://github.com/sveawebpay/virtuemart2-module/tree/develop#Viktiginfo)


## Krav
Joomla 2.5
VirtueMart 2.0 och upp�t

## Installation
1.	K�r scriptet fr�n install.sql med prefixet fr�n din egen joomlatabell
2.	Kopiera �ver resterande filer till rootkatalogen f�r din joomlainstallation
3.	G� in under inst�llningarna f�r virtuemart, under shop->payment methods
4.	V�lj New
5.	Fyll i SveaWebPay i Payment Name
6.	�ndra published till Yes
7.	Fyll eventuell beskrivning i Payment Description, om du tex t�nker ta ut en faktureringsavgift eller liknande
8.	V�lj SveaWebPay Payment under Payment group
9.	Klicka p� fliken configuration
10.	 V�lj om du vill visa v�r logotyp
11.	 Fyll i Merchant ID och Secret Word

Du kan du anv�nda SveaWebPay:s betalsida med de betalningsmetoder som du har hos Svea.

##Viktig info
Anropet fr�n denna modul till v�ra system sker genom att ett formul�r skickas via en redirect. 
Svaret fr�n betalningen skickas tillbaka till modulen fr�n oss via POST eller GET (valbart i admin).

###Vid GET
T�nk p� att en f�r l�ng redirect som skickas via GET kan kapas av vissa webbl�sare och vissa applikationsservrar filtreras bort d� de anses vara �f�r l�nga� av s�kerhetssk�l. 
Vi rekommenderar d� att man �ndrar inst�llningarna i php p� servern s� att minst 512 tecken kan tas emot via GET.

###Vid POST
D� v�r server anv�nder SSL-certifikat f�r man t�nka p� att om man anv�nder POST f�r att skicka respons fr�n oss till en vanlig http sida (utan SSL-certifikat, utan https) s� kommer det att komma upp en fr�ga till kunden vid avslutat k�p om att sidan �r okrypterad och om denne d� vill forts�tta. 
Skulle kunden d� klicka avbryt kommer detta leda till att k�pet inte registreras. 
D�rf�r rekommenderar vi att ni kontaktar er serverleverant�r och ber om ett SSL-certifikat.

Vi kan rekommendera f�ljande certifikatsleverant�rer:
* InfraSec:  infrasec.se
* VeriSign : visionssl.se
