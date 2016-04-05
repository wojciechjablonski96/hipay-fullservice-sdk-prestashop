{*
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
*}

{if $status == 'ok'}
	<p>{l s='Your order on %s is complete.' sprintf=$shop_name mod='hipay_tpp'}</p>
	<pre>
		{$printHipay}
	</pre>
{else}
	<p class="warning">
		{l s='We noticed a problem with your order. If you think this is an error, feel free to contact our'  mod='hipay_tpp'} 
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='expert customer support team. ' mod='hipay_tpp'}</a>.
	</p>
{/if}
