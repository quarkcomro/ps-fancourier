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
<script type="text/javascript">
    {foreach from=$js_vals item=js_val key=key}
        var {$key|escape:'htmlall':'UTF-8'} = {if is_bool($js_val)}{$js_val|escape:'htmlall':'UTF-8'}{else}"{$js_val|escape:'htmlall':'UTF-8'}"{/if};
    {/foreach}
</script>