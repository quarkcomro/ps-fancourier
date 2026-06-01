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

<select name="RC_FANCOURIER_DEFAULT_DROPOFF_LOCATION" id="RC_FANCOURIER_DEFAULT_DROPOFF_LOCATION" class="form-control">
    <option value="0">{l s='-- None --' mod='rc_fancourier'}</option>
    {foreach from=$dropoff_locations item=location}
        <option value="{$location.id_office|escape:'htmlall':'UTF-8'}" {if $selected_dropoff_location == $location.id_office}selected="selected"{/if}>
            {$location.name|escape:'htmlall':'UTF-8'}
        </option>
    {/foreach}
</select>
<p class="help-block">
    {l s='Select the default dropoff location (FANbox or PayPoint) that will be automatically selected when dropoff service is chosen in the order page.' mod='rc_fancourier'}
</p>
<script type="text/javascript">
    (function() {
        function initSelect2() {
            if (typeof $.fn.select2 !== 'undefined' && $('#RC_FANCOURIER_DEFAULT_DROPOFF_LOCATION').length) {
                $('#RC_FANCOURIER_DEFAULT_DROPOFF_LOCATION').select2({
                    width: '100%',
                    placeholder: '{l s='-- None --' mod='rc_fancourier'|escape:'javascript'}',
                    allowClear: true
                });
            }
        }
        
        if (typeof jQuery !== 'undefined') {
            if (jQuery(document).ready) {
                jQuery(document).ready(initSelect2);
            } else {
                jQuery(initSelect2);
            }
        } else if (typeof $ !== 'undefined') {
            if ($(document).ready) {
                $(document).ready(initSelect2);
            } else {
                $(initSelect2);
            }
        }
    })();
</script>

