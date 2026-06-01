/**
 * 2018-2026 GURU CODERS SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    GURU CODERS SRL <stickyrst@gmail.com>
 * @copyright 2018-2026 GURU CODERS SRL
 * @license   Commercial
 */

$(document).ready(function() {
    const rc_fancourier_set_cookie = (key, value, days) => {
        let d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        let expires = "expires=" + d.toUTCString();
        document.cookie = key + "=" + value + ";" + expires + ";path=/";
    }

    const rc_fancourier_get_cookie = (key) => {
        let cookie = '';
        document.cookie.split(';').forEach(function (value) {
            if (value.split('=')[0].trim() === key) {
                return cookie = value.split('=')[1];
            }
        });

        return cookie;
    }


    let selectedPickUpPoint = null;

    if (rc_fancourier_get_cookie('fancourier_locker_name').length) {
        $(".FANshowLockerDetails").html(rc_fancourier_get_cookie('fancourier_locker_name'));
    }

    window.addEventListener("map:select-point", event => {
        selectedPickUpPoint = event.detail.item;
        rc_fancourier_set_cookie('fancourier_locker_name', selectedPickUpPoint.name, 30);
        rc_fancourier_set_cookie('fancourier_locker_details', JSON.stringify(selectedPickUpPoint), 30);
        $(".FANshowLockerDetails").html(selectedPickUpPoint.name);

        $.ajax({
            type: 'POST',
            url: rc_fancourier_ajax_url,
            data: {
                action: 'insertFanboxCartViaMap',
                rc_fancourier_token: rc_fancourier_token_front,
                fancourier_locker_name: selectedPickUpPoint.name,
                fancourier_locker_details: JSON.stringify(selectedPickUpPoint)
            }
        });
    });

    const rootNode = document.getElementById("FANmapDiv");
    const btn = document.getElementById("showFANboxMap");

    $(document).on('click', '#FANopenMap', () => {
        rc_fancourier_set_cookie('fancourier_locker_name', '', 30);
        $(".FANshowLockerDetails").html('');

        window.LoadMapFanBox({
            pickUpPoint: selectedPickUpPoint,
            county: rc_fancourier_defaultCounty,
            locality: rc_fancourier_defaultLocality,
            rootNode,
        });
    });

    $('#rc_fancourier_fanbox_office').on('change', function() {
       var officeName = $(this).val();

        $.ajax({
            type: 'POST',
            url: rc_fancourier_ajax_url,
            data: {
                action: 'insertFanboxCartViaSelect',
                rc_fancourier_token: rc_fancourier_token_front,
                officeName: officeName
            },
            success: function() {}
        });
    });
});

$(function () {
    if (typeof RC_FANCOURIER_CITY_SELECTOR != 'undefined' && RC_FANCOURIER_CITY_SELECTOR) {
        $(document).ready(function () {
            $(document).on('change', '[name="id_state"]', function () {
                loadCitiesSelect($('[name="id_state"]').val(), 'city', $('[name="city"]').val());
            });
            $(document).on('change', '[name="id_state_invoice"]', function () {
                loadCitiesSelect($('[name="id_state_invoice"]').val(), 'city_invoice', $('[name="city_invoice"]').val());
            });
            $(document).on('change', '[name="delivery_id_state"]', function () {
                loadCitiesSelect($('[name="delivery_id_state"]').val(), 'delivery_city', $('[name="delivery_city"]').val());
            });
            $(document).on('change', '[name="invoice_id_state"]', function () {
                loadCitiesSelect($('[name="invoice_id_state"]').val(), 'invoice_city', $('[name="invoice_city"]').val());
            });
            $(document).on('change', '[name="shipping_address[id_state]"]', function () {
                loadCitiesSelect($('[name="shipping_address[id_state]"]').val(), 'shipping_address[city]', $('[name="shipping_address[city]"]').val());
            });
            $(document).on('change', '[name="payment_address[id_state]"]', function () {
                loadCitiesSelect($('[name="payment_address[id_state]"]').val(), 'payment_address[city]', $('[name="payment_address[city]"]').val());
            });
            $(document).on('change', '[name="delivery_city"]', function(e) {
                if (typeof Carrier === 'object' && typeof Carrier.getByCountry === 'function') {
                    Carrier.getByCountry();
                }
            });
            if ($(document).find('[name="city"]').length) {
                if ($('[name="id_state"]').val()) {
                    setTimeout(function () {
                        loadCitiesSelect($('[name="id_state"]').val(), 'city', $('[name="city"]').val());
                    }, 1000);
                }
            }
            if ($(document).find('[name="city_invoice"]').length) {
                if ($('[name="id_state_invoice"]').val()) {
                    setTimeout(function () {
                        loadCitiesSelect($('[name="id_state_invoice"]').val(), 'city_invoice', $('[name="city_invoice"]').val());
                    }, 1000);
                }
            }

            $(window).on('rc_fancourier_load_cities', function() {
                loadCitiesSelect($('[name="delivery_id_state"]').val(), 'delivery_city', $('[name="delivery_city"]').val());
                loadCitiesSelect($('[name="invoice_id_state"]').val(), 'invoice_city', $('[name="invoice_city"]').val());
            });

            if (typeof prestashop !== 'undefined' && typeof prestashop.on !== 'undefined') {
                prestashop.on('steco_event_updated', function (e) {
                    if ($('.st_address_form_delivery [name="id_state"]').val()) {
                        setTimeout(function () {
                            loadCitiesSelect($('.st_address_form_delivery [name="id_state"]').val(), 'city', $('.st_address_form_delivery [name="city"]').val(), '.st_address_form_delivery');
                        }, 1);
                    }

                    if ($('.st_address_form_invoice [name="id_state"]').val()) {
                        setTimeout(function () {
                            loadCitiesSelect($('.st_address_form_invoice [name="id_state"]').val(), 'city', $('.st_address_form_invoice [name="city"]').val(), '.st_address_form_invoice');
                        }, 1);
                    }
                });
            }

            if (typeof RC_FANCOURIER_SELECT2 !== 'undefined' && RC_FANCOURIER_SELECT2) {
                $('[name="id_state"], [name="id_state_invoice"], [name="delivery_id_state"], [name="invoice_id_state"], [name="shipping_address[id_state]"], [name="payment_address[id_state]"]').trigger('change.select2');
                $('[name="id_state"], [name="id_state_invoice"], [name="delivery_id_state"], [name="invoice_id_state"], [name="shipping_address[id_state]"], [name="payment_address[id_state]"]').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).removeClass('select2-hidden-accessible');
                        $(this).select2('destroy');
                        $(this).select2();
                    } else {
                        $(this).select2();
                    }
                });
            }
        });
    }
});

function loadCitiesSelect(id_state, city_input_name, selected, city_input_selector_prefix) {
    if (!id_state) {
        return;
    }
    var delivery_or_invoice = 'delivery';
    if (city_input_name.includes('invoice')) {
        delivery_or_invoice = 'invoice';
    }
    var cities_class = $('[name="' + city_input_name + '"]').attr('class');
    if (cities_class && !cities_class.includes('form-control-select')) {
        cities_class += ' form-control-select';
    }
    $.ajax({
        type: "GET",
        url: rc_fancourier_ajax_url,
        data: 'action=getCities&id_state=' + id_state + '&rc_fancourier_token=' + rc_fancourier_token_front,
        success: function (data) {
            if (data && data.success) {
                var cities_html = '<select name="' + city_input_name + '" id="' + city_input_name + '" class="' + cities_class + '" data-validate="isGenericName" data-field-name="city">';
                for (var i in data.cities) {
                    cities_html += '<option value="' + data.cities[i].name + '"';
                    if (selected && selected.toLowerCase() == data.cities[i].name.toLowerCase()) {
                        cities_html += ' selected';
                    }
                    cities_html += '>' + data.cities[i].label + '</option>';
                }
                cities_html += '</select>';
                if (typeof $.uniform != 'undefined') {
                    $.uniform.restore();
                }
                if (city_input_selector_prefix) {
                    $(city_input_selector_prefix + ' [name="' + city_input_name + '"]').replaceWith(cities_html);
                    $(city_input_selector_prefix + ' [name="' + city_input_name + '"]').change().blur();
                } else {
                    $('[name="' + city_input_name + '"]').replaceWith(cities_html);
                    $('[name="' + city_input_name + '"]').change().blur();
                }
                if (typeof bindUniform != 'undefined') {
                    bindUniform();
                }
                if (typeof RC_FANCOURIER_SELECT2 !== 'undefined' && RC_FANCOURIER_SELECT2) {
                    $('[name="' + city_input_name + '"]').trigger('change.select2');
                    $('[name="' + city_input_name + '"]').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).removeClass('select2-hidden-accessible');
                            $(this).select2('destroy');
                            $(this).select2();
                        } else {
                            $(this).select2();
                        }
                    });
                }
            } else {
                var cities_html = '<input type="text" class="' + cities_class + '" name="' + city_input_name + '" id="' + city_input_name + '" data-validate="isCityName" value="' + selected + '" data-field-name="city">';
                if (typeof $.uniform != 'undefined') {
                    $.uniform.restore();
                }
                $('[name="' + city_input_name + '"]').replaceWith(cities_html);
                if (typeof bindUniform != 'undefined') {
                    bindUniform();
                }
            }
        },
        dataType: 'json',
    });
}