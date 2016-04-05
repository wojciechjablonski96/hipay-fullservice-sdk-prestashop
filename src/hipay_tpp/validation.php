<?php
include(dirname(__FILE__).'/../../config/config.inc.php');

if (_PS_VERSION_ < '1.5') {
	// version 1.4
	include (dirname ( __FILE__ ) . '/1.4/validation.php');
} else {
	// Version 1.5 or above
	include (dirname ( __FILE__ ) . '/1.5/validation.php');
}