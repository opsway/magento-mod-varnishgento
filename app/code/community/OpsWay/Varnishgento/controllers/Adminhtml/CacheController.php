<?php
/**
 * Varnish adminhtml cache controller
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */

include_once("Mage/Adminhtml/controllers/CacheController.php");
class OpsWay_Varnishgento_Adminhtml_CacheController extends Mage_Adminhtml_CacheController
{
    /**
     * Action flush varnish cache by URL
     */
    public function flushVarnishAction()
    {
        try {
            $url = urldecode($this->getRequest()->getParam('purge_url',''));
            $url = str_replace('?','.',$url);
            Mage::helper('opsway_varnishgento')->flushByUrlManually(trim($url) ? $url : OpsWay_Varnishgento_Model_Processor::FLUSH_ALL_PATTERN);
            $url = trim($url)?$url:'All';
        	$this->_getSession()->addSuccess(Mage::helper('adminhtml')->__("Varnish cache has been flushed. Frontal caches (%s) will be updated in several minutes", $url));
        } catch (Exception $e) {
            $this->_getSession()->addError(Mage::helper('adminhtml')->__("Error: %s", $e->getMessage()));
        }

    	$this->_redirect('*/*');
    }

    public function generateAjaxifyBlocksAction(){
        $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'ajaxify/?blocks='.implode(",",Mage::helper('opsway_varnishgento/ajaxify')->getAjaxifyNameBlocks());
        $dummyRequest = new Zend_Http_Client($url);
        // This need if server password required
        //$dummyRequest->setAuth('ecpadmin', '*******');
        $response = $dummyRequest->request();
        if ($response->isSuccessful()){
            $ajaxifyBlocks = Zend_Json::decode($response->getBody());
            $zip = new ZipArchive();
            $filename = "/tmp/ajaxify-static-blocks.zip";
            @unlink($filename);
            if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
                exit("cannot open <$filename>\n");
            }
            foreach ($ajaxifyBlocks as $name => $content){
                $zip->addFromString($name.".phtml", $content);
            }
            $zip->close();

            $this->getResponse()->setHeader('Content-Type','application/zip',true);
            $this->getResponse()->setHeader('Content-Length',filesize($filename),true);
            $this->getResponse()->setHeader('Content-Disposition','attachment; filename="'.basename($filename).'"',true);
            $this->getResponse()->setBody(file_get_contents($filename));

        } else {
            $this->_getSession()->addError(Mage::helper('adminhtml')->__("Error during generation ajaxify static blocks: %s", $response->getHeadersAsString()));
            $this->_redirect('*/*');
        }
    }

}
