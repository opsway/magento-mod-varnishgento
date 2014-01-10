<?php
/**
 * Created by PhpStorm.
 * User: Shandy
 * Date: 10.01.14
 * Time: 21:15
 */
class OpsWay_Varnishgento_Block_Adminhtml_System_Config_Field_PeriodTags extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function _prepareToRender()
    {

        $this->addColumn('type', array(
            'label' => Mage::helper('opsway_varnishgento')->__('Tag ShotName')

        ));
        $this->addColumn('period', array(
            'label' => Mage::helper('opsway_varnishgento')->__('Period time')
        ));

        // Disables "Add after" button
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('opsway_varnishgento')->__('Add Field');

        $htmlTooltip  = '<div style="border-radius:6px;background-color:#fcc;padding:10px;margin-left:10px;width:230px;"><h3>You should use this this shotcuts:</h3><p>';
        $htmlTooltip .= '<table>';
        $htmlTooltip .= '<tr><td>Full name cache tag</td><td style="text-align: center;">Shot name tag</td></tr>';
        foreach (Mage::helper('opsway_varnishgento')->getListTagTypes() as $full => $shot){
            $htmlTooltip .= '<tr><td>'.$full.'</td><td><b>'.$shot . '</b></td></tr>';
        }
        $htmlTooltip .= '</table>';
        $htmlTooltip .= '</p></div>';
        $this->getElement()->setTooltip($htmlTooltip);

    }
}