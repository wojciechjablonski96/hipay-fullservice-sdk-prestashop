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
class HiPay_TppAcceptModuleFrontController extends ModuleFrontController
{
    /**
     *
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        // Disconnect User from cart
        HipayClass::unsetCart();
        // récupération des informations en GET ou POST venant de la page de paiement
        $cart_id = Tools::getValue('orderId');
        $transac = Tools::getValue('reference');
        $token = Tools::getValue('token');
        $context = Context::getContext();
        $hipay = new HiPay_Tpp();

        $this->HipayLog('##############################');
        $this->HipayLog('## ACCEPT.PHP - INFO getValue');
        $this->HipayLog('## cart_id ' . $cart_id );
        $this->HipayLog('## transaction ' . $transac );
        $this->HipayLog('## token ' . $token );
        $this->HipayLog('##############################');

        // --------------------------------------------------------------------------
        // vérification si les informations ne sont pas = à FALSE
        if (!$cart_id) {
            // récupération du dernier panier via son compte client
            $sql = 'SELECT `id_cart`
					FROM `' . _DB_PREFIX_ . 'cart`
					WHERE `id_customer` = ' . $context->customer->id . '
					ORDER BY date_upd DESC';
            $result = Db::getInstance()->getRow($sql);
            $cart_id = isset($result['id_cart']) ? $result['id_cart'] : false;

            $this->HipayLog('##############################');
            $this->HipayLog('## // récupération du dernier panier via son compte client ' . $cart_id);
            $this->HipayLog('##############################');
        }

        // LOCK SQL
        #################################################################
        $sql = 'begin;';
        $sql .= 'SELECT id_cart FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int)$cart_id . ' FOR UPDATE;';
        if (!Db::getInstance()->execute($sql)) {
            HipayLogger::addLog($hipay->l('Bad LockSQL initiated', 'hipay'), HipayLogger::ERROR,
                'Bad LockSQL initiated, Lock could not be initiated for id_cart = ' . $cart_id);
            die('Lock not initiated');
        } else {
            $this->HipayLog('##############################');
            $this->HipayLog('## LOCK ON for the cart ' . $cart_id);
            $this->HipayLog('##############################');
        }

        // load cart
        $objCart = new Cart((int)$cart_id);

        //check request integrity
        if ($token != HipayClass::getHipayToken($objCart->id)) {
            HipayLogger::addLog('# Wrong token on payment validation');
            $redirectUrl = $context->link->getModuleLink(
                $this->module->name,
                'exception',
                array('status_error' => 405),
                true
            );
            Tools::redirect($redirectUrl);
        }

        // load order for id_order
        $order_id = Order::getOrderByCartId($cart_id);

        $this->HipayLog('##############################');
        $this->HipayLog('## Order ID ' . $order_id );
        $this->HipayLog('##############################');

        $customer = new Customer((int)$objCart->id_customer);
        if ($order_id && !empty($order_id) && $order_id > 0) {
            // load transaction by id_order
            $sql = 'SELECT DISTINCT(op.transaction_id)
					FROM `' . _DB_PREFIX_ . 'order_payment` op
					INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.reference = op.order_reference
					WHERE o.id_order = ' . $order_id;
            $result = Db::getInstance()->getRow($sql);

            $this->HipayLog('##############################');
            $this->HipayLog('## Order exist, sql execute =  ' . $sql );
            $this->HipayLog('##############################');
        } else {
            $shop_id = $objCart->id_shop;
            $shop = new Shop($shop_id);
            // forced shop
            Shop::setContext(Shop::CONTEXT_SHOP, $objCart->id_shop);

            $this->HipayLog('##############################');
            $this->HipayLog('## Order not exist');
            $this->HipayLog('## shop_id =  ' . $shop_id );
            $this->HipayLog('##############################');
            $this->HipayLog('##############################');
            $this->HipayLog('## start validate order');
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
            $this->HipayLog('## End validate order');
            $this->HipayLog('##############################');
            // get order id
            $order_id = $hipay->currentOrder;

            $this->HipayLog('##############################');
            $this->HipayLog('## Order ID after validate order =  ' . $order_id );
            $this->HipayLog('##############################');

            // transaction table Hipay
            $sql = "
            INSERT INTO `" . _DB_PREFIX_ . "hipay_transactions`
            (`cart_id`,`order_id`,`customer_id`,`transaction_reference`, `device_id`, `ip_address`, `ip_country`, `token`) VALUES
            ('" . (int)$cart_id . "',
                '" . (int)$order_id . "',
                '" . (int)$customer->id . "',
                '" . pSQL($transac) . "',0,0,0,0);";
            if (!Db::getInstance()->execute($sql)) {
                //LOG
                HipayLogger::addLog('Insert table HiPay transactions error');
            } else {
                $this->HipayLog('##############################');
                $this->HipayLog('## SQL hipay transaction =  ' . $sql );
                $this->HipayLog('##############################');
            }

        }

        // commit lock SQL
        $sql = 'commit;';
        if (!Db::getInstance()->execute($sql)) {
            HipayLogger::addLog($hipay->l('Bad LockSQL initiated', 'hipay'), HipayLogger::ERROR,
                'Bad LockSQL end, Lock could not be end for id_cart = ' . $cart_id);
        }

        $this->HipayLog('##############################');
        $this->HipayLog('## LOCK OFF for the cart ' . $cart_id);
        $this->HipayLog('##############################');

        $transaction = isset($result['transaction_id']) ? $result['transaction_id'] : (int)$transac;
        $context->smarty->assign(array(
            'id_order' => $order_id,
            'total' => $objCart->getOrderTotal(true),
            'transaction' => $transaction,
            'currency' => $context->currency->iso_code,
            'email' => $context->customer->email
        ));
        Hook::exec('displayHiPayAccepted', array('cart' => $objCart, "order_id" => $order_id));
        $this->setTemplate('payment_accept.tpl');
    }

    #
    # fonction qui log le script pour debug
    #
    private function HipayLog($msg){
        $fp = fopen(_PS_ROOT_DIR_.'/modules/hipay_tpp/hipaylogs.txt','a+');
        fseek($fp,SEEK_END);
        fputs($fp,$msg."\r\n");
        fclose($fp);
    }
}
