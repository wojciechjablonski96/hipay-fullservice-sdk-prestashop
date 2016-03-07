<?php
/**
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
require_once(dirname ( __FILE__ ) . '/../../../config/config.inc.php');
$str_ps_version = ( int ) str_replace ( '.', '', _PS_VERSION_ );
if ($str_ps_version < 1600) {
	// version 1.5 or 1.4
	include_once (dirname ( __FILE__ ) . '/../../../init.php');
} else {
	// Version 1.6 or above
	include_once (dirname ( __FILE__ ) . '/../../../init.php');
}
include_once (dirname ( __FILE__ ) . '/hipay_tpp.php');

try {
    $hipay = new HiPay_Tpp();
    $content = Tools::jsonEncode( $_POST );

    // Insert into order_history
    $log_state = ($_POST['state']) ? $_POST['state'] : 'error'; // Sets to error if nothing is found
    $log_status = ($_POST['status']) ? $_POST['status'] : 'error'; // Sets to error if nothing is found
    HipayLogger::addLog ( $hipay->l ( 'Callback recieved', 'hipay' ), HipayLogger::NOTICE, 'Callback recieved - cid : ' . ( int ) $_POST['order']['id'] . ' - state : ' . $log_state . ' - status : ' . $log_status . ' - content : '.mysql_real_escape_string($content) );
    $sql_insert = "INSERT INTO `" . _DB_PREFIX_ . "hipay_callbacks` (`callback`) VALUES ('".mysql_real_escape_string($content)."');";
    $insert = Db::getInstance()->execute( $sql_insert );
    if($insert)
    {
        echo 'Callback captured';
    } else {
        echo 'Callback failed to be captured';
    }
    
} catch ( Exception $e ) {
    echo 'Callback failed : '.$e->getMessage();
}
