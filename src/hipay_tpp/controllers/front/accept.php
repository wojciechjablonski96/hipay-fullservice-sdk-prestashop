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
class HiPay_TppAcceptModuleFrontController extends ModuleFrontController {
	/**
	 *
	 * @see FrontController::postProcess()
	 */
	public function postProcess() {
		// Disconnect User from cart
        HipayClass::unsetCart();	
    	// récupération des informations en GET ou POST venant de la page de paiement
    	$cart_id 		= Tools::getValue('orderId');
    	$transac 		= Tools::getValue('reference');
    	$context 		= Context::getContext();
        $hipay = new HiPay_Tpp();
    	// --------------------------------------------------------------------------
    	// vérification si les informations ne sont pas = àgit status FALSE
    	if(!$cart_id){
    		// récupération du dernier panier via son compte client
    		$sql = 'SELECT `id_cart`
					FROM `'._DB_PREFIX_.'cart`
					WHERE `id_customer` = '.$context->customer->id .'
					ORDER BY date_upd DESC';
	        $result = Db::getInstance()->getRow($sql);
	        $cart_id = isset($result['id_cart']) ? $result['id_cart'] : false;
			if($cart_id){
				$objCart = new Cart((int)$cart_id);
			}
    	}else{
    		// load cart
    		$objCart = new Cart((int)$cart_id);
    	}
		// LOCK SQL
		#################################################################
		$sql = 'begin;';
		$sql .= 'SELECT id_cart FROM '._DB_PREFIX_.'cart WHERE id_cart = '. (int)$cart_id .' FOR UPDATE;';
		if (!Db::getInstance()->execute($sql)){
			HipayLogger::addLog($hipay->l('Bad LockSQL initiated', 'hipay'), HipayLogger::ERROR, 'Bad LockSQL initiated, Lock could not be initiated for id_cart = ' . $cart_id);
			die('Lock not initiated');
		}

    	// load order for id_order 
    	$order_id = Order::getOrderByCartId($cart_id);
    	if($order_id && !empty($order_id) && $order_id > 0 ){
	    	// load transaction by id_order
	    	$sql = 'SELECT DISTINCT(op.transaction_id)
					FROM `'._DB_PREFIX_.'order_payment` op
					INNER JOIN `'._DB_PREFIX_.'orders` o ON o.reference = op.order_reference
					WHERE o.id_order = '.$order_id;
	        $result = Db::getInstance()->getRow($sql);
	    } else {
			$customer = new Customer((int)$objCart->id_customer);
			$shop_id = $objCart->id_shop;
			$shop = new Shop($shop_id);
			// forced shop
			Shop::setContext(Shop::CONTEXT_SHOP,$objCart->id_shop);
			$hipay->validateOrder(
				(int)$cart_id, 
				Configuration::get('HIPAY_PENDING'), 
				(float)$objCart->getOrderTotal(true), 
				$hipay->displayName, 
				'Order created by HiPay after success payment.', 
				array(), 
				$context->currency->id, 
				false, 
				$customer->secure_key,
		  		$shop
			);
			// get order id
			$order_id = $hipay->currentOrder;
		}
		// commit lock SQL
		$sql = 'commit;';
		if (!Db::getInstance()->execute($sql)) {
			HipayLogger::addLog($hipay->l('Bad LockSQL initiated', 'hipay'), HipayLogger::ERROR, 'Bad LockSQL end, Lock could not be end for id_cart = ' . $cart_id);
		}
        $transaction = isset($result['transaction_id']) ? $result['transaction_id'] : 0;
        $context->smarty->assign(array(
            'id_order' 		=> $order_id,
            'total' 		=> $objCart->getOrderTotal(true),
            'transaction' 	=> $transaction,
            'currency' 		=> $context->currency->iso_code,
            'email'			=> $context->customer->email
        ));
        Hook::exec('displayHiPayAccepted', ['cart' => $objCart, "order_id" => $order_id]);
        $this->setTemplate ( 'payment_accept.tpl' );
	}
}