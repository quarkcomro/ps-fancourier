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

{if !$ps15}
    <div class="panel card">
        <div class="panel-heading card-header">
            <i class="icon-truck"></i>
            {l s='Fan Courier AWB' mod='rc_fancourier'}
        </div>
        <div class="panel-content panel-content-rc_fancourier card-body">
            <div class="rc_fancourier_awbs" {if !$awbs}style="display: none;"{/if}
                 data-delete_label="{l s='Delete AWB' mod='rc_fancourier'}"
                 data-show_label="{l s='Show AWB' mod='rc_fancourier'}"
                 data-status_label="{l s='Show status' mod='rc_fancourier'}">
                <p><strong>{l s='AWBs:' mod='rc_fancourier'}</strong></p>
                {if $awbs}
                    {foreach from=$awbs item=awb}
                        <div class="row">
                            <div class="col-xs-12 col-12">
                                <input type="text" readonly="readonly" value="{$awb.awb|escape:'htmlall':'UTF-8'}" class="rc_fancourier_awb_input form-control"/>
                                <a href="#" class="btn btn-danger rc_fancourier_deleteawb"
                                   data-awb="{$awb.awb|escape:'htmlall':'UTF-8'}">{l s='Delete AWB' mod='rc_fancourier'}</a>
                                <a href="#" class="btn btn-warning rc_fancourier_showawb"
                                   data-awb="{$awb.awb|escape:'htmlall':'UTF-8'}">{l s='Show AWB' mod='rc_fancourier'}</a>
                                <a href="#" class="btn btn-info rc_fancourier_statusawb"
                                   data-awb="{$awb.awb|escape:'htmlall':'UTF-8'}">{l s='Show status' mod='rc_fancourier'}</a>
                            </div>
                        </div>
                    {/foreach}
                {/if}
            </div>
            <form id="rc_fancourier_form" class="row" action="{$rc_fancourier_ajax_url|escape:'htmlall':'UTF-8'}">
                <input type="hidden" name="id_order" value="{$id_order|escape:'htmlall':'UTF-8'}"/>
                <input type="hidden" name="rc_fancourier_token" value="{$rc_fancourier_token|escape:'htmlall':'UTF-8'}"/>
                <input type="hidden" name="rc_fancourier_customer_email" value="{$rc_fancourier_customer_email|escape:'htmlall':'UTF-8'}"/>
                <input type="hidden" name="action" value="generateAwb"/>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_service" class="control-label">{l s='Service:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_service" id="rc_fancourier_service" class="custom-select">
                        {foreach from=$services item=service}
                            <option value="{$service|escape:'htmlall':'UTF-8'}" {if $service == $service_selected}selected{/if}>{$service|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_location" class="control-label">{l s='Location:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_location" id="rc_fancourier_location" class="custom-select">
                        {foreach from=$locations item=location}
                            <option value="{$location.client_id|escape:'htmlall':'UTF-8'}"
                                    {if $location.client_id == $location_selected}selected{/if}>{$location.label|escape:'htmlall':'UTF-8'}
                                ({$location.client_id|escape:'htmlall':'UTF-8'})
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_cod_tax"
                           class="control-label">{l s='Who pays COD tax:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_cod_tax" id="rc_fancourier_cod_tax" class="custom-select">
                        <option value="expeditor"
                                {if $plata_ramburs_la == 'expeditor'}selected{/if}>{l s='Sender' mod='rc_fancourier'}</option>
                        <option value="destinatar"
                                {if $plata_ramburs_la == 'destinatar'}selected{/if}>{l s='Receiver' mod='rc_fancourier'}</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_del_tax"
                           class="control-label">{l s='Who pays delivery tax:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_del_tax" id="rc_fancourier_del_tax" class="custom-select">
                        <option value="expeditor"
                                {if $plata_la == 'expeditor'}selected{/if}>{l s='Sender' mod='rc_fancourier'}</option>
                        <option value="destinatar"
                                {if $plata_la == 'destinatar'}selected{/if}>{l s='Receiver' mod='rc_fancourier'}</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <div class="row">
                        <div class="col-6 col-xs-6">
                            <label for="rc_fancourier_envelopes"
                                   class="control-label">{l s='Envelopes' mod='rc_fancourier'}</label>
                            <input type="text" name="rc_fancourier_envelopes" id="rc_fancourier_envelopes"
                                   value="{$envelopes|escape:'htmlall':'UTF-8'}" class="form-control" />
                        </div>
                        <div class="col-6 col-xs-6">
                            <label for="rc_fancourier_boxes"
                                   class="control-label">{l s='Boxes' mod='rc_fancourier'}</label>
                            <input type="text" name="rc_fancourier_boxes" id="rc_fancourier_boxes" value="{$boxes|escape:'htmlall':'UTF-8'}" class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_weight" class="control-label">{l s='Weight (kg)' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_weight" id="rc_fancourier_weight_total" value="{$weight|escape:'htmlall':'UTF-8'}" class="form-control"/>
                    <small class="text-muted rc_fancourier_weight_hint" style="display:none">{l s='Sum from per-package weights below' mod='rc_fancourier'}</small>
                </div>
                <div class="form-group col-lg-12 col-md-12 rc_fancourier_per_package_panel" data-panel="bo16"
                     data-envelope_label="{$rc_fancourier_envelope_label|escape:'htmlall':'UTF-8'}"
                     data-box_label="{$rc_fancourier_box_label|escape:'htmlall':'UTF-8'}"
                     style="display:none">
                    <label class="control-label">{l s='Weight per package (kg) — required by FAN for compound AWBs' mod='rc_fancourier'}</label>
                    <button type="button" class="btn btn-default btn-sm rc_fancourier_distribute_btn" style="margin-left:10px">{l s='Distribute total equally' mod='rc_fancourier'}</button>
                    <div class="rc_fancourier_per_package_inputs" style="margin-top:8px"></div>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_contact_person"
                           class="control-label">{l s='Contact person' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_contact_person" value="{$contact_person|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_declared_value"
                           class="control-label">{l s='Declared value (RON)' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_declared_value" value="{$declared_value|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_cod_value"
                           class="control-label">{l s='Cash on delivery (RON)' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_cod_value" value="{$cod_value|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_observations"
                           class="control-label">{l s='Observations' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_observations" value="{$observations|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-1 col-md-3">
                    <label for="rc_fancourier_restitution"
                           class="control-label">{l s='Restitution' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_restitution" value="{$restitution|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-3 col-md-3">
                    <label for="rc_fancourier_content"
                           class="control-label">{l s='Content' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_content" value="{$content|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                {if $show_dim}
                    <div class="form-group col-lg-2 col-md-2">
                        <label for="rc_fancourier_length"
                               class="control-label">{l s='Length' mod='rc_fancourier'}</label>
                        <input type="text" name="rc_fancourier_length" value="{$length|escape:'htmlall':'UTF-8'}" class="form-control"/>
                    </div>
                    <div class="form-group col-lg-2 col-md-2">
                        <label for="rc_fancourier_width"
                               class="control-label">{l s='Width' mod='rc_fancourier'}</label>
                        <input type="text" name="rc_fancourier_width" value="{$width|escape:'htmlall':'UTF-8'}" class="form-control"/>
                    </div>
                    <div class="form-group col-lg-2 col-md-2">
                        <label for="rc_fancourier_height"
                               class="control-label">{l s='Height' mod='rc_fancourier'}</label>
                        <input type="text" name="rc_fancourier_height" value="{$height|escape:'htmlall':'UTF-8'}" class="form-control"/>
                    </div>
                {/if}
                <div class="form-group col-xs-12 col-12">
                    <div class="form-group col-lg-4 col-md-6">
                        <label for="rc_fancourier_options"
                               class="control-label">{l s='Options' mod='rc_fancourier'}</label><br/>
                        <input type="checkbox" name="rc_fancourier_options[]" value="A" id="rc_fancourier_options_A"
                               {if stripos($options, 'A') !== false}checked{/if} /> <label
                                for="rc_fancourier_options_A">{l s='Deschidere la livrare' mod='rc_fancourier'}</label><br/>
                        <input type="checkbox" name="rc_fancourier_options[]" value="B" id="rc_fancourier_options_B"
                               {if stripos($options, 'B') !== false}checked{/if} /> <label
                                for="rc_fancourier_options_B">{l s='oPOD (restituire AWB semnat in original)' mod='rc_fancourier'}</label><br/>
                        <input type="checkbox" name="rc_fancourier_options[]" value="S" id="rc_fancourier_options_S"
                               {if stripos($options, 'S') !== false}checked{/if} /> <label
                                for="rc_fancourier_options_S">{l s='Livrare sambata' mod='rc_fancourier'}</label><br/>
                        <input type="checkbox" name="rc_fancourier_options[]" value="X" id="rc_fancourier_options_X"
                               {if stripos($options, 'X') !== false}checked{/if} /> <label
                                for="rc_fancourier_options_X">{l s='ePOD (semnatura electronica, in PDA)' mod='rc_fancourier'}</label><br/>
                        <input type="checkbox" name="rc_fancourier_options[]" value="W" id="rc_fancourier_options_W"
                               {if $default_dropoff_enabled || stripos($options, 'W') !== false}checked{/if} /> <label
                                for="rc_fancourier_options_W">{l s='Predare la FANbox (dropoff)' mod='rc_fancourier'}</label><br/>
                        <div id="rc_fan_dropoff_section" style="{if !$default_dropoff_enabled && stripos($options, 'W') === false}display:none;{/if}margin-top:6px;">
                            <label class="control-label" style="display:block;font-weight:600;margin-bottom:4px;">{l s='Drop-off FANbox location' mod='rc_fancourier'}</label>
                            <select name="rc_fancourier_dropoff_location" id="rc_fancourier_dropoff_location" class="custom-select">
                                <option value="">— {l s='Select location' mod='rc_fancourier'} —</option>
                                {foreach from=$lockers item=locker}
                                    <option value="{$locker.id_office|escape:'htmlall':'UTF-8'}"
                                            {if $default_dropoff_id == $locker.id_office}selected{/if}>
                                        [{$locker.judet|escape:'htmlall':'UTF-8'}] {$locker.strada|escape:'htmlall':'UTF-8'} — {$locker.localitate|escape:'htmlall':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group col-lg-8 col-md-6 panel rc_fancourier_packing_panel">
                        <div class="rc_fancourier_packing_title panel-heading">{l s='Packing list' mod='rc_fancourier'}</div>
                        <table class="rc_fancourier_packing_table table">
                            <thead>
                                <tr>
                                    <th>{l s='Product' mod='rc_fancourier'}</th>
                                    <th>{l s='Description' mod='rc_fancourier'}</th>
                                    <th>{l s='Code/Serial' mod='rc_fancourier'}</th>
                                    <th>{l s='Qty' mod='rc_fancourier'}</th>
                                    <th>{l s='Decl. value (RON)' mod='rc_fancourier'}</th>
                                    <th align="center" class="center">{l s='Actions' mod='rc_fancourier'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$products item=product}
                                    <tr>
                                        <td><input type="text" name="rc_fancourier_packing[name][]" value="{$product.product_name|escape:'htmlall':'UTF-8'}"  class="form-control"/></td>
                                        <td><input type="text" name="rc_fancourier_packing[description][]" value=""  class="form-control"/></td>
                                        <td><input type="text" name="rc_fancourier_packing[code][]" value="{$product.reference|escape:'htmlall':'UTF-8'}"  class="form-control"/></td>
                                        <td><input type="text" name="rc_fancourier_packing[quantity][]" value="{$product.product_quantity|escape:'htmlall':'UTF-8'}"  class="form-control"/></td>
                                        <td><input type="text" name="rc_fancourier_packing[decl_value][]" value="{number_format($product.total_price_tax_incl, 2, '.', '')|escape:'htmlall':'UTF-8'}"  class="form-control"/></td>
                                        <td align="center"><a href="#" class="rc_fancourier_packing_minus"><span class="fa fa-minus icon icon-minus"></span></a> <a href="#" class="rc_fancourier_packing_plus"><span class="fa fa-plus icon icon-plus"></span></a></td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                        <div class="rc_fancourier_packing_data">
                            <p>
                                {l s='ID Series: ' mod='rc_fancourier'} <input type="text" name="rc_fancourier_id_series" value=""  class="form-control"/>
                                {l s='ID Number: ' mod='rc_fancourier'} <input type="text" name="rc_fancourier_id_number" value=""  class="form-control"/>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="form-group col-lg-2 col-md-3" style="clear: left;">
                    <label for="rc_fancourier_options_D" class="control-label">{l s='Shipping method' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_options[]" id="rc_fancourier_options_D" class="custom-select">
                        <option value="">{l s='To address' mod='rc_fancourier'}</option>
                        <option value="D" {if $selected_fanbox}selected{/if}>{l s='To Fan Courier Office' mod='rc_fancourier'}</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_customer_name"
                           class="control-label">{l s='Customer name' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_customer_name" value="{$customer_name|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_customer_contact"
                           class="control-label">{l s='Customer contact' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_customer_contact" value="{$customer_contact|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_phone" class="control-label">{l s='Phone' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_phone" value="{$phone|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-4 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_address" class="control-label">{l s='Address' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_address" value="{$address|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-2 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_city" class="control-label">{l s='City' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_city" value="{$city|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_state" class="control-label">{l s='State' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_state" value="{$state|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_postcode" class="control-label">{l s='Postcode' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_postcode" value="{$postcode|escape:'htmlall':'UTF-8'}" class="form-control"/>
                </div>
                <div class="form-group col-lg-2 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_address" class="control-label">{l s='Office' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_office_address" class="custom-select">
                        {foreach from=$offices item=office}
                            <option data-city="{$office.localitate|escape:'htmlall':'UTF-8'}" data-state="{$office.judet|escape:'htmlall':'UTF-8'}" data-postcode="{$office.cod_postal|escape:'htmlall':'UTF-8'}" value="{$office.id_office}" {if isset($selected_fanbox) && $selected_fanbox == $office.id_office} selected {/if}>{$office.strada|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_city" class="control-label">{l s='City' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_office_city" value="" class="form-control"/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_state" class="control-label">{l s='State' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_office_state" value="" class="form-control"/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_postcode" class="control-label">{l s='Postcode' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_office_postcode" value="" class="form-control"/>
                </div>
                <div class="clearfix"></div>
                <div class="col-xs-12 col-12">
                    {if $show_noinv_warning}
                        <div class="alert alert-warning">{l s='Warning! The invoice has not yet been generated!' mod='rc_fancourier'}</div>
                    {/if}
                    <div class="rc_fancourier_awb_actions">
                        <button type="submit" class="btn btn-primary"
                                id="rc_fancourier_generate_awb">{l s='Generate AWB' mod='rc_fancourier'}</button>
                        <button type="button" class="btn btn-warning" id="rc_fancourier_return_awb_btn"
                                title="{l s='Pre-fills form with reversed addresses for a return shipment' mod='rc_fancourier'}">
                            <i class="icon-refresh"></i> {l s='Generate Return AWB' mod='rc_fancourier'}
                        </button>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="col-12 col-xs-12">
                    <div class="rc_fancourier_infos_container">
                        <p>{l s='Total weight:' mod='rc_fancourier'} <span class="rc_fancourier_total_weight">{$rc_fancourier_total_weight|escape:'htmlall':'UTF-8'}</span> {l s='kg' mod='rc_fancourier'}</p>
                        <p>{l s='Extra weight:' mod='rc_fancourier'} <span class="rc_fancourier_extra_weight">{$rc_fancourier_extra_weight|escape:'htmlall':'UTF-8'}</span> {l s='kg' mod='rc_fancourier'}</p>
                        <p>{l s='Extra km:' mod='rc_fancourier'} <span class="rc_fancourier_extra_km">{$rc_fancourier_extra_km|escape:'htmlall':'UTF-8'}</span> {l s='km' mod='rc_fancourier'}</p>
                        <p><a class="btn btn-sm btn-primary rc_fancourier_refresh_order_infos" href="#">{l s='Refresh' mod='rc_fancourier'}</a></p>
                    </div>
                    <div class="rc_fancourier_awb_manual">
                        <p><strong>{l s='Manual AWB' mod='rc_fancourier'}</strong></p>
                        <p>
                            <label for="manual_add_awb">{l s='AWB' mod='rc_fancourier'}</label>
                            <input type="text" name="manual_add_awb" id="manual_add_awb"  class="form-control"/>
                        </p>
                        <p>
                            <label for="manual_add_awb">{l s='Location' mod='rc_fancourier'}</label>
                            <select name="rc_fancourier_location_manual" id="rc_fancourier_location_manual" class="custom-select">
                                {foreach from=$locations item=location}
                                    <option value="{$location.client_id|escape:'htmlall':'UTF-8'}"
                                            {if $location.client_id == $location_selected}selected{/if}>{$location.label|escape:'htmlall':'UTF-8'}
                                        ({$location.client_id|escape:'htmlall':'UTF-8'})
                                    </option>
                                {/foreach}
                            </select>
                        </p>
                        <a class="btn btn-sm btn-primary rc_fancourier_manual_awb_add" href="#">{l s='Add' mod='rc_fancourier'}</a>
                    </div>
                    <div class="rc_fancourier_cost_container">
                        <p><strong>{l s='Shipping cost' mod='rc_fancourier'}</strong></p>
                        <p>
                            <button type="button" class="btn btn-sm btn-secondary" id="rc_fancourier_calculate_cost">
                                {l s='Calculate shipping cost' mod='rc_fancourier'}
                            </button>
                        </p>
                        <div id="rc_fancourier_cost_result" class="rc_fancourier_cost_result" style="display: none;"></div>
                    </div>
                </div>
            </form>
        </div>
    </div>
{else}
    <fieldset>
        <legend>{l s='Fan Courier AWB' mod='rc_fancourier'}</legend>
        <div class="panel-content panel-content-rc_fancourier">
            <div class="rc_fancourier_awbs" {if !$awbs}style="display: none;"{/if}
                 data-delete_label="{l s='Delete AWB' mod='rc_fancourier'}"
                 data-show_label="{l s='Show AWB' mod='rc_fancourier'}"
                 data-status_label="{l s='Show status' mod='rc_fancourier'}">
                <p><strong>{l s='AWBs:' mod='rc_fancourier'}</strong></p>
                {if $awbs}
                    {foreach from=$awbs item=awb}
                        <div class="row">
                            <div class="col-xs-12">
                                <input type="text" readonly="readonly" value="{$awb.awb|escape:'htmlall':'UTF-8'}" class="rc_fancourier_awb_input"/>
                                <a href="#" class="btn btn-danger rc_fancourier_deleteawb"
                                   data-awb="{$awb.awb|escape:'htmlall':'UTF-8'}">{l s='Delete AWB' mod='rc_fancourier'}</a>
                                <a href="#" class="btn btn-warning rc_fancourier_showawb"
                                   data-awb="{$awb.awb|escape:'htmlall':'UTF-8'}">{l s='Show AWB' mod='rc_fancourier'}</a>
                                <a href="#" class="btn btn-info rc_fancourier_statusawb"
                                   data-awb="{$awb.awb|escape:'htmlall':'UTF-8'}">{l s='Show status' mod='rc_fancourier'}</a>
                            </div>
                        </div>
                    {/foreach}
                {/if}
            </div>
            <form id="rc_fancourier_form" class="row" action="{$rc_fancourier_ajax_url|escape:'htmlall':'UTF-8'}">
                <input type="hidden" name="id_order" value="{$id_order|escape:'htmlall':'UTF-8'}"/>
                <input type="hidden" name="rc_fancourier_token" value="{$rc_fancourier_token|escape:'htmlall':'UTF-8'}"/>
                <input type="hidden" name="rc_fancourier_customer_email" value="{$rc_fancourier_customer_email|escape:'htmlall':'UTF-8'}"/>
                <input type="hidden" name="action" value="generateAwb"/>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_service" class="control-label">{l s='Service:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_service" id="rc_fancourier_service">
                        {foreach from=$services item=service}
                            <option value="{$service|escape:'htmlall':'UTF-8'}" {if $service == $service_selected}selected{/if}>{$service|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_location" class="control-label">{l s='Location:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_location" id="rc_fancourier_location">
                        {foreach from=$locations item=location}
                            <option value="{$location.client_id|escape:'htmlall':'UTF-8'}"
                                    {if $location.client_id == $location_selected}selected{/if}>{$location.label|escape:'htmlall':'UTF-8'}
                                ({$location.client_id|escape:'htmlall':'UTF-8'})
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_cod_tax"
                           class="control-label">{l s='Who pays COD tax:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_cod_tax" id="rc_fancourier_cod_tax">
                        <option value="expeditor"
                                {if $plata_ramburs_la == 'expeditor'}selected{/if}>{l s='Sender' mod='rc_fancourier'}</option>
                        <option value="destinatar"
                                {if $plata_ramburs_la == 'destinatar'}selected{/if}>{l s='Receiver' mod='rc_fancourier'}</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_del_tax"
                           class="control-label">{l s='Who pays delivery tax:' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_del_tax" id="rc_fancourier_del_tax">
                        <option value="expeditor"
                                {if $plata_la == 'expeditor'}selected{/if}>{l s='Sender' mod='rc_fancourier'}</option>
                        <option value="destinatar"
                                {if $plata_la == 'destinatar'}selected{/if}>{l s='Receiver' mod='rc_fancourier'}</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_envelopes"
                           class="control-label">{l s='Envelopes / boxes' mod='rc_fancourier'}</label>
                    <div class="row">
                        <div class="col-6 ">
                            <input type="text" name="rc_fancourier_envelopes" id="rc_fancourier_envelopes"
                                   value="{$envelopes|escape:'htmlall':'UTF-8'}"/>
                        </div>
                        <div class="col-6 col-xs-6">
                            <input type="text" name="rc_fancourier_boxes" id="rc_fancourier_boxes" value="{$boxes|escape:'htmlall':'UTF-8'}"/>
                        </div>
                    </div>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_weight" class="control-label">{l s='Weight (kg)' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_weight" id="rc_fancourier_weight_total_17" value="{$weight|escape:'htmlall':'UTF-8'}"/>
                    <small class="text-muted rc_fancourier_weight_hint" style="display:none">{l s='Sum from per-package weights below' mod='rc_fancourier'}</small>
                </div>
                <div class="form-group col-lg-12 col-md-12 rc_fancourier_per_package_panel" data-panel="bo17"
                     data-envelope_label="{$rc_fancourier_envelope_label|escape:'htmlall':'UTF-8'}"
                     data-box_label="{$rc_fancourier_box_label|escape:'htmlall':'UTF-8'}"
                     style="display:none">
                    <label class="control-label">{l s='Weight per package (kg) — required by FAN for compound AWBs' mod='rc_fancourier'}</label>
                    <button type="button" class="btn btn-default btn-sm rc_fancourier_distribute_btn" style="margin-left:10px">{l s='Distribute total equally' mod='rc_fancourier'}</button>
                    <div class="rc_fancourier_per_package_inputs" style="margin-top:8px"></div>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_contact_person"
                           class="control-label">{l s='Contact person' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_contact_person" value="{$contact_person|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_declared_value"
                           class="control-label">{l s='Declared value (RON)' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_declared_value" value="{$declared_value|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_cod_value"
                           class="control-label">{l s='Cash on delivery (RON)' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_cod_value" value="{$cod_value|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_observations"
                           class="control-label">{l s='Observations' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_observations" value="{$observations|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-1 col-md-3">
                    <label for="rc_fancourier_restitution"
                           class="control-label">{l s='Restitution' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_restitution" value="{$restitution|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-3 col-md-9">
                    <label for="rc_fancourier_content"
                           class="control-label">{l s='Content' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_content" value="{$content|escape:'htmlall':'UTF-8'}"/>
                </div>
                {if $show_dim}
                    <div class="form-group col-lg-2 col-md-2">
                        <label for="rc_fancourier_length"
                               class="control-label">{l s='Length' mod='rc_fancourier'}</label>
                        <input type="text" name="rc_fancourier_length" value="{$length|escape:'htmlall':'UTF-8'}" class="form-control"/>
                    </div>
                    <div class="form-group col-lg-2 col-md-2">
                        <label for="rc_fancourier_width"
                               class="control-label">{l s='Width' mod='rc_fancourier'}</label>
                        <input type="text" name="rc_fancourier_width" value="{$width|escape:'htmlall':'UTF-8'}" class="form-control"/>
                    </div>
                    <div class="form-group col-lg-2 col-md-2">
                        <label for="rc_fancourier_height"
                               class="control-label">{l s='Height' mod='rc_fancourier'}</label>
                        <input type="text" name="rc_fancourier_height" value="{$height|escape:'htmlall':'UTF-8'}" class="form-control"/>
                    </div>
                {/if}
                {*<div class="form-group col-lg-5 col-md-6">
                    <label for="rc_fancourier_observations" class="control-label">{l s='Observations' mod='rc_fancourier'}</label>
                    <div class="row">
                        <div class="col-xs-5">
                            <input type="checkbox" name="rc_fancourier_observations[]" value="Livrare urgenta" id="rc_fancourier_observations_1" /> <label for="rc_fancourier_observations_1">{l s='Livrare urgenta' mod='rc_fancourier'}</label><br />
                            <input type="checkbox" name="rc_fancourier_observations[]" value="Livrare luni" id="rc_fancourier_observations_2" /> <label for="rc_fancourier_observations_2">{l s='Livrare luni' mod='rc_fancourier'}</label><br />
                            <input type="checkbox" name="rc_fancourier_observations[]" value="A se contacta telefonic" id="rc_fancourier_observations_3" /> <label for="rc_fancourier_observations_3">{l s='A se contacta telefonic' mod='rc_fancourier'}</label><br />
                            <input type="checkbox" name="rc_fancourier_observations[]" value="Atentie-FRAGIL" id="rc_fancourier_observations_4" /> <label for="rc_fancourier_observations_4">{l s='Atentie-FRAGIL' mod='rc_fancourier'}</label><br />
                        </div>
                        <div class="col-xs-7">
                            <input type="checkbox" name="rc_fancourier_observations[]" value="Livrare personala cu CNP/serie CI" id="rc_fancourier_observations_5" /> <label for="rc_fancourier_observations_5">{l s='Livrare personala cu CNP/serie CI' mod='rc_fancourier'}</label><br />
                            <input type="checkbox" name="rc_fancourier_observations[]" value="Livrare cu stampila si semnatura" id="rc_fancourier_observations_6" /> <label for="rc_fancourier_observations_6">{l s='Livrare cu stampila si semnatura' mod='rc_fancourier'}</label><br />
                            <input type="checkbox" name="rc_fancourier_observations[]" value="Livrare dupa ora 16:00" id="rc_fancourier_observations_7" /> <label for="rc_fancourier_observations_7">{l s='Livrare dupa ora 16:00' mod='rc_fancourier'}</label><br />
                            <input type="checkbox" name="rc_fancourier_observations[]" value="Livrare in intervalul 09:00 - 17:00" id="rc_fancourier_observations_8" /> <label for="rc_fancourier_observations_8">{l s='Livrare in intervalul 09:00 - 17:00' mod='rc_fancourier'}</label>
                        </div>
                    </div>
                </div>*}
                <div class="form-group col-lg-4 col-md-6">
                    <label for="rc_fancourier_options"
                           class="control-label">{l s='Options' mod='rc_fancourier'}</label><br/>
                    <label
                            for="rc_fancourier_options_A"> <input type="checkbox" name="rc_fancourier_options[]" value="A" id="rc_fancourier_options_A"
                           {if stripos($options, 'A') !== false}checked{/if} /> {l s='Deschidere la livrare' mod='rc_fancourier'}</label><br/>
                    <label
                            for="rc_fancourier_options_B"> <input type="checkbox" name="rc_fancourier_options[]" value="B" id="rc_fancourier_options_B"
                           {if stripos($options, 'B') !== false}checked{/if} /> {l s='oPOD (restituire AWB semnat in original)' mod='rc_fancourier'}</label><br/>
                    <label
                            for="rc_fancourier_options_S"> <input type="checkbox" name="rc_fancourier_options[]" value="S" id="rc_fancourier_options_S"
                           {if stripos($options, 'S') !== false}checked{/if} /> {l s='Livrare sambata' mod='rc_fancourier'}</label><br/>
                    <label
                            for="rc_fancourier_options_X"> <input type="checkbox" name="rc_fancourier_options[]" value="X" id="rc_fancourier_options_X"
                           {if stripos($options, 'X') !== false}checked{/if} /> {l s='ePOD (semnatura electronica, in PDA)' mod='rc_fancourier'}</label><br/>
                </div>
                <div class="form-group col-lg-8 col-md-6 panel rc_fancourier_packing_panel">
                    <div class="rc_fancourier_packing_title panel-heading">{l s='Packing list' mod='rc_fancourier'}</div>
                    <table class="rc_fancourier_packing_table table">
                        <thead>
                        <tr>
                            <th>{l s='Product' mod='rc_fancourier'}</th>
                            <th>{l s='Description' mod='rc_fancourier'}</th>
                            <th>{l s='Code/Serial' mod='rc_fancourier'}</th>
                            <th>{l s='Qty' mod='rc_fancourier'}</th>
                            <th>{l s='Decl. value (RON)' mod='rc_fancourier'}</th>
                            <th align="center" class="center">{l s='Actions' mod='rc_fancourier'}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {foreach from=$products item=product}
                            <tr>
                                <td><input type="text" name="rc_fancourier_packing[name][]" value="{$product.product_name|escape:'htmlall':'UTF-8'}" /></td>
                                <td><input type="text" name="rc_fancourier_packing[description][]" value="" /></td>
                                <td><input type="text" name="rc_fancourier_packing[code][]" value="{$product.reference|escape:'htmlall':'UTF-8'}" /></td>
                                <td><input type="text" name="rc_fancourier_packing[quantity][]" value="{$product.product_quantity|escape:'htmlall':'UTF-8'}" /></td>
                                <td><input type="text" name="rc_fancourier_packing[decl_value][]" value="{number_format($product.total_price_tax_incl, 2, '.', '')|escape:'htmlall':'UTF-8'}" /></td>
                                <td align="center"><a href="#" class="rc_fancourier_packing_minus"><strong>-</strong></a> <a href="#" class="rc_fancourier_packing_plus"><strong>+</strong></a></td>
                            </tr>
                        {/foreach}
                        </tbody>
                    </table>
                    <div class="rc_fancourier_packing_data">
                        <p>
                            {l s='ID Series: ' mod='rc_fancourier'} <input type="text" name="rc_fancourier_id_series" value="" />
                            {l s='ID Number: ' mod='rc_fancourier'} <input type="text" name="rc_fancourier_id_number" value="" />
                        </p>
                    </div>
                </div>
                <div class="form-group col-lg-2 col-md-3" style="clear: left;">
                    <label for="rc_fancourier_options_D" class="control-label">{l s='Shipping method' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_options[]" id="rc_fancourier_options_D">
                        <option value="">{l s='To address' mod='rc_fancourier'}</option>
                        <option value="D" {if $selected_fanbox}selected{/if}>{l s='To Fan Courier Office' mod='rc_fancourier'}</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_customer_name"
                           class="control-label">{l s='Customer name' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_customer_name" value="{$customer_name|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="rc_fancourier_customer_contact"
                           class="control-label">{l s='Customer contact' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_customer_contact" value="{$customer_contact|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-1 col-md-3">
                    <label for="rc_fancourier_phone" class="control-label">{l s='Phone' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_phone" value="{$phone|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-2 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_address" class="control-label">{l s='Address' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_address" value="{$address|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_city" class="control-label">{l s='City' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_city" value="{$city|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_state" class="control-label">{l s='State' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_state" value="{$state|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_nonoffice">
                    <label for="rc_fancourier_postcode" class="control-label">{l s='Post code' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_postcode" value="{$postcode|escape:'htmlall':'UTF-8'}"/>
                </div>
                <div class="form-group col-lg-2 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_address" class="control-label">{l s='Office' mod='rc_fancourier'}</label>
                    <select name="rc_fancourier_office_address">
                        {foreach from=$offices item=office}
                            <option data-city="{$office.localitate|escape:'htmlall':'UTF-8'}" data-state="{$office.judet|escape:'htmlall':'UTF-8'}" data-postcode="{$office.cod_postal|escape:'htmlall':'UTF-8'}">{$office.strada|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_city" class="control-label">{l s='City' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_office_city" value=""/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_state" class="control-label">{l s='State' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_office_state" value=""/>
                </div>
                <div class="form-group col-lg-1 col-md-3 rc_fan_show_office">
                    <label for="rc_fancourier_office_postcode" class="control-label">{l s='Postcode' mod='rc_fancourier'}</label>
                    <input type="text" name="rc_fancourier_office_postcode" value=""/>
                </div>
                <div class="clearfix"></div>
                <div class="col-xs-12" style="padding: 5px;">
                    {if $show_noinv_warning}
                        <div class="alert alert-warning">{l s='Warning! The invoice has not yet been generated!' mod='rc_fancourier'}</div>
                    {/if}
                    <div class="rc_fancourier_awb_actions">
                        <button type="submit" class="btn btn-primary"
                                id="rc_fancourier_generate_awb">{l s='Generate AWB' mod='rc_fancourier'}</button>
                        <button type="button" class="btn btn-warning" id="rc_fancourier_return_awb_btn"
                                title="{l s='Pre-fills form with reversed addresses for a return shipment' mod='rc_fancourier'}">
                            <i class="icon-refresh"></i> {l s='Generate Return AWB' mod='rc_fancourier'}
                        </button>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="col-xs-12 col-12">
                    <div class="rc_fancourier_infos_container">
                        <p>{l s='Total weight:' mod='rc_fancourier'} <span class="rc_fancourier_total_weight">{$rc_fancourier_total_weight|escape:'htmlall':'UTF-8'}</span> {l s='kg' mod='rc_fancourier'}</p>
                        <p>{l s='Extra weight:' mod='rc_fancourier'} <span class="rc_fancourier_extra_weight">{$rc_fancourier_extra_weight|escape:'htmlall':'UTF-8'}</span> {l s='kg' mod='rc_fancourier'}</p>
                        <p>{l s='Extra km:' mod='rc_fancourier'} <span class="rc_fancourier_extra_km">{$rc_fancourier_extra_km|escape:'htmlall':'UTF-8'}</span> {l s='km' mod='rc_fancourier'}</p>
                        <p><a class="btn btn-sm btn-primary rc_fancourier_refresh_order_infos" href="#">{l s='Refresh' mod='rc_fancourier'}</a></p>
                    </div>
                    <div class="rc_fancourier_awb_manual">
                        <p><strong>{l s='Manual AWB' mod='rc_fancourier'}</strong></p>
                        <p>
                            <label for="manual_add_awb">{l s='AWB' mod='rc_fancourier'}</label>
                            <input type="text" name="manual_add_awb" id="manual_add_awb" />
                        </p>
                        <p>
                            <label for="manual_add_awb">{l s='Location' mod='rc_fancourier'}</label>
                            <select name="rc_fancourier_location_manual" id="rc_fancourier_location_manual">
                                {foreach from=$locations item=location}
                                    <option value="{$location.client_id|escape:'htmlall':'UTF-8'}"
                                            {if $location.client_id == $location_selected}selected{/if}>{$location.label|escape:'htmlall':'UTF-8'}
                                        ({$location.client_id|escape:'htmlall':'UTF-8'})
                                    </option>
                                {/foreach}
                            </select>
                        </p>
                        <a class="btn btn-sm btn-primary rc_fancourier_manual_awb_add" href="#">{l s='Add' mod='rc_fancourier'}</a>
                    </div>
                    <div class="rc_fancourier_cost_container">
                        <p><strong>{l s='Shipping cost' mod='rc_fancourier'}</strong></p>
                        <p>
                            <button type="button" class="btn btn-sm btn-secondary" id="rc_fancourier_calculate_cost">
                                {l s='Calculate shipping cost' mod='rc_fancourier'}
                            </button>
                        </p>
                        <div id="rc_fancourier_cost_result" class="rc_fancourier_cost_result" style="display: none;"></div>
                    </div>
                </div>
            </form>
        </div>
    </fieldset>
{/if}