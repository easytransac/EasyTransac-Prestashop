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
<script type="text/javascript" src="{if isset($force_ssl) && $force_ssl}{$base_dir_ssl}{else}{$base_dir}{/if}/modules/easytransac/views/js/oneclick.js"></script>