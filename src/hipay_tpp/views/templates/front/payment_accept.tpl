{*
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
*
* ########################################################
* HELP - VARIABLES FOR TAGS ANALYTICS
* ########################################################
* {$id_order} - ID order
* {$total} - Total order paid 	
* {$transaction} - Transaction ID sending by HiPay
* {$currency} - Currency used by this Order
* {$email} - Email customer
* ########################################################
* EXAMPLE TAG ANALYTICS - Don't use the TAG in {litoral}
*	<script type="text/javascript">
*	  var sf = sf || [];
*	  sf.push(['6826'], ['{$id_order}'], ['{$total}']);
*	 
*	  (function() {
*	    var sf_script = document.createElement('script');
*	    sf_script.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'tag.xxxxxxxxxxx.com/async.js';
*	    sf_script.setAttribute('async', 'true');
*	    document.documentElement.firstChild.appendChild(sf_script);
*	  })();
*	</script>
*}

{capture name=path}{l s='HiPay payment.' mod='hipay_tpp'}{/capture}
<h2>{l s='Payment Summary' mod='hipay_tpp'}</h2>


<h3>{l s='Your order has been taken into account.' mod='hipay_tpp'}</h3>
<p>
	{l s='Access to ' mod='hipay_tpp'} <strong><a href="{$link->getPageLink('history', true)}">{l s='your order history' mod='hipay_tpp'}</a></strong>
</p>
<p><a href="index.php">{l s='Back to home' mod='hipay_tpp'}</a></p>
{*
*
* HERE CODE FOR TAG ANALYTICS
*
*}



