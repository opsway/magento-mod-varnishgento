<?php
/**
 * Base observer model
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */
class OpsWay_Varnishgento_Model_Observer
{
    const XML_NODE_ALLOWED_CACHE = 'frontend/cache/varnishgento';

    /**
     * Enable or Disable flag for observer logic
     * @var null | bool
     */
    private  $isActive = null;

    /**
     * @var null | bool
     */
    private $isAllowToCache = null;

    /**
     * @var bool
     */
    private $_skipCatalogTags = false;
    /**
     * Is stock checked for products after order save. Prevent multiple cache flush for the same products.
     * @var bool
     */
    protected  $_isStockProcessed = false;

    /**
     * @var OpsWay_Varnishgento_Helper_Data
     */
    protected $_helper;

    public function __construct(){
        $this->_helper = Mage::helper('opsway_varnishgento');
    }


    /**
     * Check if module is active
     * @return bool
     */
    protected function _isActive()
    {
        $this->isActive = $this->_helper->isActive();
        if($this->isActive){
            //$this->isActive = $this->isAllowToCache(Mage::app()->getRequest());
        }
        return $this->isActive;
    }

    /**
     * Return ttl time to store in varnish
     * @param Zend_Controller_Request_Http $request
     * @return bool
     */
    public function isAllowToCache(Zend_Controller_Request_Http $request)
    {
        if (is_null($this->isAllowToCache)) {
            $this->isAllowToCache = false;
            $configuration = Mage::getConfig()->getNode(self::XML_NODE_ALLOWED_CACHE);
            if ($configuration) {
                $configuration = $configuration->asArray();
            }
            $module = $request->getModuleName();
            if (isset($configuration[$module])) {
                $model = $configuration[$module];
                $controller = $request->getControllerName();
                if (is_array($configuration[$module]) && isset($configuration[$module][$controller])) {
                    $model = $configuration[$module][$controller];
                    $action = $request->getActionName();
                    if (is_array($configuration[$module][$controller]) && isset($configuration[$module][$controller][$action])) {
                        $model = $configuration[$module][$controller][$action];
                    }
                }
                if (is_array($model) && isset($model['config']) && is_array($model['config'])) {
                    $this->isAllowToCache = true;
                    $conf = $model['config'];
                    $this->_skipCatalogTags = $conf['skipCatalogTags'] == 'true';
                }
            }
        }

        return $this->isAllowToCache;
    }

    /**
     * Set cache header
     * @param Varien_Event_Observer $obsever
     */
    public function setCacheHeader(Varien_Event_Observer $observer)
    {
        if (!$this->_isActive()) {
            return;
        }
        /**
         * @var $controller Mage_Core_Controller_Varien_Front
         */
        $controller = $observer->getEvent()->getFront();
        if ($controller->getResponse()->canSendHeaders()){
            $controller->getResponse()->setHeader(
                OpsWay_Varnishgento_Model_Processor::CACHE_HEADER_NAME,
                Mage::getSingleton('opsway_varnishgento/processor')->getCacheHeader($this->_skipCatalogTags)
            );
        }

    }

    /**
     * Clean cache
     * @param Varien_Event_Observer $observer
     */
    public function cleanCache(Varien_Event_Observer $observer)
    {
        if (!$this->_isActive()) {
            return;
        }
        Mage::getSingleton('opsway_varnishgento/processor')->cleanCache();
    }

    /**
     * Add model cache tage on load
     * @param Varien_Event_Observer $observer
     */
    public function addModelCacheTagsOnLoad(Varien_Event_Observer $observer)
    {
        $object = $observer->getEvent()->getObject();
        if (!$this->_isActive() || !$this->_helper->getCollectTags()) {
            return;
        }
        Mage::getSingleton('opsway_varnishgento/processor')->addModelCacheTags($object);
    }

    /**
     * Add collection cache tage on load
     * @param Varien_Event_Observer $observer
     */
    public function addCollectionCacheTagsOnLoad(Varien_Event_Observer $observer)
    {
        if (!$this->_isActive() || !$this->_helper->getCollectTags()) {
            return;
        }
        $collection = $observer->getEvent()->getCollection();
        foreach ($collection as $item) {
            Mage::getSingleton('opsway_varnishgento/processor')->addModelCacheTags($item);
        }
    }

    /**
     * Add category collection cache tage on load (yep-yep, the interface is not the same)
     * @param Varien_Event_Observer $observer
     */
    public function addCategoryCollectionCacheTagsOnLoad(Varien_Event_Observer $observer)
    {
        if (!$this->_isActive() || !$this->_helper->getCollectTags()) {
            return;
        }
        $collection = $observer->getEvent()->getCategoryCollection();
        foreach ($collection as $item) {
            Mage::getSingleton('opsway_varnishgento/processor')->addModelCacheTags($item);
        }
    }

    /**
     * Add cache tags
     * @param Varien_Event_Observer $observer
     */
    public function addCacheTags(Varien_Event_Observer $observer)
    {
        if (!$this->_isActive()) {
            return;
        }

        $tags = $observer->getEvent()->getTags();
        Mage::getSingleton('opsway_varnishgento/processor')->addTags($tags);
    }

    /**
     * Clean cache tags when application do it
     * @param Varien_Event_Observer $observer
     */
    public function cleanCacheByTags(Varien_Event_Observer $observer)
    {        
        if (!$this->_isActive()) {
            return;
        }
        $tags = $observer->getEvent()->getTags();
        $this->_helper->cleanCache($tags);
    }

    /**
     * Clean cache after any stock item refresh
     * @param Varien_Event_Observer $observer
     */
    public function cleanStockCacheByTags($observer)
    {             
        if (!$this->_isActive()) {
            return;
        }
        /**
        * @var $itemStock Mage_CatalogInventory_Model_Stock_Item
        */
        $itemStock = $observer->getEvent()->getItem();
        $hasChange = $itemStock->dataHasChangedFor('is_in_stock');
        if ($hasChange) {
            $this->_helper->cleanCache(
                $this->_helper->convertIdsToTags( array($itemStock->getProductId()) )
            );
        }

    }

    /**
     * Disable varnigento for products with special price
     * @param Varien_Event_Observer $observer
     */
    public function  disableCacheForProductWithSpecialPrice(Varien_Event_Observer $observer)
    {
        /**
         * @var $product Mage_Catalog_Model_Product
         */
        try {
            $product = $observer->getEvent()->getProduct();
            if (null != $product) {
                if ($product->getSpecialToDate() != null) {
                    $this->_helper->disable();
                    return;
                }
                if ($product->getSpecialFromDate() != null) {
                    $d = new DateTime($product->getSpecialFromDate());
                    if ($d != null && $d->format('U') > time()) {
                        $this->_helper->disable();
                        return;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_helper->disable();
            return;
        }
    }
    
    /**
     * Enter exception mode for specific HTML block
     * @param Varien_Event_Observer $observer
     */
    public function checkBlockExceptionBeforeRender(Varien_Event_Observer $observer)
    {
        if (!$this->_isActive()) {
            return;
        }
        if ($this->_helper->getAjaxifyHelper()->isActive()){
            $this->_helper->getAjaxifyHelper()->startProcessingBlock($observer->getEvent()->getBlock());
        }
        Mage::getSingleton('opsway_varnishgento/processor')->checkBlockExceptionBeforeRender($observer->getEvent()->getBlock());
    }

    /**
     * Disable exception mode if was enabled before
     * @param Varien_Event_Observer $observer
     */
    public function checkBlockExceptionAfterRender(Varien_Event_Observer $observer)
    {
        if (!$this->_isActive()) {
            return;
        }
        if ($this->_helper->getAjaxifyHelper()->isActive()){
            $this->_helper->getAjaxifyHelper()->stopProcessingBlock($observer->getEvent()->getBlock(),$observer->getEvent()->getTransport());
        }
        Mage::getSingleton('opsway_varnishgento/processor')->checkBlockExceptionAfterRender($observer->getEvent()->getBlock());
    }

    public function afterLoadLayout(Varien_Event_Observer $observer){
        if (!$this->_isActive()) {
            return;
        }
        if ($this->_helper->getAjaxifyHelper()->isActive()){
            $this->_helper->getAjaxifyHelper()->setLayout($observer->getEvent()->getLayout())->addJsScripts();
        }
    }

    public function addMessageAddedCookie()
    {
        Mage::getSingleton('core/cookie')->set(OpsWay_Varnishgento_Model_Ajaxify_Processor::MESSAGES_COOKIE, true, null, null, null, null, FALSE);
    }


    /**
     * Flush Varnish cache for product if product is out of stock after order placed.
     *
     * @param $observer Varien_Object Observer
     *
     * @return void
     */
    public function processOutOfStockProducts($observer)
    {
        if (!$this->_isActive()) {
            return;
        }
        if ($this->_isStockProcessed === false) {
            $this->_isStockProcessed = true;
            $order = $observer->getEvent()->getOrder();

            $outOfStockProductIds = array();
            foreach ($order->getItemsCollection() as $item) {
                if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                    continue;
                }
                $stockQty = (int)Mage::getModel('cataloginventory/stock_item')->loadByProduct($item->getProductId())->getQty();
                if ($stockQty == 0) {
                    $outOfStockProductIds[] = $item->getProductId();
                }
            }
            if (count($outOfStockProductIds)) {
                $outOfStockProductIds = array_unique($outOfStockProductIds);
                $this->_helper->refreshCacheForProduct($outOfStockProductIds);
            }
        }
    }

    /**
     * Clean cache for specific product ids list
     * @param Varien_Event_Observer $observer
     */
    public function cleanCacheByProductIds($observer){
        if (!$this->_isActive()) {
            return;
        }
        $product_ids = $observer->getEvent()->getProductIds();
        $this->_helper->refreshCacheForProduct($product_ids);
    }

    /**
     * Clean cache after for affected products from standard magento import process
     * @param Varien_Event_Observer $observer
     */
    public function cleanCacheByTagsAfterImport($observer){
        if (!$this->_isActive()) {
            return;
        }
        if (Mage::getStoreConfig('opsway_varnishgento/flushing/after_import')) {
            /**
             * @var $importEntityAdapter Mage_ImportExport_Model_Import_Entity_Product
             */
            $importEntityAdapter = $observer->getEvent()->getAdapter();
            $this->_helper->refreshCacheForProduct($importEntityAdapter->getAffectedEntityIds());
        }
    }

}
