/* 
 * EasyTransac oneclick.js
 */


window.onload = function () {
    jQuery(function ($) {
        'use strict';
        // Double load failsafe.
        var session_id = 'easytransac-oneclick' + Date.now();

        // Creates workspace.
        $('#easytransac-namespace').append($('<div id="' + session_id + '" class="payment_box payment_method_easytransac">'));

        // Requires : listcards_url
        var listcards_url = location.protocol + '//' + document.domain + '/module/easytransac/listcards';
        var oneclick_url = location.protocol + '//' + document.domain + '/module/easytransac/oneclick';

        $('#' + session_id).html('<span id="etocloa001">Chargement ...</span>');

        // JSON Call
        $.getJSON(listcards_url, {}, buildFromJson);

        // Build  OneClick form from JSON.
        function buildFromJson(json) {

            $('#etocloa001').fadeOut().remove();
            var dropdown = $('#etalcadd001').first();
            dropdown.isSelected = () => $('#etalcadd001 option:selected').val() != "";
            dropdown.getVal = () => $('#etalcadd001 > option:selected').val();

            // Prestashop pay button.

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

            if (!json.status || json.packet.length === 0) {

                // No cards available.
                $('#' + session_id).remove();
                return;
            }

            // Namespace
            var _space = $('#' + session_id);

            // Label
            var label = $('<span style="margin-left: -7px" title="Direct credit card payment"></span>')
            if (typeof (chooseCardi18n) !== 'undefined') {
                label[0].innerHTML = chooseCardi18n + ' ';
            }
            _space.append(label);

            // Dropdown
            _space.append($('<select id="etalcadd001" name="oneclick_alias" style="width:200p;margin-top:10px;text-align:center;">'));

            $('#etalcadd001')
                .append($('<option value="">---</option>'));

            json.packet.forEach(row => {
                $('#etalcadd001')
                    .append($('<option value="' + row.Alias + '">' + row.CardNumber + " " + row.CardMonth + "/" + row.CardYear + '</option>'));
            });

            // Button
            var button = $(' <input type="submit" id="etocbu001" class="button btn btn-primary center-block" style="margin-top:15px;margin-left: -6px;text-align:center;">');
            button.disable = () => {
                button[0].disabled = true;
            };
            button.enable = () => {
                button[0].disabled = false;
            };
            button.disable();

            if (typeof (payNowi18n) !== 'undefined') {
                button[0].value = payNowi18n;
            }
            _space.append(button);

            // On dropdown change.
            $('#etalcadd001').on('change', () => {

                if (dropdown.isSelected()) {

                    // Credit card token is selected
                    if ($('.custom-checkbox input[type=checkbox]').length === 1) {
                        if ($('.custom-checkbox input[type=checkbox]').is(':checked')) {
                            button.enable();
                            prestaButton.disable();
                        }
                    } else {
                        button.enable();
                        prestaButton.disable();
                    }
                } else {

                    //No credit card token selected.
                    button.disable();
                    prestaButton.enable();
                }
            });

            // On accept checkbox change.
            if ($('.custom-checkbox input[type=checkbox]').length === 1) {
                $('.custom-checkbox input[type=checkbox]').on('change', () => {
                    if ($('.custom-checkbox input[type=checkbox]').is(':checked')) {
                        if ($('#etalcadd001 option:selected').val() != '') {
                            button.enable();
                        }
                    } else {
                        button.disable();
                    }
                });
            }

            // Button click/*
            $('#etocbu001').click(function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if ($('.custom-checkbox input[type=checkbox]').length === 1) {
                    if (!$('.custom-checkbox input[type=checkbox]').is(':checked')) {
                        return;
                    }
                }
                _space.hide();

                // Set Drupal Commerce fields.
                $('#easytransac_is_oneclick').val('yes');
                $('#easytransac_oneclick_card').val(dropdown.getVal());
                $(this).parents('form').submit();

                var payload = { Alias: dropdown.getVal() };

                var _undisable = function () {
                    $('#easytransac-waiting-room').remove();
                    _space.fadeIn();
                    if (prestaButton) {
                        prestaButton.enable();
                    }
                };

                var loadingroom = $('<p id="easytransac-waiting-room" style="color:#AAA;font-size:1.2em;margin-left:-5px;"></p>');
                $('#easytransac-namespace').append(loadingroom);


                if (typeof (loadingi18n) !== 'undefined') {
                    loadingroom[0].innerHTML = loadingi18n;
                }

                if (prestaButton) {
                    prestaButton.disable();
                }

                // OneClick
                $.ajax({
                    url: oneclick_url,
                    data: payload,
                    type: 'POST',
                    dataType: 'json'
                }).done(function (data) {
                    if (data.error === 'no') {
                        if (data.paid_status === 'processed') {
                            window.location.href = data.redirect_page;
                        } else {
                            alert('EasyTransac : Payment failed');
                            _undisable();
                        }
                    } else {
                        alert('Payment Error: ' + data.message);
                        _undisable();
                    }
                }).fail(_undisable);
            });
        }
    });
};
