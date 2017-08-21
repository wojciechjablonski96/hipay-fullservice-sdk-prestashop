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

if (!defined('_PS_VERSION_'))
	exit();

include_once (dirname(__FILE__) . '/classes/HipayClass.php');

class HiPay_Tpp extends PaymentModule {

	private $_html = '';
	private $_postErrors = array();

	public function __construct() {
		$this->name = 'hipay_tpp';
		$this->tab = 'payments_gateways';
		$this->version = '1.3.11';
		$this->module_key = 'e25bc8f4f9296ef084abf448bca4808a';
		$this->author = 'HiPay';

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('HiPay Fullservice');
		$this->description = $this->l('Accept transactions worldwide on any device with local & international payment methods. Benefit from a next-gen fraud protection tool.');
		$this->confirmUninstall = $this->l('Are you sure you wish to uninstall HiPay Fullservice?');

		if (!count(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module.');
			
		if(!function_exists('curl_init')){
			$this->warning = $this->l('You need to activate cURL for this module to work.');
		}	

		$this->smarty->assign(array(
			'hipay_version' => $this->version,
		));
	}

	public function install() {
		if (!$this->addHooks() || !parent::install() || !$this->registerHook('footer') || !$this->registerHook('payment') || !$this->registerHook('paymentReturn') || !$this->registerHook('displayAdminOrder') || !$this->registerHook('header') || !$this->registerHook('displayBackOfficeHeader') || !HipayLogger::createTables() || !$this->_installOrderState()) {
			return false;
		}

		Configuration::updateGlobalValue('HIPAY_PROCESSING_QUEUE', 0);
		Configuration::updateGlobalValue('HIPAY_LAST_PROCESS', time());

		HipayLogger::createTables();
		return true;
	}

	public function uninstall() {
		if (!$this->removesHooks() || !parent::uninstall() || !HipayLogger::DropTables() || !Configuration::deleteByName('HIPAY_API_USERNAME') || !Configuration::deleteByName('HIPAY_API_PASSWORD') || !Configuration::deleteByName('HIPAY_API_PASSPHRASE') || !Configuration::deleteByName('HIPAY_TEST_API_PASSPHRASE') || !Configuration::deleteByName('HIPAY_TEST_API_USERNAME') || !Configuration::deleteByName('HIPAY_TEST_API_PASSWORD') || !Configuration::deleteByName('HIPAY_TEST_MODE') || !Configuration::deleteByName('HIPAY_PAYMENT_MODE') || !Configuration::deleteByName('HIPAY_CHALLENGE_URL') || !Configuration::deleteByname('HIPAY_CSS_URL') || !Configuration::deleteByname('HIPAY_ALLOWED_CARDS') || !Configuration::deleteByname('HIPAY_TEMPLATE_MODE') || !Configuration::deleteByname('HIPAY_SELECTOR_MODE') || !Configuration::deleteByname('HIPAY_IFRAME_WIDTH') || !Configuration::deleteByname('HIPAY_IFRAME_HEIGHT') || !Configuration::deleteByname('HIPAY_ALLOWED_LOCAL_CARDS') || !Configuration::deleteByname('HIPAY_THREEDSECURE') || !Configuration::deleteByname('HIPAY_THREEDSECURE_AMOUNT') || !Configuration::deleteByname('HIPAY_MANUALCAPTURE') || !Configuration::deleteByname('HIPAY_MEMORIZE') || !parent::uninstall()){
			return false;
		}
		return true;
	}

	/**
	 * Create order states
	 *
	 * @version 1.0
	 * @global object $cookie Informations users
	 * @return boolean
	 */
	private function _installOrderState() {
		$cookie = $this->context->cookie; 	

		$iso = Language::getIsoById((int) ($cookie->id_lang));

		// List of order state
		$oStates = OrderState::getOrderStates($cookie->id_lang);
		$orderStateName = array();
		// Just name
		foreach ($oStates as $state) {
			$orderStateName [$state ['id_order_state']] = $state ['name'];
		}

		// HIPAY_PENDING
		$translate = ($iso == "fr" ? 'HIPAY - EN ATTENTE' : 'HIPAY - PENDING');
		if (!in_array($translate, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - EN ATTENTE';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - PENDING';
			}
		} else {
			// if order state exists
			$key = array_search($translate, $orderStateName);
			$OS = new OrderState($key);
		}
		$OS->send_email = false;
		$OS->color = "RoyalBlue";
		$OS->hidden = false;
		$OS->delivery = false;
		$OS->logable = true;
		$OS->invoice = false;
		$OS->paid = false;
		$OS->module_name = $this->name;
		if (!$OS->save()) {
			return false;
		}
		if (!in_array($translate, $orderStateName)) {
			Configuration::updatevalue('HIPAY_PENDING', $OS->id);
		} else {
			Configuration::updatevalue('HIPAY_PENDING', $key);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';
		}
		@copy(dirname(__FILE__) . "/wait.gif", _PS_IMG_DIR_ . $file);
		
		
		// HIPAY_CHALLENGED
		$translate = ($iso == "fr" ? 'HIPAY - CONTESTÉ' : 'HIPAY - CHALLENGED');
		if (!in_array($translate, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - CONTESTÉ';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - CHALLENGED';
			}
		} else {
			// if order state exists
			$key = array_search($translate, $orderStateName);
			$OS = new OrderState($key);
		}
		$OS->send_email = false;
		$OS->color = "RoyalBlue";
		$OS->hidden = false;
		$OS->delivery = false;
		$OS->logable = true;
		$OS->invoice = false;
		$OS->paid = false;
		$OS->module_name = $this->name;
		if (!$OS->save()) {
			return false;
		}
		if (!in_array($translate, $orderStateName)) {
			Configuration::updatevalue('HIPAY_CHALLENGED', $OS->id);
		} else {
			Configuration::updatevalue('HIPAY_CHALLENGED', $key);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';
		}
		@copy(dirname(__FILE__) . "/wait.gif", _PS_IMG_DIR_ . $file);

		// HIPAY_EXPIRED
		$translate2 = ($iso == "fr" ? 'HIPAY - EXPIRÉ' : 'HIPAY - EXPIRED');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - EXPIRÉ';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - EXPIRED';
			}
		} else {
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
		}
		$OS->send_email = false;
		$OS->color = "#8f0621";
		$OS->hidden = false;
		$OS->delivery = false;
		$OS->logable = true;
		$OS->invoice = false;
		$OS->paid = false;
		$OS->module_name = $this->name;
		if (!$OS->save()) {
			return false;
		}
		if (!in_array($translate2, $orderStateName)) {
			Configuration::updateValue('HIPAY_EXPIRED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			Configuration::updateValue('HIPAY_EXPIRED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_AUTHORIZED
		$translate3 = ($iso == "fr" ? 'HIPAY - AUTORISÉ' : 'HIPAY - AUTHORIZED');
		if (!in_array($translate3, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - AUTORISÉ';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - AUTHORIZED';
			}
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_AUTHORIZED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate3, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_AUTHORIZED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_CAPTURE_REQUESTED
		$translate2 = ($iso == "fr" ? 'HIPAY - CAPTURE DEMANDÉE' : 'HIPAY - CAPTURE REQUESTED');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - CAPTURE DEMANDÉE';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - CAPTURE REQUESTED';
			}
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_CAPTURE_REQUESTED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_CAPTURE_REQUESTED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_CAPTURED
		$translate2 = ($iso == "fr" ? 'HIPAY - CAPTURÉE' : 'HIPAY - CAPTURED');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - CAPTURÉE';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - CAPTURED';
			}
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_CAPTURED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_CAPTURED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_PARTIALLY_CAPTURED
		$translate2 = ($iso == "fr" ? 'HIPAY - CAPTURE PARTIELLE' : 'HIPAY - PARTIALLY CAPTURED');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - CAPTURE PARTIELLE';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - PARTIALLY CAPTURED';
			}
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_PARTIALLY_CAPTURED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "LimeGreen";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_PARTIALLY_CAPTURED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_REFUND_REQUESTED
		$translate2 = ($iso == "fr" ? 'HIPAY - REMBOURSEMENT DEMANDÉ' : 'HIPAY - REFUND REQUESTED');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - REMBOURSEMENT DEMANDÉ';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - REFUND REQUESTED';
			}
			$OS->send_email = false;
			$OS->color = "#ec2e15";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_REFUND_REQUESTED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "#ec2e15";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_REFUND_REQUESTED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_REFUNDED
		$translate2 = ($iso == "fr" ? 'HIPAY - REMBOURSÉ' : 'HIPAY - REFUNDED');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - REMBOURSÉ';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - REFUNDED';
			}
			$OS->send_email = false;
			$OS->color = "HotPink";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_REFUNDED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "HotPink";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_REFUNDED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_DENIED
		$translate2 = ($iso == "fr" ? 'HIPAY - REFUSÉ' : 'HIPAY - DENIED');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - REFUSÉ';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - DENIED';
			}
			$OS->send_email = false;
			$OS->color = "#8f0621";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = false;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_DENIED', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "#8f0621";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = false;
			$OS->invoice = false;
			$OS->paid = false;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_DENIED', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		// HIPAY_CHARGEDBACK
		$translate2 = ($iso == "fr" ? 'HIPAY - CHARGED BACK' : 'HIPAY - CHARGED BACK');
		if (!in_array($translate2, $orderStateName)) {
			$OS = new OrderState ();
			$OS->name = array();
			foreach (Language::getLanguages() as $language) {
				if (Tools::strtolower($language ['iso_code']) == 'fr')
					$OS->name [$language ['id_lang']] = 'HIPAY - CHARGED BACK';
				else
					$OS->name [$language ['id_lang']] = 'HIPAY - CHARGED BACK';
			}
			$OS->send_email = false;
			$OS->color = "#f89406";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_CHARGEDBACK', $OS->id);
			if (version_compare(_PS_VERSION_, '1.5', '>'))
				$file = 'os/' . $OS->id . '.gif';
			else
				$file = 'tmp/order_state_mini_' . $OS->id . '.gif';

			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . $file);
		} else { // if order state exists
			$key = array_search($translate2, $orderStateName);
			$OS = new OrderState($key);
			$OS->send_email = false;
			$OS->color = "#f89406";
			$OS->hidden = false;
			$OS->delivery = false;
			$OS->logable = true;
			$OS->invoice = true;
			$OS->paid = true;
			$OS->module_name = $this->name;
			if (!$OS->save()) {
				return false;
			}
			Configuration::updateValue('HIPAY_CHARGEDBACK', $key);
			@copy(dirname(__FILE__) . "/done.gif", _PS_IMG_DIR_ . 'os/' . $OS->id . ".gif");
		}

		return true;
	}

	/**
	 * Generates Admin Configure interface
	 *
	 * @return type smarty template
	 */
	public function getContent() {
		if (Tools::isSubmit('btnSubmit') || Tools::getValue('btnLocalCardsubmit') || Tools::getValue('btnCurrencyCardsubmit')) {
			$this->_postValidation();
			if (!count($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors as $err)
					$this->_html .= $this->displayError($err);
		}
		else
			$this->_html .= '<br />';

		$this->_html .= $this->_displayHiPay();
		$this->_html .= $this->renderForm();
		$this->_html .= $this->renderFormCurrencyCards();
		$this->_html .= '<br />';
		$this->_html .= $this->renderFormLogs();

		return $this->_html;
	}

	private function _displayHiPay() {
		$this->smarty->assign(array(
			'this_callback' => HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/validation.php'),
			'this_ip' => getenv("SERVER_ADDR"),
			'this_path_bw' => $this->_path
		));

		return $this->display(__FILE__, 'infos.tpl');
	}
	/**
	 * Interface de configuration
	 */
	public function renderForm() {
		$this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/js/15hipay.js');

		$selection_cards = array(
			array(
				'id' => 'american-express',
				'val' => 'american-express',
				'name' => $this->l('American Express')
			),
			array(
				'id' => 'bcmc',
				'val' => 'bcmc',
				'name' => $this->l('Bancontact / Mister Cash')
			),
			array(
				'id' => 'cb',
				'val' => 'cb',
				'name' => $this->l('Carte Bancaire')
			),
			array(
				'id' => 'maestro',
				'val' => 'maestro',
				'name' => $this->l('Maestro')
			),
			array(
				'id' => 'mastercard',
				'val' => 'mastercard',
				'name' => $this->l('MasterCard')
			),
			array(
				'id' => 'visa',
				'val' => 'visa',
				'name' => $this->l('Visa')
			)
		);

		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Configure HiPay TPP Module'),
					'icon' => 'icon-envelope'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('HiPay API Username :'),
						'name' => 'HIPAY_API_USERNAME',
						'desc' => $this->l('Your API Username'),
						'required' => false
					),
					array(
						'type' => 'text',
						'label' => $this->l('HiPay API Password :'),
						'name' => 'HIPAY_API_PASSWORD',
						'desc' => $this->l('Your API Password'),
						'required' => false
					),
					array(
						'type' => 'text',
						'label' => $this->l('HiPay API Passphrase :'),
						'name' => 'HIPAY_API_PASSPHRASE',
						'desc' => $this->l('Your API Passphrase'),
						'required' => false
					),
					array(
						'type' => 'text',
						'label' => $this->l('HiPay Test API Username :'),
						'name' => 'HIPAY_TEST_API_USERNAME',
						'desc' => $this->l('Your Test API Username'),
						'required' => false
					),
					array(
						'type' => 'text',
						'label' => $this->l('HiPay Test API Password :'),
						'name' => 'HIPAY_TEST_API_PASSWORD',
						'desc' => $this->l('Your Test API Password'),
						'required' => false
					),
					array(
						'type' => 'text',
						'label' => $this->l('HiPay Test API Passphrase :'),
						'name' => 'HIPAY_TEST_API_PASSPHRASE',
						'desc' => $this->l('Your Test API Passphrase'),
						'required' => false
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Switch to mode :'),
						'name' => 'HIPAY_TEST_MODE',
						'class' => 't',
						'required' => true,
						'is_bool' => false,
						'values' => array(
							array(
								'id' => 'hipay_demo_on',
								'value' => 1,
								'label' => $this->l('Test')
							),
							array(
								'id' => 'hipay_demo_off',
								'value' => 0,
								'label' => $this->l('Production')
							)
						),
						'desc' => $this->l('Switch to test mode (Pre-production mode).')
					),
					array(
						'type' => 'select',
						'label' => $this->l('Operating Mode :'),
						'name' => 'HIPAY_PAYMENT_MODE',
						'options' => array(
							'query' => array(
								array(
									'id' => 0,
									'name' => $this->l('Dedicated Page')
								),
								array(
									'id' => 1,
									'name' => $this->l('IFrame')
								),
								array(
									'id' => 2,
									'name' => $this->l('API')
								)
							),
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'text',
						'label' => $this->l('iFrame width :'),
						'name' => 'HIPAY_IFRAME_WIDTH',
						'id' => 'HIPAY_IFRAME_WIDTH',
						'desc' => $this->l('iFrame width (100% by default)'),
						'required' => false,
						'class' => 'IFRAME_SIZE'
					),
					array(
						'type' => 'text',
						'label' => $this->l('iFrame height :'),
						'name' => 'HIPAY_IFRAME_HEIGHT',
						'id' => 'HIPAY_IFRAME_HEIGHT',
						'desc' => $this->l('iFrame height (670 by default)'),
						'required' => false,
						'class' => 'IFRAME_SIZE'
					),
					array(
						'type' => 'select',
						'label' => $this->l('Hosted page template :'),
						'name' => 'HIPAY_TEMPLATE_MODE',
						'options' => array(
							'query' => array(
								array(
									'id' => 'basic-js',
									'name' => $this->l('basic-js')
								),
								array(
									'id' => 'basic',
									'name' => $this->l('basic')
								)
							),
							'id' => 'id',
							'name' => 'name'
						),
						'desc' => $this->l('Basic template showed on Hosted page.')
					),
					array(
						'type' => 'select',
						'label' => $this->l('Display card selector :'),
						'name' => 'HIPAY_SELECTOR_MODE',
						'options' => array(
							'query' => array(
								array(
									'id' => 0,
									'name' => $this->l('Show card selector')
								),
								array(
									'id' => 1,
									'name' => $this->l('No card selector')
								)
							)
							,
							'id' => 'id',
							'name' => 'name'
						),
						'desc' => $this->l('Display card selector on iFrame or Hosted page.')
					),
					array(
						'type' => 'text',
						'label' => $this->l('Challenge status URL :'),
						'name' => 'HIPAY_CHALLENGE_URL',
						'desc' => $this->l('Redirection page for the challenge status')
					),
					array(
						'type' => 'text',
						'label' => $this->l('URL CSS :'),
						'name' => 'HIPAY_CSS_URL',
						'desc' => $this->l('URL for css to style your merchant page')
					),
					array(
						'type' => 'checkbox',
						'label' => $this->l('Types de authorised cards :'),
						'name' => 'card_selection',
						'lang' => true,
						'values' => array(
							'query' => $selection_cards,
							'id' => 'id',
							'name' => 'name'
						)
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Activate 3D Secure :'),
						'name' => 'HIPAY_THREEDSECURE',
						'class' => 't',
						'required' => true,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'hipay_threedsecure_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'hipay_threedsecure_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						)
					),
					array(
						'type' => 'text',
						'label' => $this->l('3D Secure minimum amount :'),
						'name' => 'HIPAY_THREEDSECURE_AMOUNT',
						'desc' => $this->l('Minimum amount for 3D secure to activate'),
						'required' => false,
						'class' => '3D_secure'
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Switch to capture :'),
						'name' => 'HIPAY_MANUALCAPTURE',
						'class' => 't',
						'required' => true,
						'is_bool' => false,
						'values' => array(
							array(
								'id' => 'hipay_manualcapture_on',
								'value' => 1,
								'label' => $this->l('manual')
							),
							array(
								'id' => 'hipay_manualcapture_off',
								'value' => 0,
								'label' => $this->l('automatic')
							)
						)
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Allow Memorization of card tokens  :'),
						'name' => 'HIPAY_MEMORIZE',
						'class' => 't',
						'required' => true,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'hipay_memorize_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'hipay_memorize_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
						'desc' => $this->l('Allow user to memorize card token, as well as provide feature to select memorized tokens')
					)
				),
				'submit' => array(
					'title' => $this->l('Save'),
					'class' => 'btn btn-default'
				)
			)
		);

		$helper = new HelperForm ();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int) Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array(
					$fields_form
		));
	}
	
	// Form to render local cards
	public function renderFormLocalCards() {
		$local_cards = $this->checkLocalCards();
		if ($local_cards == '') {
			$html = '';
		} else {
			$html = '<br/>';

			if (version_compare(_PS_VERSION_, '1.6', '>')) {
				$html .= '<div class="panel-heading"><i class="icon-globe"></i>' . $this->l('Local cards') . '</div>';
			} else {
				$html .= '<fieldset id="fieldset_1">
                            <legend>' . $this->l('Local cards') . '</legend>';
			}

			$html .= $this->l('Local cards header') . "<br/>";
			$html .= '<table cellpadding="0" cellspacing="0" class="table">';
			$html .= '<tbody><tr><th style="width: 200px">' . $this->l('Local cards') . '</th><th style="text-align: center">' . $this->l('Activate') . '</th><th style="text-align: center">' . $this->l('Available currencies') . '</th></tr>';

			foreach ($local_cards as $value) {
				$html .= '<tr><td><label class="t" for="card_selection_' . (string) $value->code . '"><strong>' . (string) $value->name . '</strong></label></td>';
				$html .= '<td style="text-align: center"><input type="checkbox" ' . $this->checkLocalCardifChecked((string) $value->code) . ' value="' . (string) $value->code . '" class="" id="card_selection_' . (string) $value->code . '" name="local_card_selection_' . (string) $value->code . '"></td>';
				$html .= '<td style="text-align: center">';
				foreach ($value->currencies as $value) {
					foreach ($value->iso_code as $value) {
						$html .= Tools::strtoupper((string) $value) . ' ';
					}
				}
				$html .= '</td></tr>';
			}
			$html .= '</tbody></table>';

			if (version_compare(_PS_VERSION_, '1.6', '>')) {
				$html .= '<button type="submit" value="1" id="module_form_submit_btn" name="btnLocalCardsubmit" class="btn btn-default"><i class="process-icon-save"></i> ' . $this->l('Save') . '</button>';
			} else {
				$html .= '<input id="module_form_submit_btn" class="btn btn-default" type="submit" name="btnLocalCardsubmit" value="' . $this->l('Save') . '">';
			}
			// $html .= '</div>';
			if (version_compare(_PS_VERSION_, '1.6', '>')) {

			} else {
				$html .= '</fieldset>';
			}
			$html .= '</form></div>';
		}
		return $html;
	}

	public function checkLocalCardifChecked($code) {
		// Check config to return checked value or not
		$localPayments = Tools::jsonDecode(Configuration::get('HIPAY_LOCAL_PAYMENTS'));
		if ($localPayments != '') {
			if (in_array($code, $localPayments)) {
				return ' checked="checked" ';
			} else {
				return '';
			}
		} else {
			return '';
		}
	}

	/**
	 * Currecies activation by cerdit-card
	 */
	// Form to render currencies by credit-cards
	public function renderFormCurrencyCards() {
		$currencies_module = Currency::getCurrencies();

		$selection_cards = array(
			'american-express' => $this->l('American Express'),
			'bcmc' => $this->l('Bancontact / Mister Cash'),
			'cb' => $this->l('Carte Bancaire'),
			'maestro' => $this->l('Maestro'),
			'mastercard' => $this->l('MasterCard'),
			'visa' => $this->l('Visa')
		);

		$token = Tools::getAdminTokenLite('AdminModules');
		$html = '<br/><div class="panel">';

		$html .= '<form enctype="multipart/form-data" method="post" action="index.php?controller=AdminModules&amp;configure=' . $this->name . '&amp;tab_module=payments_gateways&amp;module_name=' . $this->name . '&amp;btnCurrencyCardsubmit=1&amp;token=' . $token . '" class="defaultForm " id="module_form">';

		if (version_compare(_PS_VERSION_, '1.6', '>')) {
			$html .= '<br/><div class="panel-heading"><i class="icon-money"></i>' . $this->l('Authorized currencies by credit card') . '</div>';
		} else {
			$html .= '<fieldset id="fieldset_1">
                            <legend>' . $this->l('Authorized currencies by credit card') . '</legend>';
		}

		$html .= $this->l('Currencies cards header') . "<br/>";
		$html .= '<table cellpadding="0" cellspacing="0" class="table">';
		$html .= '<tbody><tr><th style="width: 200px"></th>';

		// Currencies cols
		foreach ($currencies_module as $value) {
			$html .= '<th style="text-align: center">' . $value ['iso_code'] . '</th>';
		}
		$html .= '</tr>';

		// Credit cards rows
		foreach ($selection_cards as $ccode => $cvalue) {
			$html .= '<tr><td><strong>' . $cvalue . '</strong></td>';
			foreach ($currencies_module as $value) {
				$html .= '<td style="text-align: center"><input type="checkbox" ' . $this->checkCurrencyCardifChecked((string) $value ['iso_code'] . '-' . $ccode) . ' value="' . (string) $value ['iso_code'] . '-' . $ccode . '" class="" id="card_selection_' . (string) $value ['iso_code'] . '-' . $ccode . '" name="currency_card_selection_' . (string) $value ['iso_code'] . '-' . $ccode . '"></td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		if (version_compare(_PS_VERSION_, '1.6', '>')) {
			$html .= '<button type="submit" value="1" id="module_form_submit_btn" name="btnLocalCardsubmit" class="btn btn-default"><i class="process-icon-save"></i> ' . $this->l('Save') . '</button>';
		} else {
			$html .= '<input id="module_form_submit_btn" class="btn btn-default" type="submit" name="btnLocalCardsubmit" value="' . $this->l('Save') . '">';
		}

		if (version_compare(_PS_VERSION_, '1.6', '>')) {

		} else {
			$html .= '</fieldset>';
		}

		$html .= '<div class="clear"></div><br/>';
		$html .= '<div class="clear"></div>';
		$html .= $this->renderFormLocalCards();

		return $html;
	}

	public function checkCurrencyCardifChecked($code) {
		// Check config to return checked value or not
		$currencyCards = Tools::jsonDecode(Configuration::get('HIPAY_CURRENCY_CARDS'));
		if ($currencyCards != '') {
			if (in_array($code, $currencyCards)) {
				return ' checked="checked" ';
			} else {
				return '';
			}
		} else {
			return '';
		}
	}

	/**
	 * End of currencies activation by credit card
	 */

	/**
	 * Table des logs
	 */
	public function renderFormLogs() {
		$html = '';
		$context = Context::getContext();
		$language_code = HipayClass::getLanguageCode($context->language->iso_code);
			
		if (_PS_VERSION_ >= '1.6'){
			$this->context->controller->addCSS(_MODULE_DIR_ . $this->name . '/css/16logstable.css', 'all');
		}else{
			$this->context->controller->addCSS(_MODULE_DIR_ . $this->name . '/css/15logstable.css', 'all');
		}
		
		$this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/js/logs_' . $language_code . '.js');
		$this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/js/jquery.dataTables.min.js');
		
		$logs = Db::getInstance()->executeS('
			SELECT id, name, date, level, message
			FROM `' . _DB_PREFIX_ . 'hipay_logs` ORDER BY id DESC LIMIT 0, 6000
		');
		if (is_array($logs)) {
			$html = '
	        <div class="panel">
	    	<fieldset id="fieldset_1">
				<legend>' . $this->l('Logs') . '</legend>
	    		<table id="hipay_logs" cellpadding="0" cellspacing="0" border="0">
	    			<thead>
	    				<tr>
							<th>' . $this->l('Id') . '</th>
	    					<th>' . $this->l('Name') . '</th>
	    					<th>' . $this->l('Date') . '</th>
	    					<th>' . $this->l('Level') . '</th>
	    					<th>' . $this->l('Message') . '</th>
	    				</tr>
	    			</thead>
					<tbody>';
			$i = 0;
			foreach ($logs as $log) {

				$date = date("d/m/y H:i:s", strtotime($log ['date']));

				if ($log ['level'] == 1)
					$level = $this->l('NOTICE');
				elseif ($log ['level'] == 2)
					$level = $this->l('WARNING');
				elseif ($log ['level'] == 3)
					$level = $this->l('ERROR');
				elseif ($log ['level'] == 4)
					$level = $this->l('APICALL');

				$html .= '
	    				<tr>
							<td>' . $log ['id'] . '</td>
	    					<td>' . $log ['name'] . '</td>
	    					<td>' . $date . '</td>
	    					<td>' . $level . '</td>
	    					<td><span id="shortdetails' . $i . '" class="shortDetails">' . Tools::substr($log ['message'], 0, 150);

				if (Tools::strlen($log ['message']) > 150) {
					$html .= '<a href="#" class="more" onclick="document.getElementById(\'details' . $i . '\').style.display = \'inline\';document.getElementById(\'shortdetails' . $i . '\').style.display = \'none\';return false;"> ...</a></span>
	    					<span id="details' . $i . '" class="moreDetails" style="display:none">' . $log ['message'] . '</span></td>';
				}

				$html .= '</tr>';
				$i++;
			}

			$html .= '</tbody>
				</table>
			</fieldset></div>';
		}
		return $html;
	}

	public function getConfigFieldsValues() {
		// Modification to save the amount of 3D Secure
		$str = Tools::getValue('HIPAY_THREEDSECURE_AMOUNT', Configuration::get('HIPAY_THREEDSECURE_AMOUNT'));
		$str = str_replace(".", ",", $str);

		$set_config_fields_values = array(
			'HIPAY_API_USERNAME' => Tools::getValue('HIPAY_API_USERNAME', Configuration::get('HIPAY_API_USERNAME')),
			'HIPAY_API_PASSWORD' => Tools::getValue('HIPAY_API_PASSWORD', Configuration::get('HIPAY_API_PASSWORD')),
			'HIPAY_API_PASSPHRASE' => Tools::getValue('HIPAY_API_PASSPHRASE', Configuration::get('HIPAY_API_PASSPHRASE')),
			'HIPAY_TEST_API_USERNAME' => Tools::getValue('HIPAY_TEST_API_USERNAME', Configuration::get('HIPAY_TEST_API_USERNAME')),
			'HIPAY_TEST_API_PASSWORD' => Tools::getValue('HIPAY_TEST_API_PASSWORD', Configuration::get('HIPAY_TEST_API_PASSWORD')),
			'HIPAY_TEST_API_PASSPHRASE' => Tools::getValue('HIPAY_TEST_API_PASSPHRASE', Configuration::get('HIPAY_TEST_API_PASSPHRASE')),
			'HIPAY_TEST_MODE' => Tools::getValue('HIPAY_TEST_MODE', Configuration::get('HIPAY_TEST_MODE')),
			'HIPAY_THREEDSECURE' => Tools::getValue('HIPAY_THREEDSECURE', Configuration::get('HIPAY_THREEDSECURE')),
			'HIPAY_THREEDSECURE_AMOUNT' => $str,
			'HIPAY_MANUALCAPTURE' => Tools::getValue('HIPAY_MANUALCAPTURE', Configuration::get('HIPAY_MANUALCAPTURE')),
			'HIPAY_MEMORIZE' => Tools::getValue('HIPAY_MEMORIZE', Configuration::get('HIPAY_MEMORIZE')),
			'HIPAY_PAYMENT_MODE' => Tools::getValue('HIPAY_PAYMENT_MODE', Configuration::get('HIPAY_PAYMENT_MODE')),
			'HIPAY_CHALLENGE_URL' => Tools::getValue('HIPAY_CHALLENGE_URL', Configuration::get('HIPAY_CHALLENGE_URL')),
			'HIPAY_CSS_URL' => Tools::getValue('HIPAY_CSS_URL', Configuration::get('HIPAY_CSS_URL')),
			'HIPAY_TEMPLATE_MODE' => Tools::getValue('HIPAY_TEMPLATE_MODE', Configuration::get('HIPAY_TEMPLATE_MODE')),
			'HIPAY_SELECTOR_MODE' => Tools::getValue('HIPAY_SELECTOR_MODE', Configuration::get('HIPAY_SELECTOR_MODE')),
			'HIPAY_IFRAME_WIDTH' => Tools::getValue('HIPAY_IFRAME_WIDTH', Configuration::get('HIPAY_IFRAME_WIDTH')),
			'HIPAY_IFRAME_HEIGHT' => Tools::getValue('HIPAY_IFRAME_HEIGHT', Configuration::get('HIPAY_IFRAME_HEIGHT'))
				)
		;

		// Update display choix des cartes when saving

		$hasCardValues = false; // Initialize to false for double check below
		if (Tools::getValue('card_selection_american-express')) {
			$set_config_fields_values ['card_selection_american-express'] = true;
			$hasCardValues = true;
		}
		if (Tools::getValue('card_selection_bcmc')) {
			$set_config_fields_values ['card_selection_bcmc'] = true;
			$hasCardValues = true;
		}
		if (Tools::getValue('card_selection_cb')) {
			$set_config_fields_values ['card_selection_cb'] = true;
			$hasCardValues = true;
		}
		if (Tools::getValue('card_selection_maestro')) {
			$set_config_fields_values ['card_selection_maestro'] = true;
			$hasCardValues = true;
		}
		if (Tools::getValue('card_selection_mastercard')) {
			$set_config_fields_values ['card_selection_mastercard'] = true;
			$hasCardValues = true;
		}
		if (Tools::getValue('card_selection_visa')) {
			$set_config_fields_values ['card_selection_visa'] = true;
			$hasCardValues = true;
		}
		// Do a double check on the config file because when just opening the config, the Tools::getValue[] is always empty.
		// If no Tools::getValue detected, then check directly from old config values
		if ($hasCardValues === false) {
			$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');
			$cart_arr = explode(',', $card_str);

			foreach ($cart_arr as $value) {
				if ($value == 'visa') {
					$set_config_fields_values ['card_selection_visa'] = true;
				}
				if ($value == 'mastercard') {
					$set_config_fields_values ['card_selection_mastercard'] = true;
				}
				if ($value == 'american-express') {
					$set_config_fields_values ['card_selection_american-express'] = true;
				}
				if ($value == 'bcmc') {
					$set_config_fields_values ['card_selection_bcmc'] = true;
				}
				if ($value == 'cb') {
					$set_config_fields_values ['card_selection_cb'] = true;
				}
				if ($value == 'maestro') {
					$set_config_fields_values ['card_selection_maestro'] = true;
				}
			}
		}

		return $set_config_fields_values;
	}

	/**
	 * Vérifications de soumission des valeurs de configuration
	 */
	private function _postValidation() {
		if (Tools::isSubmit('btnSubmit')) {
			// Verify is test authentication access is present if test mode is activated
			if (!Tools::getValue('HIPAY_TEST_MODE')) {
				if (!Tools::getValue('HIPAY_API_USERNAME'))
					$this->_postErrors [] = $this->l('API Username is required.');
				if (!Tools::getValue('HIPAY_API_PASSWORD'))
					$this->_postErrors [] = $this->l('API Password is required.');
			}

			// Verify is test authentication access is present if test mode is activated
			if (Tools::getValue('HIPAY_TEST_MODE')) {
				if (!Tools::getValue('HIPAY_TEST_API_USERNAME'))
					$this->_postErrors [] = $this->l('Test API Username is required.');
				if (!Tools::getValue('HIPAY_TEST_API_PASSWORD'))
					$this->_postErrors [] = $this->l('Test API Password is required.');
			}

			// Verify is 3D secure mode is activated
			if (Tools::getValue('HIPAY_THREEDSECURE')) {
				$str = Tools::getValue('HIPAY_THREEDSECURE_AMOUNT');
				$str = str_replace(",", ".", $str);
				if ((float)$str < 0 || !is_numeric($str)) {
					$this->_postErrors [] = $this->l('3D Secure minimum amount is invalid');
				}
			}

			// Verify that css url starts with https:// is it has been entered
			if (Tools::getValue('HIPAY_CHALLENGE_URL')) {
				$segment_http = Tools::substr(Tools::getValue('HIPAY_CHALLENGE_URL'), 0, 7);
				$segment_https = Tools::substr(Tools::getValue('HIPAY_CHALLENGE_URL'), 0, 8);

				if (($segment_http === 'http://') || ($segment_https === 'https://')) {
					// Ok proceed
				} else {
					$this->_postErrors [] = $this->l('Your Challenge url is not a valid url.');
				}
			}

			// Verify that css url starts with https:// is it has been entered
			if (Tools::getValue('HIPAY_CSS_URL')) {
				$segment = Tools::substr(Tools::getValue('HIPAY_CSS_URL'), 0, 8);
				if ($segment === 'https://') {
					// Ok proceed
				} else {
					$this->_postErrors [] = $this->l('Your CSS url must be secure and start with https://');
				}
			}
		}
	}

	private function _postProcess() {
		if (Tools::getValue('btnLocalCardsubmit')) {
			$localPayments = array();
			foreach ($_POST as $key => $value) {
				if ($this->startsWith($key, 'local_card_selection_')) {
					$localPayments [] = $value;
				}
			}
			Configuration::updateValue('HIPAY_LOCAL_PAYMENTS', Tools::jsonEncode($localPayments));

			$currencyCards = array();
			foreach ($_POST as $key => $value) {
				if ($this->startsWith($key, 'currency_card_selection_')) {
					$currencyCards [] = $value;
				}
			}
			Configuration::updateValue('HIPAY_CURRENCY_CARDS', Tools::jsonEncode($currencyCards));
		}
		if (Tools::isSubmit('btnSubmit')) {
			Configuration::updateValue('HIPAY_API_USERNAME', Tools::getValue('HIPAY_API_USERNAME'));
			Configuration::updateValue('HIPAY_API_PASSWORD', Tools::getValue('HIPAY_API_PASSWORD'));
			Configuration::updateValue('HIPAY_API_PASSPHRASE', Tools::getValue('HIPAY_API_PASSPHRASE'));
			Configuration::updateValue('HIPAY_TEST_API_USERNAME', Tools::getValue('HIPAY_TEST_API_USERNAME'));
			Configuration::updateValue('HIPAY_TEST_API_PASSWORD', Tools::getValue('HIPAY_TEST_API_PASSWORD'));
			Configuration::updateValue('HIPAY_TEST_API_PASSPHRASE', Tools::getValue('HIPAY_TEST_API_PASSPHRASE'));
			Configuration::updateValue('HIPAY_TEST_MODE', Tools::getValue('HIPAY_TEST_MODE'));
			Configuration::updateValue('HIPAY_THREEDSECURE', Tools::getValue('HIPAY_THREEDSECURE'));
			// Modification to save the amount of 3D Secure
			$str = Tools::getValue('HIPAY_THREEDSECURE_AMOUNT');
			$str = str_replace(",", ".", $str);
			Configuration::updateValue('HIPAY_THREEDSECURE_AMOUNT', $str);
			Configuration::updateValue('HIPAY_MANUALCAPTURE', Tools::getValue('HIPAY_MANUALCAPTURE'));
			Configuration::updateValue('HIPAY_MEMORIZE', Tools::getValue('HIPAY_MEMORIZE'));
			Configuration::updateValue('HIPAY_PAYMENT_MODE', Tools::getValue('HIPAY_PAYMENT_MODE'));
			Configuration::updateValue('HIPAY_CHALLENGE_URL', Tools::getValue('HIPAY_CHALLENGE_URL'));
			Configuration::updateValue('HIPAY_CSS_URL', Tools::getValue('HIPAY_CSS_URL'));
			Configuration::updateValue('HIPAY_TEMPLATE_MODE', Tools::getValue('HIPAY_TEMPLATE_MODE'));
			Configuration::updateValue('HIPAY_SELECTOR_MODE', Tools::getValue('HIPAY_SELECTOR_MODE'));
			Configuration::updateValue('HIPAY_IFRAME_WIDTH', Tools::getValue('HIPAY_IFRAME_WIDTH'));
			Configuration::updateValue('HIPAY_IFRAME_HEIGHT', Tools::getValue('HIPAY_IFRAME_HEIGHT'));

			// Processing cards
			$card_arr = array();
			if (Tools::getValue('card_selection_cb'))
                $card_arr [0] = Tools::getValue('card_selection_cb');
            if (Tools::getValue('card_selection_visa'))
                $card_arr [1] = Tools::getValue('card_selection_visa');
			if (Tools::getValue('card_selection_mastercard'))
                $card_arr [2] = Tools::getValue('card_selection_mastercard');
			if (Tools::getValue('card_selection_maestro'))
                $card_arr [3] = Tools::getValue('card_selection_maestro');
            if (Tools::getValue('card_selection_bcmc'))
                $card_arr [4] = Tools::getValue('card_selection_bcmc');
			if (Tools::getValue('card_selection_american-express'))
                $card_arr [5] = Tools::getValue('card_selection_american-express');
			$card_str = implode(',', $card_arr);
			Configuration::updateValue('HIPAY_ALLOWED_CARDS', $card_str);
		}
		HipayLogger::addLog($this->l('Hipay BO updated'), HipayLogger::NOTICE, 'The HiPay backoffice params have been updated');
		$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}
	
	public function hookPayment($params) {

		if (!$this->active)
			return;

		// Verify if customer has memorized tokens
		$cart = $this->context->cart;
		$tokens = HipayToken::getTokens($cart->id_customer); // Retrieve list of tokens
		if (isset($tokens ['0'])) {
			$token_display = 'true';
		} else {
			$token_display = 'false';
		}

		if (_PS_VERSION_ >= '1.5'){
			// Get invoice Country
			$customer = new Customer((int)$cart->id_customer);
			$customerInfo = $customer->getAddresses((int)$cart->id_lang);
			foreach ($customerInfo as $key => $value){
				if ($value['id_address'] == $cart->id_address_invoice) {
					$invoice_country = HipayClass::getCountryCode($value['country']);
				}
			}
		}
		// End Get invoice country
		
		// Verify if systems should display memorized tokens
		$allow_memorize = HipayClass::getShowMemorization();

		// If both are true, activate additional info to allow payment via existing token
		if (($allow_memorize == 'true')) {
			$currency_array = $this->getCurrency((int) $cart->id_currency);
			$currency = $currency_array [0] ['iso_code'];
			foreach ($currency_array as $value) {
				if ($value ['id_currency'] == $cart->id_currency) {
					$actual_currency = $value ['iso_code'];
				}
			}
			if ($currency != $actual_currency)
				$currency = $actual_currency;

			$this->context->smarty->assign(array(
				'cart_id' => $cart->id,
				'currency' => $currency,
				'amount' => $cart->getOrderTotal(true, Cart::BOTH)
			));
		}

		// Create dynamic payment button
		$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');
		// Cards filter by country
		if ($invoice_country != 'FR') {
			$card_str = str_replace('cb','', $card_str);			
		}
		if ($invoice_country != 'BE') {
			$card_str = str_replace('bcmc','', $card_str);			
		}

		$cart_arr = explode(',', $card_str);

		$card_currency = Configuration::get('HIPAY_CURRENCY_CARDS');
		$card_curr_val = array();
				
		if (Tools::strlen($card_currency) > 3) {
			$currency_array = $this->getCurrency((int) $cart->id_currency);
			$currency = $currency_array [0] ['iso_code'];
			foreach ($currency_array as $value) {
				if ($value ['id_currency'] == $cart->id_currency) {
					$actual_currency = $value ['iso_code'];
				}
			}
			$card_currency_arr = explode(',', Tools::substr($card_currency, 1, - 1));

			foreach ($card_currency_arr as $value) {
				foreach ($cart_arr as $cardvalue) {
					if ($value == '"' . $actual_currency . '-' . $cardvalue . '"') {
						$card_curr_val[$cardvalue] = true;
					}
				}
			}
		} else {
			foreach ($cart_arr as $cardvalue) {
				$card_curr_val[$cardvalue] = true;
			}
		}

		$btn_image = '';
		$card_currency_ok = '0';
		$payment_product_list_upd = '';
		$count_ccards = 0;

		foreach ($cart_arr as $value) {
			if ($value == 'visa' && $card_curr_val['visa']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/visa_small.png" alt="Visa" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'visa,';
				$count_ccards++;
			}
			if ($value == 'mastercard' && $card_curr_val['mastercard']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/mc_small.png" alt="MasterCard" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'mastercard,';
				$count_ccards++;
			}
			if ($value == 'american-express' && $card_curr_val['american-express']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/amex_small.png" alt="American Express" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'american-express,';
				$count_ccards++;
			}
			if ($value == 'bcmc' && $card_curr_val['bcmc']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/bcmc_small.png" alt="Bancontact / Mister Cash" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'bcmc,';
				$count_ccards++;
			}
			if ($value == 'cb' && $card_curr_val['cb']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/cb_small.png" alt="CB" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'cb,';
				$count_ccards++;
			}
			if ($value == 'maestro' && $card_curr_val['maestro']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/maestro_small.png" alt="Maestro" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'maestro,';
				$count_ccards++;
			}
		}

		// Assign smarty variables
		$this->context->smarty->assign(array(
			'hipay_ssl' => Configuration::get('PS_SSL_ENABLED'),
			'token_display' => $token_display,
			'allow_memorize' => $allow_memorize,
			'tokens' => $tokens,
			'payment_mode' => Configuration::get('HIPAY_PAYMENT_MODE'),
			'PS_VERSION' => _PS_VERSION_,
			'btn_image' => $btn_image,
			'card_currency_ok' => $card_currency_ok,
			'payment_product_list_upd' => $payment_product_list_upd,
			'count_ccards' => $count_ccards
		));

		// Assign paths
		$this->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
		));
		// Local cards variables
		$localPayments = Tools::jsonDecode(Configuration::get('HIPAY_LOCAL_PAYMENTS'));

		$local_cards = $this->checkLocalCards();
		// Retrieving images and storing in any array associate to the card code.
		$local_cards_img = array();
		$local_cards_name = array();
		$show_cards = array();
		if (count($local_cards)) {
			$currency_array = $this->getCurrency((int) $cart->id_currency);
			$currency = $currency_array [0] ['iso_code'];
			foreach ($currency_array as $value) {
				if ($value ['id_currency'] == $cart->id_currency) {
					$actual_currency = $value ['iso_code'];
				}
			}
			foreach ($local_cards as $value) {
				$local_cards_img [(string) $value->code] = (string) $value->image;
				$local_cards_name [(string) $value->code] = (string) $value->name;
				// Get local card country
				$local_cards_countries [(string) $value->code] = (string) $value->countries;
				// End Get local card country
				$show_cards [(string) $value->code] = 'false'; // Initialize to false
				// Assigning temporary code to variable
				$card_code = (string) $value->code;
				foreach ($value->currencies as $value) {
					foreach ($value->iso_code as $value) {
						if (Tools::strtoupper($actual_currency) == Tools::strtoupper((string) $value)) {
							$show_cards [$card_code] = 'true'; // Update to true
						}
					}
				}
				// Check local card country
				if (strpos($local_cards_countries[$card_code], $invoice_country) === false && $local_cards_countries[$card_code] != 'ALL') {
					$show_cards [$card_code] = 'false';
				}
				// End Check local card country
			}
		}
		if (count($localPayments)) {
			$allow_local_cards = 'true';
		} else {
			$allow_local_cards = 'false';
		}

		$this->smarty->assign(array(
			'allow_local_cards' => $allow_local_cards,
			'local_cards_list' => $localPayments,
			'local_cards_img' => $local_cards_img,
			'local_cards_name' => $local_cards_name,
			'show_cards' => $show_cards
		));

		// modif One Page Checkout
		// Check if cart is in OPC
		$is_opc = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'true' : 'false';
		$id_opc = ''; // Set id_opc to empty by default
		if ($is_opc == 'true') {
			$id_opc = 'OPC'; // This will update hidden field 'ioBB' to 'ioBBOPC' to prevent duplicate id
		}
		// Add generic smarty variables;
		$this->smarty->assign(array(
			'id_opc' => $id_opc
		));

		return $this->display(__FILE__, 'payment.tpl');		
	}

	public function hookFooter($params) {
		// modif One Page Checkout
		// Check if cart is in OPC
		$is_opc = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'true' : 'false';
		$id_opc = ''; // Set id_opc to empty by default
		if ($is_opc == 'true') {
			$id_opc = 'OPC'; // This will update hidden field 'ioBB' to 'ioBBOPC' to prevent duplicate id
		}
		// Add generic smarty variables;
		$this->smarty->assign(array(
			'id_opc' => $id_opc
		));
		return $this->display(__FILE__, 'payment_opc_footer.tpl');
	}

	public function hookPaymentReturn($params) {
		if (!$this->active)
			return;

		$state = $params ['objOrder']->getCurrentState();
		if ($state)
			$this->smarty->assign('status', 'OK');
		else
			$this->smarty->assign('status', 'failed');
		return $this->display(__FILE__, 'payment_return.tpl');
	}
	
	/**
	 * Mes 2 formulaires sur les commandes permettant les remboursements ou les captures
	 */
	public function HookDisplayAdminOrder() {
		$orderLoaded = new OrderCore(Tools::getValue('id_order'));
		// Verify the payment method name
		$payment_method_sql = "SELECT payment_method FROM `" . _DB_PREFIX_ . "order_payment` WHERE order_reference='" . $orderLoaded->reference . "'";
		$payment_method = Db::getInstance()->executeS($payment_method_sql);

		$hide_refund = false;
		$hide_capture = false;

		if (isset($payment_method [0] ['payment_method'])) {
			$explode_payment_local_card = explode($this->displayName . ' via', $payment_method [0] ['payment_method']);
			if (isset($explode_payment_local_card [1])) {

				$payment_local_card = $explode_payment_local_card [1];

				$local_cards = $this->checkLocalCards();

				if (isset($local_cards)) {
					if (count($local_cards)) {
						foreach ($local_cards as $value) {
							if ((string) $value->name == trim($payment_local_card)) {
								if ((string) $value->refund == '0') {
									$hide_refund = true;
								}
								if ((string) $value->manualcapture == '0') {
									$hide_capture = true;
								}
							}
						}
					}
				}
				if (Tools::strtolower(trim($payment_local_card)) == 'bcmc')
					$hide_refund = true;
			}
			// Verify if already CAPTURED
			$payment_message_sql = "SELECT * FROM `" . _DB_PREFIX_ . "message` WHERE id_order='" . $orderLoaded->id . "' AND message LIKE 'HiPay%Status : 118%'";
			$paymentmessage = Db::getInstance()->executeS($payment_message_sql);
			if (empty($paymentmessage))
				$hide_refund = true;

		}

		$currentState = $orderLoaded->current_state;
		$stateLoaded = new OrderState($currentState);

		// Check if current state = Configuration::get( 'HIPAY_REFUND_REQUESTED' )
		// If renfund requested, then prevent any further refund until current refund has been completed
		if ($currentState == Configuration::get('HIPAY_REFUND_REQUESTED')) {
			$hide_refund = true;
		}

		$form = '';
		if ($orderLoaded->module == $this->name) {
			if ($stateLoaded->paid) {
				/**
				 * variables de vérification
				 */
				$orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;
				$totalEncaissement = $this->getOrderTotalAmountCaptured($orderLoaded->reference);

				$adminDir = _PS_ADMIN_DIR_;
				$adminDir = Tools::substr($adminDir, strrpos($adminDir, '/'));
				$adminDir = Tools::substr($adminDir, strrpos($adminDir, '\\'));
				$adminDir = str_replace('\\', '', $adminDir);
				$adminDir = str_replace('/', '', $adminDir);

				$context = Context::getContext();
				$form_action = '../index.php?fc=module&module=' . $this->name . '&controller=refund';

				if (version_compare(_PS_VERSION_, '1.6', '>')) {
					$form .= '<div id="htmlcontent" class="panel">
	                 <div class="panel-heading"><img src="../img/admin/money.gif">&nbsp;&nbsp;' . $this->l('Hipay Refund') . '</div>
	                 <fieldset>';
				} else {
					$form .= '
		        		<div style="height:10px"></div>
		        		<div>
		        		<fieldset>';
					$form .= '<legend><img src="../img/admin/money.gif">&nbsp;&nbsp;' . $this->l('Hipay Refund') . '</legend>';
				}

				if (Tools::getValue('hipay_refund_err')) {
					if (Tools::getValue('hipay_refund_err') == 'ok') {
						$form .= '<p style="" class="conf">
									<a style="position: relative; top: -100px;" id="hipay"></a>
			        				' . $this->l('Request successfully sent') . '
						        	</p>';
					} else {
						if(_PS_VERSION_ >= '1.6')
						{
							$form .= '<style media="screen" type="text/css">
							p.error{
								color:red;
							}
							</style>';
						}
						$form .= '<p style="" class="error">
									<a style="position: relative; top: -100px;" id="hipay"></a>
						        	' . Tools::getValue('hipay_refund_err') . '
						        	</p>';
					}
				}

				/**
				 * FORMULAIRE DE REMBOURSEMENT
				 */
				$form .= '
		        		<fieldset>
		        			<legend>' . $this->l('Refund this order') . '</legend>';

				// summary of amount captured, amount that can be refunded
				$form .= '
	        			<table class="table" width="auto" cellspacing="0" cellpadding="0">
	        				<tr>
	        					<th>' . $this->l('Amount that can be refunded') . '</th>
	        				</tr>
	        				<tr>
	        					<td class="value"><span class="badge badge-success">' . Tools::displayPrice($totalEncaissement) . '</span></td>
	        				</tr>
	        			</table>';
				$form .= '<div style="font-size: 12px;">
					<sup>*</sup> ' . $this->l('Amount will be updated once the refund will be confirmed by HiPay Fullservice') . '</div>';

				if ($totalEncaissement != 0 && $hide_refund == false) {
					$form .= '
					<script>
                       $( document ).ready(function() {
                            $("#hipay_refund_form").submit( function(){
                                var type=$("[name=hipay_refund_type]:checked").val();
                                var proceed = "true";
                                /*if(type=="partial")
                                {
                                    var amount=$("#hidden2").val();
                                    if(amount == "" || !$.isNumeric(amount))
                                    {
                                        alert("' . $this->l('Please enter an amount') . '");
                                        proceed = "false";
                                    }
                                    if(amount<=0)
                                    {
                                        alert("' . $this->l('Please enter an amount greater than zero') . '");
                                        proceed = "false";
                                    }
                                    if(amount>' . $totalEncaissement . ')
                                    {
                                        alert("' . $this->l('Amount exceeding authorized amount') . '");
                                        proceed = "false";
                                    }
                                }*/

                                if(proceed == "false")
                                {
                                    return false;
                                }else{
                                    return true;
                                }

                                return false;
                            });
					    });
                    </script>
					<form action="' . $form_action . '" method="post" id="hipay_refund_form">';

					$form .= '<input type="hidden" name="id_order" value="' . Tools::getValue('id_order') . '" />';
					$form .= '<input type="hidden" name="id_emp" value="' . $context->employee->id . '" />';
					$form .= '<input type="hidden" name="token" value="' . Tools::getValue('token') . '" />';
					$form .= '<input type="hidden" name="adminDir" value="' . $adminDir . '" />';
					$form .= '<p><table>';
					$form .= '<tr><td><label for="hipay_refund_type">' . $this->l('Refund type') . '</label></td><td>&nbsp;</td>';
					if ((boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_REFUNDED'))) {
						$form .= '<td>';
						$form .= '<input type="radio" onclick="javascript:document.getElementById(\'hidden1\').style.display=\'inline\';javascript:document.getElementById(\'hidden2\').style.display=\'inline\';" name="hipay_refund_type" id="hipay_refund_type" value="partial" checked />' . $this->l('Partial') . '</td></tr>';
					} else {
						$form .= '<td><input type="radio" onclick="javascript:document.getElementById(\'hidden1\').style.display=\'none\';javascript:document.getElementById(\'hidden2\').style.display=\'none\';" name="hipay_refund_type" value="complete" checked />' . $this->l('Complete') . '<br/>';
						$form .= '<input type="radio" onclick="javascript:document.getElementById(\'hidden1\').style.display=\'inline\';javascript:document.getElementById(\'hidden2\').style.display=\'inline\';" name="hipay_refund_type" id="hipay_refund_type" value="partial" />' . $this->l('Partial') . '</td></tr>';
					}
					$form .= '</table></p>';
					$form .= '<p>';
					if ((boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_REFUNDED'))) {
						$form .= '<label style="display:block;" id="hidden1" for="">' . $this->l('Refund amount') . '</label>';
						$form .= '<input style="display:block;" id="hidden2" type="text" name="hipay_refund_amount" value="" />';
					} else {
						$form .= '<label style="display:none;" id="hidden1" for="">' . $this->l('Refund amount') . '</label>';
						$form .= '<input style="display:none;" id="hidden2" type="text" name="hipay_refund_amount" value="" />';
					}
					$form .= '</p>';
					$form .= '<label>&nbsp;</label><input type="submit" name="hipay_refund_submit" class="btn btn-primary" value="' . $this->l('Refund') . '" />';
					$form .= '</form>';
				} else {
					$form .= $this->l('This order has already been fully refunded or refund is not allowed');
				}
				$form .= '</fieldset>';

				$form .= '</fieldset></div>';
			}
			$showCapture = false;
			if ($orderLoaded->current_state == Configuration::get('HIPAY_AUTHORIZED') || $orderLoaded->current_state == _PS_OS_PAYMENT_ || $orderLoaded->current_state == Configuration::get('HIPAY_PARTIALLY_CAPTURED')) {
				$showCapture = true;
			}
			if ($showCapture) {
				// Modification to allow a full capture if the previous state was HIPAY_PENDING or HIPAY_CHALLENGED
				$get_HIPAY_MANUALCAPTURE = Configuration::get('HIPAY_MANUALCAPTURE');

				$context = Context::getContext();
				if ((boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_PENDING'))
					|| (boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_CHALLENGED'))
				) {
					// Order was previously pending or challenged
					// Then check if its currently in authorized state
					if ($orderLoaded->current_state == Configuration::get('HIPAY_AUTHORIZED')) {

						$get_HIPAY_MANUALCAPTURE = 1;
					}
				} else {
					// Nothing to do, classical system behaviour will take over
				}

				// FORCING ORDER CAPTURED AMOUNT UPDATE
				$sql = "UPDATE `" . _DB_PREFIX_ . "order_payment`
                        SET `amount` = '" . $this->getOrderTotalAmountCaptured($orderLoaded->reference) . "'
                        WHERE `order_reference`='" . $orderLoaded->reference . "'";
				//Db::getInstance()->execute($sql);

				/**
				 * FORMULAIRE DE CAPTURE
				 */
				if (version_compare(_PS_VERSION_, '1.6', '>')) {
					$form .= '<div id="htmlcontent" class="panel">
	                 <div class="panel-heading"><img src="../img/admin/money.gif">&nbsp;&nbsp;' . $this->l('Hipay Capture') . '</div>
	                 <fieldset>';
				} else {
					$form .= '
		        		<div style="height:10px"></div>
		        		<div>
		        		<fieldset>';
					$form .= '<legend><img src="../img/admin/money.gif">&nbsp;&nbsp;' . $this->l('Hipay Capture') . '</legend>';
				}

				if ($get_HIPAY_MANUALCAPTURE) {

					if (Tools::getValue('hipay_err')) {
						if (Tools::getValue('hipay_err') == 'ok') {
							$form .= '<p style="" class="conf">
									<a style="position: relative; top: -100px;" id="hipay"></a>
			        				' . $this->l('Request successfully sent') . '
						        	</p>';
						} else {
							if(_PS_VERSION_ >= '1.6')
							{
								$form .= '<style media="screen" type="text/css">
								p.error{
									color:red;
								}
								</style>';
							}
							$form .= '<p style="" class="error">
									<a style="position: relative; top: -100px;" id="hipay"></a>
						        	' . Tools::getValue('hipay_err') . '
						        	</p>';
						}
					}

					$form_action = '../index.php?fc=module&module=' . $this->name . '&controller=capture';
					$form .= '
			        		<div style="height:10px"></div>
			        		<fieldset>
			        			<legend>' . $this->l('Capture this order') . '</legend>';
					$orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;

					$totalEncaissement = $this->getOrderTotalAmountCaptured($orderLoaded->reference);

					$stillToCapture = $orderTotal - $totalEncaissement;
					
					// Modif ajout texte warning si montant pas completement capture
					if($stillToCapture)
					{
						// Retrieve _PS_OS_PAYMENT_ real name
						$form .= '
						   <table class="table" width="100%" cellspacing="0" cellpadding="0">
						   <tr>
								<th>' . $this->l('The order has not been fully captured.') . '</th>
							</tr><tr>
								<th>' . $this->l('To generate the invoice, you must capture the remaining amount due which will generate an invoice once the order full amount has been captured.') . '</th>
						   </tr>
							</table>
							<p>&nbsp;</p>
							';
					}

					// summary of amount captured, still to capture
					$form .= '
                                       <table class="table" width="100%" cellspacing="0" cellpadding="0">
                                       <tr>
                                       	<th>' . $this->l('Amount already captured') . '</th>
                                       	<th>' . $this->l('Amount still to be captured') . '</th>
                                       </tr>
                                       <tr>
                                       	<td class="value"><span class="badge badge-success">' . Tools::displayPrice($totalEncaissement) . '</span></td>
                                       	<td class="value"><span class="badge badge-info">' . Tools::displayPrice($stillToCapture) . '</span></td>
                                       </tr>
                                       </table>';
					$form .= '<div style="font-size: 12px;">
					<sup>*</sup> ' . $this->l('Amounts will be updated once the capture will be confirmed by HiPay Fullservice') . '</div>';

					$adminDir = _PS_ADMIN_DIR_;
					$adminDir = Tools::substr($adminDir, strrpos($adminDir, '/'));
					$adminDir = Tools::substr($adminDir, strrpos($adminDir, '\\'));
					$adminDir = str_replace('\\', '', $adminDir);
					$adminDir = str_replace('/', '', $adminDir);

					$context = Context::getContext();

					// Last check
					// If state should not allow user to manually capture then disable display
					if ($currentState == _PS_OS_ERROR_ || $currentState == _PS_OS_CANCELED_
							/* || $currentState == _PS_OS_PAYMENT_ issue with partical capture returning state _PS_OS_PAYMENT_ */ || $currentState == Configuration::get('HIPAY_EXPIRED') || $currentState == Configuration::get('HIPAY_REFUND_REQUESTED') || $currentState == Configuration::get('HIPAY_REFUNDED')) {
						$stillToCapture = false;
					}
					if (($stillToCapture) && $hide_capture == false) {
						$form .= "<script>
                           $( document ).ready(function() {
                            $('#hipay_capture_form').submit( function(){
                                var type=$('[name=hipay_capture_type]:checked').val();
                                var proceed = 'true';
                                /*if(type=='partial')
                                {
                                    var amount=$('#hidden4').val();
                                    if(amount == '')
                                    {
                                        alert('" . $this->l('Please enter an amount') . "');
                                        proceed = 'false';
                                    }
                                    if(amount<=0)
                                    {
                                        alert('" . $this->l('Please enter an amount greater than zero') . "');
                                        proceed = 'false';
                                    }
                                    if(amount>" . $stillToCapture . ")
                                    {
                                        alert('" . $this->l('Amount exceeding authorized amount') . "');
                                        proceed = 'false';
                                    }
                                }*/
    
                                if(proceed == 'false')
                                {
                                    return false;
                                }else{
                                    return true;
                                }
    
                                return false;
                            });
					    });
                        </script>";
						$form .= '<form action="' . $form_action . '" method="post" id="hipay_capture_form">';
						$form .= '<input type="hidden" name="id_order" value="' . Tools::getValue('id_order') . '" />';
						$form .= '<input type="hidden" name="id_emp" value="' . $context->employee->id . '" />';
						$form .= '<input type="hidden" name="token" value="' . Tools::getValue('token') . '" />';
						$form .= '<input type="hidden" name="adminDir" value="' . $adminDir . '" />';
						$form .= '<p><table>';
						$form .= '<tr><td><label for="hipay_capture_type">' . $this->l('Capture type') . '</label></td><td>&nbsp;</td>';
						if ((boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_PARTIALLY_CAPTURED'))) {
							$form .= '<td>';
							$form .= '<input type="radio" onclick="javascript:document.getElementById(\'hidden3\').style.display=\'inline\';javascript:document.getElementById(\'hidden4\').style.display=\'inline\';" name="hipay_capture_type" id="hipay_capture_type" value="partial" checked />' . $this->l('Partial') . '</td></tr>';
						} else {
							$form .= '<td><input type="radio" onclick="javascript:document.getElementById(\'hidden3\').style.display=\'none\';javascript:document.getElementById(\'hidden4\').style.display=\'none\';" name="hipay_capture_type" value="complete" checked />' . $this->l('Complete') . '<br>';
							$form .= '<input type="radio" onclick="javascript:document.getElementById(\'hidden3\').style.display=\'inline\';javascript:document.getElementById(\'hidden4\').style.display=\'inline\';" name="hipay_capture_type" id="hipay_capture_type" value="partial" />' . $this->l('Partial') . '</td></tr>';
						}
						$form .= '</table></p>';
						$form .= '<p>';
						if ((boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_PARTIALLY_CAPTURED'))) {
							$form .= '<label style="display:block;" id="hidden3" >' . $this->l('Capture amount') . '</label>';
							$form .= '<input style="display:block;" id="hidden4" type="text" name="hipay_capture_amount" value="' . round($stillToCapture, 2) . '" />';
						} else {
							$form .= '<label style="display:none;" id="hidden3" >' . $this->l('Capture amount') . '</label>';
							$form .= '<input style="display:none;" id="hidden4" type="text" name="hipay_capture_amount" value="' . round($stillToCapture, 2) . '" />';
						}
						$form .= '</p>';
						$form .= '<label>&nbsp;</label><input type="submit" name="hipay_capture_submit" class="btn btn-primary" value="' . $this->l('Capture') . '" />';
						$form .= '</form>';
					} else {
						$form .= '<p>' . $this->l('This order has already been fully captured, cannot be captured or waiting authorization for capture') . '</p>';
					}

					$form .= '</fieldset>';
				}
				$form .= '</fieldset></div>';
			}
			return $form;
		}
	}

	public function hookHeader($params) {

	}

	public function hookDisplayHeader() {
		$this->context->controller->addCSS(_MODULE_DIR_ . $this->name . '/css/hipay2.css', 'all');		
	}
	public function hookDisplayBackOfficeHeader($params) { }
	

	public function checkCurrency($cart) {
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module ['id_currency'])
					return true;
		return false;
	}

	public function checkLocalCards() {
        if (file_exists(_PS_THEME_DIR_ . '/modules/' . $this->name . '/special_cards.xml')) {
            $local_cards = simplexml_load_file(_PS_THEME_DIR_ . '/modules/' . $this->name . '/special_cards.xml');
        } else if (file_exists(_PS_ROOT_DIR_ . '/modules/' . $this->name . '/special_cards.xml')) {
            $local_cards = simplexml_load_file(_PS_ROOT_DIR_ . '/modules/' . $this->name . '/special_cards.xml');
        } else {
			$local_cards = '';
		}

		return $local_cards;
	}

	public function startsWith($haystack, $needle) {
		return $needle === "" || strpos($haystack, $needle) === 0;
	}

	/**
	 * get total amount already captured by traversing the messages/log with keyword HIPAY_CAPTURE
	 *
	 * @return float
	 */
	public function getOrderTotalAmountCaptured($order_reference) {
		// refonte du calcul du montant restant à rembourser...
		$sum = 0.0;
		$sum_refund = 0.0;
		$sql = "SELECT * FROM `" . _DB_PREFIX_ . "order_payment` WHERE order_reference='" . pSQL($order_reference) . "';";
		if ($results = Db::getInstance()->ExecuteS($sql)){
			foreach ($results as $row){
				if($row['payment_method'] == 'HiPay - refund'){
					$sum_refund += $row['amount'];
				}else{
					$sum += $row['amount'];
				}
			}
		}		
		return ($sum + $sum_refund);
	}

	public function updateOrderCAPTUREmsg($id_order, $amount) {
		$tag = 'HiPay '; // don't forget the space!
		$msgs = Message::getMessagesByOrderId($id_order, true); // true for private messages (got example from AdminOrdersController)
		if (count($msgs))
			foreach ($msgs as $msg) {
				$line = $msg ['message'];
				if ($this->startsWith($line, $tag)) {
					$to_update_msg = new Message($msg ['id_message']);
					$to_update_msg->message = $tag . $amount;
					$to_update_msg->save();
					break;
				}
			}
	}

    private function addHooks()
    {
        return Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'hook` (`name`, `title`, `description`, `position`)'
            .' VALUES (\'displayHiPayAccepted\', \'After HiPay accepted\', \'Called just before rendering accept page.\', 1)'
            .', (\'displayHiPayCanceled\', \'After HiPay canceled\', \'Called just before rendering cancel page.\', 1)'
            .', (\'displayHiPayDeclined\', \'After HiPay declined\', \'Called just before rendering decline page.\', 1)'
            .', (\'displayHiPayException\', \'After HiPay payment exception\', \'Called just before rendering exception page.\', 1)'
            .', (\'displayHiPayPending\', \'After HiPay pending\', \'Called just before rendering pending page.\', 1)');
    }

    private function removesHooks()
    {
        return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'hook` WHERE `name` IN (\'displayHiPayAccepted\', \'displayHiPayCanceled\', \'displayHiPayDeclined\', \'displayHiPayException\', \'displayHiPayPending\')');
    }

}

