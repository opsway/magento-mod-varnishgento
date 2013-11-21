<?php
/**
 * Mysql model collection for queue db table
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class OpsWay_Varnishgento_Model_Mysql4_Flag_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
	protected function _construct()
	{
		$this->_init('opsway_varnishgento/flag');
	}

    /**
     * Select purge_ur and flushed field
     * @return $this
     */
    public function addActivePurgesFilters()
    {
        $this->addFieldToSelect(array('purge_url', 'flushed'))
             ->addFieldToFilter('flushed', array('eq', 0));
        return $this;
    }

    /**
     * Load only not flushed urls collection
     * @return $this
     */
    public function getActivePurges()
    {
        if ($this->isLoaded()) {
            return $this;
        }
        $this->addActivePurgesFilters();
        $this->load();
        return $this;
    }

    /**
     * Check exist not flushed urls
     * @return bool
     */
    public function checkActiveFlag() {
        if (!$this->isLoaded()) {
            $this->getActivePurges();
        }
        return $this->count() > 0;
    }

    public function isFlushAllActive(){
        if (!$this->isLoaded()) {
            $this->addFieldToSelect(array('purge_url', 'flushed'))
                 ->addFieldToFilter('flushed', array('eq', 0))
                 ->addFieldToFilter('purge_url',array('eq',OpsWay_Varnishgento_Model_Processor::FLUSH_ALL_PATTERN));
            $this->load();
        }
        return $this->count() > 0;
    }

}