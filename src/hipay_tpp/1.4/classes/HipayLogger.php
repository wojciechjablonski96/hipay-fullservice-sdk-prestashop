<?php
/**
 * 2007-2013 Profileo NOTICE OF LICENSE This source file is subject to the Academic Free License (AFL 3.0) that is bundled with this package in the file LICENSE.txt. It is also available through the world-wide-web at this URL: http://opensource.org/licenses/afl-3.0.php If you did not receive a copy of the license and are unable to obtain it through the world-wide-web, please send an email to contact@profileo.com so we can send you a copy immediately. DISCLAIMER Do not edit or add to this file if you wish to upgrade Profileo to newer versions in the future. If you wish to customize Profileo for your needs please refer to http://www.profileo.com for more information. @author Profileo <contact@profileo.com> @copyright 2007-2013 Profileo International Registered Trademark & Property of Profileo
 */
class HipayLogger extends ObjectModel {
	const NOTICE = 1;
	const WARNING = 2;
	const ERROR = 3;
	const APICALL = 4;
	public static function createTables() {
		return ( HipayLogger::createLogTable() && HipayLogger::createTransactionTable() && HipayLogger::createTokenTable() && HipayLogger::createTokenTempTable() && HipayLogger::createCallbacksTable() && HipayLogger::createCartSentTable() );
	}
	public static function createLogTable() {
		$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hipay_transactions`(
                `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                `cart_id` INT(10) UNSIGNED NOT NULL,
                `order_id` INT(10) UNSIGNED NOT NULL,
                `customer_id` INT(10) UNSIGNED NOT NULL,
                `transaction_reference` TEXT NOT NULL,
                `device_id` TEXT NOT NULL,
                `ip_address` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                `ip_country` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                `token` TEXT NOT NULL,                
                PRIMARY KEY (`id`)
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
		
		return Db::getInstance ()->execute ( $sql );
	}
	public static function createTransactionTable() {
		$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hipay_logs`(
                `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `date` DATETIME NOT NULL,
                `level` TINYINT(1) UNSIGNED NOT NULL,
                `message` TEXT NOT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
		
		return Db::getInstance ()->execute ( $sql );
	}
	public static function createTokenTable() {
		$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hipay_tokens`(
                `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` INTEGER UNSIGNED NOT NULL,
                `token` VARCHAR(45) NOT NULL,
                `brand` VARCHAR(255) NOT NULL,
                `pan` VARCHAR(20) NOT NULL,
                `card_holder` VARCHAR(255) NOT NULL,
                `card_expiry_month` INTEGER(2) UNSIGNED NOT NULL,
                `card_expiry_year` INTEGER(4) UNSIGNED NOT NULL,
                `issuer` VARCHAR(255) NOT NULL,
                `country` VARCHAR(15) NOT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
		
		return Db::getInstance ()->execute ( $sql );
	}
	public static function createTokenTempTable() {
		$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hipay_tokens_tmp`(
                `cart_id` INTEGER UNSIGNED NOT NULL
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
		
		return Db::getInstance ()->execute ( $sql );
	}
	public static function createCallbacksTable() {
		$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hipay_callbacks`(
                `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                `callback` TEXT,
                `treated` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
		
		return Db::getInstance ()->execute ( $sql );
	}
	public static function createCartSentTable() {
		$sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'hipay_cart_sent`(
                `cart_id` INTEGER UNSIGNED NOT NULL,
                `timestamp` DATETIME NOT NULL
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
		
		return Db::getInstance()->execute ( $sql );
	}	
	public static function dropTables() {
		// $sql = 'DROP TABLE `'._DB_PREFIX_.'hipay_callbacks`';
		// return Db::getInstance()->execute($sql);
		return true;
	}
	public static function addLog($name = '', $level = '', $message = '') {
		if ($name == '' || $level == '' || $message == '')
			return;
		
		$sql = "INSERT INTO `" . _DB_PREFIX_ . "hipay_logs` (`name`, `date`, `level`, `message`)
            VALUES('" . $name . "', NOW(), '" . ( int ) $level . "', '" . mysql_real_escape_string($message) . "')";
		
		Db::getInstance ()->execute ( $sql );
		
		return Db::getInstance ()->Insert_ID ();
	}
	
	// cart_id and transaction_reference are mandatory.
	// Other values are optional
	// If
	public static function addTransaction($cart_id = 0, $order_id = 0, $customer_id = 0, $transaction_reference = 0, $device_id = 0, $ip_address = 0, $ip_country = 0, $token = 0) {
		$sql = "SELECT * FROM `" . _DB_PREFIX_ . "hipay_transactions` AS ht
                        WHERE `cart_id`='" . $cart_id . "'
                        AND `transaction_reference`='" . $transaction_reference . "'";
		
		$result = Db::getInstance ()->getRow ( $sql );
		
		if ($result ['id']) {
			// 'Already exists record for order_id';
		} else {
			// 'insert in DB';
			$sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "hipay_transactions` (`cart_id`, `order_id`, `customer_id`, `transaction_reference`, `device_id`, `ip_address`, `ip_country`, `token`)
                VALUES('" . $cart_id . "', '" . $order_id . "', '" . $customer_id . "', '" . $transaction_reference . "', '" . $device_id . "', '" . $ip_address . "', '" . $ip_country . "', '" . $token . "')";
			
			Db::getInstance ()->execute ( $sql_insert );
		}
		
		return true;
	}
}