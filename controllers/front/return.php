<?php

use Estandarte\Pagseguro\UpdateOrder;

/**
* 2018 Estandarte
*
*  @author    Leandro Chaves <leandrorchaves@gmail.com>
*  @copyright 2018 Estandarte
*/
class PagSeguroReturnModuleFrontController extends ModuleFrontController
{

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        \PagSeguro\Library::initialize();
        $this->configure();
        $code = Tools::getValue('transaction');
        $response = \PagSeguro\Services\Transactions\Search\Code::search(
                \PagSeguro\Configuration\Configure::getAccountCredentials(),
                $code
            );
        $update = new UpdateOrder();
        $order = $update->updateStatus($response);
        $uri = __PS_BASE_URI__.'order-confirmation.php?id_cart='.$order->id_cart;
        \Tools::redirectLink($uri);

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

}
