<?php
class OpsWay_Varnishgento_Helper_Ajaxify_Url extends Mage_Core_Helper_Url
{
    public function getCurrentUrl()
   {
        if (Mage::helper('opsway_varnishgento/ajaxify')->isAjaxifyRequest()){
            return Mage::getSingleton('core/session')->getAjaxReferer();
        }
       return parent::getCurrentUrl();
   }
}