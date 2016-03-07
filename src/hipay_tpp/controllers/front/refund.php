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
define('HIPAY_LOG',1);


class hipay_TpprefundModuleFrontController extends ModuleFrontController {
	/**
	 *
	 * @see FrontController::postProcess()
	 */
	public function postProcess() {

		$this->HipayLog('####################################################');
		$this->HipayLog('# Début demande de remboursement partiel ou complète');
		$this->HipayLog('####################################################');

		$context = Context::getContext ();
		$hipay = new HiPay_Tpp ();
		$hipay_redirect_status = 'ok';

		$this->HipayLog('-- context et hipay sont init');
		
		// If id_order is sent, we instanciate a new Order object
		if (Tools::isSubmit ( 'id_order' ) && Tools::getValue ( 'id_order' ) > 0) {
			$this->HipayLog('--------------------------------------------------');
			$this->HipayLog('-- init de la commande = '.Tools::getValue ( 'id_order' ));

			$order = new Order ( Tools::getValue ( 'id_order' ) );
			if (! Validate::isLoadedObject ( $order ))
				throw new PrestaShopException ( 'Can\'t load Order object' );
			if (version_compare(_PS_VERSION_, '1.5.6', '>')){

				$this->HipayLog('---- init du shop si version > à la 1.5.6 = '.$order->id_shop);

				ShopUrl::cacheMainDomainForShop ( ( int ) $order->id_shop );
			} 
			if (Tools::isSubmit ( 'id_emp' ) && Tools::getValue ( 'id_emp' ) > 0) {
				$id_employee = Tools::getValue ( 'id_emp' );
			} else {
				$id_employee = '1';
			}
			$this->HipayLog('---- init id_emp = '.$id_employee);
			$this->HipayLog('--------------------------------------------------');
		}
		if (Tools::isSubmit ( 'hipay_refund_type' )) {

			$this->HipayLog('--------------------------------------------------');

            $refund_type = Tools::getValue ( 'hipay_refund_type' );
            $refund_amount = Tools::getValue ( 'hipay_refund_amount' );
            $refund_amount = str_replace(' ', '', $refund_amount);
            $refund_amount = floatval ( str_replace ( ',', '.', $refund_amount ) );

            $this->HipayLog('-- init refund_type = '.$refund_type);
            $this->HipayLog('-- init refund_amount = '.$refund_amount);

            $this->HipayLog('--------------------------------------------------');

		}
                
        // First check
        if ( Tools::isSubmit('hipay_refund_submit') &&  $refund_type == 'partial' ) 
        {

        	$this->HipayLog('--------------------------------------------------');
        	$this->HipayLog('-- Début Refund_submit & partiel');

            $hipay_redirect_status = false;
            $hipay = new HiPay_Tpp();
            $orderLoaded = new OrderCore(Tools::getValue('id_order'));
            $orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;

            $this->HipayLog('---- Init id_order = ' . Tools::getValue('id_order'));
            $this->HipayLog('---- Init orderTotal => '. $orderTotal . ' = '. $orderLoaded->total_products_wt .' + '. $orderLoaded->total_shipping_tax_incl .' + '. $orderLoaded->total_wrapping_tax_incl);
            // patch de compatibilité
            if (_PS_VERSION_ < '1.5') {
            	$id_or_reference = $orderLoaded->id;
            }else{
            	$id_or_reference = $orderLoaded->reference;
            }
            $this->HipayLog('---- PS_VERSION = '. _PS_VERSION_);
            $this->HipayLog('---- id_or_reference = '. $id_or_reference);

            $totalEncaissement = $hipay->getOrderTotalAmountCaptured($id_or_reference);

            $this->HipayLog('---- totalEncaissement = '. $totalEncaissement);
			// -----------------------

            if(!$refund_amount)
            {
                $hipay_redirect_status = $hipay->l('Please enter an amount','refund');

                $url = Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $orderLoaded->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay';
                $this->HipayLog('---- Init URL pour redirectAdmin - refund_amount = ' . $url);
                $this->HipayLog('--------------------------------------------------');

                Tools::redirectAdmin ( $url );
                die('');
            }
            if($refund_amount<0)
            {
                $hipay_redirect_status = $hipay->l('Please enter an amount greater than zero','refund');
                
                $url = Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $orderLoaded->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay';
                $this->HipayLog('---- Init URL pour redirectAdmin - refund_amount = ' . $url);
                $this->HipayLog('--------------------------------------------------');

                Tools::redirectAdmin ( $url );
                die('');
            }
            if($refund_amount>$totalEncaissement)
            {
                $hipay_redirect_status = $hipay->l('Amount exceeding authorized amount','refund');
                                
                $url = Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $orderLoaded->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay';
                $this->HipayLog('---- Init URL pour redirectAdmin - refund_amount = ' . $url);
                $this->HipayLog('--------------------------------------------------');

                Tools::redirectAdmin ( $url );
                die('');
            }
			if(!is_numeric($refund_amount))
			{
				$hipay_redirect_status = $hipay->l('Please enter an amount','refund');
                                
                $url = Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $orderLoaded->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay';
                $this->HipayLog('---- Init URL pour redirectAdmin - refund_amount = ' . $url);
                $this->HipayLog('--------------------------------------------------');

                Tools::redirectAdmin ( $url );
                die('');
			}

			$this->HipayLog('--------------------------------------------------');

        }
				
		if (Tools::isSubmit ( 'hipay_refund_submit' ) && isset ( $order )) {

			$this->HipayLog('--------------------------------------------------');

			$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_transactions` WHERE `cart_id`='" . ( int ) $order->id_cart . "'";

			$this->HipayLog('-- SQL hipay refund submit & isset order = ' . $sql);

			$result = Db::getInstance ()->getRow ( $sql );
			$reference = $result ['transaction_reference'];

			$this->HipayLog('-- Transaction reference = ' . $reference);
			$this->HipayLog('---- type = ' . $refund_type);

			if ($refund_type == 'complete') {
				// Appel HiPay
				$data = HipayMaintenance::getMaintenanceData ( 'refund', '0' );
				$response = HipayMaintenance::restMaintenanceApi ( $reference, $data, (int)$order->id_shop );

				// Ajout commentaire
				$msg = new Message ();
				$message = 'HiPay - Complete refund requested to HiPay.';
				$message = strip_tags ( $message, '<br>' );

				$this->HipayLog('---- message = ' . $message);

				if (Validate::isCleanHtml ( $message )) {
					$msg->message = $message;
					$msg->id_order = intval ( $order->id );
					$msg->private = 1;
					$msg->add ();
				}
			} else {
				// 'partial';
				// Appel HiPay
				$this->HipayLog('---- Partiel ');
				/**
				 * VERIFICATION
				 */
				$orderTotal = $order->total_products_wt + $order->total_shipping_tax_incl + $order->total_wrapping_tax_incl;

				$this->HipayLog('---- OrderTotal = '. $order->total_products_wt .'+'. $order->total_shipping_tax_incl .'+'. $order->total_wrapping_tax_incl);
				// patch de compatibilité
	            if (_PS_VERSION_ < '1.5') {
	            	$id_or_reference = $order->id;
	            }else{
	            	$id_or_reference = $order->reference;
	            }

	            $this->HipayLog('---- PS_VERSION = '. _PS_VERSION_);
            	$this->HipayLog('---- id_or_reference = '. $id_or_reference);

	            $totalEncaissement = $this->module->getOrderTotalAmountCaptured($id_or_reference);
				// -----------------------
				
	            $this->HipayLog('---- totalEncaissement = '. $totalEncaissement);

				if ($totalEncaissement < $refund_amount) {
					$hipay_redirect_status = $hipay->l('Error, you are trying to refund an amount that is more than the amount captured', 'refund');

					$this->HipayLog('---- Error = '. $hipay_redirect_status);

				} else {
					$data = HipayMaintenance::getMaintenanceData ( 'refund', $refund_amount );
                                        
					$response = HipayMaintenance::restMaintenanceApi ( $reference, $data, (int)$order->id_shop );

					// Ajout commentaire
					$msg = new Message ();
					$message = 'HIPAY_REFUND_REQUESTED ' . $refund_amount;
					$message = strip_tags ( $message, '<br>' );

					$this->HipayLog('---- Message = '. $message);

					if (Validate::isCleanHtml ( $message )) {
						$msg->message = $message;
						$msg->id_order = intval ( $order->id );
						$msg->private = 1;
						$msg->add ();
					}

					$hipay_redirect_status = 'ok';

					$this->HipayLog('---- Redirect status = '. $hipay_redirect_status);
				}
			}
		} else {

			$hipay_redirect_status = $hipay->l('You do not have permission to do this.','refund');

			$this->HipayLog('---- Error = '. $hipay_redirect_status);
		}
		
		$this->HipayLog('####################################################');
		$this->HipayLog('# Fin demande de remboursement partiel ou complète');
		$this->HipayLog('####################################################');
			
		Tools::redirectAdmin ( Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $order->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay' );
	}

	#
	# fonction qui log le script pour debug
	#
	public function HipayLog($msg){
		if(HIPAY_LOG){
			$fp = fopen(_PS_ROOT_DIR_.'/modules/hipay_tpp/hipay_refund_logs.txt','a+');
	        fseek($fp,SEEK_END);
	        fputs($fp,$msg."\r\n");
	        fclose($fp);
		}        
	}
}
