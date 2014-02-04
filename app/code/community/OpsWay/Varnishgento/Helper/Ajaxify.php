<?php
/**
 * Created by PhpStorm.
 * User: Shandy
 * Date: 09.12.13
 * Time: 23:47
 */
class OpsWay_Varnishgento_Helper_Ajaxify extends Mage_Core_Helper_Abstract
{
    protected $_isActive = null;
    protected $_uncachedBlocks = null;
    protected $_currentProcessing = null;
    protected $_currentProcessingHtmlOutput = null;

    public function isActive(){
        if (is_null($this->_isActive)){
            $this->_isActive = false;
            if (Mage::helper('opsway_varnishgento')->isActive()){
                $this->_isActive = (bool)Mage::getStoreConfig('opsway_varnishgento/ajaxify/active');
            }
        }
        return $this->_isActive;
    }

    public function isAjaxifyRequest(){
        return preg_match('/'.OpsWay_Varnishgento_Model_Ajaxify_Processor::AJAXIFY_URL_PATTERN.'/', $this->_getRequest()->getRequestUri());
    }

    public function addJsScripts(){
        if ($this->getLayout()) {
            /**
             * @var $blockHead Mage_Page_Block_Html_Head
             */
            $blockHead = $this->getLayout()->getBlock('head');
            if ($blockHead){
                $blockHead->addJs('varnishgento/ajaxify.js');
                $block = $this->getLayout()->createBlock(
                    'Mage_Core_Block_Template',
                    'ajaxify',
                    array('template' => 'varnishgento/ajaxify.phtml')
                );
                $blockHead->append($block);
            }
        }
    }

    /**
     * @param $block Mage_Core_Block_Abstract
     */
    public function startProcessingBlock($block){
        if ($this->checkIsAjaxifyBlock($block) && !$this->_currentProcessing){
            $this->_currentProcessing = $block->getNameInLayout();
            if (Mage::app()->useCache(Mage_Core_Block_Abstract::CACHE_GROUP)){
                //Not compatible with old version magento
                //$block->addCacheTag('static-'.$this->_currentProcessing);
                $block->setData('cache_lifetime',3600*24*7);
                $this->_currentProcessingHtmlOutput = Mage::app()->loadCache($block->getCacheKey());
            }
            if ($this->_currentProcessingHtmlOutput) {
                $this->_currentProcessingHtmlOutput = null;
                return;
            } else {
                $this->_currentProcessingHtmlOutput = $block->getLayout()->createBlock(
                                                'Mage_Core_Block_Template',
                                                'ajaxify-'.$this->_currentProcessing,
                                                array('template' => 'varnishgento/ajaxify_static/'. strtolower($this->_currentProcessing) .'.phtml')
                                            )->toHtml();
            }
            if ($this->_currentProcessingHtmlOutput){
                $tags = $block->getCacheTags();
                Mage::app()->saveCache($this->_currentProcessingHtmlOutput, $block->getCacheKey(), $tags, $block->getCacheLifetime());
            }
        }
    }

    public function stopProcessingBlock($block,$htmlObject){
        /**
         * @var $block Mage_Core_Block_Abstract
         */
        if ($this->checkIsAjaxifyBlock($block) && $htmlObject && ($this->_currentProcessing == $block->getNameInLayout())){
            $this->_wrapBlock($block,$htmlObject);
            $this->_currentProcessing = null;
            $this->_currentProcessingHtmlOutput = null;
        }
    }

    protected function _wrapBlock($block,$htmlObject){
        $htmlObject->setHtml(
            '<div class="ajax-loader" id="'
            .$block->getNameInLayout()
            .'_block" rel="'
            .$block->getNameInLayout()
            .'" handles="'
            .implode(",",$block->getLayout()->getUpdate()->getHandles())
            .'">'
            .(($this->_currentProcessingHtmlOutput) ? $this->_currentProcessingHtmlOutput : $htmlObject->getHtml())
            .'</div>'
        );

    }

    public function checkIsAjaxifyBlock($block){
        return in_array($block->getNameInLayout(),$this->getAjaxifyNameBlocks());
    }

    public function getAjaxifyNameBlocks(){
        if (is_null($this->_uncachedBlocks)){
            $this->_uncachedBlocks = explode("\n",Mage::getStoreConfig('opsway_varnishgento/ajaxify/uncached_blocks'));
        }
        return $this->_uncachedBlocks;
    }
}