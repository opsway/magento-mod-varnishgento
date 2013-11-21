<?php
/**
 * Varnish cache connector
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */
class OpsWay_Varnishgento_Model_Connector
{
    /**
     * Lock identifier
     */
    const LOCK_NAME = 'VARNISHGENTO_LOCK';

    /**
     * Sockets list
     * @var array
     */
    protected $_sockets = null;

    /**
     * Init connector with servers list
     * @param array $servers
     * @param bool $debug
     * @param bool $checkResponse
     * @return bool
     */
    public function init($servers, $debug = null, $checkResponse = true)
    {
        try {
            $sockets = array();
            foreach ($servers as $server) {
                $server['check_resp'] = $checkResponse;
                $socket = Mage::getModel('opsway_varnishgento/connector_socket', $server);
                if (!is_null($debug)) {
                    $socket->setDebug($debug);
                }
                $sockets[] = $socket;
            }
        } catch (OpsWay_Varnishgento_Model_Connector_Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR);
            return false;
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
        $this->_sockets = $sockets;
        return true;
    }

    /**
     * Check is connector is initialized
     * @return bool
     */
    public function isInited()
    {
        return !is_null($this->_sockets) && (count($this->_sockets) > 0);
    }

    /**
     * Purge pages by specific response header
     * @param string $header
     * @param string $tag
     */
    public function purgeByResponseHeader($header, $tag)
    {
        if (!$this->isInited()) {
            return false;
        }
        foreach ($this->_sockets as $socket) {
            $socket->purgeByResponseHeader($header, $tag);
        }
    }

    /**
     * Check if connector is locked
     * @return bool
     */
    public function isLocked()
    {
        return (Mage::app()->loadCache(self::LOCK_NAME) !== false);
    }

    /**
     * Lock connector
     */
    public function lock()
    {
        Mage::app()->saveCache('LOCK', self::LOCK_NAME, array());
    }

    /**
     * Unlock connector
     */
    public function unlock()
    {
        Mage::app()->removeCache(self::LOCK_NAME);
    }

    /**
     * Send to socket url for purging
     * @param $url string
     *
     * @throws Exception
     */
    public function purgeByUrl($url)
    {
        if (!$this->isInited()) {
            throw new Exception('Socket not Initialized');
        }

        foreach ($this->_sockets as $socket) {
            $socket->purgeByUrl($url);
        }
    }

    /**
     * Flush cache memcached
     * @return bool | null
     */
    public function flushMemcached()
    {
        if (!$this->isInited()) {
            return false;
        }
        foreach ($this->_sockets as $socket) {
            $socket->purgeMemcached();
        }
    }
}
