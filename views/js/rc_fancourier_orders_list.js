/**
 * 2018-2026 GURU CODERS SRL
 *
 * Fan Courier - Orders List: Generate AWB button
 * Adds a "Generate AWB" link next to orders that use a Fan Courier carrier.
 */
(function ($) {
    'use strict';

    if (typeof rc_fancourier_carrier_names === 'undefined' || !rc_fancourier_carrier_names.length) {
        return;
    }

    $(document).ready(function () {
        // Find rows in the order list where the carrier column matches a Fan Courier carrier
        $('table.order tbody tr, #order-list tbody tr').each(function () {
            var $row = $(this);
            // Try to find carrier cell (PS 8/9 uses data attributes or specific columns)
            var carrierText = $row.find('td.carrier, td[data-carrier]').text().trim();
            if (!carrierText) {
                // Fallback: look for any td containing the carrier name
                $row.find('td').each(function () {
                    var text = $(this).text().trim();
                    $.each(rc_fancourier_carrier_names, function (i, name) {
                        if (text === name) {
                            carrierText = text;
                            return false;
                        }
                    });
                    if (carrierText) {
                        return false;
                    }
                });
            }

            var isFanCourier = false;
            $.each(rc_fancourier_carrier_names, function (i, name) {
                if (carrierText === name) {
                    isFanCourier = true;
                    return false;
                }
            });

            if (isFanCourier) {
                // Get order ID from the row link or data attribute
                var idOrder = $row.data('id') || $row.find('a[href*="id_order"]').first().attr('href');
                if (idOrder && typeof idOrder === 'string') {
                    var match = idOrder.match(/id_order=(\d+)/);
                    if (match) {
                        idOrder = match[1];
                    }
                }

                if (idOrder) {
                    var awbUrl = rc_fancourier_order_url + '&id_order=' + idOrder + '&vieworder';
                    var $btn = $('<a>', {
                        href: awbUrl,
                        class: 'btn btn-xs btn-default rc-fancourier-list-awb-btn',
                        title: rc_fancourier_bulk_awb_label,
                        html: '<i class="icon-truck"></i> ' + rc_fancourier_bulk_awb_label
                    });

                    var $actionCell = $row.find('td.actions, td:last-child');
                    if ($actionCell.length) {
                        $actionCell.append($btn);
                    }
                }
            }
        });
    });
}(jQuery));
