<?php
/**
 * Block for add flush button in admin
 *
 * @category Opsway
 * @package  Opsway_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class Opsway_Varnishgento_Block_Adminhtml_Additional extends Mage_Adminhtml_Block_Template
{
    /**
     * Get url for clean varnish cache
     */
    public function getFlushVarnishUrl()
    {
        return $this->getUrl('*/*/flushVarnish');
    }
    
}