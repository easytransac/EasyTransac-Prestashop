
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
{capture name=path}<a href="order.php">{l s='Your shopping cart' mod='easytransac'}</a><span class="navigation-pipe"> {$navigationPipe|escape:'htmlall':'UTF-8'} </span> {l s='EasyTransac' mod='easytransac'}{/capture}


<h2>{$message|escape:'htmlall':'UTF-8'}</h2>
{if isset($logs) && $logs}
	<div class="error">
		<p><b>{l s='Please try to contact the merchant:' mod='easytransac'}</b></p>

		<ol>
			{foreach from=$logs key=key item=log}
				<li>{$log|escape:'htmlall':'UTF-8'}</li>
				{/foreach}
		</ol>

		<br>	

		<p><a href="/" class="button_small" title="{l s='Back' mod='easytransac'}">&laquo; {l s='Back' mod='easytransac'}</a></p>
	</div>

{/if}
