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

<p class="alert alert-info">
    {l s='Use the following link to automatically change the order states of your orders, based on their delivery state, automatically taken from Fan Courier:' mod='rc_fancourier'}
    <a href="{$rc_fancourier_check_states_link|escape:'htmlall':'UTF-8'}" target="_blank">{$rc_fancourier_check_states_link|escape:'htmlall':'UTF-8'}</a><br/>
    {l s='TIP: You can add the link above in a cronjob to automate the process.' mod='rc_fancourier'}<br/>
    {l s='Use the settings below to configure how the order states shall be changed based on their delivery status.' mod='rc_fancourier'}
</p>