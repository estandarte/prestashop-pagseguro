<?php
namespace Estandarte\Pagseguro;

use PagSeguro\Parsers\Transaction\Response;
class UpdateOrder
{
    public function updateStatus(Response $response)
    {
        $order = new \Order(str_replace('PS','', $response->getReference()));
        if($order){
            $order->setCurrentState(\Configuration::get('PAGSEGURO_STATUS_'.$response->getStatus()));
            return $order;
        }
        return null;
    }
}
