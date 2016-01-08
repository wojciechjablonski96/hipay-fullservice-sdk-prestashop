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
        // block 5s because
    	sleep(5);	
    	// récupération des informations en GET ou POST venant de la page de paiement
    	$cart_id 		= Tools::getValue('orderId');
    	$transac 		= Tools::getValue('reference'); 
    	$context 		= Context::getContext();
    	// --------------------------------------------------------------------------
    	// vérification si les informations ne sont pas = à FALSE
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
    	// load order for id_order 
    	$order_id = Order::getOrderByCartId($cart_id);
    	if($order_id && !empty($order_id) && $order_id > 0 ){
	    	// load transaction by id_order
	    	$sql = 'SELECT DISTINCT(op.transaction_id)
					FROM `'._DB_PREFIX_.'order_payment` op
					INNER JOIN `'._DB_PREFIX_.'orders` o ON o.reference = op.order_reference
					WHERE o.id_order = '.$order_id;
	        $result = Db::getInstance()->getRow($sql);
	    }
        $transaction = isset($result['transaction_id']) ? $result['transaction_id'] : 0;
    	$context->smarty->assign(array(
			'id_order' 		=> $order_id,
			'total' 		=> $objCart->getOrderTotal(true),
			'transaction' 	=> $transaction,
			'currency' 		=> $context->currency->iso_code,
			'email'			=> $context->customer->email
		));
                   
		$this->setTemplate ( 'payment_accept.tpl' );
	}
}