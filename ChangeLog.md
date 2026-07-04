# CHANGELOG SCANINVOICES FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.4.84 - 20260616

 * fix for some race conditions on import thanks to Hans B.

## 1.4.82 - 20260605

* fix manual import
* update translations
* update doc
* handle memcached setup
* fix bad / missing dol include uses

## 1.4.80 - 20260506

* fix pdf upload storm / delay and handle server throttle

## 1.4.78 - 20260106

* fix setup-3 yes/no : unset does not work !

## 1.4.76 - 20251105

* new functionnal auto import peppol files from peppol AP like peppyrus
  (need cap-rel peppol module)

## 1.4.74 - 20250901

* new option in module setup : SCANINVOICES_FORCE_SUPPLIER_SETTINGS_FROM_DOLIBARR
  then you can force supplier payment date / conditions and so from thirdpart settings

## 1.4.72 - 20250729

* fix php warning on array values

## 1.4.70 - 20250630

* fix race condition for due_date if specified on invoice
* fix some php warnings

## 1.4.68 - 20250611

* better search supplier on local dolibarr database when result commes from self hosted
  docwizon server without cap-rel siret webservice

## 1.4.66 - 20250606

* fix setup save fields values

## 1.4.64 - 20250515

* fix undef delivery_untaxed race condition
* update css
* diable global css, only needed for that module don't apply it to all dolibarr pages
* code filters thanks to phpcs

## 1.4.62 - 20250314

* fix module path thanks to Florian Henry (SCOPEN)
* fix undef variable $error thanks to Marc de Lima Lucio (SCOPEN)
* update some css class names to avoid conflicts with other modules (.flex for example)

## 1.4.60 - 20250209

* fix dolibarr 20+ error

## 1.4.58 - 20250127

* fix race condition with manual import & supplier with no vat number on his card

## 1.4.56 - 20250120

* fix auto create supplier
* fix race condition on empty supplier name (or ---)
* add your dolibarr ip on setup to help support
* fix default zone position in manual import

## 1.4.52 - 20241212

* code cleanup thanks to phpstan
* add try/catch on stamp code to try to avoir php error 500
* update js messages to make diff between dolibarr local timeout and
  remote scaninvoices webservice delays

## 1.4.50 - 20241113

* better debugs (track pdf file not linked to invoice)
* add synology webdav share support thanks to CopyColor Sponsoring

## 1.4.48 - 20241001

* remove manual js optimization (bad idea / bugs / users requests)

## 1.4.46 - 20240924

* new message for "no label position changes"

## 1.4.44 - 20240913

* better php8 compatibilty
* fix some multientity sql requests
* start auto link new products to a specific category
* handle vat rate = 0 for lines

## 1.4.40

* setup : fix first drop down to choose default product
* ui/ux : remove etiq for supplier and vat supplier in case of a supplier
          was choosed on first step
* fix : max length for code_fournisseur is 24 in dolibarr database, not 40 !

## 1.4.32

* new option to update product label on new imports

## 1.4.30

* try to fix errors with oblyon theme
* fix collision with doliscan module
* better debugs logs

## 1.4.28 2024-01-30

* fix multicompany support thanks to eldy
* fix choose service/product on admin tab
* add tests against cap-rel / inli global firewall rules

## 1.4.26 20231126

* better version check
* migrate old way conf->global to new getDolGlobal
* code cleanup thanks to phpstan (thanks to dolibarr devcamp)
* fix info & status on thirdparty tab thanks to eldy
* fix security check thanks to eldy
* fix product list: do not display "closed" products thanks to eldy
* fix translation file (remove ")
* new option to disable line imports
* new drag & drop of pdf files on manual import form (or clic on button but drag & drop is easyer)

## 1.4.24 20231103

* new ttc line support thanks to jean-remi (netlogic)
* fix create product / service (view admin settings)
* fix create product (to check)

## 1.4.23 20231020

* fix autopay supplier invoice cron : do not make auto pay on invoices where a payment was already done
* update spanish locale
* new dutch locale

## 1.4.22 20231017

* fix windows / doliwamp compatibility: tempnam and sys_get_temp_dir do not respect open_basedir settings

## 1.4.20 20231013

* fix product / service for product | service (thanks to nico & stephanie)

## 1.4.18 20231010

* fix facturx import (error 500)
* fix an other import error
* fix error 500 on setup.php (version 1.4.16)

## 1.4.12 20231007

* add some debug entries
* fix v17 order button thanks to Franck M.

## 1.4.10 20230726

* fix bug type product / service
* fix url last version

## 1.4.8 20230704

* new compatibility with dolibarr 18 thanks to eldy
* new compatibility with php 8 thanks to eldy
* fix check against new version url
* better 'error' messages (file size and so on)
* supplier could be always editable
* better canvas size
* fix default tms values
* fix compatibility with dolibarr 17.0.2
* fix typo on default reglement id auto fill on import

## 1.4.6 20230413

* add transport / eco-part cf https://doc.cap-rel.fr/projet_scaninvoices/associer_frais_speciaux_aux_factures_importees
* new config to choose default product to use for transport

## 1.4.2 20230327

* better amount handle (race conditions)
* fix manual import date label (html scories)

## 1.4.1 20230227

* public release of 1.3-dev :-)
* fix 1.4.0 white page bug

## 1.3.105 20230219

* add rcpc private copy tax
* fix socid/facid in supplier invoice link
* fix some CSS conflict when using MD theme

## 1.3.102 20230131

* fix product search on database (line import)
* fix undef variable on search products (thanks to Kamel)
* fix vat code for some products (line extract and docwizon upgrade)

## 1.3.98 20230103

* full remove bootstrap + fix css (thanks to eldy)
* enable or disable details import (if server can extract lines)
* add translations into english, german, spanish, italian
* better resize pdf file to be well displayed on screen
* fix take good company when there is 2 companies with same name (and one is closed) (eldy)
* search third parts take active one in priority (eldy)
* fix a qpdf call on linux for pdf_scaninvoice_stamp supplier model in case of protected pdf (like freemobile)

## 1.3.95 20221123

* merge with master+stable-1.0 branches
* fix php warnings (thanks to eldy) (1.0.38)
* better css for smartphones (eldy) (1.0.38)
* fix html tables contents (eldy) (1.0.38)
* fix #15: add invoice label if exist (1.0.38)
* fix #30: create a supplier or customer code if dolibarr (1.0.38)
  is configured with leopard mod (no auto-code generator) (1.0.38)
* scaninvoicesClean_label (1.0.36)
* belgium surtax == ecotax (1.0.36)
* allow negative prices or quantity (1.0.36)
* clean up utf8 hiphen to minus (1.0.36)
* add "now" button thanks to eldy (1.0.36)
* better display (css fixes) on smartphone thanks to eldy (again) (1.0.36)
* implement belgium "surtax" on lines (1.0.31)
* fix multiline import (supplier ref with spaces) : remove spaces (1.0.30)
* fix search for product on supplier ref, not only own refs (1.0.30)
* fix date convert error on manual import (ex with 01/08/2022 or 08/01/2022) (1.0.28)
* fix css (1.0.27)
* add "manual import" button on import details (1.0.27)
* fix manual import flow (flow was dead on one race situation) (1.0.27)
* thanks to eldy, start multicompany compatibility (1.0.27)

## 1.3.94 20550803

* new feature : support for DEE + ECOTAX invoices
* new feature : add supplier 'template' to extract first page and add number on it
                for printing only miminal paper for accounter archives
* big step to 2.0 : import lines tests is working for some suppliers (contact us for details)
* thanks to eldy : Fix the goback page after creation of a scaninvoice setting.
* thanks to eldy : Fix PHP8 + Multientity support + some CSS fixes
* fix flow bugs when supplier -> import invoice on smartphone, take picture -> resume on computer

## 1.0.23 20220705

 * add a check agains php depends functions

## 1.0.22 20220603

  * fix a bug on default product id on some race conditions

## 1.0.21 20220511

  * fix glitches on 4/3 screen (manual import)

## 1.0.20 20220505

 * add a new import settings from a nextcloud share : just drop all your invoices
   (pdf files) and ScanInvoices will get it in background, please have a look to
   cron tasks "ScanInvoices"
 * add a new select dropdown on automatic import : you can now "force" a supplier
   to be used on automatic import (please drag & drop only invoices or the same
   supplier)

## 1.0.18 20220427

* add a import invoice from scaninvoice card on supplier section
* fix (i hoppe) default choice for product on manual import
* big step about jpeg / picture upload from smartphone
* better dropdown list about default products
* add smartphone camera support on upload (experimental)
* add touch event support for smartphone (experimental)
* add "import invoice" on supplier scaninvoice card
* add a new test for success or not and restart / re-upload import of bad invoices

## 1.0.14 + 1.0.15 20220408

* add a new select box on manual import to choose a product to apply for
  that invoice (priority above default product)
* add a prefix on each scaninvoice function (php) to avoid collision with
  others modules
* push all data on manual import submit (even if field is readonly)
* fix css position on some screen resolutions (1.0.15)

## 1.0.12 - 2022406

* thanks to eldy: fix a SQL database type (timestamp)
* add a new message in case of server unavailable or upgrade in progress
* fix #20 : in case of manual import: do not redo ocr on unchanged zones
* add services as module depends
* add due_date if found
* better vat % search
* fix a divider by zero case
* add language chooser on manual import for invoices in other languages
* add a new scheduler task to create a payment of supplier invoices if
  1. you specify CB or LCR or PREV as payment mode
  2. payment due date is over
  3. invoice is validated

## 1.0.10 - 20220317

* set default file ext as pdf due to some bad servers (without magic mime)

## 1.0.9 - 20220315

* fix a race condition when account was disabled server side (or remote api key reached end of life)
* set a default firstname/lastname in case of local dolibarr user does not have one
* add swiss companies auto create if VAT number found like french & belgium
* start allow of xml upload for peppol e-invoices

## 1.0.8 - 20220307

* add line import for factur-x (and others, depends on server) invoices
* add jpeg & png file import support
* auto rotate pictures if needed
* better setup process (two steps, hide API key)
* add some translations for es/en/de/it
* fix multi line import -> default product apply if not found with ref
* fix choose default product (only products with buy price)

## 1.0.5 - 20220222

 * fix file name (problem with spaces, non ascii chars etc. on some platforms)
 * fix read-only fields on manual import (really readonly now)

## 1.0.4 - 20220221

 * fix supplier country affectation on auto-create entity
 * fix manual import and auto-link pdf invoice file

## 1.0.3 - 20220220

* fix imported filename (empty in some rare cases)
* fix supplier name detection (on some cases it stay empty)
* fix 1.0 protocol with server

## 1.0.2 - 20220217

* fix a duplicate issue on import same file twice (one from automatic, then from manual import)
* fix a dolibarr 15 CSRF issue (thanks to eldy)
* split SQL updates files just in case

## 1.0.1 - 20220216

* fix a restricted area special case filter

## 1.0 - 20220215

* official version 1.0 !
* fix delay of payment
* enable import button on manual import in case of data early available
* new manual import : some IA on server will try to found and pre-select area
* use a new pdf text overlay early to enhance data extraction
* better errors catch
* use dolibarr price2num
* object, cron, list, import later concept
* allow http requests not only https (ready for local ocr webservice)
* fix 2 pages model -> fit to "good" positions on 1 page
* add a protocol check (srv / plugin)
* add mass actions (delete / reimport now)
* better menu management thanks to Franck
* new admin configuration thanks to Franck
* better module configuration (add depends on other modules)
* check server protocol version if <> plugin
* move files outside temp dir to prevent remove old files not yet analyzed (delayed by errors or server load)
* better duplicate detection (dolibarr part before sending to ocr server)



## 0.9.16 - 20220130

* fix css (prefix) to avoid collision with others parts of dolibarr

## 0.9.12 - 20220128

* code cleanning
* change manual import "popup" -> no more popup thanks to Franck :)
* please don't forget to desactivate / reactivate plugin (new menu url)
* CSS/Canvas/GPU optimizing for Windows/Edge
* fix CSRF token errors, ready for dolibarr v15 :)
* better errors handler -> push php errors up to user via ajax requests and js
* thanks to eldy, fix a js error (in a loop)
* thanks to eldy, get curl errors
* thanks to franck, fix order status

## 0.9.9 - 20220124

* clean code
* display only buyable products/services in scaninvoice supplier choice
* add scaninvoice in order card
* add order id as ref if exists
* fix php syntax error (thanks eldy
* clean args before sending vat number
* vat number is now not required on invoices
* rename french "fournisseur" by "supplier" in sources
* confirm good values on invoice create to ocr server
* fix vat values (if no vat on invoice, apply a "no vat rules" on dolibarr)
* add a new solution if there is no supplier found in manual import (popup to create supplier then come back to import invoice)
* add server informations on first manual import process
* try to download extracts zones from server on manuel import process if an other user has the same supplier

## 0.9.8 - 20220117

* fix link error (html is displayed) on automatique import error
* fix ratio delta between different import screen settings
* fix css on manual import


## 0.9.7 - 20220115

* uploads dir is now temp, please trash your uploads dir !
* add security check on each php file, big thanks to eldy (again)
* full support for translations (langs everywhere, no more french in html)
* big changes from bootstrap-tour to driver due to bootstrap5 conflicts
* please delete all files from previous installation (directories was not cleaned)
* start of english/espanol/deutsch translations (plugin only, ocr webservice is not yet ready for translations)

## 0.9.6 - 20220112

* Full rewrite of manual import assistant
  * Multipage (2) PDF in manual import is now ok
  * Extract zones are now saved in bdd for the next run (same supplier)
  * Auto documentation on first run (for manual import)
* Full remove of guzzle and there is no more composer part (BIG THANKS to eldy)
* Full integration with remote server (quota, number of scan...)
* And lot of code cleaning
* Thanks to all testers and contributors

## 0.9.4 - 20220103

* Enhance configuration in scaninvoices supplier sheet (numbers, refs, fields)


## 0.9.2 - 20211216

* Manual import with pdf to jpeg converter is now ok
* Big changes in manual import tool

## 0.9.1 - 20211214

* Mass upload is now available by default, please have a look to that demo
https://inlitube.fr/w/dtw1bbbzBoj7jjP4BmhKsX

## 0.9.0 - 20211208

* Add a new configuration entry : enter your ocr server address (for self hosted server or dedicated servers)

## 0.8.9 - 20211201

* PHP 7.0 support - but invoicex is (temporary) disabled due to lack of PHP 7 support of some intermediates modules
* Full use of webservice (call webservice to transform pdf to jpeg files for "manual" import)
* Thanks to eldy:
  * vat rate is now not only "20%" (it was for first step and prototype time)
  * name company is now in "nom" and "name_alias"
  * set a more complete description of module
  * uploaded file name must contains the ref of invoice as a the prefix
  * fix bad name of tab
  * use the dol_copy instead of copy for a better portability


## 0.8 - 20211110

* "Manual" import call OCR webservice instead of local OCR soft
* ShowWait / HideWait is well used
* PDF File to inspect is passed from auto import to manual import in case of auto import failed
* Fixe some bugs


## 0.6 - 20211103

* "Manual" import stuff is working now
* Auto redirect from manuel import to invoices in case of success

## 0.5 - 20211026

* Next step - after dolibarr camp !
  * full screen manual import
  * you can choose a default product for each provider

## 0.2 - 20211015

* New version for early beta testers :-)
  * auto create company
  * auto import invoices


## 0.1

* Initial version for early alpha testers
