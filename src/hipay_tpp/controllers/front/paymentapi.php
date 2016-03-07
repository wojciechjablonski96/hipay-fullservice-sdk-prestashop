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
class HiPay_TppPaymentApiModuleFrontController extends ModuleFrontController {

	public $ssl = true;

	/**
	 *
	 * @see FrontController::initContent()
	 */
	public function initContent() {
		$this->display_column_left = false;
		$this->display_column_right = false;
		parent::initContent();
	}

	/**
	 *
	 * @see FrontController::postProcess()
	 */
	public function postProcess() {
		$hipay = new HiPay_Tpp ();

		//$cart = $this->context->cart;
		$context = Context::getContext();
		$cart = $context->cart;
		
		if (!$this->module->checkCurrency($cart))
			Tools::redirect('index.php?controller=order&xer=1');

		$context->smarty->assign(array(
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->module->getCurrency((int) $cart->id_currency),
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->module->getPathUri(),
			'this_path_bw' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/'
		));

		// Token is called when the user ENTERS the card details.
		$paymentproductswitcher = Tools::getValue('paymentproductswitcher');
		if ($paymentproductswitcher == 'american-express') {
			// American Express
			// No cardHolder, but firstname and lastname
			$cardNumber = Tools::getValue('cardNumber');
			$cardHolder = null;
			$cardFirstName = Tools::getValue('cardFirstName');
			$cardLastName = Tools::getValue('cardLastName');
			$cardExpiryMonth = Tools::getValue('cardExpiryMonth');
			$cardExpiryYear = Tools::getValue('cardExpiryYear');
			$cardSecurityCode = Tools::getValue('cardSecurityCode');
			$cardMemorizeCode = Tools::getValue('cardMemorizeCode');
			$cartUseExistingToken = Tools::getValue('cartUseExistingToken');
			$cardToken = Tools::getValue('cardToken');
		} else if ($paymentproductswitcher == 'bcmc') {
			// BanckContact/MisterCash
			// No CRC check
			$cardNumber = Tools::getValue('cardNumber');
			$cardHolder = Tools::getValue('cardHolder');
			$cardFirstName = null;
			$cardLastName = null;
			$cardExpiryMonth = Tools::getValue('cardExpiryMonth');
			$cardExpiryYear = Tools::getValue('cardExpiryYear');
			$cardSecurityCode = null;
			$cardMemorizeCode = Tools::getValue('cardMemorizeCode');
			$cartUseExistingToken = Tools::getValue('cartUseExistingToken');
			$cardToken = Tools::getValue('cardToken');
		} else {
			$cardNumber = Tools::getValue('cardNumber');
			$cardHolder = Tools::getValue('cardHolder');
			$cardFirstName = null;
			$cardLastName = null;
			$cardExpiryMonth = Tools::getValue('cardExpiryMonth');
			$cardExpiryYear = Tools::getValue('cardExpiryYear');
			$cardSecurityCode = Tools::getValue('cardSecurityCode');
			$cardMemorizeCode = Tools::getValue('cardMemorizeCode');
			$cartUseExistingToken = Tools::getValue('cartUseExistingToken');
			$cardToken = Tools::getValue('cardToken');
		}

		if ($cartUseExistingToken) { // $cartUseExistingToken = 1 -> Use memorized card token.
			// Pre-check
			$errors = true; // Initialize to true
			if ($cardToken != '' || $cardToken != null) {
				if ($cardToken) {
					$token_to_use = $cardToken; // This variable will be used to make the payment. Assign only when token is present.
					$errors = false; // proceed with the submit
				}
			}
			// If $cardToken is null or empty or false
			// Send error 999 to indicate that user should select the card
			if ($errors)
				$cardtoken = '999';
		} else { // $cartUseExistingToken = 0 -> Default processing of fetching card token.
			$cardtoken = HipayToken::createToken($cardNumber, $cardHolder, $cardExpiryMonth, $cardExpiryYear, $cardSecurityCode, $cardFirstName, $cardLastName, $paymentproductswitcher);

			// Pre-check
			$errors = true; // Initialize to true
			if (is_object($cardtoken)) {
				// Verify if token is not 0 or false
				if ($cardtoken->token) {
					if ($cardMemorizeCode == 'memorize') {
						HipayToken::saveToken($cardtoken, $cart);
					}
					$token_to_use = $cardtoken->token; // This variable will be used to make the payment. Assign only when token is present.
					$errors = false; // proceed with the submit
				}
			}
		}

		if ($errors) {
			$cart = $context->cart;
			$context->smarty->assign(array(
				'nbProducts' => $cart->nbProducts(),
				'cust_currency' => $cart->id_currency,
				'currencies' => $this->module->getCurrency((int) $cart->id_currency),
				'total' => $cart->getOrderTotal(true, Cart::BOTH),
				'this_path' => $this->module->getPathUri(),
				'this_path_bw' => $this->module->getPathUri(),
				'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/'
			));
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
				'status_error' => (int) $cardtoken, // status error
				'cart_id' => $cart->id,
				'currency' => $currency,
				'amount' => $cart->getOrderTotal(true, Cart::BOTH)
			));
			// Tpl will load a form that will store those infomations.

			$context->controller->addCSS(_MODULE_DIR_ . $this->module->name . '/css/hipay.css');
			$context->controller->addJs(_MODULE_DIR_ . $this->module->name . '/js/15hipay.js');

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
			if ($tokens ['0']) {
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
			return $this->setTemplate($payment_tpl);
			die();
		} else {
			// Mode API
			// Constructs data array and sends it as a parameter to the tpl
			$data = HipayToken::getApiData($cart, $token_to_use, null, $cartUseExistingToken);
			$response = HipayApi::restApi('order', $data);

			// Check if 3D secure is activated
			//if((int)$data['authentication_indicator'])
			//{
			// Check if forwardURL is true
			if ($response->forwardUrl) {
				// Redirect user
				Tools::redirect($response->forwardUrl);
			}
			//}

			if (get_class($response) != 'Exception') {
				switch ($response->state) {
					case 'completed' :
						$response_state = 'completed';
						break;
					case 'forwarding' :
						$response_state = 'forwarding';
						break;
					case 'pending' :
						$response_state = 'pending';
						break;
					case 'declined' :
						$response_state = 'declined';
						break;
					case 'error' :
					default :
						$response_state = 'error';
						break;
				}
				$context->smarty->assign(array(
					'error_code' => '',
					'error_message' => '',
					'error_response' => '',
					'response_state' => $response_state
				));
			} else {

				$response_code = $response->getCode();
				$response_message = $response->getMessage();

				$context->smarty->assign(array(
					'error_code' => $response_code,
					'error_message' => $response_message,
					'error_response' => 'exception_error',
					'response_state' => 'error'
						)
				);
			}

			switch ($response_state) {
				case 'completed' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=accept');
					break;
				case 'declined' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=decline');
					break;
				case 'cancel' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=cancel');
					break;
				case 'pending' :
				case 'forwarding' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=pending');
					// Implementing challenge url
					// Redirecting to challenge url if url present
					if (Configuration::get('HIPAY_CHALLENGE_URL')) {
						$redirect_url = Configuration::get('HIPAY_CHALLENGE_URL');
					}
					break;
				case 'exception' :
				default :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=exception');
					break;
			}

			// Disconnect User from cart
			HipayClass::unsetCart();

			die('
                <script type="text/javascript">
                    try{
                        parent.window.location.replace("' . $redirect_url . '");
                    }catch(e){
                        alert(e);
                    }
                </script>
                <h1>' . Tools::displayError('Now loading..') . '</h1>
            ');


			$this->setTemplate('payment_api_response.tpl');
		}
	}

}
