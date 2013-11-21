<?php
/**
 * Block for varnish management section on cache admin page
 *
 * @category Opsway
 * @package  Opsway_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */


class Opsway_Varnishgento_Block_Adminhtml_Cache extends Mage_Adminhtml_Block_Cache
{
    /**
     * Class constructor
     */
    public function __construct()
    {
    	parent::__construct();
        
    	$this->_addButton('flush_varnish', array(
            'label'     => Mage::helper('core')->__('Flush Home Page Varnish cache'),
            'onclick'   => 'setLocation(\'' . $this->getFlushVarnishUrl() .'\')',
	    	'class'     => 'delete',
        ));
    }

    /**
     * Get url for clean varnish cache
     */
    public function getFlushVarnishUrl()
    {
        //@todo get Home page from base URL
        return $this->getUrl('*/*/flushVarnish'.'?purge_url='.urlencode("/"));
    }

}
