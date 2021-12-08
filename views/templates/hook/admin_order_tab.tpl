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
      {$notice}
    </div>

{if $show_history}

  </div><table class="table">
      <thead>
        <tr>
          <th class="table-head-date">Date</th>
          <th class="table-head-message">message</th>
          <th class="table-head-transaction">Transaction ID</th>
          <th class="table-head-amount">Amount</th>
        </tr>
      </thead>
      <tbody>

{foreach from=$history item=item}
        <tr class="d-print-none">
          <td>
          {$item['date']}
          </td>

          <td>
          {$item['message']}
          </td>

          <td>
          {$item['external_id']}
          </td>

          <td>
          {if $item['amount'] != 0}
            {$item['amount']} €
          {/if}
          </td>
        </tr>
 {/foreach}
      
      </tbody>
    </table>

{/if}
      </div>