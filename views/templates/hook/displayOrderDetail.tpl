{*
*  2014-2021 PayGlocal
*
*  @author    PayGlocal
*  @copyright 2014-2021 PayGlocal
*  @license   PayGlocal Commercial License
*}

<div id="payglocal-payment-info" class="box">
    <h4>{l s='PayGlocal Payment Info' mod='payglocal'}</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <tbody>
                {if $paymentdata.gid}
                    <tr><td><strong>{l s='Gid' mod='payglocal'}</strong></td> <td>{$paymentdata.gid}</td></tr>
                {/if}
                {if $paymentdata.merchantid}
                    <tr><td><strong>{l s='Merchant Unique Id' mod='payglocal'}</strong></td> <td>{$paymentdata.merchantid}</td></tr>
                {/if}
            </tbody>
        </table>
    </div>
</div>