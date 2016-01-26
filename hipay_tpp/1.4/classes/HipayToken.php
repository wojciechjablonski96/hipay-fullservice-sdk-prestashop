<?php

/**
 * 2007-2013 Profileo NOTICE OF LICENSE This source file is subject to the Academic Free License (AFL 3.0) that is bundled with this package in the file LICENSE.txt. It is also available through the world-wide-web at this URL: http://opensource.org/licenses/afl-3.0.php If you did not receive a copy of the license and are unable to obtain it through the world-wide-web, please send an email to contact@profileo.com so we can send you a copy immediately. DISCLAIMER Do not edit or add to this file if you wish to upgrade Profileo to newer versions in the future. If you wish to customize Profileo for your needs please refer to http://www.profileo.com for more information. @author Profileo <contact@profileo.com> @copyright 2007-2013 Profileo International Registered Trademark & Property of Profileo
 */

class HipayToken extends ObjectModel {

	public static function getApiData($cart = null, $cardtoken = null, $context = null, $cartUseExistingToken = 0) {
		$hipay = new HiPay_Tpp ();
		if (!$context)
			$context = Context::getContext();

		// Basic check for security
		// If no currency for the cart, redirect to first order step
		if (!$hipay->checkCurrency($cart))
			Tools::redirect('index.php?controller=order&xer=3');


		$language = HipayClass::getLanguageCode($context->language->iso_code);

		// Retrieving Currency
		$currency_array = $hipay->getCurrency((int) $cart->id_currency);
		$currency = $currency_array [0] ['iso_code'];
		foreach ($currency_array as $key => $value) {
			if ($value ['id_currency'] == $cart->id_currency) {
				$actual_currency = $value ['iso_code'];
			}
		}
		if ($currency != $actual_currency)
			$currency = $actual_currency;

		// Retrieve Total
		$amount = $cart->getOrderTotal(true, Cart::BOTH);

		// Cart other details
		$cart_summary = $cart->getSummaryDetails(null, true);
		$shipping = $cart_summary ['total_shipping'];
		$tax = $cart_summary ['total_tax'];

		$description = ''; // Initialize to blank
		foreach ($cart_summary ['products'] as $key => $value) {
			if ($value ['reference']) {
				// Add reference of each product
				$description .= 'ref_' . $value ['reference'] . ', ';
			}
		}

		// Order ID
		$orderid = $cart->id . "(" . time() . ")";

		// Trim trailing seperator
		$description = Tools::substr($description, 0, - 2);
		if (Tools::strlen($description) == 0) {
			$description = 'cart_id_' . $orderid;
		}
		// If description exceeds 255 char, trim back to 255
		$max_length = 255;
		if (Tools::strlen($description) > $max_length) {
			$offset = ($max_length - 3) - Tools::strlen($description);
			$description = Tools::substr($description, 0, strrpos($description, ' ', $offset)) . '...';
		}

		// Load customer and populate data array
		$customer = new Customer((int) $cart->id_customer);
		// Verify if customer is indeed a customer object
		if (!Validate::isLoadedObject($customer))
			Tools::redirect('index.php?controller=order&xer=5');

		// Retrive Customer ID
		$cid = (int) $customer->id;
		// Retrieve first name and last name
		$firstname = $customer->firstname;
		$lastname = $customer->lastname;
		// Retrieve Gender

		$gender = HipayClass::getAPIGender($customer->id_gender);
		// Retrieve Email
		$email = $customer->email;
		// Retrieve Birthdate
		$birthdate = $customer->birthday;
		$birthdate = str_replace('-', '', $birthdate);

		// Load Addresses - Invoice addr and Delivery addr
		$invoice = new Address((int) $cart->id_address_invoice);
		$delivery = new Address((int) $cart->id_address_delivery);

		if (isset($invoice->phone) && $invoice->phone != '')
			$phone = $invoice->phone;
		elseif (isset($invoice->phone_mobile) && $invoice->phone_mobile != '')
			$phone = $invoice->phone_mobile;
		else
			$phone = '';

		$streetaddress = $invoice->address1;
		$streetaddress2 = $invoice->address2;
		$city = $invoice->city;
		$zipcode = $invoice->postcode;
		// Data 'state' = The USA state or the Canada state of the
		// customer making the purchase. Send this
		// information only if the address country of the
		// customer is US (USA) or CA (Canada
		$state = '';

		// Data 'country' = The country code of the customer.
		// This two-letter country code complies with ISO
		// 3166-1 (alpha 2).
		$country = HipayClass::getCountryCode($invoice->country);

		// Delivery info
		$shipto_firstname = $delivery->firstname;
		$shipto_lastname = $delivery->lastname;
		$shipto_streetaddress = $delivery->address1;
		$shipto_streetaddress2 = $delivery->address2;
		$shipto_city = $delivery->city;
		$shipto_zipcode = $delivery->postcode;

		// Data 'shipto_state' = The USA state or the Canada state of the
		// customer making the purchase. Send this
		// information only if the address country of the
		// customer is US (USA) or CA (Canada
		$shipto_state = '';

		// Data 'shipto_country' = The country code of the customer.
		// This two-letter country code complies with ISO
		// 3166-1 (alpha 2).
		$shipto_country = HipayClass::getCountryCode($delivery->country);

		// Data set => cdata1, cdata2, cdata3, cdata4
		// Custom data. You may use these parameters
		// to submit values you wish to receive back in
		// the API response messages or in the
		// notifications, e.g. you can use these
		// parameters to get back session data, order
		// content or user info.
		$cdata1 = 'c' . $orderid; // Cart ID
		$cdata2 = 'u' . $cid; // User ID
		$cdata3 = 'nnone';
		$cdata4 = 'cdata4';

		$accept_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14accept.php');
		$decline_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14decline.php');
		$cancel_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14cancel.php');
		$exception_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14exception.php');
		$pending_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14pending.php');

		// Implementing challenge url
		// Redirecting to challenge url if url present
		if (Configuration::get('HIPAY_CHALLENGE_URL')) {
			$pending_url = Configuration::get('HIPAY_CHALLENGE_URL');
		}

		// Data 'eci'
		// Electronic Commerce Indicator (ECI).
		// The ECI indicates the security level at
		// which the payment information is
		// processed between the cardholder and
		// merchant.
		// Possible values:
		// 1 = MO/TO (Card Not Present)
		// 2 = MO/TO - Recurring
		// 3 = Installment Payment
		// 4 = Manually Keyed (Card Present)
		// 7 = Secure E-commerce with SSL/TLS
		// Encryption
		// 9 = Recurring E-commerce
		$eci = '7';

		// 3D Secure authentication
		// Data authentication_indicator
		// Indicates if the authentication should be
		// performed. Can be used to overrule the
		// merchant level configuration.
		// 0 = Bypass authentication
		// 1 = Continue if possible (Default)
		$authentication_indicator = '0'; // Instantiate to default zero
		if ((int) Configuration::get('HIPAY_THREEDSECURE')) {
			if ($amount >= (int) Configuration::get('HIPAY_THREEDSECURE_AMOUNT')) {
				$authentication_indicator = Configuration::get('HIPAY_THREEDSECURE');
			} else {
				$authentication_indicator = '0';
			}
		}

		// If customer is using a memorized card, force the following params
		if ($cartUseExistingToken) {
			$authentication_indicator = '0'; // Override ThreeDSecure
			$eci = '9'; // 9 = Recurring E-commerce
		}

		$payment_product = Tools::getValue('paymentproductswitcher');

		if ($payment_product == '') {
			$payment_product = 'visa';
		}

		if (Configuration::get('HIPAY_MANUALCAPTURE')) {
			$operation = 'Authorization';
		} else {
			$operation = 'Sale';
		}

		if ($cardtoken) {
			// Important : Proceed only if cardtoken is not false!
			$data = array(
				'operation' => $operation,
				'payment_product' => $payment_product,
				'description' => $description,
				'long_description' => '',
				'currency' => $currency,
				'orderid' => $orderid,
				'amount' => $amount,
				'shipping' => $shipping,
				'tax' => $tax,
				'accept_url' => $accept_url,
				'decline_url' => $decline_url,
				'pending_url' => $pending_url,
				'cancel_url' => $cancel_url,
				'exception_url' => $exception_url,
				'language' => $language,
				'cdata1' => $cdata1,
				'cdata2' => $cdata2,
				'cdata3' => $cdata3,
				'cdata4' => $cdata4,
				'cid' => $cid,
				'phone' => $phone,
				'birthdate' => $birthdate,
				'gender' => $gender,
				'firstname' => $firstname,
				'lastname' => $lastname,
				'recipientinfo' => 'Client',
				'streetaddress' => $streetaddress,
				'streetaddress2' => $streetaddress2,
				'city' => $city,
				'state' => $state,
				'zipcode' => $zipcode,
				'country' => $country,
				'shipto_firstname' => $shipto_firstname,
				'shipto_lastname' => $shipto_lastname,
				'shipto_recipientinfo' => 'Client',
				'shipto_streetaddress' => $shipto_streetaddress,
				'shipto_streetaddress2' => $shipto_streetaddress2,
				'shipto_city' => $shipto_city,
				'shipto_state' => $shipto_state,
				'shipto_zipcode' => $shipto_zipcode,
				'shipto_country' => $shipto_country,
				'ipaddr' => $_SERVER ['REMOTE_ADDR'],
				'email' => $email,
				'cardtoken' => $cardtoken,
				'authentication_indicator' => strval($authentication_indicator),
				'eci' => $eci,
			);
			// TPPPRS-21
			if($birthdate == 0){
				unset($data['birthdate']);
			}
			return $data;
		}
		return false;
	}

	public static function createToken($cardNumber = null, $cardHolder = null, $cardExpiryMonth = null, $cardExpiryYear = null, $cardSecurityCode = null, $firstname = null, $lastname = null, $paymentproductswitcher = null) {
		try {
			$hipay = new HiPay_Tpp ();
			HipayLogger::addLog($hipay->l('Token Create call initiated', 'hipay'), HipayLogger::APICALL, 'Action : Create Token');

			define('API_ENDPOINT_TOKEN', HipayClass::getAPITokenURL());
			define('API_USERNAME_TOKEN', HipayClass::getAPIUsername());
			define('API_PASSWORD_TOKEN', HipayClass::getAPIPassword());

			$credentials_token = API_USERNAME_TOKEN . ':' . API_PASSWORD_TOKEN;

			$resource_token = API_ENDPOINT_TOKEN . 'create';

			// Multi_use : only boolean
			// 0 = Generate a single-use token
			// 1 = Generate a multi-use token (default)
			$multi_use = 1;

			if ($paymentproductswitcher == 'american-express') {
				$data_token = array(
					'card_number' => $cardNumber,
					'card_expiry_month' => $cardExpiryMonth,
					'card_expiry_year' => $cardExpiryYear,
					'firstname' => $firstname,
					'lastname' => $lastname,
					'cvc' => $cardSecurityCode,
					'multi_use' => $multi_use
				);
			} elseif ($paymentproductswitcher == 'bcmc') {
				$data_token = array(
					'card_number' => $cardNumber,
					'card_expiry_month' => $cardExpiryMonth,
					'card_expiry_year' => $cardExpiryYear,
					'card_holder' => $cardHolder,
					'multi_use' => $multi_use
				);
			} else {
				$data_token = array(
					'card_number' => $cardNumber,
					'card_expiry_month' => $cardExpiryMonth,
					'card_expiry_year' => $cardExpiryYear,
					'card_holder' => $cardHolder,
					'cvc' => $cardSecurityCode,
					'multi_use' => $multi_use
				);
			}

			// create a new cURL resource
			$curl_token = curl_init();

			// set appropriate options
			$options_token = array(
				CURLOPT_URL => $resource_token,
				CURLOPT_USERPWD => $credentials_token,
				CURLOPT_HTTPHEADER => array(
					'Accept: application/json'
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FAILONERROR => false,
				CURLOPT_HEADER => false,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($data_token),
				// CURLOPT_POSTFIELDS => http_build_query($data_token),
				// CURLOPT_POSTFIELDS => Tools::jsonEncode($data_token),
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false
			);

			foreach ($options_token as $option => $value) {
				curl_setopt($curl_token, $option, $value);
			}

			$result_token = curl_exec($curl_token);

			$status_token = (int) curl_getinfo($curl_token, CURLINFO_HTTP_CODE);
			$response_token = Tools::jsonDecode($result_token);
			// p($credentials_token);
			// p($resource_token);
			// p($data_token);
			// p($status_token);
			// p($response_token);
			// execute the given cURL session
			if (false === ($result_token)) {
				throw new Exception(curl_error($curl_token));
			}

			if (floor($status_token / 100) != 2) {
				throw new Exception($status_token);
			}
			curl_close($curl_token);

			HipayLogger::addLog($hipay->l('Token Create call success', 'hipay'), HipayLogger::APICALL, 'Creation token avec success');

			return $response_token;
		} catch (Exception $e) {
			HipayLogger::addLog($hipay->l('Token Create call status error', 'hipay'), HipayLogger::ERROR, mysql_real_escape_string($e->getMessage()));

			return $e->getMessage();
		}
	}

	// Object cardtoken to be saved
	public static function saveToken($cardtoken = null, $cart = null) {
		$customer_id = $cart->id_customer;
		$token = $cardtoken->token;
		$brand = $cardtoken->brand;
		$pan = $cardtoken->pan;
		$card_holder = $cardtoken->card_holder;
		$card_expiry_month = $cardtoken->card_expiry_month;
		$card_expiry_year = $cardtoken->card_expiry_year;
		$issuer = $cardtoken->issuer;
		$country = $cardtoken->country;

		$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_tokens`
                        WHERE `customer_id`='" . $customer_id . "'
                        AND `token`='" . $token . "'";
		$result = Db::getInstance()->getRow($sql);

		if ($result ['id']) {
			return true;
			// 'Already exists record for order_id';
		} else {
			// 'insert in DB';
			$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "hipay_tokens` (`customer_id`, `token`, `brand`, `pan`, `card_holder`, `card_expiry_month`, `card_expiry_year`, `issuer`, `country`)
                VALUES('" . $customer_id . "', '" . $token . "', '" . $brand . "', '" . $pan . "', '" . $card_holder . "', '" . $card_expiry_month . "', '" . $card_expiry_year . "', '" . $issuer . "', '" . $country . "')";
			return Db::getInstance()->execute($sql_insert);
		}
	}

	// Object cardtoken to be saved
	public static function getTokens($id_customer = null) {
		// List of allowed cards
		$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');
		$cart_arr = explode(',', $card_str);
		$allow_memorize = array();
		foreach ($cart_arr as $key => $value) {
			if ($value == 'american-express') {
				$allow_memorize [] = "'american-express'";
				$allow_memorize [] = "'americanExpress'";
				$allow_memorize [] = "'AMERICANEXPRESS'";
				$allow_memorize [] = "'AMERICAN EXPRESS'";
				$allow_memorize [] = "'american express'";
				$allow_memorize [] = "'american Express'";
			}
			if ($value == 'cb') {
				$allow_memorize [] = "'cb'";
			}
			if ($value == 'visa') {
				$allow_memorize [] = "'visa'";
				$allow_memorize [] = "'VISA'";
			}
			if ($value == 'mastercard') {
				$allow_memorize [] = "'mastercard'";
			}
		}

		$sql = 'false';
		if ((count($allow_memorize))) {

			$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_tokens`
                         WHERE `customer_id`='" . (int) $id_customer . "'";
			$allow_memorize_str = implode(', ', $allow_memorize);
			$sql .= "AND `brand` IN (" . $allow_memorize_str . ")";
			return Db::getInstance()->executeS($sql);
		}
		return $sql;
	}

}

