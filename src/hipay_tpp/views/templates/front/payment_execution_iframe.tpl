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
{if ($show_breadcrumb)}
{include file="$tpl_dir./breadcrumb.tpl"}
{/if}

<h2>{l s='Order summary' mod='hipay_tpp'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
	<p class="warning">{l s='Your shopping cart is empty.' mod='hipay_tpp'}</p>
{else}
	<h3>{l s='HiPay payment.' mod='hipay_tpp'}</h3>
<span id="cartInformation">
        <div class="control-group">
            <label class="control-label" style="float: left; margin: 0 0px 0 0; font-size: 15px; font-weight: bold;">{l s='Order' mod='hipay_tpp'}:&nbsp;</label>
            <div class="controls" style="float: left; font-size: 13px; font-weight: bold;">
                #{$cart_id}<span id="cartIdMessage"></span>
                <input type="hidden" class="input-medium" name="cartId" id="cartId" value="{$cart_id}">
            </div>
            <div style="clear: both;"></div>
        </div>
        <br />
        <div class="control-group">
            <label class="control-label" style="float: left; margin: 0 0px 0 0; font-size: 15px; font-weight: bold;">{l s='Amount' mod='hipay_tpp'}:&nbsp;</label>
            <div class="controls" style="float: left; font-weight:bold; color:#072959;font-size:15px;">
               {$amount} {$currency}<span id="cartAmountMessage"></span>
            </div>
            <div style="clear: both;"></div>
        </div>
    </span>
    
    <div class="enter_card" style="border:1px solid #CCC;border-radius:15px;padding:10px;width:{$iframe_width}">
    <iframe src="{$iframe_url}" width="100%" height="{$iframe_height}" frameborder="0" ></iframe>
    </div>
{/if}
