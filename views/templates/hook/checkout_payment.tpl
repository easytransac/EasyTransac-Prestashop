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
