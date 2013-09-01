<?php
/**
 * Rewrite Magento Index Process for flushing varnish after reindex
 *
 * @category Opsway
 * @package  Opsway_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class Opsway_Varnishgento_Model_Index_Process extends Mage_Index_Model_Process
{
    /**
     * Reindex all data what this process responsible is
     *
     * @return void
     */
    public function reindexAll()
    {
        parent::reindexAll();

        if (Mage::getStoreConfig('opsway_varnishgento/general/flushall') == 1) {
            // Send event to varnigento to clean pages
            Mage::dispatchEvent('application_clean_cache', array('tags' =>array('.*')));
            Mage::getSingleton('opsway_varnishgento/processor')->cleanCache();
        }
    }
}
