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

<div class="alert alert-info">
    <p><strong>{l s='This module has multiple calculation methods:' mod='rc_fancourier'}</strong></p>
    <ol>
        <li>
            <strong>{l s='API method' mod='rc_fancourier'}</strong> {l s='- Shipping price is calculated using selfawb\'s API integration. This options requires you to have a valid selfawb account.' mod='rc_fancourier'}
        </li>
        <li>
            <strong>{l s='Local method' mod='rc_fancourier'}</strong> {l s='- Shipping price is calculated without accessing any API from outside, so it does not require any account. This method has increased speed compared to the API one, and we strongly recommend it, because you do not depend on anyone else\'s resources. Plus it has configurable price rules (starting price, price per extra km, price per extra kg, free shipping price starting from X RON etc.)' mod='rc_fancourier'}
        </li>
        <li>
            <strong>{l s='No calculations' mod='rc_fancourier'}</strong> {l s='- You can use your own carrier rules to calculate the prices and only use this module for other features.' mod='rc_fancourier'}
        </li>
    </ol>
</div>