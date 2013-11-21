<?php
/**
 * Varnish socket
 *
 * @category OpsWay
 * @package  OpsWay_Varnishgento
 * @author   Ivan Shcherbakov <ivan.shcherbakov@smile.fr>
 * @author   Oleksandr Zirka <olzir@smile.fr>
 * @author   Alexandr Vronskiy <alvro@opsway.com>
 */
class OpsWay_Varnishgento_Model_Connector_Socket
{
    /**
     * Response code for successful request
     */
    const RESPONSE_CODE_OK = 200;

    /**
     * Response code to indicate that an authentication is required
     */
    const RESPONSE_CODE_AUTH_REQUIRED = 107;

    /**
     * Socket handle
     * @var resource
     */
    protected $_handler = null;

    /**
     * Socket host
     * @var string
     */
    protected $_host = null;

    /**
     * Varnish secret
     * @var string
     */
    protected $_secret = null;

    /**
     * Debug mode
     * @var bool
     */
    protected $_debug = false;

    /**
     * Varnish command for purging cache
     * @var null | string
     */
    protected $_purge_command = null;

    /**
     * List of commands for varnish purging
     * @var array
     */
    protected $_purge_commands = array(2 => 'purge', 3 => 'ban');

    /**
     * Create and object and init a connection
     * @param array $server
     * @throws OpsWay_Varnishgento_Model_Connector_Exception
     */
    public function __construct($server)
    {
        if (!isset($server['host']) || !isset($server['port'])) {
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Parameters are invalid')
            );
        }
        $host = $server['host'];
        $port = $server['port'];
        $this->_handler = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->_handler === false) {
            $this->_logSocketErr($this->_handler, $host, 'socket_create');
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Could not create a socket')
            );
        }
        $result = socket_connect($this->_handler, $host, $port);
        if ($result === false) {
            $this->_logSocketErr($this->_handler, $host, 'socket_connect');
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Could not connect to %s:%s', $host, $port)
            );
        }
        //set timeout 5sec
        socket_set_option($this->_handler,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>5, "usec"=>0));

        if (!isset($server['check_resp']) || $server['check_resp']) {
            $response = $this->_popLastResponse();
            if ($response->code != self::RESPONSE_CODE_OK) {
                if ($response->code == self::RESPONSE_CODE_AUTH_REQUIRED) {
                    if (!isset($server['secret'])) {
                        throw new OpsWay_Varnishgento_Model_Connector_Exception(
                            Mage::helper('opsway_varnishgento')->__(
                                'Authentification required at %s:%s. Secret is not set',
                                $host,
                                $port
                            )
                        );
                    }
                    $this->_secret = $server['secret'];
                    $parts = explode("\n", $response->body);
                    $challenge = $parts[0];
                    $this->_authenticate($challenge);
                    $authetificationResponse = $this->_popLastResponse();
                    if ($authetificationResponse->code != self::RESPONSE_CODE_OK) {
                        throw new OpsWay_Varnishgento_Model_Connector_Exception(
                            Mage::helper('opsway_varnishgento')->__(
                                'Authentification failed at %s:%s',
                                $host,
                                $port
                            )
                        );
                    }
                } else {
                    throw new OpsWay_Varnishgento_Model_Connector_Exception(
                        Mage::helper('opsway_varnishgento')->__(
                            'Could not retrive an information at %s:%s. Unknown response code %s',
                            $host,
                            $port,
                            $response->code
                        )
                    );
                }
            }
        }
        $this->_host = $host;
        $this->_purge_command = $this->_purge_commands[Mage::getStoreConfig('opsway_varnishgento/general/version')];
    }

    /**
     * Set debug mode
     * @param bool $value
     */
    public function setDebug($value)
    {
        $this->_debug = (bool)$value;
    }

    /**
     * Put a log message
     * @param string $message
     * @param int $level
     */
    protected function _log($message, $level = null)
    {
        if (is_null($level)) {
            $level = Zend_Log::DEBUG;
        }
        if (!$this->_debug && in_array($level, array(Zend_Log::DEBUG, Zend_Log::INFO))) {
            return;
        }
        Mage::log($message, $level, 'varnish.log');
    }

    /**
     * Put a log for a socket error
     * @param resource $handler
     * @param string $host
     * @param string $function
     */
    protected function _logSocketErr($handler, $host, $function)
    {
        $errno = socket_last_error($handler);
        $message = '['.$host.']['.$function.']['.$errno.'] '.socket_strerror($errno);
        $this->_log($message, Zend_Log::ERR);
    }

    /**
     * Put a command
     * @param string $command
     * @return stdClass response
     * @throws OpsWay_Varnishgento_Model_Connector_Exception
     */
    protected function _put($command, $checkResponse = false)
    {
        $this->_log('['.$this->_host.']'.$command, Zend_Log::INFO);
        $command .= "\n";
        if ((socket_write($this->_handler, $command, strlen($command))) === false) {
            $this->_logSocketErr($this->_handler, $this->_host, 'socket_write');
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Unable to send a command to %s', $this->_host)
            );
        }
        if ($checkResponse) {
            $response = $this->_popLastResponse();
            if ($response->code != self::RESPONSE_CODE_OK) {
                $message = Mage::helper('opsway_varnishgento')->__(
                    'Command "%s" failed at %s',
                    trim($command, "\n"),
                    $this->_host
                );
                $this->_log('['.$this->_host.']'.$message."\n".'Reason: '.trim($response->body, "\n"), Zend_Log::ERR);
                throw new OpsWay_Varnishgento_Model_Connector_Exception($message);
            }
        }
        $this->_log('OK', Zend_Log::INFO);
    }

    /**
     * Pop the last socket response
     * @return stdClass
     * @throws OpsWay_Varnishgento_Model_Connector_Exception
     */
    protected function _popLastResponse()
    {
        $rawResponse = socket_read($this->_handler, 12 + 1);
        if ($rawResponse === false) {
            $this->_logSocketErr($this->_handler, $this->_host, 'socket_read');
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Error during result processing on %s', $this->_host)
            );
        }
        $params = explode(' ', trim($rawResponse));
        $rawResponse = socket_read($this->_handler, $params[1] + 1);
        if ($rawResponse === false) {
            $this->_logSocketErr($this->_handler, $this->_host, 'socket_read');
            throw new OpsWay_Varnishgento_Model_Connector_Exception(
                Mage::helper('opsway_varnishgento')->__('Error during result processing on %s', $this->_host)
            );
        }
        $response = new stdClass();
        $response->code = $params[0];
        $response->body = $rawResponse;
        return $response;
    }

    /**
     * Purge pages by specific response header
     * @param string $header
     * @param string $tag
     */
    public function purgeByResponseHeader($header, $tag)
    {
        $this->_put($this->_purge_command.' obj.http.'.$header.' ~ '.$tag, true);
    }

    /**
     * Send authentification request
     * @param string $challenge
     */
    protected function _authenticate($challenge)
    {
        $key = $challenge."\n".$this->_secret."\n".$challenge."\n";
        $key = hash('sha256', $key);
        $this->_put('auth '.$key);
    }

    /**
     * Purge pages by specific url
     * @param string $header
     * @param string $tag
     */
    public function purgeByUrl($url)
    {
        $this->_put($this->_purge_command.'.url ^'.$url.'$', true);
    }

    /**
     * Purge all cache in memcached
     */
    public function purgeMemcached()
    {
        $this->_put("flush_all", false);
    }

}
