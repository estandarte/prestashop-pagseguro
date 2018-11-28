<?php
/**
* 2018 Estandarte
*
*  @author    Leandro Chaves <leandrorchaves@gmail.com>
*  @copyright 2018 Estandarte
*/

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pagseguro` (
    `id_pagseguro` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_pagseguro`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
