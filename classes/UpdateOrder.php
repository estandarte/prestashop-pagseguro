<?php
namespace Estandarte\Pagseguro;

use PagSeguro\Parsers\Transaction\Response;
class UpdateOrder
{
    public function updateStatus(Response $response)
    {
        $order = \Order::getByReference(explode('-',  $response->getReference())[1])->getFirst();
        if($order){
            $order->setCurrentState(\Configuration::get('PAGSEGURO_STATUS_'.$response->getStatus()));
            return $order;
        }
        return null;
    }
}
