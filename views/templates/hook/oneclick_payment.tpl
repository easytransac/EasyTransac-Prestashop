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
<p class="payment_module easytransac">
                            
</p>
<h5 class="easytransac">{l s='One click payment' mod='easytransac'}</h5>

<style>
.payment_method_easytransac{
    display: grid;
    grid-column-gap: 0.5rem;
    grid-row-gap: 1rem;
    grid-template-columns: 1fr 1fr 1fr;
    padding-top: 15px;
}

    .etlabeltext > label{
        text-align: center;
    }

@media (max-width: 600px) {
    .payment_method_easytransac{
        grid-template-columns: repeat(1, 1fr);
    }
}

h5.easytransac{
    padding-top: 1rem;
}

#etocbu001{
    width: 97%;
}
@media (max-width: 600px) {
    #etocbu001{
        width: 100%;
    }
}
</style>

<div id="easytransac-namespace">
</div>

<script type="text/javascript">
    const chooseCardi18n = '{l s='Choose a card' mod='easytransac'}';
    const payNowi18n = '{l s='Pay now' mod='easytransac'}';
    const loadingi18n = '{l s='Please wait ...' mod='easytransac'}';
</script>
<script type="text/javascript" src="{$urls.base_url}/modules/easytransac/views/js/oneclick.js"></script>