<?php

use Estandarte\Pagseguro\UpdateOrder;

/**
* 2018 Estandarte
*
*  @author    Leandro Chaves <leandrorchaves@gmail.com>
*  @copyright 2018 Estandarte
*/
class PagSeguroNotifyModuleFrontController extends ModuleFrontController
{

    /**
     * @see FrontController::initContent()
     */
    public function init()
    {
        header("access-control-allow-origin: *");
        \PagSeguro\Library::initialize();
        $this->configure();
        parent::init();
        // $this->setTemplate('_blank.tpl');
    }
    public function postProcess()
       {
        if (\PagSeguro\Helpers\Xhr::hasPost()) {
            $response = \PagSeguro\Services\Transactions\Notification::check(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );
            $update = new UpdateOrder();
            $update->updateStatus($response);
            http_response_code(201);
            die;
        } else {
            throw new \InvalidArgumentException($_POST);
        }
    }
    protected function configure(){
        \PagSeguro\Configuration\Configure::setEnvironment(\Configuration::get('PAGSEGURO_SANDBOX') ?'sandbox' : 'production');
        \PagSeguro\Configuration\Configure::setCharset('UTF-8');
        \PagSeguro\Configuration\Configure::setAccountCredentials(
            \Configuration::get('PAGSEGURO_ACCOUNT_EMAIL'),
            \Configuration::get('PAGSEGURO_ACCOUNT_PUBLICKEY')
        );
        \PagSeguro\Configuration\Configure::setLog(false, null);

    }

}
