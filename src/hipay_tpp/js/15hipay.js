/**
 * Hipay GUI handling
 */

$( document ).ready(function() {
        var paymentproductswitcherchecked = $('#tokenizerForm #payment-product-switcher option:selected').val();
        if(paymentproductswitcherchecked == 'american-express')
        {
        	$('#cardHolderEnabled').hide();
            $('#cardFirstNameEnabled').show();
            $('#cardLastNameEnabled').show();
        }else{
            $('#cardFirstNameEnabled').hide();
            $('#cardLastNameEnabled').hide();
            $('#cardHolderEnabled').show();
        }

        if( paymentproductswitcherchecked == 'american-express'
        || paymentproductswitcherchecked == 'cb'
        || paymentproductswitcherchecked == 'visa'
        || paymentproductswitcherchecked == 'mastercard' )
        {
            $('#cardMemorizedEnabled').show();
        } else {
            $('#cardMemorizedEnabled').hide();
        }

        if(paymentproductswitcherchecked == 'bcmc')
        {
            $('#cardSecurityCodeEnabled').hide();
        }else{
            $('#cardSecurityCodeEnabled').show();
        }
	
	//Onload check for visibility
	if($('#hipay_threedsecure_on').is(":checked")){
		$('.3D_secure').parent().show();
		$('.3D_secure').parent().prev('label').show()
	}else{
		$('.3D_secure').parent().hide();
		$('.3D_secure').parent().prev('label').hide()
	}
	
	$('#hipay_threedsecure_on').change(function(){
		$('.3D_secure').parent().show();
		$('.3D_secure').parent().prev('label').show()
	});
	
	
	$('#hipay_threedsecure_off').change(function(){
		$('.3D_secure').parent().hide();
		$('.3D_secure').parent().prev('label').hide()
	});
	
	//Check if iFrame selected
	//alert($('#HIPAY_IFRAME_WIDTH').val('100%'));
	if($('#HIPAY_IFRAME_WIDTH').val() == '')  $('#HIPAY_IFRAME_WIDTH').val('100%');
	if($('#HIPAY_IFRAME_HEIGHT').val() == '')  $('#HIPAY_IFRAME_HEIGHT').val('670');
	var hipaypaymentmode = $('#HIPAY_PAYMENT_MODE option:selected').val();
    if(hipaypaymentmode == '1'){
    	$('.IFRAME_SIZE').parent().show();
		$('.IFRAME_SIZE').parent().prev('label').show()
	}else{
		$('.IFRAME_SIZE').parent().hide();
		$('.IFRAME_SIZE').parent().prev('label').hide()
	}
	
	$('#HIPAY_PAYMENT_MODE').change(function(){
		var hipaypaymentmode = $('#HIPAY_PAYMENT_MODE option:selected').val();
	    if(hipaypaymentmode == '1'){
	    	$('.IFRAME_SIZE').parent().show();
			$('.IFRAME_SIZE').parent().prev('label').show()
		}else{
			$('.IFRAME_SIZE').parent().hide();
			$('.IFRAME_SIZE').parent().prev('label').hide()
		}
	});
	//End Check if iFrame selected
	
	    
        $('#tokenizerForm input[name=cartUseExistingToken]').click(function(){
            var cartUseExistingToken = $('#tokenizerForm input[name=cartUseExistingToken]:checked').val();
            if(cartUseExistingToken == 1)
            {
            	$('.enter_card').hide('fast');
                $('.enter_token').show('fast');
                $('.enter_token').css("display","inline-block")
            }else{
                $('.enter_token').hide('fast');
                $('.enter_card').show('fast');
            }
        });
        
        $('#tokenizerForm #payment-product-switcher').change(function(){
        	var paymentproductswitcherchecked = $('#tokenizerForm #payment-product-switcher option:selected').val();
            if(paymentproductswitcherchecked == 'american-express')
            {
            	
                $('#cardHolderEnabled').hide();
                $('#cardFirstNameEnabled').show();
                $('#cardLastNameEnabled').show();
            }else{
                $('#cardFirstNameEnabled').hide();
                $('#cardLastNameEnabled').hide();
                $('#cardHolderEnabled').show();
            }
            
            if( paymentproductswitcherchecked == 'american-express'
            || paymentproductswitcherchecked == 'cb'
            || paymentproductswitcherchecked == 'visa'
            || paymentproductswitcherchecked == 'mastercard' )
            {
                $('#cardMemorizedEnabled').show();
            } else {
                $('#cardMemorizedEnabled').hide();
                $('input[name=cardMemorizeCode]').attr('checked', false);
            }
            
            if(paymentproductswitcherchecked == 'bcmc')
            {
                $('#cardSecurityCodeEnabled').hide();
            }else{
                $('#cardSecurityCodeEnabled').show();
            }
        });


	$('input#hipay_payment').click(function(){
            var cartUseExistingToken = $('#tokenizerForm input[name=cartUseExistingToken]:checked').val();

            if(cartUseExistingToken == 1) 
            {
                var cardToken = $('#tokenizerForm #cardToken').val();
                if(cardToken == '')
                {
                    alert('Please select your card');
                    return false;
                }
            }else{
                var paymentproductswitcher = $('#tokenizerForm #payment-product-switcher option:selected').val();

                var cardNumber = $('#tokenizerForm input#cardNumber').val().replace(/ /g,'');
		var cardHolder = $('#tokenizerForm input#cardHolder').val();
		var cardFirstName = $('#tokenizerForm input#cardFirstName').val();
		var cardLastName = $('#tokenizerForm input#cardLastName').val();
		var cardSecurityCode = $('#tokenizerForm input#cardSecurityCode').val().replace(/ /g,'');
                
		//Check for card number contains 19 characters
		//If not a number
		if(!($.isNumeric(cardNumber))){
			alert('Invalid Characters in Card Number');
			return false;
		}else{
			var count_num = cardNumber.length;
			
			//If count is not equal to 19
			if((count_num < 12)){
				alert('Invalid Card Number');
				return false;
			}
                        if((count_num > 19)){
				alert('Invalid Card Number');
				return false;
			}
		}	
		
		
		
		
		//Check for card holder name contains upto 25 characters and no integer
                if(paymentproductswitcher=='american-express')
                {
                    check(cardFirstName);
                    check(cardLastName);
                }else{
                    check(cardHolder);
                }
		
		//Check for security card number contains 3-4 characters
		//If not a number
                if (paymentproductswitcher!='bcmc')
                {
                    if(!($.isNumeric(cardSecurityCode))){
                        alert('Invalid Characters in Security Card Number');
                        return false;
                    }else{
                        var count_num = cardSecurityCode.length;

                        //If count is between 3 or 4
                        if((count_num < 3) || (count_num > 4)){
                            alert('Invalid Security Card Number');
                            return false;
                        }
                    }
                }
		

            }
		
	});
	
});


function check(data){
	var patren=/^[A-Za-z\s]+$/;
    if(!(patren.test(data))) {
       alert('Invalid Characters in Card Holder Name');       
       return false;
    }else{
    	validateName(data);
    }
}

function validateName(data){
	var name_length = data.length;
	
	if((name_length < 0) || (name_length > 25)){
		alert('Invalid Card Holder Name');
		return false;
	}
}

     
	