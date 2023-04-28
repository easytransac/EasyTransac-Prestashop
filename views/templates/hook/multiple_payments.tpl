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
<h5 class="easytransac">{l s='Payment in instalments' mod='easytransac'}</h5>

<style>
.multipayment_method_easytransac{
    display: grid;
    grid-column-gap: 0.5rem;
    grid-template-columns: repeat(auto-fit, minmax(100px, 0.33fr));
    grid-row-gap: 1rem;

}

.etlabeltext{
    display: grid;
    align-items: center;
}
    .etlabeltext > label{
        text-align: center;
    }

#easytransac-multipay-namespace{
    padding-top: 17px;
}

    @media (max-width: 600px) {
        .multipayment_method_easytransac{
            grid-template-columns: repeat(1, 1fr);
        }
    }

h5.easytransac{
    padding-top: 1rem;
}
</style>

<div id="easytransac-multipay-namespace"> 
</div>

<script type="text/javascript">
    const i18n2 = '{l s='Pay in 2 times' mod='easytransac'}';
    const i18n3 = '{l s='Pay in 3 times' mod='easytransac'}';
    const i18n4 = '{l s='Pay in 4 times' mod='easytransac'}';
    const i18nLabel = '{l s='Recurrence' mod='easytransac'}';
    const enableInstallment = [
                                [{$enableInstallment2|escape:'html':'UTF-8'}, i18n2, 2],
                                [{$enableInstallment3|escape:'html':'UTF-8'}, i18n3, 3],
                                [{$enableInstallment4|escape:'html':'UTF-8'}, i18n4, 4],
    ];
                            
</script>
<script type="text/javascript" src="{$urls.base_url}/modules/easytransac/views/js/multiple_payments.js"></script>