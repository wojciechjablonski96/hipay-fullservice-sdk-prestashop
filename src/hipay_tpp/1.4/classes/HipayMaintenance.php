<?php
/**
 * 2007-2013 Profileo NOTICE OF LICENSE This source file is subject to the Academic Free License (AFL 3.0) that is bundled with this package in the file LICENSE.txt. It is also available through the world-wide-web at this URL: http://opensource.org/licenses/afl-3.0.php If you did not receive a copy of the license and are unable to obtain it through the world-wide-web, please send an email to contact@profileo.com so we can send you a copy immediately. DISCLAIMER Do not edit or add to this file if you wish to upgrade Profileo to newer versions in the future. If you wish to customize Profileo for your needs please refer to http://www.profileo.com for more information. @author Profileo <contact@profileo.com> @copyright 2007-2013 Profileo International Registered Trademark & Property of Profileo
 */
class HipayMaintenance extends ObjectModel {
	
	/**
	 * returns API response array()
	 */
	public static function restMaintenanceApi($transaction_reference = null, $data = null) {
		try {
			$hipay = new HiPay_Tpp ();
			HipayLogger::addLog ( $hipay->l ( 'API Refund call initiated', 'hipay' ), HipayLogger::APICALL, 'Transaction_reference : ' . $transaction_reference . ' - Data : ' . Tools::jsonEncode ( $data ) );
			
			if ($transaction_reference == null)
				return 'Error - No transaction reference';
			if ($data == null)
				return 'Error - No data';
			
			define ( 'API_ENDPOINT', HipayClass::getAPIURL () );
			define ( 'API_USERNAME', HipayClass::getAPIUsername () );
			define ( 'API_PASSWORD', HipayClass::getAPIPassword () );
			
			$credentials = API_USERNAME . ':' . API_PASSWORD;
			
			$resource = API_ENDPOINT . 'maintenance/transaction/' . $transaction_reference;
			
			// create a new cURL resource
			$curl = curl_init ();
			
			// set appropriate options
			$options = array (
					CURLOPT_URL => $resource,
					CURLOPT_USERPWD => $credentials,
					CURLOPT_HTTPHEADER => array (
							'Accept: application/json' 
					),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FAILONERROR => false,
					CURLOPT_HEADER => false,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $data,
					// CURLOPT_POSTFIELDS => http_build_query($data),
					// CURLOPT_POSTFIELDS => Tools::jsonEncode($data),
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => false 
			);
			
			foreach ( $options as $option => $value ) {
				curl_setopt ( $curl, $option, $value );
			}
			
			$result = curl_exec ( $curl );
			
			$status = ( int ) curl_getinfo ( $curl, CURLINFO_HTTP_CODE );
			$response = Tools::jsonDecode ( $result );
			
			// execute the given cURL session
			if (false === ($result)) {
				throw new Exception ( curl_error ( $curl ) );
			}
			
			if (floor ( $status / 100 ) != 2) {
				throw new Exception ( 'Err Msg : ' . $response->message . ', Err Desc : ' . $response->description . ', Err Code : ' . $response->code );
			}
			curl_close ( $curl );
			
			HipayLogger::addLog ( $hipay->l ( 'API call success', 'hipay' ), HipayLogger::APICALL, 'Appel vers API avec success : ' . mysql_real_escape_string ( Tools::jsonEncode($response) ) );
			return $response;
		} catch ( Exception $e ) {
			HipayLogger::addLog ( $hipay->l ( 'API call error', 'hipay' ), HipayLogger::ERROR, mysql_real_escape_string ( $e->getMessage () ) );
			
			return false;
		}
	}
	
	/**
	 * Generates API data Note : This data structure is different from HipayToken::getApiData.
	 * @param $cart : Contains cart information @param $data_type : Can be either 'null' or 'iframe'. 'null' = default dedicated page behaviour 'iframe' = Updates some values to match iframe behaviour @param $context : Optional parameter through which current context is passed. If not present, the context will get instantiated none the less. returns API response array()
	 */
	public static function getMaintenanceData($operation = null, $amount = null) {
		$data = array (
				'operation' => $operation 
		);
		if ($amount > 0) {
			$data2 = array (
					'amount' => round ( $amount, 2 ) 
			);
			$data = array_merge ( $data, $data2 );
		}
		
		return $data;
	}
}