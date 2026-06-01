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

<div class="panel panel-rc_fancourier_locations">
    <div class="panel-heading">
        <span class="icon-home"></span> {l s='Locations (Client IDs)' mod='rc_fancourier'}
    </div>
    <form id="rc_fancourier_locations_form" method="POST">
        <table id="rc_fancourier_locations_table" class="table">
            <thead>
            <tr>
                {if !$ps15}
                    <th>
                        {l s='Move' mod='rc_fancourier'}
                    </th>
                {/if}
                <th>
                    {l s='Client ID' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='Location name' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='Actions' mod='rc_fancourier'}
                </th>
                <th>
                    {l s='Default' mod='rc_fancourier'}
                </th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$locations item=location}
                <tr>
                    {if !$ps15}
                        <td class="locations-drag"><span class="icon-move"></span></td>
                    {/if}
                    <td>
                        <input type="text" name="rc_fancourier_locations[client_id][]"
                               value="{$location.client_id|escape:'htmlall':'UTF-8'}"/>
                    </td>
                    <td>
                        <input type="text" name="rc_fancourier_locations[label][]"
                               value="{$location.label|escape:'htmlall':'UTF-8'}"/>
                    </td>
                    <td>
                        <a href="#"
                           class="btn btn-danger rc_fancourier_location_delete_button">{l s='Delete' mod='rc_fancourier'}</a>
                    </td>
                    <td></td>
                </tr>
            {/foreach}
            {if sizeof($locations) == 0}
                <tr>
                    {if !$ps15}
                        <td class="locations-drag"><span class="icon-move"></span></td>
                    {/if}
                    <td>
                        <input type="text" name="rc_fancourier_locations[client_id][]" value=""/>
                    </td>
                    <td>
                        <input type="text" name="rc_fancourier_locations[label][]" value=""/>
                    </td>
                    <td>
                        <a href="#"
                           class="btn btn-danger rc_fancourier_location_delete_button">{l s='Delete' mod='rc_fancourier'}</a>
                    </td>
                    <td></td>
                </tr>
            {/if}
            <tr class="rc_fancourier_listing_add">
                <td colspan="5">
                    <a href="#" class="rc_fancourier_listing_add_button btn btn-primary"
                       data-delete_label="{l s='Delete' mod='rc_fancourier'}">{l s='Add another location' mod='rc_fancourier'}</a>
                    <a href="#" class="rc_fancourier_listing_retrieve_button btn btn-success">{l s='Automatically retrieve locations' mod='rc_fancourier'}</a>
                </td>
            </tr>
            </tbody>
        </table>
        <div class="panel-footer">
            <button type="submit" value="1" id="rc_fancourier_locations_submit"
                    name="submitRc_fancourierModuleLocations" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save' mod='rc_fancourier'}
            </button>
        </div>
    </form>
</div>