<?php
/**
 *  2014-2021 PayGlocal
 *
 *  @author    PayGlocal
 *  @copyright 2014-2021 PayGlocal
 *  @license   PayGlocal Commercial License
 */

class PayGlocalPaymentModuleFrontController extends ModuleFrontController
{

    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
    }

    public function postProcess()
    {
        parent::postProcess();
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'payglocal') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            $message = $this->module->l('This payment method is not available.');
            $this->errors[] = $message;
            $this->redirectWithNotifications('index.php?controller=order');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $message = $this->module->l('Customer is not valid.');
            $this->errors[] = $message;
            $this->redirectWithNotifications('index.php?controller=order');
        }
        
        //Create a payment url
        $payment = $this->module->createPayGlSPaymentUrl($cart);
        
        if(isset($payment['data']['redirectUrl']) && array_key_exists('redirectUrl', $payment['data'])) {
            Tools::redirect($payment['data']['redirectUrl']);
        } else {
            //$message = $payment['errors']['detailedMessage'];
            //$message = $payment['errors']['displayMessage'];
            $message = $this->module->l('Unfortunately your order cannot be processed as an error has occured. Please contact site owner.');
            $this->errors[] = $message;
            $this->redirectWithNotifications('index.php?controller=order');
        }
    }
}
