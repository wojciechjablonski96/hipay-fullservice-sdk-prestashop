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
require_once(dirname(__FILE__) . '/../../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../../init.php');
require_once(dirname(__FILE__) . '/hipay_tpp.php');

// init version prestashop = or != 1600
$str_ps_version = (int) str_replace('.', '', _PS_VERSION_);

define('HIPAY_LOG', 1);
define('HIPAY_STATUS_DENIED', 111);
define('HIPAY_STATUS_REFUSED', 113);
define('HIPAY_STATUS_AUTHORIZED', 116);
define('HIPAY_STATUS_CAPTURE_REQUESTED', 117);
define('HIPAY_STATUS_CAPTURED', 118);
define('HIPAY_STATUS_REFUNDED', 125);

$hipay = new HiPay_Tpp();
$callback_arr = array();

// récupération data et reconstruction des données
$arr = (array) $_POST;
foreach ($arr as $key => $value) {
	$callback_arr[$key] = $value;
}

//LOG 
HipayLog('##########################################');
HipayLog('##########################################');
HipayLog('DEBUT TRAITEMENT DU CALLBACK');
HipayLog(print_r($callback_arr,TRUE));

// if state and status exist or not
if (!isset($callback_arr['state']) && !isset($callback_arr['status'])) {
	HipayLogger::addLog($hipay->l('Bad Callback initiated', 'hipay'), HipayLogger::ERROR, 'Bad Callback initiated, but not processed further');
	die();
}
//LOG 
HipayLog('state exist');
// set variables state and status, if value is empty => init error
$log_state = ($callback_arr['state']) ? $callback_arr['state'] : 'error';
$num_status = (int)$callback_arr['status'];
$log_status = ($callback_arr['status']) ? $callback_arr['status'] : 'error';

//LOG 
HipayLog('---------- $log_state = '.$log_state);
HipayLog('---------- $num_status = '.$num_status);
HipayLog('---------- $log_status = '.$log_status);

// if cart_id exist or not
if (!isset($callback_arr['order']['id'])) {
	HipayLogger::addLog($hipay->l('Bad Callback initiated', 'hipay'), HipayLogger::ERROR, 'Bad Callback initiated, no cart ID found - data : ' . Db::getInstance()->escape($data));
	die('No cart found'); 
}

//LOG 
HipayLog('---------- $callback id cart = '.$callback_arr['order']['id']);

// init du panier en question
$id_cart = (int)$callback_arr['order']['id'];
$cart = new Cart($id_cart);
// on vérifie si l'objet est bien chargé
if (!Validate::isLoadedObject($cart)) {
	HipayLogger::addLog($hipay->l('Bad Callback initiated', 'hipay'), HipayLogger::ERROR, 'Bad Callback initiated, cart could not be initiated - data : ' . Db::getInstance()->escape($data));
	die('Cart empty');
}

//LOG
HipayLog('---------- Boutique N°'.$cart->id_shop);
//LOG 
HipayLog('---------- panier exist ID = '.$cart->id);

try{
	// pose du lock SQL via SELECT FOR UPDATE sur la ligne panier
	// met en attente les autres notifications pour éviter de traiter
	// des données en doublon
	#################################################################
	#################################################################
	#################################################################
	$sql = 'begin;';
	$sql .= 'SELECT id_cart FROM '._DB_PREFIX_.'cart WHERE id_cart = '. (int)$id_cart .' FOR UPDATE;';
	
	//LOG 
	HipayLog('********** SQL LOCK = '. $sql);
	
	if (!Db::getInstance()->execute($sql)){
		HipayLogger::addLog($hipay->l('Bad LockSQL initiated', 'hipay'), HipayLogger::ERROR, 'Bad LockSQL initiated, Lock could not be initiated for id_cart = ' . $id_cart);
		die('Lock not initiated');
	}
	
	//LOG 
	HipayLog('********** LOCK posé');

	#################################################################
	#################################################################
	#################################################################
	// log début du traitement
	HipayLogger::addLog(
		$hipay->l('Callback initiated', 'hipay'), 
		HipayLogger::NOTICE, 
		'Callback initiated - cid : ' . $id_cart . ' - token = ' . $callback_arr['payment_method']['token'] . ' - state : ' . $log_state . ' - status : ' . $log_status
	);

	// init numero statuts
	$stt_challenged = Configuration::get('HIPAY_CHALLENGED',null,null,1);
	$stt_pending	= Configuration::get('HIPAY_PENDING',null,null,1);
	$stt_expired	= Configuration::get('HIPAY_EXPIRED',null,null,1);
	$stt_authorized = Configuration::get('HIPAY_AUTHORIZED',null,null,1);
	$stt_partially 	= Configuration::get('HIPAY_PARTIALLY_CAPTURED',null,null,1);
	$stt_refunded_rq= Configuration::get('HIPAY_REFUND_REQUESTED',null,null,1);
	$stt_refunded	= Configuration::get('HIPAY_REFUNDED',null,null,1);
	$stt_chargedback= Configuration::get('HIPAY_CHARGEDBACK',null,null,1);
	$stt_c_refused 	= Configuration::get('HIPAY_CAPTURE_REFUSED',null,null,1);
	$stt_denied		= Configuration::get('HIPAY_DENIED',null,null,1);
	$stt_cancel		= _PS_OS_CANCELED_;
	$stt_error		= _PS_OS_ERROR_;
	$stt_payment	= _PS_OS_PAYMENT_;
	$arrStatut = array(
			'stt_challenged = '.$stt_challenged,
			'stt_pending = '.$stt_pending,
			'stt_expired = '.$stt_expired,
			'stt_authorized = '.$stt_authorized,
			'stt_partially = '.$stt_partially,
			'stt_refunded_rq = '.$stt_refunded_rq,
			'stt_chargedback = '.$stt_chargedback,
			'stt_c_refused = '.$stt_c_refused,
			'stt_denied = '.$stt_denied,
			'stt_cancel = '.$stt_cancel,
			'stt_error = '.$stt_error,
			'stt_payment = '.$stt_payment
		);
	// LOG
	HipayLog('Vérification configuration statut = [ '.print_r($arrStatut,true).' ]');

	//LOG 
	HipayLog('---------- config statut intialisé');

	// order exist
	// init order pour traitement
	// on charge tout une fois au début
	$order_exist = false;
	$id_order 	 = false;
	$objOrder	 = false;

	//LOG 
	HipayLog('---------- début ctrl order existe');

	if($cart->orderExists()){
		// il existe une commande associée à ce panier
		$order_exist = true;
		// init de l'id de commande
		//$id_order = Order::getOrderByCartId($id_cart);
		$sql = 'SELECT `id_order`
				FROM `'._DB_PREFIX_.'orders`
				WHERE `id_cart` = '.(int)$id_cart;
        $result = Db::getInstance()->getRow($sql);
        $id_order = isset($result['id_order']) ? $result['id_order'] : false;
		if($id_order){
			$objOrder = new Order((int)$id_order);
		}
	}

	//LOG 
	HipayLog('---------- order_exist = '. $order_exist);
	HipayLog('---------- id_order = '. $id_order);
	if($id_order){
		HipayLog('---------- objOrder initialisé');
	}

	// exécution du traitement en rapport avec le N° de statut
	switch ($num_status) {
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
			$orderState = $stt_error;
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			break;

		// Status HIPAY_DENIED
		case HIPAY_STATUS_DENIED : // Denied
		case HIPAY_STATUS_REFUSED : // Refused
			$orderState = $stt_denied;
			// ctrl if payment accepted is already in order history
			if(!controleIfStatushistoryExist($id_order, $stt_payment, $orderState, true)){
				changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
				// si pas de commande existante, on créé la commande
				createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			}
			break;

		// Status HIPAY_CHALLENGED
		case 112 : // Authorized and Pending
			$orderState = $stt_challenged;
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			break;
		// Status HIPAY_PENDING
		case 140 : // Authentication Requested
		case 142 : // Authorization Requested
		case 200 : // Pending Payment
			$orderState = $stt_pending;
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			break;

		// Status HIPAY_EXPIRED
		case 114 : // Expired
			$orderState = $stt_expired;
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			break;

		// Status _PS_OS_CANCELED_
		case 115 : // Cancelled
			$orderState = $stt_cancel;
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			break;

		// Status HIPAY_AUTHORIZED
		case HIPAY_STATUS_AUTHORIZED :
			$orderState = $stt_authorized;
			// si commande existante, on modifie le statut de la commande
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState, $objOrder);
			break;

		// Status HIPAY_CAPTURE_REQUESTED
		case HIPAY_STATUS_CAPTURED :
		case HIPAY_STATUS_CAPTURE_REQUESTED : // Capture Requested
			$orderState = $stt_payment;
			if ($callback_arr['captured_amount'] < $callback_arr['authorized_amount']) {
				$orderState = $stt_partially;
			}
			// on change de statut de la commande tous les critères sont ok
			if(changeStatusOrder($order_exist, $id_order, $orderState, $objOrder, $callback_arr)){
				// on modifie la transaction de commande pour la référence commande
				captureOrder($callback_arr, $objOrder);
			}
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			break;
			
		// Status HIPAY_PARTIALLY_CAPTURED
		case 119 : // Partially Captured
			$orderState = $stt_partially;
			// on change de statut de la commande tous les critères sont ok
			if(changeStatusOrder($order_exist, $id_order, $orderState, $objOrder)){
				// on modifie la transaction de commande pour la référence commande
				captureOrder($callback_arr, $objOrder);
			}
			// si pas de commande existante, on créé la commande
			createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $orderState);
			break;

		// Status HIPAY_REFUND_REQUESTED
		case 124 : // Refund Requested
			$orderState = $stt_refunded_rq;
			// si commande existante, on modifie le statut de la commande
			if(changeStatusOrder($order_exist, $id_order, $orderState, $objOrder)){
				$statuts = array(
					'refund_requested'=>$orderState,
				);
				addMessageRefund($objOrder, $callback_arr, $hipay, $statuts);
			}
			break;

		// Status HIPAY_REFUNDED
		case 125 : // Refunded
			$orderState = $stt_refunded;
			// si commande existante, on modifie le statut de la commande
			if(changeStatusOrder($order_exist, $id_order, $orderState, $objOrder)){
				refundOrder($callback_arr, $objOrder, $hipay, $stt_refunded);
				addMessageRefund($objOrder, $callback_arr, $hipay, $orderState);
			}

			break;

		// Status HIPAY_CHARGED BACK
		case 129 : // Charged back
			$orderState = $stt_chargedback;
			// si commande existante, on modifie le statut de la commande
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			break;

		// Status HIPAY_CAPTURE_REFUSED
		case 173 : // Capture Refused
			$orderState = $stt_c_refused;
			// si commande existante, on modifie le statut de la commande
			changeStatusOrder($order_exist, $id_order, $orderState, $objOrder);
			break;
	}

	//LOG 12
	HipayLog('---------- Fin de traitement');

	// FIN du lock SQL - par un commit SQL
	#################################################################
	#################################################################
	#################################################################
	$sql = 'commit;';

	//LOG 13
	HipayLog('********** Fin LOCK = ' .$sql);
	HipayLog('FIN TRAITEMENT DU CALLBACK');
	HipayLog('##########################################');
	HipayLog('##########################################');

	if (!Db::getInstance()->execute($sql)){
		HipayLogger::addLog($hipay->l('Bad LockSQL initiated', 'hipay'), HipayLogger::ERROR, 'Bad LockSQL end, Lock could not be end for id_cart = ' . $id_cart);
		die('Lock not initiated');
	}
	#################################################################
	#################################################################
	#################################################################
}catch(Exception $e){
	$sql = 'commit;';
	if (!Db::getInstance()->execute($sql)){
		HipayLogger::addLog($hipay->l('Bad LockSQL initiated', 'hipay'), HipayLogger::ERROR, $e->getMessage());
		die('Lock not initiated');
	}
}

/*
 * Fonction ajouter un message privé dans la commande
 * @order 			- objet de la commande loadé
 * @callback_arr 	- tableau datas venant de TPP
 * @hipay 			- module hipay instancié
 * @statuts 		- tableau des statuts à traiter
 */
function addMessageRefund($order, $callback_arr, $hipay, $statuts = NULL){
	//LOG 
	HipayLog('--------------- Début addMessageRefund');

	if($statuts !== NULL){
			
		//LOG 
		HipayLog('--------------- addMessageRefund - init message');

		// Paiement has already been done at least once, stop the process
		$msg = new Message ();
		$message = $hipay->l('HiPay - Callback initiated') . "<br>";
		$message .= ' - ' . $hipay->l('Transaction_reference : ') . $callback_arr['transaction_reference'] . "<br>";
		$message .= ' - ' . $hipay->l('State : ') . $callback_arr['state'] . "<br>";
		$message .= ' - ' . $hipay->l('Status : ') . $callback_arr['status'] . "<br>";
		$message .= ' - ' . $hipay->l('Message : ') . $callback_arr['message'] . "<br>";
		$message .= ' - ' . $hipay->l('Amount : ') . $callback_arr['authorized_amount'] . "<br>";
		$message .= ' - ' . $hipay->l('Refund Amount : ') . $callback_arr['refunded_amount'] . "<br>";
		$message .= ' - '.$hipay->l ( 'NO ACTION TAKEN, CART HAS ALREADY BEEN INITIATED AS REFUND REQUESTED.') . "<br>";
		$message = strip_tags($message, '<br>');
		if (Validate::isCleanHtml($message)) {
			$msg->message = $message;
			$msg->id_order = (int)$order->id;
			$msg->private = 1;
			$msg->add();

			//LOG 
			HipayLog('--------------- addMessageRefund - fin');

			return true;
		}
	}
	return false;
}
/*
 * Fonction générique pour créer la commande avec le paiement HiPay
 * @order_exist 	- Boolean ture or false
 * @callback_arr 	- array data venant de TPP
 * @hipay 			- objet Hipay du module pour utiliser la traduction et le nom du module
 * @cart 			- objet panier pour l'id demandé
 * @statut 			- id du statut au moment de la création de la commande
 */
function createOrderByHipay($order_exist,$callback_arr, $hipay, $cart, $statut, $order = false){
	$bool = false;
	//LOG 
	HipayLog('--------------- Début createOrderByHipay');
	if(!$order_exist){
		//LOG
		HipayLog('--------------- pas de commande existante');
		// init message pour création de commande
		$message  = $hipay->l('HiPay - Callback initiated') . "<br>";
		$message .= ' - ' . $hipay->l('Transaction_reference : ') . $callback_arr['transaction_reference'] . "<br>";
		$message .= ' - ' . $hipay->l('State : ') . $callback_arr['state'] . "<br>";
		$message .= ' - ' . $hipay->l('Status : ') . $callback_arr['status'] . "<br>";
		$message .= ' - ' . $hipay->l('Message : ') . $callback_arr['message'] . "<br>";
		$message .= ' - ' . $hipay->l('Amount : ') . $callback_arr['authorized_amount'] . "<br>";
		$message = strip_tags($message, '<br>');
		//LOG 2
		HipayLog('--------------- MESSAGE = '.$message);
		// init order_payement
		$orderPayment = array(
			'transaction_id' => $callback_arr['transaction_reference'],
			'card_number' => $callback_arr['payment_method']['pan'],
			'card_brand' => $callback_arr['payment_method']['brand'],
			'card_expiration' => $callback_arr['payment_method']['card_expiry_month'].'/'.$callback_arr['payment_method']['card_expiry_year'],
			'card_holder' => $callback_arr['payment_method']['card_holder'],
			);
		// création de la commande sur le statut authorized
		// car pas de commande
		$tmpshop = new Shop((int)$cart->id_shop);
		//LOG
		HipayLog('--------------- SHOP Id = '.$cart->id_shop);
		HipayLog('--------------- Début validateOrder ');
		try{
			$hipay->validateOrder(
				$cart->id, 
				$statut, 
				(float)$callback_arr['authorized_amount'], 
				$hipay->displayName . ' via ' . ucfirst($callback_arr['payment_product']), 
				$message, 
				$orderPayment, 
				NULL, 
				false, 
				$cart->secure_key,
				$tmpshop
			);
		}catch(Exception $e){
			// on catch mais on continue le traitement
			HipayLog($e->getCode().' : ' . $e->getMessage());
		}
		HipayLog('--------------- Fin validateOrder ');
		// init order for message HIPAY_CAPTURE
		$id_order = $hipay->currentOrder;
		// LOG
		HipayLog('--------------- ID Current Order = '.$id_order);
		// Init / MAJ de la ligne message HIPAY_CAPTURE
		addHipayCaptureMessage($callback_arr,$id_order);
		$new_order = new order($id_order);
		// MAJ ligne transaction pour le status 116
		if ($callback_arr['status'] == HIPAY_STATUS_AUTHORIZED) {	
			// LOG
			HipayLog('--------------- STATUS 116 début update order payment');		
			$sql = "UPDATE `" . _DB_PREFIX_ . "order_payment` SET 
						`amount` = '" . $callback_arr['captured_amount'] . "'
                        WHERE `order_reference`='" . $new_order->reference . "'";
			Db::getInstance()->execute($sql);
			// LOG
			HipayLog('--------------- STATUS 116 fin update order payment');
		}
		// transaction table Hipay
        $sql = "
        		INSERT INTO `" . _DB_PREFIX_ . "hipay_transactions`
                    (`cart_id`,`order_id`,`customer_id`,`transaction_reference`,`device_id`,`ip_address`,`ip_country`,`token`) VALUES 
                    ('" . (int)$cart->id . "',
                    	'" . (int)$id_order . "',
                    	'" . (int)$new_order->id_customer . "',
                    	'" . pSQL($callback_arr['transaction_reference']) . "',
                    	'',
                    	'" . pSQL($callback_arr['ip_address']) . "',
                    	'" . pSQL($callback_arr['ip_country']) . "',
                    	'" . pSQL($callback_arr['payment_method']['token']) . "');";
		// LOG
        HipayLog('--------------- TABLE HIPAY = '. $sql);
        if(!Db::getInstance()->execute($sql)){
        	//LOG 
			HipayLog('Insert table HiPay en erreur');
			return false;
        }
        // Check if card is either an Americain-express, CB, Mastercard et Visa card.
		if ($callback_arr['payment_product'] == 'american-express' || $callback_arr['payment_product'] == 'cb' || $callback_arr['payment_product'] == 'visa' || $callback_arr['payment_product'] == 'mastercard') {
			// Memorize new card only if card used can be "recurring"
			// LOG
			$customer_id = $new_order->id_customer;
			$token = $callback_arr['payment_method']['token'];
			$brand = $callback_arr['payment_method']['brand'];
			$pan = $callback_arr['payment_method']['pan'];
			$card_holder = $callback_arr['payment_method']['card_holder'];
			$card_expiry_month = $callback_arr['payment_method']['card_expiry_month'];
			$card_expiry_year = $callback_arr['payment_method']['card_expiry_year'];
			$issuer = $callback_arr['payment_method']['issuer'];
			$country = $callback_arr['payment_method']['country'];

			$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_tokens`
	                        WHERE `customer_id`='" . (int)$customer_id . "'
	                        AND `token`='" . pSQL($token) . "'";
	        HipayLogger::addLog('SQL', HipayLogger::APICALL, $sql);
			$result = Db::getInstance()->getRow($sql);

			if (!$result['id']) {
				// LOG
        		HipayLog('--------------- Enregistrement nouveau Token');
				// 'insert in DB';
				$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "hipay_tokens` (`customer_id`, `token`, `brand`, `pan`, `card_holder`, `card_expiry_month`, `card_expiry_year`, `issuer`, `country`)
	                VALUES('" . (int)$customer_id . "', '" . pSQL($token) . "', '" . pSQL($brand) . "', '" . pSQL($pan) . "', '" . pSQL($card_holder) . "', '" . pSQL($card_expiry_month) . "', '" . pSQL($card_expiry_year) . "', '" . pSQL($issuer) . "', '" . pSQL($country) . "')";
				Db::getInstance()->execute($sql_insert);
			}
		}
		//LOG
		HipayLog('--------------- currentOrder = '.$hipay->currentOrder);
		$bool = true;
	} else if ($order_exist && $callback_arr['status'] == HIPAY_STATUS_AUTHORIZED) {
		// prepare message for this status HIPAY_STATUS_AUTHORIZED
		$message  = $hipay->l('HiPay - Callback initiated') . "<br>";
		$message .= ' - ' . $hipay->l('Transaction_reference : ') . $callback_arr['transaction_reference'] . "<br>";
		$message .= ' - ' . $hipay->l('State : ') . $callback_arr['state'] . "<br>";
		$message .= ' - ' . $hipay->l('Status : ') . $callback_arr['status'] . "<br>";
		$message .= ' - ' . $hipay->l('Message : ') . $callback_arr['message'] . "<br>";
		$message .= ' - ' . $hipay->l('Amount : ') . $callback_arr['authorized_amount'] . "<br>";
		$message = strip_tags($message, '<br>');
		// LOG
		HipayLog('--------------- MSG : '. $message);
		// prepare payment for this status HIPAY_STATUS_AUTHORIZED
		$amount = $callback_arr['captured_amount']; // Set refund to negative
		$payment_method = 'HiPay Fullservice via ' . (string) ucwords($callback_arr['payment_product']);
		$payment_transaction_id = $callback_arr['transaction_reference'];
		$currency = new Currency($order->id_currency);
		$payment_date = date("Y-m-d H:i:s");
		$order_invoice = null;
		if ($order && Validate::isLoadedObject($order)) {
			// Add order payment 
			if ($order->addOrderPayment($amount, $payment_method, $payment_transaction_id, $currency, $payment_date, $order_invoice)) {
				// LOG
				HipayLog('--------------- Order payment add with success');
				// Add message for this status
				$msg = new Message ();
				if (Validate::isCleanHtml($message)) {
					$msg->message = $message;
					$msg->id_order = (int)$order->id;
					$msg->private = 1;
					$msg->add();
					// LOG
					HipayLog('--------------- MSG register with success');
					$bool = true;
				}
			}
		} else {
			HipayLogger::addLog($hipay->l('Bad Callback Error', 'hipay'), HipayLogger::ERROR, 'Error, order exist but the object order not loaded');
			HipayLog('--------------- Error, order exist but the object order not loaded');
		}
	}
	return $bool;
}
/*
 * Fonction générique pour modifier le status
 * @param1 $orderState 		-> id du statut de commande souhaitée
 * @param2 $id_order   		-> id de la commande à traiter
 * @param3 $orderState   	-> status
 * @param4 $callback_arr   	->  Tableau des données envoyé en POST de TPP
 */
function changeStatusOrder($order_exist, $id_order, $orderState, $order, $callback_arr = ''){
	$bool = false;
	//LOG 
	HipayLog('--------------- Début changeStatusOrder');

	if($order_exist && $id_order){
		//LOG 
		HipayLog('--------------- oderexist && id_order');
		if ((int)$order->getCurrentState() != (int)$orderState && !controleIfStatushistoryExist($id_order, _PS_OS_PAYMENT_, $orderState)){
			//LOG 
			HipayLog('--------------- statut différent');
			$order_history = new OrderHistory();
			//LOG 
			HipayLog('--------------- order_history init');
			$order_history->id_order = $id_order;
			//LOG 
			HipayLog('--------------- order_id init');
			$order_history->changeIdOrderState($orderState, $id_order,true);
			//LOG 
			HipayLog('--------------- changeIdOrderState('.$orderState.','.$id_order.')');
			$order_history->add();

			//LOG 
			HipayLog('--------------- statut changé = '.$orderState);

			$bool = true;
		}
		if(!empty($callback_arr) && isset($callback_arr['status']) && $callback_arr['status'] == HIPAY_STATUS_CAPTURED){
			$hipay = new HiPay_Tpp();
			// historise le callback sous forme de message
			$message  = $hipay->l('HiPay - Callback initiated') . "<br>";
			$message .= ' - ' . $hipay->l('Transaction_reference : ') . $callback_arr['transaction_reference'] . "<br>";
			$message .= ' - ' . $hipay->l('State : ') . $callback_arr['state'] . "<br>";
			$message .= ' - ' . $hipay->l('Status : ') . $callback_arr['status'] . "<br>";
			$message .= ' - ' . $hipay->l('Message : ') . $callback_arr['message'] . "<br>";
			$message .= ' - ' . $hipay->l('Amount : ') . $callback_arr['authorized_amount'] . "<br>";
			$message = strip_tags($message, '<br>');
			if (Validate::isCleanHtml($message)) {
				$msg = new Message ();
				$msg->message = $message;
				$msg->id_order = (int)$order->id;
				$msg->private = 1;
				$msg->add();
			}
		}
		// Init / MAJ de la ligne message HIPAY_CAPTURE
		addHipayCaptureMessage($callback_arr,$order->id);

		//LOG
		HipayLog('--------------- statut est le même');

		// Controle if it's again a partially captured and inferior to the total order

		//LOG 
		HipayLog('--------------- Controle si status 117 et amount > amount captured');

		if(!empty($callback_arr) 
			&& isset($callback_arr['status']) 
				&& $callback_arr['status'] == HIPAY_STATUS_CAPTURE_REQUESTED
			 		&& $callback_arr['captured_amount'] < $callback_arr['authorized_amount']){
			//LOG 
			HipayLog('--------------- captured_amount ('.$callback_arr['captured_amount'].') est < à authorized_amount ('.$callback_arr['authorized_amount'].')');
			$bool = true;
		}

		return $bool;
	}
	//LOG 
	HipayLog('--------------- pas de changement de statut car pas de commande');
	return $bool;
}
/*
 * Fonction pour tracer la transaction dans la commande
 * @param1 $callback_arr -> données remontées par TPP
 * @param2 $order        -> la commande instanciée
 */
function captureOrder($callback_arr = null, $order = null) {

	//LOG 
	HipayLog('--------------- Début captureOrder');
	$hipay = new HiPay_Tpp();
	// Local Cards update
	$local_card_name = ''; // Initialize to empty string
	if ($callback_arr['payment_product'] != '') {
		// Add the card name
		$local_card_name = ' via ' . (string) ucwords($callback_arr['payment_product']);
		// Retrieve xml list
		if (file_exists(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml')) {
			$local_cards = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/' . $hipay->name . '/special_cards.xml');
			// If cards exists
			if (isset($local_cards)) {
				// If cards count > 0
				if (count($local_cards)) {
					// Go through each card
					foreach ($local_cards as $value) {
						// If card code value = payment_product value
						if ((string) $value->code == trim($callback_arr['payment_product'])) {
							// Add the card name
							$local_card_name = ' via ' . (string) $value->name;
						}
					}
				}
			}
		}
	}

	// On met à jour la ligne transaction / paiement de la commande
	// création de la transaction
	if (isset($callback_arr['payment_method']['token'])) {
		$sql = "
				UPDATE `" . _DB_PREFIX_ . "order_payment`
                    SET `card_number` = '" . $callback_arr['payment_method']['pan'] . "',
	                    `amount` = '" . $callback_arr['captured_amount'] . "',
	                    `transaction_id` = '" . $callback_arr['transaction_reference'] . "',
	                    `card_brand` = '" . $callback_arr['payment_method']['brand'] . "',
	                    `card_expiration` = '" . $callback_arr['payment_method']['card_expiry_month'] . "/" . $callback_arr['payment_method']['card_expiry_year'] . "',
	                    `card_holder` = '" . $callback_arr['payment_method']['card_holder'] . "'
                    WHERE 
                    	`payment_method` = '" . 'HiPay Fullservice' . $local_card_name . "'
                    AND `order_reference`= '" . $order->reference . "';";
        
        if(!Db::getInstance()->execute($sql)){
        	//LOG 
			HipayLog('--------------- Update en erreur');
			return false;
        }
	
		// Check if there is a duplicated OrderPayment and remove duplicate from same order ref but with incomplete payment method name
		$sql_duplicate_order_payment = "
			DELETE FROM `" . _DB_PREFIX_ . "order_payment` 
			WHERE 
				payment_method='HiPay Fullservice' 
				AND transaction_id='' 
				AND order_reference='" . $order->reference . "'
			;";

		Db::getInstance()->execute($sql_duplicate_order_payment);

		// set invoice order
		if ($callback_arr['status'] == HIPAY_STATUS_CAPTURE_REQUESTED || $callback_arr['status'] == HIPAY_STATUS_CAPTURED) {
			$sql = 'SELECT `id_order_payment`
				FROM `'._DB_PREFIX_.'order_payment`
				WHERE order_reference="' . pSQL($order->reference) . ' LIMIT 1";';
	        $result = Db::getInstance()->getRow($sql);
	        $id_orderP = isset($result['id_order_payment']) ? $result['id_order_payment'] : false;
			if($id_orderP){
				$sql_update = "
					UPDATE `" . _DB_PREFIX_ . "order_invoice_payment`
                    SET `id_order_payment` = " . (int)$id_orderP . "
                    WHERE `id_order` = " . (int)$order->id;
				Db::getInstance()->execute($sql_update);
			}
		}

		//LOG 
		HipayLog('--------------- delete transaction pas invoice = '. $sql_duplicate_order_payment);

		// init message pour création de commande
		$message = $hipay->l('Transaction Reference:') . ' ' . $callback_arr['transaction_reference'] . '<br />
            ' . $hipay->l('State:') . ' ' . $callback_arr['state'] . '<br />
            ' . $hipay->l('Status:') . ' ' . $callback_arr['status'] . '<br />
            ' . $hipay->l('Message:') . ' ' . $callback_arr['message'] . '<br />
            ' . $hipay->l('Data:') . ' ' . $callback_arr['cdata1'] . '<br />
            ' . $hipay->l('Amount : ') . $callback_arr['authorized_amount'] . '<br />
            ' . $hipay->l('Payment mean:') . ' ' . $callback_arr['payment_product'] . '<br />
            ' . $hipay->l('Payment has began at:') . ' ' . $callback_arr['date_created'] . '<br />
            ' . $hipay->l('Payment received at:') . ' ' . $callback_arr['date_authorized'] . '<br />
            ' . $hipay->l('authorization Code:') . ' ' . $callback_arr['authorization_code'] . '<br />
            ' . $hipay->l('Currency:') . ' ' . $callback_arr['currency'] . '<br />
            ' . $hipay->l('Customer IP address:') . ' ' . $callback_arr['ip_address'];
        
        //LOG 
		HipayLog('--------------- MESSAGE = '. $message);
		$message = strip_tags($message, '<br>');
		if (Validate::isCleanHtml($message)) {
			$msg = new Message ();
			$msg->message = $message;
			$msg->id_order = (int)$order->id;
			$msg->private = 1;
			$msg->add();

			//LOG 
			HipayLog('--------------- addMessage on captureOrder - ' . $message);
		}
    }
    if($callback_arr)
	//LOG 
	HipayLog('--------------- Fin captureOrder');
	HipayLog('--------------- --------------- --------------- --------------- ');
	HipayLog('--------------- --------------- --------------- --------------- ');

	return true;
}
/*
 * fonction permettant d'enregistrer le remboursement
 * @param1 $callback_arr -> données remontées par TPP
 * @param2 $order        -> la commande instanciée
 * @param3 $hipay 		 -> Module hipay instancié
 * @param4 $statut 		 -> Statut de la commande
 */
function refundOrder($callback_arr, $order, $hipay, $statut) {
	
	//LOG 
	HipayLog('--------------- Début refundOrder'); 

	// Modif to update payment if refund has already been made once.
	$payment_message_sql = "SELECT * FROM `" . _DB_PREFIX_ . "order_payment` WHERE payment_method='HiPay - refund' AND order_reference='" . pSQL($order->reference) . "'";
	
	HipayLog('--------------- SQL Si déjà un refund = '. $payment_message_sql);

	$paymentmessage = Db::getInstance()->executeS($payment_message_sql);
	if (!empty($paymentmessage)) {

		// LOG
		HipayLog('--------------- Refund existant donc procédure update');

		// Set refund to negative
		$amount = - 1 * $callback_arr['refunded_amount'];

		// LOG
		HipayLog('--------------- Montant à refund = '.$amount);

		// Update existing payment method
		$sql = "UPDATE `" . _DB_PREFIX_ . "order_payment`
				SET `amount` = '" . $amount . "'
				WHERE `payment_method`='HiPay - refund' AND `order_reference`='" . $order->reference . "'";
		if (!Db::getInstance()->execute($sql)) {
			// Ajout commentaire status KO
			$msg = new Message ();
			$message = $hipay->l('HiPay - Refund failed.');
			$message .= ' - ' . $hipay->l('Amount refunded failed =') . ' ' . $amount;
			$message = strip_tags($message, '<br>');
			if (Validate::isCleanHtml($message)) {
				$msg->message = $message;
				$msg->id_order = (int)$order->id;
				$msg->private = 1;
				$msg->add();
			}
		} else {
			$order_id = $order->id;
			$tag = 'HIPAY_CAPTURE ';
			// amount est déjà négatif
			$amount_rest = $callback_arr['captured_amount'] + ($amount);
			$msgs = Message::getMessagesByOrderId($order_id, true); 
			//true for private messages (got example from AdminOrdersController)

			if (count($msgs)) {
				foreach ($msgs as $msg) {
					$line = $msg['message'];
					if (startsWith($line, $tag)) {
						$to_update_msg = new Message($msg['id_message']);
						$to_update_msg->message = $tag . $amount_rest;
						$to_update_msg->save();
						break;
					}
				}
			}
		}
	} else {

		// LOG
		HipayLog('--------------- Nouveau Refund donc procédure insert');

		$amount = - 1 * $callback_arr['refunded_amount']; // Set refund to negative
		$payment_method = 'HiPay - refund';
		$payment_transaction_id = $callback_arr['transaction_reference'];
		$currency = new Currency($order->id_currency);
		$payment_date = date("Y-m-d H:i:s");
		$order_has_invoice = $order->hasInvoice();
		if ($order_has_invoice)
			$order_invoice = new OrderInvoice(Tools::getValue('payment_invoice'));
		else
			$order_invoice = null;

		if (!$order->addOrderPayment($amount, $payment_method, $payment_transaction_id, $currency, $payment_date, $order_invoice)) {
			// Ajout commentaire status KO
			$msg = new Message ();
			$message = $hipay->l('HiPay - Refund failed.');
			$message .= ' - ' . $hipay->l('Amount refunded failed =') . ' ' . $amount;
			$message = strip_tags($message, '<br>');
			if (Validate::isCleanHtml($message)) {
				$msg->message = $message;
				$msg->id_order = (int)$order->id;
				$msg->private = 1;
				$msg->add();
			}
		} else {
			$order_id = $order->id;
			$tag = 'HIPAY_CAPTURE ';
			// amount est déjà en négatif
			$amount_rest = $callback_arr['captured_amount'] + ($amount);
			$msgs = Message::getMessagesByOrderId($order_id, true); 
			//true for private messages (got example from AdminOrdersController)

			if (count($msgs)) {
				foreach ($msgs as $msg) {
					$line = $msg['message'];
					if (startsWith($line, $tag)) {
						$to_update_msg = new Message($msg['id_message']);
						$to_update_msg->message = $tag . $amount_rest;
						$to_update_msg->save();
						break;
					}
				}
			}
		}
	}

	// Force le statut refunded 
	$sql_status = "UPDATE `" . _DB_PREFIX_ . "orders`
			SET current_state =  " . $statut . "
			WHERE id_order = ".$order->id.";";

	//LOG
	HipayLog('--------------- SQL Update Order = ' . $sql_status); 

	if(!Db::getInstance()->execute($sql_status)){
		//LOG 
		HipayLog('--------------- Update statut = '.$statut.' en erreur pour la commande = '.$order->id);
		return false;
	} 
	//LOG
	HipayLog('--------------- SQL Update Order OK'); 	
	HipayLog('--------------- fin refundOrder'); 

	return true;
}
/*
 * fonction permettant d'enregistrer le montant de la capture
 * @param1 $callback_arr -> données remontées par TPP
 * @param2 $id_order     -> ID de la commande
 */
function addHipayCaptureMessage($callback_arr,$id_order){
	//LOG
	HipayLog('--------------- Début addHipayCaptureMessage');
	// Init / MAJ de la ligne message HIPAY_CAPTURE
	$tag = 'HIPAY_CAPTURE ';
	$amount = ((int)$callback_arr['status'] == HIPAY_STATUS_AUTHORIZED ? '0.00' : (!empty($callback_arr['captured_amount']) ? $callback_arr['captured_amount']:'0.00'));
	$msgs = Message::getMessagesByOrderId((int)$id_order, true);
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
			$msg->id_order = (int)$id_order;
			$msg->private = 1;
			$msg->add();
		}
	}
	//LOG
	HipayLog('--------------- Fin addHipayCaptureMessage');
}
/*
 * fonction permettant de contrôler si il y a déjà eu un statut paiement accepté
 * @param1 $id_order 	-> ID de la commande
 * @param2 $stt_payment -> ID du statut paiement accepté
 * @param3 $forceCtrl	-> Si true on vérifie par tout les moyens si 118 déjà effectué
 */
function controleIfStatushistoryExist($id_order, $stt_payment, $orderState, $forceCtrl = false){
	//LOG
	HipayLog('--------------- Début contrôle si déjà eu paiement accepté');

	$bool = false;
	$bGoGo= false;

	if( $orderState == $stt_payment || $forceCtrl ) {
		$bGoGo = true;
		//LOG
		HipayLog('--------------- Control action = TRUE');
	}

	if($bGoGo && $id_order){
		$sql = 'SELECT COUNT(id_order_history) as count
				FROM `'._DB_PREFIX_.'order_history`
				WHERE `id_order` = '.(int)$id_order.' AND id_order_state = '.(int)$stt_payment;

		//LOG
		HipayLog('--------------- SQL de contrôle = ' . $sql);

	    $result = Db::getInstance()->getRow($sql);
	    if( isset($result['count']) && $result['count'] > 0 ){
	    	$bool = true;
	    }
	} 

    //LOG
    HipayLog('--------------- Statut traité => ' . $orderState);
    HipayLog('--------------- Résultat - Existe déjà ? => ' . ($bool ? 'TRUE':'FALSE'));
	HipayLog('--------------- Fin contrôle si déjà eu paiement accepté');

    return $bool;
}


function startsWith($haystack, $needle) {
	return $needle === "" || strpos($haystack, $needle) === 0;
}
#
# fonction qui log le script pour debug
#
function HipayLog($msg){
	if(HIPAY_LOG){
		$fp = fopen(_PS_ROOT_DIR_.'/modules/hipay_tpp/hipaylogs.txt','a+');
        fseek($fp,SEEK_END);
        fputs($fp,$msg."\r\n");
        fclose($fp);
	}        
}