<?php
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

/**
 * Initialisation API prestashop
 */
include(dirname(__FILE__).'/../../config/config.inc.php');
if (_PS_VERSION_ < '1.5') {
	// token
	$token = Tools::getValue('token');
	$token_module = Tools::getToken(false);
	if($token == $token_module){
		require_once(dirname(__FILE__).'/1.4/hipay_tpp.php');
		$hipay = new HiPay_Tpp();
		$hipay->validation_process();
	}else{
		echo "Vous n'avez pas la permission.";
	}
}else{ 
	echo 'Version Module Hipay is not compatible with your version.';
}