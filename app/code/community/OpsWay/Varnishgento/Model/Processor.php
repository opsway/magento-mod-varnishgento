<?php
/**
 * Varnish cache processor
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */
class OpsWay_Varnishgento_Model_Processor
{
    /**
     * Cache header name
     */
    const CACHE_HEADER_NAME = 'X-Cache-Tags';

    const NO_CACHE_TAG_NAME = 'NO_CACHE_TAG';

    /**
     * Cache storage id
     */
    const CACHE_TAGS_STORAGE_ID = 'VARNISH_CACHE_TAGS';
    /**
     * Cache storage counter
     */
    const CACHE_TAGS_STORAGE_ID_CALL_COUNTER = 'VARNISH_CACHE_TAGS_CALL_COUNTER';
    /**
     * Cache storage counter
     */
    const CLEAN_CATEGORY_PER_TIME = 60;

    const CACHE_CLEAN_COUNTER = 'VARNISH_CACHE_CLEAN_COUNTER';

    /**
     * Tags catalog category prefix
     */
    const CATALOG_CATEGORY_TAG_PREFIX = 'CATALOG_CATEGORY_';
    const CATALOG_CATEGORY_TAG_PREFIX_SHORT = 'CT_';
    const TOP_LEVEL_CATALOG_ARRAY = 'VARNIGENTO_TOP_LEVEL_CATALOG_ARRAY';

    const FLUSH_ALL_PATTERN = '.*';

    /**
     * Tags separator in the cache tags
     */
    const CACHE_HEADER_TAG_SEPARATOR = '~';

    /**
     * Purge tick-tock counter - cycle during 24h
     */
    const MAX_TICKTOCK_COUNTER = 1440;

    /**
     * Exception blocks list
     * @var array
     */
    protected $_cacheTagShortcuts = null;

    /**
     * Exception blocks list
     * @var array
     */
    protected $_exceptionBlockList = null;

    /**
     * Exception blocks stack
     * @var array
     */
    protected $_exceptionBlockStock = array();

    protected $_exceptionTagsList = null;

    /**
     * The list of cache tags on page
     * @var array
     */
    protected $_cacheHeaderTags = array();

    /**
     * Retrive cache header
     * @param $skipCatalogTags boolean
     * @return string
     */
    public function getCacheHeader($skipCatalogTags)
    {
        $separator = self::CACHE_HEADER_TAG_SEPARATOR;
        $tags = $this->_getCacheHeader($skipCatalogTags);
        return $separator . join($separator, $tags) . $separator;
    }

    /**
     * Retrive cache header
     *
     * @return string
     */
    protected function _getCacheHeader($skipCatalogTags)
    {
        if (empty($this->_cacheHeaderTags)) {
            $tagsToPut = Mage::helper('opsway_varnishgento')->getTagsToPut();
            $topLevelCategoryTag = $this->getTopLevelCategoryTags();
            foreach ($tagsToPut as $tag) {
                if (in_array($tag, $topLevelCategoryTag)) {
                    continue;
                }
                if ($skipCatalogTags) {
                    if (!$this->startsWith($tag, self::CATALOG_CATEGORY_TAG_PREFIX)) {
                        $this->_cacheHeaderTags[] = $this->_getCacheTagShortcut($tag);
                    }
                } else {
                    $this->_cacheHeaderTags[] = $this->_getCacheTagShortcut($tag);
                }
            }
        }
        return $this->_cacheHeaderTags;
    }

    /**
     * Clean varnish cache
     */
    public function cleanCache()
    {
        $tagsToClean = Mage::helper('opsway_varnishgento')->getTagsToClean();
        if (empty($tagsToClean)) {
            return;
        }

        if (Mage::helper('opsway_varnishgento')->checkLimitObjectToFlush($tagsToClean)){
            return;
        }
        if (Mage::helper('opsway_varnishgento')->isFlushAllActive()){
            return;
        }

        foreach ($tagsToClean as &$tag) {
            $tag = $this->_getCacheTagShortcut($tag);
        }
        unset($tag);

        $tagsToClean = $this->filterTags($tagsToClean);
        if (empty($tagsToClean)) {
            return;
        }

        if (!Mage::getStoreConfig('opsway_varnishgento/flushing/asynchronous')) {
            try {
                $this->purgeTags($tagsToClean);
            } catch (OpsWay_Varnishgento_Model_Connector_Exception $e) {
                Mage::log(
                    Mage::helper('opsway_varnishgento')->__('Error during cache clean. Reason: %s', $e->getMessage()),
                    Zend_Log::ERR
                );
            } catch (Exception $e) {
                Mage::logException($e);
            }
        } else {
            $this->addTagsToQueue($tagsToClean);
        }
    }

    /**
     * @param $counter int
     * @return array|bool
     */
    public function getPurgeTypeTagsScheduled($counter){
        $period = Mage::getStoreConfig('opsway_varnishgento/flushing/base_period');
        if ($counter % $period == 0){
            $periodTags = Mage::helper('opsway_varnishgento')->getFlushPeriodByTags();
            return array_keys(
                        array_filter(
                            $periodTags,
                            function($tag_period) use ($counter){
                                return ($counter % $tag_period == 0);
                            }
                        )
            );
        }
        return false;
    }

    /**
     * Increment TickTock Counter (Base generator)
     * @return int
     */
    public function tickTockCounter(){
        $counter = (int) Mage::app()->loadCache(self::CACHE_CLEAN_COUNTER);
        if(!$counter){
             $counter = 0;
        }
        $counter++;
        if ($counter > self::MAX_TICKTOCK_COUNTER){
            $counter = 1;
        }
        Mage::app()->saveCache($counter,self::CACHE_CLEAN_COUNTER, array('CUSTOM_VARNIGENTO'));
        return $counter;
    }

    /**
     * @param array $tags
     * @param array $byTypeTags
     *
     * @return array
     */
    public function filterTags($tags,$byTypeTags = array())
    {
        if (count($byTypeTags)){
            return array_uintersect($tags, $byTypeTags, Mage::helper('opsway_varnishgento')->getCompareTagFunc());
        }

        if ($this->_exceptionTagsList === null){
            $configTags = trim(Mage::getStoreConfig('opsway_varnishgento/flushing/exception_tags'));
            if ($configTags != ''){
                $this->_exceptionTagsList = explode(",",$configTags);
            } else {
                $this->_exceptionTagsList = array();
            }
        }
        if (empty($this->_exceptionTagsList)){
            return $tags;
        }
        return array_udiff($tags,$this->_exceptionTagsList,Mage::helper('opsway_varnishgento')->getCompareTagFunc());
    }

    /**
     *
     * Purge varnish cache by specified tags
     * @param array $tags
     * @throws OpsWay_Varnishgento_Model_Connector_Exception
     */
    public function purgeTags($tags)
    {
        $connector = Mage::getSingleton('opsway_varnishgento/connector');
        if ($connector->isLocked()) {
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Connector is locked')
            );
        }
        try {
            $connector->lock();
            $connector->init(
                Mage::helper('opsway_varnishgento')->getServers(),
                Mage::getStoreConfig('opsway_varnishgento/general/debug')
            );
            $separator = self::CACHE_HEADER_TAG_SEPARATOR;
            $_tags = array();
            foreach ($tags as $tag) {
                $_tags[] = $separator.$tag.$separator;
            }
            $connector->purgeByResponseHeader(self::CACHE_HEADER_NAME, join('|', $_tags));
        } catch (Exception $e) {
            $connector->unlock();
            throw $e;
        }
        $connector->unlock();
    }

    /**
     * Add cache tags from the given model
     * @param Mage_Core_Model_Abstract $model
     */
    public function addModelCacheTags(Mage_Core_Model_Abstract $model)
    {
        $tags = $model->getCacheIdTags();
        if ($tags){
            $this->addTags($tags);
        }
    }

    /**
     * Add tags
     * @param array $tags
     */
    public function addTags(array $tags)
    {
        Mage::helper('opsway_varnishgento')->addTags($tags);
    }

    /**
     * Get tags from queue
     * @return string
     */
    public function getTagsFromQueue($forClean = false)
    {
        $tags = Mage::app()->loadCache(self::CACHE_TAGS_STORAGE_ID);
        if (!empty($tags)) {
            $tags = unserialize($tags);
            if (!is_array($tags)) {
                $tags = array();
            }
        } else {
            $tags = array();
        }


        if($forClean){

            $counter = Mage::app()->loadCache(self::CACHE_TAGS_STORAGE_ID_CALL_COUNTER);
            if(!$counter){
                 Mage::app()->saveCache(1,self::CACHE_TAGS_STORAGE_ID_CALL_COUNTER, array('CUSTOM_VARNIGENTO'));
                 $counter = 1;
            }
            if($counter++ % self::CLEAN_CATEGORY_PER_TIME != 0){
                $tagsWithoutCatalog = array();

                foreach($tags as $tag){
                    if(!$this->startsWith($tag, self::CATALOG_CATEGORY_TAG_PREFIX_SHORT)){
                        $tagsWithoutCatalog[] = $tag;
                    }
                }
                $tags = $tagsWithoutCatalog;
            }
            Mage::app()->saveCache($counter,self::CACHE_TAGS_STORAGE_ID_CALL_COUNTER, array('CUSTOM_VARNIGENTO'));
        }

        return $tags;
    }

    /**
     * Add tags to queue
     * @param array $tags
     */
    public function addTagsToQueue(array $tags)
    {
        $oldTags = $this->getTagsFromQueue();
        $tags = array_unique(array_merge($tags, $oldTags));
        Mage::app()->saveCache(serialize($tags), self::CACHE_TAGS_STORAGE_ID);
    }

    /**
     * Remove tags from queue
     * @param array $tags
     */
    public function removeTagsFromQueue(array $tags)
    {
        $oldTags = $this->getTagsFromQueue();
        $tags = array_diff($oldTags, array_intersect($tags, $oldTags));
        Mage::app()->saveCache(serialize($tags), self::CACHE_TAGS_STORAGE_ID);
    }

    /**
     * Get cache tag shotcuts list
     * @return array
     */
    protected function _getCacheTagShortcuts()
    {
        if (is_null($this->_cacheTagShortcuts)) {
            $this->_cacheTagShortcuts = array();
            $path = 'global/opsway_varnishgento/cache_tag_shortcuts';
            foreach (Mage::app()->getConfig()->getNode($path)->children() as $node) {
                $this->_cacheTagShortcuts[(string)$node->source] = (string)$node->target;
            }
        }
        return $this->_cacheTagShortcuts;
    }

    /**
     * Get short version of cache tag if available
     * @param string $tag
     * @return string
     */
    protected function _getCacheTagShortcut($tag)
    {
        $shortcuts = $this->_getCacheTagShortcuts();
        foreach ($shortcuts as $source => $target) {
            if (strpos($tag, $source) === 0) {
                return str_replace($source, $target, $tag);
            }
        }
        return $tag;
    }

    /**
     * Get exception blocks
     * @return array
     */
    protected function _getExceptionBlockList()
    {
        if (is_null($this->_exceptionBlockList)) {
            $this->_exceptionBlockList = array();
            $path = 'frontend/opsway_varnishgento/block_exceptions';
            foreach (Mage::app()->getConfig()->getNode($path)->children() as $node) {
                $block = Mage::getBlockSingleton((string)$node->type);
                $callback = true;
                if (isset($node->callback)) {
                    $callback = new stdClass();
                    $callback->model = Mage::getSingleton((string)$node->callback->model);
                    $callback->method = (string)$node->callback->method;
                }
                $this->_exceptionBlockList[get_class($block)] = $callback;
            }
        }
        return $this->_exceptionBlockList;
    }

    /**
     * Check current block is in exception list and deactivate tags collection if necessary
     * @param Mage_Core_Block_Abstract $block
     */
    public function checkBlockExceptionBeforeRender(Mage_Core_Block_Abstract $block)
    {
        $exceptionBlockList = $this->_getExceptionBlockList();
        $blockToCheck = $block;
        while(null != $blockToCheck){
            if (isset($exceptionBlockList[$blockToCheck->getNameInLayout()])) {
                Mage::helper('opsway_varnishgento')->setCollectTags(false);
                return;
            }
            $blockToCheck = $blockToCheck->getParentBlock();
        }
        Mage::helper('opsway_varnishgento')->setCollectTags(true);
    }

    /**
     * Check if exception is already rendered and reactivate tags collection if necessary
     * @param Mage_Core_Block_Abstract $block
     */
    public function checkBlockExceptionAfterRender(Mage_Core_Block_Abstract $block)
    {
        if (empty($this->_exceptionBlockStock)) {
            return;
        }
        $lastBlock = $this->_exceptionBlockStock[count($this->_exceptionBlockStock) - 1];
        if ($lastBlock == get_class($block)) {
            array_pop($this->_exceptionBlockStock);
            if (empty($this->_exceptionBlockStock)) {
                Mage::helper('opsway_varnishgento')->setCollectTags(true);
            }
            $exceptionBlockList = $this->_getExceptionBlockList();
            if (is_object($exceptionBlockList[$lastBlock])) {
                call_user_func(array($exceptionBlockList[$lastBlock]->model, $exceptionBlockList[$lastBlock]->method), $block);
            }
        }
    }

    /**
     * @return array
     */
    protected function getTopLevelCategoryTags(){

        $ret = Mage::app()->loadCache(self::TOP_LEVEL_CATALOG_ARRAY);

        if(!$ret){
            $ret = array();
            $rootCategoryId = Mage::app()->getStore()->getRootCategoryId();

            $ret[] =  self::CATALOG_CATEGORY_TAG_PREFIX.$rootCategoryId;
            $ret[] =  self::CATALOG_CATEGORY_TAG_PREFIX_SHORT.$rootCategoryId;

            $_category = Mage::getModel('catalog/category')->load($rootCategoryId);
            $_subcategories = $_category->getChildrenCategories();
            foreach($_subcategories as $_subCat){
                $ret[] =  self::CATALOG_CATEGORY_TAG_PREFIX.$_subCat->getEntityId();
                $ret[] =  self::CATALOG_CATEGORY_TAG_PREFIX_SHORT.$_subCat->getEntityId();
            }
            $ret = serialize($ret);
            Mage::app()->saveCache($ret,self::TOP_LEVEL_CATALOG_ARRAY, array('CUSTOM_VARNIGENTO'), 24*60*60);
        }
        $ret = unserialize($ret);
        return $ret;
    }

    /**
     * Purging varnish cache by url
     * @param $urls string
     *
     * @throws OpsWay_Varnishgento_Model_Connector_Exception
     * @throws Exception
     */
    public function purgeUrls($urls)
    {
        $connector = Mage::getSingleton('opsway_varnishgento/connector');
        if ($connector->isLocked()) {
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Connector is locked')
            );
        }
        try {
            $connector->lock();
            $connector->init(
                Mage::helper('opsway_varnishgento')->getServers(),
                Mage::getStoreConfig('opsway_varnishgento/general/debug')
            );
            foreach ($urls as $url) {
                $connector->purgeByUrl(trim($url));
            }

        } catch (Exception $e) {
            $connector->unlock();
            throw $e;
        }
        $connector->unlock();

        if (Mage::getStoreConfigFlag('opsway_varnishgento/memcached/auto')) {
            $this->flushMemcached();
        }
    }

    public function flushMemcached()
    {
        $connector = Mage::getSingleton('opsway_varnishgento/connector');
        if ($connector->isLocked()) {
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Connector is locked')
            );
        }
        try {
            $connector->lock();
            $connector->init(
                Mage::helper('opsway_varnishgento')->getMemcachedServers(),
                Mage::getStoreConfig('opsway_varnishgento/general/debug'),
                false
            );
            $connector->flushMemcached();

        } catch (Exception $e) {
            $connector->unlock();
            throw $e;
        }
        $connector->unlock();
    }

    /**
     * @param $haystack string
     * @param $needle string
     *
     * @return bool
     */
    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
}
