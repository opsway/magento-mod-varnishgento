<?php
/**
 * Rewrite for standard html block
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class OpsWay_Varnishgento_Block_Html extends Mage_Page_Block_Html
{

    public function getAbsoluteFooter()
    {
        $html = parent::getAbsoluteFooter();
        return $html . Mage::helper('opsway_varnishgento')->showCurrentNode();
    }

}