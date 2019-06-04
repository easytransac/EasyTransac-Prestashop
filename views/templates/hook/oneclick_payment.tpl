<div class="row">
    <div class="col-xs-12 col-md-6">
        <p class="payment_module easytransac">
                                    
        </p>
                    <div id="easytransac-namespace" style="margin-left: 20px;height:100px;">
                    </div>
                    
                    <script type="text/javascript">
                        var chooseCard = '{l s='Choose a card:' mod='easytransac'}';
                        var payNow = '{l s='Pay now' mod='easytransac'}';
                    </script>
                    <script type="text/javascript" src="{if isset($force_ssl) && $force_ssl}{$base_dir_ssl}{else}{$base_dir}{/if}/modules/easytransac/views/js/oneclick.js"></script>
    </div>
</div>