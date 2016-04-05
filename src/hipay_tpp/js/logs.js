/**
 * Dynamic Jquery Logs Table
 */
$( document ).ready(function() {
	
	$('#hipay_logs').dataTable( {
		"bJQueryUI": true,
		"sPaginationType": "full_numbers",
		"aaSorting": [[ 0, "desc" ]]
	});
	
});