<?php
/**
* Process AJAX requests
*
* @category StageStores_Ajaxify
* @package StageStores
* @author Oleksandr Zirka <oleksandr.zirka@smile.fr>
* @copyright 2013 Smile
*/
class OpsWay_Varnishgento_Model_Ajaxify_Processor
{
    /**
* Url pattern for AJAX requests
*/
    const AJAXIFY_URL_PATTERN = '^\/ajaxify\/\?.*';
    const MESSAGES_COOKIE = 'messages_added';

    protected $_content;
    /**
     * @var Mage_Core_Model_Layout
     */
    protected $_layout;
    protected $_cache;

    /**
* Class constructor
*/
    public function __construct()
    {
        if (preg_match('/'.self::AJAXIFY_URL_PATTERN.'/', Mage::app()->getRequest()->getRequestUri())) {
            $this->_content = true;
        }
    }

    protected function _bootstrap(){
        Mage::app()->init('','store'); //('','store');
        $this->_cache = Mage::app()->getCache();
        Mage::getSingleton('core/session', array('name' => 'frontend'));
        Mage::app()->getTranslator()->init('frontend');
        Mage::app()->getFrontController()->init();
        Mage::getSingleton('log/visitor')->initByRequest(false);
        Mage::getSingleton('core/session')->setAjaxReferer($this->_getRefererUrl());
    }

    /**
* Get page content from cache storage
*
* @param string $content
* @return string | false
*/
    public function extractContent($content)
    {
        if ($this->_content){

            $this->_bootstrap();

            $blockNames = Mage::app()->getRequest()->getParam('blocks');
            if (strpos($blockNames,"messages") === false) $blockNames .= ',messages';

            $blocks = $this->generateBlocks(explode(",",$blockNames));

            /*$blocks['messages'] = $this->_layout->createBlock(
                                'Mage_Core_Block_Template',
                                'ajaxify',
                                array('template' => 'varnishgento/messages.phtml')
                            )->toHtml();*/

            Mage::app()->getResponse()
                ->setHeader('Cache-Control', 'max-age=0, no-cache, no-store, must-revalidate')
                ->setHeader('Expires', '-1')
                ->setHeader('Content-Type', 'application/json');

            $content = Zend_Json::encode($blocks);
            Mage::getSingleton('log/visitor')->saveByRequest(false);
        }
        return $content;
    }

    public function generateBlocks(array $blockNames){
                    // Add specific layout handles to our layout and then load them
            $this->_layout = Mage::app()->getLayout();
            $lu = $this->_layout->getUpdate();
            foreach (explode(",","default,ajaxify") as $handle){
                $lu->addHandle($handle);
            }
            if (!Mage::getSingleton('customer/session')->isLoggedIn()){
                $lu->addHandle('customer_logged_out');
            } else {
                $lu->addHandle('customer_logged_in');
            }
            $lu->load();
            // Generate blocks, but XML from previously loaded layout handles must be loaded first
            $this->_layout->generateXml()
                   ->generateBlocks();

            $blocks = array();

            foreach ($blockNames as $val) {

                $block = $this->_layout->getBlock($val);
                if ($block) {
                    $content = $block->toHtml();
                } else {
                    $content = false;
                }
                $blocks[$val] = $content;
            }
        return $blocks;
    }
    /**
* Check if processor is allowed for current HTTP request.
* Disable processing HTTPS requests and requests with "NO_CACHE" cookie
*
* @return bool
*/
    public function isAllowed()
    {
        if (!$this->_requestId) {
            return false;
        }
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            return false;
        }
        if (isset($_COOKIE['NO_CACHE'])) {
            return false;
        }
        if (isset($_GET['no_cache'])) {
            return false;
        }
        return true;
    }

    protected function _getRefererUrl()
    {
        $refererUrl = $this->getRequest()->getServer('HTTP_REFERER');
        if ($url = $this->getRequest()->getParam(Mage_Core_Controller_Varien_Action::PARAM_NAME_REFERER_URL)) {
            $refererUrl = $url;
        }
        if ($url = $this->getRequest()->getParam(Mage_Core_Controller_Varien_Action::PARAM_NAME_BASE64_URL)) {
            $refererUrl = Mage::helper('core')->urlDecode($url);
        }
        if ($url = $this->getRequest()->getParam(Mage_Core_Controller_Varien_Action::PARAM_NAME_URL_ENCODED)) {
            $refererUrl = Mage::helper('core')->urlDecode($url);
        }

        $refererUrl = Mage::helper('core')->escapeUrl($refererUrl);

        return $refererUrl;
    }

    protected function getRequest(){
        return Mage::app()->getRequest();
    }
}