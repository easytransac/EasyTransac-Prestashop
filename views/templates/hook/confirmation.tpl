<!--/**
 * Copyright (c) 2022 Easytransac
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
 -->
<p>
	{if $isAccepted}
		{l s='Thank you for your order!' mod='easytransac'}
	
		<br /><br /><span class="bold">{l s='Your order will be sent very soon.' mod='easytransac'}</span>
		<br /><br />{l s='For any questions or for further information, please contact our' mod='easytransac'}
			<a href="{$link->getPageLink('contact', true)}" data-ajax="false" target="_blank">{l s='customer support' mod='easytransac'}</a>.
		
	{else if $isCanceled}
		<span class="bold">{l s='Your payment was canceled.' mod='easytransac'}</span>
		<br /><br />{l s='For any questions or for further information, please contact our' mod='easytransac'}
			<a href="{$link->getPageLink('contact', true)}" data-ajax="false" target="_blank">{l s='customer support' mod='easytransac'}</a>.
		
	{else if $isPending}
		{l s='Your order on' mod='easytransac'} <span class="bold">{$shop_name|escape:'htmlall':'UTF-8'}</span> {l s='is currently being processed.' mod='easytransac'}
		<br /><br />
		<div class="easytproclo"></div>
		<img src="{$base_dir_ssl}modules/easytransac/views/img/loader.gif" height="46px" width="46px"/>
		{l s='Please wait for EasyTransac payment confirmation...' mod='easytransac'}
		
		{literal}
		<script>
			setTimeout(function(){location.reload();}, 5000);
		</script>
		{/literal}
	{/if}
</p>
