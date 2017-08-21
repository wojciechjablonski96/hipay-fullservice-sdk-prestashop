<?php
/**
* 2007-2013 Profileo
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to contact@profileo.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade Profileo to newer
* versions in the future. If you wish to customize Profileo for your
* needs please refer to http://www.profileo.com for more information.
*
*  @author    Profileo <contact@profileo.com>
*  @copyright 2007-2013 Profileo
*  @license   http://opensource.org/licenses/afl-3.0.php
*  
*  International Registered Trademark & Property of Profileo
*/

class HipayClass extends ObjectModel {

	public static function getAPIURL() {
		// Production = https://secure-gateway.hipay-tpp.com/rest/v1/
		// Stage/testing = https://stage-secure-gateway.hipay-tpp.com/rest/v1/
		return 'https://' . (Configuration::get('HIPAY_TEST_MODE') ? 'stage-' : '') . 'secure-gateway.hipay-tpp.com/rest/v1/';
	}

	public static function getAPITokenURL() {
		// Warning : URL different from API Url
		// Production = https://stage-secure-vault.hipay-tpp.com/rest/v1/token/
		// Stage/testing = https://secure-vault.hipay-tpp.com/rest/v1/token/
		return 'https://' . (Configuration::get('HIPAY_TEST_MODE') ? 'stage-' : '') . 'secure-vault.hipay-tpp.com/rest/v1/token/';
	}

	public static function getAPIUsername($shop_id=null) {
		if($shop_id>0)
		{	
			$context = Context::getContext();
			if($shop_id!=$context->shop->id)
			{
				$TMP_HIPAY_TEST_MODE = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE `name` = "'.pSQL('HIPAY_TEST_MODE').'" AND `id_shop`="'.$shop_id.'" ');
				$TMP_HIPAY_TEST_API_USERNAME = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE `name` = "'.pSQL('HIPAY_TEST_API_USERNAME').'" AND `id_shop`="'.$shop_id.'" ');
				$TMP_HIPAY_API_USERNAME = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE `name` = "'.pSQL('HIPAY_API_USERNAME').'" AND `id_shop`="'.$shop_id.'" ');
				
				return ($TMP_HIPAY_TEST_MODE) ? $TMP_HIPAY_TEST_API_USERNAME : $TMP_HIPAY_API_USERNAME;

			}else
				return (Configuration::get('HIPAY_TEST_MODE') ? Configuration::get('HIPAY_TEST_API_USERNAME') : Configuration::get('HIPAY_API_USERNAME'));
		}else
			return (Configuration::get('HIPAY_TEST_MODE') ? Configuration::get('HIPAY_TEST_API_USERNAME') : Configuration::get('HIPAY_API_USERNAME'));
	}

	public static function getAPIPassword($shop_id=null) {
		if($shop_id>0)
		{	
			$context = Context::getContext();
			if($shop_id!=$context->shop->id)
			{
				$TMP_HIPAY_TEST_MODE = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE `name` = "'.pSQL('HIPAY_TEST_MODE').'" AND `id_shop`="'.$shop_id.'" ');
				$TMP_HIPAY_TEST_API_PASSWORD = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE `name` = "'.pSQL('HIPAY_TEST_API_PASSWORD').'" AND `id_shop`="'.$shop_id.'" ');
				$TMP_HIPAY_API_PASSWORD = Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE `name` = "'.pSQL('HIPAY_API_PASSWORD').'" AND `id_shop`="'.$shop_id.'" ');
				
				return ($TMP_HIPAY_TEST_MODE) ? $TMP_HIPAY_TEST_API_PASSWORD : $TMP_HIPAY_API_PASSWORD;

			}else
				return (Configuration::get('HIPAY_TEST_MODE') ? Configuration::get('HIPAY_TEST_API_PASSWORD') : Configuration::get('HIPAY_API_PASSWORD'));
		}else
			return (Configuration::get('HIPAY_TEST_MODE') ? Configuration::get('HIPAY_TEST_API_PASSWORD') : Configuration::get('HIPAY_API_PASSWORD'));
	}

	/*
	 * Returns a URL with either HTTPS or HTTP based on initial caller's HTTP(S) status
	 */

	public static function getRedirectionUrl($url = NULL) {
		if ($url == NULL)
			return false;

		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
			// SSL connection found, replace http:// by https://
			return str_ireplace('http://', 'https://', $url);
		}
		return $url;
	}

	/*
	 * Returns a HiPay TPP compatible gender information @param $id_gender : Prestashop gender id
	 */

	public static function getAPIGender($id_gender = NULL) {
		// Gender of the customer (M=male, F=female, U=unknown).
		$return_gender = 'U';
		
		if ($id_gender == NULL)
			$return_gender = 'U';

		switch ($id_gender) {
			case '1' :
				$return_gender = 'M';
				break;
			case '2' :
				$return_gender = 'F';
				break;
			default :
				$return_gender = 'U';
				break;
		}
		
		return $return_gender;
	}

	public static function getCountryCode($country_name = null) {
		if ($country_name == null)
			Tools::redirect('index.php?controller=order&xer=8');

		return Db::getInstance()->getValue("
            SELECT c.iso_code
            FROM `" . _DB_PREFIX_ . "country` AS c
            LEFT JOIN `" . _DB_PREFIX_ . "country_lang` AS cl ON cl.id_country=c.id_country
            WHERE cl.name='" . $country_name . "'");
	}

	public static function getLanguageCode($iso_code = 'en') {
		$lang_code = 'en_GB';
		switch (Tools::strtolower($iso_code)) {
			case 'fr' :
				$lang_code = 'fr_FR';
				break;
			case 'fr' :
				$lang_code = 'fr_BE';
				break;
			case 'fr' :
				$lang_code = 'fr_LU';
				break;
			case 'lv' :
				$lang_code = 'lv_LV';
				break;
			case 'es' :
				$lang_code = 'es_ES';
				break;
			case 'pt' :
				$lang_code = 'pt_PT';
				break;
			case 'nl' :
				$lang_code = 'nl_NL';
				break;
			case 'nl' :
				$lang_code = 'nl_BE';
				break;
			case 'de' :
				$lang_code = 'de_DE';
				break;
			case 'de' :
				$lang_code = 'de_AT';
				break;
			case 'de' :
				$lang_code = 'de_LU';
				break;
			case 'it' :
				$lang_code = 'it_IT';
				break;
			case 'da' :
				$lang_code = 'da_DK';
				break;
			case 'cs' :
				$lang_code = 'cs_CZ';
				break;
			case 'pl' :
				$lang_code = 'pl_PL';
				break;
			case 'fi' :
				$lang_code = 'fi_FI';
				break;
			case 'hu' :
				$lang_code = 'hu_HU';
				break;
			case 'no' :
				$lang_code = 'no_NO';
				break;
			case 'sv' :
				$lang_code = 'sv_SE';
				break;
			case 'zh' :
				$lang_code = 'zh_CN';
				break;
			case 'en' :
			default :
				$lang_code = 'en_GB';
				break;
		}
		return $lang_code;
	}
	
	public static function duplicateCart() {
		$hipay = new HiPay_Tpp();
		// Taken from controllers\front\ParentOrderController.php , keyword "submitReorder"
		/* Disable some cache related bugs on the cart/order */
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		$errors = array();
		$context = Context::getContext();
		//$oldCart = new Cart(Order::getCartIdStatic($context->cookie->id_cart, $context->customer->id));
		$oldCart = new Cart($context->cookie->id_cart);
		$duplication = $oldCart->duplicate();
		if (!$duplication || !Validate::isLoadedObject($duplication['cart']))
			$errors[] = $hipay->l('Sorry. We cannot renew your order.', 'hipay');
		else if (!$duplication['success'])
			$errors[] = $hipay->l('Some items are no longer available, and we are unable to renew your order.', 'hipay');
		else
		{
			// FR. Le panier courant a d�j� �t� utilis� sur la plateforme Hipay. Un nouveau panier viens d'�tre cr�� afin de proc�der malgr� tout au paiement. Attention, celui-ci va impliquer une nouvelle transaction sur la plateforme Hipay. 
			// EN. The current cart has already been used on the Hipay platform. A new cart just been created to make the payment anyway. Warning, this will involve a new transaction on the Hipay platform.
			$errors[] = $hipay->l('The current cart has already been used on the Hipay platform. A new cart just been created to make the payment anyway. Warning, this will involve a new transaction on the Hipay platform.', 'hipay');
			$context->cookie->id_cart = $duplication['cart']->id;
			$context->cookie->write();
		}
		if(count($errors))
			return $errors;
		return false;
	}

	public static function unsetCart() {
		$context = Context::getContext();

		$cart = new Cart($context->cookie->id_cart);
		unset($context->cookie->id_cart, $cart, $context->cookie->checkedTOS);
		$context->cookie->check_cgv = false;
		$context->cookie->write();
		$context->cookie->update();

		return true;
	}

	public static function getShowMemorization() {
		$allow_memorize = 'false';
		// Verify if systems should display memorized tokens
		if (Configuration::get('HIPAY_MEMORIZE')) {
			// Verify if card should allow memorization.
			$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');
			$cart_arr = explode(',', $card_str);
			foreach ($cart_arr as $value) {
				if ($value == 'american-express') {
					$allow_memorize = 'true';
				}
				if ($value == 'cb') {
					$allow_memorize = 'true';
				}
				if ($value == 'visa') {
					$allow_memorize = 'true';
				}
				if ($value == 'mastercard') {
					$allow_memorize = 'true';
				}
			}
		} else {
			$allow_memorize = 'false';
		}

		return $allow_memorize;
	}
	
	
	public static function getConfiguration($config_name=null) {
		if($config_name==null)
			return _PS_OS_ERROR_;
			
		// Check if config already in db
		$sql_get_config = "SELECT value FROM `" . _DB_PREFIX_ . "configuration` WHERE name='".$config_name."'";
		$result_get_config = Db::getInstance()->getRow($sql_get_config);
		
		if ($result_get_config['value']) {
			return $result_get_config['value'];
		} else {
			return _PS_OS_ERROR_;
		}
	}

	/**
	 * Generate unique token
	 * @param type $cartId
	 * @param type $page
	 * @return type
	 */
	public static function getHipayToken($cartId, $page = 'accept.php')
	{
		return md5(Tools::getToken($page).$cartId);
	}

	/**
	 * Check if hipay server signature match post data + passphrase
	 * @param type $signature
	 * @param type $config
	 * @param type $fromNotification
	 * @return boolean
	 */
	public static function checkSignature(
		$signature, $fromNotification = false
	)
	{
		$passphrase     = Configuration::get('HIPAY_TEST_MODE') ? Configuration::get('HIPAY_TEST_API_PASSPHRASE')
			: Configuration::get('HIPAY_API_PASSPHRASE');

		if (empty($passphrase) && empty($signature)) {
			return true;
		}

		if ($fromNotification) {
			$rawPostData = Tools::file_get_contents("php://input");
			if ($signature == sha1($rawPostData.$passphrase)) {
				return true;
			}
			return false;
		}

		return false;
	}
}

// Include other Classes with default Hipay Class
include_once (dirname(__FILE__) . '/HipayLogger.php');
include_once (dirname(__FILE__) . '/HipayAPI.php');
include_once (dirname(__FILE__) . '/HipayToken.php');
include_once (dirname(__FILE__) . '/HipayMaintenance.php');