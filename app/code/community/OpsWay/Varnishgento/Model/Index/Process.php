<?php
/**
 * Rewrite Magento Index Process for flushing varnish after reindex
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class OpsWay_Varnishgento_Model_Index_Process extends Mage_Index_Model_Process
{
    /**
     * Reindex all data what this process responsible is
     *
     * @return void
     */
    public function reindexAll()
    {
        parent::reindexAll();

        if (Mage::getStoreConfig('opsway_varnishgento/flushing/after_reindex') == 1) {
            // Send event to varnigento to clean pages
            Mage::dispatchEvent('application_clean_cache', array('tags' =>array('.*')));
            Mage::getSingleton('opsway_varnishgento/processor')->cleanCache();
        }
    }
}
