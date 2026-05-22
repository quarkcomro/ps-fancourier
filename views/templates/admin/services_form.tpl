{*
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
*}

<div class="panel panel-rc_fancourier_services">
    <div class="panel-heading">
        <span class="icon-home"></span> {l s='Services' mod='rc_fancourier'}
    </div>
    <script>
        var rc_fancourier_services = {$services|json_encode};
    </script>
    <form id="rc_fancourier_services_form" method="POST">
        <table id="rc_fancourier_services_table" class="table">
            <thead>
            <tr>
                <th>
                    {l s='ID/REF' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='Carrier Name' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='Service' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='COD tax' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='Active' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='Actions' mod='rc_fancourier'}
                </th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$carriers item=carrier}
                <tr>
                    <td>
                        {$carrier.id_carrier|escape:'htmlall':'UTF-8'}/{$carrier.id_reference|escape:'htmlall':'UTF-8'}
                        <input type="hidden" name="rc_fancourier_services[id_reference][]"
                               value="{$carrier.id_reference|escape:'htmlall':'UTF-8'}"/>
                    </td>
                    <td>
                        <input type="text" name="rc_fancourier_services[name][]" value="{$carrier.name|escape:'htmlall':'UTF-8'}"/>
                    </td>
                    <td>
                        <select name="rc_fancourier_services[service][]">
                            {foreach from=$services item=service}
                                <option value="{$service|escape:'htmlall':'UTF-8'}"
                                        {if $service == $carrier.service}selected="selected"{/if}>{$service|escape:'htmlall':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </td>
                    <td>
                        <input type="hidden" name="rc_fancourier_services[cod][]" value="{(int)$carrier.cod|escape:'htmlall':'UTF-8'}"/>
                        <a class="carrier_cod_toggle" data-id_reference="{$carrier.id_reference|escape:'htmlall':'UTF-8'}" href="#">
                            {if !$ps15}
                                {if $carrier.cod}
                                    <i class="icon-check list-action-enable action-enabled"></i>
                                {else}
                                    <i class="icon-remove list-action-enable action-disabled"></i>
                                {/if}
                            {else}
                                {if $carrier.cod}
                                    <img src="../img/admin/enabled.gif" alt="{l s='Enabled' mod='rc_fancourier'}" title="{l s='Enabled' mod='rc_fancourier'}">
                                {else}
                                    <img src="../img/admin/disabled.gif" alt="{l s='Disabled' mod='rc_fancourier'}" title="{l s='Disabled' mod='rc_fancourier'}">
                                {/if}
                            {/if}
                        </a>
                    </td>
                    <td>
                        <input type="hidden" name="rc_fancourier_services[active][]" value="{(int)$carrier.active|escape:'htmlall':'UTF-8'}"/>
                        <a class="carrier_toggle" data-id_reference="{$carrier.id_reference|escape:'htmlall':'UTF-8'}" href="#">
                            {if !$ps15}
                                {if $carrier.active}
                                    <i class="icon-check list-action-enable action-enabled"></i>
                                {else}
                                    <i class="icon-remove list-action-enable action-disabled"></i>
                                {/if}
                            {else}
                                {if $carrier.active}
                                    <img src="../img/admin/enabled.gif" alt="{l s='Enabled' mod='rc_fancourier'}" title="{l s='Enabled' mod='rc_fancourier'}">
                                {else}
                                    <img src="../img/admin/disabled.gif" alt="{l s='Disabled' mod='rc_fancourier'}" title="{l s='Disabled' mod='rc_fancourier'}">
                                {/if}
                            {/if}
                        </a>
                    </td>
                    <td>
                        <a class="carrier_delete btn btn-danger" data-id_reference="{$carrier.id_reference|escape:'htmlall':'UTF-8'}" href="#">
                            {l s='Delete' mod='rc_fancourier'}
                        </a>
                    </td>
                </tr>
            {/foreach}
            <tr class="rc_fancourier_service_add">
                <td colspan="6">
                    <a href="#" class="rc_fancourier_service_add_button btn btn-primary"
                       data-delete_label="{l s='Delete' mod='rc_fancourier'}">{l s='Add another carrier' mod='rc_fancourier'}</a>
                </td>
            </tr>
            </tbody>
        </table>
        <div class="services_payment_methods_service">
            <br />
            <div class="alert alert-info">{l s='By configuring the information below, the module will be able to preselect the desired service and to autocomplete or not the Cash on delivery field, based the payment method.' mod='rc_fancourier'}</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>{l s='Payment module' mod='rc_fancourier'}</th>
                        <th>{l s='Service' mod='rc_fancourier'}</th>
                        <th>{l s='Autocomplete Cash on delivery' mod='rc_fancourier'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$payment_methods_info item=method}
                        <tr>
                            <td>{$method.name|escape:'htmlall':'UTF-8'}</td>
                            <td>
                                <select name="RC_FANCOURIER_PM_SRV_{$method.id_module|escape:'htmlall':'UTF-8'}">
                                    <option value="0">{l s='- Set based on carrier -' mod='rc_fancourier'}</option>
                                    {foreach from=$services item=service}
                                        <option value="{$service|escape:'htmlall':'UTF-8'}"
                                                {if $service == $method.service}selected="selected"{/if}>{$service|escape:'htmlall':'UTF-8'}</option>
                                    {/foreach}
                                </select>
                            </td>
                            <td>
                                <input type="checkbox" name="RC_FANCOURIER_PM_COD_{$method.id_module|escape:'htmlall':'UTF-8'}" {if $method.set_cod}checked="checked"{/if} value="1"/>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <div class="panel-footer">
            <div class="pull-left">
                <div class="alert alert-warning">
                    {l s='You can restrict payment options available per carrier by going here:' mod='rc_fancourier'} <a
                            href="{$payment_preferences_link|escape:'htmlall':'UTF-8'}"
                            target="_blank">{l s='Payment preferences' mod='rc_fancourier'}</a>
                </div>
            </div>
            <button type="submit" value="1" id="rc_fancourier_services_submit" name="submitRc_fancourierModuleServices"
                    class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save' mod='rc_fancourier'}
            </button>
        </div>
    </form>
</div>