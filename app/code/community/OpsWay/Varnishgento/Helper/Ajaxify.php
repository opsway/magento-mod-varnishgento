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

    public function startProcessingBlock($block){
        if ($this->checkIsAjaxifyBlock($block) && !$this->_currentProcessing){
            $this->_currentProcessing = $block->getNameInLayout();
        }
    }

    public function stopProcessingBlock($block,$htmlObject){
        /**
         * @var $block Mage_Core_Block_Abstract
         */
        if ($this->checkIsAjaxifyBlock($block) && $htmlObject && ($this->_currentProcessing == $block->getNameInLayout())){
            $handles = implode(",",$block->getLayout()->getUpdate()->getHandles());
            $htmlObject->setHtml('<div class="ajax-loader" id="'.$block->getNameInLayout().'_block" rel="'.$block->getNameInLayout().'" handles="'.$handles.'">'.$htmlObject->getHtml().'</div>');
            $this->_currentProcessing = null;
        }
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