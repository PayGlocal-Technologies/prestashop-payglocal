<?php
/**
 *  2014-2021 PayGlocal
 *
 *  @author    PayGlocal
 *  @copyright 2014-2021 PayGlocal
 *  @license   PayGlocal Commercial License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP256;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256KW;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as SigCompactSerializer;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\JWSLoader;

class PayGlocal extends PaymentModule
{

    protected $output = '';
    protected $errors = array();

    public function __construct()
    {
        $this->name = 'payglocal';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'PayGlocal';
        $this->bootstrap = true;
        $this->module_key = '';
        $this->author_address = '';
        parent::__construct();
        $this->displayName = $this->l('PayGlocal Payment');
        $this->description = $this->l('This module allows any merchant to accept payments with payglocal payment service.');
        $this->pgl_payment_mode = Configuration::get('PGL_PAYMENT_MODE');
        $this->pgl_show_logo = Configuration::get('PGL_SHOW_LOGO');
        $this->pgl_merchant_id = Configuration::get('PGL_MERCHANT_ID');
        $this->pgl_public_kid = Configuration::get('PGL_PUBLIC_KID');
        $this->pgl_private_kid = Configuration::get('PGL_PRIVATE_KID');
        $this->pgl_public_pem = Configuration::get('PGL_PUBLIC_PEM');
        $this->pgl_private_pem = Configuration::get('PGL_PRIVATE_PEM');
        $this->pgl_payment_endpoint = ($this->pgl_payment_mode) ? 'https://api.prod.payglocal.in' : 'https://api.uat.payglocal.in';
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('displayAdminOrderLeft') &&
            $this->registerHook('displayAdminOrderTabLink') &&
            $this->registerHook('displayAdminOrderTabContent') &&
            $this->registerHook('displayOrderDetail') &&
            $this->installPayGlSettings() &&
            $this->createPayGlSettingsTables();
    }

    protected function installPayGlSettings()
    {
        Configuration::updateValue('PGL_PAYMENT_MODE', 0);
        Configuration::updateValue('PGL_SHOW_LOGO', 0);
        Configuration::updateValue('PGL_MERCHANT_ID', '');
        Configuration::updateValue('PGL_PUBLIC_KID', '');
        Configuration::updateValue('PGL_PRIVATE_KID', '');
        Configuration::updateValue('PGL_PUBLIC_PEM', '');
        Configuration::updateValue('PGL_PRIVATE_PEM', '');
        return true;
    }

    protected function createPayGlSettingsTables()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'payglocal` (
            `id_payglocal` int(11) NOT NULL AUTO_INCREMENT,
            `id_order` INT( 11 ) UNSIGNED,
            `gid` TEXT NULL,
            `merchantid` TEXT NULL,
            `iat` TEXT NULL,
            PRIMARY KEY  (`id_payglocal`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        if (Db::getInstance()->execute($sql) == false) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            $this->unregisterHook('actionFrontControllerSetMedia') &&
            $this->unregisterHook('paymentOptions') &&
            $this->unregisterHook('displayPaymentReturn') &&
            $this->unregisterHook('displayAdminOrderLeft') &&
            $this->unregisterHook('displayAdminOrderTabLink') &&
            $this->unregisterHook('displayAdminOrderTabContent') &&
            $this->unregisterHook('displayOrderDetail') &&
            $this->uninstallPayGlSettings();
    }

    protected function uninstallPayGlSettings()
    {
        Configuration::deleteByName('PGL_PAYMENT_MODE');
        Configuration::deleteByName('PGL_SHOW_LOGO');
        Configuration::deleteByName('PGL_MERCHANT_ID');
        Configuration::deleteByName('PGL_PUBLIC_KID');
        Configuration::deleteByName('PGL_PRIVATE_KID');

        if (file_exists(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . Configuration::get('PGL_PUBLIC_PEM'))) {
            Tools::deleteFile(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . Configuration::get('PGL_PUBLIC_PEM'));
        }

        if (file_exists(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . Configuration::get('PGL_PRIVATE_PEM'))) {
            Tools::deleteFile(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . Configuration::get('PGL_PRIVATE_PEM'));
        }

        Configuration::deleteByName('PGL_PUBLIC_PEM');
        Configuration::deleteByName('PGL_PRIVATE_PEM');

        return true;
    }

    public function getContent()
    {
        if (((bool) Tools::isSubmit('deletePublicPem')) == true) {
            if (file_exists(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_public_pem)) {
                Tools::deleteFile(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_public_pem);
            }
            Configuration::updateValue('PGL_PUBLIC_PEM', '');
            $this->output .= $this->displayConfirmation($this->l('Public pem is successfully deleted.'));
        }

        if (((bool) Tools::isSubmit('deletePrivatePem')) == true) {
            if (file_exists(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_private_pem)) {
                Tools::deleteFile(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_private_pem);
            }
            Configuration::updateValue('PGL_PRIVATE_PEM', '');
            $this->output .= $this->displayConfirmation($this->l('Private pem is successfully deleted.'));
        }

        if (((bool) Tools::isSubmit('submitPayGlForm')) == true) {
            $this->validPayGlFormPostValues();
            if (!count($this->errors)) {
                $this->savePayGlFormPostValues();
            } else {
                foreach ($this->errors as $err) {
                    $this->output .= $this->displayError($err);
                }
            }
        }

        $this->output .= $this->display(__FILE__, 'views/templates/admin/info.tpl');
        return $this->output . $this->renderPayGlForm();
    }

    protected function renderPayGlForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPayGlForm';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'uri' => $this->getPathUri(),
            'fields_value' => $this->getPayGlFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($this->getPayGlForm()));
    }

    protected function getPayGlForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('PayGlocal Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live Payment Mode'),
                        'name' => 'PGL_PAYMENT_MODE',
                        'is_bool' => true,
                        'required' => true,
                        'desc' => $this->l('If you choose YES then enable live or production mode for payglocal payment.'),
                        'values' => array(
                            array(
                                'id' => 'PGL_PAYMENT_MODE_ON',
                                'value' => 1,
                                'label' => $this->l('Live')
                            ),
                            array(
                                'id' => 'PGL_PAYMENT_MODE_OFF',
                                'value' => 0,
                                'label' => $this->l('Sandbox')
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Show Logo'),
                        'name' => 'PGL_SHOW_LOGO',
                        'is_bool' => true,
                        'required' => true,
                        'desc' => $this->l('If you choose YES then display payglocal logo on checkout page.'),
                        'values' => array(
                            array(
                                'id' => 'PGL_SHOW_LOGO_ON',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'PGL_SHOW_LOGO_OFF',
                                'value' => 0,
                                'label' => $this->l('No')
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Merchant ID'),
                        'name' => 'PGL_MERCHANT_ID',
                        'required' => true,
                        'desc' => $this->l('Enter a merchant id which is provided by payglocal.'),
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Public KID'),
                        'name' => 'PGL_PUBLIC_KID',
                        'required' => true,
                        'desc' => $this->l('Enter a public kid which is provided by payglocal.'),
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Private KID'),
                        'name' => 'PGL_PRIVATE_KID',
                        'required' => true,
                        'desc' => $this->l('Enter a private kid which is provided by payglocal.'),
                    ),
                    array(
                        'type' => 'file',
                        'label' => $this->l('Public Pem'),
                        'name' => 'PGL_PUBLIC_PEM',
                        'required' => true,
                        'desc' => $this->l('Upload a public pem file which is provided by payglocal.'),
                    ),
                    array(
                        'type' => 'file',
                        'label' => $this->l('Private Pem'),
                        'name' => 'PGL_PRIVATE_PEM',
                        'required' => true,
                        'desc' => $this->l('Upload a private pem file which is provided by payglocal.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
        return $fields_form;
    }

    protected function getPayGlFormValues()
    {
        return array(
            'PGL_PAYMENT_MODE' => Tools::getValue('PGL_PAYMENT_MODE', Configuration::get('PGL_PAYMENT_MODE')),
            'PGL_SHOW_LOGO' => Tools::getValue('PGL_SHOW_LOGO', Configuration::get('PGL_SHOW_LOGO')),
            'PGL_MERCHANT_ID' => Tools::getValue('PGL_MERCHANT_ID', Configuration::get('PGL_MERCHANT_ID')),
            'PGL_PUBLIC_KID' => Tools::getValue('PGL_PUBLIC_KID', Configuration::get('PGL_PUBLIC_KID')),
            'PGL_PRIVATE_KID' => Tools::getValue('PGL_PRIVATE_KID', Configuration::get('PGL_PRIVATE_KID')),
            'PGL_PUBLIC_PEM' => Tools::getValue('PGL_PUBLIC_PEM', Configuration::get('PGL_PUBLIC_PEM')),
            'PGL_PRIVATE_PEM' => Tools::getValue('PGL_PRIVATE_PEM', Configuration::get('PGL_PRIVATE_PEM')),
        );
    }

    protected function validPayGlFormPostValues()
    {
        $pgl_merchant_id = Tools::getValue('PGL_MERCHANT_ID');
        $pgl_public_kid = Tools::getValue('PGL_PUBLIC_KID');
        $pgl_private_kid = Tools::getValue('PGL_PRIVATE_KID');
        $pgl_public_pem = Tools::getValue('PGL_PUBLIC_PEM');
        $pgl_private_pem = Tools::getValue('PGL_PRIVATE_PEM');

        if (Tools::isEmpty($pgl_merchant_id)) {
            $this->errors[] = $this->l('Merchant id is required.');
        }
        if (Tools::isEmpty($pgl_public_kid)) {
            $this->errors[] = $this->l('Public kid is required.');
        }
        if (Tools::isEmpty($pgl_private_kid)) {
            $this->errors[] = $this->l('Private kid is required.');
        }

        if (Tools::isEmpty($this->pgl_public_pem)) {
            if (Tools::isEmpty($_FILES['PGL_PUBLIC_PEM']['tmp_name'])) {
                $this->errors[] = $this->l('Public pem file is required.');
            }
        }
        if (Tools::isEmpty($this->pgl_private_pem)) {
            if (Tools::isEmpty($_FILES['PGL_PRIVATE_PEM']['tmp_name'])) {
                $this->errors[] = $this->l('Private pem file is required.');
            }
        }
    }

    protected function savePayGlFormPostValues()
    {
        Configuration::updateValue('PGL_PAYMENT_MODE', Tools::getValue('PGL_PAYMENT_MODE'));
        Configuration::updateValue('PGL_SHOW_LOGO', Tools::getValue('PGL_SHOW_LOGO'));
        Configuration::updateValue('PGL_MERCHANT_ID', trim(Tools::getValue('PGL_MERCHANT_ID')));
        Configuration::updateValue('PGL_PUBLIC_KID', trim(Tools::getValue('PGL_PUBLIC_KID')));
        Configuration::updateValue('PGL_PRIVATE_KID', trim(Tools::getValue('PGL_PRIVATE_KID')));

        $pgl_public_pem_name = $_FILES['PGL_PUBLIC_PEM']['name'];
        if (!Tools::isEmpty($pgl_public_pem_name)) {
            if (file_exists(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_public_pem)) {
                Tools::deleteFile(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_public_pem);
            }
            if (move_uploaded_file($_FILES['PGL_PUBLIC_PEM']['tmp_name'], _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $pgl_public_pem_name)) {
                Configuration::updateValue('PGL_PUBLIC_PEM', $pgl_public_pem_name);
            } else {
                $this->output .= $this->displayError($this->l('An error occurred while attempting to upload the public pem file.'));
            }
        }

        $pgl_private_pem_name = $_FILES['PGL_PRIVATE_PEM']['name'];
        if (!Tools::isEmpty($pgl_private_pem_name)) {
            if (file_exists(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_private_pem)) {
                Tools::deleteFile(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_private_pem);
            }
            if (move_uploaded_file($_FILES['PGL_PRIVATE_PEM']['tmp_name'], _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $pgl_private_pem_name)) {
                Configuration::updateValue('PGL_PRIVATE_PEM', $pgl_private_pem_name);
            } else {
                $this->output .= $this->displayError($this->l('An error occurred while attempting to upload the private pem file.'));
            }
        }

        $this->output .= $this->displayConfirmation($this->l('General settings are updated.'));
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (Tools::isEmpty($this->pgl_merchant_id) || Tools::isEmpty($this->pgl_public_kid) || Tools::isEmpty($this->pgl_private_kid) || Tools::isEmpty($this->pgl_public_pem) || Tools::isEmpty($this->pgl_private_pem)) {
            return;
        }

        $payment_options = [
            $this->getPayGlPaymentOption(),
        ];

        return $payment_options;
    }

    protected function getPayGlPaymentOption()
    {
        if ($this->pgl_show_logo) {
            $logo = Media::getMediaPath(_PS_MODULE_DIR_ . 'payglocal/views/img/payglocal.png');
        } else {
            $logo = "";
        }
        $payGlOption = new PaymentOption();
        $payGlOption->setCallToActionText($this->l('Pay by PayGlocal'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setLogo($logo)
            ->setAdditionalInformation($this->context->smarty->fetch('module:payglocal/views/templates/hook/paymentinfo.tpl'));

        return $payGlOption;
    }

    public function createPayGlSPaymentUrl($cart)
    {
        $jweToken = $this->createPayGlSPaymentJweToken($cart);
        $jwsToken = $this->createPayGlSPaymentJwsToken($jweToken);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->pgl_payment_endpoint . '/gl/v1/payments/initiate/paycollect',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jweToken,
            CURLOPT_HTTPHEADER => array(
                'x-gl-token-external: ' . $jwsToken,
                'Content-Type: text/plain'
            ),
        ));
        
        $response = curl_exec($curl);
        $data = json_decode($response, true);
        curl_close($curl);
        
        return $data;
    }

    protected function createPayGlSPaymentJweToken($cart)
    {
        $keyEncryptionAlgorithmManager = new AlgorithmManager([
            new RSAOAEP256(),
        ]);
        $contentEncryptionAlgorithmManager = new AlgorithmManager([
            new A128CBCHS256(),
        ]);
        $compressionMethodManager = new CompressionMethodManager([
            new Deflate(),
        ]);
        $jweBuilder = new JWEBuilder(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );

        $key = JWKFactory::createFromKeyFile(
                _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_public_pem,
                null,
                [
                    'kid' => $this->pgl_public_kid,
                    'use' => 'enc',
                    'alg' => 'RSA-OAEP-256',
                ]
        );
        $header = [
            'issued-by' => $this->pgl_merchant_id,
            'enc' => 'A128CBC-HS256',
            'exp' => 30000,
            'iat' => (string) round(microtime(true) * 1000),
            'alg' => 'RSA-OAEP-256',
            'kid' => $this->pgl_public_kid,
        ];

        $payload = $this->createPayGlSPaymentDatas($cart);

        $jwe = $jweBuilder
            ->create()
            ->withPayload($payload)
            ->withSharedProtectedHeader($header)
            ->addRecipient($key)
            ->build();

        $serializer = new CompactSerializer();
        $token = $serializer->serialize($jwe, 0);

        return $token;
    }

    protected function createPayGlSPaymentJwsToken($jweToken)
    {
        $algorithmManager = new AlgorithmManager([
            new RS256(),
        ]);

        $jwsBuilder = new JWSBuilder(
            $algorithmManager
        );

        $jwskey = JWKFactory::createFromKeyFile(
                _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_private_pem,
                null,
                [
                    'kid' => $this->pgl_private_kid,
                    'use' => 'sig'
                //'alg' => 'RSA-OAEP-256',
                ]
        );

        $jwsheader = [
            'issued-by' => $this->pgl_merchant_id,
            'is-digested' => 'true',
            'alg' => 'RS256',
            'x-gl-enc' => 'true',
            'x-gl-merchantId' => $this->pgl_merchant_id,
            'kid' => $this->pgl_private_kid
        ];

        $hashedPayload = base64_encode(hash('sha256', $jweToken, $BinaryOutputMode = true));

        $jwspayload = json_encode([
            'digest' => $hashedPayload,
            'digestAlgorithm' => "SHA-256",
            'exp' => 300000,
            'iat' => (string) round(microtime(true) * 1000)
        ]);

        $jws = $jwsBuilder
            ->create()
            ->withPayload($jwspayload)
            ->addSignature($jwskey, $jwsheader)
            ->build();

        $jwsserializer = new \Jose\Component\Signature\Serializer\CompactSerializer(); // The serializer
        $jwstoken = $jwsserializer->serialize($jws, 0);

        return $jwstoken;
    }

    protected function createPayGlSPaymentDatas($cart)
    {
        $customer = new Customer($cart->id_customer);
        $currency = new Currency($cart->id_currency);
        $invoice_address = new Address($cart->id_address_invoice);
        $delivery_address = new Address($cart->id_address_delivery);
        $invoice_country = new Country($invoice_address->id_country);
        $delivery_country = new Country($delivery_address->id_country);
        $invoice_phone = $invoice_address->phone;
        $delivery_phone = $delivery_address->phone;
        if(empty($invoice_phone)) {
            $invoice_phone = $invoice_address->phone_mobile;
        }
        if(empty($delivery_phone)) {
            $delivery_phone = $delivery_address->phone_mobile;
        }
        
        $products = array();
        foreach ($cart->getProducts() as $product) {
            $data = [
                'productDescription' => $product['name'],
                'itemUnitPrice' => $product['total_wt'],
                'itemQuantity' => $product['quantity']
            ];
            $products[] = $data;
        }
        
        $payload = [
            "merchantTxnId" => $cart->id . '#' . $this->createPayGlSRandomString(16),
            "merchantUniqueId" => $cart->id . '#' . $this->createPayGlSRandomString(16),
            "paymentData" => array(
                "totalAmount" => $cart->getOrderTotal(true, Cart::BOTH),
                "txnCurrency" => $currency->iso_code,
                "billingData" => array(
                    "firstName" => $customer->firstname,
                    "lastName" => $customer->lastname,
                    "addressStreet1" => $invoice_address->address1,
                    "addressStreet2" => isset($invoice_address->address2) ? $invoice_address->address2 : "",
                    "addressCity" => $invoice_address->city,
                    "addressState" => $invoice_address->id_state ? State::getNameById($invoice_address->id_state) : "",
                    "addressPostalCode" => $invoice_address->postcode,
                    "addressCountry" => $invoice_country->iso_code,
                    "emailId" => $customer->email,
                    "callingCode" => $invoice_country->call_prefix,
                    "phoneNumber" => $invoice_phone,
                )
            ),
            "riskData" => array(
                "orderData" => $products,
                "customerData" => array(
                    //"merchantAssignedCustomerId" => $customer->id,
                    //"customerAccountType" => "1",
                    //"customerAccountCreationDate" => date_create($customer->date_add),
                    "ipAddress" => Tools::getRemoteAddr(),
                    "httpAccept" => isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : null,
                    "httpUserAgent" => $_SERVER['HTTP_USER_AGENT'],
                ),
                "shippingData" => array(
                    "firstName" => $customer->firstname,
                    "lastName" => $customer->lastname,
                    "addressStreet1" => $delivery_address->address1,
                    "addressStreet2" => isset($delivery_address->address2) ? $delivery_address->address2 : "",
                    "addressCity" => $delivery_address->city,
                    "addressState" => $delivery_address->id_state ? State::getNameById($delivery_address->id_state) : "",
                    "addressPostalCode" => $delivery_address->postcode,
                    "addressCountry" => $delivery_country->iso_code,
                    "emailId" => $customer->email,
                    "callingCode" => $delivery_country->call_prefix,
                    "phoneNumber" => $delivery_phone,
                )
            ),
            "clientPlatformDetails" => array(
                "platformName" => "Prestashop",
                "platformVersion" => _PS_VERSION_,
            ),
            "merchantCallbackURL" => $this->context->link->getModuleLink($this->name, 'response', array(), true)
        ];
        
        return json_encode($payload);
    }

    protected function createPayGlSRandomString($length = 16)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function verifyPayGlSPayment($token)
    {
        $algorithmManager = new AlgorithmManager([
            new RS256(),
        ]);
        $jwsVerifier = new JWSVerifier(
            $algorithmManager
        );
        $jwk = JWKFactory::createFromKeyFile(
                _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $this->pgl_public_pem,
                null,
                [
                    'kid' => $this->pgl_public_kid,
                    'use' => 'sig'
                //'alg' => 'RSA-OAEP-256',
                ]
        );
        $serializerManager = new JWSSerializerManager([
            new SigCompactSerializer(),
        ]);

        $jws = $serializerManager->unserialize($token);
        $isVerified = $jwsVerifier->verifyWithKey($jws, $jwk, 0);

        $headerCheckerManager = $payload = null;

        $jwsLoader = new JWSLoader(
            $serializerManager,
            $jwsVerifier,
            $headerCheckerManager
        );

        $jws = $jwsLoader->loadAndVerifyWithKey($token, $jwk, $signature, $payload);

        return json_decode($jws->getPayload(), true);
    }
    
     public function setPayGlSOrderPaymentData($id_order, $paymentdata)
    {
        $result = $this->getPayGlSOrderPaymentData($id_order);
        if ($result) {
            $data = array(
                'id_order' => (int) $id_order,
                'gid' => isset($paymentdata['gid']) ? pSQL($paymentdata['gid']) : '',
                'merchantid' => isset($paymentdata['merchantUniqueId']) ? pSQL($paymentdata['merchantUniqueId']) : '',
                'iat' => isset($paymentdata['iat']) ? pSQL($paymentdata['iat']) : '',
            );
            Db::getInstance()->update('payglocal', $data, 'id_order = ' . (int) $id_order);
        } else {
            $data = array(
                'id_order' => (int) $id_order,
                'gid' => isset($paymentdata['gid']) ? pSQL($paymentdata['gid']) : '',
                'merchantid' => isset($paymentdata['merchantUniqueId']) ? pSQL($paymentdata['merchantUniqueId']) : '',
                'iat' => isset($paymentdata['iat']) ? pSQL($paymentdata['iat']) : '',
            );
            Db::getInstance()->insert('payglocal', $data);
        }
    }
    
    protected function getPayGlSOrderPaymentData($id_order)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'payglocal WHERE id_order = ' . (int) $id_order;
        $result = Db::getInstance()->getRow($sql);
        return $result;
    }
    
    public function hookDisplayPaymentReturn($params)
    {
        $id_order = $params['order']->id;
        $paymentdata = $this->getPayGlSOrderPaymentData($id_order);
        if ($paymentdata) {
            $this->context->smarty->assign(array(
                'paymentdata' => $paymentdata,
            ));
            return $this->context->smarty->fetch('module:payglocal/views/templates/hook/displayPaymentReturn.tpl');
        }
    }

    public function hookDisplayOrderDetail($params)
    {
        $id_order = $params['order']->id;
        $paymentdata = $this->getPayGlSOrderPaymentData($id_order);
        if ($paymentdata) {
            $this->context->smarty->assign(array(
                'paymentdata' => $paymentdata,
            ));
            return $this->context->smarty->fetch('module:payglocal/views/templates/hook/displayOrderDetail.tpl');
        }
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        $id_order = $params['id_order'];
        $paymentdata = $this->getPayGlSOrderPaymentData($id_order);
        if ($paymentdata) {
            $this->context->smarty->assign(array(
                'paymentdata' => $paymentdata,
            ));
            return $this->context->smarty->fetch('module:payglocal/views/templates/hook/displayAdminOrderLeft.tpl');
        }
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        $id_order = $params['id_order'];
        $paymentdata = $this->getPayGlSOrderPaymentData($id_order);
        if ($paymentdata) {
            return $this->context->smarty->fetch('module:payglocal/views/templates/hook/displayAdminOrderTabLink.tpl');
        }
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        $id_order = $params['id_order'];
        $paymentdata = $this->getPayGlSOrderPaymentData($id_order);
        if ($paymentdata) {
            $this->context->smarty->assign(array(
                'paymentdata' => $paymentdata,
            ));
            return $this->context->smarty->fetch('module:payglocal/views/templates/hook/displayAdminOrderTabContent.tpl');
        }
    }
}
