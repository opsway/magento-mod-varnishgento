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
        if ($this->isLocked()) {
            Mage::throwException(
                Mage::helper('index')->__(
                    '%s Index process is working now. Please try run this process later.',
                    $this->getIndexer()->getName()
                )
            );
        }
        $this->_getResource()->startProcess($this);
        $this->lock();
        $this->getIndexer()->reindexAll();
        $this->unlock();
        $this->_getResource()->endProcess($this);

        if (Mage::getStoreConfig('opsway_varnishgento/general/flushall') == 1) {
            // Send event to varnigento to clean pages
            Mage::dispatchEvent('application_clean_cache', array('tags' =>array('.*')));
            Mage::getSingleton('opsway_varnishgento/processor')->cleanCache();
        }
    }
}