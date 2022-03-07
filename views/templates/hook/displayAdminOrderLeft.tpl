{*
*  2014-2021 PayGlocal
*
*  @author    PayGlocal
*  @copyright 2014-2021 PayGlocal
*  @license   PayGlocal Commercial License
*}

<div id="" class="panel">
    <div class="panel-heading">
        <i class="icon-money"></i>
        {l s='PayGlocal Payment Info' mod='payglocal'}
    </div>
    <div class="table-responsive">
        <table class="table">
            <tbody>
                {if $paymentdata.gid}
                    <tr><td><strong>{l s='Gid' mod='payglocal'}</strong></td> <td>{$preanzpaymentdata.gid}</td></tr>
                {/if}
                {if $paymentdata.merchantid}
                    <tr><td><strong>{l s='Merchant Unique Id' mod='payglocal'}</strong></td> <td>{$preanzpaymentdata.merchantid}</td></tr>
                {/if}
            </tbody>
        </table>
    </div>
</div>

