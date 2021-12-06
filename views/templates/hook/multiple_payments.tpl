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
                                [{$enableInstallment2}, i18n2, 2],
                                [{$enableInstallment3}, i18n3, 3],
                                [{$enableInstallment4}, i18n4, 4],
    ];
                            
</script>
<script type="text/javascript" src="{$urls.base_url}/modules/easytransac/views/js/multiple_payments.js"></script>