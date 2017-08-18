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

class HipayApi extends ObjectModel {

	/**
	 * returns API response array()
	 */
	public static function restApi($action = null, $data = null) {
		try {
			$hipay = new HiPay_Tpp ();
			HipayLogger::addLog($hipay->l('API call initiated', 'hipay'), HipayLogger::APICALL, 'Action : ' . $action . ' - Data : ' . Tools::jsonEncode($data));

			if ($action == null)
				Tools::redirect('index.php?controller=order&xer=6');
			if ($data == null)
				Tools::redirect('index.php?controller=order&xer=7');

			define('API_ENDPOINT', HipayClass::getAPIURL());
			define('API_USERNAME', HipayClass::getAPIUsername());
			define('API_PASSWORD', HipayClass::getAPIPassword());

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
				// CURLOPT_POSTFIELDS => $data,
				// CURLOPT_POSTFIELDS => Tools::jsonEncode($data),
				CURLOPT_POSTFIELDS => http_build_query($data),
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
				$errorCurl = curl_error($curl);
				throw new Exception('Curl error: ' . $errorCurl);
			}

			if (floor($status / 100) != 2) {
				throw new Exception('Hipay message: ' . $response->message, $response->code);
			}
			curl_close($curl);

			HipayLogger::addLog($hipay->l('API call success', 'hipay'), HipayLogger::APICALL, 'Appel vers API avec success : ' . Tools::jsonEncode($response));
			return $response;
		} catch (Exception $e) {
			HipayLogger::addLog($hipay->l('API call error', 'hipay'), HipayLogger::ERROR, $e->getMessage());

			return $e;
		}
	}

	/**
	 * Generates API data Note : This data structure is different from HipayToken::getApiData.
	 *
	 * @param $cart :
	 *        	Contains cart information @param $data_type : Can be either 'null' or 'iframe'. 'null' = default dedicated page behaviour 'iframe' = Updates some values to match iframe behaviour @param $context : Optional parameter through which current context is passed. If not present, the context will get instantiated none the less. returns API response array()
	 */
	public static function getApiData($cart = null, $data_type = null, $context = null, $local_card = null) {
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
		foreach ($currency_array as $value) {
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
		foreach ($cart_summary ['products'] as $value) {
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
		$cdata3 = 'My+data+3';
		$cdata4 = 'My+data+4';

		$token  = HipayClass::getHipayToken($orderid, 'accept.php');

		// Set of return URLs
		if ($data_type == 'iframe') {
			$accept_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=iframe&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid).'&return_status=accept&content_only=1');
			$decline_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=iframe&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid).'&return_status=decline&content_only=1');
			$cancel_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=iframe&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid).'&return_status=cancel&content_only=1');
			$pending_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=iframe&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid).'&return_status=pending&content_only=1');
			$exception_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=iframe&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid).'&return_status=exception&content_only=1');
			// Template = iframe
			$template = 'iframe';
			if (Configuration::get('HIPAY_TEMPLATE_MODE') == 'basic-js')
				$template .= '-js';
		} else {
			$accept_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=accept&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid));
			$decline_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=decline&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid));
			$cancel_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=cancel&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid));
			$exception_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=exception&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid));
			$pending_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=pending&token='.$token.'&id_order='.(int) Order::getOrderByCartId($orderid));
			// Template = basic
			$template = Configuration::get('HIPAY_TEMPLATE_MODE');
		}

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
		$authentication_indicator = (int) '0';
		if ((int) Configuration::get('HIPAY_THREEDSECURE')) {
			if ($amount >= (int) Configuration::get('HIPAY_THREEDSECURE_AMOUNT')) {
				$authentication_indicator = (int) Configuration::get('HIPAY_THREEDSECURE');
			} else {
				$authentication_indicator = (int) '0';
			}
		}

		// Get last payment methods list
		$payment_product_list_upd = Tools::getValue('payment_product_list_upd');

		if (Configuration::get('HIPAY_MANUALCAPTURE')) {
			$operation = 'Authorization';
		} else {
			$operation = 'Sale';
		}

		// Intergrating Local cards logic into the data construction
		if ($local_card != null) {
			// Override payment_product_list with local card
			$payment_product_list_upd = $local_card;
			$operation = 'Sale'; // Default value
			// Override operation - Force sale, not manual capture.
            if (file_exists(_PS_THEME_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml')) {
                $local_cards = simplexml_load_file(_PS_THEME_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml');
            } else if (file_exists(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml')) {
                $local_cards = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml');
            }

            if (!isset($local_cards) && count($local_cards)) {
                foreach ($local_cards as $value) {
                    if ($local_card == (string) $value->code) {
                        if ((string) $value->manualcapture == '1') {
                            $operation = 'Authorization';
                        } else {
                            $operation = 'Sale';
                        }
                    }
                }
            }
		}

		// On module administration we change the values of display selector to get always by default the selector showed
		if (Configuration::get('HIPAY_SELECTOR_MODE') == '1')
			$display_selector = 0;
		else
			$display_selector = 1;

		$data = array(
			'operation' => $operation,
			'payment_product_list' => $payment_product_list_upd,
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
			'authentication_indicator' => (string)$authentication_indicator,
			'eci' => $eci,
			'template' => $template,
			'css' => Configuration::get('HIPAY_CSS_URL'),
			'display_selector' => $display_selector
		);
		// TPPPRS-21
		if($birthdate == 0){
			unset($data['birthdate']);
		}

		// Merchant display name limited to 32 characters only
		if ($data_type == 'iframe') {
			// No merchant_display_name for mode iframe
		} else {
			$merchant_display_name = Tools::substr(Configuration::get('PS_SHOP_NAME'), 0, 32);
			$data ['merchant_display_name'] = $merchant_display_name;
		}

		return $data;
	}

}

