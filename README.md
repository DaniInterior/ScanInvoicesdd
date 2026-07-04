# SCANINVOICES FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

One target : easy import supplier invoices into dolibarr. Just put pdf file then let do the magic.

For the moment we try to find a solution for pdf scanned documents ("paper" invoices) because i'm sure it will be very easy to do the same with real numeric pdf invoices (not pictures, PDF with plain text you can select with your mouse).

Other modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be define manually by editing files into directories *langs*.

## Installation

### nginx

in case of nginx install; please note that special rules you have to add (that is an example, please adapt it)

```
location /custom/scaninvoices/api.php {
    include /etc/nginx/fastcgi_params;
    #note: configurez la ligne fastcgi_pass selon votre configuration générale de nginx...
    #fastcgi_pass  127.0.0.1:9004;
    #fastcgi_pass unix:/var/run/php-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param  SCRIPT_FILENAME  /where/is/your/dolibarr/htdocs/custom/scaninvoices/api.php;
    fastcgi_param  BASE_VERB  /scaninvoices/api.php;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
}
```

### From the ZIP file and GUI interface

- If you get the module in a zip file (like when downloading it from the market place [Dolistore](https://www.dolistore.com)), go into
menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.

Note: If this screen tell you there is no custom directory, check your setup is correct:

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```

### <a name="final_steps"></a>Final steps

From your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup" -> "Modules"
  - You should now be able to find and enable the module

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
