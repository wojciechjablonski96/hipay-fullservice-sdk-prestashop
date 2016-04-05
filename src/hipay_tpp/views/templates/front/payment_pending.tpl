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

{literal}
<script>
window.onload = function() {
    if(!window.location.hash) {
        window.location = window.location + '#loaded';
        window.location.reload();
    }
}
</script>
{/literal}
{capture name=path}{l s='HiPay payment.' mod='hipay_tpp'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}
<h2>{l s='Payment Summary' mod='hipay_tpp'}</h2>
<p>{l s='Your order is awaiting confirmation from the bank.' mod='hipay_tpp'}
    <br /><br />{l s='Once it is approved it will be available in your' mod='hipay_tpp'} <strong><a href="{$link->getPageLink('history', true)}">{l s='order history' mod='hipay_tpp'}</a></strong>
</p>
<p><a href="index.php">{l s='Back to home' mod='hipay_tpp'}</a></p>