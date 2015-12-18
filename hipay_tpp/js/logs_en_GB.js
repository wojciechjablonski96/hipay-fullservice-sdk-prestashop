/**
 * Dynamic Jquery Logs Table
 */
$( document ).ready(function() {
	
	$('#hipay_logs').dataTable( {
		"bJQueryUI": true,
		"aaSorting": [[ 0, "desc" ]],
		"sPaginationType": "full_numbers"
	});
	
});