<?php
require_once 'abstract.php';

/**
 * Script to flush varnish cache
 *
 * @category    OpsWay
 * @package     OpsWay_Shell
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */
class OpsWay_Shell_Varnishgento extends Mage_Shell_Abstract
{
    /**
     * Run script for flushing varnish cache
     */
    public function run()
    {
        $processor = Mage::getSingleton('opsway_varnishgento/processor');
        $this->purgeUrls($processor);

        if (!$processor->checkPurgeIsScheduled()){
            return;
        }
        try {
            $tagsToClean = $processor->getTagsFromQueue(false);
            if (empty($tagsToClean)) {
                return;
            }
            $processor->purgeTags($tagsToClean);
            Mage::dispatchEvent( 'splunk_log_default', array('metric' => 'varnishgento.flush_tags', 'value' => count($tagsToClean)) );
        } catch (OpsWay_Varnishgento_Model_Connector_Exception $e) {
            Mage::log(
                Mage::helper('opsway_varnishgento')->__('Error during cache clean. Reason: %s', $e->getMessage()),
                Zend_Log::ERR
            );
        } catch (Exception $e) {
            Mage::logException($e);
        }
        $processor->removeTagsFromQueue($tagsToClean);
    }

    /**
     * Flushing varnish cache by url from db table queue
     * @param $processor OpsWay_Varnishgento_Model_Processor
     */
    public function purgeUrls($processor)
    {
        $flags = Mage::getModel('opsway_varnishgento/flag')->getCollection();
        if ($flags->checkActiveFlag()) {
            foreach ($flags->getActivePurges() as $row)
            {
                if ($row['purge_url']) {
                    try{
                        $processor->purgeUrls(explode(",", $row['purge_url']));
                        if ($row['purge_url'] == OpsWay_Varnishgento_Model_Processor::FLUSH_ALL_PATTERN){
                            Mage::dispatchEvent( 'splunk_log_default', array('metric' => 'varnishgento.flush_all', 'value' => 1) );
                        } else {
                            Mage::dispatchEvent( 'splunk_log_default', array('metric' => 'varnishgento.flush_url', 'value' => 1) );
                        }
                    }catch (Exception $e){
                        Mage::logException($e);
                    }
                }
                $row->setFlushed(1)->save();
            }
        }
    }
}

$shell = new OpsWay_Shell_Varnishgento();
$shell->run();
