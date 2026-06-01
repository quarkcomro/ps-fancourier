{*
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
*}

{if $awbsChanged}
    <style type="text/css">
        .rc_fancourier_table {
            border: solid 1px #DDEEEE;
            border-collapse: collapse;
            border-spacing: 0;
            font: normal 13px Arial, sans-serif;
            width: 100%;
        }

        .rc_fancourier_table thead th {
            background-color: #DDEFEF;
            border: solid 1px #DDEEEE;
            color: #336B6B;
            padding: 10px;
            text-align: left;
            text-shadow: 1px 1px 1px #fff;
        }

        .rc_fancourier_table tbody td {
            border: solid 1px #DDEEEE;
            color: #333;
            padding: 10px;
            text-shadow: 1px 1px 1px #fff;
        }

        .rc_fancourier_state {
            display: inline-block;
            text-shadow: none;
            padding: 3px 5px;
            border-radius: 3px;
        }
    </style>
    <table class="rc_fancourier_table">
        <thead>
        <tr>
            <th>{l s='Order date' mod='rc_fancourier'}</th>
            <th>{l s='Order ID' mod='rc_fancourier'}</th>
            <th>{l s='Order reference' mod='rc_fancourier'}</th>
            <th>{l s='Customer' mod='rc_fancourier'}</th>
            <th>{l s='AWB' mod='rc_fancourier'}</th>
            <th>{l s='Current Fan Courier State' mod='rc_fancourier'}</th>
            <th>{l s='Previous state' mod='rc_fancourier'}</th>
            <th>{l s='Next state' mod='rc_fancourier'}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$awbsChanged item=awb}
            <tr>
                <td>{$awb.order_date|escape:'htmlall':'UTF-8'}</td>
                <td>{$awb.id_order|escape:'htmlall':'UTF-8'}</td>
                <td>{$awb.reference|escape:'htmlall':'UTF-8'}</td>
                <td>{$awb.customer_name|escape:'htmlall':'UTF-8'}</td>
                <td>{$awb.awb|escape:'htmlall':'UTF-8'}</td>
                <td>{$awb.status|escape:'htmlall':'UTF-8'}</td>
                <td><span style="background-color: {$awb.current_state_color|escape:'htmlall':'UTF-8'}; color: {$awb.current_state_text_color|escape:'htmlall':'UTF-8'};"
                          class="rc_fancourier_state">{$awb.current_state|escape:'htmlall':'UTF-8'}</span></td>
                <td>
                    {if $awb.next_state}
                        <span style="background-color: {$awb.next_state_color|escape:'htmlall':'UTF-8'}; color: {$awb.next_state_text_color|escape:'htmlall':'UTF-8'};"
                              class="rc_fancourier_state">{$awb.next_state|escape:'htmlall':'UTF-8'}</span>
                    {/if}
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
{else}
    <h1>{l s='No orders to be changed.' mod='rc_fancourier'}</h1>
{/if}