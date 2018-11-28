<?php
/**
* 2018 Estandarte
*
*  @author    Leandro Chaves <leandrorchaves@gmail.com>
*  @copyright 2018 Estandarte
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

include dirname(__FILE__).'/vendor/autoload.php';
include dirname(__FILE__).'/classes/EnvioFacil.php';

class PagSeguro extends PaymentModule
{
    protected $config_form = false;
    public $id_carrier;
    public function __construct()
    {
        $this->name = 'PagSeguro';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->author = 'Estandarte';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Pag Seguro');
        $this->description = $this->l('Habilite o pagamento com PagSeguro em sua loja Prestashop!');

        $this->confirmUninstall = $this->l('Tem certeza que quer desinstalar esse módulo?');

        $this->limited_countries = array('BR');

        $this->limited_currencies = array('BRL');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if ( in_array($iso_code, $this->limited_countries) == false )
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        Configuration::updateValue('PAGSEGURO_ACTIVATE', false);

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayOrderDetail') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('displayPayment');
    }

    public function uninstall()
    {
        Configuration::deleteByName('PAGSEGURO_ACTIVATE');
        Configuration::deleteByName('PAGSEGURO_ACCOUNT_EMAIL');
        Configuration::deleteByName('PAGSEGURO_ACCOUNT_PUBLICKEY');
        Configuration::deleteByName('PAGSEGURO_CARRIER');
        Configuration::deleteByName('PAGSEGURO_CARRIER_0');
        Configuration::deleteByName('PAGSEGURO_CARRIER_1');
        Configuration::deleteByName('PAGSEGURO_CARRIER_CEP');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitPagSeguroModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPagSeguroModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Activate'),
                        'name' => 'PAGSEGURO_ACTIVATE',
                        'is_bool' => true,
                        'desc' => $this->l('Activate payment with PagSeguro'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        // 'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'PAGSEGURO_ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'PAGSEGURO_ACCOUNT_PUBLICKEY',
                        'label' => $this->l('Public Key'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Activate Carrier'),
                        'name' => 'PAGSEGURO_CARRIER',
                        'is_bool' => true,
                        'desc' => $this->l('Activate PagSeguro Carrier'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'PAGSEGURO_CARRIER_CEP',
                        'label' => $this->l('CEP do Remetente'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'PAGSEGURO_ACTIVATE' => Configuration::get('PAGSEGURO_ACTIVATE', true),
            'PAGSEGURO_ACCOUNT_EMAIL' => Configuration::get('PAGSEGURO_ACCOUNT_EMAIL', TRUE),
            'PAGSEGURO_ACCOUNT_PUBLICKEY' => Configuration::get('PAGSEGURO_ACCOUNT_PUBLICKEY', null),
            'PAGSEGURO_CARRIER' => Configuration::get('PAGSEGURO_CARRIER', false),
            'PAGSEGURO_CARRIER_CEP' => Configuration::get('PAGSEGURO_CARRIER_CEP', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
        if (Tools::getValue("PAGSEGURO_CARRIER")){
            $this->installCarrier();
        } else {
            $this->removeCarrier();
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (!in_array($currency->iso_code, $this->limited_currencies) || !Configuration::get('PAGSEGURO_ACTIVATE')) {
            return false;
        }

        $this->smarty->assign('module_dir', $this->_path);

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return;
        }

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'ok');
        }

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    public function hookDisplayPayment()
    {
        /* Place your code here. */
    }
    public function installCarrier()
    {
        $custom_text = 'PagSeguro Envio Fácil';
        $carrierConfig = array(
            0 => array('name' => 'PAC',
                'carrier_code' => 'PAC',
                'id_tax_rules_group' => 0,
                'active' => true,
                'deleted' => 0,
                'shipping_handling' => false,
                'range_behavior' => 0,
                'delay' => '',
                'id_zone' => 1,
                'is_module' => true,
                'shipping_external' => true,
                'external_module_name' => $this->name,
                'need_range' => true,
            ),
            1 => array('name' => 'SEDEX',
                'carrier_code' => 'SEDEX',
                'id_tax_rules_group' => 0,
                'active' => true,
                'deleted' => 0,
                'shipping_handling' => false,
                'range_behavior' => 0,
                'delay' => '',
                'id_zone' => 1,
                'is_module' => true,
                'shipping_external' => true,
                'external_module_name' => $this->name,
                'need_range' => true,
            ),
        );

        Configuration::updateValue('PAGSEGURO_CARRIER_0', $this->installExternalCarrier($carrierConfig[0]));
        Configuration::updateValue('PAGSEGURO_CARRIER_1', $this->installExternalCarrier($carrierConfig[1]));
    }
    public static function installExternalCarrier($config)
    {
        $carrier = new Carrier();
        $carrier->name = $config['name'];
        $carrier->id_tax_rules_group = $config['id_tax_rules_group'];
        $carrier->id_zone = $config['id_zone'];
        $carrier->active = $config['active'];
        $carrier->deleted = $config['deleted'];
        $carrier->delay[Configuration::get('PS_LANG_DEFAULT')] = 0;
        $carrier->shipping_handling = $config['shipping_handling'];
        $carrier->range_behavior = $config['range_behavior'];
        $carrier->is_module = $config['is_module'];
        $carrier->shipping_external = $config['shipping_external'];
        $carrier->external_module_name = $config['external_module_name'];
        $carrier->need_range = $config['need_range'];

        // $languages = Language::getLanguages(true);
        // foreach ($languages as $language) {
        //     if ($language['iso_code'] == 'br') {
        //         $carrier->delay[(int) $language['id_lang']] = $config['delay'][$language['iso_code']];
        //     } elseif ($language['iso_code'] == 'es') {
        //         $carrier->delay[(int) $language['id_lang']] = $config['delay'][$language['iso_code']];
        //     } elseif ($language['iso_code'] == Language::getIsoById(Configuration::get('PS_LANG_DEFAULT'))) {
        //         $carrier->delay[(int) $language['id_lang']] = $config['delay'][$language['iso_code']];
        //     }
        // }

        if ($carrier->add()) {
            $groups = Group::getGroups(true);
            foreach ($groups as $group) {
                Db::getInstance()->insert(
                    'carrier_group',
                    array('id_carrier' => (int) ($carrier->id),
                    'id_group' => (int) ($group['id_group']),
                    )
                );
            }

            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '30.000';
            $rangeWeight->add();

            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                Db::getInstance()->insert(
                    'carrier_zone',
                    array('id_carrier' => (int) ($carrier->id),
                        'id_zone' => (int) ($zone['id_zone']),
                    )
                );
                Db::getInstance()->insert(
                    'delivery',
                    array('id_carrier' => (int) ($carrier->id),
                        'id_range_price' => (int) ($rangePrice->id),
                        'id_range_weight' => null,
                        'id_zone' => (int) ($zone['id_zone']),
                        'price' => '0',
                    ),
                    null
                );
                Db::getInstance()->insert(
                    'delivery',
                    array('id_carrier' => (int) ($carrier->id),
                        'id_range_price' => null,
                        'id_range_weight' => (int) ($rangeWeight->id),
                        'id_zone' => (int) ($zone['id_zone']),
                        'price' => '0',
                    ),
                    null
                );
            }

            // Copy Logo
            @copy(dirname(__FILE__).'/views/img/carrier.jpg', _PS_SHIP_IMG_DIR_.'/'.(int) $carrier->id.'.jpg');

            // Return ID Carrier
            return (int) ($carrier->id);
        }

        return false;
    }
    private function removeCarrier(){
        foreach (['PAGSEGURO_CARRIER_0','PAGSEGURO_CARRIER_1'] as $value) {
            $id = Configuration::get($value, false);
            if($id){
                $carrier = new Carrier($id);
                $carrier->deleted = true;
                $carrier->active = false;
                $carrier->save();
            }
        }
    }
    public function getPackageShippingCost($params, $shipping_cost,  $products)
    {
        $carriers = [
            Configuration::get('PAGSEGURO_CARRIER_0') => 'PAC',
            Configuration::get('PAGSEGURO_CARRIER_1') => 'SEDEX'
        ];
        if (!in_array($this->id_carrier, array_keys($carriers))) {
            return false;
        }
        if ($retorno = $this->calculate($params, $carriers[$this->id_carrier])){
            return (float)$retorno;
        }

        return false;
    }
    private function calculate($params, $type)
    {

        $cart = Context::getContext()->cart;
        $price_total = 0;

        // Init var
        $address = new Address($params->id_address_delivery);
        $products = $cart->getProducts();

        $dimensions = $this->getDimensions($products);
        $postcode = $address->postcode;

        $return = null;
        $dimensions['cepFrom'] = Configuration::get('PAGSEGURO_CARRIER_CEP');
        $dimensions['cepTo'] = $postcode;

        $cacheID = 'pagseguro_carrier_'.md5(var_export($dimensions, true));

        $carrier = new Estandarte\Pagseguro\EnvioFacil();
        $options = $carrier->calculate($dimensions);
        if (!is_array($options)) {
            return false;
        }
        foreach ($options as $id => $option) {
            if ( $type == $option->serviceType) {
                return $option->totalValue;
            }
        }

        return $return;
    }
    public function getDimensions(&$products)
    {
        $width = 0;
        $height = 0;
        $depth = 0;
        $weight = 0;

        foreach ($products as &$product) {
            $product['weight2'] = $product['weight'] ? $product['weight']  : 0;
            $product['width2'] = $product['width'];
            $product['height2'] = $product['height'];
            $product['depth2'] = $product['depth'];
        }
        if (Configuration::get('shipping_calc_mode') == 'longer_side') {
            foreach ($products as $p) {
                if ($p['width2'] && $p['width2'] > $width) {
                    $width = $p['width2'];
                }
                if ($p['height2'] && $p['height2'] > $height) {
                    $height = $p['height2'];
                }
                if ($p['depth2'] && $p['depth2'] > $depth) {
                    $depth = $p['depth2'];
                }
                if ($p['weight2']) {
                    $weight += ($p['weight2'] * $p['quantity']);
                } else {
                    $weight += $this->config['default_weight'];
                }
            }
        } else {
            foreach ($products as $p) {
                $dimensions = array(0, 0, 0);
                $dimensions[0] = $p['width2'] > 0.01 ? $p['width2'] : Configuration::get('default_width');
                $dimensions[1] = $p['height2'] > 0.01 ? $p['height2'] : Configuration::get('default_height');
                $dimensions[2] = $p['depth2'] > 0.01 ? $p['depth2'] : Configuration::get('default_depth');
                sort($dimensions);
                for ($i = 0; $i < $p['quantity']; ++$i) {
                    $width = max($width, $dimensions[1]);
                    $height = max($height, $dimensions[2]);
                    $depth += $dimensions[0];
                    $sort_dim = array( $width, $height, $depth );
                    sort($sort_dim);
                    $depth = $sort_dim[0];
                    $height = $sort_dim[1];
                    $width = $sort_dim[2];
                }
                $weight += ($p['weight2'] > 0.1 ? $p['weight2'] : Configuration::get('default_weight')) * $p['quantity'];
            }
        }

        return array(
            'width' => (string) max((int)Tools::ps_round($width, 0), 11),
            'height' => (string) max((int)Tools::ps_round($height, 0), 2),
            'length' =>(string) max((int)Tools::ps_round($depth, 0), 16),
            'weight' => $weight//(int)Tools::ps_round($weight, 0)
        );
    }
}
