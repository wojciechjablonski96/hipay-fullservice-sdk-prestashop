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
class hipay_TppcaptureModuleFrontController extends ModuleFrontController {
	/**
	 *
	 * @see FrontController::postProcess()
	 */
	public function postProcess() {
		$context = Context::getContext ();
		$hipay = new HiPay_Tpp ();
		$hipay_redirect_status = 'ok';
		$errs = false;
		
		// If id_order is sent, we instanciate a new Order object
		if (Tools::isSubmit ( 'id_order' ) && Tools::getValue ( 'id_order' ) > 0) {
			$order = new Order ( Tools::getValue ( 'id_order' ) );
			if (! Validate::isLoadedObject ( $order ))
				throw new PrestaShopException ( 'Can\'t load Order object' );
			if (version_compare(_PS_VERSION_, '1.5.6', '>')) ShopUrl::cacheMainDomainForShop ( ( int ) $order->id_shop );
			if (Tools::isSubmit ( 'id_emp' ) && Tools::getValue ( 'id_emp' ) > 0) {
				$id_employee = Tools::getValue ( 'id_emp' );
			} else {
				$id_employee = '1';
			}
		}
		if (Tools::isSubmit ( 'hipay_capture_type' )) {
			$refund_type = Tools::getValue ( 'hipay_capture_type' );
			$refund_amount = Tools::getValue ( 'hipay_capture_amount' );
			$refund_amount = str_replace(' ', '', $refund_amount);
			$refund_amount = floatval ( str_replace ( ',', '.', $refund_amount ) );
		}

                // First check
                if (Tools::isSubmit ( 'hipay_capture_submit' ) &&  $refund_type == 'partial' ) 
                {
                    $hipay_redirect_status = false;
                    $hipay = new HiPay_Tpp();
                    $orderLoaded = new OrderCore(Tools::getValue('id_order'));
                    $orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;
                    // patch de compatibilité
		            if (_PS_VERSION_ < '1.5') {
		            	$id_or_reference = $orderLoaded->id;
		            }else{
		            	$id_or_reference = $orderLoaded->reference;
		            }
		            $totalEncaissement = $hipay->getOrderTotalAmountCaptured($id_or_reference);
					// -----------------------

                    
                    $stillToCapture = floatval($orderTotal - $totalEncaissement);

					
                    if(!$refund_amount)
                    {
                        $hipay_redirect_status = $hipay->l('Please enter an amount','capture');
                        Tools::redirectAdmin ( Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $order->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_err=' . $hipay_redirect_status . '#hipay' );
                        die('');
                    }
                    if($refund_amount<0)
                    {
                        $hipay_redirect_status = $hipay->l('Please enter an amount greater than zero','capture');
                        Tools::redirectAdmin ( Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $order->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_err=' . $hipay_redirect_status . '#hipay' );
                        die('');
                    }
                    if(round($refund_amount,2)>round($stillToCapture,2))
                    {
                        $hipay_redirect_status = $hipay->l('Amount exceeding authorized amount','capture');
                        Tools::redirectAdmin ( Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $order->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_err=' . $hipay_redirect_status . '#hipay' );
                        die('');
                    }
					
					if(!is_numeric($refund_amount))
                    {
                        $hipay_redirect_status = $hipay->l('Please enter an amount','capture');
                        Tools::redirectAdmin ( Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $order->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_err=' . $hipay_redirect_status . '#hipay' );
                        die('');
                    }
                }
                
		if (Tools::isSubmit ( 'hipay_capture_submit' ) && isset ( $order ) && $errs == false) {
			$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_transactions` WHERE `cart_id`='" . ( int ) $order->id_cart . "'";
			$result = Db::getInstance ()->getRow ( $sql );
			$reference = $result ['transaction_reference'];
			if ($refund_type == 'complete') {
				// Appel HiPay
				$data = HipayMaintenance::getMaintenanceData ( 'capture', '0' );
				$response = HipayMaintenance::restMaintenanceApi ( $reference, $data, (int)$order->id_shop );
				// Ajout commentaire
				$msg = new Message ();
				$message = 'HIPAY_CAPTURE_REQUESTED '.$orderTotal;
				$message = strip_tags ( $message, '<br>' );
				if (Validate::isCleanHtml ( $message )) {
					$msg->message = $message;
					$msg->id_order = intval ( $order->id );
					$msg->private = 1;
					$msg->add ();
				}
			} else {
				// 'partial';
				// Appel HiPay
				
				/**
				 * VERIFICATION
				 */
				$orderTotal = $order->total_products_wt + $order->total_shipping_tax_incl + $order->total_wrapping_tax_incl;
				// patch de compatibilité
	            if (_PS_VERSION_ < '1.5') {
	            	$id_or_reference = $order->id;
	            }else{
	            	$id_or_reference = $order->reference;
	            }
	            $totalEncaissement = $this->module->getOrderTotalAmountCaptured($id_or_reference);
				// -----------------------
				$stillToCapture = $orderTotal - $totalEncaissement;
				
                $orderLoaded = new OrderCore ( Tools::getValue ( 'id_order' ) );
                $currentState = $orderLoaded->current_state;
                $stateLoaded = new OrderState ( $currentState );
                                
				if (round($stillToCapture,2) < round($refund_amount,2)) {					
					$hipay_redirect_status = $hipay->l('Error, you are trying to capture more than the amount remaining', 'capture');
				} else {
					$data = HipayMaintenance::getMaintenanceData ( 'capture', $refund_amount );
                                        
					$response = HipayMaintenance::restMaintenanceApi ( $reference, $data, (int)$order->id_shop );
					
					// Ajout commentaire
					$msg = new Message ();
					$message = 'HIPAY_CAPTURE_REQUESTED ' . $refund_amount;
					$message = strip_tags ( $message, '<br>' );
					if (Validate::isCleanHtml ( $message )) {
						$msg->message = $message;
						$msg->id_order = intval ( $order->id );
						$msg->private = 1;
						$msg->add ();
					}

					$hipay_redirect_status = 'ok';
				}
			}
		} else
			$hipay_redirect_status = $hipay->l('You do not have permission to do this.','capture');
		
		Tools::redirectAdmin ( Tools::getValue ( 'adminDir' ) . '/index.php?controller=AdminOrders' . '&id_order=' . ( int ) $order->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_err=' . $hipay_redirect_status . '#hipay' );
	}
}
