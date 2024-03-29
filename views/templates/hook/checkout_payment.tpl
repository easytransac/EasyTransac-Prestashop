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
{if $smarty.const._PS_VERSION_ >= 1.6}

	<div class="row">
		<div class="col-xs-12 col-md-6">
			<p class="payment_module easytransac">
					{l s='Pay with your credit card' mod='easytransac'}
                                        
			</p>
                        <div id="easytransac-namespace" style="margin-left: 20px;height:100px;">
                        </div>
		</div>
	</div>

{else}
	<p class="payment_module easytransac">
			{l s='Pay with your credit card' mod='easytransac'}
	</p>
{/if}

<style>
	p.payment_module.easytransac a 
	{ldelim}
	padding-left:17px;
	{rdelim}
</style>
