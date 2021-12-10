<style>

body{
	font-family: "Open Sans", sans-serif;
	background: rgb(80,179,216);
	background: linear-gradient(90deg, rgba(80,179,216,1) 0%, rgba(87,95,167,1) 35%, rgba(0,212,255,1) 100%);
	padding-top:50px;
}
.container{
	background-color: white;
	border-radius: 18px;
	width: 60%;
	margin: 0 auto;
	padding: 20px;
	color: #333;
}

h3{
	color: white;
	margin: 0 auto;
	width: 60%;
 	padding-bottom: 50px
}
</style>

	<h3 class="page-subheading">Easytransac</h3>
<div class="container">


	<p>
		{l s='Your payment is currently being processed.' mod='easytransac'}
		<br /><br />
	{literal}
		<script>
			setTimeout(function () {
				location.reload();
			}, 5000);
		</script>
	{/literal}
	</p>
</div>



