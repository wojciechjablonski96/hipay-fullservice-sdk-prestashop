<?php

/**
 * 2007-2013 Profileo NOTICE OF LICENSE This source file is subject to the Academic Free License (AFL 3.0) that is bundled with this package in the file LICENSE.txt. It is also available through the world-wide-web at this URL: http://opensource.org/licenses/afl-3.0.php If you did not receive a copy of the license and are unable to obtain it through the world-wide-web, please send an email to contact@profileo.com so we can send you a copy immediately. DISCLAIMER Do not edit or add to this file if you wish to upgrade Profileo to newer versions in the future. If you wish to customize Profileo for your needs please refer to http://www.profileo.com for more information. @author Profileo <contact@profileo.com> @copyright 2007-2013 Profileo International Registered Trademark & Property of Profileo
 */
if (!defined('_PS_VERSION_'))
	exit();

require_once (dirname(__FILE__) . '/classes/HipayClass.php');

class HiPay_Tpp extends PaymentModule {

	private $_html = '';
	private $_postErrors = array();

	public function __construct() {
		$this->name = 'hipay_tpp';
		$this->tab = 'payments_gateways';
		$this->version = '1.3.17';
		$this->module_key = 'e25bc8f4f9296ef084abf448bca4808a';
		$this->author = 'HiPay';

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		$config = Configuration::getMultiple(array(
					'HIPAY_API_USERNAME',
					'HIPAY_API_PASSWORD',
					'HIPAY_TEST_API_USERNAME',
					'HIPAY_TEST_API_PASSWORD',
					'HIPAY_TEST_MODE',
					'HIPAY_PAYMENT_MODE',
					'HIPAY_CHALLENGE_URL',
					'HIPAY_CSS_URL',
					'HIPAY_ALLOWED_CARDS',
					'HIPAY_ALLOWED_LOCAL_CARDS',
					'HIPAY_THREEDSECURE',
					'HIPAY_THREEDSECURE_AMOUNT',
					'HIPAY_MANUALCAPTURE',
					'HIPAY_MEMORIZE',
					'HIPAY_TEMPLATE_MODE',
					'HIPAY_SELECTOR_MODE',
					'HIPAY_IFRAME_WIDTH',
					'HIPAY_IFRAME_HEIGHT'
		));

		// For callbacks
		$config = Configuration::getMultiple(array(
					'HIPAY_PROCESSING_QUEUE',
					'HIPAY_LAST_PROCESS'
		));

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('HiPay Fullservice');
		$this->description = $this->l('Accept transactions worldwide on any device with local & international payment methods. Benefit from a next-gen fraud protection tool.');
		$this->confirmUninstall = $this->l('Are you sure you wish to uninstall HiPay Fullservice?');

		if (!count(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module.');

		/** Backward compatibility */
		if (_PS_VERSION_ < '1.5')
			require (_PS_MODULE_DIR_ . $this->name . '/backward_compatibility/backward.php');
			
		global $smarty;
		$smarty->assign(array(
			'hipay_version' => $this->version,
		));
	}

	public function install() {

		// HipayLogger::createTables();
		// $this->_installOrderState();
		if (!parent::install() || !$this->registerHook('footer') || !$this->registerHook('header') || !HipayLogger::createTables() || !$this->_installOrderState() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn') || !$this->registerHook('adminOrder') || !$this->registerHook('backOfficeHeader')) {
			return false;
		}

		Configuration::updateValue('HIPAY_PROCESSING_QUEUE', 0);
		Configuration::updateValue('HIPAY_LAST_PROCESS', time());
		return true;
	}

	public function uninstall() {
		if (!parent::uninstall() || !HipayLogger::DropTables() || !Configuration::deleteByName('HIPAY_API_USERNAME') || !Configuration::deleteByName('HIPAY_API_PASSWORD') || !Configuration::deleteByName('HIPAY_TEST_API_USERNAME') || !Configuration::deleteByName('HIPAY_TEST_API_PASSWORD') || !Configuration::deleteByName('HIPAY_TEST_MODE') || !Configuration::deleteByName('HIPAY_PAYMENT_MODE') || !Configuration::deleteByName('HIPAY_CHALLENGE_URL') || !Configuration::deleteByname('HIPAY_CSS_URL') || !Configuration::deleteByname('HIPAY_ALLOWED_CARDS') || !Configuration::deleteByname('HIPAY_TEMPLATE_MODE') || !Configuration::deleteByname('HIPAY_SELECTOR_MODE') || !Configuration::deleteByname('HIPAY_IFRAME_WIDTH') || !Configuration::deleteByname('HIPAY_IFRAME_HEIGHT') || !Configuration::deleteByname('HIPAY_ALLOWED_LOCAL_CARDS') || !Configuration::deleteByname('HIPAY_THREEDSECURE') || !Configuration::deleteByname('HIPAY_THREEDSECURE_AMOUNT') || !Configuration::deleteByname('HIPAY_MANUALCAPTURE') || !Configuration::deleteByname('HIPAY_MEMORIZE') || !parent::uninstall())
			return false;
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
		if (version_compare(_PS_VERSION_, '1.5', '>')) {
			$cookie = $this->context->cookie;
		} else {
			global $cookie;
		}

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
			$OS->invoice = false;
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
			$OS->invoice = false;
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
			$OS->invoice = false;
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
			$OS->invoice = false;
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
			$OS->invoice = false;
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
			$OS->invoice = false;
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
			$OS->invoice = false;
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
			$OS->invoice = false;
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
		// $this->_html .= $this->renderFormLocalCards();
		$this->_html .= '<br />';
		$this->_html .= $this->renderFormLogs();

		return $this->_html;
	}

	private function _displayHiPay() {
		global $smarty;
		$smarty->assign(array(
			'this_callback' => HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/validation.php'),
			'this_ip' => getenv("SERVER_ADDR"),
			'this_path_bw' => $this->_path,
			'this_cron' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/validation.php?token='.Tools::getToken(false),
			'this_config_cron' => '*/5 * * * * wget '.Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/validation.php?token='.Tools::getToken(false),
		));

		return $this->display(__FILE__, '1.4/views/templates/hook/infos.tpl');
	}

	/**
	 * Interface de configuration
	 */
	public function renderForm() {
		global $smarty;
		$html = '';
		Tools::addJS(_MODULE_DIR_ . $this->name . '/js/14hipay.js');
		$currencies_module = $this->getCurrency();

		// Retrieve stored variables or set defaults
		$HIPAY_API_USERNAME = Tools::getValue('HIPAY_API_USERNAME', Configuration::get('HIPAY_API_USERNAME'));
		$HIPAY_API_PASSWORD = Tools::getValue('HIPAY_API_PASSWORD', Configuration::get('HIPAY_API_PASSWORD'));
		$HIPAY_TEST_API_USERNAME = Tools::getValue('HIPAY_TEST_API_USERNAME', Configuration::get('HIPAY_TEST_API_USERNAME'));
		$HIPAY_TEST_API_PASSWORD = Tools::getValue('HIPAY_TEST_API_PASSWORD', Configuration::get('HIPAY_TEST_API_PASSWORD'));
		$HIPAY_TEST_MODE = Tools::getValue('HIPAY_TEST_MODE', Configuration::get('HIPAY_TEST_MODE'));
		switch ($HIPAY_TEST_MODE) {
			case 1 :
			default :
				$hipay_demo_on = ' checked="checked" ';
				$hipay_demo_off = '';
				break;
			case 0 :
				$hipay_demo_on = '';
				$hipay_demo_off = ' checked="checked" ';
				break;
		}
		$HIPAY_PAYMENT_MODE = Tools::getValue('HIPAY_PAYMENT_MODE', Configuration::get('HIPAY_PAYMENT_MODE'));
		switch ($HIPAY_PAYMENT_MODE) {
			case 0 :
			default :
				$hipay_payment_mode_0 = ' selected="selected" ';
				$hipay_payment_mode_1 = '';
				$hipay_payment_mode_2 = '';
				break;
			case 1 :
				$hipay_payment_mode_0 = '';
				$hipay_payment_mode_1 = ' selected="selected" ';
				$hipay_payment_mode_2 = '';
				break;
			case 2 :
				$hipay_payment_mode_0 = '';
				$hipay_payment_mode_1 = '';
				$hipay_payment_mode_2 = ' selected="selected" ';
				break;
		}

		$HIPAY_IFRAME_WIDTH = Tools::getValue('HIPAY_IFRAME_WIDTH', Configuration::get('HIPAY_IFRAME_WIDTH'));
		$HIPAY_IFRAME_HEIGHT = Tools::getValue('HIPAY_IFRAME_HEIGHT', Configuration::get('HIPAY_IFRAME_HEIGHT'));

		$HIPAY_TEMPLATE_MODE = Tools::getValue('HIPAY_TEMPLATE_MODE', Configuration::get('HIPAY_TEMPLATE_MODE'));
		switch ($HIPAY_TEMPLATE_MODE) {
			case 'basic' :
			default :
				$hipay_template_mode_basic = ' selected="selected" ';
				$hipay_template_mode_basic_js = '';
				break;
			case 'basic-js' :
				$hipay_template_mode_basic = '';
				$hipay_template_mode_basic_js = ' selected="selected" ';
				break;
		}

		$HIPAY_SELECTOR_MODE = Tools::getValue('HIPAY_SELECTOR_MODE', Configuration::get('HIPAY_SELECTOR_MODE'));
		switch ($HIPAY_SELECTOR_MODE) {
			case 0 :
			default :
				$hipay_selector_mode_0 = ' selected="selected" ';
				$hipay_selector_mode_1 = '';
				break;
			case 1 :
				$hipay_selector_mode_0 = '';
				$hipay_selector_mode_1 = ' selected="selected" ';
				break;
		}

		$HIPAY_CHALLENGE_URL = Tools::getValue('HIPAY_CHALLENGE_URL', Configuration::get('HIPAY_CHALLENGE_URL'));
		$HIPAY_CSS_URL = Tools::getValue('HIPAY_CSS_URL', Configuration::get('HIPAY_CSS_URL'));
		$HIPAY_THREEDSECURE = Tools::getValue('HIPAY_THREEDSECURE', Configuration::get('HIPAY_THREEDSECURE'));
		switch ($HIPAY_THREEDSECURE) {
			case 1 :
			default :
				$hipay_threedsecure_on = ' checked="checked" ';
				$hipay_threedsecure_off = '';
				break;
			case 0 :
				$hipay_threedsecure_on = '';
				$hipay_threedsecure_off = ' checked="checked" ';
				break;
		}
		$str = Tools::getValue('HIPAY_THREEDSECURE_AMOUNT', Configuration::get('HIPAY_THREEDSECURE_AMOUNT'));
		$str = str_replace(".", ",", $str);
		$HIPAY_THREEDSECURE_AMOUNT = $str;
		$HIPAY_MANUALCAPTURE = Tools::getValue('HIPAY_MANUALCAPTURE', Configuration::get('HIPAY_MANUALCAPTURE'));
		switch ($HIPAY_MANUALCAPTURE) {
			case 1 :
			default :
				$hipay_manualcapture_on = ' checked="checked" ';
				$hipay_manualcapture_off = '';
				break;
			case 0 :
				$hipay_manualcapture_on = '';
				$hipay_manualcapture_off = ' checked="checked" ';
				break;
		}
		$HIPAY_MEMORIZE = Tools::getValue('HIPAY_MEMORIZE', Configuration::get('HIPAY_MEMORIZE'));
		switch ($HIPAY_MEMORIZE) {
			case 1 :
			default :
				$hipay_memorize_on = ' checked="checked" ';
				$hipay_memorize_off = '';
				break;
			case 0 :
				$hipay_memorize_on = '';
				$hipay_memorize_off = ' checked="checked" ';
				break;
		}

		// Recup des cartes
		// Update display choix des cartes when saving
		$hasCardValues = false; // Initialize to false for double check below
		$card_selection_american_express = '';
		$card_selection_bcmc = '';
		$card_selection_cb = '';
		$card_selection_maestro = '';
		$card_selection_mastercard = '';
		$card_selection_visa = '';

		// Do a double check on the config file because when just opening the config, the Tools::getValue[] is always empty.
		// If no Tools::getValue detected, then check directly from old config values
		if ($hasCardValues === false) {
			$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');
			$cart_arr = explode(',', $card_str);
			foreach ($cart_arr as $key => $value) {
				if ($value == 'visa') {
					$card_selection_visa = ' checked="checked" ';
				}
				if ($value == 'mastercard') {
					$card_selection_mastercard = ' checked="checked" ';
				}
				if ($value == 'american-express') {
					$card_selection_american_express = ' checked="checked" ';
				}
				if ($value == 'bcmc') {
					$card_selection_bcmc = ' checked="checked" ';
				}
				if ($value == 'cb') {
					$card_selection_cb = ' checked="checked" ';
				}
				if ($value == 'maestro') {
					$card_selection_maestro = ' checked="checked" ';
				}
			}
		}
		// End of Retrieve stored variables or set defaults
		$html .= "
                <script type=\"text/javascript\">
                    $(document).ready(function() {
                        //Onload check for visibility
                        if($('#hipay_threedsecure_on').is(\":checked\")){
                            $('.3D_secure').parent().parent().show();
                            $('.3D_secure').parent().parent().prev('label').show()
                        }else{
                            $('.3D_secure').parent().parent().hide();
                            $('.3D_secure').parent().parent().prev('label').hide()
                        }

                        $('#hipay_threedsecure_on').change(function(){
                            $('.3D_secure').parent().parent().show();
                            $('.3D_secure').parent().parent().prev('label').show()
                        });


                        $('#hipay_threedsecure_off').change(function(){
                            $('.3D_secure').parent().parent().hide();
                            $('.3D_secure').parent().parent().prev('label').hide()
                        });

                        //Check if iFrame selected
                        //alert($('#HIPAY_IFRAME_WIDTH').val('100%'));
                        if($('#HIPAY_IFRAME_WIDTH').val() == '')  $('#HIPAY_IFRAME_WIDTH').val('100%');
                        if($('#HIPAY_IFRAME_HEIGHT').val() == '')  $('#HIPAY_IFRAME_HEIGHT').val('670');
                        var hipaypaymentmode = $('#HIPAY_PAYMENT_MODE option:selected').val();
                        if(hipaypaymentmode == '1'){
                            $('.IFRAME_SIZE').parent().parent().show();
                            $('.IFRAME_SIZE').parent().parent().prev('label').show()
                        }else{
                            $('.IFRAME_SIZE').parent().parent().hide();
                            $('.IFRAME_SIZE').parent().parent().prev('label').hide()
                        }

                        $('#HIPAY_PAYMENT_MODE').change(function(){
                            var hipaypaymentmode = $('#HIPAY_PAYMENT_MODE option:selected').val();
                            if(hipaypaymentmode == '1'){
                                $('.IFRAME_SIZE').parent().parent().show();
                                $('.IFRAME_SIZE').parent().parent().prev('label').show()
                            }else{
                                $('.IFRAME_SIZE').parent().parent().hide();
                                $('.IFRAME_SIZE').parent().parent().prev('label').hide()
                            }
                        });
                        //End Check if iFrame selected

                    });
                </script>
        ";

		$html .= '
                <form action="' . Tools::htmlentitiesUTF8($_SERVER ['REQUEST_URI']) . '" method="post">
                    <fieldset>
						<legend><img src="../img/admin/contact.gif" />' . $this->l('Configuration Module Hipay TPP') . '</legend>
                        <table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('HiPay API Username :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_API_USERNAME" value="' . $HIPAY_API_USERNAME . '" style="width: 300px;" />
                                    <p>' . $this->l('Your API Username') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('HiPay API Password :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_API_PASSWORD" value="' . $HIPAY_API_PASSWORD . '" style="width: 300px;" />
                                    <p>' . $this->l('Your API Password') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('HiPay Test API Username :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_TEST_API_USERNAME" value="' . $HIPAY_TEST_API_USERNAME . '" style="width: 300px;" />
                                    <p>' . $this->l('Your Test API Username') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('HiPay Test API Password :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_TEST_API_PASSWORD" value="' . $HIPAY_TEST_API_PASSWORD . '" style="width: 300px;" />
                                    <p>' . $this->l('Your Test API Password') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Switch to mode :') . '<p /></td>
                                <td>
                                    <input type="radio" name="HIPAY_TEST_MODE" value="1" id="hipay_demo_on" ' . $hipay_demo_on . ' /> ' . $this->l('Test') . '
                                    <input type="radio" name="HIPAY_TEST_MODE" value="0" id="hipay_demo_off" ' . $hipay_demo_off . ' /> ' . $this->l('Production') . '
                                    <p>' . $this->l('Switch to test mode (Pre-production mode).') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Operating Mode :') . '<p /></td>
                                <td>
                                    <select id="HIPAY_PAYMENT_MODE" class="" name="HIPAY_PAYMENT_MODE">
                                        <option ' . $hipay_payment_mode_0 . ' value="0">' . $this->l('Dedicated Page') . '</option>
                                        <option ' . $hipay_payment_mode_1 . ' value="1">' . $this->l('IFrame') . '</option>
                                        <option ' . $hipay_payment_mode_2 . ' value="2">' . $this->l('API') . '</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('iFrame width :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_IFRAME_WIDTH" id="HIPAY_IFRAME_WIDTH" class="IFRAME_SIZE" value="' . $HIPAY_IFRAME_WIDTH . '" style="width: 300px;" />
                                    <p>' . $this->l('iFrame width (100% by default)') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('iFrame height :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_IFRAME_HEIGHT" id="HIPAY_IFRAME_HEIGHT" class="IFRAME_SIZE" value="' . $HIPAY_IFRAME_HEIGHT . '" style="width: 300px;" />
                                    <p>' . $this->l('iFrame height (670 by default)') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Hosted page template :') . '<p /></td>
                                <td>
                                    <select id="HIPAY_TEMPLATE_MODE" class="" name="HIPAY_TEMPLATE_MODE">
                                        <option ' . $hipay_template_mode_basic_js . ' value="basic-js">basic-js</option>
                                        <option ' . $hipay_template_mode_basic . ' value="basic">basic</option>
                                    </select>
                                    <p>' . $this->l('Basic template showed on Hosted page.') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Display card selector :') . '<p /></td>
                                <td>
                                    <select name="HIPAY_SELECTOR_MODE" class="" id="HIPAY_SELECTOR_MODE">
                                        <option value="0" ' . $hipay_selector_mode_0 . '>Afficher le menu de sélection de carte</option>
                                        <option value="1" ' . $hipay_selector_mode_1 . '>Ne pas afficher le menu de sélection de carte</option>
                                    </select>
                                    <p>' . $this->l('Display card selector on iFrame or Hosted page.') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Challenge status URL :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_CHALLENGE_URL" id="HIPAY_CHALLENGE_URL" value="' . $HIPAY_CHALLENGE_URL . '" style="width: 300px;" />
                                    <p>' . $this->l('Redirection page for the challenge status') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('URL CSS :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_CSS_URL" id="HIPAY_CSS_URL" value="' . $HIPAY_CSS_URL . '" style="width: 300px;" />
                                    <p>' . $this->l('URL for css to style your merchant page') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Types de authorised cards :') . '<p /></td>
                                <td>
                                    <input type="checkbox" value="american-express" class="" id="card_selection_american-express" name="card_selection_american-express" ' . $card_selection_american_express . ' >
                                    <label class="t" for="card_selection_american-express"><strong>American Express - for - card_selection_american-express</strong></label><br>
                                    <input type="checkbox" value="bcmc" class="" id="card_selection_bcmc" name="card_selection_bcmc" ' . $card_selection_bcmc . ' >
                                    <label class="t" for="card_selection_bcmc"><strong>Bancontact / Mister Cash - for - card_selection_bcmc</strong></label><br>
                                    <input type="checkbox" value="cb" class="" id="card_selection_cb" name="card_selection_cb" ' . $card_selection_cb . ' >
                                    <label class="t" for="card_selection_cb"><strong>Carte Bancaire - for - card_selection_cb</strong></label><br>
                                    <input type="checkbox" value="maestro" class="" id="card_selection_maestro" name="card_selection_maestro" ' . $card_selection_maestro . ' >
                                    <label class="t" for="card_selection_maestro"><strong>Maestro - for - card_selection_maestro</strong></label><br>
                                    <input type="checkbox" value="mastercard" class="" id="card_selection_mastercard" name="card_selection_mastercard" ' . $card_selection_mastercard . '>
                                    <label class="t" for="card_selection_mastercard"><strong>MasterCard - for - card_selection_mastercard</strong></label><br>
                                    <input type="checkbox" value="visa" class="" id="card_selection_visa" name="card_selection_visa" ' . $card_selection_visa . ' >
                                    <label class="t" for="card_selection_visa"><strong>Visa - for - card_selection_visa</strong></label><br>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Activate 3D Secure :') . '<p /></td>
                                <td>
                                    <img src="../img/admin/enabled.gif" /> <input type="radio" name="HIPAY_THREEDSECURE" id="hipay_threedsecure_on" value="1" ' . $hipay_threedsecure_on . ' />
                                    <img src="../img/admin/disabled.gif" /> <input type="radio" name="HIPAY_THREEDSECURE" id="hipay_threedsecure_off" value="0" ' . $hipay_threedsecure_off . ' />
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('3D Secure minimum amount :') . '<p /></td>
                                <td>
                                    <input type="text" name="HIPAY_THREEDSECURE_AMOUNT" id="HIPAY_THREEDSECURE_AMOUNT" class="3D_secure" value="' . $HIPAY_THREEDSECURE_AMOUNT . '" style="width: 300px;" />
                                    <p>' . $this->l('Minimum amount for 3D secure to activate') . '</p>
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Switch to capture :') . '<p /></td>
                                <td>
                                    <input type="radio" name="HIPAY_MANUALCAPTURE" value="1" id="hipay_manualcapture_on" ' . $hipay_manualcapture_on . ' /> ' . $this->l('manual') . '
                                    <input type="radio" name="HIPAY_MANUALCAPTURE" value="0" id="hipay_manualcapture_off" ' . $hipay_manualcapture_off . ' /> ' . $this->l('automatic') . '
                                </td>
                            </tr>
                            <tr>
                                <td width="130" style="height: 35px;">' . $this->l('Allow Memorization of card tokens  :') . '<p /></td>
                                <td>
                                    <img src="../img/admin/enabled.gif" /> <input type="radio" name="HIPAY_MEMORIZE" value="1" id="hipay_memorize_on" ' . $hipay_memorize_on . ' />
                                    <img src="../img/admin/disabled.gif" /> <input type="radio" name="HIPAY_MEMORIZE" value="0" id="hipay_memorize_off" ' . $hipay_memorize_off . ' />
                                    <p>' . $this->l('Allow user to memorize card token, as well as provide feature to select memorized tokens') . '</p>
                                </td>
                            </tr>



                            <tr><td colspan="2" align="center"><input class="button" name="btnSubmit" value="' . $this->l('Update settings') . '" type="submit" /></td></tr>
                    </table>
                    </fieldset>
                </form>';

		return $html;
	}

	// Form to render local cards
	public function renderFormLocalCards() {
		$local_cards = $this->checkLocalCards();
		if ($local_cards == '') {
			$html = '';
		} else {
			$token = Tools::getAdminTokenLite('AdminModules');
			$html = '<br/>';
			// $html = '<div>';
			// $html .= '<form enctype="multipart/form-data" method="post" action="index.php?controller=AdminModules&amp;configure=' . $this->name . '&amp;tab_module=payments_gateways&amp;module_name=' . $this->name . '&amp;btnLocalCardsubmit=1&amp;token=' . $token . '" class="defaultForm " id="module_form">';

			if (version_compare(_PS_VERSION_, '1.6', '>')) {
				$html .= '<div class="panel-heading"><i class="icon-globe"></i>' . $this->l('Local cards') . '</div>';
			} else {
				$html .= '<fieldset id="fieldset_1">
                            <legend>' . $this->l('Local cards') . '</legend>';
			}
			// $html .= '<div class="margin-form">';
			$html .= $this->l('Local cards header') . "<br/>";
			$html .= '<table cellpadding="0" cellspacing="0" class="table">';
			$html .= '<tbody><tr><th style="width: 200px">' . $this->l('Local cards') . '</th><th style="text-align: center">' . $this->l('Activate') . '</th><th style="text-align: center">' . $this->l('Available currencies') . '</th></tr>';

			foreach ($local_cards as $key => $value) {
				$html .= '<tr><td><label class="t" for="card_selection_' . (string) $value->code . '"><strong>' . (string) $value->name . '</strong></label></td>';
				$html .= '<td style="text-align: center"><input type="checkbox" ' . $this->checkLocalCardifChecked((string) $value->code) . ' value="' . (string) $value->code . '" class="" id="card_selection_' . (string) $value->code . '" name="local_card_selection_' . (string) $value->code . '"></td>';
				$html .= '<td style="text-align: center">';
				foreach ($value->currencies as $key => $value) {
					foreach ($value->iso_code as $key => $value) {
						$html .= Tools::strtoupper((string) $value) . ' ';
					}
				}
				$html .= '</td></tr>';
			}
			$html .= '</tbody></table>';
			// $html .= '<div class="clear"></div>
			// </div>';
			// $html .= '<div class="margin-form">';
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

		// $html .= '<form enctype="multipart/form-data" method="post" action="index.php?controller=AdminModules&amp;configure=' . $this->name . '&amp;tab_module=payments_gateways&amp;module_name=' . $this->name . '&amp;btnCurrencyCardsubmit=1&amp;token=' . $token . '" class="defaultForm " id="module_form">';
		$html .= '<form action="' . Tools::htmlentitiesUTF8($_SERVER ['REQUEST_URI']) . '" method="post" class="defaultForm" id="module_form">';

		if (version_compare(_PS_VERSION_, '1.6', '>')) {
			$html .= '<br/><div class="panel-heading"><i class="icon-money"></i>' . $this->l('Authorized currencies by credit card') . '</div>';
		} else {
			$html .= '<fieldset id="fieldset_1">
                            <legend>' . $this->l('Authorized currencies by credit card') . '</legend>';
		}

		// $html .= '<div class="margin-form">';
		$html .= $this->l('Currencies cards header') . "<br/>";
		$html .= '<table cellpadding="0" cellspacing="0" class="table">';
		$html .= '<tbody><tr><th style="width: 200px"></th>';

		// Currencies cols
		foreach ($currencies_module as $key => $value) {
			$html .= '<th style="text-align: center">' . $value ['iso_code'] . '</th>';
		}
		$html .= '</tr>';

		// Credit cards rows
		foreach ($selection_cards as $ccode => $cvalue) {
			$html .= '<tr><td><strong>' . $cvalue . '</strong></td>';
			foreach ($currencies_module as $key => $value) {
				$html .= '<td style="text-align: center"><input type="checkbox" ' . $this->checkCurrencyCardifChecked((string) $value ['iso_code'] . '-' . $ccode) . ' value="' . (string) $value ['iso_code'] . '-' . $ccode . '" class="" id="card_selection_' . (string) $value ['iso_code'] . '-' . $ccode . '" name="currency_card_selection_' . (string) $value ['iso_code'] . '-' . $ccode . '"></td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';
		// $html .= '<div class="clear"></div>
		// </div>';
		// $html .= '<div class="margin-form">';
		if (version_compare(_PS_VERSION_, '1.6', '>')) {
			$html .= '<button type="submit" value="1" id="module_form_submit_btn" name="btnLocalCardsubmit" class="btn btn-default"><i class="process-icon-save"></i> ' . $this->l('Save') . '</button>';
		} else {
			$html .= '<input id="module_form_submit_btn" class="btn btn-default" type="submit" name="btnLocalCardsubmit" value="' . $this->l('Save') . '">';
		}
		// $html .= '</div>';
		// $html .= '</div>';

		if (version_compare(_PS_VERSION_, '1.6', '>')) {

		} else {
			$html .= '</fieldset>';
		}
		// $html .= '</form></div>';
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
		$context = $context = Context::getContext();
		$language_code = HipayClass::getLanguageCode($context->language->iso_code);
		Tools::addCSS(_MODULE_DIR_ . $this->name . '/css/14logstable.css', 'all');
		Tools::addJS(_MODULE_DIR_ . $this->name . '/js/logs_' . $language_code . '.js');
		Tools::addJS(_MODULE_DIR_ . $this->name . '/js/jquery.dataTables.min.js');

		$logs = Db::getInstance()->executeS('
			SELECT id, name, date, level, message
			FROM `' . _DB_PREFIX_ . 'hipay_logs` ORDER BY id DESC LIMIT 0, 6000
		');
		$html = '';

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
				if (floatval($str) < 0 || !is_numeric($str)) {
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
			Configuration::updateValue('HIPAY_TEST_API_USERNAME', Tools::getValue('HIPAY_TEST_API_USERNAME'));
			Configuration::updateValue('HIPAY_TEST_API_PASSWORD', Tools::getValue('HIPAY_TEST_API_PASSWORD'));
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
		if (Tools::getValue('card_selection_visa'))
                $card_arr [0] = Tools::getValue('card_selection_visa');
			if (Tools::getValue('card_selection_mastercard'))
                $card_arr [1] = Tools::getValue('card_selection_mastercard');
			if (Tools::getValue('card_selection_maestro'))
                $card_arr [2] = Tools::getValue('card_selection_maestro');
            if (Tools::getValue('card_selection_cb'))
                $card_arr [3] = Tools::getValue('card_selection_cb');
			if (Tools::getValue('card_selection_american-express'))
                $card_arr [5] = Tools::getValue('card_selection_american-express');
			if (Tools::getValue('card_selection_bcmc'))
                $card_arr [4] = Tools::getValue('card_selection_bcmc');
			$card_str = implode(',', $card_arr);
			Configuration::updateValue('HIPAY_ALLOWED_CARDS', $card_str);
		}
		HipayLogger::addLog($this->l('Hipay BO updated'), HipayLogger::NOTICE, 'The HiPay backoffice params have been updated');
		$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}

	public function execAccept($cart) {
		// Disconnect User from cart
		HipayClass::unsetCart();
		return $this->display(__FILE__, '1.4/views/templates/front/payment_accept.tpl');
	}

	public function execDecline($cart) {
		// Disconnect User from cart
		HipayClass::unsetCart();
		return $this->display(__FILE__, '1.4/views/templates/front/payment_decline.tpl');
	}

	public function execCancel($cart) {
		// Disconnect User from cart
		HipayClass::unsetCart();
		return $this->display(__FILE__, '1.4/views/templates/front/payment_cancel.tpl');
	}

	public function execException($cart) {
		// Disconnect User from cart
		HipayClass::unsetCart();
		return $this->display(__FILE__, '1.4/views/templates/front/payment_exception.tpl');
	}

	public function execPending($cart) {
		// Disconnect User from cart
		HipayClass::unsetCart();
		return $this->display(__FILE__, '1.4/views/templates/front/payment_pending.tpl');
	}

	public function execIframe($cart) {
		$hipay = new HiPay_Tpp ();
		// Acceptable return status for iframe :
		// Accept, decline, cancel and exception
		// Default value = exception
		$return_status = Tools::getValue("return_status", "exception");

		switch ($return_status) {
			case 'accept' :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14accept.php');
				break;
			case 'decline' :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14decline.php');
				break;
			case 'cancel' :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14cancel.php');
				break;
			case 'pending' :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14pending.php');
				// Implementing challenge url
				// Redirecting to challenge url if url present
				if (Configuration::get('HIPAY_CHALLENGE_URL')) {
					$redirect_url = Configuration::get('HIPAY_CHALLENGE_URL');
				}
				break;
			case 'exception' :
			default :
				$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14exception.php');
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

	public function execPayment($cart) {
		if (!$this->active)
			return;

		global $cookie, $smarty;

		// $this->display_column_left = false;
		// $this->display_column_right = false;
		
		// Check if cart_id has already been stored in tbl cart_sent
		$cart_id_count = @Db::getInstance()->getValue("SELECT COUNT( cart_id ) FROM  `" . _DB_PREFIX_ . "hipay_cart_sent` WHERE cart_id = '".(int)$cart->id."'");
		if($cart_id_count==0)
		{
			$sql_add_cart_id = "INSERT INTO `" . _DB_PREFIX_ . "hipay_cart_sent` (`cart_id`, `timestamp`)
            VALUES('" . (int)$cart->id . "', NOW() )";
			@Db::getInstance()->execute( $sql_add_cart_id );
		}else{
			// Found. Duplicate cart
			$duplicate_status_msg = HipayClass::duplicateCart();
			if($duplicate_status_msg)
			{
				$override_payment_mode = true;
			}
		}
		

		$smarty->assign(array(
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->getCurrency((int) $cart->id_currency),
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
		));

		Tools::addCSS(_MODULE_DIR_ . $this->name . '/css/hipay.css');
		Tools::addJS(_MODULE_DIR_ . $this->name . '/js/14hipay.js');

		$hipay_payment_mode = Configuration::get('HIPAY_PAYMENT_MODE');

		if (Tools::getValue('cartMemorizeToken')) {
			$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "hipay_tokens_tmp` (`cart_id`) VALUES('" . (int)$cart->id . "')";
			@Db::getInstance()->execute($sql_insert);
		}

		// Initializing the payment mode to the default configuration mode
		$payment_mode = Configuration::get('HIPAY_PAYMENT_MODE');

		// Check card used - if card used is a local card, force mode 'dedicated page'
		if (Tools::isSubmit('localcardToken') && tools::getValue('localcardToken')) {
			// Override to mode page dedicated
			$payment_mode = 3;
		}
		
		// Last check, if $override_payment_mode = true then override all payement modes and force error message display
		if($override_payment_mode) {
			// Override to mode page cart duplicated
			$payment_mode = 4;
			// Use $duplicate_status_msg to display msg err
		}

		// Different calls depending on Payment mode
		switch ($payment_mode) {
			case 1 :
				// Mode Iframe
				$data = HipayApi::getApiData($cart, 'iframe');

				$response = HipayApi::restApi('hpayment', $data);

				// Update to display montant
				$currency_array = $this->getCurrency((int) $cart->id_currency);
				$currency = $currency_array [0] ['iso_code'];
				foreach ($currency_array as $key => $value) {
					if ($value ['id_currency'] == $cart->id_currency) {
						$actual_currency = $value ['iso_code'];
					}
				}
				if ($currency != $actual_currency)
					$currency = $actual_currency;

				if (Tools::strlen(Configuration::get('HIPAY_IFRAME_WIDTH')) > 0)
					$iframe_width = Configuration::get('HIPAY_IFRAME_WIDTH');
				else
					$iframe_width = '100%';
				if (Tools::strlen(Configuration::get('HIPAY_IFRAME_HEIGHT')) > 0)
					$iframe_height = Configuration::get('HIPAY_IFRAME_HEIGHT');
				else
					$iframe_height = '670';

				$this->context->smarty->assign(array(
					'iframe_url' => $response->forwardUrl,
					'cart_id' => $cart->id,
					'currency' => $currency,
					'amount' => $cart->getOrderTotal(true, Cart::BOTH),
					'iframe_width' => $iframe_width,
					'iframe_height' => $iframe_height
				));

				// $payment_tpl = 'payment_execution_iframe.tpl';
				return $this->display(__FILE__, '1.4/views/templates/front/payment_execution_iframe.tpl');
				break;

			case 2 :
				// Mode API
				// Mode API
				// Constructs data array and sends it as a parameter to the tpl
				$currency_array = $this->getCurrency((int) $cart->id_currency);
				$currency = $currency_array [0] ['iso_code'];
				foreach ($currency_array as $key => $value) {
					if ($value ['id_currency'] == $cart->id_currency) {
						$actual_currency = $value ['iso_code'];
					}
				}
				if ($currency != $actual_currency)
					$currency = $actual_currency;

				$this->context->smarty->assign(array(
					'status_error' => '200', // Force to ok for first call
					'cart_id' => $cart->id,
					'currency' => $currency,
					'amount' => $cart->getOrderTotal(true, Cart::BOTH)
				));
				// Tpl will load a form that will store those infomations.

				$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');

				$selection_cards = array(
					'american-express' => $this->l('American Express'),
					'bcmc' => $this->l('Bancontact / Mister Cash'),
					'cb' => $this->l('Carte Bancaire'),
					'maestro' => $this->l('Maestro'),
					'mastercard' => $this->l('MasterCard'),
					'visa' => $this->l('Visa')
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

				$this->context->smarty->assign(array(
					'cartes' => $carte
				));

				$tokens = HipayToken::getTokens($cart->id_customer); //
				if (isset($tokens ['0'])) {
					$token_display = 'true';
				} else {
					$token_display = 'false';
				}

				$allow_memorize = HipayClass::getShowMemorization();

				$this->context->smarty->assign(array(
					'token_display' => $token_display,
					'allow_memorize' => $allow_memorize,
					'tokens' => $tokens
				));

				// Assign paths
				$smarty->assign(array(
					'this_path' => $this->_path,
					'this_path_bw' => $this->_path,
					'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
				));

				// $payment_tpl = 'payment_execution_api.tpl';
				return $this->display(__FILE__, '1.4/views/templates/front/payment_execution_api.tpl');
				break;
			case 3 :
				$local_card = tools::getValue('localcardToken');

				$data = HipayApi::getApiData($cart, null, null, $local_card);

				if ($local_card == 'sofort-uberweisung' || $local_card == 'sisal' || $local_card == 'przelewy24' || $local_card == 'webmoney' || $local_card == 'yandex' || $local_card == 'paypal') {
					$data ['payment_product'] = $local_card;
					unset($data ['payment_product_list']);
					unset($data ['merchant_display_name']);
					unset($data ['css']);

					$response = HipayApi::restApi('order', $data);
				}
				else
					$response = HipayApi::restApi('hpayment', $data);

				if ($response == false) // Wrong response, redirect to page order first step
					Tools::redirect('index.php?controller=order&xer=2');

				Tools::redirect($response->forwardUrl, '');

				break;
			case 4 :
					// Use $duplicate_status_msg array to display msg err
					$this->context->smarty->assign(array(
						'duplicate_status_msg' => $duplicate_status_msg,
					));
					
					return $this->display(__FILE__, '1.4/views/templates/front/payment_cart_duplicate.tpl');
				break;
			case 0 :
			default :
				// Dedicated page
				// NO TPL NEEDED, will redirect to response forwardURL
				if (Tools::isSubmit('localcardToken') && tools::getValue('localcardToken')) {
					$local_card = tools::getValue('localcardToken');
				} else {
					$local_card = null;
				}
				// p($local_card);

				$data = HipayApi::getApiData($cart, null, null, $local_card);
				// p($data);
				// die(0);
				$response = HipayApi::restApi('hpayment', $data);
				// p($response);
				// die('here');
				if ($response == false) // Wrong response, redirect to page order first step
					Tools::redirect('index.php?controller=order&xer=2');

				Tools::redirect($response->forwardUrl, '');
				break;
		}

		return $this->display(__FILE__, '1.4/views/templates/front/payment_execution.tpl');
	}

	public function execPaymentapi($cart) {
		$hipay = new HiPay_Tpp ();

		$cart = $this->context->cart;
		if (!$this->checkCurrency($cart))
			Tools::redirect('index.php?controller=order&xer=1');

		$this->context->smarty->assign(array(
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->getCurrency((int) $cart->id_currency),
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->_path,
			'this_path_bw' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
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
			// p($cardNumber.', '.$cardHolder.', '.$cardExpiryMonth.', '.$cardExpiryYear.', '.$cardSecurityCode.', '.$cardFirstName.', '.$cardLastName.', '.$paymentproductswitcher);
			$cardtoken = HipayToken::createToken($cardNumber, $cardHolder, $cardExpiryMonth, $cardExpiryYear, $cardSecurityCode, $cardFirstName, $cardLastName, $paymentproductswitcher);
			// p($cardtoken);
			// p('XAXA');
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
			$cart = $this->context->cart;
			$this->context->smarty->assign(array(
				'nbProducts' => $cart->nbProducts(),
				'cust_currency' => $cart->id_currency,
				'currencies' => $this->getCurrency((int) $cart->id_currency),
				'total' => $cart->getOrderTotal(true, Cart::BOTH),
				'this_path' => $this->_path,
				'this_path_bw' => $this->_path,
				'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
			));
			$currency_array = $this->getCurrency((int) $cart->id_currency);
			$currency = $currency_array [0] ['iso_code'];
			foreach ($currency_array as $key => $value) {
				if ($value ['id_currency'] == $cart->id_currency) {
					$actual_currency = $value ['iso_code'];
				}
			}
			if ($currency != $actual_currency)
				$currency = $actual_currency;

			$this->context->smarty->assign(array(
				'status_error' => (int) $cardtoken, // status error
				'cart_id' => $cart->id,
				'currency' => $currency,
				'amount' => $cart->getOrderTotal(true, Cart::BOTH)
			));
			// Tpl will load a form that will store those infomations.

			Tools::addCSS(_MODULE_DIR_ . $this->name . '/css/hipay.css');
			Tools::addJS(_MODULE_DIR_ . $this->name . '/js/14hipay.js');

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

			$this->context->smarty->assign(array(
				'cartes' => $carte
			));

			$tokens = HipayToken::getTokens($cart->id_customer); //
			if ($tokens ['0']) {
				$token_display = 'true';
			} else {
				$token_display = 'false';
			}

			$allow_memorize = HipayClass::getShowMemorization();

			$this->context->smarty->assign(array(
				'token_display' => $token_display,
				'allow_memorize' => $allow_memorize,
				'tokens' => $tokens
			));

			$payment_tpl = 'payment_execution_api.tpl';
			// return $this->setTemplate ( $payment_tpl );
			return $this->display(__FILE__, '1.4/views/templates/front/' . $payment_tpl);
			die();
		} else {
			// Mode API
			// Constructs data array and sends it as a parameter to the tpl
			$data = HipayToken::getApiData($cart, $token_to_use, null, $cartUseExistingToken);
			$response = HipayApi::restApi('order', $data);

			// Check if 3D secure is activated
			// if((int)$data['authentication_indicator'])
			// {
			// Check if forwardURL is true
			if ($response->forwardUrl) {
				// Redirect user
				Tools::redirect($response->forwardUrl, '');
			}
			// }

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
				$this->context->smarty->assign(array(
					'error_code' => '',
					'error_message' => '',
					'error_response' => '',
					'response_state' => $response_state
				));
			} else {

				$response_code = $response->getCode();
				$response_message = $response->getMessage();

				$this->context->smarty->assign(array(
					'error_code' => $response_code,
					'error_message' => $response_message,
					'error_response' => 'exception_error',
					'response_state' => 'error'
				));
			}

			switch ($response_state) {
				case 'completed' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14accept.php');
					break;
				case 'declined' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14decline.php');
					break;
				case 'cancel' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14cancel.php');
					break;
				case 'pending' :
				case 'forwarding' :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14pending.php');
					// Implementing challenge url
					// Redirecting to challenge url if url present
					if (Configuration::get('HIPAY_CHALLENGE_URL')) {
						$redirect_url = Configuration::get('HIPAY_CHALLENGE_URL');
					}
					break;
				case 'exception' :
				default :
					$redirect_url = HipayClass::getRedirectionUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $hipay->name . '/14exception.php');
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

			// $this->setTemplate ( 'payment_api_response.tpl' );
			return $this->display(__FILE__, '1.4/views/templates/front/' . 'payment_api_response.tpl');
		}
	}

	public function execRefund($cart) {
		$context = Context::getContext();
		$hipay = new HiPay_Tpp ();
		$hipay_redirect_status = 'ok';

		// If id_order is sent, we instanciate a new Order object
		if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
			$order = new Order(Tools::getValue('id_order'));
			if (!Validate::isLoadedObject($order))
				throw new PrestaShopException('Can\'t load Order object');
			if (version_compare(_PS_VERSION_, '1.5.6', '>'))
				ShopUrl::cacheMainDomainForShop((int) $order->id_shop);
			if (Tools::isSubmit('id_emp') && Tools::getValue('id_emp') > 0) {
				$id_employee = Tools::getValue('id_emp');
			} else {
				$id_employee = '1';
			}
		}
		if (Tools::isSubmit('hipay_refund_type')) {
			$refund_type = Tools::getValue('hipay_refund_type');
			$refund_amount = Tools::getValue('hipay_refund_amount');
			$refund_amount = str_replace(' ', '', $refund_amount);
			$refund_amount = floatval(str_replace(',', '.', $refund_amount));
		}

		// First check
		if (Tools::isSubmit('hipay_refund_submit') && $refund_type == 'partial') {
			$hipay_redirect_status = false;
			$hipay = new HiPay_Tpp ();
			$orderLoaded = new OrderCore(Tools::getValue('id_order'));
			// v1.5 // $orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;
			$orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping + $orderLoaded->total_wrapping;
			$totalEncaissement = $hipay->getOrderTotalAmountCaptured($orderLoaded->id);

			if (!$refund_amount) {
				$hipay_redirect_status = $hipay->l('Please enter an amount', 'refund');
				Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay');
				die('');
			}
			if ($refund_amount < 0) {
				$hipay_redirect_status = $hipay->l('Please enter an amount greater than zero', 'refund');
				Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay');
				die('');
			}
			if ($refund_amount > $totalEncaissement) {
				$hipay_redirect_status = $hipay->l('Amount exceeding authorized amount', 'refund');
				Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay');
				die('');
			}
		}

		if (Tools::isSubmit('hipay_refund_submit') && isset($order)) {
			$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_transactions` WHERE `cart_id`='" . (int) $order->id_cart . "'";
			$result = Db::getInstance()->getRow($sql);
			$reference = $result ['transaction_reference'];
			if ($refund_type == 'complete') {
				// Appel HiPay
				$data = HipayMaintenance::getMaintenanceData('refund', '0');
				$response = HipayMaintenance::restMaintenanceApi($reference, $data);
				// Ajout commentaire
				$msg = new Message ();
				$message = 'HiPay - Complete refund requested to HiPay.';
				$message = strip_tags($message, '<br>');
				if (Validate::isCleanHtml($message)) {
					$msg->message = $message;
					$msg->id_order = intval($order->id);
					$msg->private = 1;
					$msg->add();
				}
			} else {
				// 'partial';
				// Appel HiPay

				/**
				 * VERIFICATION
				 */
				// v1.5 // $orderTotal = $order->total_products_wt + $order->total_shipping_tax_incl + $order->total_wrapping_tax_incl;
				$orderTotal = $order->total_products_wt + $order->total_shipping + $order->total_wrapping;
				$totalEncaissement = $this->getOrderTotalAmountCaptured($order->id);

				if ($totalEncaissement < $refund_amount) {
					$hipay_redirect_status = $hipay->l('Error, you are trying to refund an amount that is more than the amount captured', 'refund');
				} else {
					$data = HipayMaintenance::getMaintenanceData('refund', $refund_amount);

					$response = HipayMaintenance::restMaintenanceApi($reference, $data);

					// Ajout commentaire
					$msg = new Message ();
					$message = 'HIPAY_REFUND_REQUESTED ' . $refund_amount;
					$message = strip_tags($message, '<br>');
					if (Validate::isCleanHtml($message)) {
						$msg->message = $message;
						$msg->id_order = intval($order->id);
						$msg->private = 1;
						$msg->add();
					}

					$hipay_redirect_status = 'ok';
				}
			}
		}
		else
			$hipay_redirect_status = $hipay->l('You do not have permission to do this.', 'refund');

		// die('Redirection here : '.'../../' . Tools::getValue ( 'adminDir' ) . '/index.php?tab=AdminOrders' . '&id_order=' . ( int ) $order->id . '&vieworder&token=' . Tools::getValue ( 'token' ) . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay');
		Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_refund_err=' . $hipay_redirect_status . '#hipay');
	}

	public function execCapture() {
		$context = Context::getContext();
		$hipay = new HiPay_Tpp ();
		$hipay_redirect_status = 'ok';

		// If id_order is sent, we instanciate a new Order object
		if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
			$order = new Order(Tools::getValue('id_order'));
			if (!Validate::isLoadedObject($order))
				throw new PrestaShopException('Can\'t load Order object');
			if (version_compare(_PS_VERSION_, '1.5.6', '>'))
				ShopUrl::cacheMainDomainForShop((int) $order->id_shop);
			if (Tools::isSubmit('id_emp') && Tools::getValue('id_emp') > 0) {
				$id_employee = Tools::getValue('id_emp');
			} else {
				$id_employee = '1';
			}
		}
		if (Tools::isSubmit('hipay_capture_type')) {
			$refund_type = Tools::getValue('hipay_capture_type');
			$refund_amount = Tools::getValue('hipay_capture_amount');
			$refund_amount = str_replace(' ', '', $refund_amount);
			$refund_amount = floatval(str_replace(',', '.', $refund_amount));
		}

		// First check
		if (Tools::isSubmit('hipay_capture_submit') && $refund_type == 'partial') {
			$hipay_redirect_status = false;
			$hipay = new HiPay_Tpp ();
			$orderLoaded = new OrderCore(Tools::getValue('id_order'));
			// v1.5 // $orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;
			$orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping + $orderLoaded->total_wrapping;
			$totalEncaissement = $hipay->getOrderTotalAmountCaptured($orderLoaded->id);
			$stillToCapture = floatval($orderTotal - $totalEncaissement);

			if (!$refund_amount) {
				$hipay_redirect_status = $hipay->l('Please enter an amount', 'capture');
				Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_err=' . $hipay_redirect_status . '#hipay');
				die('');
			}
			if ($refund_amount < 0) {
				$hipay_redirect_status = $hipay->l('Please enter an amount greater than zero', 'capture');
				Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_err=' . $hipay_redirect_status . '#hipay');
				die('');
			}
			if ($refund_amount > $stillToCapture) {
				$hipay_redirect_status = $hipay->l('Amount exceeding authorized amount', 'capture');
				Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_err=' . $hipay_redirect_status . '#hipay');
				die('');
			}
		}

		if (Tools::isSubmit('hipay_capture_submit') && isset($order)) {
			$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_transactions` WHERE `cart_id`='" . (int) $order->id_cart . "'";
			$result = Db::getInstance()->getRow($sql);

			$reference = $result ['transaction_reference'];
			if ($refund_type == 'complete') {
				// Appel HiPay
				$data = HipayMaintenance::getMaintenanceData('capture', '0');
				$response = HipayMaintenance::restMaintenanceApi($reference, $data);

				// Ajout commentaire
				$msg = new Message ();
				$message = 'HIPAY_CAPTURE_REQUESTED ' . $orderTotal;
				$message = strip_tags($message, '<br>');
				if (Validate::isCleanHtml($message)) {
					$msg->message = $message;
					$msg->id_order = intval($order->id);
					$msg->private = 1;
					$msg->add();
				}
			} else {
				// 'partial';
				// Appel HiPay

				/**
				 * VERIFICATION
				 */
				// v1.5 // $orderTotal = $order->total_products_wt + $order->total_shipping_tax_incl + $order->total_wrapping_tax_incl;
				$orderTotal = $order->total_products_wt + $order->total_shipping + $order->total_wrapping;
				$totalEncaissement = $this->getOrderTotalAmountCaptured($order->id);
				$stillToCapture = $orderTotal - $totalEncaissement;

				$orderLoaded = new OrderCore(Tools::getValue('id_order'));
				$currentState = $orderLoaded->getCurrentState();
				$stateLoaded = new OrderState($currentState);

				if (round($stillToCapture, 2) < round($refund_amount, 2)) {
					$hipay_redirect_status = $hipay->l('Error, you are trying to capture more than the amount remaining', 'capture');
				} else {
					$data = HipayMaintenance::getMaintenanceData('capture', $refund_amount);

					$response = HipayMaintenance::restMaintenanceApi($reference, $data);

					// Ajout commentaire
					$msg = new Message ();
					$message = 'HIPAY_CAPTURE_REQUESTED ' . $refund_amount;
					$message = strip_tags($message, '<br>');
					if (Validate::isCleanHtml($message)) {
						$msg->message = $message;
						$msg->id_order = intval($order->id);
						$msg->private = 1;
						$msg->add();
					}

					$hipay_redirect_status = 'ok';
				}
			}
		}
		else
			$hipay_redirect_status = $hipay->l('You do not have permission to do this.', 'capture');

		Tools::redirectAdmin('../../' . Tools::getValue('adminDir') . '/index.php?tab=AdminOrders' . '&id_order=' . (int) $order->id . '&vieworder&token=' . Tools::getValue('token') . '&hipay_err=' . $hipay_redirect_status . '#hipay');
	}

	public function hookPayment($params) {
		global $smarty, $cookie;
		if (!$this->active)
			return;

		// Verify if customer has memorized tokens
		// $cart = $this->context->cart; // v1.5
		$cart = new Cart((int) $cookie->id_cart);

		$tokens = HipayToken::getTokens($cart->id_customer); // Retrieve list of tokens
		if (isset($tokens ['0'])) {
			$token_display = 'true';
		} else {
			$token_display = 'false';
		}

		// Verify if systems should display memorized tokens
		$allow_memorize = HipayClass::getShowMemorization();

		// If both are true, activate additional info to allow payment via existing token
		if (($allow_memorize == 'true')) {
			$currency_array = $this->getCurrency((int) $cart->id_currency);
			$currency = $currency_array [0] ['iso_code'];
			foreach ($currency_array as $key => $value) {
				if ($value ['id_currency'] == $cart->id_currency) {
					$actual_currency = $value ['iso_code'];
				}
			}
			if ($currency != $actual_currency)
				$currency = $actual_currency;

			$smarty->assign(array(
				'cart_id' => $cart->id,
				'currency' => $currency,
				'amount' => $cart->getOrderTotal(true, Cart::BOTH)
			));
		}

		// Create dynamic payment button
		$card_str = Configuration::get('HIPAY_ALLOWED_CARDS');
		$cart_arr = explode(',', $card_str);

		$card_currency = Configuration::get('HIPAY_CURRENCY_CARDS');
		if (Tools::strlen($card_currency) > 3) {
			$currency_array = $this->getCurrency((int) $cart->id_currency);
			$currency = $currency_array [0] ['iso_code'];
			foreach ($currency_array as $key => $value) {
				if ($value ['id_currency'] == $cart->id_currency) {
					$actual_currency = $value ['iso_code'];
				}
			}
			$card_currency_arr = explode(',', Tools::substr($card_currency, 1, - 1));

			foreach ($card_currency_arr as $key => $value) {
				foreach ($cart_arr as $cardkey => $cardvalue) {
					if ($value == '"' . $actual_currency . '-' . $cardvalue . '"') {
						$card_curr_val [$cardvalue] = true;
					}
				}
			}
		} else {
			foreach ($cart_arr as $cardkey => $cardvalue) {
				$card_curr_val [$cardvalue] = true;
			}
		}

		$btn_image = '';
		$card_currency_ok = '0';
		$payment_product_list_upd = '';
		$count_ccards = 0;

		foreach ($cart_arr as $key => $value) {
			if ($value == 'visa' && $card_curr_val ['visa']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/visa_small.png" alt="Visa" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'visa,';
				$count_ccards++;
			}
			if ($value == 'mastercard' && $card_curr_val ['mastercard']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/mc_small.png" alt="MasterCard" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'mastercard,';
				$count_ccards++;
			}
			if ($value == 'american-express' && $card_curr_val ['american-express']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/amex_small.png" alt="American Express" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'american-express,';
				$count_ccards++;
			}
			if ($value == 'bcmc' && $card_curr_val ['bcmc']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/bcmc_small.png" alt="Bancontact / Mister Cash" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'bcmc,';
				$count_ccards++;
			}
			if ($value == 'cb' && $card_curr_val ['cb']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/cb_small.png" alt="CB" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'cb,';
				$count_ccards++;
			}
			if ($value == 'maestro' && $card_curr_val ['maestro']) {
				$btn_image .= '<img class= "hipay_method" src="' . _MODULE_DIR_ . $this->name . '/img/maestro_small.png" alt="Maestro" />';
				$card_currency_ok = '1';
				$payment_product_list_upd .= 'maestro,';
				$count_ccards++;
			}
		}

		// Assign smarty variables
		$smarty->assign(array(
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
		$smarty->assign(array(
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
			foreach ($currency_array as $key => $value) {
				if ($value ['id_currency'] == $cart->id_currency) {
					$actual_currency = $value ['iso_code'];
				}
			}
			foreach ($local_cards as $key => $value) {
				$local_cards_img [(string) $value->code] = (string) $value->image;
				$local_cards_name [(string) $value->code] = (string) $value->name;
				$show_cards [(string) $value->code] = 'false'; // Initialize to false
				// Assigning temporary code to variable
				$card_code = (string) $value->code;
				foreach ($value->currencies as $key => $value) {
					foreach ($value->iso_code as $key => $value) {
						if (Tools::strtoupper($actual_currency) == Tools::strtoupper((string) $value)) {
							$show_cards [$card_code] = 'true'; // Update to true
						}
					}
				}
			}
		}
		if (count($localPayments)) {
			$allow_local_cards = 'true';
		} else {
			$allow_local_cards = 'false';
		}

		$smarty->assign(array(
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
		$smarty->assign(array(
			'id_opc' => $id_opc
		));

		return $this->display(__FILE__, '1.4/views/templates/hook/payment.tpl');
	}

	public function hookFooter($params) {
		global $smarty;
		// modif One Page Checkout
		// Check if cart is in OPC
		$is_opc = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'true' : 'false';
		$id_opc = ''; // Set id_opc to empty by default
		if ($is_opc == 'true') {
			$id_opc = 'OPC'; // This will update hidden field 'ioBB' to 'ioBBOPC' to prevent duplicate id
		}
		// Add generic smarty variables;
		$smarty->assign(array(
			'id_opc' => $id_opc
		));

		return $this->display(__FILE__, '1.4/views/templates/hook/payment_opc_footer.tpl');
	}

	public function hookPaymentReturn($params) {
		global $smarty;
		if (!$this->active)
			return;

		$state = $params ['objOrder']->getCurrentState();
		if ($state)
			$smarty->assign('status', 'OK');
		else
			$smarty->assign('status', 'failed');
		return $this->display(__FILE__, '1.4/views/templates/hook/payment_return.tpl');
	}

	/**
	 * Mes 2 formulaires sur les commandes permettant les remboursements ou les captures
	 */
	public function hookAdminOrder() {
		// return false;
		$orderLoaded = new OrderCore(Tools::getValue('id_order'));
		$id_order = (int) Tools::getValue('id_order');
		// p($order;Loaded);
		// Verify the payment method name
		// V1.5 $payment_method_sql = "SELECT payment_method FROM `" . _DB_PREFIX_ . "order_payment` WHERE order_reference='" . $orderLoaded->reference . "'";
		$payment_method_sql = "SELECT payment FROM `" . _DB_PREFIX_ . "orders` WHERE id_order='" . (int)$id_order . "'";
		$payment_method = Db::getInstance()->executeS($payment_method_sql);

		$hide_refund = false;
		$hide_capture = false;

		if (isset($payment_method [0] ['payment'])) {
			$explode_payment_local_card = explode($this->displayName . ' via', $payment_method [0] ['payment']);
			if (isset($explode_payment_local_card [1])) {

				$payment_local_card = $explode_payment_local_card [1];

				$local_cards = $this->checkLocalCards();

				if (isset($local_cards)) {
					if (count($local_cards)) {
						foreach ($local_cards as $key => $value) {
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
			// v1.5 // $payment_message_sql = "SELECT * FROM `" . _DB_PREFIX_ . "message` WHERE id_order='" . $orderLoaded->id . "' AND message LIKE 'HiPay%Status : 118%'";
			$payment_message_sql = "SELECT * FROM `" . _DB_PREFIX_ . "message` WHERE id_order='" . (int)$orderLoaded->id . "' AND ( message LIKE '%Status: 118%' OR message LIKE '%Status : 118%' ) ";
			$paymentmessage = Db::getInstance()->executeS($payment_message_sql);

			if (empty($paymentmessage))
				$hide_refund = true;
		}

		$currentState = $orderLoaded->getCurrentState();
		$stateLoaded = new OrderState($currentState);

		// Check if current state = Configuration::get( 'HIPAY_REFUND_REQUESTED' )
		// If renfund requested, then prevent any further refund until current refund has been completed
		if ($currentState == Configuration::get('HIPAY_REFUND_REQUESTED')) {
			$hide_refund = true;
		}

		$form = '';
		if ($orderLoaded->module == $this->name) {

			if ($stateLoaded->id == _PS_OS_PAYMENT_ || $stateLoaded->id == Configuration::get('HIPAY_REFUNDED') || $stateLoaded->id == Configuration::get('HIPAY_PARTIALLY_CAPTURED')) {
				/**
				 * variables de vérification
				 */
				// v1.5 // $orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;
				$orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping + $orderLoaded->total_wrapping;
				$totalEncaissement = $this->getOrderTotalAmountCaptured($orderLoaded->id);

				$adminDir = _PS_ADMIN_DIR_;
				$adminDir = Tools::substr($adminDir, strrpos($adminDir, '/'));
				$adminDir = Tools::substr($adminDir, strrpos($adminDir, '\\'));
				$adminDir = str_replace('\\', '', $adminDir);
				$adminDir = str_replace('/', '', $adminDir);

				$context = Context::getContext();
				// v1.5 // $form_action = '../index.php?fc=module&module=' . $this->name . '&controller=refund';
				$form_action = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/14refund.php';

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
			if ($orderLoaded->getCurrentState() == Configuration::get('HIPAY_AUTHORIZED') || $orderLoaded->getCurrentState() == _PS_OS_PAYMENT_ || $orderLoaded->getCurrentState() == Configuration::get('HIPAY_PARTIALLY_CAPTURED')) {
				$showCapture = true;
			}
			if ($showCapture) {
				// Modification to allow a full capture if the previous state was HIPAY_PENDING or HIPAY_CHALLENGED
				$get_HIPAY_MANUALCAPTURE = Configuration::get('HIPAY_MANUALCAPTURE');
				$allow_pending_capture = false; // Initialize to false
				$context = Context::getContext();
				if ((boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_PENDING'))
					|| (boolean) $orderLoaded->getHistory($context->language->id, Configuration::get('HIPAY_CHALLENGED'))
				) {
					// Order was previously pending or challenged
					// Then check if its currently in authorized state
					if ($orderLoaded->getCurrentState() == Configuration::get('HIPAY_AUTHORIZED')) {
						$allow_pending_capture = true;
						$get_HIPAY_MANUALCAPTURE = 1;
					}
				} else {
					// Nothing to do, classical system behaviour will take over
				}

				// FORCING ORDER CAPTURED AMOUNT UPDATE
				// v1.5 // $sql = "UPDATE `"._DB_PREFIX_."order_payment`
				// SET `amount` = '".$this->getOrderTotalAmountCaptured($orderLoaded->id)."'
				// WHERE `order_reference`='".$orderLoaded->reference."'";
				$sql = "UPDATE `" . _DB_PREFIX_ . "orders`
                        SET `total_paid_real` = '" . $this->getOrderTotalAmountCaptured($orderLoaded->id) . "'
                        WHERE `id_order`='" . $orderLoaded->id . "'";

				Db::getInstance()->execute($sql);

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
							$form .= '<p style="" class="error">
									<a style="position: relative; top: -100px;" id="hipay"></a>
						        	' . Tools::getValue('hipay_err') . '
						        	</p>';
						}
					}

					// v1.5 // $form_action = '../index.php?fc=module&module=' . $this->name . '&controller=capture';
					$form_action = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/14capture.php';
					$form .= '
			        		<div style="height:10px"></div>
			        		<fieldset>
			        			<legend>' . $this->l('Capture this order') . '</legend>';
					// v1.5 // $orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping_tax_incl + $orderLoaded->total_wrapping_tax_incl;
					$orderTotal = $orderLoaded->total_products_wt + $orderLoaded->total_shipping + $orderLoaded->total_wrapping;

					$totalEncaissement = $this->getOrderTotalAmountCaptured($orderLoaded->id);

					$stillToCapture = $orderTotal - $totalEncaissement;

					// Modif ajout texte warning si montant pas completement capture
					if($stillToCapture)
					{
						if (version_compare(_PS_VERSION_, '1.5', '>')) {
							$cookie = $this->context->cookie;
						} else {
							global $cookie;
						}
						// Retrieve _PS_OS_PAYMENT_ real name
						$tmpOS = new OrderState((int)_PS_OS_PAYMENT_, $cookie->id_lang);
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

					$old_message = Message::getMessageByCartId((int) Tools::getValue('id_order'));
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
		Tools::addCSS(_MODULE_DIR_ . $this->name . '/css/hipay.css');
		Tools::addJS(_MODULE_DIR_ . $this->name . '/js/14hipay.js');

	}

	public function hookDisplayHeader() {
		Tools::addCSS(_MODULE_DIR_ . $this->name . '/css/hipay2.css', 'all');
	}

	public function hookBackOfficeHeader($params) {

		return '<link type="text/css" rel="stylesheet" href="' . __PS_BASE_URI__ . 'modules/' . $this->name . '/css/14logstable.css' . '" media="all" />
            <script type="text/javascript" src="' . __PS_BASE_URI__ . 'modules/' . $this->name . '/js/logs_fr_FR.js' . '"></script>
            <script type="text/javascript" src="' . __PS_BASE_URI__ . 'modules/' . $this->name . '/js/jquery.dataTables.min.js' . '"></script>
            ';
	}

	public function validation_process() {
		// For callbacks
		$config = Configuration::getMultiple(array(
					'HIPAY_PROCESSING_QUEUE',
					'HIPAY_LAST_PROCESS'
		));

		// securite pour eviter les blocages
		if ((Configuration::get('HIPAY_LAST_PROCESS') < (time() - 30 * 60)) && Configuration::get('HIPAY_PROCESSING_QUEUE')) {
			Configuration::updateValue('HIPAY_PROCESSING_QUEUE', 0);
		}

		// on fais le select pour voir si qqch dans la file d'attente
		$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_callbacks` WHERE `treated`=0 AND `callback` LIKE  '%" . '"status":"117"' . "%' ORDER BY id ASC";
		$found = Db::getInstance()->executeS($sql);

		if (!sizeof($found)) {
			$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_callbacks` WHERE `treated`=0 ORDER BY id ASC";
			$found = Db::getInstance()->executeS($sql);
		}

		if (sizeof($found) && !Configuration::get('HIPAY_PROCESSING_QUEUE')) {
			// met ajoute le blocage
			Configuration::updateValue('HIPAY_PROCESSING_QUEUE', 1);

			foreach ($found as $key => $value) {
				// Verify if another treatement has not already started on that same id
				$sql_check = "SELECT `treated` FROM `" . _DB_PREFIX_ . "hipay_callbacks` WHERE `id` = '" . (int) $value ['id'] . "'";
				$is_treated = Db::getInstance()->getValue($sql_check);
				if ($is_treated == '1') {
					// If treatement already started, break the process.
					break; // Breaks from
				}

				// Mise-a-jour du callback comme etant traité
				// Mesure preventive au cas ou le delete ne passe pas correctement pour empecher le system de reprocess le meme callback 2 fois.
				$sql_update = "UPDATE `" . _DB_PREFIX_ . "hipay_callbacks`
                        SET `treated` = 1
                        WHERE `id` = '" . (int) $value ['id'] . "'";
				Db::getInstance()->execute($sql_update);

				// on fais le fopen
				$url = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/validation_process.php';
				$tempfile = $url . '?' . http_build_query(Tools::jsonDecode($value ['callback'], true), '', '&');
				$fp = fopen($tempfile, 'r');
				fclose($fp);
				@unlink($tempfile);

				// on supprime l'entree de la liste des callbac
				$sql_del = 'DELETE FROM `' . _DB_PREFIX_ . 'hipay_callbacks`
                WHERE `id` = ' . (int) $value ['id'];
				Db::getInstance()->execute($sql_del);
			}
			// on enleve le blocage
			Configuration::updateValue('HIPAY_PROCESSING_QUEUE', 0);
			Configuration::updateValue('HIPAY_LAST_PROCESS', time());
		}
	}

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
		if (file_exists(_PS_ROOT_DIR_ . '/modules/' . $this->name . '/special_cards.xml')) {
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
	public function getOrderTotalAmountCaptured($id_order) {
		$tag = 'HIPAY_CAPTURE '; // don't forget the space!
		$sum = 0.0;
		$msgs = Message::getMessagesByOrderId($id_order, true); // true for private messages (got example from AdminOrdersController)
		if (count($msgs))
			foreach ($msgs as $msg) {
				$line = $msg ['message'];
				if ($this->startsWith($line, $tag)) {
					$sum += (float) trim(Tools::substr($line, Tools::strlen($tag)));
				}
			}
		return $sum;
	}

	public function updateOrderCAPTUREmsg($id_order, $amount) {
		$tag = 'HIPAY_CAPTURE '; // don't forget the space!
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

}

