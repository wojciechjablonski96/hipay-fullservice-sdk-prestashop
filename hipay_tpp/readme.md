/**
* Copyright © 2015 HIPAY
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to support.tpp@hipay.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade HiPay to newer
* versions in the future. If you wish to customize HiPay for your
* needs please refer to http://www.hipayfullservice.com/ for more information.
*
*  @author    Support HiPay <support.tpp@hipay.com>
*  @copyright © 2015 HIPAY
*  @license   http://opensource.org/licenses/afl-3.0.php
*  
*  Copyright © 2015 HIPAY
*/

About
=====
+ Payment via HiPay TPP for PrestaShop.
+ Version 1.1.26

System Requirements
===================
+ HiPay TPP API key
+ PrestaShop 1.6.x
+ Curl PHP Extension
+ JSON Encode
  
Configuration Instructions
==========================
    1. Upload files to your PrestaShop installation.
    2. Go to your PrestaShop administration Modules list.
    Click on Payments and Gateways in the Categories list, find HiPay TPP and click [Install]  
    3. In module settings "Configuration Module Hipay TPP" <- set your API key and configure to your site's needs.		
    4. In module settings "Informations sur la configuration HiPay TPP" <- Use those information to configure your HiPay TPP backoffice to allow your site to communicate with 
    the module properly

Advanced configuration on Prestashop 1.4
========================================

    1. Copy the URL in module settings "Configuration Module Hipay TPP" :
       http(s)://your-domain.com/modules/hipay_tpp/cron.php?token=XXXX-token-display-in-module-settings-XXXX
    2. Open your page management for your server and configure the crontab
    3. Configure the frequency to all 5min
OR
    1. Connect to your SSH server
    2. write : sudo gedit /etc/crontab
    3. configure the crontab with this line :
       */5 * * * * wget http(s)://your-domain.com/modules/hipay_tpp/cron.php?token=XXXX-token-display-in-module-settings-XXXX 
    4. Save 

### Tested with:
+ PrestaShop 1.6.x

### Versionning:
#### v1.1.26:
+ Force Callback in live
+ Optimization treatment when the callback change status
+ Optimization Order message with more informations
+ Compatibility with multishop, the callback use the id_shop of cart - Prestashop > v1.5
+ Update treatment on Prestashop 1.4 : Need to configure the server with a URL Cron.php to the crontab
+ Bugfix - Correction on the configuration page about the log table was not displayed correctly in English
+ Optimization Callback Captured (118) - if status captured exist in the order history, the status was not changed again
+ All status before Authorized (116) create an order with the proper status


