/* 
 * EasyTransac multiple_payments.js
 */

var ETPaymentInstallment = function () {

    jQuery(function ($) {
        'use strict';
        // Double load failsafe.
        var session_id = 'easytransac-multipay' + Date.now();
        
        // Creates workspace.
        $('#easytransac-multipay-namespace').append($('<div id="' + session_id + '" class="payment_box multipayment_method_easytransac">'));

        // Endpoint.
        var multipay_url = location.protocol + '//' + document.domain + '/module/easytransac/multipay';

        // Prestashop Place Order button.
        var prestaButton = null;
        if ($('#payment-confirmation .btn.btn-primary.center-block').length > 0) {
            prestaButton = $('#payment-confirmation .btn.btn-primary.center-block').first();
            prestaButton.disable = () => prestaButton[0].disabled = true;
            prestaButton.enable = () => prestaButton[0].disabled = false;
        } else {
            prestaButton = {};
            prestaButton.disable = () => { };
            prestaButton.enable = () => { };
        }


        // Namespace
        var _space = $('#' + session_id);

        // var _labeldiv = $('<div class="etlabeltext">');
        // var _label = $('<label id="installmentlabelet">');
        // // _label.text(i18nLabel);
        // _labeldiv.append(_label)
        // _space.append(_labeldiv);

        // Payment installments buttons.
        var buttons = [];

        for(const i in enableInstallment){
            let isEnabled   = enableInstallment[i][0];
            let title       = enableInstallment[i][1];
            let count       = enableInstallment[i][2];

            if(!isEnabled){
                continue;
            }
            let button = $('<input type="submit" class="etinstallpay button btn btn-primary center-block" style="display:block;text-align:center;">');

            button.data('installmentcount', count);

            button.disable = () => {
                button[0].disabled = true;
            };
            button.enable = () => {
                button[0].disabled = false;
            };
            button.disable();
    
            button[0].value = count+'x';
            _space.append(button);

            buttons.push(button);
        }

        // On accept conditions checkbox change.
        if ($('.custom-checkbox input[type=checkbox]').length === 1) {
            $('.custom-checkbox input[type=checkbox]').on('change', () => {
                if ($('.custom-checkbox input[type=checkbox]').is(':checked')) {
                    buttons.forEach(element => element.enable());
                } else {
                    buttons.forEach(element => element.disable());
                    // button.disable();
                }
            });
        }

        // Installments payments button click/*
        $('.etinstallpay').click(function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if ($('.custom-checkbox input[type=checkbox]').length === 1) {
                if (!$('.custom-checkbox input[type=checkbox]').is(':checked')) {
                    return;
                }
            }
            _space.hide();

            // $('#easytransac_is_oneclick').val('yes');
            // $('#easytransac_oneclick_card').val(dropdown.getVal());
            $(this).parents('form').submit();

            var payload = {
                'Count': $(this).data('installmentcount'),
            };

            var _undisable = function () {
                _space.fadeIn();
                if (prestaButton) {
                    prestaButton.enable();
                }
            };
            
            if (prestaButton) {
                prestaButton.disable();
            }

            // Request multiple payments page.
            $.ajax({
                url: multipay_url,
                data: payload,
                type: 'POST',
                dataType: 'json'
            }).done(function (data) {
                if (data.error === 'no') {
                    window.location.href = data.redirect_page;
                } else {
                    alert('Multiple payment page request Error: ' + data.message);
                    _undisable();
                }
            }).fail(_undisable);
        });
    });
};

if (window.addEventListener) // W3C standard
{
  window.addEventListener('load', ETPaymentInstallment, false);
} 
else if (window.attachEvent) // Microsoft
{
  window.attachEvent('onload', ETPaymentInstallment);
}
