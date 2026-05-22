/**
 * 2018-2024 GURU CODERS SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    GURU CODERS SRL <stickyrst@gmail.com>
 * @copyright 2018-2024 GURU CODERS SRL
 * @license   Commercial
 */
$(document).ready(function() {
   console.log($('#rc_fancourier_options_D').val());
    if ($('#rc_fancourier_options_D').val() == 'D') {
        $('.rc_fan_show_nonoffice').hide();
        $('.rc_fan_show_office').show();
        $('select[name="rc_fancourier_office_address"]').select2({width: '100%'});
    }

    checkRcFanCourierOfficeAddress();
});
$(function () {
    $(document).on('submit', '#rc_fancourier_form', function (e) {
        e.preventDefault();
        var post_data = $(this).serialize();
        $.ajax({
            type: "POST",
            url: $(this).attr('action'),
            data: post_data,
            success: function (data) {
                if (data.success) {
                    showSuccessMessage(data.message);
                    addAwb(data.awb);
                } else {
                    showErrorMessage(data.message);
                }
            },
            error: function (data) {
                showErrorMessage('ERROR!');
            },
            dataType: 'json',
        });
    });
    $(document).on('click', '.rc_fancourier_deleteawb', function (e) {
        e.preventDefault();
        var $this = $(this);
        var awb = $(this).data('awb');
        var post_data = {
            awb: awb,
            rc_fancourier_token: $('input[name="rc_fancourier_token"]').val(),
            action: 'deleteAwb',
        };
        $.ajax({
            type: "POST",
            url: $('#rc_fancourier_form').attr('action'),
            data: post_data,
            success: function (data) {
                if (data.success) {
                    showSuccessMessage(data.message);
                    $this.closest('.row').remove();
                } else {
                    showErrorMessage(data.message);
                }
            },
            error: function (data) {
                showErrorMessage('ERROR!');
            },
            dataType: 'json',
        });
    });
    $(document).on('click', '.rc_fancourier_showawb', function (e) {
        e.preventDefault();
        var awb = $(this).data('awb');
        var post_data = 'awb=' + awb + '&rc_fancourier_token=' + $('input[name="rc_fancourier_token"]').val() + '&action=showAwb';
        if ($('#rc_fancourier_form').attr('action').includes('?')) {
            var params_separator = '&';
        } else {
            var params_separator = '?';
        }
        window.open($('#rc_fancourier_form').attr('action') + params_separator + post_data, '_blank', 'width=300');
    });
    $(document).on('click', '.rc_fancourier_statusawb', function (e) {
        e.preventDefault();
        var $this = $(this);
        var awb = $(this).data('awb');
        var post_data = {
            awb: awb,
            rc_fancourier_token: $('input[name="rc_fancourier_token"]').val(),
            action: 'statusAwb',
        };
        $.ajax({
            type: "POST",
            url: $('#rc_fancourier_form').attr('action'),
            data: post_data,
            success: function (data) {
                if (data.success) {
                    $.fancybox({
                        content: '<pre>' + data.message + '</pre>',
                        helpers: {
                            overlay: {
                                locked: false
                            }
                        }
                    });
                } else {
                    showErrorMessage(data.message);
                }
            },
            error: function (data) {
                showErrorMessage('ERROR!');
            },
            dataType: 'json',
        });
    });

    $(document).on('change', '#rc_fancourier_options_D', function(e) {
        var val = $(this).val();
        if (val == 'D') {
            $('.rc_fan_show_nonoffice').hide();
            $('.rc_fan_show_office').show();
            $('select[name="rc_fancourier_office_address"]').select2({width: '100%'});
        } else {
            $('.rc_fan_show_nonoffice').show();
            $('.rc_fan_show_office').hide();
        }

        checkRcFanCourierOfficeAddress();
    });

    $(document).on('change', 'select[name="rc_fancourier_office_address"]', checkRcFanCourierOfficeAddress);

    $(document).on('click', '.rc_fancourier_refresh_order_infos', function(e) {
        e.preventDefault();
        if ($('#rc_fancourier_options_D').val() == 'D') {
            var state = $('input[name="rc_fancourier_office_state"]').val();
            var city = $('input[name="rc_fancourier_office_city"]').val();
        } else {
            var state = $('input[name="rc_fancourier_state"]').val();
            var city = $('input[name="rc_fancourier_city"]').val();
        }
        var post_data = 'action=refreshOrderInfo&state='+state+'&city='+city+'&rc_fancourier_token='+$('input[name="rc_fancourier_token"]').val();
        $('.rc_fancourier_refresh_order_infos').prepend('<i class="fa fa-spinner fa-spin fa-fw icon icon-spinner icon-spin icon-fw" style="margin-right: 3px;"></i>').prop('disabled', true);
        $.ajax({
            type: "POST",
            url: $('#rc_fancourier_form').attr('action'),
            data: post_data,
            success: function (data) {
                $('.rc_fancourier_refresh_order_infos').prop('disabled', false).find('.fa-spinner').remove();
                if (data && data.success) {
                    showSuccessMessage(data.message);
                    $('.rc_fancourier_extra_km').html(data.extra_km);
                } else {
                    if (typeof data.message !== 'undefined') {
                        showErrorMessage(data.message);
                    } else {
                        showErrorMessage('ERROR! #1')
                    }
                }
            },
            error: function () {
                $('.rc_fancourier_refresh_order_infos').prop('disabled', false).find('.fa-spinner').remove();
                showErrorMessage('ERROR! #2');
            },
            dataType: 'json',
        });
    });

    $(document).ready(checkRcFanCourierPackingPanel);
    $(document).on('change', '#rc_fancourier_options_A', checkRcFanCourierPackingPanel);

    $(document).on('click', '.rc_fancourier_packing_minus', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        checkRcFancourierPackingListTable();
    });

    $(document).on('click', '.rc_fancourier_packing_plus', function(e) {
        e.preventDefault();
        rcFanCourierAddPackingLine($(this).closest('tr'));
    });

    $(document).on('click', '.rc_fancourier_manual_awb_add', function(e) {
        e.preventDefault();
        var awb = $('#manual_add_awb').val();
        var awb_location = $('#rc_fancourier_location_manual').val();
        var id_order = $('#rc_fancourier_form [name="id_order"]').val();
        var post_data = 'action=addAwbManual&id_order='+id_order+'&awb='+awb+'&awb_location='+awb_location+'&rc_fancourier_token='+$('input[name="rc_fancourier_token"]').val();
        $.ajax({
            type: "POST",
            url: $('#rc_fancourier_form').attr('action'),
            data: post_data,
            success: function (data) {
                if (data.success) {
                    showSuccessMessage(data.message);
                    addAwb(data.awb);
                } else {
                    showErrorMessage(data.message);
                }
            },
            error: function () {
                showErrorMessage('ERROR! #1');
            },
            dataType: 'json',
        });
    });
});

function addAwb(awb) {
    var delete_label = $('.rc_fancourier_awbs').data('delete_label');
    var show_label = $('.rc_fancourier_awbs').data('show_label');
    var status_label = $('.rc_fancourier_awbs').data('status_label');
    var html = '<div class="row"><div class="col-xs-12 col-12"><input type="text" readonly="readonly" value="' + awb + '" class="rc_fancourier_awb_input form-control" /> <a href="#" class="btn btn-danger rc_fancourier_deleteawb" data-awb="' + awb + '">' + delete_label + '</a> <a href="#" class="btn btn-warning rc_fancourier_showawb" data-awb="' + awb + '">' + show_label + '</a> <a href="#" class="btn btn-info rc_fancourier_statusawb" data-awb="' + awb + '">' + status_label + '</a></div></div>';
    $('.rc_fancourier_awbs').slideDown().append(html);
}

function checkRcFanCourierOfficeAddress() {
    var option_selected = $('select[name="rc_fancourier_office_address"]').find('option:selected');
    $('input[name="rc_fancourier_office_city"]').val(option_selected.data('city'));
    $('input[name="rc_fancourier_office_state"]').val(option_selected.data('state'));
    $('input[name="rc_fancourier_office_postcode"]').val(option_selected.data('postcode'));
}

function checkRcFanCourierPackingPanel()
{
    if ($('#rc_fancourier_options_A').is(':checked')) {
        $('.rc_fancourier_packing_panel').slideDown();
    } else {
        $('.rc_fancourier_packing_panel').slideUp();
    }
}

var rc_fancourier_packing_inputs = [
    'name',
    'description',
    'code',
    'quantity',
    'decl_value',
];
function checkRcFancourierPackingListTable()
{
    if (!$('.rc_fancourier_packing_table tbody tr').length) {
        rcFanCourierAddPackingLine();
    }
}

function rcFanCourierAddPackingLine(el)
{
    var html = '<tr>';
    for (var i in rc_fancourier_packing_inputs) {
        html += '<td><input type="text" name="rc_fancourier_packing['+rc_fancourier_packing_inputs[i]+'][]" value="" /></td>';
    }
    html += '<td align="center"><a href="#" class="rc_fancourier_packing_minus">' + ((_PS_VERSION_.substr(0, 3) == '1.5') ? '<strong>-</strong>' : '<span class="fa fa-minus icon icon-minus"></span>') + '</a> <a href="#" class="rc_fancourier_packing_plus">' + ((_PS_VERSION_.substr(0, 3) == '1.5') ? '<strong>+</strong>' : '<span class="fa fa-plus icon icon-plus"></span>') + '</a></td>';
    html += '</tr>';
    if (el) {
        el.after(html);
    } else {
        $('.rc_fancourier_packing_table tbody').append(html);
    }
}