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

<div class="rc_free_shipping_per_group_container">
    <table class="table">
        <thead>
            <tr>
                <th>{l s='Group' mod='rc_fancourier'}</th>
                <th>{l s='Starting from (RON)' mod='rc_fancourier'}</th>
                <th>{l s='With tax?' mod='rc_fancourier'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$groups item=group}
                <tr>
                    <td>{$group.name|escape:'htmlall':'UTF-8'}</td>
                    <td><input type="text" name="rc_fancourier_free_shipping_group_amount[{$group.id_group|escape:'htmlall':'UTF-8'}]" value="{if isset($RC_FANCOURIER_FREE_GROUPS[$group.id_group])}{$RC_FANCOURIER_FREE_GROUPS[$group.id_group].amount|escape:'htmlall':'UTF-8'}{/if}" /></td>
                    <td><input type="checkbox" name="rc_fancourier_free_shipping_group_tax[{$group.id_group|escape:'htmlall':'UTF-8'}]" value="1" {if isset($RC_FANCOURIER_FREE_GROUPS[$group.id_group]) && $RC_FANCOURIER_FREE_GROUPS[$group.id_group].with_tax}checked{/if}/></td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>