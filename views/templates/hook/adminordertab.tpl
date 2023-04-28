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
  <div class="card-header">
    <h3 class="card-header-title">
      Easytransac
    </h3>
  </div>

  <div class="card-body">
    
    <div class="form-group row type-hidden ">
      
      <label for="order_payment__token" class="form-control-label ">
                

              </label>
    
    <div class="col-sm">
      {$notice|escape:'html':'UTF-8'}
    </div>

{if $show_history}

  </div><table class="table">
      <thead>
        <tr>
          <th class="table-head-date">
          	{l s='Date' mod='easytransac'}
          </th>

          <th class="table-head-message">
          	{l s='Message' mod='easytransac'}</th>

          <th class="table-head-message">
          	{l s='Status' mod='easytransac'}</th>

          <th class="table-head-transaction">
          	{l s='Transaction ID' mod='easytransac'}</th>
          <th class="table-head-amount">
          	{l s='Amount' mod='easytransac'}</th>
        </tr>
      </thead>
      <tbody>

{foreach from=$history item=item}
        <tr class="d-print-none">
          <td>
          {$item['date']|escape:'html':'UTF-8'}
          </td>

          <td>
          {$item['message']|escape:'html':'UTF-8'}
          </td>

          <td>
          {$item['status']|escape:'html':'UTF-8'}
          </td>

          <td>
          {$item['external_id']|escape:'html':'UTF-8'}
          </td>

          <td>
          {if $item['amount'] != 0}
            {$item['amount']|escape:'html':'UTF-8'} €
          {/if}
          </td>
        </tr>
 {/foreach}
      
      </tbody>
    </table>

{/if}
      </div>