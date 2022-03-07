{*
*  2014-2021 PayGlocal
*
*  @author    PayGlocal
*  @copyright 2014-2021 PayGlocal
*  @license   PayGlocal Commercial License
*}

{extends file="helpers/form/form.tpl"}
{block name="field"}
    {if $input.type == 'file'}
        <div class="col-lg-8">
            <div class="form-group">
                <div class="col-lg-4">
                    <input id="{$input.name}" type="file" name="{$input.name}" class="hide" />
                    <div class="dummyfile input-group">
                        <span class="input-group-addon"><i class="icon-file"></i></span>
                        <input id="{$input.name}-name" type="text" class="disabled" name="filename" readonly />
                        <span class="input-group-btn">
                            <button id="{$input.name}-selectbutton" type="button" name="submitAddAttachments" class="btn btn-default">
                                <i class="icon-folder-open"></i> {l s='Choose a file' mod='payglocal'}
                            </button>
                        </span>
                    </div>
                </div>
                {if isset($fields_value[$input.name]) && $fields_value[$input.name] != ''}
                    <div class="col-lg-6">
                        <span style="color:red">{$fields_value[$input.name]}</span>
                        {if $input.name == 'PGL_PUBLIC_PEM'}
                            <a class="btn btn-default" href="{$current}&{$identifier}={$form_id|intval}&token={$token}&deletePublicPem=1">
                                <i class="icon-trash"></i> {l s='Delete' mod='payglocal'}
                            </a>
                        {else}
                            <a class="btn btn-default" href="{$current}&{$identifier}={$form_id|intval}&token={$token}&deletePrivatePem=1">
                                <i class="icon-trash"></i> {l s='Delete' mod='payglocal'}
                            </a>
                        {/if}
                    </div>  
                {/if}
            </div>
            <script>
                $(document).ready(function () {
                    $('#{$input.name}-selectbutton').click(function (e) {
                        $('#{$input.name}').trigger('click');
                    });
                    $('#{$input.name}').change(function (e) {
                        var val = $(this).val();
                        var file = val.split(/[\\/]/);
                        $('#{$input.name}-name').val(file[file.length - 1]);
                    });
                });
            </script>

            {if isset($input.desc) && !empty($input.desc)}
                <p class="help-block">
                    {$input.desc}
                </p>
            {/if}
        </div>
    {else if $input.type == 'password'}
        <div class="col-lg-8">
            <input type="password"
                   id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}"
                   name="{$input.name}"
                   class="{if isset($input.class)}{$input.class}{/if}"
                   value="{$fields_value[$input.name]}"
                   {if isset($input.autocomplete) && !$input.autocomplete}autocomplete="off"{/if}
                   {if isset($input.required) && $input.required } required="required" {/if} />

            {if isset($input.desc) && !empty($input.desc)}
                <p class="help-block">
                    {$input.desc}
                </p>
            {/if}
        </div>

    {else}
        {$smarty.block.parent}
    {/if}
{/block}