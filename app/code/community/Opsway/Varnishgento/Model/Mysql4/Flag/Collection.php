<?php
/**
 * Mysql model collection for queue db table
 *
 * @category Opsway
 * @package  Opsway_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class Opsway_Varnishgento_Model_Mysql4_Flag_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
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

}