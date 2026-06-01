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

$(function () {
    $(document).ready(function () {
        $('#RC_FANCOURIER_FREETEXT1, #RC_FANCOURIER_FREETEXT2, #RC_FANCOURIER_FREETEXT3, #RC_FANCOURIER_FREETEXT4').closest('.form-group').addClass('alert alert-info rc_fancourier_heading');
        $(function () {
            if (typeof $.fn.sortable !== 'undefined') {
                $("#rc_fancourier_locations_table tbody").sortable({
                    placeholder: "rc_fancourier_locations_placeholder",
                    items: "tr:not(.rc_fancourier_listing_add)",
                    handle: ".locations-drag",
                    forcePlaceholderSize: true,
                });
            }
        });
        $('#RC_FANCOURIER_PASSWORD').prop('type', 'password');
        if (typeof $.fn.select2 !== 'undefined') {
            var $dropoff = $('#RC_FANCOURIER_DEFAULT_DROPOFF_ID');
            if ($dropoff.length) {
                if ($dropoff.hasClass('select2-hidden-accessible')) {
                    $dropoff.select2('destroy');
                }
                $dropoff.select2({
                    width: '100%',
                    minimumResultsForSearch: 0,
                    allowClear: true,
                    placeholder: $dropoff.find('option[value=""]').first().text() || '—'
                });
            }
        }
    });
    $(document).on('click', '.rc_fancourier_listing_add_button', function (e) {
        e.preventDefault();
        var delete_label = $(this).data('delete_label');
        var html = '';
        html += '<tr>' +
            '                        <td>\n' +
            '<span class="icon-move locations-drag"></span>\n' +
            '                        </td>\n' +
            '<td>\n' +
            '                            <input type="text" name="rc_fancourier_locations[client_id][]" value=""/>\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '                            <input type="text" name="rc_fancourier_locations[label][]" value=""/>\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '<a href="#" class="btn btn-danger rc_fancourier_location_delete_button">' + delete_label + '</a>\n' +
            '                        </td><td></td></tr>';
        $('.rc_fancourier_listing_add').before(html);
        if (typeof $.fn.sortable !== 'undefined') {
            $("#rc_fancourier_locations_table tbody").sortable('refresh');
        }
    });
    $(document).on('click', '.rc_fancourier_listing_retrieve_button', function(e) {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: window.location.href,
            data: {
                action: 'retrieveLocations',
                RC_FANCOURIER_USERNAME: $('[name="RC_FANCOURIER_USERNAME"]').val(),
                RC_FANCOURIER_PASSWORD: $('[name="RC_FANCOURIER_PASSWORD"]').val(),
            },
            success: function (data) {
                if (typeof data.error !== 'undefined') {
                    showErrorMessage(data.error);
                }
                if (typeof data.html !== undefined) {
                    $('.panel-rc_fancourier_locations').replaceWith(data.html);
                    $("#rc_fancourier_locations_table tbody").sortable({
                        placeholder: "rc_fancourier_locations_placeholder",
                        items: "tr:not(.rc_fancourier_listing_add)",
                        handle: ".locations-drag",
                        forcePlaceholderSize: true,
                    });
                }
            },
            error: function () {
                showErrorMessage('ERROR!');
            },
            dataType: 'json',
        });
    });
    $(document).on('click', '.rc_fancourier_service_add_button', function (e) {
        e.preventDefault();
        var delete_label = $(this).data('delete_label');
        var fancourier_services_options_html = '';
        for (var i in rc_fancourier_services) {
            fancourier_services_options_html += '<option value="' + rc_fancourier_services[i] + '">' + rc_fancourier_services[i] + '</option>\n';
        }
        var html = '';
        html += '<tr>\n' +
            '                        <td>\n' +
            '                            <input type="hidden" name="rc_fancourier_services[id_reference][]" value="0" />\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '                            <input type="text" name="rc_fancourier_services[name][]" value="Fan Courier"/>\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '                            <select name="rc_fancourier_services[service][]">\n' +
            fancourier_services_options_html +
            '                            </select>\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '                            <input type="text" name="rc_fancourier_services[free_shipping_from][]" value="" placeholder="RON" style="width:80px;"/>\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '                            <input type="hidden" name="rc_fancourier_services[cod][]" value="0" />\n' +
            '                            <a class="carrier_toggle" data-id_reference="0" href="#">\n' +
            '                                 <i class="icon-remove list-action-enable action-disabled"></i>\n' +
            '                            </a>\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '                            <input type="hidden" name="rc_fancourier_services[active][]" value="1" />\n' +
            '                            <a class="carrier_toggle" data-id_reference="0" href="#">\n' +
            '                                 <i class="icon-check list-action-enable action-enabled"></i>\n' +
            '                            </a>\n' +
            '                        </td>\n' +
            '                        <td>\n' +
            '                            <a class="carrier_delete btn btn-danger" data-id_reference="0" href="#">\n' +
            delete_label +
            '                            </a>\n' +
            '                        </td>\n' +
            '                    </tr>';
        $('.rc_fancourier_service_add').before(html);
        if (typeof $.fn.sortable !== 'undefined') {
            $("#rc_fancourier_services_table tbody").sortable('refresh');
        }
    });

    $(document).on('click', '.carrier_cod_toggle', function (e) {
        e.preventDefault();
        var current_val = $(this).closest('td').find('input[type="hidden"]').val();
        if (current_val == "0") {
            $(this).closest('td').find('input[type="hidden"]').val(1);
            if ($(this).find('i').length) {
                $(this).find('i').removeClass('icon-remove action-disabled list-action-enable').addClass('icon-check list-action-enable action-enabled');
            } else {
                $(this).find('img').attr('src', $(this).find('img').attr('src').replace('disabled', 'enabled'));
            }
        } else {
            $(this).closest('td').find('input[type="hidden"]').val(0);
            if ($(this).find('i').length) {
                $(this).find('i').removeClass('icon-check action-enabled list-action-enable').addClass('icon-remove list-action-enable action-disabled');
            } else {
                $(this).find('img').attr('src', $(this).find('img').attr('src').replace('enabled', 'disabled'));
            }
        }
    });

    $(document).on('click', '.carrier_toggle', function (e) {
        e.preventDefault();
        var current_val = $(this).closest('td').find('input[type="hidden"]').val();
        if (current_val == "0") {
            $(this).closest('td').find('input[type="hidden"]').val(1);
            if ($(this).find('i').length) {
                $(this).find('i').removeClass('icon-remove action-disabled list-action-enable').addClass('icon-check list-action-enable action-enabled');
            } else {
                $(this).find('img').attr('src', $(this).find('img').attr('src').replace('disabled', 'enabled'));
            }
        } else {
            $(this).closest('td').find('input[type="hidden"]').val(0);
            if ($(this).find('i').length) {
                $(this).find('i').removeClass('icon-check action-enabled list-action-enable').addClass('icon-remove list-action-enable action-disabled');
            } else {
                $(this).find('img').attr('src', $(this).find('img').attr('src').replace('enabled', 'disabled'));
            }
        }
    });
    $(document).on('click', '.carrier_delete', function (e) {
        e.preventDefault();
        var id_reference = $(this).data('id_reference');
        if (id_reference) {
            $('.rc_fancourier_service_add_button').before('<input type="hidden" name="rc_fancourier_services_deleted_' + id_reference + '" value="1" />');
        }
        $(this).closest('tr').hide();
    });
    $(document).on('click', '.rc_fancourier_location_delete_button', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });
    $(document).on('submit', '#module_form, #rc_fancourier_locations_form, #rc_fancourier_services_form, #module_form_1', function (e) {
        e.preventDefault();
        if (window.rc_fancourier_forms_saving) {
            return;
        }
        window.rc_fancourier_forms_saving = true;
        var saved = 0;
        $('#module_form, #rc_fancourier_locations_form, #rc_fancourier_services_form, #module_form_1').each(function () {
            var post_data = $(this).serialize();
            var submit_btn_name = $(this).find('[type="submit"]').attr('name');
            post_data += '&' + submit_btn_name + '=1';
            $.ajax({
                type: "POST",
                url: window.location.href,
                data: post_data,
                success: function (data) {
                    if (data && data.success) {
                        showSuccessMessage(data.message);
                        if (data.refresh) {
                            window.location.reload();
                        }
                    } else {
                        if (typeof data.message !== 'undefined') {
                            showErrorMessage(data.message);
                        } else {
                            showErrorMessage('ERROR! #1')
                        }
                    }
                    saved++;
                },
                error: function () {
                    showErrorMessage('ERROR! #2');
                    saved++;
                },
                dataType: 'json',
            });
        });
        var checkSaved = setInterval(function () {
            if (saved >= $('#module_form, #rc_fancourier_locations_form, #rc_fancourier_services_form, #module_form_1').length) {
                window.rc_fancourier_forms_saving = false;
                clearInterval(checkSaved);
            }
        }, 100);
    });

    $(document).on('click', '.rc_fancourier_updatelocations', function(e) {
        e.preventDefault();
        var post_data = 'action=updateLocations';
        $('.rc_fancourier_updatelocations').prepend('<i class="fa fa-spinner fa-spin fa-fw icon icon-spinner icon-spin icon-fw" style="margin-right: 3px;"></i>').prop('disabled', true);
        $.ajax({
            type: "POST",
            url: window.location.href,
            data: post_data,
            success: function (data) {
                $('.rc_fancourier_updatelocations').prop('disabled', false).find('.fa-spinner').remove();
                $('.rc_fancourier_updatelocations').parent().find('span').remove();
                if (data && data.success) {
                    showSuccessMessage(data.message);
                    $('.rc_fancourier_updatelocations').after('<span class="fa fa-check icon icon-check"></span>');
                } else {
                    $('.rc_fancourier_updatelocations').after('<span class="fa fa-times icon icon-times"></span>');
                    if (typeof data.message !== 'undefined') {
                        showErrorMessage(data.message);
                    } else {
                        showErrorMessage('ERROR! #1')
                    }
                }
            },
            error: function () {
                $('.rc_fancourier_updatelocations').prop('disabled', false).find('.fa-spinner').remove();
                $('.rc_fancourier_updatelocations').parent().find('span').remove();
                $('.rc_fancourier_updatelocations').after('<span class="fa fa-times icon icon-times"></span>');
                showErrorMessage('ERROR! #2');
            },
            dataType: 'json',
        });
    });
});