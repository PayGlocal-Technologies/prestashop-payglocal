{*
*  2014-2021 PayGlocal
*
*  @author    PayGlocal
*  @copyright 2014-2021 PayGlocal
*  @license   PayGlocal Commercial License
*}

<div class="tab-pane" id="payglocal-payment-info">
    <div class="table-responsive">
        <table class="table">
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


