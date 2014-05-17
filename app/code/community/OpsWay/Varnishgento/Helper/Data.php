<?php
/**
 * Basic module helper
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

class OpsWay_Varnishgento_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CONFIG_KEY_LONG_CACHE_TAG = 'long_cache_tag';
    const CONFIG_KEY_SHORT_CACHE_TAG = 'short_cache_tag';
    const CONFIG_KEY_MODEL_CACHE_TAG = 'model_cache_tag';
    /**
     * Cache tags to attach to the page
     * @var array
     */
    protected $_tagsToPut = array();

    /**
     * Cache tags to clean after script execution
     * @var array
     */
    protected $_tagsToClean = array();

    /**
     * Varnish servers list
     * @var array
     */
    protected $_servers = null;

    /**
     * Varnish servers list
     * @var array
     */
    protected $_memcahcedServers = null;

    /**
     * Flag that indicates if the module is active
     * @var bool
     */
    protected $_isActive = null;

    /**
     * Collect tags flag, do not collect cache tags if false
     * @var bool
     */
    protected $_collectTags = true;

    /**
     * Add one or several tags
     * @param array | string $tags
     */
    public function addTags($tags)
    {
        if (!$this->getCollectTags()) {
            return;
        }
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        $this->_tagsToPut = array_merge($this->_tagsToPut, $tags);
    }

    /**
     * Get cache tags to put in the header
     * @return array
     */
    public function getTagsToPut()
    {
        $this->_tagsToPut = array_unique($this->_tagsToPut);
        foreach ($this->_tagsToPut as $index => $tag) {
            if (!empty($tag)) {
                $this->_tagsToPut[$index] = strtoupper($tag);
            } else {
                unset($this->_tagsToPut[$index]);
            }
        }
        return $this->_tagsToPut;
    }

    /**
     * Clean one or several tags
     * @param array | string $tags
     */
    public function cleanCache($tags)
    {
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        $this->_tagsToClean = array_merge($this->_tagsToClean, $tags);
    }

    /**
     * Get cache tags to clean in the end of the script
     * @return array
     */
    public function getTagsToClean()
    {
        $this->_tagsToClean = array_unique($this->_tagsToClean);
        foreach ($this->_tagsToClean as $index => $tag) {
            $this->_tagsToClean[$index] = strtoupper($tag);
        }
        return $this->_tagsToClean;
    }

    /**
     * Get collect tags flag value
     * @return bool
     */
    public function getCollectTags()
    {
        return $this->_collectTags;
    }

    /**
     * Set collect tags flag value
     * @param bool $value
     */
    public function setCollectTags($value)
    {
        $this->_collectTags = (bool)$value;
    }

    /**
     * Check if model is active
     * @return bool
     */
    public function isActive()
    {
        if (is_null($this->_isActive)) {
            $servers = $this->getServers();
            $this->_isActive = (bool)(Mage::getStoreConfig('opsway_varnishgento/general/active') && !empty($servers));
        }
        if ($this->_isActive){
            if (!$this->isModuleOutputEnabled()){
                $this->disable();
            }

            if ($this->isFrontendRequest() && !$this->checkVarnishgentoHeader()){
                $this->disable();
            }
        }
        return $this->_isActive;
    }

    public function isFrontendRequest(){
        if(Mage::app()->getStore()->isAdmin())
        {
            return false;
        }

        if(Mage::getDesign()->getArea() == 'adminhtml')
        {
            return false;
        }

        return true;
    }

    public function getAjaxifyHelper(){
        return Mage::helper('opsway_varnishgento/ajaxify');
    }

    public function checkVarnishgentoHeader(){
        return (bool)$this->_getRequest()->getHeader(OpsWay_Varnishgento_Model_Processor::VARNISH_HEADER_NAME);
    }

    /**
     * Disable varnishgento logic for current request
     */
    public function disable(){
        $this->_isActive = false;
    }

    /**
     * Enable varnishgento logic for current request
     */
    public function enable(){
        $this->_isActive = true;
    }

    /**
     * Get varnish servers
     * @return array
     */
    public function getServers()
    {
        if (is_null($this->_servers)) {
            $this->_servers = array();
            $servers = trim(Mage::getStoreConfig('opsway_varnishgento/general/servers'), ' ');
            if (!empty($servers)) {
                $servers = explode(';', $servers);
                foreach ($servers as $server) {
                    $parts = explode(':', $server);
                    $result = array(
                        'host' => $parts[0],
                        'port' => $parts[1]
                    );
                    if (!empty($parts[2])) {
                        $result['secret'] = $parts[2];
                    }
                    $this->_servers[] = $result;
                }
            }
        }
        return $this->_servers;
    }

    /**
     * Get memcached servers
     * @return array
     */
    public function getMemcachedServers()
    {
        if (is_null($this->_memcahcedServers)) {
            $this->_memcahcedServers = array();
            $servers = trim(Mage::getStoreConfig('opsway_varnishgento/memcached/servers'), ' ');
            if (!empty($servers)) {
                $servers = explode(';', $servers);
                foreach ($servers as $server) {
                    $parts = explode(':', $server);
                    $result = array(
                        'host' => $parts[0],
                        'port' => $parts[1]
                    );
                    if (!empty($parts[2])) {
                        $result['secret'] = $parts[2];
                    }
                    $this->_memcahcedServers[] = $result;
                }
            }
        }
        return $this->_memcahcedServers;
    }

    public function flushAll(){
        $this->addUrlToFlush(OpsWay_Varnishgento_Model_Processor::FLUSH_ALL_PATTERN);
    }

    public function checkLimitObjectToFlush($objects)
    {
        if (count($objects) > (int)(Mage::getStoreConfig('opsway_varnishgento/flushing/limit_to_flush'))){
            if (!$this->isFlushAllActive()){
                $this->flushAll();
            }
            return true;
        }
        return false;
    }

    public function isFlushAllActive()
    {
        return Mage::getModel('opsway_varnishgento/flag')->getCollection()->isFlushAllActive();
    }

    protected function addUrlToFlush($url,$iniciatorName = 'varnishgento',$iniciatorLogin = 'system'){
        $flag = Mage::getModel('opsway_varnishgento/flag');
        $data = array(
            'set_on' => Mage::getSingleton('core/date')->gmtDate(),
            'purge_url' => $url,
            'flushed' => 0,
            'iniciator_name' => $iniciatorName,
            'iniciator_login' =>  $iniciatorLogin
        );
        $flag->setData($data);
        $flag->save();
    }
    /**
     * Add url to db table queue for flush
     * @param $url string
     */
    public function flushByUrlManually($url){
        $name = Mage::getSingleton('admin/session')->getUser()->getFirstname().' '. Mage::getSingleton('admin/session')->getUser()->getLastname();
        $this->addUrlToFlush($url,$name,Mage::getSingleton('admin/session')->getUser()->getUsername());
    }

    /**
     * Getting IP server address
     * @return mixed
     */
    public function detectCurrentNode(){
        return $_SERVER['SERVER_ADDR'];
    }

    /**
     * Showing node name by IP
     * @return mixed
     */
    public function showCurrentNode(){
        $enabled = (bool)(Mage::getStoreConfig('opsway_varnishgento/general/show_nodes'));
        if ($enabled){
            $nodesconf = Mage::getStoreConfig('opsway_varnishgento/general/nodes_ip');
            $nodesconf = explode(';',$nodesconf);
            $nnodes = array();
            foreach($nodesconf as $nodestr){
                $a = explode(':',$nodestr);
                $nnodes[$a[0]] = $a[1];
            }
            if (($n = array_search($this->detectCurrentNode(),$nnodes)) !== FALSE){
                return $n;
            }
            return $this->detectCurrentNode();

        }
    }

    public function convertIdsToTags(array $entityIds, $type = 'catalog_product')
    {
        $entityTagPrefix = Mage::app()->getConfig()->getNode("global/opsway_varnishgento/cache_tag_shortcuts/{$type}/target");

        return array_map(function($t) use ($entityTagPrefix){
                return "{$entityTagPrefix}_{$t}";
            },$entityIds);
    }

    /**
     * Refresh Varnish cache for defined products
     *
     * @param $productIds array Product ids
     *
     * @return void
     */
    public function refreshCacheForProduct(array $productIds)
    {
        if (count($productIds)>0) {
            if ($this->checkLimitObjectToFlush($productIds)){
                return;
            }
            $categoryIds = $this->_getProductCategory($productIds);

            $tagsList = $this->convertIdsToTags($productIds);
            $tagsList += $this->convertIdsToTags($categoryIds,'catalog_category');

            Mage::dispatchEvent('application_clean_cache', array('tags' => $tagsList));
            Mage::getSingleton('opsway_varnishgento/processor')->cleanCache();
        }
    }

    /**
     * Get category ids for products
     *
     * @param array $productIds Products ids
     *
     * @return array
     */
    protected function _getProductCategory($productIds)
    {
        $categoryIds = array();
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $select = $read->select()->from($resource->getTableName('catalog_category_product'))
            ->reset(Zend_Db_Select::COLUMNS)
            ->distinct()
            ->columns('category_id')
            ->where('product_id IN (?)', $productIds);
        $rows = $read->fetchAll($select);
        foreach ($rows as $row) {
            $categoryIds[] = $row['category_id'];
        }
        return $categoryIds;
    }

    public function getFlushPeriodByTags(){
        $result = array();
        $basePeriod = Mage::getStoreConfig('opsway_varnishgento/flushing/base_period');
        $rawField = @unserialize(Mage::getStoreConfig('opsway_varnishgento/flushing/period_by_tags'));
        if (!is_array($rawField)){
            $rawField = array();
        }
        foreach ($this->getListTagTypes() as $shotTagType){
            $foundSaveValue = false;
            foreach ($rawField as $value){
                if ($value['type'] == $shotTagType){
                    $foundSaveValue = $value['period'];
                    break;
                }
            }
            if ($foundSaveValue === FALSE){
                $result[$shotTagType] = $basePeriod;
            } else {
                if ($foundSaveValue % $basePeriod == 0){
                    $result[$shotTagType] = $foundSaveValue;
                } else {
                    $result[$shotTagType] = $basePeriod;
                }
            }
        }
        return $result;
    }

    public function getCustomTagTypes($returnShotcuts = false){
        $rawField = @unserialize(Mage::getStoreConfig('opsway_varnishgento/flushing/custom_tags'));
        if (!is_array($rawField)){
            $rawField = array();
        }
        if ($returnShotcuts){
            $listTagTypes = array();
            foreach ($rawField as $row){
                $listTagTypes[strtoupper($row[self::CONFIG_KEY_LONG_CACHE_TAG])] = $row[self::CONFIG_KEY_SHORT_CACHE_TAG];
            }
            return $listTagTypes;
        }
        return $rawField;
    }

    public function getListTagTypes(){
        $cacheTagShortcuts = array();
        $path = 'global/opsway_varnishgento/cache_tag_shortcuts';
        foreach (Mage::app()->getConfig()->getNode($path)->children() as $node) {
            $cacheTagShortcuts[(string)$node->source] = (string)$node->target;
        }
        $cacheTagShortcuts += $this->getCustomTagTypes(true);
        return $cacheTagShortcuts;
    }

    public function checkCustomTag($modelName){
        foreach ($this->getCustomTagTypes() as $row){
            if ($modelName == $row[self::CONFIG_KEY_MODEL_CACHE_TAG]) return $row[self::CONFIG_KEY_LONG_CACHE_TAG];
        }
        return false;
    }

    public function getCompareTagFunc($exTags,$useRegex = false){
        return function($tag) use ($exTags,$useRegex){
                            foreach ($exTags as $exTag){
                                if ($useRegex){
                                    if (preg_match('/'.$exTag.'/ui',$tag)){
                                        return false;
                                    }
                                } else {
                                    if (stripos($tag,$exTag) === 0){
                                        return false;
                                    }
                                }
                            }
                            return true;
                        };
    }
}