{*
*  2014-2021 Prestashoppe
*
*  @author    Prestashoppe
*  @copyright 2014-2021 Prestashoppe
*  @license   Prestashoppe Commercial License
*}

<div class="pre-emis-payment">
    <form action="{$action_url}" id="emis-payment-form">
        <input type="hidden" name="token" value="{$token}" />
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="emis-phone-number" class="control-label emis-control-label">{l s='Your GPO phone number' mod='preemis'} <span class="required">*</span></label>
                    <input name="emis-phone-number" id="emis-phone-number" type="tel" class="input-lg form-control emis-phone-number" placeholder="" required>
                </div>
            </div>
        </div>
    </form>
</div>
