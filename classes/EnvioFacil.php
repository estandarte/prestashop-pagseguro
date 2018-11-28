<?php
namespace Estandarte\Pagseguro;

class EnvioFacil
{
    public $api = 'https://pagseguro.uol.com.br/para-seu-negocio/online/envio-facil';
    private static $cacheEnvios = [];
    public function calculate($params)
    {
        $cacheID = 'enviofacil_' . md5(var_export($params, true));
        if ($freight = self::getCache($cacheID)) {
            return $freight;
        } else {
            self::setCache($cacheID, $freight = $this->request($params));
            return $freight;
        }

    }
    /**
     * Request to the api the freight values
     * @param [] $params
     **/
    public function request($params){
        $req = curl_init();
        curl_setopt( $req, CURLOPT_URL, $this->api );
        curl_setopt( $req, CURLOPT_POST, true );
        curl_setopt( $req, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
        curl_setopt( $req, CURLOPT_POSTFIELDS, json_encode( $params ) );
        curl_setopt( $req, CURLOPT_RETURNTRANSFER, true );
        $res = curl_exec( $req );
        curl_close( $req );
        return json_decode( $res );
    }
    public static function getCache($cacheID)
    {
        $data = false;
        if (isset(self::$cacheEnvios[$cacheID]) && ($data = self::$cacheEnvios[$cacheID])) {
            return $data;
        }
        if (defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_) {
            $cache = Cache::getInstance();
            if ($data = $cache->get($cacheID)) {
                return $data;
            }
        }
        return $data;
    }
    public static function setCache($cacheID, $value, $session = 600)
    {
        self::$cacheEnvios[$cacheID] = $value;
        if (defined('_PS_CACHE_ENABLED_') &&
    _PS_CACHE_ENABLED_) {
            $cache = Cache::getInstance();
            if ($cache->set($cacheID, $value, $session)) {
                return true;
            }
        }
    }
}
