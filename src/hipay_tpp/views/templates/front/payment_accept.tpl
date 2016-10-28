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

{if $id_order == '' && $transaction == 0}

	<div class="hipay-fullwidth ">
		<div class="hipay-clearfix ">
			<img src="{$base_dir_ssl}modules/hipay_tpp/views/img/hipay-fullservice_450x96.png" title="HiPay Fullservice" alt="HiPay Fullservice" />
		</div>
		<div class="hipay-clearfix "><h3>{l s='Chargement en cours' mod='hipay_tpp'}...</h3></div>
		<div class="spinner"></div>
	</div>

	<style type="text/css">

		.hipay-fullwidth { width:100%;padding-top: 50px; }
		.hipay-clearfix { clear:both;text-align:center; }
		.spinner {
		  width: 40px;
		  height: 40px;
		  background-color: #007CC1;

		  margin: 100px auto;
		  -webkit-animation: sk-rotateplane 1.2s infinite ease-in-out;
		  animation: sk-rotateplane 1.2s infinite ease-in-out;
		}

		@-webkit-keyframes sk-rotateplane {
		  0% { -webkit-transform: perspective(120px) }
		  50% { -webkit-transform: perspective(120px) rotateY(180deg) }
		  100% { -webkit-transform: perspective(120px) rotateY(180deg)  rotateX(180deg) }
		}

		@keyframes sk-rotateplane {
		  0% { 
		    transform: perspective(120px) rotateX(0deg) rotateY(0deg);
		    -webkit-transform: perspective(120px) rotateX(0deg) rotateY(0deg) 
		  } 50% { 
		    transform: perspective(120px) rotateX(-180.1deg) rotateY(0deg);
		    -webkit-transform: perspective(120px) rotateX(-180.1deg) rotateY(0deg) 
		  } 100% { 
		    transform: perspective(120px) rotateX(-180deg) rotateY(-179.9deg);
		    -webkit-transform: perspective(120px) rotateX(-180deg) rotateY(-179.9deg);
		  }
		}
	</style>
	{literal}
		<script type="text/javascript">
			function sleep(milliseconds) {
				var start = new Date().getTime();
					for (var i = 0; i < 1e7; i++) {
						if ((new Date().getTime() - start) > milliseconds){
						break;
					}
				}
			}
			window.onload = function() {
				if (!window.location.hash) {
					window.location = window.location + '#loaded';
					sleep(5000);
					window.location.reload();
				}
			}
		</script>
	{/literal}
{else}
	<p>{l s='Your order has been taken into account.' mod='hipay_tpp'}
	    <br /><br />{l s='It will be available in a few moments in your' mod='hipay_tpp'} <strong><a href="{$link->getPageLink('history', true)}">{l s='order history' mod='hipay_tpp'}</a></strong>
	</p>
	<p><a href="index.php">{l s='Back to home' mod='hipay_tpp'}</a></p>
	{*
	 *
	 * HERE CODE FOR TAG ANALYTICS
	 *
	 *}
{/if}


