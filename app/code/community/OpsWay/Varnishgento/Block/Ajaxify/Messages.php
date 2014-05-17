<?php
/**
 * Created by PhpStorm.
 * User: Shandy
 * Date: 26.12.13
 * Time: 0:11
 */
class OpsWay_Varnishgento_Block_Ajaxify_Messages extends Mage_Core_Block_Messages
{

    /**
* Storage for used types of message storages
*
* @var array
*/
    protected $_usedStorageTypes = array('core/session',
                                         'catalog/session',
                                         'checkout/session',
                                         'customer/session');

    public function _prepareLayout()
    {
       foreach ($this->_usedStorageTypes as $class_name) {
            $storage = Mage::getSingleton($class_name);
            if ($storage) {
                $messages = $storage->getMessages(true);
                if ($messages->count() > 0) {
                    Mage::helper('opsway_varnishgento')->addTags(OpsWay_Varnishgento_Model_Processor::NO_CACHE_TAG_NAME);
                }
                $this->addMessages($messages);
            }
        }
        Mage_Core_Block_Template::_prepareLayout();
    }

    public function getGroupedHtml()
    {
            $types = array(
                Mage_Core_Model_Message::ERROR,
                Mage_Core_Model_Message::WARNING,
                Mage_Core_Model_Message::NOTICE,
                Mage_Core_Model_Message::SUCCESS
            );
            $html = '';
            foreach ($types as $type) {
                if ( $messages = $this->getMessages($type) ) {
                    if ( !$html ) {
                        $html .= '<' . $this->_messagesFirstLevelTagName . ' class="messages">';
                    }
                    $html .= '<' . $this->_messagesSecondLevelTagName . ' class="' . $type . '-msg">';
                    $html .= '<' . $this->_messagesFirstLevelTagName . '>';

                    foreach ( $messages as $message ) {
                        $html.= '<' . $this->_messagesSecondLevelTagName . '>';
                        $html.= '<' . $this->_messagesContentWrapperTagName . '>';
                        $html.= ($this->_escapeMessageFlag) ? $this->htmlEscape($message->getText()) : $message->getText();
                        $html.= '</' . $this->_messagesContentWrapperTagName . '>';
                        $html.= '</' . $this->_messagesSecondLevelTagName . '>';
                    }
                    $html .= '</' . $this->_messagesFirstLevelTagName . '>';
                    $html .= '</' . $this->_messagesSecondLevelTagName . '>';
                }
            }
            if ( $html) {
                $html .= '</' . $this->_messagesFirstLevelTagName . '>';
                Mage::getSingleton('core/cookie')->delete(OpsWay_Varnishgento_Model_Ajaxify_Processor::MESSAGES_COOKIE);
            }

            if (Mage::helper('opsway_varnishgento/ajaxify')->isActive() && !Mage::helper('opsway_varnishgento/ajaxify')->isAjaxifyRequest()){
                if ($this->getNameInLayout() != 'global_messages'){
                    $html = '<div id="ajaxify-messages-block">'.$html.'</div>';
                }
            }

            return $html;

    }
}