<?php
/**
 * Used in creating options for Yes|No config value selection
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class OpsWay_Varnishgento_Model_Source_Varnishversion
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 2, 'label'=>Mage::helper('adminhtml')->__('Version 2.x or less')),
            array('value' => 3, 'label'=>Mage::helper('adminhtml')->__('Version 3.0 or greater')),
        );
    }

}
