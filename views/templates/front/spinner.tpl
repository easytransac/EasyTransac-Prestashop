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



