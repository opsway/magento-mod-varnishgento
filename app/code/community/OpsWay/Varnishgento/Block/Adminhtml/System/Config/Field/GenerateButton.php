<?php
/**
 * Created by PhpStorm.
 * User: Shandy
 * Date: 01.02.14
 * Time: 18:29
 */
class OpsWay_Varnishgento_Block_Adminhtml_System_Config_Field_GenerateButton extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $url = $this->getUrl('adminhtml/cache/generateAjaxifyBlocks'); //

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('scalable')
                    ->setLabel('Run Now!')
                    ->setOnClick("setLocation('$url')")
                    ->toHtml();

        return $html;
    }
}