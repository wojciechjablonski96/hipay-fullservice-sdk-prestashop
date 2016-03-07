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
class HiPay_TppIframeModuleFrontController extends ModuleFrontController {

	/**
	 *
	 * @see FrontController::postProcess()
	 */
	public function postProcess() {
		$hipay = new HiPay_Tpp();
		// Acceptable return status for iframe :
		// Accept, decline, cancel and exception
		// Default value = exception
		$return_status = Tools::getValue("return_status", "exception");

		switch ($return_status) {
			case 'accept' :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=accept');
				break;
			case 'decline' :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=decline');
				break;
			case 'cancel' :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?fc=module&module=' . $hipay->name . '&controller=cancel');
				break;
			case 'pending' :
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
	}

}
