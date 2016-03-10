<?php

/*
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
 *  @author Profileo <contact@profileo.com>
 *  @copyright  2007-2013 Profileo
 *
 *  International Registered Trademark & Property of Profileo
 */

/**
 *
 * @since 1.5.0
 */
class HiPay_TppPaymentModuleFrontController extends ModuleFrontController {

	public $ssl = true;

	/**
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent() {
		$hipay = new HiPay_Tpp();

		$this->display_column_left = false;
		$this->display_column_right = false;
		parent::initContent();

		#PROFILEO64 - Multishop issue when using $this->context->cart. Switching to Context::getContext()
		//$cart = $this->context->cart;
		$context = Context::getContext();
		$cart = $context->cart;

		if (!$this->module->checkCurrency($cart)) {
			Tools::redirect('index.php?controller=order&xer=1');
		}
	
		// Check if cart_id has already been stored in tbl cart_sent
		$override_payment_mode = false;
		$cart_id_count = Db::getInstance()->getValue("SELECT COUNT( cart_id ) FROM  `" . _DB_PREFIX_ . "hipay_cart_sent` WHERE cart_id = '".(int)$cart->id."'");
		if($cart_id_count==0)
		{
			// Not found. Add new entry
			$sql_add_cart_id = "INSERT INTO `" . _DB_PREFIX_ . "hipay_cart_sent` (`cart_id`, `timestamp`)
            VALUES('" . (int)$cart->id . "', NOW() )";
			Db::getInstance()->execute( $sql_add_cart_id );
		}
		/*
		// TPPPRS-23
		else{
			// Found. Duplicate cart
			$duplicate_status_msg = HipayClass::duplicateCart();
			if($duplicate_status_msg)
			{
				$override_payment_mode = true;
			}
		}*/

		
		$context->smarty->assign(array(
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->module->getCurrency((int) $cart->id_currency),
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->module->getPathUri(),
			'this_path_bw' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/'
		));

		$context->controller->addCSS(_MODULE_DIR_ . $this->module->name . '/css/hipay.css');
		$context->controller->addJs(_MODULE_DIR_ . $this->module->name . '/js/15hipay.js');

		$hipay_payment_mode = Configuration::get('HIPAY_PAYMENT_MODE');

		if (Tools::getValue('cartMemorizeToken')) {
			$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "hipay_tokens_tmp` (`cart_id`) VALUES('" . $cart->id . "')";
			@Db::getInstance()->execute($sql_insert);
		}

		// Initializing the payment mode to the default configuration mode
		$payment_mode = Configuration::get('HIPAY_PAYMENT_MODE');

		// Check card used - if card used is a local card, force mode 'dedicated page'
		if (Tools::isSubmit('localcardToken') && tools::getValue('localcardToken')) {
			// Override to mode page dedicated
			$payment_mode = 3;
		}
		
		// Last check, if $override_payment_mode = true then override all payement modes and force error message display
		/*
		// TPPPRS-23
		if($override_payment_mode) {
			// Override to mode page cart duplicated
			$payment_mode = 4;
			// Use $duplicate_status_msg to display msg err
		}*/

		// Different calls depending on Payment mode
		switch ($payment_mode) {
			case 1 :
				// Mode Iframe
				$data = HipayApi::getApiData($cart, 'iframe');

				$response = $this->restApi('hpayment', $data);

				// Update to display montant
				$currency_array = $this->module->getCurrency((int) $cart->id_currency);
				$currency = $currency_array [0] ['iso_code'];
				foreach ($currency_array as $key => $value) {
					if ($value['id_currency'] == $cart->id_currency) {
						$actual_currency = $value['iso_code'];
					}
				}
				if ($currency != $actual_currency)
					$currency = $actual_currency;

				if (Tools::strlen(Configuration::get('HIPAY_IFRAME_WIDTH')) > 0)
					$iframe_width = Configuration::get('HIPAY_IFRAME_WIDTH');
				else
					$iframe_width = '100%';
				if (Tools::strlen(Configuration::get('HIPAY_IFRAME_HEIGHT')) > 0)
					$iframe_height = Configuration::get('HIPAY_IFRAME_HEIGHT');
				else
					$iframe_height = '670';
					
				if(_PS_VERSION_ >= '1.6'){
					$show_breadcrumb = false;
				} else {
					$show_breadcrumb = true;
				}

				$context->smarty->assign(array(
					'iframe_url' => $response->forwardUrl,
					'cart_id' => $cart->id,
					'currency' => $currency,
					'show_breadcrumb' => $show_breadcrumb,
					'amount' => $cart->getOrderTotal(true, Cart::BOTH),
					'iframe_width' => $iframe_width,
					'iframe_height' => $iframe_height,
				));

				$payment_tpl = 'payment_execution_iframe.tpl';
				break;

			case 2 :
				// Mode API
				// Constructs data array and sends it as a parameter to the tpl
				$currency_array = $this->module->getCurrency((int) $cart->id_currency);
				$currency = $currency_array [0] ['iso_code'];
				foreach ($currency_array as $key => $value) {
					if ($value['id_currency'] == $cart->id_currency) {
						$actual_currency = $value['iso_code'];
					}
				}
				if ($currency != $actual_currency)
					$currency = $actual_currency;

				$context->smarty->assign(array(
					'status_error' => '200', // Force to ok for first call
					'cart_id' => $cart->id,
					'currency' => $currency,
					'amount' => $cart->getOrderTotal(true, Cart::BOTH)
				));
				// Tpl will load a form that will store those infomations.

				$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');

				$selection_cards = array(
					'american-express' => $hipay->l('American Express'),
					'bcmc' => $hipay->l('Bancontact / Mister Cash'),
					'cb' => $hipay->l('Carte Bancaire'),
					'maestro' => $hipay->l('Maestro'),
					'mastercard' => $hipay->l('MasterCard'),
					'visa' => $hipay->l('Visa')
				);

				$cart_arr = explode(',', $card_str);
				$carte = array();

				foreach ($cart_arr as $key => $value) {
					foreach ($selection_cards as $key1 => $value1) {
						if ($key1 && $value == $key1) {
							$carte [$key1] = $value1;
						}
					}
				}

				$context->smarty->assign(array(
					'cartes' => $carte
				));

				$tokens = HipayToken::getTokens($cart->id_customer); //
				if (isset($tokens['0'])) {
					$token_display = 'true';
				} else {
					$token_display = 'false';
				}

				$allow_memorize = HipayClass::getShowMemorization();
				
				if(_PS_VERSION_ >= '1.6'){
					$show_breadcrumb = false;
				} else {
					$show_breadcrumb = true;
				}

				$context->smarty->assign(array(
					'token_display' => $token_display,
					'allow_memorize' => $allow_memorize,
					'show_breadcrumb' => $show_breadcrumb,
					'tokens' => $tokens
				));

				$payment_tpl = 'payment_execution_api.tpl';
				break;
			case 3:
				$local_card = tools::getValue('localcardToken');

				$data = HipayApi::getApiData($cart, null, null, $local_card);

				if ($local_card == 'sofort-uberweisung' || $local_card == 'sisal' || $local_card == 'przelewy24' || $local_card == 'webmoney' || $local_card == 'yandex' || $local_card == 'paypal') {
					$data['payment_product'] = $local_card;
					unset($data['payment_product_list']);
					unset($data['merchant_display_name']);
					unset($data['css']);

					$response = $this->restApi('order', $data);
				}
				else
					$response = $this->restApi('hpayment', $data);

				if ($response == false) // Wrong response, redirect to page order first step
					Tools::redirect('index.php?controller=order&xer=2');

				Tools::redirect($response->forwardUrl);

				break;
			case 4 :
					// Use $duplicate_status_msg to display msg err
					if(_PS_VERSION_ >= '1.6'){
						$show_breadcrumb = false;
					} else {
						$show_breadcrumb = true;
					}
					
					$context->smarty->assign(array(
						'duplicate_status_msg' => $duplicate_status_msg,
						'show_breadcrumb' => $show_breadcrumb,
					));
					
					$payment_tpl = 'payment_cart_duplicate.tpl';
				break;
			case 0 :
			default :
				// Dedicated page
				// NO TPL NEEDED, will redirect to response forwardURL
				if (Tools::isSubmit('localcardToken') && tools::getValue('localcardToken')) {
					$local_card = tools::getValue('localcardToken');
				} else {
					$local_card = null;
				}

				$data = HipayApi::getApiData($cart, null, null, $local_card);

				$response = $this->restApi('hpayment', $data);

				if ($response == false) // Wrong response, redirect to page order first step
					Tools::redirect('index.php?controller=order&xer=2');

				Tools::redirect($response->forwardUrl);
				break;
		}

		$this->setTemplate($payment_tpl);
	}

	/**
	 * returns API response array()
	 */
	public function restApi($action = null, $data = null) {
		try {
			$hipay = new HiPay_Tpp();
			HipayLogger::addLog($hipay->l('API call initiated', 'hipay'), HipayLogger::APICALL, 'Action : ' . $action . ' - Data : ' . Tools::jsonEncode($data));

			if ($action == null)
				Tools::redirect('index.php?controller=order&xer=6');
			if ($data == null)
				Tools::redirect('index.php?controller=order&xer=7');

			define('API_ENDPOINT', $this->getAPIURL());
			define('API_USERNAME', $this->getAPIUsername());
			define('API_PASSWORD', $this->getAPIPassword());

			$credentials = API_USERNAME . ':' . API_PASSWORD;

			$resource = API_ENDPOINT . $action;

			// create a new cURL resource
			$curl = curl_init();

			// set appropriate options
			$options = array(
				CURLOPT_URL => $resource,
				CURLOPT_USERPWD => $credentials,
				CURLOPT_HTTPHEADER => array(
					'Accept: application/json'
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FAILONERROR => false,
				CURLOPT_HEADER => false,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $data,
				// CURLOPT_POSTFIELDS => http_build_query($data),
				// CURLOPT_POSTFIELDS => Tools::jsonEncode($data),
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false
			);

			foreach ($options as $option => $value) {
				curl_setopt($curl, $option, $value);
			}

			$result = curl_exec($curl);

			$status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$response = Tools::jsonDecode($result);

			// execute the given cURL session
			if (false === ($result)) {
				throw new Exception(curl_error($curl));
			}

			if (floor($status / 100) != 2) {
				throw new Exception('Err Msg : ' . $response->message . ', Err Desc : ' . $response->description . ', Err Code : ' . $response->code);
			}
			curl_close($curl);

			HipayLogger::addLog($hipay->l('API call success', 'hipay'), HipayLogger::APICALL, 'Appel vers API avec success');
			return $response;
		} catch (Exception $e) {
			HipayLogger::addLog($hipay->l('API call error', 'hipay'), HipayLogger::ERROR, Db::getInstance()->escape($e->getMessage()));

			return false;
		}
	}

	public function getAPIURL() {
		// Production = https://secure-gateway.hipay-tpp.com/rest/v1/
		// Stage/testing = https://stage-secure-gateway.hipay-tpp.com/rest/v1/
		return 'https://' . (Configuration::get('HIPAY_TEST_MODE') ? 'stage-' : '') . 'secure-gateway.hipay-tpp.com/rest/v1/';
	}

	public function getAPIUsername() {
		return (Configuration::get('HIPAY_TEST_MODE') ? Configuration::get('HIPAY_TEST_API_USERNAME') : Configuration::get('HIPAY_API_USERNAME'));
	}

	public function getAPIPassword() {
		return (Configuration::get('HIPAY_TEST_MODE') ? Configuration::get('HIPAY_TEST_API_PASSWORD') : Configuration::get('HIPAY_API_PASSWORD'));
	}

	public function getAPIGender($id_gender = NULL) {
		// Gender of the customer (M=male, F=female, U=unknown).
		if ($id_gender == NULL)
			return 'U';

		switch ($id_gender) {
			case '1' :
				return 'M';
				break;
			case '2' :
				return 'F';
				break;
			default :
				return 'U';
				break;
		}
	}

	public function getCountryCode($country_name = null) {
		if ($country_name == null)
			Tools::redirect('index.php?controller=order&xer=8');

		return Db::getInstance()->getValue("
                SELECT c.iso_code
					FROM `" . _DB_PREFIX_ . "country` AS c
					LEFT JOIN `" . _DB_PREFIX_ . "country_lang` AS cl ON cl.id_country=c.id_country
					WHERE cl.name='" . $country_name . "'");
	}

}
