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
 * Initialisation API prestashop
 */
require_once(dirname(__FILE__) . '/../../../config/config.inc.php');
$str_ps_version = (int) str_replace('.', '', _PS_VERSION_);
if ($str_ps_version < 1600) {
	// version 1.5 or 1.4
//	include_once (dirname ( __FILE__ ) . '/../../../config/config.inc.php');
	require_once(dirname(__FILE__) . '/../../../init.php');
} else {
	// Version 1.6 or above
//	include_once (dirname ( __FILE__ ) . '/../../../config/config.inc.php');
	require_once(dirname(__FILE__) . '/../../../init.php');
}
require_once(dirname(__FILE__) . '/hipay_tpp.php');
// Global variable for new ORDER ID
$GLOBALS['_HIPAY_CALLBACK_ORDER_ID_'] = 0;

$hipay = new HiPay_Tpp();

$data = json_encode($_GET);
$arr = (array) json_decode($data);
foreach ($arr as $key => $value)
	$_POST [$key] = $value;

// Series of security checks:
$proceed = true;

if (!isset($_POST ['state']) && !isset($_POST ['status'])) {
	HipayLogger::addLog($hipay->l('Bad Callback initiated', 'hipay'), HipayLogger::ERROR, 'Bad Callback initiated, but not processed further');
	die();
}

$log_state = ($_POST ['state']) ? $_POST ['state'] : 'error'; // Sets to error if nothing is found
$log_status = ($_POST ['status']) ? $_POST ['status'] : 'error'; // Sets to error if nothing is found
// Check if cart_id has been posted
if (!isset($_POST['order']->id)) {
	HipayLogger::addLog($hipay->l('Bad Callback initiated', 'hipay'), HipayLogger::ERROR, 'Bad Callback initiated, no order ID found - data : ' . mysql_real_escape_string($data));
	die('No order found'); // No need to proceed further, if no order_id is present
}

$cart = new Cart((int) $_POST ['order']->id);
if (!Validate::isLoadedObject($cart)) {
	HipayLogger::addLog($hipay->l('Bad Callback initiated', 'hipay'), HipayLogger::ERROR, 'Bad Callback initiated, cart could not be initiated - data : ' . mysql_real_escape_string($data));
	die('Order empty'); // No need to proceed further if the order_id do not match any cart
}

HipayLogger::addLog($hipay->l('Callback initiated', 'hipay'), HipayLogger::NOTICE, 'Callback initiated - cid : ' . (int) $_POST ['order']->id . ' - state : ' . $log_state . ' - status : ' . $log_status);


// Memorize Token
// Find is cart_id has been marked to be memorized
$sql = 'SELECT count(cart_id) FROM `' . _DB_PREFIX_ . 'hipay_tokens_tmp` WHERE `cart_id` = ' . (int) $cart->id;
$result = Db::getInstance()->getValue($sql);
if ($result) {
	// Retrieve card token
	$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_tokens`
                        WHERE `customer_id`='" . (int)$cart->id_customer . "'
                        AND `token`='" . pSQL($_POST ['payment_method']->token) . "'";
	$result = Db::getInstance()->getRow($sql);
	// If no results found
	if (!$result ['id']) {
		// Check if card is either an Americain-express, CB, Mastercard et Visa card.
		if ($_POST ['payment_product'] == 'american-express' || $_POST ['payment_product'] == 'cb' || $_POST ['payment_product'] == 'visa' || $_POST ['payment_product'] == 'mastercard') {
			// Memorize new card only if card used can be "recurring"
			$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "hipay_tokens` (`customer_id`, `token`, `brand`, `pan`, `card_holder`, `card_expiry_month`, `card_expiry_year`, `issuer`, `country`)
                VALUES('" . (int)$cart->id_customer . "', '" . pSQL($_POST ['payment_method']->token) . "', '" . pSQL($_POST ['payment_method']->brand) . "', '" . pSQL($_POST ['payment_method']->pan) . "', '" . pSQL($_POST ['payment_method']->card_holder) . "', '" . pSQL($_POST ['payment_method']->card_expiry_month) . "', '" . pSQL($_POST ['payment_method']->card_expiry_year) . "', '" . pSQL($_POST ['payment_method']->issuer) . "', '" . pSQL($_POST ['payment_method']->country) . "')";
			Db::getInstance()->execute($sql_insert);
		}
	}
}


// Sometimes the captured callback arrives BEFORE the authorized callback
// If status = 116, but order already created and in status capture_partielle or paiement
// Then skip the callback
if ($log_status == '116') {
	// If order exists for cart
	if ($cart->orderExists()) {
		$context = Context::getContext();
		// Retrieve Order ID
		$order_id = retrieveOrderId($cart->id);
		$order = new Order((int) $order_id);
		// If current state is paiement accepted or capture partielle
		// then skip the process
		$partially_captured = (Configuration::get('HIPAY_PARTIALLY_CAPTURED')) ? Configuration::get('HIPAY_PARTIALLY_CAPTURED') : '0';
		if ($order->current_state == _PS_OS_PAYMENT_ || $order->current_state == $partially_captured) {
			// Paiement has already been done at least once, stop the process
			$msg = new Message ();
			$message = $hipay->l('HiPay - Callback initiated') . "<br>";
			$message .= ' - ' . $hipay->l('Transaction_reference : ') . $_POST['transaction_reference'] . "<br>";
			$message .= ' - ' . $hipay->l('State : ') . $_POST['state'] . "<br>";
			$message .= ' - ' . $hipay->l('Status : ') . $_POST['status'] . "<br>";
			$message .= ' - ' . $hipay->l('Message : ') . $_POST['message'] . "<br>";
			$message .= ' - ' . $hipay->l('Amount : ') . $_POST['authorized_amount'] . "<br>";
			$message .= ' - ' . $hipay->l('NO ACTION TAKEN, CART HAS ALREADY BEEN CAPTURED') . "<br>";
			$message = strip_tags($message, '<br>');
			if (Validate::isCleanHtml($message)) {
				$msg->message = $message;
				$msg->id_order = intval($order->id);
				$msg->private = 1;
				$msg->add();
			}
			HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'NO ACTION TAKEN, CART HAS ALREADY BEEN CAPTURED - cid : ' . (int) $_POST ['order']->id);
			die();
		}
	}
}



// If status = 117 or 118, check if status 118 has not already been processed.
// If status 118 already processed, then die the process
if ($log_status == '118' ) {

	// Check if its a capture returning after a forced refund
	// status 117 > 124 > 118 > 125
	if ($cart->orderExists()) {
		$context = Context::getContext();
		// Retrieve Order ID
		$order_id = retrieveOrderId($callback_arr, $cart->id);
		$order = new Order((int) $order_id);
		// If current state is paiement accepted or capture partielle
		// then skip the process
		$refund_requested = (Configuration::get('HIPAY_REFUND_REQUESTED')) ? Configuration::get('HIPAY_REFUND_REQUESTED') : '0';
		if ($order->current_state == $refund_requested) {
			// Paiement has already been done at least once, stop the process
			$msg = new Message ();
			$message = $hipay->l('HiPay - Callback initiated') . "<br>";
			$message .= ' - ' . $hipay->l('Transaction_reference : ') . $callback_arr['transaction_reference'] . "<br>";
			$message .= ' - ' . $hipay->l('State : ') . $callback_arr['state'] . "<br>";
			$message .= ' - ' . $hipay->l('Status : ') . $callback_arr['status'] . "<br>";
			$message .= ' - ' . $hipay->l('Message : ') . $callback_arr['message'] . "<br>";
			$message .= ' - ' . $hipay->l('Amount : ') . $callback_arr['authorized_amount'] . "<br>";
			$message .= ' - '.$hipay->l ( 'NO ACTION TAKEN, CART HAS ALREADY BEEN INITIATED AS REFUND REQUESTED.') . "<br>";
			$message = strip_tags($message, '<br>');
			if (Validate::isCleanHtml($message)) {
				$msg->message = $message;
				$msg->id_order = (int)$order->id;
				$msg->private = 1;
				$msg->add();
			}
			HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'NO ACTION TAKEN, CART HAS ALREADY BEEN INITIATED AS REFUND REQUESTED - cid : ' . (int) $callback_arr['order']->id);
			die();
		}
	}

	// Check if its a partiel capture before zapping the process
	if ($_POST['authorized_amount'] == $_POST['captured_amount']) {
		// This meants its a full capture
		// Proceed to check if payment already exists
		if ($cart->orderExists()) { // If order exists for cart
			$context = Context::getContext();
			// Retrieve Order ID
			$order_id = retrieveOrderId($cart->id);
			$order = new Order((int) $order_id);

			// Check if partial payment has been made before
			// If partial payment has been done, then proceed to make a final capture
			$totalEncaissement = $hipay->getOrderTotalAmountCaptured($order->id);

			if ($totalEncaissement >= $_POST['captured_amount']) {
				// Total Encaissement greater/equalto captured amount, then stop and issue a warning msg
				if ((boolean) $order->getHistory($context->language->id, Configuration::get('PS_OS_PAYMENT'))) {
					// Paiement has already been done at least once, stop the process
					$msg = new Message ();
					$message = $hipay->l('HiPay - Callback initiated') . "<br>";
					$message .= ' - ' . $hipay->l('Transaction_reference : ') . $_POST['transaction_reference'] . "<br>";
					$message .= ' - ' . $hipay->l('State : ') . $_POST['state'] . "<br>";
					$message .= ' - ' . $hipay->l('Status : ') . $_POST['status'] . "<br>";
					$message .= ' - ' . $hipay->l('Message : ') . $_POST['message'] . "<br>";
					$message .= ' - ' . $hipay->l('Amount : ') . $_POST['captured_amount'] . "<br>";
					$message .= ' - ' . $hipay->l('data : ') . $_POST['cdata1'] . "<br>";
					$message .= ' - ' . $hipay->l('NO ACTION TAKEN, CART HAS ALREADY BEEN CAPTURED') . "<br>";
					$message = strip_tags($message, '<br>');
					if (Validate::isCleanHtml($message)) {
						$msg->message = $message;
						$msg->id_order = intval($order->id);
						$msg->private = 1;
						$msg->add();
					}
					HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'NO ACTION TAKEN, CART HAS ALREADY BEEN CAPTURED- cid : ' . (int) $_POST ['order']->id);
					die();
				}
			} else {
				// Proceed normally
			}
		}
	}
}


// Store transaction in DB
if (isset($_POST ['payment_method']->token)) {
	$cart_id = (int) $_POST ['order']->id;
	$order_id = '0';
	$customer_id = $cart->id_customer;
	$transaction_reference = $_POST ['transaction_reference'];
	$device_id = $_POST ['device_id'];
	$ip_address = $_POST ['ip_address'];
	$ip_country = $_POST ['ip_country'];
	$token = $_POST ['payment_method']->token;
	HipayLogger::addTransaction($cart_id, $order_id, $customer_id, $transaction_reference, $device_id, $ip_address, $ip_country, $token);
}

switch ($log_state) {
	case 'completed' :
		processStatusCompleted($cart);
		break;

	case 'pending' :
	case 'forwarding' :
		processStatusPending($cart);
		break;

	case 'declined' :
		processStatusDeclined($cart);
		break;

	case 'error' :
	default :
		processStatusError($cart);
		break;
}

function processStatusCompleted($cart = null) {
	echo '-fn1';
	$hipay = new HiPay_Tpp ();

	// Verify if the cart already exists
	// Create new cart

	$currency = new Currency($cart->id_currency);

	$orderState = retrieveCallbackOS();
	HipayLogger::addLog($hipay->l('Callback initiated', 'hipay'), HipayLogger::NOTICE, 'retrieveCallbackOS - cid : ' . (int) $_POST ['order']->id . ' / ' . (int) $cart->id . ' - status : ' . (int) $_POST ['status'] . ' / order_state : ' . $orderState);

	if ($cart->orderExists())
		hipayUpdateOrder($cart, $orderState);
	else
		hipayValidateOrder($cart, $orderState);

	hipayResetOrderStatus($cart);
	HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'processStatusCompleted ended - cid : ' . (int) $_POST ['order']->id);
	die();
}

function processStatusPending($cart = null) {
	echo '-fn2';
	$hipay = new HiPay_Tpp ();

	$currency = new Currency($cart->id_currency);

	$orderState = retrieveCallbackOS();
	HipayLogger::addLog($hipay->l('Callback initiated in status Pending', 'hipay'), HipayLogger::NOTICE, 'retrieveCallbackOS - cid : ' . (int) $_POST ['order']->id . ' - status : ' . (int) $_POST ['status'] . ' / callback state : ' . (int) $_POST ['state']);

	// Verify if the cart already exists
	if ($cart->orderExists())
		hipayUpdateOrder($cart, $orderState);
	else
		hipayValidateOrder($cart, $orderState);

	hipayResetOrderStatus($cart);
	HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'processStatusPending ended - cid : ' . (int) $_POST ['order']->id);
	die();
}

function processStatusDeclined($cart = null) {
	echo '-fn3';
	$hipay = new HiPay_Tpp ();

	$currency = new Currency($cart->id_currency);

	$orderState = retrieveCallbackOS();
	HipayLogger::addLog('Callback initiated in status Declined', HipayLogger::NOTICE, 'retrieveCallbackOS - cid : ' . (int) $_POST ['order']->id . ' - status : ' . (int) $_POST ['status'] . ' / callback state : ' . (int) $_POST ['state']);


	// Verify if the cart already exists
	if ($cart->orderExists())
		hipayUpdateOrder($cart, $orderState);
	else
		hipayValidateOrder($cart, $orderState);

	hipayResetOrderStatus($cart);
	HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'processStatusDeclined ended - cid : ' . (int) $_POST ['order']->id);
	die();
}

function processStatusError($cart = null) {
	echo '-fn4';
	$hipay = new HiPay_Tpp ();

	$currency = new Currency($cart->id_currency);

	$orderState = retrieveCallbackOS();
	HipayLogger::addLog($hipay->l('Callback initiated in status Error', 'hipay'), HipayLogger::NOTICE, 'retrieveCallbackOS - cid : ' . (int) $_POST ['order']->id . ' - status : ' . (int) $_POST ['status'] . ' / callback state : ' . (int) $_POST ['state']);

	// Verify if the cart already exists
	if ($cart->orderExists()) {
		// Cart already exists, just update cart
		hipayUpdateOrder($cart, $orderState);
	} else {
		// Create new cart
		hipayValidateOrder($cart, $orderState);
	}

	hipayResetOrderStatus($cart);
	HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'processStatusError ended - cid : ' . (int) $_POST ['order']->id);
	die();
}

function hipayValidateOrder($cart = null, $orderState = _PS_OS_ERROR_) {
	echo '-fnVO';
	$hipay = new HiPay_Tpp ();
	$customer = new Customer((int) $cart->id_customer);

	if ($orderState == 'skip') {
		// Simply log the callback
		$msg = new Message ();
		$message = $hipay->l('HiPay - Callback initiated');
		$message .= ' - ' . $hipay->l('Transaction_reference : ') . $_POST['transaction_reference'];
		$message .= ' - ' . $hipay->l('State : ') . $_POST['state'];
		$message .= ' - ' . $hipay->l('Status : ') . $_POST['status'];
		$message .= ' - ' . $hipay->l('Message : ') . $_POST['message'];
		$message .= ' - ' . $hipay->l('data : ') . $_POST['cdata1'];
		$message = strip_tags($message, '<br>');
		if (Validate::isCleanHtml($message)) {
			$msg->message = $message;
			$msg->id_order = intval($order->id);
			$msg->private = 1;
			$msg->add();
		}
		HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'hipayValidateOrder status skip - cid : ' . (int) $_POST ['order']->id);
		die();
		return;
	}

	if ($orderState == '124') {
		// NOTE only status 124 present, because logically, status 124 needs to be processed BEFORE status 125 can be send.
		// 124 = If orderstate is refund requested, we don't add a new order but only the log
		// 125 = If orderstate is refund approved, we don't add a new order but only the log
		hipayUpdateOrder($cart, $orderState);
		return;
	}

	HipayLogger::addLog($hipay->l('Callback paiement starting', 'hipay'), HipayLogger::NOTICE, 'Cart id : ' . $cart->id . ' - Order state : ' . $orderState);
	$id_cart = $cart->id;
	$id_order_state = $orderState;
	$amount_paid = $_POST ['captured_amount'];
	$message = $hipay->l('Transaction Reference:') . ' ' . $_POST ['transaction_reference'] . '
                ' . $hipay->l('State:') . ' ' . $_POST ['state'] . '
                ' . $hipay->l('Status:') . ' ' . $_POST ['status'] . '
                ' . $hipay->l('Message:') . ' ' . $_POST ['message'] . '
                ' . $hipay->l('Data:') . ' ' . $_POST['cdata1'] . '
                ' . $hipay->l('orderState:') . ' ' . $orderState . '
                ' . $hipay->l('Payment mean:') . ' ' . $_POST ['payment_product'] . '
                ' . $hipay->l('Payment has began at:') . ' ' . $_POST ['date_created'] . '
                ' . $hipay->l('Payment received at:') . ' ' . $_POST ['date_authorized'] . '
                ' . $hipay->l('authorization Code:') . ' ' . $_POST ['authorization_code'] . '
                ' . $hipay->l('Currency:') . ' ' . $_POST ['currency'] . '
                ' . $hipay->l('Customer IP address:') . ' ' . $_POST ['ip_address'];

	/**
	 * Validate an order in database
	 * Function called from a payment module
	 *
	 * @param integer $id_cart
	 *        	Value
	 * @param integer $id_order_state
	 *        	Value
	 * @param float $amount_paid
	 *        	Amount really paid by customer (in the default currency)
	 * @param string $payment_method
	 *        	Payment method (eg. 'Credit card')
	 * @param string $message
	 *        	Message to attach to order
	 */
	// Local Cards update
	$local_card_name = ''; // Initialize to empty string

	if ($_POST ['payment_product'] != '') {
		// Add the card name
		$local_card_name = ' via ' . (string) ucwords($_POST ['payment_product']);
		// Retrieve xml list
		if (file_exists(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml')) {
			$local_cards = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml');
			// If cards exists
			if (isset($local_cards)) {
				// If cards count > 0
				if (count($local_cards)) {
					// Go through each card
					foreach ($local_cards as $key => $value) {
						// If card code value = payment_product value
						if ((string) $value->code == trim($_POST ['payment_product'])) {
							// Add the card name
							$local_card_name = ' via ' . (string) $value->name;
						}
					}
				}
			}
		}
	}
	$secure_key = $customer->secure_key;
	if ($secure_key == null) {
		// If secure key is null force a secure key
		$secure_key = md5(uniqid(rand(), true));
	}

	// If captured amount is zero, capture amount to be paid to prevent errors
	// Then update to actual captured amount when order has been created.
	$update_order_payment = false;

	if ($amount_paid <= 0) {
		$amount_paid = $_POST['authorized_amount'];
		$update_order_payment = true;
	}


	if ($hipay->validateOrder(intval($id_cart), $id_order_state, $amount_paid, $hipay->displayName . $local_card_name, $message, array(), NULL, false, $secure_key)) {
		$GLOBALS['_HIPAY_CALLBACK_ORDER_ID_'] = $hipay->currentOrder;
		// Check if the amount_paid = 0
		// If amount_paid = 0, PS will an error paid status.
		// We need to update that to the id_order_state.
		if ($amount_paid == 0) {
			$new_order = new order($hipay->currentOrder);
			$history = new OrderHistory ();
			$history->id_order = (int) ($hipay->currentOrder);
			$history->changeIdOrderState((int) $id_order_state, $new_order, true);
			$history->add();
		}

		// If $update_order_payment = true then update order_payment with captured_amount.
		if ($update_order_payment) {
			$new_order = new order($hipay->currentOrder);
			$sql = "UPDATE `" . _DB_PREFIX_ . "order_payment`
                        SET `amount` = '" . $_POST['captured_amount'] . "'
                        WHERE `order_reference`='" . $new_order->reference . "'";
			Db::getInstance()->execute($sql);
		}

		// Add card details to orderpayments
		// $hipay->currentOrder should give the current cart ID
		if (isset($_POST ['payment_method']->token)) {
			$new_order = new order($hipay->currentOrder);
			$sql = "UPDATE `" . _DB_PREFIX_ . "order_payment`
                        SET `card_number` = '" . pSQL($_POST['payment_method']->pan) . "',
                        `transaction_id` = '" . pSQL($_POST['transaction_reference']) . "',
                        `card_brand` = '" . pSQL($_POST['payment_method']->brand) . "',
                        `card_expiration` = '" . pSQL($_POST['payment_method']->card_expiry_month) . "/" . pSQL($_POST['payment_method']->card_expiry_year) . "',
                        `card_holder` = '" . pSQL($_POST['payment_method']->card_holder) . "'
                        WHERE `order_reference`='" . pSQL($new_order->reference) . "'";
			Db::getInstance()->execute($sql);
		}

		// Add HIPAY_CAPTURE message to allow use of refund and capture
		$tag = 'HIPAY_CAPTURE ';
		$amount = $_POST['captured_amount'];
		$msgs = Message::getMessagesByOrderId($hipay->currentOrder, true); //true for private messages (got example from AdminOrdersController)
		$create_new_msg = true;
		if (count($msgs)) {
			foreach ($msgs as $msg) {
				$line = $msg['message'];
				if (startsWith($line, $tag)) {
					$create_new_msg = false;
					$to_update_msg = new Message($msg['id_message']);
					$to_update_msg->message = $tag . $amount;
					$to_update_msg->save();
					break;
				}
			}
		}
		if ($create_new_msg) {
			// Create msg
			$msg = new Message ();
			$message = 'HIPAY_CAPTURE ' . $amount;
			$message = strip_tags($message, '<br>');
			if (Validate::isCleanHtml($message)) {
				$msg->message = $message;
				$msg->id_order = intval((int) $hipay->currentOrder);
				$msg->private = 1;
				$msg->add();
			}
		}

		// 'OK ORDER';
		HipayLogger::addLog($hipay->l('Callback paiement successful', 'hipay'), HipayLogger::NOTICE, 'Cart id : ' . $cart->id . ' - Order state : ' . $orderState . ' - Message : ' . $message);
	} else {
		// 'KO ORDER';
		HipayLogger::addLog($hipay->l('Callback paiement failed', 'hipay'), HipayLogger::NOTICE, 'Cart id : ' . $cart->id . ' - Order state : ' . $orderState . ' - Message : ' . $message);
	}
}

function hipayUpdateOrder($cart = null, $newOrderStatusId = _PS_OS_ERROR_) {
	echo '-fnUO';
	checkStatus116();
	$hipay = new HiPay_Tpp();
	$order_id = retrieveOrderId($cart->id);
	$order = new Order((int) $order_id);
	echo '/Oid_' . $order_id;
	if ($newOrderStatusId == 'skip') {
		// Simply log the callback
		$msg = new Message ();
		$message = $hipay->l('HiPay - Callback initiated');
		$message .= ' - ' . $hipay->l('Transaction_reference : ') . $_POST['transaction_reference'];
		$message .= ' - ' . $hipay->l('State : ') . $_POST['state'];
		$message .= ' - ' . $hipay->l('Status : ') . $_POST['status'];
		$message .= ' - ' . $hipay->l('Message : ') . $_POST['message'];
		$message .= ' - ' . $hipay->l('data : ') . $_POST['cdata1'];
		$message = strip_tags($message, '<br>');
		if (Validate::isCleanHtml($message)) {
			$msg->message = $message;
			$msg->id_order = intval($order->id);
			$msg->private = 1;
			$msg->add();
		}
		HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'hipayUpdateOrder status skip - cid : ' . (int) $_POST ['order']->id);
		die();
		return;
	}

	if ($_POST['status'] == '124') {
		$amount = - 1 * $_POST ['refunded_amount'];
		$msg = new Message ();
		$message = $hipay->l('HiPay - Refund request Callback initiated - ');
		$message .= ' - ' . $hipay->l('Refund amount requested : ') . $amount;
		$message .= ' - ' . $hipay->l('Transaction_reference : ') . $_POST['transaction_reference'];
		$message .= ' - ' . $hipay->l('State : ') . $_POST['state'];
		$message .= ' - ' . $hipay->l('Status : ') . $_POST['status'];
		$message .= ' - ' . $hipay->l('Message : ') . $_POST['message'];
		$message .= ' - ' . $hipay->l('data : ') . $_POST['cdata1'];
		$message = strip_tags($message, '<br>');
		if (Validate::isCleanHtml($message)) {
			$msg->message = $message;
			$msg->id_order = intval($order->id);
			$msg->private = 1;
			$msg->add();
		}
	}

	if ($newOrderStatusId == Configuration::get('HIPAY_REFUNDED')) {
		refundOrder($order);
	}

	checkStatus116();
	updateHistory($order_id, $newOrderStatusId);
	return true;
}

function updateHistory($order_id = null, $newOrderStatusId = null) {
	echo '-fnUH';
	checkStatus116();
	
	$updateorders = true;
	
	// Verifications to skip the update History is there is a need for it.
	
	// Case 1 : Paiement partielle
	// Status 117 and 118 creates a double entry. Only one entry is needed ( in case of 117 only )
	$order = new Order((int) $order_id);
	if ($_POST['status'] == '118') {
		// If current state is already partially captured
		if ($order->getCurrentState()==Configuration::get('HIPAY_PARTIALLY_CAPTURED')) {
			// And if the amount capture is still being partially captured
			if ($_POST['captured_amount'] < $_POST['authorized_amount']) {
				$updateorders = false;
			}
		}
	}
	
	
	if($updateorders)
	{
	// Update orders
	$sql_update = "UPDATE `" . _DB_PREFIX_ . "orders`
        SET `current_state` = '" . (int)$newOrderStatusId . "'
        WHERE `id_order`='" . (int)$order_id . "'";
	Db::getInstance()->execute($sql_update);

	// Insert into order_history
	$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`)
        VALUES ('0', '" . (int)$order_id . "', '" . (int)$newOrderStatusId . "', now());";
	Db::getInstance()->execute($sql_insert);

	}
	
	// Update to minimize risk of simultaneous calls for status 116 and 117
	if ($_POST['status'] == '116') {
		// If order exists for cart
		$cart = new Cart((int) $_POST ['order']->id);
		if ($cart->orderExists()) {
			$context = Context::getContext();
			// Retrieve Order ID
			$order_id = retrieveOrderId($cart->id);
			$order = new Order((int) $order_id);
			// If current state is paiement accepted or capture partielle
			// then skip the process
			if ((boolean) $order->getHistory($context->language->id, _PS_OS_PAYMENT_)) {
				// Update orders
				$sql_update = "UPDATE `" . _DB_PREFIX_ . "orders`
                    SET `current_state` = '" . _PS_OS_PAYMENT_ . "'
                    WHERE `id_order`='" . (int)$order_id . "'";
				Db::getInstance()->execute($sql_update);

				// Insert into order_history
				$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`)
                    VALUES ('0', '" . (int)$order_id . "', '" . _PS_OS_PAYMENT_ . "', now());";
				Db::getInstance()->execute($sql_insert);
			}
			if ((boolean) $order->getHistory($context->language->id, Configuration::get('HIPAY_PARTIALLY_CAPTURED'))) {
				// Update orders
				$sql_update = "UPDATE `" . _DB_PREFIX_ . "orders`
                    SET `current_state` = '" . Configuration::get('HIPAY_PARTIALLY_CAPTURED') . "'
                    WHERE `id_order`='" . (int)$order_id . "'";
				Db::getInstance()->execute($sql_update);

				// Insert into order_history
				$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`)
                    VALUES ('0', '" . (int)$order_id . "', '" . Configuration::get('HIPAY_PARTIALLY_CAPTURED') . "', now());";
				Db::getInstance()->execute($sql_insert);
			}
			HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'updateHistory status 116 order already exists - cid : ' . (int) $_POST ['order']->id);
			die();
		}
	}

	return true;
}

function retrieveCallbackOS() {
	echo '-fnCBOS/' . (int) $_POST['status'] . '/' . $_POST['captured_amount'];
	$hipay = new HiPay_Tpp ();
	$orderState = _PS_OS_ERROR_; // Default to error;

	switch ((int) $_POST ['status']) {
		// Do nothing - Just log the status and skip further processing
		case 101 : // Created
		case 103 : // Cardholder Enrolled 3DSecure
		case 104 : // Cardholder Not Enrolled 3DSecure
		case 105 : // Unable to Authenticate 3DSecure
		case 106 : // Cardholder Authenticate
		case 107 : // Authentication Attempted
		case 108 : // Could Not Authenticate
		case 109 : // Authentication Failed
		case 120 : // Collected
		case 150 : // Acquirer Found
		case 151 : // Acquirer not Found
		case 161 : // Risk Accepted
		default :
			$orderState = 'skip';
			break;

		// Status _PS_OS_ERROR_
		case 110 : // Blocked
		case 129 : // Charged Back
			$orderState = _PS_OS_ERROR_;
			break;

		// Status HIPAY_DENIED
		case 111 : // Denied
		case 113 : // Refused
			// Modif : passer en _PS_OS_ERROR_	 puis en HIPAY_DENIED
//			$orderState = (Configuration::get ( 'HIPAY_DENIED' )) ? Configuration::get ( 'HIPAY_DENIED' ) : HipayClass::getConfiguration('HIPAY_DENIED');
			$orderState = _PS_OS_ERROR_;
			break;

		// Status HIPAY_CHALLENGED
		case 112 : // Authorized and Pending
			$orderState = (Configuration::get('HIPAY_CHALLENGED')) ? Configuration::get('HIPAY_CHALLENGED') : HipayClass::getConfiguration('HIPAY_CHALLENGED');
			break;
		
		// Status HIPAY_PENDING
		case 140 : // Authentication Requested
		case 142 : // Authorization Requested
		case 200 : // Pending Payment
			$orderState = (Configuration::get('HIPAY_PENDING')) ? Configuration::get('HIPAY_PENDING') : HipayClass::getConfiguration('HIPAY_PENDING');
			break;

		// Status HIPAY_EXPIRED
		case 114 : // Expired
			$orderState = (Configuration::get('HIPAY_EXPIRED')) ? Configuration::get('HIPAY_EXPIRED') : HipayClass::getConfiguration('HIPAY_EXPIRED');
			break;

		// Status _PS_OS_CANCELED_
		case 115 : // Cancelled
			$orderState = _PS_OS_CANCELED_;
			break;

		// Status HIPAY_AUTHORIZED
		case 116 : // Authorized
			$orderState = (Configuration::get('HIPAY_AUTHORIZED')) ? Configuration::get('HIPAY_AUTHORIZED') : HipayClass::getConfiguration('HIPAY_AUTHORIZED');
			break;

		// Status HIPAY_CAPTURE_REQUESTED
		case 118 :
		case 117 : // Capture Requested
			//$orderState = (Configuration::get ( 'HIPAY_CAPTURE_REQUESTED' )) ? Configuration::get ( 'HIPAY_CAPTURE_REQUESTED' ) : HipayClass::getConfiguration('HIPAY_CAPTURE_REQUESTED');
			$orderState = _PS_OS_PAYMENT_;
			if ($_POST['captured_amount'] < $_POST['authorized_amount']) {
				$orderState = (Configuration::get('HIPAY_PARTIALLY_CAPTURED')) ? Configuration::get('HIPAY_PARTIALLY_CAPTURED') : HipayClass::getConfiguration('HIPAY_PARTIALLY_CAPTURED');
			}

			// FORCING PRIVATE MSG FOR CAPTURE HERE
			// STATUS 119 does not seem to be called at all, even for partially captured calls.
			// Check if message exists already
			$cart = new Cart((int) $_POST ['order']->id);
			if ($cart->orderExists()) {
				$order_id = retrieveOrderId($cart->id);
				$order = new Order($order_id);
				captureOrder($order);
				$tag = 'HIPAY_CAPTURE ';
				$amount = $_POST['captured_amount'];
				$msgs = Message::getMessagesByOrderId($order_id, true); //true for private messages (got example from AdminOrdersController)
				$create_new_msg = true;
				if (count($msgs)) {
					foreach ($msgs as $msg) {
						$line = $msg['message'];
						if (startsWith($line, $tag)) {
							$create_new_msg = false;
							$to_update_msg = new Message($msg['id_message']);
							$to_update_msg->message = $tag . $amount;
							$to_update_msg->save();
							break;
						}
					}
				}
				if ($create_new_msg) {
					// Create msg
					$msg = new Message ();
					$message = 'HIPAY_CAPTURE ' . $amount;
					$message = strip_tags($message, '<br>');
					if (Validate::isCleanHtml($message)) {
						$msg->message = $message;
						$msg->id_order = intval((int) $order_id);
						$msg->private = 1;
						$msg->add();
					}
				}
			}
			break;

		// Status HIPAY_PARTIALLY_CAPTURED
		case 119 : // Partially Captured
			$orderState = (Configuration::get('HIPAY_PARTIALLY_CAPTURED')) ? Configuration::get('HIPAY_PARTIALLY_CAPTURED') : HipayClass::getConfiguration('HIPAY_PARTIALLY_CAPTURED');
			break;

		// Status HIPAY_REFUND_REQUESTED
		case 124 : // Refund Requested
			$orderState = (Configuration::get('HIPAY_REFUND_REQUESTED')) ? Configuration::get('HIPAY_REFUND_REQUESTED') : HipayClass::getConfiguration('HIPAY_REFUND_REQUESTED');
			break;

		// Status HIPAY_REFUNDED
		case 125 : // Refunded
			$orderState = (Configuration::get('HIPAY_REFUNDED')) ? Configuration::get('HIPAY_REFUNDED') : HipayClass::getConfiguration('HIPAY_REFUNDED');
			break;

		// Status HIPAY_CHARGED BACK
		case 129 : // Charged back
			$orderState = (Configuration::get('HIPAY_CHARGEDBACK')) ? Configuration::get('HIPAY_CHARGEDBACK') : HipayClass::getConfiguration('HIPAY_CHARGEDBACK');
			break;

		// Status HIPAY_CAPTURE_REFUSED
		case 173 : // Capture Refused
			$orderState = (Configuration::get('HIPAY_CAPTURE_REFUSED')) ? Configuration::get('HIPAY_CAPTURE_REFUSED') : HipayClass::getConfiguration('HIPAY_CAPTURE_REFUSED');
			break;
	}

	return $orderState;
}

function retrieveOrderId($cart_id = 0, $count = 0) {
	$sql = 'SELECT id_order FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart` = ' . (int) $cart_id;
	$result = Db::getInstance()->getValue($sql);
	if ($result) {
		echo '-Oid' . $result . '/c' . $count;
		return $result;
	} else {
		usleep(500000); // 0.5sec
		$count++;
		if ($count >= 15) {
			// Ajout commentaire status KO
			$msg = new Message ();
			$message = $hipay->l('HiPay - Callback Timedout.');
			$message .= ' - ' . $hipay->l('Status:') . ' ' . $_POST ['status'];
			$message .= ' - ' . $hipay->l('Amount to be captured :') . ' ' . $_POST ['captured_amount'];
			;
			$message = strip_tags($message, '<br>');
			if (Validate::isCleanHtml($message)) {
				$msg->message = $message;
				$msg->id_order = intval($order->id);
				$msg->private = 1;
				$msg->add();
			}
			HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'retrieveOrderId Callback Timedout - cid : ' . (int) $_POST ['order']->id);
			die();
		} else {
			retrieveOrderId($cart_id, $count);
		}
	}
}

function startsWith($haystack, $needle) {
	return $needle === "" || strpos($haystack, $needle) === 0;
}

function captureOrder($order = null) {
	echo '-fnCO';
	$hipay = new HiPay_Tpp();

	// Local Cards update
	$local_card_name = ''; // Initialize to empty string
	if ($_POST ['payment_product'] != '') {
		// Add the card name
		$local_card_name = ' via ' . (string) ucwords($_POST ['payment_product']);
		// Retrieve xml list
		if (file_exists(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml')) {
			$local_cards = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml');
			// If cards exists
			if (isset($local_cards)) {
				// If cards count > 0
				if (count($local_cards)) {
					// Go through each card
					foreach ($local_cards as $key => $value) {
						// If card code value = payment_product value
						if ((string) $value->code == trim($_POST ['payment_product'])) {
							// Add the card name
							$local_card_name = ' via ' . (string) $value->name;
						}
					}
				}
			}
		}
	}

	$sql = "UPDATE `" . _DB_PREFIX_ . "order_payment`
            SET `amount` = '" . pSQL($_POST ['captured_amount']) . "',
            `transaction_id` = '" . pSQL($_POST['transaction_reference']) . "'
            WHERE order_reference='" . pSQL($order->reference) . "'
            AND payment_method='" . $hipay->displayName . $local_card_name . "'
            AND amount>=0
            LIMIT 1";

	Db::getInstance()->execute($sql);

	return true;
}

function refundOrder($order = null) {
	$hipay = new HiPay_Tpp ();

	$amount = - 1 * $_POST ['refunded_amount']; // Set refund to negative
	$payment_method = 'HiPay - refund';
	$payment_transaction_id = '';
	$currency = new Currency($order->id_currency);
	$payment_date = date("Y-m-d H:i:s");
	$order_has_invoice = $order->invoice_number;
//	if ($order_has_invoice)
//		$order_invoice = new OrderInvoice( Tools::getValue ( 'payment_invoice' ) );
//	else
//		$order_invoice = null;

	if (!addOrderPayment($order->id, $amount)) {
		// Ajout commentaire status KO
		$msg = new Message();
		$message = $hipay->l('HiPay - Refund failed.');
		$message .= ' - ' . $hipay->l('Amount refunded failed =') . ' ' . $amount;
		$message = strip_tags($message, '<br>');
		if (Validate::isCleanHtml($message)) {
			$msg->message = $message;
			$msg->id_order = intval($order->id);
			$msg->private = 1;
			$msg->add();
		}
	} else {
		$cart = new Cart((int) $_POST ['order']->id);
		$order_id = retrieveOrderId($cart->id);
		$tag = 'HIPAY_CAPTURE ';
		$amount = $_POST ['captured_amount'] - $_POST ['refunded_amount'];
		$msgs = Message::getMessagesByOrderId($order_id, true); //true for private messages (got example from AdminOrdersController)
		$create_new_msg = true;
		if (count($msgs)) {
			foreach ($msgs as $msg) {
				$line = $msg['message'];
				if (startsWith($line, $tag)) {
					$create_new_msg = false;
					$to_update_msg = new Message($msg['id_message']);
					$to_update_msg->message = $tag . $amount;
					$to_update_msg->save();
					break;
				}
			}
		}
	}

	return true;
}

/**
 * Function to die the callback process if the status is 116 (Authorised) and if the order already has been paid or partially paid
 *
 */
function checkStatus116() {
	// Update to minimize risk of simultaneous calls for status 116 and 117
	if ($_POST['status'] == '116') {
		echo '-fn116';
		// If order exists for cart
		$cart = new Cart((int) $_POST['order']->id);
		if ($cart->orderExists()) {
			echo '/C_OK_' . $GLOBALS['_HIPAY_CALLBACK_ORDER_ID_'];
			$context = Context::getContext();
			// Retrieve Order ID
			$order_id = retrieveOrderId($cart->id);
			$order = new Order((int) $order_id);
			echo '/Oid_' . (int) $order_id;
			// If current state is paiement accepted or capture partielle
			// then skip the process
			if ($order->current_state == _PS_OS_PAYMENT_ || $order->current_state == Configuration::get('HIPAY_PARTIALLY_CAPTURED')
			) {
				HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'checkStatus116 current state _PS_OS_PAYMENT_ or HIPAY_PARTIALLY_CAPTURED captured already - cid : ' . (int) $_POST ['order']->id);
				die();
			}
			if ((boolean) $order->getHistory($context->language->id, _PS_OS_PAYMENT_)) {
				// Update orders
				HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'checkStatus116 status _PS_OS_PAYMENT_ already in order history - cid : ' . (int) $_POST ['order']->id);
				die();
			}
			if ((boolean) $order->getHistory($context->language->id, Configuration::get('HIPAY_PARTIALLY_CAPTURED'))) {
				// Update orders
				HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::ERROR, 'checkStatus116 status HIPAY_PARTIALLY_CAPTURED already in order history - cid : ' . (int) $_POST ['order']->id);
				die();
			}
		} else {
			echo '/C_KO_' . $GLOBALS['_HIPAY_CALLBACK_ORDER_ID_'];
		}
	}
}

/*
 * Function called at the end of all processes to reset order status/history if the order has already been paid, but status 116 is currently being processed.
 */

function hipayResetOrderStatus($cart = null) {
	echo '-fnROS';	
	
	if ($_POST['status'] == '117' || $_POST['status'] == '118') {
		$cart = new Cart((int) $_POST['order']->id);
		if ($cart->orderExists()) {
			$orderState = _PS_OS_PAYMENT_;
			if ($_POST['captured_amount'] < $_POST['authorized_amount']) {
				$orderState = (Configuration::get('HIPAY_PARTIALLY_CAPTURED')) ? Configuration::get('HIPAY_PARTIALLY_CAPTURED') : HipayClass::getConfiguration('HIPAY_PARTIALLY_CAPTURED');
			}
			// FORCE INVOICE CREATION IF OrderState = _PS_OS_PAYMENT_
			if ($orderState == _PS_OS_PAYMENT_) {
				$order_id = retrieveOrderId($cart->id); // Retrieve order id
				$order = new Order((int) $order_id); // Recreate order
				$newOS = new OrderState((int) ($orderState), $order->id_lang); // Emulate the order state _PS_OS_PAYMENT_
				// Uf the order state allows invoice and there is no invoice number, then generate the invoice
				if ($newOS->invoice AND !$order->invoice_number)
					$order->setInvoice();
			}
		}
	}
	
	// New modification for status challenged
	// Second check for status 112 -> 117 -> 118
	if ($_POST['status'] == '117') {
		if ((boolean) $order->getHistory($context->language->id, Configuration::get('HIPAY_CHALLENGED'))) {
			$cart = new Cart((int) $_POST['order']->id);
			if ($cart->orderExists()) {
				$orderState = _PS_OS_PAYMENT_;
			}
			if ($_POST['captured_amount'] < $_POST['authorized_amount']) {
				$orderState = (Configuration::get('HIPAY_PARTIALLY_CAPTURED')) ? Configuration::get('HIPAY_PARTIALLY_CAPTURED') : HipayClass::getConfiguration('HIPAY_PARTIALLY_CAPTURED');
			}
			// FORCE INVOICE CREATION IF OrderState = _PS_OS_PAYMENT_
			if ($orderState == _PS_OS_PAYMENT_) {
				$order_id = retrieveOrderId($cart->id); // Retrieve order id
				$order = new Order((int) $order_id); // Recreate order
				$newOS = new OrderState((int) ($orderState), $order->id_lang); // Emulate the order state _PS_OS_PAYMENT_
				// Uf the order state allows invoice and there is no invoice number, then generate the invoice
				if ($newOS->invoice AND !$order->invoice_number)
					$order->setInvoice();
			}
		}
	}
	
	// Update to minimize risk of simultaneous calls for status 116 and 117
	if ($_POST['status'] == '116') {
		usleep(500000); // 0.5sec
		echo '/116';
		// If order exists for cart
		$cart = new Cart((int) $_POST['order']->id);
		if ($cart->orderExists()) {
			echo '/C_OK' . $GLOBALS['_HIPAY_CALLBACK_ORDER_ID_'];
			$context = Context::getContext();
			// Retrieve Order ID
			$order_id = retrieveOrderId($cart->id);
			$order = new Order((int) $order_id);
			echo '/' . (int) $order_id;
			// If current state is paiement accepted or capture partielle
			// then skip the process
			if ((boolean) $order->getHistory($context->language->id, _PS_OS_PAYMENT_)) {
				echo '/' . (int) _PS_OS_PAYMENT_ . '_U';
				// Update orders
				$sql_update = "UPDATE `" . _DB_PREFIX_ . "orders`
                    SET `current_state` = '" . _PS_OS_PAYMENT_ . "'
                    WHERE `id_order`='" . (int)$order_id . "'";
				Db::getInstance()->execute($sql_update);

				// Insert into order_history
				$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`)
                    VALUES ('0', '" . (int)$order_id . "', '" . _PS_OS_PAYMENT_ . "', now());";
				Db::getInstance()->execute($sql_insert);
			}
			if ((boolean) $order->getHistory($context->language->id, Configuration::get('HIPAY_PARTIALLY_CAPTURED'))) {
				echo '/' . (int) Configuration::get('HIPAY_PARTIALLY_CAPTURED') . '_U';
				// Update orders
				$sql_update = "UPDATE `" . _DB_PREFIX_ . "orders`
                    SET `current_state` = '" . Configuration::get('HIPAY_PARTIALLY_CAPTURED') . "'
                    WHERE `id_order`='" . (int)$order_id . "'";
				Db::getInstance()->execute($sql_update);

				// Insert into order_history
				$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "order_history` (`id_employee`, `id_order`, `id_order_state`, `date_add`)
                    VALUES ('0', '" . (int)$order_id . "', '" . Configuration::get('HIPAY_PARTIALLY_CAPTURED') . "', now());";
				Db::getInstance()->execute($sql_insert);
			}
			HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::NOTICE, 'hipayResetOrderStatus status 116 cart already ok - cid : ' . (int) $_POST ['order']->id);
			die();
		} else {
			echo '/C_KO' . $GLOBALS['_HIPAY_CALLBACK_ORDER_ID_'];
		}
	}
	HipayLogger::addLog($hipay->l('Callback process', 'hipay'), HipayLogger::NOTICE, 'hipayResetOrderStatus ended - cid : ' . (int) $_POST ['order']->id);
	die();
}

/**
 *
 * This method allows to add a payment to the current order
 * @since 1.5.0.1
 * @param float $amount_paid
 * @param string $payment_method
 * @param string $payment_transaction_id
 * @param Currency $currency
 * @param string $date
 * @param OrderInvoice $order_invoice
 * @return bool
 */
function addOrderPayment($id_order = 0, $amount_paid = 0) {
	$result = 0;

	// recreate order
	$order = new Order($id_order);

	// Modif - bug found on $amount_paid. Amount keeps incrementing ( sum of all refunds ) instead of reflecting refund for this specific callback
	$total_paid_real = $_POST ['captured_amount'] - $_POST ['refunded_amount'];

	$sql = "UPDATE `" . _DB_PREFIX_ . "orders`
            SET `total_paid_real` = '" . pSQL($total_paid_real) . "'
            WHERE `id_order`='" . (int)$id_order . "'";
	$result = Db::getInstance()->execute($sql);

	return $result;
}
