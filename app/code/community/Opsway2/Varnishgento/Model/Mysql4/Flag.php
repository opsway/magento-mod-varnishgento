<?php
/**
 * Mysql model for queue db table
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class OpsWay_Varnishgento_Model_Mysql4_Flag extends Mage_Core_Model_Mysql4_Abstract
{
    protected $_result = false;

    protected function _construct()
    {
        $this->_init('opsway_varnishgento/flag', 'flag_id');
    }
}