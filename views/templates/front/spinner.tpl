
<div class="box cheque-box">

	<h3 class="page-subheading">EasyTransac</h3>

	<p>
		{l s='Your payment is currently being processed.' mod='easytransac'}
		<br /><br />
	<div class="easytproclo"></div>
	<img src="/modules/easytransac/views/img/loader.gif" height="46px" width="46px"/>
	{l s='Please wait for EasyTransac payment confirmation...' mod='easytransac'}

	{literal}
		<script>
			setTimeout(function () {
				location.reload();
			}, 5000);
		</script>
	{/literal}
	</p>
</div>



