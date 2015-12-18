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

{capture name=path}{l s='HiPay payment.' mod='hipay_tpp'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Payment Summary' mod='hipay_tpp'}</h2>

{if ($error_response == 'exception_error')}
	<div class="error">
		<p>{$error_message}</p>
	</div>
	
	<br />
	<h2>Error code: {$error_code}</h2>
{else}
	{if ($response_state == 'completed')}	
		<p>	
			{l s='Your order on %s is complete.' sprintf=$shop_name mod='hipay_tpp'}
			<br /><br />{l s='Payment amount' mod='hipay_tpp'}: <span class="price"><strong>{$total} &euro;</strong></span>
			<br /><br />{l s='An email has been sent to you.' mod='hipay_tpp'}
		</p>
		
	{elseif ($response_state == 'declined')}
		<p>
			{l s='Your order on %s has been cancelled.' sprintf=$shop_name mod='hipay_tpp'}	
		</p>
		
	{elseif ($response_state == 'pending')}	
		<p>
			{l s='Your order on %s is pending.' sprintf=$shop_name mod='hipay_tpp'}	
			<br /><br /><strong>{l s='Your order is awaiting confirmation from the bank. ' mod='hipay_tpp'}</strong>
		</p>
	{/if}
{/if}


