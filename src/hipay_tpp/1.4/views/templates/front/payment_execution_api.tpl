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

<h2>{l s='Order summary' mod='hipay_tpp'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
	<p class="warning">{l s='Your shopping cart is empty.' mod='hipay_tpp'}</p>
{else}
	<h3>{l s='HiPay payment.' mod='hipay_tpp'}</h3>
{if $status_error=='200'}
	{*}No display of errors{*}
{else if $status_error=='400'}
	<p class="error">{l s='The request was rejected due to a validation error. Please verify the card details you entered.' mod='hipay_tpp'}</p>
{else if $status_error=='503'}
	<p class="error">{l s='HiPay TPP is temporarily unable to process the request. Try again later.' mod='hipay_tpp'}</p>
{else if $status_error=='403'}
	<p class="error">{l s='A forbidden action has been identified, process has been cancelled.' mod='hipay_tpp'}</p>
{else if $status_error=='999'}
	<p class="error">{l s='Please select one of the memorized card before continuing.' mod='hipay_tpp'}</p>
{else}
	<p class="error">
	    <strong>{l s='Error code' mod='hipay_tpp'} : {$status_error}</strong>
	    <br />
	    {l s='An error occured, process has been cancelled.' mod='hipay_tpp'}
	</p>
{/if}
    <form enctype="application/x-www-form-urlencoded" class="form-horizontal" action="{$this_path_ssl}14paymentapi.php" method="post" name="tokenizerForm" id="tokenizerForm" autocomplete="off">
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
               {$amount} {$currency}
            </div>
            <div style="clear: both;"></div>
        </div>
        <br />
        {if ($allow_memorize == 'true')}
        {if ($token_display == 'true')}
        <div class="control-group">
            <label class="control-label" style="float: left; margin: 0 0px 0 0; font-size: 15px; font-weight: bold;">{l s='Entry type' mod='hipay_tpp'}:&nbsp;</label>
            <div class="controls" style="float: left; ">
            	<table><tr>
               <td><input type="radio" name="cartUseExistingToken" value="1" id="cartUseExistingTokenOne" style="float: left;" /> </td>
               <td><label class="control-label-radio" for="cartUseExistingTokenOne" style="float:left; margin: 0 0 0 5px; font-size: 15px; font-weight: bold;">{l s='Use memorized card' mod='hipay_tpp'}</label></td>
               </tr><tr>
               <td><input type="radio" name="cartUseExistingToken" value="0" checked="checked" id="cartUseExistingTokenZero" style="float: left;" /></td>
               <td><label class="control-label-radio" for="cartUseExistingTokenZero" style="float:left; margin: 0 0 0 5px; font-size: 15px; font-weight: bold;">{l s='Enter card details' mod='hipay_tpp'}</label></td>
               </tr></table>
            </div>
            <div style="clear: both;"></div>
        </div>
        <br />
        {else}
           <input type="hidden" class="input-medium" name="cartUseExistingToken" id="cartUseExistingToken" value="0">
        {/if}
        {/if}
    </span>
    <input type="hidden" class="input-medium" name="cartCurrency" id="cartCurrency" value="{$currency}">
    <input type="hidden" class="input-medium" name="cartAmount" id="cartAmount" value="{$amount}">
    <input type="hidden" value="tokenizerForm" name="tokenizerForm">

    
{if ($allow_memorize == 'true')}
{if ($token_display == 'true')}
    <div class="enter_token" style="display:none;border: 1px solid rgb(204, 204, 204); border-radius: 15px; padding: 10px;">
        <div style="margin: 15px 0 0 0;">
            <span id="cardTokenEnabled">
                <div class="control-group">
                    <label class="control-label" for="cardToken" style="float:left; margin:0px 49px 0px 0px; font-size:15px; font-weight:bold; width:170px;">{l s='Select your card' mod='hipay_tpp'}: &nbsp;</label>
                    <div class="controls" style="float:left;">
                        <select size="1" class="input-mini" name="cardToken" id="cardToken">
                            <option value="">{l s='Please select your card' mod='hipay_tpp'}</option>
                            {foreach $tokens as $key=>$value}
                                <option value="{$value['token']}">{$value['brand']} / {$value['pan']}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div style="clear: both;"></div>
            </span>
        </div>
        <br />
    </div>
{/if}
{/if}
{assign var='isAMEX' value='false'}
    <div class="enter_card" style="border:1px solid #CCC;border-radius:15px;padding:10px;">
        <div class="control-group">
            <label class="control-label" style="float: left; margin: 0 57px 0 0; font-size: 15px; font-weight: bold;">{l s='Card Type' mod='hipay_tpp'}: &nbsp;</label>
            <div class="controls" style="float: left;">
                <select class="input-xlarge" id="payment-product-switcher" name="paymentproductswitcher" style="width:160px;">
                    {foreach key=keyvalue item=carte from=$cartes}
                        <option value="{$keyvalue}">{$carte}</option>
                        {if ($keyvalue == 'american-express')}
                            {assign var='isAMEX' value='true'}
                        {/if}
                    {/foreach}
                </select>
            </div>
            <div style="clear: both;"></div>
        </div>
        <div class="control-group" style="margin-top: 15px;">
            <label class="control-label" for="cardNumber" style="float: left; margin: 0 35px 0 0; font-size: 15px; font-weight: bold;">{l s='Card Number' mod='hipay_tpp'}: &nbsp;</label>
            <div class="controls" style="float: left;">
                <input type="text" class="input-medium" name="cardNumber" id="cardNumber" style="padding:4px;font-size:13px;border-radius:5px;"><span id="cardNumberMessage"></span>
            </div>
            <div style="clear: both;"></div>
        </div>
        <div class="control-group" style="margin-top: 15px;">
                <span id="cardHolderEnabled" style="margin-top: 15px;{if ($isAMEX)}display:none;{else}{/if}" >
                    <div class="control-group">
                        <label class="control-label" for="cardHolder" style="float: left; margin: 0 0px 0 0; font-size: 15px; font-weight: bold;">{l s='Card Holder Name' mod='hipay_tpp'}: &nbsp;</label>
                        <div class="controls" style="float: left;">
                            <input type="text" class="input-medium" name="cardHolder" id="cardHolder" style="padding:4px;font-size:13px;border-radius:5px;"><span id="cardHolderMessage"></span>
                        </div>
                    </div>
                    <div style="clear: both;"></div>
                </span>
		</div>
		<div class="control-group" style="margin-top: 15px;">
                <span id="cardFirstNameEnabled" style="margin-top: 15px;{if ($isAMEX)}{else}display:none;{/if}" >
                    <div class="control-group">
                        <label class="control-label" for="cardFirstName" style="float: left; margin: 0 53px 0 0; font-size: 15px; font-weight: bold;">{l s='First Name' mod='hipay_tpp'}: &nbsp;</label>
                        <div class="controls" style="float: left;">
                            <input type="text" class="input-medium" name="cardFirstName" id="cardFirstName" style="padding:4px;font-size:13px;border-radius:5px;"><span id="cardFirstNameMessage"></span>
                        </div>
                    </div>
                    <div style="clear: both;"></div>
                </span>
		</div>
		<div class="control-group" style="margin-top: 15px;">
                <span id="cardLastNameEnabled" style="margin-top: 50px;{if ($isAMEX)}{else}display:none;{/if}" >
                    <div class="control-group">
                        <label class="control-label" for="cardLastName" style="float: left; margin: 0 53px 0 0; font-size: 15px; font-weight: bold;">{l s='Last Name' mod='hipay_tpp'}: &nbsp;</label>
                        <div class="controls" style="float: left;">
                            <input type="text" class="input-medium" name="cardLastName" id="cardLastName" style="padding:4px;font-size:13px;border-radius:5px;"><span id="cardLastNameMessage"></span>
                        </div>
                    </div>
                    <div style="clear: both;"></div>
                </span>
        </div>

              <div style="margin: 15px 0 0 0;">
                <span id="cardExpiryDateEnabled">
                    <div class="control-group">
                        <label class="control-label" for="cardExpiryMonth" style="float:left; margin: 0 49px 0 0; font-size: 15px; font-weight: bold;">{l s='Expiry Date' mod='hipay_tpp'}: &nbsp;</label>
                        <div class="controls" style="float:left;">
                            <select size="1" class="input-mini" name="cardExpiryMonth" id="cardExpiryMonth">
                                <option value="01">01</option>
                                <option value="02">02</option>
                                <option value="03">03</option>
                                <option value="04">04</option>
                                <option value="05">05</option>
                                <option value="06">06</option>
                                <option value="07">07</option>
                                <option value="08">08</option>
                                <option value="09">09</option>
                                <option value="10">10</option>
                                <option value="11">11</option>
                                <option value="12">12</option>
                            </select>
                            &nbsp;
                            {assign var='current_year' value=$smarty.now|date_format:"%Y"}
                            {assign var='end_year' value=($current_year+10)}
                            <select size="1" class="input-mini" name="cardExpiryYear" id="cardExpiryYear">
                                {while $current_year <= $end_year}
                                    <option value="{$current_year}">{$current_year}</option>
                                    {$current_year++}
                                {/while}
                            </select>
                            <span id="cardExpiryYearMessage"></span>
                        </div>
                    </div>
                    <div style="clear: both;"></div>
                </span>
              </div>

            <div style="margin: 15px 0 0 0;"> 
                <span id="cardSecurityCodeEnabled">
                    <div class="control-group">
                        <label class="control-label" for="cardSecurityCode" style="float:left; margin: 0 35px 0 0; font-size: 15px; font-weight: bold;">{l s='Cryptogramme' mod='hipay_tpp'}<i id="cardSecurityCodeInfo" class="glyphicons circle_info"></i></label>
                        <div class="controls">
                            <input type="text" class="input-medium" name="cardSecurityCode" id="cardSecurityCode" style="padding:4px;font-size:13px;border-radius:5px;"><span id="cardSecurityCodeMessage"></span>
                        </div>
                    </div>
                    <div style="clear: both;"></div>
                </span>
            </div>
            {if ($allow_memorize == 'true')}   
            <div style="margin: 15px 0 0 0;"> 
                <span id="cardMemorizedEnabled">
                    <div class="control-group">
                        <label class="control-label" for="cardMemorizeCode" style="float:left; margin: 0 35px 0 0; font-size: 15px; font-weight: bold;">{l s='Memorize the card' mod='hipay_tpp'}<i id="cardMemorizeCodeInfo" class="glyphicons circle_info"></i></label>
                        <div class="controls">
                            <input type="checkbox" class="input-medium" name="cardMemorizeCode" id="cardMemorizeCode" value="memorize"><span id="cardSecurityCodeMessage"></span>
                        </div>
                    </div>
                    <div style="clear: both;"></div>
                </span>
            </div>
            {else}
                <input type="hidden" class="input-medium" name="cardMemorizeCode" id="cardMemorizeCode" value="memorize">
            {/if}
    </div>

    <div style="margin: 15px 0 30px 180px;"> 
            <input id="hipay_payment" class="exclusive standard-checkout" type="submit" value="{l s='Send' mod='hipay_tpp'} &raquo;" />
    </div>

    </form>
{/if}
