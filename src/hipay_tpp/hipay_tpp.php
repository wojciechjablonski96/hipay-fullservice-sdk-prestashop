<?php
if (_PS_VERSION_ < '1.5') {
	// version 1.4
	require_once(_PS_ROOT_DIR_.'/modules/hipay_tpp/1.4/hipay_tpp.php');
} else {
	// Version 1.5 or above
	require_once(_PS_ROOT_DIR_.'/modules/hipay_tpp/1.5/hipay_tpp.php');
}