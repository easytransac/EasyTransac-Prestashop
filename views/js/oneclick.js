/**
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
 
var ETOneClick = function () {
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
            var _labeldiv = $('<div class="etlabeltext">');

            var label = $('<label title="Direct credit card payment"></label>')
            if (typeof (chooseCardi18n) !== 'undefined') {
                label[0].innerHTML = chooseCardi18n + ' ';
            }
            _labeldiv.append(label);
            _space.append(_labeldiv);

            // Dropdown
            _space.append($('<select id="etalcadd001" class="custom-select form-control" name="oneclick_alias" style="text-align:center;">'));

            $('#etalcadd001')
                .append($('<option value="">---</option>'));

            json.packet.forEach(row => {
                $('#etalcadd001')
                    .append($('<option value="' + row.Alias + '">' + row.CardNumber + " " + row.CardMonth + "/" + row.CardYear + '</option>'));
            });

            // Button
            var button = $(' <input type="submit" id="etocbu001" class="button btn btn-primary center-block" style="text-align:center;">');
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
            var _divbtn = $('<div class="oneclick-et-btndiv">');
            _divbtn.append(button);
            _space.append(_divbtn);

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

                    console.log(data);
                    console.log(typeof(data.redirect_page));
                    
                    if (typeof(data.redirect_page) !== 'undefined') {
                        window.location.href = data.redirect_page;
                        return;
                    }

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

if (window.addEventListener) // W3C standard
{
  window.addEventListener('load', ETOneClick, false);
} 
else if (window.attachEvent) // Microsoft
{
  window.attachEvent('onload', ETOneClick);
}