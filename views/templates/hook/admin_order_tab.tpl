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
        <tr class="d-print-none">
          <td>
          </td>

          <td>
          </td>

          <td>
          </td>

          <td>
          </td>
        </tr>
      </tbody>
    </table>

{/if}
      </div>