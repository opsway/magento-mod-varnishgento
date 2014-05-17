<?php
/**
 * Rewrited core/config magento module
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */
class OpsWay_Varnishgento_Model_Rewrite_Core_Config extends Mage_Core_Model_Config
{
    /**
     * Get model class instance.
     *
     * Example:
     * $config->getModelInstance('catalog/product')
     *
     * Will instantiate Mage_Catalog_Model_Mysql4_Product
     *
     * @param string $modelClass
     * @param array|object $constructArguments
     * @return Mage_Core_Model_Abstract
     */
    public function getModelInstance($modelClass='', $constructArguments=array())
    {
        $obj = parent::getModelInstance($modelClass,$constructArguments);
        if (is_null(Mage::registry('controller'))){  //application_params
            return $obj;
        }

        $helper = new OpsWay_Varnishgento_Helper_Data();
        if (($obj === false) || !(Mage::getStoreConfig('opsway_varnishgento/general/active'))){
            return $obj;
        }

        if (($obj instanceof Mage_Core_Model_Abstract) && ($obj->getCacheTags() === false)) {
            //check $modelClass in custom associated taglist from config
            if (($cacheTagName = $helper->checkCustomTag($modelClass)) !== false){
                $reflectionObj = new ReflectionObject($obj);
                $reflectCacheTagProp = $reflectionObj->getProperty('_cacheTag');
                $reflectCacheTagProp->setAccessible(true);
                $reflectCacheTagProp->setValue($obj,$cacheTagName);
            }
        }
        return $obj;
    }
}