<?php
/**
 *  2014-2021 PayGlocal
 *
 *  @author    PayGlocal
 *  @copyright 2014-2021 PayGlocal
 *  @license   PayGlocal Commercial License
 */

class PayGlocalResponseModuleFrontController extends ModuleFrontController
{

    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
    }

    public function postProcess()
    {
        parent::postProcess();
        $response = Tools::getAllValues();
        if (isset($response['x-gl-token']) && array_key_exists('x-gl-token', $response)) {
            $payment = $this->module->verifyPayGlSPayment($response['x-gl-token']);
            if (isset($payment['merchantUniqueId']) && array_key_exists('merchantUniqueId', $payment)) {
                list($id_cart, ) = explode('#', $payment['merchantUniqueId']);
                $cart = new Cart($id_cart);
                $currency = new Currency($cart->id_currency);
                $customer = new Customer($cart->id_customer);
                if (isset($payment['status']) && $payment['status'] == 'SENT_FOR_CAPTURE') {
                    $extra_vars = array(
                        'transaction_id' => $payment['merchantUniqueId']
                    );
                    
                    $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $cart->getOrderTotal(true, Cart::BOTH), $this->module->displayName, null, $extra_vars, (int) $currency->id, false, $customer->secure_key);

                    $this->module->setPayGlSOrderPaymentData($this->module->currentOrder, $payment);

                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
                } else {
                    $message = $this->module->l('Unfortunately your order cannot be processed as an error has occured. Please contact site owner.');
                    $this->errors[] = $message;
                    $this->redirectWithNotifications('index.php?controller=order');
                }
            } else {
                $message = $this->module->l('Unfortunately your order cannot be processed as an error has occured. Please contact site owner.');
                $this->errors[] = $message;
                $this->redirectWithNotifications('index.php?controller=order');
            }
        } else {
            $message = $this->module->l('Unfortunately your order cannot be processed as an error has occured. Please contact site owner.');
            $this->errors[] = $message;
            $this->redirectWithNotifications('index.php?controller=order');
        }
    }
}
