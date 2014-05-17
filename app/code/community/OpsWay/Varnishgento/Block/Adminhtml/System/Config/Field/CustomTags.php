<?php
/**
 * Created by PhpStorm.
 * User: Shandy
 * Date: 10.01.14
 * Time: 21:15
 */
class OpsWay_Varnishgento_Block_Adminhtml_System_Config_Field_CustomTags extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function _prepareToRender()
    {

        $this->addColumn(OpsWay_Varnishgento_Helper_Data::CONFIG_KEY_MODEL_CACHE_TAG, array(
            'label' => Mage::helper('opsway_varnishgento')->__('Model Name')

        ));
        $this->addColumn(OpsWay_Varnishgento_Helper_Data::CONFIG_KEY_LONG_CACHE_TAG, array(
            'label' => Mage::helper('opsway_varnishgento')->__('Cache Long Tag')
        ));
        $this->addColumn(OpsWay_Varnishgento_Helper_Data::CONFIG_KEY_SHORT_CACHE_TAG, array(
            'label' => Mage::helper('opsway_varnishgento')->__('Cache Short Tag')
        ));

        // Disables "Add after" button
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('opsway_varnishgento')->__('Add Field');

    }
}