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
    if ($('#rc_fancourier_options_D').val() === 'D') {
        $('.rc_fan_show_nonoffice').hide();
        $('.rc_fan_show_office').show();
        $('select[name="rc_fancourier_office_address"]').select2({width: '100%'});
    }

    if ($('#rc_fancourier_options_W').is(':checked')) {
        $('#rc_fan_dropoff_section').show();
        $('select[name="rc_fancourier_dropoff_location"]').select2({width: '100%'});
    }

    checkRcFanCourierOfficeAddress();
});
$(function () {
    $(document).on('submit', '#rc_fancourier_form', function (e) {
        e.preventDefault();
        if (!$('input[name="rc_fancourier_phone"]').val().trim()) {
            showErrorMessage('Phone number is required.');
            return;
        }
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
        if ($(this).val() === 'D') {
            $('.rc_fan_show_nonoffice').hide();
            $('.rc_fan_show_office').show();
            $('select[name="rc_fancourier_office_address"]').select2({width: '100%'});
        } else {
            $('.rc_fan_show_nonoffice').show();
            $('.rc_fan_show_office').hide();
        }

        checkRcFanCourierOfficeAddress();
    });

    $(document).on('change', '#rc_fancourier_options_W', function(e) {
        if ($(this).is(':checked')) {
            $('#rc_fan_dropoff_section').show();
            $('select[name="rc_fancourier_dropoff_location"]').select2({width: '100%'});
        } else {
            $('#rc_fan_dropoff_section').hide();
        }
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

    $(document).on('click', '#rc_fancourier_calculate_cost', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $('#rc_fancourier_form');
        var $result = $('#rc_fancourier_cost_result');
        var post_data = $form.serialize();
        post_data = post_data.replace(/action=generateAwb/, 'action=calculateShippingCost');
        $result.hide().removeClass('alert alert-success alert-danger alert-info alert-warning').empty();
        $btn.prop('disabled', true).prepend('<i class="fa fa-spinner fa-spin fa-fw icon icon-spinner icon-spin icon-fw" style="margin-right: 3px;"></i>');
        $.ajax({
            type: 'POST',
            url: $form.attr('action'),
            data: post_data,
            dataType: 'json',
            success: function (data) {
                $btn.prop('disabled', false).find('.fa-spinner').remove();
                if (data.success) {
                    var discrepancy = parseFloat(data.discrepancy);
                    var headerType = discrepancy > 0 ? 'warning' : (discrepancy < 0 ? 'danger' : 'success');
                    var discClass  = discrepancy > 0 ? 'rc-fan-disc-pos' : (discrepancy < 0 ? 'rc-fan-disc-neg' : 'rc-fan-disc-zero');
                    var headerIcons = {
                        'success': 'fa-check-circle',
                        'warning': 'fa-exclamation-triangle',
                        'danger' : 'fa-times-circle'
                    };
                    var requestJson  = data.api_request_data  ? JSON.stringify(data.api_request_data,  null, 2) : 'N/A';
                    var responseJson = data.api_response_data ? JSON.stringify(data.api_response_data, null, 2) : 'N/A';

                    // Breakdown section from API response
                    var bd = (data.api_response_data && data.api_response_data.data) ? data.api_response_data.data : null;
                    var breakdownHtml = '';
                    if (bd) {
                        var fmtRON = function(v) { return parseFloat(v || 0).toFixed(2) + ' RON'; };
                        var bdItems = [
                            { key: 'weightCost',    label: 'Cost greutate' },
                            { key: 'extraKmCost',   label: 'Cost km suplimentari' },
                            { key: 'fuelCost',      label: 'Combustibil' },
                            { key: 'insuranceCost', label: 'Asigurare' },
                            { key: 'optionsCost',   label: 'Op\u021biuni' },
                        ];
                        var bdRows = '';
                        for (var i = 0; i < bdItems.length; i++) {
                            var bVal = parseFloat(bd[bdItems[i].key] || 0);
                            var zc = bVal === 0 ? ' rc-fan-zero' : '';
                            bdRows += '<tr><td class="rc-fan-label' + zc + '">' + bdItems[i].label + '</td><td class="rc-fan-value' + zc + '">' + fmtRON(bVal) + '</td></tr>';
                        }
                        breakdownHtml =
                            '<div class="rc-fan-sep-label">Detalii tarif API</div>' +
                            '<table class="rc-fan-cost-table">' +
                                bdRows +
                                '<tr class="rc-fan-subtotal-row"><td class="rc-fan-label">Subtotal (f\u0103r\u0103 TVA)</td><td class="rc-fan-value">' + fmtRON(bd.costNoVAT) + '</td></tr>' +
                                '<tr><td class="rc-fan-label">TVA</td><td class="rc-fan-value">' + fmtRON(bd.vat) + '</td></tr>' +
                                '<tr class="rc-fan-api-total-row"><td class="rc-fan-label"><strong>Total</strong></td><td class="rc-fan-value"><strong>' + fmtRON(bd.total) + '</strong></td></tr>' +
                            '</table>';
                    }

                    // Comparison section — skip currency conversion row when order is already in RON
                    var conversionRow = data.order_currency_iso !== 'RON'
                        ? '<tr><td class="rc-fan-label">Cost API (' + rcFanEscHtml(data.order_currency_iso) + ')</td><td class="rc-fan-value">' + rcFanEscHtml(data.api_cost_order_currency_formatted) + '</td></tr>'
                        : '';
                    var comparisonHtml =
                        '<div class="rc-fan-sep-label">Compara\u021bie cu comanda</div>' +
                        '<table class="rc-fan-cost-table">' +
                            '<tr><td class="rc-fan-label">Cost API (RON)</td><td class="rc-fan-value">' + rcFanEscHtml(data.api_cost_ron_formatted) + '</td></tr>' +
                            conversionRow +
                            '<tr><td class="rc-fan-label">Cost pe comand\u0103</td><td class="rc-fan-value">' + rcFanEscHtml(data.order_shipping_formatted) + '</td></tr>' +
                            '<tr class="rc-fan-disc-row"><td class="rc-fan-label"><strong>Discrepan\u021b\u0103 (comand\u0103 \u2212 API)</strong></td><td class="rc-fan-value ' + discClass + '"><strong>' + rcFanEscHtml(data.discrepancy_formatted) + '</strong></td></tr>' +
                        '</table>';

                    var html =
                        '<div class="rc-fan-cost-card rc-fan-type-' + headerType + '">' +
                            '<div class="rc-fan-cost-card-header">' +
                                '<i class="fa ' + headerIcons[headerType] + '"></i> Calcul cost transport' +
                            '</div>' +
                            breakdownHtml +
                            comparisonHtml +
                            '<div class="rc-fan-json-section">' +
                                '<div class="rc-fan-json-toggle">' +
                                    '<span class="rc-fan-toggle-icon">&#9658;</span> Date trimise la API' +
                                '</div>' +
                                '<pre class="rc-fan-json-pre" style="display:none">' + rcFanEscHtml(requestJson) + '</pre>' +
                                '<div class="rc-fan-json-toggle">' +
                                    '<span class="rc-fan-toggle-icon">&#9658;</span> R\u0103spuns API Fan Courier' +
                                '</div>' +
                                '<pre class="rc-fan-json-pre" style="display:none">' + rcFanEscHtml(responseJson) + '</pre>' +
                            '</div>' +
                        '</div>';
                    $result.html(html).show();
                } else {
                    $result.addClass('alert alert-danger').text(data.error || 'ERROR!').show();
                }
            },
            error: function () {
                $btn.prop('disabled', false).find('.fa-spinner').remove();
                $result.addClass('alert alert-danger').text('ERROR!').show();
            },
        });
    });

    $(document).on('click', '#rc_fancourier_cost_result .rc-fan-json-toggle', function() {
        var $pre  = $(this).next('pre.rc-fan-json-pre');
        var $icon = $(this).find('.rc-fan-toggle-icon');
        if ($pre.is(':visible')) {
            $pre.slideUp(200);
            $icon.html('&#9658;');
        } else {
            $pre.slideDown(200);
            $icon.html('&#9660;');
        }
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

function rcFanEscHtml(str) {
    if (typeof str !== 'string') { str = String(str); }
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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
/**
 * Return AWB Button: pre-fills the AWB form with the current delivery address
 * as the recipient, allowing the user to set their depot as sender.
 * This is a UI helper — no data is changed on save until the user submits.
 */
$(document).on('click', '#rc_fancourier_return_awb_btn', function (e) {
    e.preventDefault();

    var $form = $('#rc_fancourier_form');
    // Read current delivery values from the form
    var currentName     = $form.find('[name="rc_fancourier_customer_name"]').val()   || $form.find('[name="rc_fancourier_customer_contact"]').val() || '';
    var currentPhone    = $form.find('[name="rc_fancourier_phone"]').val()            || '';
    var currentAddress  = $form.find('[name="rc_fancourier_address"]').val()          || '';
    var currentCity     = $form.find('[name="rc_fancourier_city"]').val()             || '';
    var currentState    = $form.find('[name="rc_fancourier_state"]').val()            || '';
    var currentPostcode = $form.find('[name="rc_fancourier_postcode"]').val()         || '';

    // Show confirmation with current values
    var confirmMsg = (typeof rc_fancourier_return_awb_confirm !== 'undefined')
        ? rc_fancourier_return_awb_confirm
        : 'Pre-fill Return AWB?\n\nRecipient (customer address):\n' +
          currentName + '\n' + currentAddress + ', ' + currentCity + ', ' + currentState + ' ' + currentPostcode +
          '\n\nClick OK to keep these values in the form.\nYou can edit them before submitting.';

    if (!window.confirm(confirmMsg)) {
        return;
    }

    // Highlight changed fields so the operator knows what was pre-filled
    $form.find('[name="rc_fancourier_address"], [name="rc_fancourier_city"], [name="rc_fancourier_state"], [name="rc_fancourier_postcode"], [name="rc_fancourier_phone"]')
         .addClass('rc_fancourier_return_awb_prefilled')
         .css({ 'background-color': '#fff3cd', 'border-color': '#ffc107' });

    // Add a visible notice
    if (!$('#rc_fancourier_return_awb_notice').length) {
        $form.prepend(
            '<div id="rc_fancourier_return_awb_notice" class="alert alert-warning" style="margin-bottom:10px;">' +
            '<strong><i class="icon-refresh"></i> ' +
            (typeof rc_fancourier_return_awb_label !== 'undefined' ? rc_fancourier_return_awb_label : 'Return AWB Mode') +
            '</strong>: ' +
            (typeof rc_fancourier_return_awb_notice !== 'undefined' ? rc_fancourier_return_awb_notice : 'Form pre-filled for return shipment. Verify the addresses before generating the AWB.') +
            '</div>'
        );
    }
});

/* ---- Per-package weight inputs (FAN compound AWBs) ---- */
$(document).ready(function () {
    function rcfcRefreshPanels(scope) {
        var $scope = scope ? $(scope) : $('body');
        $scope.find('.rc_fancourier_per_package_panel').each(function () {
            var $panel = $(this);
            var $form = $panel.closest('form');
            if (!$form.length) {
                $form = $panel.closest('.rc_fancourier_block, .panel, .card, body');
            }
            var $env = $form.find('[name="rc_fancourier_envelopes"]');
            var $box = $form.find('[name="rc_fancourier_boxes"]');
            var $totalW = $form.find('[name="rc_fancourier_weight"]');
            var envCount = parseInt($env.val(), 10) || 0;
            var boxCount = parseInt($box.val(), 10) || 0;
            var total = envCount + boxCount;
            var $inputs = $panel.find('.rc_fancourier_per_package_inputs');
            var $hint = $form.find('.rc_fancourier_weight_hint');

            if (total <= 1) {
                $panel.hide();
                $hint.hide();
                $inputs.empty();
                if ($totalW.length) {
                    $totalW.prop('readonly', false);
                }
                return;
            }

            $panel.show();
            $hint.show();
            if ($totalW.length) {
                $totalW.prop('readonly', true);
            }

            var existing = [];
            $inputs.find('input.rc_fancourier_pkg_weight').each(function () {
                existing.push($(this).val());
            });

            $inputs.empty();
            for (var i = 0; i < total; i++) {
                var label = i < envCount ? ($panel.data('envelope_label') || 'Envelope') : ($panel.data('box_label') || 'Box');
                var idx = i + 1;
                var prev = existing[i] || '';
                var $row = $('<div class="form-inline" style="display:inline-block;margin:0 10px 6px 0;"></div>');
                $row.append('<label style="margin-right:5px">' + label + ' ' + idx + '/' + total + ':</label>');
                $row.append('<input type="number" step="0.001" min="0" class="form-control rc_fancourier_pkg_weight" name="rc_fancourier_weights[]" value="' + prev + '" style="width:90px;display:inline-block" placeholder="kg" />');
                $inputs.append($row);
            }
        });
    }

    function rcfcSyncTotalFromPerPackage($panel) {
        var $form = $panel.closest('form');
        if (!$form.length) {
            $form = $panel.closest('.rc_fancourier_block, .panel, .card, body');
        }
        var sum = 0;
        var anyFilled = false;
        $panel.find('input.rc_fancourier_pkg_weight').each(function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v) && v > 0) {
                anyFilled = true;
                sum += v;
            }
        });
        if (anyFilled) {
            $form.find('[name="rc_fancourier_weight"]').val(sum.toFixed(3));
        }
    }

    rcfcRefreshPanels();

    $(document).on('input change', '[name="rc_fancourier_envelopes"], [name="rc_fancourier_boxes"]', function () {
        rcfcRefreshPanels(this.form || document);
    });

    $(document).on('input change', 'input.rc_fancourier_pkg_weight', function () {
        var $panel = $(this).closest('.rc_fancourier_per_package_panel');
        rcfcSyncTotalFromPerPackage($panel);
    });

    $(document).on('click', '.rc_fancourier_distribute_btn', function (e) {
        e.preventDefault();
        var $panel = $(this).closest('.rc_fancourier_per_package_panel');
        var $form = $panel.closest('form');
        if (!$form.length) {
            $form = $panel.closest('.rc_fancourier_block, .panel, .card, body');
        }
        var total = parseFloat($form.find('[name="rc_fancourier_weight"]').val()) || 0;
        var $inputs = $panel.find('input.rc_fancourier_pkg_weight');
        var n = $inputs.length;
        if (n <= 0) return;
        var per = total > 0 ? (total / n) : 0;
        $inputs.each(function () {
            $(this).val(per > 0 ? per.toFixed(3) : '');
        });
        rcfcSyncTotalFromPerPackage($panel);
    });
});
