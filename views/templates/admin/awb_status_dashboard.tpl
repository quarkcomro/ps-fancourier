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

<div class="panel card" id="rc_fancourier_awb_status_dashboard">
    <div class="panel-heading card-header">
        <i class="icon-truck"></i>
        {l s='AWB Status Dashboard' mod='rc_fancourier'}
        <span class="badge badge-info panel-badge" style="float:right; font-size: 0.9em; background: #6c757d; color: #fff; padding: 3px 8px; border-radius: 3px;">
            {l s='Last 50 AWBs' mod='rc_fancourier'}
        </span>
    </div>
    <div class="panel-body card-body">
        {if $rc_awb_status_list}
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" style="font-size: 0.9em;">
                    <thead>
                        <tr>
                            <th>{l s='AWB Number' mod='rc_fancourier'}</th>
                            <th>{l s='Order' mod='rc_fancourier'}</th>
                            <th>{l s='Customer' mod='rc_fancourier'}</th>
                            <th>{l s='Order Status' mod='rc_fancourier'}</th>
                            <th>{l s='Date Added' mod='rc_fancourier'}</th>
                            <th>{l s='Actions' mod='rc_fancourier'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$rc_awb_status_list item=awb_row}
                            <tr>
                                <td>
                                    <strong>{$awb_row.awb|escape:'htmlall':'UTF-8'}</strong>
                                </td>
                                <td>
                                    <a href="{$awb_row.order_url|escape:'htmlall':'UTF-8'}">
                                        #{$awb_row.id_order|intval}
                                        {if $awb_row.reference}
                                            &nbsp;({$awb_row.reference|escape:'htmlall':'UTF-8'})
                                        {/if}
                                    </a>
                                </td>
                                <td>{$awb_row.customer_name|escape:'htmlall':'UTF-8'}</td>
                                <td>{$awb_row.state_name|escape:'htmlall':'UTF-8'}</td>
                                <td>{$awb_row.date_add|escape:'htmlall':'UTF-8'}</td>
                                <td>
                                    <a href="{$awb_row.order_url|escape:'htmlall':'UTF-8'}" class="btn btn-xs btn-default">
                                        <i class="icon-eye"></i> {l s='View Order' mod='rc_fancourier'}
                                    </a>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-info">
                <p>{l s='No AWBs found in the database yet.' mod='rc_fancourier'}</p>
            </div>
        {/if}
    </div>
</div>
