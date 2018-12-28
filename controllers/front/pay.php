<?php
define('PS_CONFIG_PATH', '');
define('PS_CONFIG', '');
/**
* 2018 Estandarte
*
*  @author    Leandro Chaves <leandrorchaves@gmail.com>
*  @copyright 2018 Estandarte
*/
class PagSeguroPayModuleFrontController extends ModuleFrontController
{
    protected $paymentMethod = '';
    public $ssl = true;
    public $display_column_left = false;
    private $settings = null;
    private $site_url = null;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $customer = Context::getContext()->customer->getFields();
        $cart = Context::getContext()->cart;

        $payment = new \PagSeguro\Domains\Requests\Payment();
        foreach ($cart->getProducts() as $key => $product) {
            $payment->addItems()->withParameters(
                $product['id_product'],
                $product['name'],
                $product['quantity'],
                $product['price_wt']
            );
        }
        $payment->setCurrency("BRL");
        $payment->setReference('PS' . $cart->id);
        $payment->setRedirectUrl($this->getReturnUrl());
        $payment->setNotificationUrl($this->getNotificationUrl());

        $payment->setSender()->setName(trim($customer['firstname']) . ' '.  trim($customer['lastname']));
        $payment->setSender()->setEmail($customer['email']);
        // $payment->setSender()->setPhone()->withParameters(
        //     11,
        //     56273440
        // );
        // $payment->setSender()->setDocument()->withParameters(
        //     'CPF',
        //     'insira um numero de CPF valido'
        // );
        $address = new Address((integer) $cart->id_address_invoice);
        $payment->setShipping()->setAddress()->withParameters(
            $address->address1,
            '',
            '',
            $address->postcode,
            $address->city,
            '',
            'BRA',
            $address->address2
        );
        $payment->setShipping()->setCost()->withParameters($cart->getOrderTotal(true, Cart::ONLY_SHIPPING));
        $carrier = Configuration::get('PAGSEGURO_CARRIER_1') == $cart->id_carrier ? \PagSeguro\Enum\Shipping\Type::SEDEX : \PagSeguro\Enum\Shipping\Type::PAC;
        $payment->setShipping()->setType()->withParameters($carrier);

        $this->configure();

        $url = $payment->register(
            \PagSeguro\Configuration\Configure::getAccountCredentials()
        );

        // Validate order
        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PAGSEGURO_STATUS_1'),
            (float)$cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName,
            NULL,
            [],
            (int)$this->context->currency->id,
            false,
            $customer['secure_key']
        );
        Tools::redirectLink($url);
    }
    protected function configure(){
        \PagSeguro\Configuration\Configure::setEnvironment(Configuration::get('PAGSEGURO_SANDBOX') ?'sandbox' : 'production');
        \PagSeguro\Configuration\Configure::setCharset('UTF-8');
        \PagSeguro\Configuration\Configure::setAccountCredentials(
            Configuration::get('PAGSEGURO_ACCOUNT_EMAIL'),
            Configuration::get('PAGSEGURO_ACCOUNT_PUBLICKEY')
        );
        \PagSeguro\Configuration\Configure::setLog(false, null);

    }
    protected function getReturnUrl(){
        return $this->context->link->getModuleLink(
            'pagseguro',
            'return'
        );
    }
    protected function getNotificationUrl(){
        return $this->context->link->getModuleLink(
            'pagseguro',
            'notify'
        );
    }

}
